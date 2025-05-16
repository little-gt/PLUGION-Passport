<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件 (Custom Section UI & Robust Conditional Logic)
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.6
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
        // --- Custom CSS for Settings Page ---
        echo <<<EOT
<style>
    .passport-config-section {
        background-color: #fff; /* Typecho panel color */
        border: 1px solid #E9E9E9; /* Typecho border color */
        padding: 15px 20px;
        border-radius: 4px; /* Consistent with Typecho panels */
        margin-bottom: 25px;
        box-shadow: 0 1px 1px rgba(0,0,0,.05); /* Subtle shadow like Typecho panels */
    }
    .passport-config-section h2, .passport-config-section h3, .passport-config-section h4 {
        font-size: 1.2em;
        color: #222; /* Darker heading color */
        border-bottom: 1px solid #E9E9E9;
        padding-bottom: 10px;
        margin-top: 0;
        margin-bottom: 20px; /* More space after heading */
        font-weight: 600; /* Bolder headings */
    }
    .passport-config-section h2 .icon, .passport-config-section h3 .icon, .passport-config-section h4 .icon {
        margin-right: 8px;
        color: #666; /* Icon color */
    }
    .passport-config-section .typecho-option { /* Ensure Typecho options look okay inside custom section */
        margin-bottom:15px;
    }
    .passport-config-section .description {
        color: #999;
        font-size: .92857em;
    }
    .passport-nested-group {
        margin-left: 10px; /* Slightly indent nested groups */
        padding-left: 15px; 
        margin-top: 10px;
        margin-bottom: 15px;
        /* border-left: 3px solid #E9E9E9; */ /* Subtle left border for nesting */
    }
    .passport-nested-group h4 {
        font-size: 1.1em;
        color: #444;
        margin-bottom: 15px;
        border-bottom: none; /* No border for sub-sub-headings */
        padding-bottom: 5px;
    }
