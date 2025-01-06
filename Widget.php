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
        require_once 'template/forgot.php';

        if ($this->request->isPost()) {
            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                return false;
            }

            if (!$this->verifyReCaptcha($_POST["g-recaptcha-response"])) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                $this->response->goBack();
                return;
            }

            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                return false;
            }

            $hashString = $user['name'] . $user['mail'] . $user['password'];
            $hashValidate = Typecho_Common::hash($hashString);
            $token = base64_encode($user['uid'] . '.' . $hashValidate . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/passport/reset?token=' . $token, $this->options->index);

            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收'), 'success');
            } else {
                $this->notice->set(_t('邮件发送失败, 请稍后重试'), 'error');
            }
        }
    }

    public function doReset()
    {
        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;
        list($uid, $hashValidate, $timeStamp) = explode('.', base64_decode($token));
        $currentTimeStamp = $this->options->gmtTime;

        if (($currentTimeStamp - $timeStamp) > 3600) {
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));

        $hashString = $user['name'] . $user['mail'] . $user['password'];
        $hashValidate = Typecho_Common::hashValidate($hashString, $hashValidate);

        if (!$hashValidate) {
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        require_once 'template/reset.php';

        if ($this->request->isPost()) {
            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                return false;
            }

            if (!$this->verifyReCaptcha($_POST["g-recaptcha-response"])) {
                $this->notice->set(_t('请完成人机身份验证以后再提交您的重置密码请求'), 'error');
                $this->response->goBack();
                return;
            }

            $hasher = new PasswordHash(8, true);
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

    private function verifyReCaptcha($response)
    {
        $post_data = [
            'secret' => $this->config->secretkey,
            'response' => $response
        ];
        $recaptcha_json_result = $this->send_post('https://www.recaptcha.net/recaptcha/api/siteverify', $post_data);
        $recaptcha_result = json_decode($recaptcha_json_result, true);

        return isset($recaptcha_result['success']) ? $recaptcha_result['success'] : false;
    }

    private function sendResetEmail($user, $url)
    {
        require 'PHPMailer/Exception.php';
        require 'PHPMailer/PHPMailer.php';
        require 'PHPMailer/SMTP.php';
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->Port = $this->config->port;

            if ('none' != $this->config->secure) {
                $mail->SMTPSecure = $this->config->secure;
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

            return $mail->send();
        } catch (Exception $e) {
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

    /**reCaptcha的send方法 */
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
