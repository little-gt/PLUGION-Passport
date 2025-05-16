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

        return _t('请配置此插件的SMTP及CAPTCHA参数, 以使您的找回密码插件生效！');
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
        echo '<h2>SMTP ' . _t('设置') . '</h2>';
        //邮件服务器信息
        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.example.com', _t('SMTP 服务器'), _t('如: smtp.exmail.qq.com, smtp.gmail.com'));
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('SMTP 端口'), _t('通常 SSL 为 465, TLS 为 587, 非加密为 25'));
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, 'user@example.com', _t('SMTP 帐号'), _t('如: hello@example.com'));
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('SMTP 密码'));
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure',array(
            'ssl' => _t('SSL'),
            'tls' => _t('TLS'),
            '' => _t('无 (不推荐)')
        ), 'ssl', _t('SMTP 安全类型'));

        $form->addInput($host);
        $form->addInput($port);
        $form->addInput($username);
        $form->addInput($password);
        $form->addInput($secure);

        echo '<h3>' . _t('POP-before-SMTP (可选)') . '</h3>';
        $enable_pop_before_smtp = new Typecho_Widget_Helper_Form_Element_Radio('enable_pop_before_smtp',
            array(0 => _t('禁用'), 1 => _t('启用')), 0, _t('启用 POP-before-SMTP'),
            _t('某些邮件服务商可能需要先进行POP3登录才能发送SMTP邮件。'));
        $pop3_host = new Typecho_Widget_Helper_Form_Element_Text('pop3_host', NULL, '', _t('POP3 服务器'), _t('如: pop.example.com'));
        $pop3_port = new Typecho_Widget_Helper_Form_Element_Text('pop3_port', NULL, '110', _t('POP3 端口'), _t('通常非加密为 110, SSL 为 995'));
        $pop3_username = new Typecho_Widget_Helper_Form_Element_Text('pop3_username', NULL, '', _t('POP3 帐号'), _t('通常与SMTP帐号相同'));
        $pop3_password = new Typecho_Widget_Helper_Form_Element_Password('pop3_password', NULL, NULL, _t('POP3 密码'));

        $form->addInput($enable_pop_before_smtp);
        $form->addInput($pop3_host);
        $form->addInput($pop3_port);
        $form->addInput($pop3_username);
        $form->addInput($pop3_password);


        echo '<h2>CAPTCHA ' . _t('设置') . '</h2>';
        $captchaProvider = new Typecho_Widget_Helper_Form_Element_Select('captchaProvider', array(
            'none' => _t('无'),
            'recaptcha_v2' => _t('Google reCAPTCHA v2 ("我不是机器人"复选框)'),
            'recaptcha_v3' => _t('Google reCAPTCHA v3 (隐形, 基于分数)'),
            'hcaptcha' => _t('hCaptcha ("我是人类"复选框)')
        ), 'none', _t('选择 CAPTCHA 提供商'));
        $form->addInput($captchaProvider);

        echo '<h3>Google reCAPTCHA v2 ' . _t('设置') . '</h3>';
        $recaptcha_v2_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_sitekey', NULL, '', _t('reCAPTCHA v2 Site Key (站点密钥)'));
        $recaptcha_v2_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_secretkey', NULL, '', _t('reCAPTCHA v2 Secret Key (密钥)'));
        $form->addInput($recaptcha_v2_sitekey);
        $form->addInput($recaptcha_v2_secretkey);

        echo '<h3>Google reCAPTCHA v3 ' . _t('设置') . '</h3>';
        $recaptcha_v3_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_sitekey', NULL, '', _t('reCAPTCHA v3 Site Key (站点密钥)'));
        $recaptcha_v3_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_secretkey', NULL, '', _t('reCAPTCHA v3 Secret Key (密钥)'));
        $recaptcha_v3_threshold = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_threshold', NULL, '0.5', _t('reCAPTCHA v3 分数阈值'), _t('输入0到1之间的小数，例如0.5。分数高于此阈值才视为通过。'));
        $form->addInput($recaptcha_v3_sitekey);
        $form->addInput($recaptcha_v3_secretkey);
        $form->addInput($recaptcha_v3_threshold);

        echo '<h3>hCaptcha ' . _t('设置') . '</h3>';
        $hcaptcha_sitekey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_sitekey', NULL, '', _t('hCaptcha Site Key (站点密钥)'));
        $hcaptcha_secretkey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_secretkey', NULL, '', _t('hCaptcha Secret Key (密钥)'));
        $form->addInput($hcaptcha_sitekey);
        $form->addInput($hcaptcha_secretkey);

        echo '<h2>' . _t('邮件模板设置') . '</h2>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL,
            '<h3>{username}，您好：</h3>
             <p>您在 {sitename} 提交了密码重置操作于：{requestTime}。</p>
             <hr/>
             <p>请在 1 小时内点击此链接以完成重置 <a href="{resetLink}">{resetLink}</a></p>
             <hr/>
             <p>技术支持：{sitename} 管理员</p>', // Changed from GARFIELDTOM
            _t('邮件模板'),
            _t('请使用 {username} {sitename} {requestTime} {resetLink} 作为占位符')
        );
        $form->addInput($emailTemplate);
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