</style>
EOT;

        // --- SMTP 设置 ---
        echo '<div class="passport-config-section">';
        echo '<h2><i class="icon icon-envelope-alt"></i> ' . _t('SMTP 邮件发送设置') . '</h2>';
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
        echo '</div>'; // End SMTP Section


        // --- POP-before-SMTP 设置 ---
        echo '<div class="passport-config-section">';
        echo '<h2><i class="icon icon-signin"></i> ' . _t('POP-before-SMTP (可选高级设置)') . '</h2>';
        $enable_pop_before_smtp = new Typecho_Widget_Helper_Form_Element_Radio('enable_pop_before_smtp',
            array(0 => _t('禁用'), 1 => _t('启用')), 0, _t('启用 POP-before-SMTP'),
            _t('部分邮件服务商可能要求先进行POP3登录才能发送SMTP邮件。'));
        $form->addInput($enable_pop_before_smtp->setAttribute('id', 'passport-enable_pop_before_smtp'));

        echo '<div id="passport-pop3-fields-container" class="passport-nested-group" style="display:none;">'; // This div is toggled
        $pop3_host = new Typecho_Widget_Helper_Form_Element_Text('pop3_host', NULL, '', _t('POP3 服务器'), _t('如: pop.example.com'));
        $form->addInput($pop3_host);
        $pop3_port = new Typecho_Widget_Helper_Form_Element_Text('pop3_port', NULL, '110', _t('POP3 端口'), _t('通常非加密为 110, SSL 为 995'));
        $form->addInput($pop3_port);
        $pop3_username = new Typecho_Widget_Helper_Form_Element_Text('pop3_username', NULL, '', _t('POP3 帐号'), _t('通常与SMTP帐号相同'));
        $form->addInput($pop3_username);
        $pop3_password = new Typecho_Widget_Helper_Form_Element_Password('pop3_password', NULL, NULL, _t('POP3 密码'));
        $form->addInput($pop3_password);
        echo '</div>'; // End pop3-fields-container
        echo '</div>'; // End POP3 Section


        // --- CAPTCHA 设置 ---
        echo '<div class="passport-config-section">';
        echo '<h2><i class="icon icon-shield"></i> ' . _t('CAPTCHA 人机验证设置') . '</h2>';
        $captchaProvider = new Typecho_Widget_Helper_Form_Element_Select('captchaProvider', array(
            'none' => _t('无 (不推荐)'),
            'recaptcha_v2' => _t('Google reCAPTCHA v2'),
            'recaptcha_v3' => _t('Google reCAPTCHA v3 (首先尝试)'),
            'hcaptcha' => _t('hCaptcha')
        ), 'none', _t('主要 CAPTCHA 提供商'));
        $form->addInput($captchaProvider->setAttribute('id', 'passport-captchaProvider-select'));

        // This entire <li> for v3_fallbackProvider will be toggled
        $v3_fallbackProvider_input = new Typecho_Widget_Helper_Form_Element_Select('v3_fallbackProvider', array(
            'none' => _t('无回退 (低分则直接失败)'),
            'recaptcha_v2' => _t('回退到 Google reCAPTCHA v2'),
            'hcaptcha' => _t('回退到 hCaptcha')
        ), 'none', _t('当 reCAPTCHA v3 分数过低时:'), _t('仅当主要提供商是 reCAPTCHA v3 时生效。'));
        $form->addInput($v3_fallbackProvider_input->setAttribute('id', 'passport-v3_fallbackProvider-select'));

        // Sub-containers for specific CAPTCHA keys
        echo '<div id="passport-recaptcha-v2-key-container" class="passport-nested-group" style="display:none;">';
        echo '<h4>' . _t('Google reCAPTCHA v2 密钥') . '</h4>';
        $recaptcha_v2_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_sitekey', NULL, '', _t('Site Key (站点密钥)'));
        $form->addInput($recaptcha_v2_sitekey);
        $recaptcha_v2_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_secretkey', NULL, '', _t('Secret Key (密钥)'));
        $form->addInput($recaptcha_v2_secretkey);
        echo '</div>';

        echo '<div id="passport-recaptcha-v3-key-container" class="passport-nested-group" style="display:none;">';
        echo '<h4>' . _t('Google reCAPTCHA v3 密钥与阈值') . '</h4>';
        $recaptcha_v3_sitekey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_sitekey', NULL, '', _t('Site Key (站点密钥)'));
        $form->addInput($recaptcha_v3_sitekey);
        $recaptcha_v3_secretkey = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_secretkey', NULL, '', _t('Secret Key (密钥)'));
        $form->addInput($recaptcha_v3_secretkey);
        $recaptcha_v3_threshold = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_threshold', NULL, '0.5', _t('分数阈值 (0.0-1.0)'));
        $form->addInput($recaptcha_v3_threshold);
        echo '</div>';

        echo '<div id="passport-hcaptcha-key-container" class="passport-nested-group" style="display:none;">';
        echo '<h4>' . _t('hCaptcha 密钥') . '</h4>';
        $hcaptcha_sitekey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_sitekey', NULL, '', _t('Site Key (站点密钥)'));
        $form->addInput($hcaptcha_sitekey);
        $hcaptcha_secretkey = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_secretkey', NULL, '', _t('Secret Key (密钥)'));
        $form->addInput($hcaptcha_secretkey);
        echo '</div>';
        echo '</div>'; // End CAPTCHA Section


        // --- 邮件模板设置 ---
        echo '<div class="passport-config-section">';
        echo '<h2><i class="icon icon-file-text-alt"></i> ' . _t('邮件模板设置') . '</h2>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL,
            '<h3>亲爱的 {username}：</h3><p>您好！</p><p>我们收到了您在网站 <strong>{sitename}</strong> 于 {requestTime} 提交的密码重置请求。</p><p>请在接下来的一小时内点击以下链接来重置您的密码：</p><p><a href="{resetLink}" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #007bff; text-decoration: none; border-radius: 5px;">重置密码</a></p><p>如果上述按钮无法点击，请复制以下链接到您的浏览器地址栏中打开：<br>{resetLink}</p><p>如果您没有请求重置密码，请忽略此邮件，您的账户仍然是安全的。</p><hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;"><p style="font-size: 0.9em; color: #777;">此邮件由系统自动发送，请勿直接回复。<br>技术支持：{sitename} 管理团队</p>',
            _t('邮件模板内容'),
            _t('HTML可用。占位符: {username}, {sitename}, {requestTime}, {resetLink}')
        );
        $form->addInput($emailTemplate);
        echo '</div>'; // End Email Template Section

        // --- JavaScript for conditional display ---
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Helper to get the Typecho generated <li> or <ul> wrapper for an input
                function getInputWrapper(elementId) {
                    var el = document.getElementById(elementId);
                    if (!el) return null;
                    // Try to find the <ul> parent with class 'typecho-option'
                    var current = el;
                    while (current.parentNode) {
                        current = current.parentNode;
                        if (current.tagName === 'UL' && current.classList.contains('typecho-option')) {
                            return current;
                        }
                        // Fallback for elements like radio buttons which might be in a div inside li
                        if (current.tagName === 'LI' && current.parentNode && current.parentNode.classList.contains('typecho-option')) {
                            return current.parentNode; // Return UL
                        }
                    }
                    // If not found, try to find the closest <li> (less ideal but a fallback)
                    current = el;
                    while (current.parentNode) {
                        current = current.parentNode;
                        if (current.tagName === 'LI') return current;
                    }
                    return null;
                }

                function toggleElementWrapper(elementId, show) {
                    var wrapper = getInputWrapper(elementId);
                    if (wrapper) {
                        wrapper.style.display = show ? '' : 'none';
                    } else {
                        // console.warn('Wrapper not found for element:', elementId);
                    }
                }

                function toggleDirectDiv(divId, show) {
                    var div = document.getElementById(divId);
                    if (div) {
                        div.style.display = show ? 'block' : 'none';
                    }
                }

                // POP3 conditional display
                var pop3EnableRadio = document.getElementById('passport-enable_pop_before_smtp-1'); // Assuming ID is '...-1' for '启用'
                var pop3DisableRadio = document.getElementById('passport-enable_pop_before_smtp-0'); // Assuming ID is '...-0' for '禁用'
                var pop3FieldsContainer = document.getElementById('passport-pop3-fields-container');

                function handlePop3Display() {
                    var pop3Enabled = document.querySelector('input[name="enable_pop_before_smtp"]:checked').value === '1';
                    toggleDirectDiv('passport-pop3-fields-container', pop3Enabled);
                }
                if(pop3EnableRadio && pop3DisableRadio) { // Ensure radio buttons are found
                    document.querySelectorAll('input[name="enable_pop_before_smtp"]').forEach(function(radio) {
                        radio.addEventListener('change', handlePop3Display);
                    });
                    handlePop3Display(); // Initial state
                }


                // CAPTCHA conditional display
                var primaryCaptchaSelect = document.getElementById('passport-captchaProvider-select');
                var v3FallbackSelect = document.getElementById('passport-v3_fallbackProvider-select'); // The select element itself

                var v2KeyContainer = 'passport-recaptcha-v2-key-container';
                var v3KeyContainer = 'passport-recaptcha-v3-key-container';
                var hCaptchaKeyContainer = 'passport-hcaptcha-key-container';

                function handleCaptchaDisplay() {
                    var primaryProvider = primaryCaptchaSelect.value;
                    var v3FallbackProviderValue = v3FallbackSelect.value;

                    // Hide all specific CAPTCHA key containers and the v3 fallback select's wrapper first
                    toggleDirectDiv(v2KeyContainer, false);
                    toggleDirectDiv(v3KeyContainer, false);
                    toggleDirectDiv(hCaptchaKeyContainer, false);
                    toggleElementWrapper('passport-v3_fallbackProvider-select', false); // Hide the wrapper of the fallback select

                    if (primaryProvider === 'recaptcha_v3') {
                        toggleDirectDiv(v3KeyContainer, true);
                        toggleElementWrapper('passport-v3_fallbackProvider-select', true); // Show v3 fallback select's wrapper

                        if (v3FallbackProviderValue === 'recaptcha_v2') {
                            toggleDirectDiv(v2KeyContainer, true);
                        } else if (v3FallbackProviderValue === 'hcaptcha') {
                            toggleDirectDiv(hCaptchaKeyContainer, true);
                        }
                    } else if (primaryProvider === 'recaptcha_v2') {
                        toggleDirectDiv(v2KeyContainer, true);
                    } else if (primaryProvider === 'hcaptcha') {
                        toggleDirectDiv(hCaptchaKeyContainer, true);
                    }
                }

                if(primaryCaptchaSelect && v3FallbackSelect){ // Ensure selects are found
                    primaryCaptchaSelect.addEventListener('change', handleCaptchaDisplay);
                    v3FallbackSelect.addEventListener('change', handleCaptchaDisplay);
                    handleCaptchaDisplay(); // Initial state
                }
            });
        </script>
        <?php
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}