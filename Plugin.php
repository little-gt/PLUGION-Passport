<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件 (Enhanced Version with UI & Advanced CAPTCHA)
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.4
 * @link https://garfieldtom.cool/
 */
class Passport_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Helper::addRoute('passport_forgot', '/passport/forgot', 'Passport_Widget', 'doForgot');
        Helper::addRoute('passport_reset', '/passport/reset', 'Passport_Widget', 'doReset');
        return _t('请配置此插件的SMTP及CAPTCHA参数, 以使您的找回密码插件生效！');
    }

    public static function deactivate()
    {
        Helper::removeRoute('passport_reset');
        Helper::removeRoute('passport_forgot');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // --- SMTP 设置 ---
        echo '<div class="passport-section">';
        echo '<h2><i class="fa fa-envelope-o"></i> ' . _t('SMTP 邮件发送设置') . '</h2>';
        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.example.com', _t('SMTP 服务器'), _t('如: smtp.exmail.qq.com, smtp.gmail.com'));
        $form->addInput($host);
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('SMTP 端口'), _t('通常 SSL 为 465, TLS 为 587, 非加密为 25'));
        $form->addInput($port);
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, 'user@example.com', _t('SMTP 帐号'), _t('如: hello@example.com'));
        $form->addInput($username);
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('SMTP 密码/授权码'));
        $form->addInput($password);
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure',array(
            'ssl' => _t('SSL 加密'),
            'tls' => _t('TLS 加密'),
            '' => _t('无加密 (不推荐)')
        ), 'ssl', _t('SMTP 安全类型'));
        $form->addInput($secure);
        echo '</div>';

        // --- POP-before-SMTP 设置 ---
        echo '<div class="passport-section">';
        echo '<h3><i class="fa fa-sign-in"></i> ' . _t('POP-before-SMTP (可选高级设置)') . '</h3>';
        $enable_pop_before_smtp = new Typecho_Widget_Helper_Form_Element_Radio('enable_pop_before_smtp',
            array(0 => _t('禁用'), 1 => _t('启用')), 0, _t('启用 POP-before-SMTP'),
            _t('部分邮件服务商可能要求先进行POP3登录才能发送SMTP邮件。如果您的SMTP设置无法发送邮件，可以尝试启用此项。'));
        $form->addInput($enable_pop_before_smtp);

        echo '<div id="pop3-settings-fields" style="display:none; border-left: 2px solid #ddd; padding-left: 20px; margin-top:10px;">';
        $pop3_host = new Typecho_Widget_Helper_Form_Element_Text('pop3_host', NULL, '', _t('POP3 服务器'), _t('如: pop.example.com'));
        $form->addInput($pop3_host);
        $pop3_port = new Typecho_Widget_Helper_Form_Element_Text('pop3_port', NULL, '110', _t('POP3 端口'), _t('通常非加密为 110, SSL 为 995'));
        $form->addInput($pop3_port);
        $pop3_username = new Typecho_Widget_Helper_Form_Element_Text('pop3_username', NULL, '', _t('POP3 帐号'), _t('通常与SMTP帐号相同，如果不同请分别填写'));
        $form->addInput($pop3_username);
        $pop3_password = new Typecho_Widget_Helper_Form_Element_Password('pop3_password', NULL, NULL, _t('POP3 密码'));
        $form->addInput($pop3_password);
        echo '</div>';
        echo '</div>';

        // --- CAPTCHA 设置 ---
        echo '<div class="passport-section">';
        echo '<h2><i class="fa fa-shield"></i> ' . _t('CAPTCHA 人机验证设置') . '</h2>';
        $captchaProvider = new Typecho_Widget_Helper_Form_Element_Select('captchaProvider', array(
            'none' => _t('无 (不推荐)'),
            'recaptcha_v2' => _t('Google reCAPTCHA v2 ("我不是机器人"复选框)'),
            'recaptcha_v3' => _t('Google reCAPTCHA v3 (隐形，首先尝试)'),
            'hcaptcha' => _t('hCaptcha ("我是人类"复选框)')
        ), 'none', _t('主要 CAPTCHA 提供商'), _t('选择一种人机验证方式以增强安全性。'));
        $form->addInput($captchaProvider);

        // Fallback CAPTCHA if v3 is primary
        echo '<div id="recaptcha-v3-fallback-settings-wrapper" style="display:none; border-left: 2px solid #673AB7; padding-left: 20px; margin-top:10px;">'; // Renamed ID for clarity
        echo '<h4>' . _t('reCAPTCHA v3 低分回退设置') . '</h4>';
        $v3_fallbackProvider = new Typecho_Widget_Helper_Form_Element_Select('v3_fallbackProvider', array(
            'none' => _t('无回退 (低分则直接失败)'),
            'recaptcha_v2' => _t('回退到 Google reCAPTCHA v2'),
            'hcaptcha' => _t('回退到 hCaptcha')
        ), 'none', _t('当 reCAPTCHA v3 分数过低时:'), _t('如果主要提供商是 reCAPTCHA v3 且分数低于阈值，将启用此回退验证。'));
        $form->addInput($v3_fallbackProvider);
        echo '</div>';

        // reCAPTCHA v2 Settings (for primary or fallback)
        echo '<div id="recaptcha-v2-settings" class="captcha-specific-settings" style="display:none; border-left: 2px solid #467B96; padding-left: 20px; margin-top:10px;">';
        echo '<h4>Google reCAPTCHA v2 ' . _t('设置') . '</h4>';
        $recaptcha_v2_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_sitekey', NULL, '', _t('Site Key (站点密钥)'), _t('从 Google reCAPTCHA 管理后台获取。'));
        $form->addInput($recaptcha_v2_sitekey);
        $recaptcha_v2_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_secretkey', NULL, '', _t('Secret Key (密钥)'), _t('从 Google reCAPTCHA 管理后台获取。'));
        $form->addInput($recaptcha_v2_secretkey);
        echo '</div>';

        // reCAPTCHA v3 Settings (only for primary)
        echo '<div id="recaptcha-v3-settings" class="captcha-specific-settings" style="display:none; border-left: 2px solid #4CAF50; padding-left: 20px; margin-top:10px;">';
        echo '<h4>Google reCAPTCHA v3 ' . _t('设置') . '</h4>';
        $recaptcha_v3_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_sitekey', NULL, '', _t('Site Key (站点密钥)'), _t('从 Google reCAPTCHA 管理后台获取。'));
        $form->addInput($recaptcha_v3_sitekey);
        $recaptcha_v3_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_secretkey', NULL, '', _t('Secret Key (密钥)'), _t('从 Google reCAPTCHA 管理后台获取。'));
        $form->addInput($recaptcha_v3_secretkey);
        $recaptcha_v3_threshold = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_threshold', NULL, '0.5', _t('分数阈值 (0.0 - 1.0)'), _t('例如0.5。分数高于此阈值才视为通过。'));
        $form->addInput($recaptcha_v3_threshold);
        echo '</div>';

        // hCaptcha Settings (for primary or fallback)
        echo '<div id="hcaptcha-settings" class="captcha-specific-settings" style="display:none; border-left: 2px solid #FF9800; padding-left: 20px; margin-top:10px;">';
        echo '<h4>hCaptcha ' . _t('设置') . '</h4>';
        $hcaptcha_sitekey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_sitekey', NULL, '', _t('Site Key (站点密钥)'), _t('从 hCaptcha 管理后台获取。'));
        $form->addInput($hcaptcha_sitekey);
        $hcaptcha_secretkey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_secretkey', NULL, '', _t('Secret Key (密钥)'), _t('从 hCaptcha 管理后台获取。'));
        $form->addInput($hcaptcha_secretkey);
        echo '</div>';
        echo '</div>';

        // --- 邮件模板设置 ---
        echo '<div class="passport-section">';
        echo '<h2><i class="fa fa-file-text-o"></i> ' . _t('邮件模板设置') . '</h2>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL,
            '<h3>亲爱的 {username}：</h3>
