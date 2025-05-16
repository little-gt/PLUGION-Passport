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
use PHPMailer\PHPMailer\POP3;

class Passport_Widget extends Typecho_Widget
{
    private $options;
    private $config;
    private $notice;
    // Security object for CSRF
    private $security;


    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->notice = parent::widget('Widget_Notice');
        $this->options = parent::widget('Widget_Options');
        $this->config = $this->options->plugin('Passport');
        $this->security = parent::widget('Widget_Security'); // Initialize security for CSRF
    }

    public function execute(){}

    public function doForgot()
    {
        // Template will use $this->security->getTokenInput();
        require_once 'template/forgot.php';

        if ($this->request->isPost()) {
            $this->security->checkToken(); // CSRF Check

            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                $this->response->goBack();
                return false;
            }

            if (!$this->verifyCaptcha()) {
                $this->response->goBack();
                return;
            }

            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                $this->response->goBack();
                return false;
            }

            $hashString = $user['name'] . $user['mail'] . $user['password'];
            $hashValidate = Typecho_Common::hash($hashString);
            // The token itself is base64 encoded, which is mostly URL safe but + and / can be problematic.
            // urlencode ensures it's safe for a query parameter.
            $token = base64_encode($user['uid'] . '.' . $hashValidate . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/passport/reset?token=' . urlencode($token), $this->options->index);


            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收'), 'success');
            } else {
                $this->response->goBack();
            }
        }
    }

    public function doReset()
    {
        // PHP automatically urldecodes values from $_GET
        $token_from_url = $this->request->get('token'); // Get raw token from URL
        if (empty($token_from_url)) {
            $this->notice->set(_t('无效的重置链接 (Token缺失)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // Filter the already URL-decoded token
        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;


        // list($uid, $hashValidateToken, $timeStamp) = explode('.', base64_decode($token_from_url));
        $decoded_token_parts = explode('.', base64_decode($token)); // Use the filtered token
        if (count($decoded_token_parts) !== 3) {
            $this->notice->set(_t('无效的重置链接 (Token格式错误)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }
        list($uid, $hashValidateToken, $timeStamp) = $decoded_token_parts;


        $currentTimeStamp = $this->options->gmtTime;

        if (($currentTimeStamp - (int)$timeStamp) > 3600) { // 1 hour, ensure timestamp is int
            $this->notice->set(_t('该链接已失效或已过期, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));

        if (empty($user)) {
            $this->notice->set(_t('无效的用户信息 (UID不存在)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $hashString = $user['name'] . $user['mail'] . $user['password'];
        if (!Typecho_Common::hashValidate($hashString, $hashValidateToken)) {
            $this->notice->set(_t('该链接校验失败, 可能已失效或密码已更改, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // Template will use $this->security->getTokenInput();
        require_once 'template/reset.php';

        if ($this->request->isPost()) {
            $this->security->checkToken(); // CSRF Check

            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                $this->response->goBack();
                return false;
            }

            if (!$this->verifyCaptcha()) {
                $this->response->goBack();
                return;
            }

            $hasher = new PasswordHash(8, true);
            $new_password_hashed = $hasher->HashPassword($this->request->password);

            // Important: Invalidate the token by changing the password.
            // Any subsequent attempt with the same token will fail because $user['password'] used for validation will be the new one.
            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $new_password_hashed))
                ->where('uid = ?', $user['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败, 请稍后重试'), 'error');
                $this->response->goBack();
            } else {
                $this->notice->set(_t('重置密码成功, 请使用新密码登录'), 'success');
                $this->response->redirect($this->options->loginUrl);
            }
        }
    }

    private function verifyCaptcha()
    {
        $provider = $this->config->captchaProvider;
        $captcha_response_key = '';
        $captcha_token_key = '';

        switch ($provider) {
            case 'recaptcha_v2': $captcha_response_key = 'g-recaptcha-response'; break;
            case 'recaptcha_v3': $captcha_token_key = 'recaptcha_v3_token'; break; // This is a hidden input
            case 'hcaptcha': $captcha_response_key = 'h-captcha-response'; break;
            case 'none': default: return true;
        }

        $response = '';
        if (!empty($captcha_response_key)) {
            $response = $this->request->get($captcha_response_key);
        } elseif (!empty($captcha_token_key)) {
            $response = $this->request->get($captcha_token_key);
        }


        if ($provider !== 'none' && empty($response)) {
            $this->notice->set(_t('请完成人机身份验证'), 'error');
            return false;
        }

        switch ($provider) {
            case 'recaptcha_v2': return $this->verifyReCaptchaV2($response);
            case 'recaptcha_v3': return $this->verifyReCaptchaV3($response);
            case 'hcaptcha': return $this->verifyHCaptcha($response);
        }
        return true; // Should not reach here if provider is not 'none'
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
        $verify_url = 'https://www.recaptcha.net/recaptcha/api/siteverify';
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
            } else if (isset($result['score']) && $result['score'] < $threshold) { // Check if score is set
                $reason = _t('分数过低: ') . $result['score'];
            } else if (!isset($result['score'])) {
                $reason = _t('未能获取分数');
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
            'sitekey' => $this->config->hcaptcha_sitekey
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
        if ($this->config->enable_pop_before_smtp == 1) {
            if (empty($this->config->pop3_host) || empty($this->config->pop3_username) || empty($this->config->pop3_password)) {
                $this->notice->set(_t('POP-before-SMTP 配置不完整'), 'error');
                return false;
            }
            require_once 'PHPMailer/POP3.php';
            $pop = new POP3();
            $pop_debug_level = ($this->options->debug) ? POP3::DEBUG_CLIENT : POP3::DEBUG_OFF;
            $pop_port = empty($this->config->pop3_port) ? false : (int)$this->config->pop3_port;

            if (!$pop->authorise(
                $this->config->pop3_host, $pop_port, 30,
                $this->config->pop3_username, $this->config->pop3_password, $pop_debug_level
            )) {
                $this->notice->set(_t('POP-before-SMTP 认证失败: ') . implode('; ', $pop->getErrors()), 'error');
                return false;
            }
            // $this->notice->set(_t('POP-before-SMTP 认证成功'), 'info'); // Can be noisy
        }

        require_once 'PHPMailer/Exception.php';
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/SMTP.php';

        $mail = new PHPMailer(true);
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

            if (isset($this->options->debug) && $this->options->debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer debug level $level; message: $str");
                };
            }

            $mail->setFrom($this->config->username, $this->options->title . ' ' ._t('管理员'));
            $mail->addAddress($user['mail'], $user['name']);

            // Adjust time to user's timezone for display in email
            $userTime = $this->options->gmtTime + ($this->options->timezoneOffset - $this->options->serverTimezoneOffset);
            $emailBody = str_replace(
                ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
                [$user['name'], $this->options->title, date('Y-m-d H:i:s', $userTime), $url],
                $this->config->emailTemplate
            );

            $mail->isHTML(true);
            $mail->Subject = $this->options->title . ' - ' . _t('密码重置请求');
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", preg_replace('/<hr\/?>/i', "\n----\n", $emailBody))); // Improved AltBody

            return $mail->send();
        } catch (Exception $e) {
            $this->notice->set(_t('邮件发送失败: ') . $mail->ErrorInfo . ' (Details: ' . $e->getMessage() . ')', 'error');
            error_log('PHPMailer Exception for Passport plugin: ' . $e->getMessage() . ' | PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function forgotForm() {
        $form = new Typecho_Widget_Helper_Form($this->options->forgotUrl, Typecho_Widget_Helper_Form::POST_METHOD);

        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail',
            NULL, $this->request->mail, _t('邮箱'),
            _t('请输入您忘记密码的账号所对应的邮箱地址'));
        $form->addInput($mail);
        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule('email', _t('电子邮箱格式错误'));

        // CSRF Token Field will be rendered in the template

        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('发送重置邮件'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }

    public function resetForm() {
        $resetUrlWithToken = Typecho_Common::url('/passport/reset?token=' . urlencode($this->request->get('token')), $this->options->index);
        $form = new Typecho_Widget_Helper_Form($resetUrlWithToken, Typecho_Widget_Helper_Form::POST_METHOD);

        $password = new Typecho_Widget_Helper_Form_Element_Password('password',
            NULL, NULL, _t('新密码'),
            _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $password->input->setAttribute('class', 'w-100');
        $form->addInput($password);

        $confirm = new Typecho_Widget_Helper_Form_Element_Password('confirm',
            NULL, NULL, _t('密码确认'),
            _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirm->input->setAttribute('class', 'w-100');
        $form->addInput($confirm);

        // CSRF Token Field will be rendered in the template

        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('必须填写密码'));
        $password->addRule('minLength', _t('为了保证账户安全, 请输入至少 %d 位的密码'), 6);
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');
        return $form;
    }

    private function send_post($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type:application/x-www-form-urlencoded\r\n" .
                    "User-Agent: Typecho Passport Plugin\r\n", // Added User-Agent
                'content' => $postdata,
                'timeout' => 20,
                'ignore_errors' => true // To get content even on HTTP error codes
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        // Check for request failure
        if ($result === false) {
            $error = error_get_last();
            error_log("Passport Plugin: send_post to $url failed. Error: " . ($error['message'] ?? 'Unknown error'));
            // You might want to set a notice here if it's critical for user feedback
            // $this->notice->set(_t('无法连接到 CAPTCHA 验证服务器。'), 'error');
        }
        return $result;
    }
}