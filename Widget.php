<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 找回密码类
 *
 * @package Passport
 * @copyright Copyright (c) 2025 GARFIELDTOM & 小否先生
 * @link https://github.com/typecho-fans/plugins/tree/master/Passport
 * @link https://github.com/little-gt/PLUGION-Passport
 * @license GNU General Public License 2.0
 */

// Ensure PHPMailer classes are available
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Passport_Widget extends Typecho_Widget
{
    private $options;
    private $config;
    private $notice;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->notice = parent::widget('Widget_Notice');
        $this->options = parent::widget('Widget_Options');
        $this->config = $this->options->plugin('Passport');

        // 创建令牌表
        $this->createTokenTable();
    }

    public function execute() {}

    /**
     * 创建密码重置令牌表
     */
    private function createTokenTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'password_reset_tokens';

        // 检查表是否已存在
        $tables = $db->fetchAll($db->query("SHOW TABLES LIKE '{$table}'"));
        if (empty($tables)) {
            $query = "CREATE TABLE `{$table}` (
                `token` VARCHAR(64) NOT NULL,
                `uid` INT(10) UNSIGNED NOT NULL,
                `created_at` INT(10) UNSIGNED NOT NULL,
                `used` TINYINT(1) DEFAULT 0,
                PRIMARY KEY (`token`),
                INDEX `uid` (`uid`),
                INDEX `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->query($query);
            error_log('Passport: Created password_reset_tokens table');
        }
    }

    /**
     * 清理过期或已使用的令牌
     */
    private function cleanTokens()
    {
        $db = Typecho_Db::get();
        $expireTime = $this->options->gmtTime - 3600; // 1 小时
        $db->query($db->delete('table.password_reset_tokens')
            ->where('created_at < ? OR used = ?', $expireTime, 1));
        error_log('Passport: Cleaned expired or used tokens before: ' . $expireTime);
    }

    /**
     * 生成 HMAC-SHA256 签名
     */
    private function generateSignature($token, $uid, $createdAt)
    {
        if (empty($this->config->secretKey)) {
            error_log('Passport: HMAC secret key is not configured');
            return '';
        }
        $data = $token . '.' . $uid . '.' . $createdAt;
        return hash_hmac('sha256', $data, $this->config->secretKey);
    }

    /**
     * 验证 HMAC-SHA256 签名
     */
    private function verifySignature($token, $uid, $createdAt, $signature)
    {
        if (empty($this->config->secretKey)) {
            error_log('Passport: HMAC secret key is not configured, skipping signature verification');
            return true; // 如果未配置密钥，跳过验证（不推荐）
        }
        $expectedSignature = $this->generateSignature($token, $uid, $createdAt);
        return hash_equals($expectedSignature, $signature);
    }

    public function doForgot()
    {
        require_once 'template/forgot.php';

        // 清理过期或已使用令牌
        $this->cleanTokens();

        if ($this->request->isPost()) {
            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                return;
            }

            $captchaType = $this->config->captchaType;
            $captchaVerified = false;

            switch ($captchaType) {
                case 'recaptcha':
                    if (empty($this->config->sitekeyRecaptcha) || empty($this->config->secretkeyRecaptcha)) {
                        $this->notice->set(_t('reCAPTCHA配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyRecaptcha($this->request->get('g-recaptcha-response'), $this->config->secretkeyRecaptcha);
                    break;
                case 'hcaptcha':
                    if (empty($this->config->sitekeyHcaptcha) || empty($this->config->secretkeyHcaptcha)) {
                        $this->notice->set(_t('hCaptcha配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyHcaptcha($this->request->get('h-captcha-response'), $this->config->secretkeyHcaptcha);
                    break;
                case 'geetest':
                    if (empty($this->config->captchaIdGeetest) || empty($this->config->captchaKeyGeetest)) {
                        $this->notice->set(_t('Geetest配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyGeetest(
                        $this->request->get('lot_number'),
                        $this->request->get('captcha_output'),
                        $this->request->get('pass_token'),
                        $this->request->get('gen_time')
                    );
                    break;
                case 'none':
                default:
                    $captchaVerified = true;
                    break;
            }

            if (!$captchaVerified) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                return;
            }

            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                return;
            }

            // 生成唯一令牌
            $token = Typecho_Common::randString(64);
            $createdAt = $this->options->gmtTime;

            // 生成签名
            $signature = $this->generateSignature($token, $user['uid'], $createdAt);

            // 存储令牌到数据库
            $db->query($db->insert('table.password_reset_tokens')
                ->rows([
                    'token' => $token,
                    'uid' => $user['uid'],
                    'created_at' => $createdAt,
                    'used' => 0
                ]));

            // 构造重置链接，包含签名
            $url = Typecho_Common::url('/passport/reset?token=' . urlencode($token) . '&signature=' . urlencode($signature), $this->options->index);

            // 记录生成的令牌
            error_log('Passport: Generated token: ' . $token . ', signature: ' . $signature . ' for user: ' . $user['mail']);

            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收'), 'success');
            } else {
                $this->notice->set(_t('邮件发送失败, 请稍后重试'), 'error');
            }
        }
    }

    public function doReset()
    {
        require_once 'template/reset.php';

        // 清理过期或已使用令牌
        $this->cleanTokens();

        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;
        $signature = $this->request->filter('strip_tags', 'trim', 'xss')->signature;

        if (empty($token)) {
            $this->notice->set(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 记录接收的令牌和签名
        error_log('Passport: Received token: ' . $token . ', signature: ' . ($signature ?? 'none'));

        $db = Typecho_Db::get();

        // 查询令牌
        $tokenRecord = $db->fetchRow($db->select()->from('table.password_reset_tokens')
            ->where('token = ?', urldecode($token))
            ->where('used = ?', 0));

        if (empty($tokenRecord)) {
            error_log('Passport: Token not found or already used: ' . $token);
            $this->notice->set(_t('该链接已失效或已使用, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 验证令牌是否过期（1 小时）
        if (($this->options->gmtTime - $tokenRecord['created_at']) > 3600) {
            error_log('Passport: Token expired - Created: ' . $tokenRecord['created_at'] . ', Current: ' . $this->options->gmtTime);
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 验证签名
        if (!$this->verifySignature($token, $tokenRecord['uid'], $tokenRecord['created_at'], urldecode($signature))) {
            error_log('Passport: Invalid signature for token: ' . $token);
            $this->notice->set(_t('令牌验证失败, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 查询用户
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $tokenRecord['uid']));

        if (empty($user)) {
            error_log('Passport: User not found for UID: ' . $tokenRecord['uid']);
            $this->notice->set(_t('用户不存在或链接已失效'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        if ($this->request->isPost()) {
            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                return;
            }

            $captchaType = $this->config->captchaType;
            $captchaVerified = false;

            switch ($captchaType) {
                case 'recaptcha':
                    if (empty($this->config->sitekeyRecaptcha) || empty($this->config->secretkeyRecaptcha)) {
                        $this->notice->set(_t('reCAPTCHA配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyRecaptcha($this->request->get('g-recaptcha-response'), $this->config->secretkeyRecaptcha);
                    break;
                case 'hcaptcha':
                    if (empty($this->config->sitekeyHcaptcha) || empty($this->config->secretkeyHcaptcha)) {
                        $this->notice->set(_t('hCaptcha配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyHcaptcha($this->request->get('h-captcha-response'), $this->config->secretkeyHcaptcha);
                    break;
                case 'geetest':
                    if (empty($this->config->captchaIdGeetest) || empty($this->config->captchaKeyGeetest)) {
                        $this->notice->set(_t('Geetest配置不完整, 请联系管理员。'), 'error'); return;
                    }
                    $captchaVerified = $this->verifyGeetest(
                        $this->request->get('lot_number'),
                        $this->request->get('captcha_output'),
                        $this->request->get('pass_token'),
                        $this->request->get('gen_time')
                    );
                    break;
                case 'none':
                default:
                    $captchaVerified = true;
                    break;
            }

            if (!$captchaVerified) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                return;
            }

            $hasher = new PasswordHash(8, true);
            $password = $hasher->HashPassword($this->request->password);

            // 更新用户密码
            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $password))
                ->where('uid = ?', $user['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败'), 'error');
                return;
            }

            // 标记令牌为已使用
            $db->query($db->update('table.password_reset_tokens')
                ->rows(array('used' => 1))
                ->where('token = ?', urldecode($token)));

            error_log('Passport: Password reset successful, token marked as used: ' . $token);

            $this->notice->set(_t('重置密码成功'), 'success');
            $this->response->redirect($this->options->loginUrl);
        }
    }

    /**
     * Verify reCAPTCHA v2 response
     */
    private function verifyRecaptcha($response, $secretKey)
    {
        if (empty($response)) {
            return false;
        }

        $post_data = [
            'secret' => $secretKey,
            'response' => $response
        ];
        $recaptcha_json_result = $this->send_post('https://www.recaptcha.net/recaptcha/api/siteverify', $post_data);
        $recaptcha_result = json_decode($recaptcha_json_result, true);

        return isset($recaptcha_result['success']) && $recaptcha_result['success'];
    }

    /**
     * Verify hCaptcha response
     */
    private function verifyHcaptcha($response, $secretKey)
    {
        if (empty($response)) {
            return false;
        }

        $post_data = [
            'secret' => $secretKey,
            'response' => $response
        ];
        $hcaptcha_json_result = $this->send_post('https://hcaptcha.com/siteverify', $post_data);
        $hcaptcha_result = json_decode($hcaptcha_json_result, true);

        return isset($hcaptcha_result['success']) && $hcaptcha_result['success'];
    }

    /**
     * Verify Geetest v4 response
     */
    private function verifyGeetest($lot_number, $captcha_output, $pass_token, $gen_time)
    {
        if (empty($lot_number) || empty($captcha_output) || empty($pass_token) || empty($gen_time)) {
            return false;
        }

        $captcha_id = $this->config->captchaIdGeetest;
        $captcha_key = $this->config->captchaKeyGeetest;

        // 1. 生成签名
        $sign_token = hash_hmac('sha256', $lot_number, $captcha_key);

        // 2. 准备请求数据
        $post_data = [
            "lot_number" => $lot_number,
            "captcha_output" => $captcha_output,
            "pass_token" => $pass_token,
            "gen_time" => $gen_time,
            "sign_token" => $sign_token,
        ];

        // 3. 发送请求
        $api_server = 'http://gcaptcha4.geetest.com';
        $url = $api_server . '/validate?captcha_id=' . $captcha_id;

        $geetest_json_result = $this->send_post($url, $post_data);

        if ($geetest_json_result === false) {
            error_log('Passport: request geetest api fail');
            // 在无法连接极验服务器时，可以根据安全策略选择是否放行，这里默认不放行
            return false;
        }

        $geetest_result = json_decode($geetest_json_result, true);

        return isset($geetest_result['status']) && $geetest_result['status'] === 'success' &&
               isset($geetest_result['result']) && $geetest_result['result'] === 'success';
    }

    /**
     * Send reset email
     */
    private function sendResetEmail($user, $url)
    {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->SMTPSecure = $this->config->secure === 'none' ? false : $this->config->secure;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->Port = $this->config->port;

            $mail->setFrom($this->config->username, $this->options->title);
            $mail->addAddress($user['mail'], $user['name']);

            $emailBody = str_replace(
                ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
                [$user['name'], Helper::options()->title, date('Y-m-d H:i:s'), $url],
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
     * Forgot password form
     */
    public function forgotForm()
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
     * Reset password form
     */
    public function resetForm()
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
        $password->addRule('minLength', _t('为了保证账户安全, 请输入至少六位的密码'), 6);
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');

        return $form;
    }

    /**
     * Generic POST request sender
     */
    private function send_post($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 20
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result;
    }
}