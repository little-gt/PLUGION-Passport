<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common.php'; // Use constant

// Get plugin config for CAPTCHA
$plugin_config = Typecho_Widget::widget('Widget_Options')->plugin('Passport');

$menu->title = _t('找回密码');
include __TYPECHO_ADMIN_DIR__ . 'header.php'; // Use constant
?>
    <style>
        body { font-family: "Microsoft YaHei", tahoma, arial, 'Hiragino Sans GB', '\5b8b\4f53', sans-serif; }
        .typecho-logo { margin: 50px 0 30px; text-align: center; }
        .typecho-table-wrap { padding: 50px 30px; }
        .typecho-page-title h2 { margin: 0 0 30px; font-weight: 500; font-size: 20px; text-align: center; }
        /* label:after { content: " *"; color: #ed1c24; } */ /* Removed as per Typecho style */
        .btn { width: 100%; height: auto; padding: 10px 16px; font-size: 18px; line-height: 1.33; }
        .captcha-container { margin-bottom: 15px; display: flex; justify-content: center; }
    </style>
    <div class="body container">
        <div class="typecho-logo">
            <h1><a href="<?php $options->siteUrl(); ?>"><?php $options->title(); ?></a></h1>
        </div>

        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-6 col-tb-offset-3 typecho-content-panel">
                <?php Typecho_Widget::widget('Widget_Notice')->listMessages(); ?>
                <div class="typecho-table-wrap">
                    <div class="typecho-page-title">
                        <h2><?php _e('找回密码'); ?></h2>
                    </div>
                    <?php $this->forgotForm()->render(); // Render the form from widget ?>

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
                    <!-- End CAPTCHA -->

                    <?php if ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)): ?>
                        <input type="hidden" name="recaptcha_v3_token" id="recaptcha_v3_token">
                    <?php endif; ?>

                    <p class="more-link" style="text-align:center; margin-top: 20px;">
                        <a href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                        •
                        <a href="<?php $options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php
include __TYPECHO_ADMIN_DIR__ . 'common-js.php'; // Use constant

// CAPTCHA Scripts
if ($plugin_config->captchaProvider == 'recaptcha_v2' && !empty($plugin_config->recaptcha_v2_sitekey)) {
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>';
} elseif ($plugin_config->captchaProvider == 'recaptcha_v3' && !empty($plugin_config->recaptcha_v3_sitekey)) {
    echo '<script src="https://www.recaptcha.net/recaptcha/api.js?render=' . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . '"></script>';
    echo "<script>
    var form = document.querySelector('form[action=\"" . $options->forgotUrl . "\"]');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            grecaptcha.ready(function() {
                grecaptcha.execute('" . htmlspecialchars($plugin_config->recaptcha_v3_sitekey) . "', {action: 'forgot_password'}).then(function(token) {
                    document.getElementById('recaptcha_v3_token').value = token;
                    form.submit();
                });
            });
        });
    }
    </script>";
} elseif ($plugin_config->captchaProvider == 'hcaptcha' && !empty($plugin_config->hcaptcha_sitekey)) {
    echo '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
}
include __TYPECHO_ADMIN_DIR__ . 'footer.php'; // Use constant
?>