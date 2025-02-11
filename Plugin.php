<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.1
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

        return _t('请配置此插件的SMTP及reCAPTCHA参数, 以使您的找回密码插件生效！');
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

        //reCAPTCHA v2 验证码配置信息
        $sitekey = new Typecho_Widget_Helper_Form_Element_Text('sitekey', NULL, '', _t('reCAPTCHA Site Key'));
        $secretkey = new Typecho_Widget_Helper_Form_Element_Text('secretkey', NULL, '', _t('reCAPTCHA Secret Key'));

        // 添加发送邮件的模板设置
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
        $form->addInput($sitekey);
        $form->addInput($secretkey);
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
