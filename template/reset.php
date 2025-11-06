<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 重置密码页面模板
 */
// 导入公共变量和初始化
include 'common.php';

/** @var \Widget\Options $options */
/** @var \Widget\Menu $menu */

$menu->title = _t('重置密码'); // 设置页面标题

// 从配置中安全获取 CAPTCHA 相关的变量，并进行 HTML 转义
$captchaType = htmlspecialchars((string) ($this->config->captchaType ?? 'none'));
$recaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyRecaptcha ?? ''));
$hcaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyHcaptcha ?? ''));
$geetestCaptchaId = htmlspecialchars((string) ($this->config->captchaIdGeetest ?? ''));

// 从 Request 中安全获取 token 和 signature
$token = htmlspecialchars((string) ($this->request->token ?? ''));
$signature = htmlspecialchars((string) ($this->request->signature ?? ''));

include 'header.php';
?>
    <style>
        /* 保持与后台登录页风格一致 */
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
        /* 恢复原始的必填星号样式 */
        label.required:after {
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
        .captcha-container {
            margin-bottom: 15px;
            display: flex;
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
                        <h2><?php _e('重置密码'); ?></h2>
                    </div>
                    <?php $this->notice->render(); ?>
                    <form action="<?php $options->doReset(); ?>?token=<?php echo $token; ?>&signature=<?php echo $signature; ?>" method="post" enctype="application/x-www-form-urlencoded">
                        <ul class="typecho-option" id="typecho-option-item-password-0"><li>
                                <label class="typecho-label required" for="password-0-1"><?php _e('新密码'); ?></label>
                                <input id="password-0-1" name="password" type="password" class="w-100" required>
                                <p class="description"><?php _e('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'); ?></p></li></ul>
                        <ul class="typecho-option" id="typecho-option-item-confirm-1"><li>
                                <label class="typecho-label required" for="confirm-0-2"><?php _e('密码确认'); ?></label>
                                <input id="confirm-0-2" name="confirm" type="password" class="w-100" required>
                                <p class="description"><?php _e('请确认你的密码, 与上面输入的密码保持一致.'); ?></p></li></ul>
                        <ul class="typecho-option" id="typecho-option-item-do-2" style="display:none"><li>
                                <input name="do" type="hidden" value="password"></li></ul>

                        <?php if ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
                            <div class="captcha-container">
                                <div class="g-recaptcha" data-sitekey="<?php echo $recaptchaSiteKey; ?>"></div>
                            </div>
                        <?php elseif ($captchaType === 'hcaptcha' && !empty($hcaptchaSiteKey)): ?>
                            <div class="captcha-container">
                                <div class="h-captcha" data-sitekey="<?php echo $hcaptchaSiteKey; ?>"></div>
                            </div>
                        <?php elseif ($captchaType === 'geetest' && !empty($geetestCaptchaId)): ?>
                            <div class="captcha-container">
                                <div id="captcha-geetest"></div>
                            </div>
                            <!-- 隐藏字段用于回传验证结果 -->
                            <input type="hidden" name="lot_number" id="lot_number">
                            <input type="hidden" name="captcha_output" id="captcha_output">
                            <input type="hidden" name="pass_token" id="pass_token">
                            <input type="hidden" name="gen_time" id="gen_time">
                        <?php endif; ?>

                        <ul class="typecho-option typecho-option-submit" id="typecho-option-item-submit-3"><li>
                                <button type="submit" class="btn primary"><?php _e('更新密码'); ?></button></li></ul>
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
// Typecho 后台的公共 JS 文件
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
            const captchaElement = document.getElementById('captcha-geetest');
            if (captchaElement) {
                initGeetest4({
                    // 确保 captchaId 变量被安全转义
                    captchaId: '<?php echo $geetestCaptchaId; ?>',
                    product: 'popup', // 使用弹出式
                    language: 'zh-cn' // 设置语言
                }, function (captcha) {
                    // captcha为验证码实例
                    captcha.appendTo(captchaElement); // 将验证码插入到指定元素

                    // 监听验证成功事件
                    captcha.onSuccess(function () {
                        const result = captcha.getValidate();
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
// Typecho 后台的页脚文件
include __ADMIN_DIR__ . '/footer.php';
?>