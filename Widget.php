<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 找回密码核心逻辑类
 *
 * @package Passport
 * @copyright Copyright (c) 2025 GARFIELDTOM & 小否先生
 * @version 0.1.2
 * @license GNU General Public License 2.0
 */

// 引入PHPMailer库
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Typecho\Common;
use Typecho\Db\Exception as DbException;
use Typecho\Widget;
use Utils\PasswordHash;
use Widget\ActionInterface;
use Widget\Notice;

class Passport_Widget extends Widget implements ActionInterface
{
    private $options;
    private $config;
    private $notice;
    private $db;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->notice = Notice::alloc();
        $this->options = Widget::widget('Widget_Options');
        $this->config = $this->options->plugin('Passport');
        $this->db = Typecho_Db::get();
    }

    /**
     * [核心] 插件路由动作入口
     *
     * @return void
     * @throws DbException
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();

        // 处理 IP 解封请求
        if ($this->request->isPost() && !empty($this->request->unblock_ip)) {
            $this->handleUnblockIp($this->request->unblock_ip);
        }

        // 其他 Action 可以在这里扩展
        $this->response->goBack();
    }

    /**
     * 处理 IP 解封请求 (移至 Widget 层)
     *
     * @param string $ip_to_unblock 待解封的IP地址
     * @return void
     * @throws DbException
     */
    private function handleUnblockIp(string $ip_to_unblock)
    {
        if (!$this->isValidIp($ip_to_unblock)) {
            $this->notice->set(_t('IP地址格式不正确。'), 'error');
            return;
        }

        $update = $this->db->update($this->db->getPrefix() . 'passport_fails')
                     ->rows(['locked_until' => 0])
                     ->where('ip = ?', $ip_to_unblock);
        $this->db->query($update);

        $this->notice->set(_t('IP地址 %s 已成功解封。', htmlspecialchars($ip_to_unblock)), 'success');
    }

    /**
     * [新增] 内部通知处理方法
     * - 统一处理所有通知，并能根据特定消息格式生成倒计时。
     * @param string|array $message 消息内容
     * @param string $type          消息类型 (error, success, notice)
     */
    private function _setNotice($message, $type)
    {
        // 若消息是数组，尝试转为字符串
        if (is_array($message)) {
            $message = reset($message);
            if (!is_string($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }
        }

        // 检查是否为锁定的特殊消息格式 'LOCKED|<seconds>'
        if (is_string($message) && strpos($message, 'LOCKED|') === 0) {
            list(, $seconds) = explode('|', $message);
            $seconds = intval($seconds);

            if ($seconds > 0) {
                $minutes = floor($seconds / 60);
                $sec_part = $seconds % 60;

                $message = sprintf(
                    _t('您的请求过于频繁，已被暂时限制。请在 <span id="countdown-timer-min">%d</span> 分钟 <span id="countdown-timer-sec">%02d</span> 秒后重试。'),
                    $minutes, $sec_part
                );
                $message .= "<script>
                    (function() {
                        var seconds = {$seconds};
                        var minElement = document.getElementById('countdown-timer-min');
                        var secElement = document.getElementById('countdown-timer-sec');
                        if (!minElement || !secElement) return;

                        var interval = setInterval(function() {
                            seconds--;
                            if (seconds < 0) {
                                clearInterval(interval);
                                minElement.parentElement.innerHTML = '现在可以刷新页面重试了。';
                                return;
                            }
                            var minutes = Math.floor(seconds / 60);
                            var remainingSeconds = seconds % 60;
                            minElement.textContent = minutes;
                            secElement.textContent = remainingSeconds < 10 ? '0' + remainingSeconds : remainingSeconds;
                        }, 1000);
                    })();
                </script>";
            } else {
                $message = _t('您的请求过于频繁，请稍后重试。');
            }
        }

        $this->notice->set($message, $type);
    }


    /**
     * [新增] 递减指定IP的尝试次数
     * - 用于处理非用户错误的失败情况（如邮件服务器配置错误）。
     * @throws DbException
     */
    private function decrementAttemptCounter()
    {
        if (empty($this->config->enableRateLimit) || !$this->config->enableRateLimit) {
            return;
        }
        $ip = $this->request->getIp();
        $failTable = $this->db->getPrefix() . 'passport_fails';
        $log = $this->db->fetchRow($this->db->select()->from($failTable)->where('ip = ?', $ip));

        if ($log && $log['attempts'] > 0) {
            $this->db->query($this->db->update($failTable)
                ->rows(['attempts' => $log['attempts'] - 1])
                ->where('ip = ?', $ip));
        }
    }


    public function execute() {}

    /**
     * [核心安全功能] 处理请求速率限制与IP封禁
     * @throws Typecho_Exception 当IP被封禁时抛出异常
     */
    private function handleRateLimiting()
    {
        // [配置] 从插件设置读取是否启用此功能
        if (empty($this->config->enableRateLimit) || !$this->config->enableRateLimit) {
            return;
        }

        $ip = $this->request->getIp();
        $now = time();
        $failTable = $this->db->getPrefix() . 'passport_fails';

        // 1. 查询当前IP的记录
        $log = $this->db->fetchRow($this->db->select()->from($failTable)->where('ip = ?', $ip));

        if ($log) {
            // 2. 检查IP是否仍处于封禁期
            if ($log['locked_until'] > $now) {
                $remaining_time = $log['locked_until'] - $now;
                // [变更] 抛出带倒计时标记的异常
                throw new Typecho_Exception('LOCKED|' . $remaining_time);
            }

            // 3. 更新尝试次数
            // [策略] 如果距离上次尝试超过10分钟，则重置计数器
            $attempts = (($now - $log['last_attempt']) > 600) ? 1 : $log['attempts'] + 1;

            $updateData = [
                'attempts' => $attempts,
                'last_attempt' => $now
            ];

            // 4. 判断是否达到封禁阈值 (每间隔20次锁定一次)
            if ($attempts % 20 == 0) {
                // [变更] 封禁时长恒定为5分钟 (300秒)
                $lockDuration = 300;
                $updateData['locked_until'] = $now + $lockDuration;

                $this->db->query($this->db->update($failTable)->rows($updateData)->where('ip = ?', $ip));
                // [变更] 抛出带倒计时标记的异常
                throw new Typecho_Exception('LOCKED|' . $lockDuration);

            } else {
                // 未达到阈值，仅更新尝试次数和时间
                $this->db->query($this->db->update($failTable)->rows($updateData)->where('ip = ?', $ip));
            }

        } else {
            // 5. 如果是首次尝试，则插入新记录
            $this->db->query($this->db->insert($failTable)->rows([
                'ip' => $ip,
                'attempts' => 1,
                'last_attempt' => $now,
                'locked_until' => 0
            ]));
        }
    }

    /**
     * 忘记密码处理页面
     */
    public function doForgot()
    {
        // [解耦] 加载独立的页面模板
        require_once 'template/forgot.php';

        // [优化] 清理过期的重置令牌
        $this->cleanTokens();

        if ($this->request->isPost()) {
            try {
                // [安全] 步骤1: 检查IP是否被封禁
                $this->handleRateLimiting();

                // 步骤2: 表单基础验证
                if ($error = $this->forgotForm()->validate()) {
                    $this->_setNotice($error, 'error');
                    return;
                }

                // 步骤3: 人机验证 (CAPTCHA)
                if (!$this->verifyCaptcha()) {
                    $this->_setNotice(_t('人机身份验证失败，请重试。'), 'error');
                    return;
                }

                // 步骤4: 处理用户数据和发送邮件
                $user = $this->db->fetchRow($this->db->select()->from('table.users')->where('mail = ?', $this->request->mail));

                $mailSentSuccessfully = true;
                // [安全] 防范用户枚举漏洞
                // 无论邮箱是否存在，都假装已发送邮件。实际只在用户存在时发送。
                if (!empty($user)) {
                    $token = Common::randString(64);
                    $createdAt = $this->options->gmtTime;
                    $signature = $this->generateSignature($token, $user['uid'], $createdAt);

                    $this->db->query($this->db->insert('table.password_reset_tokens')->rows([
                        'token' => $token, 'uid' => $user['uid'], 'created_at' => $createdAt, 'used' => 0
                    ]));

                    $resetLink = Common::url('/passport/reset?token=' . urlencode($token) . '&signature=' . urlencode($signature), $this->options->index);

                    if (!$this->sendResetEmail($user, $resetLink)) {
                         error_log('Passport: 邮件发送失败，目标地址: ' . $user['mail']);
                         $mailSentSuccessfully = false;
                    }
                }

                // [变更] 根据邮件发送结果显示不同信息
                if ($mailSentSuccessfully) {
                    $this->_setNotice(_t('如果您的邮箱地址在我们系统中存在，一封包含重置链接的邮件将会发送给您。请检查您的收件箱。'), 'success');
                } else {
                    // [新增] 邮件发送失败时，显示对用户无害的通用提示，且不计入错误尝试
                    $this->decrementAttemptCounter();
                    $this->_setNotice(_t('邮件发送服务暂时出现问题，请稍后重试或联系管理员。'), 'error');
                }


            } catch (Typecho_Exception $e) {
                // [变更] 使用新的通知方法处理异常
                $this->_setNotice($e->getMessage(), 'error');
                return;
            }
        }
    }

    /**
     * 重置密码处理页面
     */
    public function doReset()
    {
        require_once 'template/reset.php';
        $this->cleanTokens();

        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;
        $signature = $this->request->filter('strip_tags', 'trim', 'xss')->signature;

        if (empty($token)) {
            $this->_setNotice(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $tokenRecord = $this->db->fetchRow($this->db->select()->from('table.password_reset_tokens')
            ->where('token = ? AND used = ?', urldecode($token), 0));

        // 验证令牌有效性、是否过期
        if (empty($tokenRecord) || ($this->options->gmtTime - $tokenRecord['created_at']) > 3600) {
            $this->_setNotice(_t('该链接已失效或已使用，请重新获取。'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // [安全] 验证HMAC签名
        if (!$this->verifySignature($token, $tokenRecord['uid'], $tokenRecord['created_at'], urldecode($signature))) {
            $this->_setNotice(_t('令牌签名验证失败，链接无效。'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        if ($this->request->isPost()) {
             try {
                // [安全] 步骤1: 检查IP是否被封禁
                $this->handleRateLimiting();

                // 步骤2: 表单基础验证
                if ($error = $this->resetForm()->validate()) {
                    $this->_setNotice($error, 'error');
                    return;
                }

                // [安全] 步骤3: 新增密码复杂度验证
                if (($complexityError = $this->validatePasswordComplexity($this->request->password)) !== true) {
                    $this->_setNotice($complexityError, 'error');
                    return;
                }

                // 步骤4: 人机验证 (CAPTCHA)
                if (!$this->verifyCaptcha()) {
                    $this->_setNotice(_t('人机身份验证失败，请重试。'), 'error');
                    return;
                }

                // 步骤5: 更新密码
                $hasher = new PasswordHash(8, true);
                $password = $hasher->hashPassword($this->request->password);
                $this->db->query($this->db->update('table.users')->rows(['password' => $password])->where('uid = ?', $tokenRecord['uid']));
                $this->db->query($this->db->update('table.password_reset_tokens')->rows(['used' => 1])->where('token = ?', urldecode($token)));

                $this->_setNotice(_t('密码重置成功，请使用新密码登录。'), 'success');
                $this->response->redirect($this->options->loginUrl);

            } catch (Typecho_Exception $e) {
                // [变更] 使用新的通知方法处理异常
                $this->_setNotice($e->getMessage(), 'error');
                return;
            }
        }
    }

    /**
     * [安全] 验证新密码的复杂度
     * @param string $password 待验证的密码
     * @return bool|string 验证通过返回 true，否则返回错误信息
     */
    private function validatePasswordComplexity(string $password): bool|string
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = _t('密码长度不能少于8位');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = _t('密码必须包含至少一个大写字母');
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = _t('密码必须包含至少一个小写字母');
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = _t('密码必须包含至少一个数字');
        }
        if (!preg_match('/[\W_]/', $password)) { // \W 匹配非字母数字字符
            $errors[] = _t('密码必须包含至少一个特殊字符');
        }

        if (empty($errors)) {
            return true;
        }
        return implode('；', $errors) . '。';
    }

    /**
     * [重构] 统一的人机验证处理函数
     * @return bool 验证是否通过
     */
    private function verifyCaptcha(): bool
    {
        $captchaType = $this->config->captchaType;
        if ($captchaType === 'none') return true;

        $captchaVerified = false;
        try {
            switch ($captchaType) {
                case 'recaptcha':
                    if (empty($this->config->secretkeyRecaptcha)) throw new Exception(_t('reCAPTCHA配置不完整'));
                    $captchaVerified = $this->verifyRecaptcha($this->request->get('g-recaptcha-response'), $this->config->secretkeyRecaptcha);
                    break;
                case 'hcaptcha':
                    if (empty($this->config->secretkeyHcaptcha)) throw new Exception(_t('hCaptcha配置不完整'));
                    $captchaVerified = $this->verifyHcaptcha($this->request->get('h-captcha-response'), $this->config->secretkeyHcaptcha);
                    break;
                case 'geetest':
                    if (empty($this->config->captchaKeyGeetest)) throw new Exception(_t('Geetest配置不完整'));
                    $captchaVerified = $this->verifyGeetest(
                        $this->request->get('lot_number'), $this->request->get('captcha_output'),
                        $this->request->get('pass_token'), $this->request->get('gen_time')
                    );
                    break;
            }
        } catch (Exception $e) {
            $this->_setNotice($e->getMessage() . _t('，请联系管理员。'), 'error');
            return false;
        }
        return $captchaVerified;
    }

    /**
     * [安全] 生成 HMAC-SHA256 签名
     * @param string $token
     * @param int $uid
     * @param int $createdAt
     * @return string
     */
    private function generateSignature(string $token, int $uid, int $createdAt): string
    {
        // 如果未配置密钥，直接返回空字符串，让验证失败
        if (empty($this->config->secretKey)) {
            error_log('Passport: HMAC secret key is not configured. Signature generation failed.');
            return '';
        }
        return hash_hmac('sha256', $token . '.' . $uid . '.' . $createdAt, $this->config->secretKey);
    }

    /**
     * [安全] 验证 HMAC-SHA256 签名
     * @param string $token
     * @param int $uid
     * @param int $createdAt
     * @param string $signature
     * @return bool
     */
    private function verifySignature(string $token, int $uid, int $createdAt, string $signature): bool
    {
        // [安全] 如果未配置密钥，验证必须失败 (Fail-Closed)
        if (empty($this->config->secretKey)) {
            error_log('Passport: HMAC secret key is not configured. Signature verification failed.');
            return false;
        }
        $expectedSignature = $this->generateSignature($token, $uid, $createdAt);
        // 使用 hash_equals 防止时序攻击
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 清理过期或已使用的令牌
     * @throws DbException
     */
    private function cleanTokens()
    {
        $expireTime = $this->options->gmtTime - 3600; // 1小时前
        $this->db->query($this->db->delete('table.password_reset_tokens')
            ->where('created_at < ? OR used = ?', $expireTime, 1));
    }

    /**
     * 校验人机验证的系统状态 (reCAPTCHA)
     * @param string $response
     * @param string $secretKey
     * @return bool
     */
    private function verifyRecaptcha(string $response, string $secretKey): bool
    {
        return $this->verifyCaptchaService('https://www.recaptcha.net/recaptcha/api/siteverify', $response, $secretKey);
    }

    /**
     * 校验人机验证的系统状态 (hCaptcha)
     * @param string $response
     * @param string $secretKey
     * @return bool
     */
    private function verifyHcaptcha(string $response, string $secretKey): bool
    {
        return $this->verifyCaptchaService('https://hcaptcha.com/siteverify', $response, $secretKey);
    }

    /**
     * 通用 CAPTCHA 服务验证
     * @param string $url
     * @param string $response
     * @param string $secretKey
     * @return bool
     */
    private function verifyCaptchaService(string $url, string $response, string $secretKey): bool
    {
        if (empty($response)) return false;
        $result_json = $this->send_post($url, ['secret' => $secretKey, 'response' => $response]);
        if ($result_json === false) {
            error_log('Passport: Failed to connect to CAPTCHA service at ' . $url);
            return false;
        }
        $result = json_decode($result_json, true);
        return isset($result['success']) && $result['success'];
    }

    /**
     * 校验人机验证的系统状态 (Geetest)
     * @param string $lot_number
     * @param string $captcha_output
     * @param string $pass_token
     * @param string $gen_time
     * @return bool
     */
    private function verifyGeetest(string $lot_number, string $captcha_output, string $pass_token, string $gen_time): bool
    {
        if (empty($lot_number) || empty($captcha_output) || empty($pass_token) || empty($gen_time)) {
            return false;
        }
        $captcha_id = $this->config->captchaIdGeetest;
        $captcha_key = $this->config->captchaKeyGeetest;
        $sign_token = hash_hmac('sha256', $lot_number, $captcha_key);
        $post_data = [
            "lot_number" => $lot_number, "captcha_output" => $captcha_output,
            "pass_token" => $pass_token, "gen_time" => $gen_time, "sign_token" => $sign_token,
        ];
        $url = 'http://gcaptcha4.geetest.com/validate?captcha_id=' . $captcha_id;
        $geetest_json_result = $this->send_post($url, $post_data);
        if ($geetest_json_result === false) {
            error_log('Passport: request geetest api fail');
            return false;
        }
        $geetest_result = json_decode($geetest_json_result, true);
        return isset($geetest_result['status']) && $geetest_result['status'] === 'success' &&
               isset($geetest_result['result']) && $geetest_result['result'] === 'success';
    }

    /**
     * 发送重置密码的邮件
     * @param array $user
     * @param string $url
     * @return bool
     */
    private function sendResetEmail(array $user, string $url): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->SMTPSecure = $this->config->secure === 'none' ? '' : $this->config->secure;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->Port = (int)$this->config->port;

            $mail->setFrom($this->config->username, $this->options->title);
            $mail->addAddress($user['mail'], $user['name']);

            $emailBody = str_replace(
                ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
                [htmlspecialchars($user['name']), htmlspecialchars(Helper::options()->title), date('Y-m-d H:i:s'), htmlspecialchars($url)],
                $this->config->emailTemplate
            );

            $mail->isHTML(true);
            $mail->Subject = Helper::options()->title . ' - 密码重置';
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags($emailBody);

            return $mail->send();
        } catch (Exception $e) {
            error_log('Passport: PHPMailer Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 申请重置密码表格
     * @return Typecho_Widget_Helper_Form
     */
    public function forgotForm(): Typecho_Widget_Helper_Form
    {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);
        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, NULL, _t('邮箱'), _t('请输入您忘记密码的账号所对应的邮箱地址'));
        $form->addInput($mail);
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'mail');
        $form->addInput($do);
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('提交'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule('email', _t('电子邮箱格式错误'));
        return $form;
    }

    /**
     * 设置新的密码表格
     * @return Typecho_Widget_Helper_Form
     */
    public function resetForm(): Typecho_Widget_Helper_Form
    {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('新密码'), _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $password->input->setAttribute('class', 'w-100');
        $form->addInput($password);
        $confirm = new Typecho_Widget_Helper_Form_Element_Password('confirm', NULL, NULL, _t('密码确认'), _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirm->input->setAttribute('class', 'w-100');
        $form->addInput($confirm);
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'password');
        $form->addInput($do);
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        $password->addRule('required', _t('必须填写密码'));
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');
        return $form;
    }

    /**
     * 向人机验证平台发送 POST 请求的方法
     * @param string $url
     * @param array $post_data
     * @return string|false
     */
    private function send_post(string $url, array $post_data): string|false
    {
        $postdata = http_build_query($post_data);
        $options = ['http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata,
            'timeout' => 20
        ]];
        $context = stream_context_create($options);
        // Suppress warning if connection fails
        return @file_get_contents($url, false, $context);
    }

    /**
     * 验证IP地址的有效性
     *
     * @param string $ip IP地址
     * @return bool 是否有效
     */
    private static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
    }
}