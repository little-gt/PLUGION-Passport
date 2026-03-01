<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 重置密码页面模板
 * 用户点击邮件链接后，输入新密码的页面。
 * @package Passport
 * @version 1.0.1
 */

// 导入公共变量和初始化
include 'partial/common.php';

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

include 'partial/header.php';
// 引入静态资源
include 'partial/resource.php';
?>

    <div class="passport-container">
        <div class="passport-logo">
            <h1><a href="<?php $options->siteUrl(); ?>"><?php $options->title(); ?></a></h1>
        </div>

        <div class="passport-card">
            <div class="passport-title">
                <h2><?php _e('重置密码'); ?></h2>
            </div>

            <?php $this->notice->render(); ?>

            <form action="<?php echo passport_route_url('/passport/reset'); ?>" method="post" enctype="application/x-www-form-urlencoded" class="passport-form">

                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <input type="hidden" name="signature" value="<?php echo $signature; ?>">

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
                            <img src="<?php $options->index('/passport/captcha'); ?>" class="passport-captcha-img" alt="<?php _e('验证码'); ?>" title="<?php _e('点击图片刷新验证码'); ?>" onclick="refreshCaptcha(this);">
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

<?php if ($captchaType === 'default'): ?>
    <script>
        // 验证码刷新功能
        function refreshCaptcha(imgElement) {
            // 防止重复点击
            if (imgElement.classList.contains('refreshing')) {
                return;
            }
            
            // 添加刷新动画
            imgElement.classList.add('refreshing');
            imgElement.style.opacity = '0.5';
            imgElement.style.transform = 'rotate(180deg)';
            imgElement.style.transition = 'all 0.3s ease';
            
            // 刷新验证码
            imgElement.src = imgElement.src.split('?')[0] + '?' + Math.random();
            
            // 图片加载完成后移除动画
            imgElement.onload = function() {
                setTimeout(() => {
                    imgElement.classList.remove('refreshing');
                    imgElement.style.opacity = '1';
                    imgElement.style.transform = 'rotate(0deg)';
                }, 300);
            };
            
            // 图片加载失败也移除动画
            imgElement.onerror = function() {
                setTimeout(() => {
                    imgElement.classList.remove('refreshing');
                    imgElement.style.opacity = '1';
                    imgElement.style.transform = 'rotate(0deg)';
                }, 300);
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 为验证码图片添加点击事件
            const captchaImgs = document.querySelectorAll('.passport-captcha-img');
            captchaImgs.forEach(img => {
                img.addEventListener('click', function() {
                    refreshCaptcha(this);
                });
            });
        });
    </script>
<?php elseif ($captchaType === 'recaptcha' && !empty($recaptchaSiteKey)): ?>
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