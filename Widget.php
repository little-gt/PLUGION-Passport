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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\POP3; // Added for POP-before-SMTP

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
    }

    public function execute(){}

    public function doForgot()
    {
        // Pass config to template for CAPTCHA keys
        require_once 'template/forgot.php';

        if ($this->request->isPost()) {
            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                $this->response->goBack(); // Added to stay on page
                return false;
            }

            if (!$this->verifyCaptcha()) { // Updated
                // Error message is set within verifyCaptcha
                $this->response->goBack();
                return;
            }

            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                $this->response->goBack(); // Added
                return false;
            }

            $hashString = $user['name'] . $user['mail'] . $user['password'];
            $hashValidate = Typecho_Common::hash($hashString);
            $token = base64_encode($user['uid'] . '.' . $hashValidate . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/passport/reset?token=' . $token, $this->options->index);

            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收'), 'success');
                // Potentially redirect or clear form here if desired after success
            } else {
                // Error message is set within sendResetEmail or POP auth
                $this->response->goBack(); // Stay on page if send fails
            }
        }
    }

    public function doReset()
    {
        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;
        list($uid, $hashValidateToken, $timeStamp) = explode('.', base64_decode($token)); // Renamed $hashValidate
        $currentTimeStamp = $this->options->gmtTime;

        if (($currentTimeStamp - $timeStamp) > 3600) { // 1 hour
            $this->notice->set(_t('该链接已失效或已过期, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));

        if (empty($user)) { // Check if user exists
            $this->notice->set(_t('无效的用户信息'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $hashString = $user['name'] . $user['mail'] . $user['password'];
        // Use Typecho_Common::hashValidate() for comparing, not generating.
        // The second parameter to Typecho_Common::hashValidate() should be the hash to validate against.
        if (!Typecho_Common::hashValidate($hashString, $hashValidateToken)) {
            $this->notice->set(_t('该链接校验失败, 可能已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // Pass config to template for CAPTCHA keys
        require_once 'template/reset.php';

        if ($this->request->isPost()) {
            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                $this->response->goBack(); // Added
                return false;
            }

            if (!$this->verifyCaptcha()) { // Updated
                // Error message is set within verifyCaptcha
                $this->response->goBack();
                return;
            }

            $hasher = new PasswordHash(8, true);
            $password = $hasher->HashPassword($this->request->password);

            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $password))
                ->where('uid = ?', $user['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败, 请稍后重试'), 'error');
                $this->response->goBack(); // Added
            } else {
                $this->notice->set(_t('重置密码成功, 请使用新密码登录'), 'success');
                $this->response->redirect($this->options->loginUrl);
            }
        }
    }

    private function verifyCaptcha()
    {
        $provider = $this->config->captchaProvider;
        switch ($provider) {
            case 'recaptcha_v2':
                $response = $this->request->get('g-recaptcha-response');
                if (empty($response)) {
                    $this->notice->set(_t('请完成人机身份验证 (reCAPTCHA v2)'), 'error');
                    return false;
                }
                return $this->verifyReCaptchaV2($response);
            case 'recaptcha_v3':
                $response = $this->request->get('recaptcha_v3_token');
                if (empty($response)) {
                    $this->notice->set(_t('人机身份验证失败 (reCAPTCHA v3 Token missing)'), 'error');
                    return false;
                }
                return $this->verifyReCaptchaV3($response);
            case 'hcaptcha':
                $response = $this->request->get('h-captcha-response');
                if (empty($response)) {
                    $this->notice->set(_t('请完成人机身份验证 (hCaptcha)'), 'error');
                    return false;
                }
                return $this->verifyHCaptcha($response);
            case 'none':
            default:
                return true;
        }
    }

    private function verifyReCaptchaV2($response)
    {
        if (empty($this->config->recaptcha_v2_secretkey)) {
            $this->notice->set(_t('reCAPTCHA v2 密钥未配置'), 'error');
            return false;
        }
        $post_data = [
            'secret' => $this->config->recaptcha_v2_secretkey,
            'response' => $response,
            'remoteip' => $this->request->getIp()
        ];
        $verify_url = 'https://www.recaptcha.net/recaptcha/api/siteverify'; // Using .net for better global access
        $result_json = $this->send_post($verify_url, $post_data);
        $result = json_decode($result_json, true);

        if (isset($result['success']) && $result['success']) {
            return true;
        } else {
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown error';
            $this->notice->set(_t('reCAPTCHA v2 验证失败: ') . $error_codes, 'error');
            return false;
        }
    }

    private function verifyReCaptchaV3($token)
    {
        if (empty($this->config->recaptcha_v3_secretkey)) {
            $this->notice->set(_t('reCAPTCHA v3 密钥未配置'), 'error');
            return false;
        }
        $post_data = [
            'secret' => $this->config->recaptcha_v3_secretkey,
            'response' => $token,
            'remoteip' => $this->request->getIp()
        ];
        $verify_url = 'https://www.recaptcha.net/recaptcha/api/siteverify';
        $result_json = $this->send_post($verify_url, $post_data);
        $result = json_decode($result_json, true);

        $threshold = (float) $this->config->recaptcha_v3_threshold;
        if (empty($threshold) || $threshold < 0 || $threshold > 1) $threshold = 0.5;

        if (isset($result['success']) && $result['success'] && isset($result['score']) && $result['score'] >= $threshold) {
            return true;
        } else {
            $reason = 'low score';
            if (!$result['success']) {
                $reason = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown error';
            } else if ($result['score'] < $threshold) {
                $reason = _t('分数过低: ') . $result['score'];
            }
            $this->notice->set(_t('reCAPTCHA v3 验证失败: ') . $reason, 'error');
            return false;
        }
    }

    private function verifyHCaptcha($response)
    {
        if (empty($this->config->hcaptcha_secretkey)) {
            $this->notice->set(_t('hCaptcha 密钥未配置'), 'error');
            return false;
        }
        $post_data = [
            'secret' => $this->config->hcaptcha_secretkey,
            'response' => $response,
            'remoteip' => $this->request->getIp(),
            'sitekey' => $this->config->hcaptcha_sitekey // Optional, but recommended
        ];
        $verify_url = 'https://hcaptcha.com/siteverify';
        $result_json = $this->send_post($verify_url, $post_data);
        $result = json_decode($result_json, true);

        if (isset($result['success']) && $result['success']) {
            return true;
        } else {
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown error';
            $this->notice->set(_t('hCaptcha 验证失败: ') . $error_codes, 'error');
            return false;
        }
    }

    private function sendResetEmail($user, $url)
    {
        // POP-before-SMTP
        if ($this->config->enable_pop_before_smtp == 1) {
            if (empty($this->config->pop3_host) || empty($this->config->pop3_username) || empty($this->config->pop3_password)) {
                $this->notice->set(_t('POP-before-SMTP 配置不完整'), 'error');
                return false;
            }
            require_once 'PHPMailer/POP3.php'; // Ensure POP3 class is loaded
            $pop = new POP3();
            $pop_debug_level = ($this->options->debug) ? POP3::DEBUG_CLIENT : POP3::DEBUG_OFF; // Basic debug mapping

            // Port can be false to use default, or an integer
            $pop_port = empty($this->config->pop3_port) ? false : (int)$this->config->pop3_port;

            if (!$pop->authorise(
                $this->config->pop3_host,
                $pop_port, // port or false for default
                30, // timeout
                $this->config->pop3_username,
                $this->config->pop3_password,
                $pop_debug_level
            )) {
                $this->notice->set(_t('POP-before-SMTP 认证失败: ') . implode('; ', $pop->getErrors()), 'error');
                return false;
            }
            $this->notice->set(_t('POP-before-SMTP 认证成功'), 'info'); // Optional info message
        }

        require_once 'PHPMailer/Exception.php';
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/SMTP.php';

        $mail = new PHPMailer(true); // Passing true enables exceptions
        try {
            $mail->CharSet = "UTF-8";
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->Port = (int)$this->config->port;

            if (!empty($this->config->secure) && in_array($this->config->secure, ['ssl', 'tls'])) {
                $mail->SMTPSecure = $this->config->secure;
            }

            // For debugging SMTP issues if Typecho debug mode is on
            if (isset($this->options->debug) && $this->options->debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Or SMTP::DEBUG_CLIENT
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer debug level $level; message: $str"); // Log to PHP error log
                };
            }


            $mail->setFrom($this->config->username, $this->options->title . ' ' ._t('管理员'));
            $mail->addAddress($user['mail'], $user['name']);

            $emailBody = str_replace(
                ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
                [$user['name'], $this->options->title, date('Y-m-d H:i:s', $this->options->gmtTime + ($this->options->timezoneOffset - $this->options->serverTimezoneOffset)), $url],
                $this->config->emailTemplate
            );

            $mail->isHTML(true);
            $mail->Subject = $this->options->title . ' - ' . _t('密码重置请求');
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags($emailBody); // Basic plain text version

            return $mail->send();
        } catch (Exception $e) {
            $this->notice->set(_t('邮件发送失败: ') . $mail->ErrorInfo . ' (PHPMailer Exception: ' . $e->getMessage() . ')', 'error');
            error_log('PHPMailer Exception for Passport plugin: ' . $e->getMessage() . ' | More Info: ' . $mail->ErrorInfo); // Log more details
            return false;
        }
    }

    public function forgotForm() {
        $form = new Typecho_Widget_Helper_Form($this->options->forgotUrl, Typecho_Widget_Helper_Form::POST_METHOD); // Use dynamic URL

        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail',
            NULL,
            $this->request->mail, // Keep submitted value on error
            _t('邮箱'),
            _t('请输入您忘记密码的账号所对应的邮箱地址'));
        $form->addInput($mail);

        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule('email', _t('电子邮箱格式错误'));

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('发送重置邮件')); // Changed text
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);


        return $form;
    }

    public function resetForm() {
        // Construct URL for form action using current token
        $resetUrl = Typecho_Common::url('/passport/reset?token=' . urlencode($this->request->token), $this->options->index);
        $form = new Typecho_Widget_Helper_Form($resetUrl, Typecho_Widget_Helper_Form::POST_METHOD);


        /** 新密码 */
        $password = new Typecho_Widget_Helper_Form_Element_Password('password',
            NULL,
            NULL,
            _t('新密码'),
            _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $password->input->setAttribute('class', 'w-100');
        $form->addInput($password);

        /** 新密码确认 */
        $confirm = new Typecho_Widget_Helper_Form_Element_Password('confirm',
            NULL,
            NULL,
            _t('密码确认'),
            _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirm->input->setAttribute('class', 'w-100');
        $form->addInput($confirm);

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('必须填写密码'));
        $password->addRule('minLength', _t('为了保证账户安全, 请输入至少 %d 位的密码'), 6); // Use %d for Typecho rule
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');

        return $form;
    }

    /**reCaptcha/hCaptcha send_post method */
    private function send_post($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 20 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }
}