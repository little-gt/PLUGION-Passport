<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include 'common.php';

$menu->title = _t('找回密码');
$captchaType = $this->config->captchaType;
$recaptchaSiteKey = $this->config->sitekeyRecaptcha;
$hcaptchaSiteKey = $this->config->sitekeyHcaptcha;

include 'header.php';
?>
    <style>
        body {
            font-family: "Microsoft YaHei", tahoma, arial, 'Hiragino Sans GB', '\5b8b\4f53', sans-serif;
        }
        .typecho-logo {
            margin: 50px 0 30px;
            text-align: center;
        }
        .typecho-table-wrap {
            padding: 50px 30px;
        }
        .typecho-page-title h2 {
            margin: 0 0 30px;
            font-weight: 500;
            font-size: 20px;
            text-align: center;
        }
        label:after {
            content: " *";
            color: #ed1c24;
        }
        .btn {
            width: 100%;
            height: auto;
            padding: 10px 16px;
            font-size: 18px;
            line-height: 1.33;
        }
        /* Updated style for captcha container for left alignment */
        .captcha-container {
            /* display: flex; */ /* Removed for left alignment */
            /* justify-content: center; */ /* Removed for left alignment */
            margin-bottom: 15px; /* Keep space below the captcha */
            /* The captcha widget itself will now align left within this block container */
        }
    </style>
    <div class="body container">
        <div class="typecho-logo">
            <h1><a href="<?php $options->siteUrl(); ?>"><?php $options->title(); ?></a></h1>
        </div>

        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-6 col-tb-offset-3 typecho-content-panel">
                <div class="typecho-table-wrap">
                    <div class="typecho-page-title">
                        <h2>找回密码</h2>
                    </div>
                    <?php $this->notice->render(); ?>
                    <form action="<?php $options->doForgot(); ?>" method="post" enctype="application/x-www-form-urlencoded">
                        <ul class="typecho-option" id="typecho-option-item-mail-0">
                            <li>
                                <label class="typecho-label" for="mail-0-1">邮箱</label>
                                <input id="mail-0-1" name="mail" type="text" class="text">
                                <p class="description">请输入您忘记密码的账号所对应的邮箱地址</p>
                            </li>
                        </ul>
                        <ul class="typecho-option" id="typecho-option-item-do-1" style="display:none"><li><input name="do" type="hidden" value="mail"></li></ul>

                        <?php if ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
                            <div class="captcha-container">
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
                            </div>
                        <?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
                            <div class="captcha-container">
                                <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars($hcaptchaSiteKey); ?>"></div>
                            </div>
                        <?php endif; ?>

                        <ul class="typecho-option typecho-option-submit" id="typecho-option-item-submit-2"><li><button type="submit" class="btn primary">提交</button></li></ul>
                    </form>

                    <p class="more-link">
                        <a href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                        •
                        <a href="<?php $options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php
include __ADMIN_DIR__ . '/common-js.php';
?>
<?php if ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
    <!-- reCAPTCHA v2 API -->
    <script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>
<?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
    <!-- hCaptcha API -->
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php endif; ?>
<?php
include __ADMIN_DIR__ . '/footer.php';
?>