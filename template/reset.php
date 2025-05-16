<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common.php';
$plugin_config = Typecho_Widget::widget('Widget_Options')->plugin('Passport');
$menu->title = _t('重置密码');
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
                    <div class="typecho-page-title"><h2><?php _e('重置密码'); ?></h2></div>
                    <?php
                    $reset_form = $passportWidget->resetForm(); // This form's action URL includes the token
                    ob_start(); $reset_form->render(); $form_html = ob_get_clean();
                    $csrf_input = '';
                    if (method_exists($passportWidget->security, 'getTokenInput')) {
                        $csrf_input = $passportWidget->security->getTokenInput();
                    }

                    $submit_marker = '<button type="submit"'; $pos_submit = strripos($form_html, $submit_marker);
                    if ($pos_submit !== false) {
                        $ul_submit_start_marker = '<ul class="typecho-option typecho-option-submit"';
                        $pos_ul_submit_start = strripos(substr($form_html, 0, $pos_submit), $ul_submit_start_marker);
                        if ($pos_ul_submit_start !== false) $form_html = substr_replace($form_html, $csrf_input, $pos_ul_submit_start, 0);
                        else $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);
                    } else $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);

                    if ($passportWidget->show_fallback_captcha) {
                        $fallback_input = '<input type="hidden" name="is_fallback_submission" value="1" />';
                        // The original reset token is already in the form's action URL from resetForm()
                        if ($pos_ul_submit_start !== false) $form_html = substr_replace($form_html, $fallback_input, $pos_ul_submit_start, 0);
                        else $form_html = str_replace('</form>', $fallback_input . '</form>', $form_html);
                    }
                    echo $form_html;
                    ?>

                    <?php
                    $display_captcha_provider_reset = '';
                    $captcha_site_key_reset = '';
                    if ($passportWidget->show_fallback_captcha && $plugin_config->captchaProvider == 'recaptcha_v3') {
                        $display_captcha_provider_reset = $plugin_config->v3_fallbackProvider;
                    } elseif (!$passportWidget->show_fallback_captcha && $plugin_config->captchaProvider != 'recaptcha_v3' && $plugin_config->captchaProvider != 'none') {
                        $display_captcha_provider_reset = $plugin_config->captchaProvider;
                    }

                    if ($display_captcha_provider_reset == 'recaptcha_v2') $captcha_site_key_reset = $plugin_config->recaptcha_v2_sitekey;
                    elseif ($display_captcha_provider_reset == 'hcaptcha') $captcha_site_key_reset = $plugin_config->hcaptcha_sitekey;
                    ?>

                    <?php if (!empty($display_captcha_provider_reset) && !empty($captcha_site_key_reset)): ?>
                        <div class="captcha-container">
                            <?php if ($display_captcha_provider_reset == 'recaptcha_v2'): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($captcha_site_key_reset); ?>"></div>
                            <?php elseif ($display_captcha_provider_reset == 'hcaptcha'): ?>
                                <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars($captcha_site_key_reset); ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$passportWidget->show_fallback_captcha && $plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)): ?>
                        <input type="hidden" name="recaptcha_v3_token" id="recaptcha_v3_token_reset">
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
if ($display_captcha_provider_reset == 'recaptcha_v2') echo '<script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>';
elseif ($display_captcha_provider_reset == 'hcaptcha') echo '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';

if ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)) {
    $resetUrlWithTokenForJS = Typecho_Common::url('/passport/reset?token=' . urlencode($passportWidget->request->get('token')), $passportWidget->options->index);
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js?render=' . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . '"></script>';
    echo "<script>
    var resetFormEl = document.querySelector('form[action=\"" . htmlspecialchars($resetUrlWithTokenForJS, ENT_QUOTES) . "\"]');
    if (resetFormEl) {
        var v3TokenFieldReset = document.getElementById('recaptcha_v3_token_reset');
        if (v3TokenFieldReset) { // Only attach if v3 token field is present (i.e., not in fallback mode)
            resetFormEl.addEventListener('submit', function(event) {
                if (!v3TokenFieldReset.value) {
                    event.preventDefault();
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . "', {action: 'submit_new_password'}).then(function(token) {
                            v3TokenFieldReset.value = token;
                            resetFormEl.submit();
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