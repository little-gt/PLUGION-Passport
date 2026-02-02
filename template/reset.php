<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 重置密码页面模板
 * 用户点击邮件链接后，输入新密码的页面。
 * @package Passport
 * @version 0.1.5
 */

// 导入公共变量和初始化
include 'common.php';

/** @var \Widget\Options $options */
/** @var \Widget\Menu $menu */

// 设置页面标题
$menu->title = _t('重置密码');

// 从配置中安全获取 CAPTCHA 相关的变量，并进行 HTML 转义
// 默认值设为 'default' (内置图片验证码)
$captchaType = htmlspecialchars((string) ($this->config->captchaType ?? 'default'));
$recaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyRecaptcha ?? ''));
$hcaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyHcaptcha ?? ''));
$geetestCaptchaId = htmlspecialchars((string) ($this->config->captchaIdGeetest ?? ''));

// 从 Request 中安全获取 token 和 signature (用于构造 Form Action)
$token = htmlspecialchars((string) ($this->request->token ?? ''));
$signature = htmlspecialchars((string) ($this->request->signature ?? ''));

include 'header.php';
?>
    <style>
        :root {
            --passport-primary: #467b96;
            --passport-primary-light: #5a8bb3;
            --passport-primary-dark: #3a6378;
            --passport-bg-light: #f8f9fa;
            --passport-bg-dark: #1a1d21;
            --passport-card-bg-light: #ffffff;
            --passport-card-bg-dark: #252a30;
            --passport-text-light: #2c3e50;
            --passport-text-dark: #e8eaed;
            --passport-border-light: #e1e4e8;
            --passport-border-dark: #374151;
            --passport-input-bg-light: #f8f9fa;
            --passport-input-bg-dark: #1f2429;
            --passport-placeholder-light: #9ca3af;
            --passport-placeholder-dark: #6b7280;
            --passport-success-light: #10b981;
            --passport-success-dark: #059669;
            --passport-error-light: #ef4444;
            --passport-error-dark: #dc2626;
            --passport-warning-light: #f59e0b;
            --passport-warning-dark: #d97706;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --passport-bg: var(--passport-bg-dark);
                --passport-card-bg: var(--passport-card-bg-dark);
                --passport-text: var(--passport-text-dark);
                --passport-border: var(--passport-border-dark);
                --passport-input-bg: var(--passport-input-bg-dark);
                --passport-placeholder: var(--passport-placeholder-dark);
                --passport-success: var(--passport-success-dark);
                --passport-error: var(--passport-error-dark);
                --passport-warning: var(--passport-warning-dark);
            }
        }

        @media (prefers-color-scheme: light) {
            :root {
                --passport-bg: var(--passport-bg-light);
                --passport-card-bg: var(--passport-card-bg-light);
                --passport-text: var(--passport-text-light);
                --passport-border: var(--passport-border-light);
                --passport-input-bg: var(--passport-input-bg-light);
                --passport-placeholder: var(--passport-placeholder-light);
                --passport-success: var(--passport-success-light);
                --passport-error: var(--passport-error-light);
                --passport-warning: var(--passport-warning-light);
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", "PingFang SC", sans-serif;
            background-color: var(--passport-bg);
            color: var(--passport-text);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .passport-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (min-width: 1920px) {
            .passport-container {
                max-width: 520px;
                padding: 24px;
            }
        }

        @media (min-width: 2560px) {
            .passport-container {
                max-width: 600px;
                padding: 32px;
            }
        }

        .passport-logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .passport-logo h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: var(--passport-primary);
            letter-spacing: -0.5px;
        }

        .passport-logo h1 a {
            text-decoration: none;
            color: inherit;
            transition: opacity 0.2s ease;
        }

        .passport-logo h1 a:hover {
            opacity: 0.8;
        }

        .passport-card {
            background-color: var(--passport-card-bg);
            padding: 40px;
            transition: background-color 0.3s ease;
            border: 1px solid var(--passport-border);
        }

        .passport-title {
            text-align: center;
            margin-bottom: 32px;
        }

        .passport-title h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: var(--passport-text);
        }

        .passport-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .passport-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .passport-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--passport-text);
            margin: 0;
        }

        .passport-label.required::after {
            content: " *";
            color: var(--passport-error);
            margin-left: 2px;
        }

        .passport-input {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            font-size: 15px;
            color: var(--passport-text);
            background-color: var(--passport-input-bg);
            border: 2px solid var(--passport-border);
            box-sizing: border-box;
            transition: all 0.2s ease;
            outline: none;
        }

        .passport-input::placeholder {
            color: var(--passport-placeholder);
        }

        .passport-input:focus {
            border-color: var(--passport-primary);
            background-color: var(--passport-card-bg);
        }

        .passport-input:hover {
            border-color: var(--passport-primary-light);
        }

        .passport-description {
            font-size: 13px;
            color: var(--passport-placeholder);
            margin: 4px 0 0 0;
            line-height: 1.5;
        }

        .passport-captcha {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .passport-captcha-input {
            flex: 1;
            min-width: 0;
        }

        .passport-captcha-img {
            height: 48px;
            border: 2px solid var(--passport-border);
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .passport-captcha-img:hover {
            border-color: var(--passport-primary);
        }

        .passport-btn {
            width: 100%;
            height: 48px;
            padding: 0 24px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            background-color: var(--passport-primary);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            margin-top: 8px;
        }

        .passport-btn:hover {
            background-color: var(--passport-primary-light);
        }

        .passport-btn:active {
            background-color: var(--passport-primary-dark);
        }

        .passport-links {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--passport-border);
        }

        .passport-links a {
            color: var(--passport-primary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s ease;
        }

        .passport-links a:hover {
            color: var(--passport-primary-light);
            text-decoration: underline;
        }

        .passport-links span {
            color: var(--passport-placeholder);
            margin: 0 8px;
        }

        .passport-notice {
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
            border: 1px solid;
        }

        .passport-notice.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--passport-success);
            border-color: var(--passport-success);
        }

        .passport-notice.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--passport-error);
            border-color: var(--passport-error);
        }

        .passport-notice.warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--passport-warning);
            border-color: var(--passport-warning);
        }

        .typecho-logo,
        .typecho-table-wrap,
        .typecho-page-title,
        .typecho-option,
        .typecho-option-submit,
        .more-link {
            display: none !important;
        }

        .body.container {
            display: none !important;
        }

        .passport-hidden {
            display: none;
        }

        @media (max-width: 480px) {
            .passport-container {
                padding: 16px;
            }

            .passport-card {
                padding: 32px 24px;
            }

            .passport-title h2 {
                font-size: 20px;
            }

            .passport-input,
            .passport-btn {
                height: 44px;
            }
        }
    </style>

    <div class="passport-container">
        <div class="passport-logo">
            <h1><a href="<?php $options->siteUrl(); ?>"><?php $options->title(); ?></a></h1>
        </div>

        <div class="passport-card">
            <div class="passport-title">
                <h2><?php _e('重置密码'); ?></h2>
            </div>

            <?php $this->notice->render(); ?>

            <form action="<?php echo passport_route_url('/passport/reset'); ?>?token=<?php echo $token; ?>&signature=<?php echo $signature; ?>" method="post" enctype="application/x-www-form-urlencoded" class="passport-form">

                <input type="hidden" name="_token" value="<?php echo $security->token('/passport/reset'); ?>">

                <div class="passport-form-group">
                    <label class="passport-label required" for="password-input"><?php _e('新密码'); ?></label>
                    <input id="password-input" name="password" type="password" class="passport-input" required placeholder="<?php _e('请输入新密码'); ?>" autocomplete="new-password">
                    <p class="passport-description"><?php _e('建议使用特殊字符与字母、数字的混编样式，以增加系统安全性'); ?></p>
                </div>

                <div class="passport-form-group">
                    <label class="passport-label required" for="confirm-input"><?php _e('密码确认'); ?></label>
                    <input id="confirm-input" name="confirm" type="password" class="passport-input" required placeholder="<?php _e('请再次输入密码'); ?>" autocomplete="new-password">
                    <p class="passport-description"><?php _e('请确认你的密码，与上面输入的密码保持一致'); ?></p>
                </div>

                <input name="do" type="hidden" value="password">

                <?php if ($captchaType === 'default'): ?>
                    <div class="passport-form-group">
                        <label class="passport-label required" for="captcha-input"><?php _e('验证码'); ?></label>
                        <div class="passport-captcha">
                            <input type="text" name="captcha" id="captcha-input" class="passport-input passport-captcha-input" required placeholder="<?php _e('请输入验证码'); ?>" autocomplete="off">
                            <img src="<?php $options->index('/passport/captcha'); ?>"
                                 class="passport-captcha-img"
                                 alt="<?php _e('验证码'); ?>"
                                 title="<?php _e('点击图片刷新验证码'); ?>"
                                 onclick="this.src='<?php $options->index('/passport/captcha'); ?>?'+Math.random();">
                        </div>
                        <p class="passport-description"><?php _e('请输入图片中的字符，不区分大小写'); ?></p>
                    </div>
                <?php elseif ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
                    <div class="passport-form-group">
                        <div class="g-recaptcha" data-sitekey="<?php echo $recaptchaSiteKey; ?>"></div>
                    </div>
                <?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
                    <div class="passport-form-group">
                        <div class="h-captcha" data-sitekey="<?php echo $hcaptchaSiteKey; ?>"></div>
                    </div>
                <?php elseif ($captchaType === 'geetest' && !empty($geetestCaptchaId)): ?>
                    <div class="passport-form-group">
                        <div id="captcha-geetest"></div>
                    </div>
                    <input type="hidden" name="lot_number" id="lot_number">
                    <input type="hidden" name="captcha_output" id="captcha_output">
                    <input type="hidden" name="pass_token" id="pass_token">
                    <input type="hidden" name="gen_time" id="gen_time">
                <?php endif; ?>

                <button type="submit" class="passport-btn"><?php _e('更新密码'); ?></button>
            </form>

            <div class="passport-links">
                <a href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                <span>•</span>
                <a href="<?php $options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
            </div>
        </div>
    </div>

<?php
// Typecho 后台的公共 JS 文件
include __ADMIN_DIR__ . '/common-js.php';
?>

<?php // --- 按需加载第三方 CAPTCHA 脚本 --- ?>

<?php if ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
    <script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>
<?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php elseif ($captchaType === 'geetest' && !empty($geetestCaptchaId)): ?>
    <script src="https://static.geetest.com/v4/gt4.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const captchaElement = document.getElementById('captcha-geetest');
            if (captchaElement) {
                initGeetest4({
                    captchaId: '<?php echo $geetestCaptchaId; ?>',
                    product: 'popup',
                    language: 'zh-cn'
                }, function (captcha) {
                    captcha.appendTo(captchaElement);
                    captcha.onSuccess(function () {
                        const result = captcha.getValidate();
                        if (result) {
                            document.getElementById('lot_number').value = result.lot_number;
                            document.getElementById('captcha_output').value = result.captcha_output;
                            document.getElementById('pass_token').value = result.pass_token;
                            document.getElementById('gen_time').value = result.gen_time;
                        }
                    });
                });
            }
        });
    </script>
<?php endif; ?>

<?php
// Typecho 后台的页脚文件
include __ADMIN_DIR__ . '/footer.php';
?>