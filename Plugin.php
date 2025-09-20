<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.4
 * @link https://garfieldtom.cool/
 */
class Passport_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        Helper::addRoute('passport_forgot', '/passport/forgot', 'Passport_Widget', 'doForgot');
        Helper::addRoute('passport_reset', '/passport/reset', 'Passport_Widget', 'doReset');
        return _t('请配置此插件的SMTP、验证码参数和HMAC密钥, 以使您的找回密码插件生效！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        Helper::removeRoute('passport_reset');
        Helper::removeRoute('passport_forgot');
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 邮件服务器信息
        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, '', _t('服务器(SMTP)'), _t('如: smtp.exmail.qq.com'));
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('端口'), _t('如: 25、465(SSL)、587(SSL)'));
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, '', _t('帐号'), _t('如: hello@example.com'));
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('密码'));
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure', array('ssl' => _t('SSL'), 'tls' => _t('TLS'), 'none' => _t('无')), 'ssl', _t('安全类型'));

        $form->addInput($host);
        $form->addInput($port);
        $form->addInput($username);
        $form->addInput($password);
        $form->addInput($secure);

        // 验证码类型选择
        $captchaType = new Typecho_Widget_Helper_Form_Element_Select('captchaType', array(
            'none' => _t('不使用验证码'),
            'recaptcha' => _t('Google reCAPTCHA v2'),
            'hcaptcha' => _t('hCaptcha'),
            'geetest' => _t('Geetest v4 (极验验证)')
        ), 'none', _t('验证码类型'), _t('选择您要使用的验证码服务。'));
        $form->addInput($captchaType);

        // reCAPTCHA v2 验证码配置信息
        $sitekeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyRecaptcha', NULL, '', _t('reCAPTCHA Site Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));
        $secretkeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyRecaptcha', NULL, '', _t('reCAPTCHA Secret Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));
        $form->addInput($sitekeyRecaptcha);
        $form->addInput($secretkeyRecaptcha);

        // hCaptcha 验证码配置信息
        $sitekeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyHcaptcha', NULL, '', _t('hCaptcha Site Key'), _t('访问 <a href="https://www.hcaptcha.com/signup" target="_blank">hCaptcha 控制台</a> 获取。'));
        $secretkeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyHcaptcha', NULL, '', _t('hCaptcha Secret Key'), _t('访问 <a href="https://www.hcaptcha.com/signup" target="_blank">hCaptcha 控制台</a> 获取。'));
        $form->addInput($sitekeyHcaptcha);
        $form->addInput($secretkeyHcaptcha);

        // Geetest v4 验证码配置信息
        $captchaIdGeetest = new Typecho_Widget_Helper_Form_Element_Text('captchaIdGeetest', NULL, '', _t('Geetest CAPTCHA ID'), _t('在极验后台获取您的 CAPTCHA ID。'));
        $captchaKeyGeetest = new Typecho_Widget_Helper_Form_Element_Text('captchaKeyGeetest', NULL, '', _t('Geetest CAPTCHA KEY'), _t('在极验后台获取您的 CAPTCHA KEY (私钥)。'));
        $form->addInput($captchaIdGeetest);
        $form->addInput($captchaKeyGeetest);

        // HMAC 密钥配置
        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey', NULL, '', _t('HMAC 密钥'), _t('用于令牌签名验证的密钥，建议使用 32 位随机含数字、大小写字母的字符串。留空将禁用签名验证（不推荐）。'));
        $form->addInput($secretKey);

        // 邮件模板
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
        $form->addInput($emailTemplate);

        // 用于动态显示/隐藏配置的JavaScript (已修复)
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // 映射验证码类型到对应的输入框name属性
                const settingsMap = {
                    recaptcha: ['sitekeyRecaptcha', 'secretkeyRecaptcha'],
                    hcaptcha: ['sitekeyHcaptcha', 'secretkeyHcaptcha'],
                    geetest: ['captchaIdGeetest', 'captchaKeyGeetest']
                };

                const captchaTypeSelector = document.querySelector('[name=captchaType]');

                function toggleCaptchaSettings() {
                    const selectedType = captchaTypeSelector.value;

                    // 遍历映射，隐藏所有验证码设置项
                    for (const type in settingsMap) {
                        settingsMap[type].forEach(name => {
                            const input = document.querySelector(`[name=${name}]`);
                            if (input) {
                                // Typecho将每个输入框包裹在 <li> 元素中，我们操作这个 li
                                const parentLi = input.closest('li');
                                if (parentLi) {
                                    parentLi.style.display = 'none';
                                }
                            }
                        });
                    }

                    // 如果选择了某个验证码类型，则显示对应的设置项
                    if (settingsMap[selectedType]) {
                        settingsMap[selectedType].forEach(name => {
                            const input = document.querySelector(`[name=${name}]`);
                            if (input) {
                                const parentLi = input.closest('li');
                                if (parentLi) {
                                    // 恢复显示
                                    parentLi.style.display = '';
                                }
                            }
                        });
                    }
                }

                // 页面加载时立即执行一次，以保证初始状态正确
                toggleCaptchaSettings();

                // 当下拉框选项改变时，再次执行
                captchaTypeSelector.addEventListener('change', toggleCaptchaSettings);
            });
        </script>
        <?php
    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
}