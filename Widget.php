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
// Adjust these paths if your PHPMailer files are in a 'src' subdirectory (e.g., 'PHPMailer/src/Exception.php')
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
    }

    public function execute(){}

    public function doForgot()
    {
        // For security, ensure the template is always included outside the POST block
        // to render the form even if there are POST validation errors.
        require_once 'template/forgot.php';

        if ($this->request->isPost()) {
            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                // Don't return false directly as it stops further rendering.
                // Instead, the notice will be displayed on the current page.
                return;
            }

            $captchaType = $this->config->captchaType;
            $gRecaptchaResponse = $this->request->get('g-recaptcha-response');
            $hCaptchaResponse = $this->request->get('h-captcha-response');
            $captchaVerified = false;

            switch ($captchaType) {
                case 'recaptcha':
                    if (empty($this->config->sitekeyRecaptcha) || empty($this->config->secretkeyRecaptcha)) {
                        $this->notice->set(_t('reCAPTCHA配置不完整, 请联系管理员。'), 'error');
                        return;
                    }
                    $captchaVerified = $this->verifyRecaptcha($gRecaptchaResponse, $this->config->secretkeyRecaptcha);
                    break;
                case 'hcaptcha':
                    if (empty($this->config->sitekeyHcaptcha) || empty($this->config->secretkeyHcaptcha)) {
                        $this->notice->set(_t('hCaptcha配置不完整, 请联系管理员。'), 'error');
                        return;
                    }
                    $captchaVerified = $this->verifyHcaptcha($hCaptchaResponse, $this->config->secretkeyHcaptcha);
                    break;
                case 'none':
                default:
                    $captchaVerified = true; // No captcha selected, so it's "verified"
                    break;
            }

            if (!$captchaVerified) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                // Keep the user on the current page to re-attempt captcha
                return;
            }

            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                return;
            }

            // Generate a hash based on unchanging user data (name, mail, and the *current* password)
            // This hash ensures the token is invalidated if the user's base info or password changes before reset.
            $hashOfUserData = Typecho_Common::hash($user['name'] . $user['mail'] . $user['password']);
            $token = base64_encode($user['uid'] . '.' . $hashOfUserData . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/passport/reset?token=' . urlencode($token), $this->options->index);

            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收'), 'success');
            } else {
                $this->notice->set(_t('邮件发送失败, 请稍后重试'), 'error');
            }
        }
    }

    public function doReset()
    {
        // For security, ensure the template is always included outside the POST block
        require_once 'template/reset.php';

        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;

        // Check if token is valid before attempting decode/explode
        if (empty($token) || strpos($token, '.') === false) {
            $this->notice->set(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // Base64 decode and split token parts
        $decodedToken = base64_decode($token);
        if ($decodedToken === false) {
            $this->notice->set(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        @list($uid, $tokenHashOfUserData, $timeStamp) = explode('.', $decodedToken);

        // Validate token parts
        if (empty($uid) || empty($tokenHashOfUserData) || empty($timeStamp) || !is_numeric($uid) || !is_numeric($timeStamp)) {
            $this->notice->set(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $currentTimeStamp = $this->options->gmtTime;

        // Token expiration check (1 hour = 3600 seconds)
        if (($currentTimeStamp - $timeStamp) > 3600) {
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));

        if (empty($user)) {
            $this->notice->set(_t('用户不存在或链接已失效'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // Re-generate the hash from current user data to validate the token
        $currentHashOfUserData = Typecho_Common::hash($user['name'] . $user['mail'] . $user['password']);

        // Compare the hash from the token with the hash generated from current user data
        if ($currentHashOfUserData !== $tokenHashOfUserData) {
            $this->notice->set(_t('该链接已失效或用户信息已更改, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        if ($this->request->isPost()) {
            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                return;
            }

            $captchaType = $this->config->captchaType;
            $gRecaptchaResponse = $this->request->get('g-recaptcha-response');
            $hCaptchaResponse = $this->request->get('h-captcha-response');
            $captchaVerified = false;

            switch ($captchaType) {
                case 'recaptcha':
                    if (empty($this->config->sitekeyRecaptcha) || empty($this->config->secretkeyRecaptcha)) {
                        $this->notice->set(_t('reCAPTCHA配置不完整, 请联系管理员。'), 'error');
                        return;
                    }
                    $captchaVerified = $this->verifyRecaptcha($gRecaptchaResponse, $this->config->secretkeyRecaptcha);
                    break;
                case 'hcaptcha':
                    if (empty($this->config->sitekeyHcaptcha) || empty($this->config->secretkeyHcaptcha)) {
                        $this->notice->set(_t('hCaptcha配置不完整, 请联系管理员。'), 'error');
                        return;
                    }
                    $captchaVerified = $this->verifyHcaptcha($hCaptchaResponse, $this->config->secretkeyHcaptcha);
                    break;
                case 'none':
                default:
                    $captchaVerified = true; // No captcha selected, so it's "verified"
                    break;
            }

            if (!$captchaVerified) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                return;
            }

            // Using Typecho's PasswordHash class for password hashing
            $hasher = new PasswordHash(8, true); // 8 is a good cost, true for portable hash
            $password = $hasher->HashPassword($this->request->password);

            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $password))
                ->where('uid = ?', $user['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败'), 'error');
            } else {
                $this->notice->set(_t('重置密码成功'), 'success');
                $this->response->redirect($this->options->loginUrl);
            }
        }
    }

    /**
     * Verify reCAPTCHA v2 response
     *
     * @param string $response The g-recaptcha-response from the client
     * @param string $secretKey The reCAPTCHA secret key
     * @return bool
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
        // Use recaptcha.net for users who might have issues with google.com
        $recaptcha_json_result = $this->send_post('https://www.recaptcha.net/recaptcha/api/siteverify', $post_data);
        $recaptcha_result = json_decode($recaptcha_json_result, true);

        return isset($recaptcha_result['success']) && $recaptcha_result['success'];
    }

    /**
     * Verify hCaptcha response
     *
     * @param string $response The h-captcha-response from the client
     * @param string $secretKey The hCaptcha secret key
     * @return bool
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


    private function sendResetEmail($user, $url)
    {
        $mail = new PHPMailer(true); // true enables exceptions
        try {
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0; // 0 for production, 2 for client and server messages
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->Port = $this->config->port;

            if ('none' != $this->config->secure) {
                // SMTPSecure can be 'ssl' or 'tls'
                $mail->SMTPSecure = $this->config->secure;
            } else {
                // If 'none' is selected, explicitly disable SMTPSecure
                $mail->SMTPSecure = false;
            }

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
            $mail->AltBody = strip_tags($emailBody); // Plain text alternative for email clients that don't support HTML

            return $mail->send();
        } catch (Exception $e) {
            // Log the error for debugging purposes if needed
            // error_log('PHPMailer Error: ' . $e->getMessage());
            return false;
        }
    }

    public function forgotForm() {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);

        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail',
            NULL,
            NULL,
            _t('邮箱'),
            _t('请输入您忘记密码的账号所对应的邮箱地址'));
        $form->addInput($mail);

        /** 用户动作 */
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'mail');
        $form->addInput($do);

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('提交'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule('email', _t('电子邮箱格式错误'));

        return $form;
    }

    public function resetForm() {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);

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

        /** 用户动作 */
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'password');
        $form->addInput($do);

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('必须填写密码'));
        $password->addRule('minLength', _t('为了保证账户安全, 请输入至少六位的密码'), 6);
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');

        return $form;
    }

    /** Generic POST request sender (for captcha verification) */
    private function send_post($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 20 // 超时时间（单位:s）
            ),
            // Add stream_context_create options for SSL verification if needed, especially for older PHP versions
            // 'ssl' => array(
            //     'verify_peer' => true,
            //     'verify_peer_name' => true,
            //     'allow_self_signed' => false,
            //     'cafile' => '/path/to/cacert.pem', // Path to your CA bundle if default isn't working
            // ),
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context); // Suppress warnings
        return $result;
    }

}