<p>您好！</p>
<p>我们收到了您在网站 <strong>{sitename}</strong> 于 {requestTime} 提交的密码重置请求。</p>
<p>请在接下来的一小时内点击以下链接来重置您的密码：</p>
<p><a href="{resetLink}" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #007bff; text-decoration: none; border-radius: 5px;">重置密码</a></p>
<p>如果上述按钮无法点击，请复制以下链接到您的浏览器地址栏中打开：<br>{resetLink}</p>
<p>如果您没有请求重置密码，请忽略此邮件，您的账户仍然是安全的。</p>
<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="font-size: 0.9em; color: #777;">此邮件由系统自动发送，请勿直接回复。<br>技术支持：{sitename} 管理团队</p>',
            _t('邮件模板内容'),
            _t('请使用 {username}, {sitename}, {requestTime}, {resetLink} 作为占位符。HTML可用。')
        );
        $form->addInput($emailTemplate);
        echo '</div>';

        ?>
        <style>
            .passport-section { background-color: #f9f9f9; padding: 20px; border: 1px solid #e5e5e5; border-radius: 3px; margin-bottom: 25px; }
            .passport-section h2, .passport-section h3, .passport-section h4 { border-bottom: 1px solid #e5e5e5; padding-bottom: 10px; margin-top: 0; margin-bottom: 15px; }
            .passport-section h2 .fa, .passport-section h3 .fa { margin-right: 8px; }
        </style>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var pop3EnableRadio = document.getElementsByName('enable_pop_before_smtp');
                var pop3SettingsFields = document.getElementById('pop3-settings-fields');
                function togglePop3Fields() {
                    pop3SettingsFields.style.display = (document.querySelector('input[name="enable_pop_before_smtp"]:checked').value == '1') ? 'block' : 'none';
                }
                for (var i = 0; i < pop3EnableRadio.length; i++) { pop3EnableRadio[i].addEventListener('change', togglePop3Fields); }
                togglePop3Fields();

                var captchaProviderSelect = document.getElementsByName('captchaProvider')[0];
                var v3FallbackProviderSelect = document.getElementsByName('v3_fallbackProvider')[0];
                var v3FallbackWrapper = document.getElementById('recaptcha-v3-fallback-settings-wrapper');
                var allCaptchaSpecificSettingsDivs = document.querySelectorAll('.captcha-specific-settings'); // v2, v3, hcaptcha key fields

                function toggleAllCaptchaFields() {
                    var primaryProvider = captchaProviderSelect.value;
                    var fallbackProvider = v3_fallbackProviderSelect.value;

                    // Hide all specific key setting divs first
                    allCaptchaSpecificSettingsDivs.forEach(function(div) { div.style.display = 'none'; });
                    v3FallbackWrapper.style.display = 'none'; // Hide fallback select by default

                    // Show fields for primary reCAPTCHA v3
                    if (primaryProvider === 'recaptcha_v3') {
                        document.getElementById('recaptcha-v3-settings').style.display = 'block';
                        v3FallbackWrapper.style.display = 'block'; // Show fallback select

                        // Show fields for the selected fallback provider
                        if (fallbackProvider === 'recaptcha_v2') {
                            document.getElementById('recaptcha-v2-settings').style.display = 'block';
                        } else if (fallbackProvider === 'hcaptcha') {
                            document.getElementById('hcaptcha-settings').style.display = 'block';
                        }
                    } else if (primaryProvider === 'recaptcha_v2') {
                        document.getElementById('recaptcha-v2-settings').style.display = 'block';
                    } else if (primaryProvider === 'hcaptcha') {
                        document.getElementById('hcaptcha-settings').style.display = 'block';
                    }
                }
                captchaProviderSelect.addEventListener('change', toggleAllCaptchaFields);
                v3FallbackProviderSelect.addEventListener('change', toggleAllCaptchaFields);
                toggleAllCaptchaFields(); // Initial check
            });
        </script>
        <?php
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}