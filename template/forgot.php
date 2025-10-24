<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include 'common.php';

$menu->title = _t('找回密码');
$options = $this->options;
$captchaType = $this->config->captchaType;
$recaptchaSiteKey = $this->config->sitekeyRecaptcha;
$hcaptchaSiteKey = $this->config->sitekeyHcaptcha;
$geetestCaptchaId = $this->config->captchaIdGeetest;

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
        /* Style for captcha container for consistent alignment */
        .captcha-container {
            margin-bottom: 15px;
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
                        <?php elseif ($captchaType === 'geetest' && !empty($geetestCaptchaId)): ?>
                            <div class="captcha-container">
                                <div id="captcha-geetest"></div>
                            </div>
                            <input type="hidden" name="lot_number" id="lot_number">
                            <input type="hidden" name="captcha_output" id="captcha_output">
                            <input type="hidden" name="pass_token" id="pass_token">
                            <input type="hidden" name="gen_time" id="gen_time">
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
<?php // --- 按需加载 CAPTCHA 的 JavaScript --- ?>

<?php if ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
    <!-- Google reCAPTCHA v2 API -->
    <script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>
<?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
    <!-- hCaptcha API -->
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php elseif ($captchaType === 'geetest' && !empty($geetestCaptchaId)): ?>
    <!-- Geetest v4 API -->
    <script src="https://static.geetest.com/v4/gt4.js"></script>
    <script>
        // 确保在DOM加载完毕后执行脚本
        document.addEventListener('DOMContentLoaded', function () {
            // 检查 #captcha-geetest 元素是否存在，确保脚本只在需要时运行
            if (document.getElementById('captcha-geetest')) {
                initGeetest4({
                    // [安全] 使用 htmlspecialchars 过滤PHP变量
                    captchaId: '<?php echo htmlspecialchars($geetestCaptchaId); ?>',
                    product: 'popup', // 使用弹出式，体验更佳
                    lang: 'zho' // 设置语言为中文
                }, function (captcha) {
                    // captcha为验证码实例
                    captcha.appendTo('#captcha-geetest'); // 将验证码插入到指定元素

                    // 监听验证成功事件
                    captcha.onSuccess(function () {
                        var result = captcha.getValidate();
                        if (result) {
                            // 将验证结果填入表单的隐藏输入框中
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
include __ADMIN_DIR__ . '/footer.php';
?>