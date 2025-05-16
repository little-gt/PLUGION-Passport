<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common.php';

$plugin_config = Typecho_Widget::widget('Widget_Options')->plugin('Passport');
$menu->title = _t('找回密码');
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
        <div class="typecho-logo">
            <h1><a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a></h1>
        </div>

        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-6 col-tb-offset-3 typecho-content-panel">
                <?php Typecho_Widget::widget('Widget_Notice')->listMessages(); ?>
                <div class="typecho-table-wrap">
                    <div class="typecho-page-title">
                        <h2><?php _e('找回密码'); ?></h2>
                    </div>
                    <?php
                    // The form action is now handled by $this->forgotForm() which uses $this->options->forgotUrl
                    // $this->forgotForm()->setAttribute('action', $this->options->forgotUrl);
                    $forgot_form = $this->forgotForm(); // Get the form object
                    // We need to inject CSRF token before rendering
                    ob_start();
                    $forgot_form->render();
                    $form_html = ob_get_clean();

                    // Inject CSRF token. This is a bit hacky but Typecho_Form doesn't have a direct way to add raw HTML inside.
                    // A cleaner way would be to modify forgotForm to add a 'hidden' CSRF element,
                    // but Typecho_Security->getTokenInput() outputs the full input tag.
                    $csrf_input = $this->security->getTokenInput();
                    if (strpos($form_html, '</form>') !== false && method_exists($this->security, 'getTokenInput')) {
                        // Try to insert before the submit button if possible for better structure
                        $submit_marker = '<button type="submit"';
                        $pos_submit = strripos($form_html, $submit_marker);
                        if ($pos_submit !== false) {
                            // Find the <li> or <ul class="typecho-option-submit"> wrapping the submit
                            $ul_submit_start_marker = '<ul class="typecho-option typecho-option-submit"';
                            $pos_ul_submit_start = strripos(substr($form_html, 0, $pos_submit), $ul_submit_start_marker);

                            if ($pos_ul_submit_start !== false) {
                                $form_html = substr_replace($form_html, $csrf_input, $pos_ul_submit_start, 0);
                            } else {
                                // Fallback: insert before </form>
                                $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);
                            }
                        } else {
                            $form_html = str_replace('</form>', $csrf_input . '</form>', $form_html);
                        }
                    }
                    echo $form_html;
                    ?>

                    <!-- CAPTCHA Integration -->
                    <?php if ($plugin_config->captchaProvider != 'none' && $plugin_config->captchaProvider != 'recaptcha_v3'): ?>
                        <div class="captcha-container">
                            <?php if ($plugin_config->captchaProvider == 'recaptcha_v2' && !empty($plugin_config->recaptcha_v2_sitekey)): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($plugin_config->recaptcha_v2_sitekey); ?>"></div>
                            <?php elseif ($plugin_config->captchaProvider == 'hcaptcha' && !empty($plugin_config->hcaptcha_sitekey)): ?>
                                <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars($plugin_config->hcaptcha_sitekey); ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)): ?>
                        <input type="hidden" name="recaptcha_v3_token" id="recaptcha_v3_token">
                    <?php endif; ?>

                    <p class="more-link" style="text-align:center; margin-top: 20px;">
                        <a href="<?php $this->options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                        •
                        <a href="<?php $this->options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common-js.php';

if ($plugin_config->captchaProvider == 'recaptcha_v2' && !empty($plugin_config->recaptcha_v2_sitekey)) {
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>';
} elseif ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)) {
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js?render=' . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . '"></script>';
    echo "<script>
    var forgotFormEl = document.querySelector('form[action=\"" . htmlspecialchars($this->options->forgotUrl, ENT_QUOTES) . "\"]');
    if (forgotFormEl) {
        forgotFormEl.addEventListener('submit', function(event) {
            var tokenInput = forgotFormEl.querySelector('#recaptcha_v3_token');
            if (!tokenInput || !tokenInput.value) { // Check if token is already set (e.g. by previous attempt)
                event.preventDefault();
                grecaptcha.ready(function() {
                    grecaptcha.execute('" . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . "', {action: 'forgot_password'}).then(function(token) {
                        if (tokenInput) { tokenInput.value = token; }
                        else { /* Create and append if missing, though it should be there */ }
                        forgotFormEl.submit();
                    });
                });
            }
        });
    }
    </script>";
} elseif ($plugin_config->captchaProvider == 'hcaptcha' && !empty($plugin_config->hcaptcha_sitekey)) {
    echo '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
}
include __TYPECHO_ADMIN_DIR__ . 'footer.php';
?>