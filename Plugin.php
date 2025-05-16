<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.2
 * @link https://garfieldtom.cool/
 */
class Passport_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Helper::addRoute('passport_forgot', '/passport/forgot', 'Passport_Widget', 'doForgot');
        Helper::addRoute('passport_reset', '/passport/reset', 'Passport_Widget', 'doReset');

        return _t('请配置此插件的SMTP及验证码参数, 以使您的找回密码插件生效！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute('passport_reset');
        Helper::removeRoute('passport_forgot');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        //邮件服务器信息
        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, '', _t('服务器(SMTP)'), _t('如: smtp.exmail.qq.com'));
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('端口'), _t('如: 25、465(SSL)、587(SSL)'));

        //发送邮件的账户信息
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, '', _t('帐号'), _t('如: hello@example.com'));
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('密码'));

        //邮件服务器安全设置
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure',array(
            'ssl' => _t('SSL'),
            'tls' => _t('TLS'),
            'none' => _t('无')
        ), 'ssl', _t('安全类型'));

        // Captcha Type Selection
        $captchaType = new Typecho_Widget_Helper_Form_Element_Select('captchaType', array(
            'none' => _t('不使用验证码'),
            'recaptcha' => _t('Google reCAPTCHA v2'),
            'hcaptcha' => _t('hCaptcha')
        ), 'none', _t('验证码类型'), _t('选择您要使用的验证码服务。不使用验证码可能增加垃圾邮件或恶意请求的风险。你只需要填写你选中的验证码的配置信息，其他的留空即可。'));

        // reCAPTCHA v2 验证码配置信息
        $sitekeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyRecaptcha', NULL, '', _t('reCAPTCHA Site Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));
        $secretkeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyRecaptcha', NULL, '', _t('reCAPTCHA Secret Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));

        // hCaptcha 验证码配置信息
        $sitekeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyHcaptcha', NULL, '', _t('hCaptcha Site Key'), _t('访问 <a href="https://www.hcaptcha.com/signup" target="_blank">hCaptcha 控制台</a> 获取。'));
        $secretkeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyHcaptcha', NULL, '', _t('hCaptcha Secret Key'), _t('访问 <a href="https://www.hcaptcha.com/signup" target="_blank">hCaptcha 控制台</a> 获取。'));

        // Add sending email template setting
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL,
            '<h3>{username}，您好：</h3>
             <p>您在 {sitename} 提交了密码重置操作于：{requestTime}。</p>
             <hr/>
             <p>请在 1 小时内点击此链接以完成重置 <a href="{resetLink}">{resetLink}</a></p>
             <hr/>
             <p>技术支持：GARFIELDTOM</p>',
            _t('邮件模板'),
            _t('请使用 {username} {sitename} {requestTime} {resetLink} 作为占位符')
        );

        $form->addInput($host);
        $form->addInput($port);
        $form->addInput($username);
        $form->addInput($password);
        $form->addInput($secure);
        $form->addInput($captchaType);
        $form->addInput($sitekeyRecaptcha);
        $form->addInput($secretkeyRecaptcha);
        $form->addInput($sitekeyHcaptcha);
        $form->addInput($secretkeyHcaptcha);
        $form->addInput($emailTemplate);

        // JavaScript for conditional display of captcha fields
        Typecho_Widget::widget('Widget_Contents_Post_Edit')->to($post); // Dummy widget to inject JS
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            function toggleCaptchaFields() {
                var captchaType = document.getElementById("captchaType").value;
                var recaptchaFields = [
                    document.getElementById("typecho-option-item-sitekeyRecaptcha-8"),
                    document.getElementById("typecho-option-item-secretkeyRecaptcha-9")
                ];
                var hcaptchaFields = [
                    document.getElementById("typecho-option-item-sitekeyHcaptcha-10"),
                    document.getElementById("typecho-option-item-secretkeyHcaptcha-11")
                ];

                recaptchaFields.forEach(function(el) {
                    if (el) el.style.display = (captchaType === "recaptcha") ? "block" : "none";
                });
                hcaptchaFields.forEach(function(el) {
                    if (el) el.style.display = (captchaType === "hcaptcha") ? "block" : "none";
                });
            }

            var captchaTypeSelect = document.getElementById("captchaType");
            if (captchaTypeSelect) {
                captchaTypeSelect.addEventListener("change", toggleCaptchaFields);
                toggleCaptchaFields(); // Initial call to set correct visibility
            }
        });
        </script>';
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

}