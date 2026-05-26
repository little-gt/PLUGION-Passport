<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 忘记密码页面模板
 * 用户输入邮箱申请重置链接的页面。
 * @package Passport
 * @version 1.0.1
 */

// 导入公共变量和初始化
include 'partial/common.php';

/** @var \Widget\Options $options */

// 设置页面标题
$menuTitle = _t('找回密码');

// 从配置中安全获取 CAPTCHA 相关的变量，并进行 HTML 转义
// 默认值设为 'default' (内置图片验证码)
$captchaType = htmlspecialchars((string) ($this->config->captchaType ?? 'default'));
$recaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyRecaptcha ?? ''));
$hcaptchaSiteKey = htmlspecialchars((string) ($this->config->sitekeyHcaptcha ?? ''));
$geetestCaptchaId = htmlspecialchars((string) ($this->config->captchaIdGeetest ?? ''));

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
                <h2><?php _e('找回密码'); ?></h2>
            </div>

            <form action="<?php echo passport_route_url('/passport/forgot'); ?>" method="post" enctype="application/x-www-form-urlencoded" class="passport-form">

                <div class="passport-form-group">
                    <label class="passport-label required" for="mail-input"><?php _e('邮箱'); ?></label>
                    <input id="mail-input" name="mail" type="email" class="passport-input" required placeholder="<?php _e('请输入您的邮箱地址'); ?>" autocomplete="email">
                    <p class="passport-description"><?php _e('请输入您忘记密码的账号所对应的邮箱地址'); ?></p>
                </div>

                <input name="do" type="hidden" value="mail">

                <?php if ($captchaType === 'default'): ?>
                    <div class="passport-form-group">
                        <label class="passport-label required" for="captcha-input"><?php _e('验证码'); ?></label>
                        <div class="passport-captcha">
                            <input type="text" name="captcha" id="captcha-input" class="passport-input passport-captcha-input" required placeholder="<?php _e('请输入验证码'); ?>" autocomplete="off">
                            <div class="passport-captcha-wrapper">
                                <div class="passport-captcha-loader active"></div>
                                <img class="passport-captcha-img loading" alt="<?php _e('验证码'); ?>" title="<?php _e('点击图片刷新验证码'); ?>" onclick="refreshCaptcha(this);">
                            </div>
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

                <button type="submit" class="passport-btn"><?php _e('提交'); ?></button>
            </form>

            <div class="passport-links">
                <a href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                <span>•</span>
                <a href="<?php $options->adminUrl('login.php'); ?>"><?php _e('用户登录'); ?></a>
            </div>
        </div>
    </div>

<?php // --- 按需加载第三方 CAPTCHA 脚本 --- ?>

<?php if ($captchaType === 'default'): ?>
    <script>
        // 验证码刷新功能
        function refreshCaptcha(imgElement) {
            // 防止重复点击
            if (imgElement.classList.contains('refreshing')) {
                return;
            }
            
            const wrapper = imgElement.parentElement;
            const loader = wrapper.querySelector('.passport-captcha-loader');
            
            // 显示加载指示器
            imgElement.classList.add('refreshing', 'loading');
            loader.classList.add('active');
            
            // 刷新验证码
            const baseUrl = imgElement.src ? imgElement.src.split('?')[0] : '<?php $options->index("/passport/captcha"); ?>';
            imgElement.src = baseUrl + '?' + Math.random();
            
            // 图片加载完成后隐藏加载指示器
            imgElement.onload = function() {
                imgElement.classList.remove('refreshing', 'loading');
                loader.classList.remove('active');
            };
            
            // 图片加载失败也隐藏加载指示器
            imgElement.onerror = function() {
                imgElement.classList.remove('refreshing', 'loading');
                loader.classList.remove('active');
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const captchaImg = document.querySelector('.passport-captcha-img');
            if (captchaImg) {
                // 首次加载验证码
                refreshCaptcha(captchaImg);
            }
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

</body>
</html>