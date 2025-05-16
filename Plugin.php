<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 密码找回插件 (Settings UI Reverted & Conditional Logic Fixed)
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.0.5
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
        echo '<h3>' . _t('SMTP 邮件发送设置') . '</h3>';
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

        echo '<br><h3>' . _t('POP-before-SMTP (可选高级设置)') . '</h3>';
        // Note: Name attribute is used for JS selection of radio buttons
        $enable_pop_before_smtp = new Typecho_Widget_Helper_Form_Element_Radio('enable_pop_before_smtp',
            array(0 => _t('禁用'), 1 => _t('启用')), 0, _t('启用 POP-before-SMTP'),
            _t('部分邮件服务商可能要求先进行POP3登录才能发送SMTP邮件。'));
        $form->addInput($enable_pop_before_smtp);

        // Assign IDs directly to the input elements for easier JS targeting
        $pop3_host_input = new Typecho_Widget_Helper_Form_Element_Text('pop3_host', NULL, '', _t('POP3 服务器'), _t('如: pop.example.com'));
        $form->addInput($pop3_host_input->setAttribute('id', 'passport-pop3_host-input')); // Suffix with -input for clarity

        $pop3_port_input = new Typecho_Widget_Helper_Form_Element_Text('pop3_port', NULL, '110', _t('POP3 端口'), _t('通常非加密为 110, SSL 为 995'));
        $form->addInput($pop3_port_input->setAttribute('id', 'passport-pop3_port-input'));

        $pop3_username_input = new Typecho_Widget_Helper_Form_Element_Text('pop3_username', NULL, '', _t('POP3 帐号'), _t('通常与SMTP帐号相同'));
        $form->addInput($pop3_username_input->setAttribute('id', 'passport-pop3_username-input'));

        $pop3_password_input = new Typecho_Widget_Helper_Form_Element_Password('pop3_password', NULL, NULL, _t('POP3 密码'));
        $form->addInput($pop3_password_input->setAttribute('id', 'passport-pop3_password-input'));


        echo '<br><h3>' . _t('CAPTCHA 人机验证设置') . '</h3>';
        $captchaProvider = new Typecho_Widget_Helper_Form_Element_Select('captchaProvider', array(
            'none' => _t('无 (不推荐)'),
            'recaptcha_v2' => _t('Google reCAPTCHA v2'),
            'recaptcha_v3' => _t('Google reCAPTCHA v3 (首先尝试)'),
            'hcaptcha' => _t('hCaptcha')
        ), 'none', _t('主要 CAPTCHA 提供商'));
        $form->addInput($captchaProvider->setAttribute('id', 'passport-captchaProvider-select')); // ID for the select itself

        $v3_fallbackProvider_input = new Typecho_Widget_Helper_Form_Element_Select('v3_fallbackProvider', array(
            'none' => _t('无回退 (低分则直接失败)'),
            'recaptcha_v2' => _t('回退到 Google reCAPTCHA v2'),
            'hcaptcha' => _t('回退到 hCaptcha')
        ), 'none', _t('当 reCAPTCHA v3 分数过低时:'), _t('仅当主要提供商是 reCAPTCHA v3 时生效。'));
        $form->addInput($v3_fallbackProvider_input->setAttribute('id', 'passport-v3_fallbackProvider-select'));

        $recaptcha_v2_sitekey_input = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_sitekey', NULL, '', _t('reCAPTCHA v2 Site Key'));
        $form->addInput($recaptcha_v2_sitekey_input->setAttribute('id', 'passport-recaptcha_v2_sitekey-input'));
        $recaptcha_v2_secretkey_input = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v2_secretkey', NULL, '', _t('reCAPTCHA v2 Secret Key'));
        $form->addInput($recaptcha_v2_secretkey_input->setAttribute('id', 'passport-recaptcha_v2_secretkey-input'));

        $recaptcha_v3_sitekey_input = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_sitekey', NULL, '', _t('reCAPTCHA v3 Site Key'));
        $form->addInput($recaptcha_v3_sitekey_input->setAttribute('id', 'passport-recaptcha_v3_sitekey-input'));
        $recaptcha_v3_secretkey_input = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_secretkey', NULL, '', _t('reCAPTCHA v3 Secret Key'));
        $form->addInput($recaptcha_v3_secretkey_input->setAttribute('id', 'passport-recaptcha_v3_secretkey-input'));
        $recaptcha_v3_threshold_input = new Typecho_Widget_Helper_Form_Element_Text('recaptcha_v3_threshold', NULL, '0.5', _t('reCAPTCHA v3 分数阈值 (0.0-1.0)'));
        $form->addInput($recaptcha_v3_threshold_input->setAttribute('id', 'passport-recaptcha_v3_threshold-input'));

        $hcaptcha_sitekey_input = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_sitekey', NULL, '', _t('hCaptcha Site Key'));
        $form->addInput($hcaptcha_sitekey_input->setAttribute('id', 'passport-hcaptcha_sitekey-input'));
        $hcaptcha_secretkey_input = new Typecho_Widget_Helper_Form_Element_Text('hcaptcha_secretkey', NULL, '', _t('hCaptcha Secret Key'));
        $form->addInput($hcaptcha_secretkey_input->setAttribute('id', 'passport-hcaptcha_secretkey-input'));

        echo '<br><h3>' . _t('邮件模板设置') . '</h3>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL,
            '<h3>亲爱的 {username}：</h3><p>您好！</p><p>我们收到了您在网站 <strong>{sitename}</strong> 于 {requestTime} 提交的密码重置请求。</p><p>请在接下来的一小时内点击以下链接来重置您的密码：</p><p><a href="{resetLink}" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #007bff; text-decoration: none; border-radius: 5px;">重置密码</a></p><p>如果上述按钮无法点击，请复制以下链接到您的浏览器地址栏中打开：<br>{resetLink}</p><p>如果您没有请求重置密码，请忽略此邮件，您的账户仍然是安全的。</p><hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;"><p style="font-size: 0.9em; color: #777;">此邮件由系统自动发送，请勿直接回复。<br>技术支持：{sitename} 管理团队</p>',
            _t('邮件模板内容'),
            _t('HTML可用。占位符: {username}, {sitename}, {requestTime}, {resetLink}')
        );
        $form->addInput($emailTemplate);

        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Helper function to get the Typecho <li> wrapper for an input element by the input's ID
                function getInputLiWrapper(inputId) {
                    var inputElement = document.getElementById(inputId);
                    if (inputElement) {
                        // Typecho form inputs are usually inside a <label> or directly in <li>, which is inside <ul>.
                        // The structure is <ul class="typecho-option" id="typecho-option-item-..."><li class="typecho-option-item">...<input id="inputId">...</li></ul>
                        // Or for radio/select: <ul ...><li><label>...</label><div><select/input id="inputId">...</div></li></ul>
                        var currentElement = inputElement;
                        while (currentElement.parentElement) {
                            if (currentElement.parentElement.tagName === 'LI' && currentElement.parentElement.parentElement.classList.contains('typecho-option')) {
                                return currentElement.parentElement.parentElement; // Return the <ul>
                            }
                            // Fallback for simpler structures if the above isn't met, aiming for the <li>
                            if (currentElement.parentElement.tagName === 'LI') {
                                return currentElement.parentElement;
                            }
                            currentElement = currentElement.parentElement;
                        }
                    }
                    return null; // Element or its wrapper not found
                }

                function toggleElementDisplay(inputId, show) {
                    var wrapper = getInputLiWrapper(inputId);
                    if (wrapper) {
                        wrapper.style.display = show ? '' : 'none';
                    }
                }

                // POP3 conditional display
                var pop3RadioName = 'enable_pop_before_smtp';
                var pop3InputIds = [
                    'passport-pop3_host-input',
                    'passport-pop3_port-input',
                    'passport-pop3_username-input',
                    'passport-pop3_password-input'
                ];

                function handlePop3Display() {
                    var pop3Enabled = document.querySelector('input[name="' + pop3RadioName + '"]:checked').value === '1';
                    pop3InputIds.forEach(function(id) {
                        toggleElementDisplay(id, pop3Enabled);
                    });
                }
                document.querySelectorAll('input[name="' + pop3RadioName + '"]').forEach(function(radio) {
                    radio.addEventListener('change', handlePop3Display);
                });
                handlePop3Display(); // Initial state

                // CAPTCHA conditional display
                var primaryCaptchaSelect = document.getElementById('passport-captchaProvider-select');
                var v3FallbackSelect = document.getElementById('passport-v3_fallbackProvider-select');

                var v2InputIds = ['passport-recaptcha_v2_sitekey-input', 'passport-recaptcha_v2_secretkey-input'];
                var v3InputIds = ['passport-recaptcha_v3_sitekey-input', 'passport-recaptcha_v3_secretkey-input', 'passport-recaptcha_v3_threshold-input'];
                var hCaptchaInputIds = ['passport-hcaptcha_sitekey-input', 'passport-hcaptcha_secretkey-input'];

                function handleCaptchaDisplay() {
                    var primaryProvider = primaryCaptchaSelect.value;
                    var v3FallbackProvider = v3FallbackSelect.value;

                    // Hide all specific CAPTCHA input groups and the v3 fallback select itself first
                    v2InputIds.forEach(function(id) { toggleElementDisplay(id, false); });
                    v3InputIds.forEach(function(id) { toggleElementDisplay(id, false); });
                    hCaptchaInputIds.forEach(function(id) { toggleElementDisplay(id, false); });
                    toggleElementDisplay('passport-v3_fallbackProvider-select', false); // Hide the fallback select by default

                    if (primaryProvider === 'recaptcha_v3') {
                        v3InputIds.forEach(function(id) { toggleElementDisplay(id, true); });
                        toggleElementDisplay('passport-v3_fallbackProvider-select', true); // Show v3 fallback select

                        if (v3FallbackProvider === 'recaptcha_v2') {
                            v2InputIds.forEach(function(id) { toggleElementDisplay(id, true); });
                        } else if (v3FallbackProvider === 'hcaptcha') {
                            hCaptchaInputIds.forEach(function(id) { toggleElementDisplay(id, true); });
                        }
                    } else if (primaryProvider === 'recaptcha_v2') {
                        v2InputIds.forEach(function(id) { toggleElementDisplay(id, true); });
                    } else if (primaryProvider === 'hcaptcha') {
                        hCaptchaInputIds.forEach(function(id) { toggleElementDisplay(id, true); });
                    }
                }

                primaryCaptchaSelect.addEventListener('change', handleCaptchaDisplay);
                v3FallbackSelect.addEventListener('change', handleCaptchaDisplay);
                handleCaptchaDisplay(); // Initial state
            });
        </script>
        <?php
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}