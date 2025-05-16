<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common.php';
$plugin_config = Typecho_Widget::widget('Widget_Options')->plugin('Passport');
$menu->title = _t('找回密码');
// It's important $this refers to Passport_Widget instance here.
// If $this is not available or refers to another widget, explicitly get Passport_Widget.
$passportWidget = Typecho_Widget::widget('Passport_Widget');
include __TYPECHO_ADMIN_DIR__ . 'header.php';
?>
    <style>
        body { font-family: "Microsoft YaHei", tahoma, arial, 'Hiragino Sans GB', '\5b8b\4f53', sans-serif; }
        .typecho-logo { margin: 50px 0 30px; text-align: center; }
        .typecho-table-wrap { padding: 50px 30px; }
        .typecho-page-title h2 { margin: 0 0 30px; font-weight: 500; font-size: 20px; text-align: center; }
        .btn { width: 100%; height: auto; padding: 10px 16px; font-size: 18px; line-height: 1.33; }
        .captcha-container { margin-bottom: 15px; display: flex; justify-content: center; }
    </style>
    <div class="body container">
        <div class="typecho-logo"><h1><a href="<?php $passportWidget->options->siteUrl(); ?>"><?php $passportWidget->options->title(); ?></a></h1></div>
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-6 col-tb-offset-3 typecho-content-panel">
                <?php Typecho_Widget::widget('Widget_Notice')->listMessages(); ?>
                <div class="typecho-table-wrap">
                    <div class="typecho-page-title"><h2><?php _e('找回密码'); ?></h2></div>
                    <?php
                    $forgot_form = $passportWidget->forgotForm();
                    ob_start(); $forgot_form->render(); $form_html = ob_get_clean();
                    $csrf_input = '';
                    if (method_exists($passportWidget->security, 'getTokenInput')) { // Ensure security object and method exist
                        $csrf_input = $passportWidget->security->getTokenInput();
                    }

                    $submit_marker = '<button type="submit"'; $pos_submit = strripos($form_html, $submit_marker);
                    if ($pos_submit !== false) {
                        $ul_submit_start_marker = '<ul class="typecho-option typecho-option-submit"';
                        $pos_ul_submit_start = strripos(substr($form_html, 0, $pos_submit), $ul_submit_start_marker);
                        if ($pos_ul_submit_start !== false) $form_html = substr_replace($form_html, $csrf_input, $pos_ul_submit_start, 0);
                        else $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);
                    } else $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);

                    // Add hidden field for fallback submission if needed
                    if ($passportWidget->show_fallback_captcha) {
                        $fallback_input = '<input type="hidden" name="is_fallback_submission" value="1" />';
                        // Try to inject it before CSRF or before submit
                        if ($pos_ul_submit_start !== false) $form_html = substr_replace($form_html, $fallback_input, $pos_ul_submit_start, 0);
                        else $form_html = str_replace('</form>', $fallback_input . '</form>', $form_html);
                    }
                    echo $form_html;
                    ?>

                    <?php
                    $display_captcha_provider = '';
                    $captcha_site_key = '';
                    // Determine which CAPTCHA to display (primary non-v3, or fallback)
                    if ($passportWidget->show_fallback_captcha && $plugin_config->captchaProvider == 'recaptcha_v3') {
                        $display_captcha_provider = $plugin_config->v3_fallbackProvider;
                    } elseif (!$passportWidget->show_fallback_captcha && $plugin_config->captchaProvider != 'recaptcha_v3' && $plugin_config->captchaProvider != 'none') {
                        $display_captcha_provider = $plugin_config->captchaProvider;
                    }

                    if ($display_captcha_provider == 'recaptcha_v2') $captcha_site_key = $plugin_config->recaptcha_v2_sitekey;
                    elseif ($display_captcha_provider == 'hcaptcha') $captcha_site_key = $plugin_config->hcaptcha_sitekey;
                    ?>

                    <?php if (!empty($display_captcha_provider) && !empty($captcha_site_key)): ?>
                        <div class="captcha-container">
                            <?php if ($display_captcha_provider == 'recaptcha_v2'): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($captcha_site_key); ?>"></div>
                            <?php elseif ($display_captcha_provider == 'hcaptcha'): ?>
                                <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars($captcha_site_key); ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$passportWidget->show_fallback_captcha && $plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)): ?>
                        <input type="hidden" name="recaptcha_v3_token" id="recaptcha_v3_token_forgot">
                    <?php endif; ?>

                    <p class="more-link" style="text-align:center; margin-top: 20px;">
                        <a href="<?php $passportWidget->options->siteUrl(); ?>"><?php _e('返回首页'); ?></a> •
                        <a href="<?php $passportWidget->options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common-js.php';
if ($display_captcha_provider == 'recaptcha_v2') echo '<script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>';
elseif ($display_captcha_provider == 'hcaptcha') echo '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';

// reCAPTCHA v3 script - always include if v3 is primary, for the initial (non-fallback) submission
if ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)) {
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js?render=' . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . '"></script>';
    echo "<script>
    var forgotFormEl = document.querySelector('form[action=\"" . htmlspecialchars($passportWidget->options->forgotUrl, ENT_QUOTES) . "\"]');
    if (forgotFormEl) {
        var v3TokenFieldForgot = document.getElementById('recaptcha_v3_token_forgot');
        // Only attach submit listener if not already in fallback mode
        if (v3TokenFieldForgot) { 
            forgotFormEl.addEventListener('submit', function(event) {
                if (!v3TokenFieldForgot.value) { // Only execute if token not already set
                    event.preventDefault();
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . "', {action: 'forgot_password'}).then(function(token) {
                            v3TokenFieldForgot.value = token;
                            forgotFormEl.submit();
                        });
                    });
                }
            });
        }
    }
    </script>";
}
include __TYPECHO_ADMIN_DIR__ . 'footer.php';
?>