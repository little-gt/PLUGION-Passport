<?php
/**
 * Passport 插件独立前端模板 - 静态资源
 *
 * 用于 Typecho 插件 Passport 的独立前端页面（如找回密码、重置密码）的静态资源。
 * 包含所有页面共享的 CSS 样式和 JavaScript 脚本。
 *
 * 设计规范：
 * - 完全矩形化设计（border-radius: 0）
 * - 纯黑中性色板，OLED 友好，护眼不刺眼
 * - 色彩令牌 100% 同步 BooAdmin dark.css 最新纯黑配色
 */

// 严格安全检查: 防止文件被直接访问。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- CSS 样式 --- ?>

<style>
    /* ========================================
     * 亮色模式默认值 (BooAdmin light.css)
     * ======================================== */
    :root {
        color-scheme: light;

        /* === 主色调 (BooAdmin light.css --booadmin-accent) === */
        --passport-primary: #5865f2;
        --passport-primary-hover: #4752c4;
        --passport-primary-active: #3c45a5;

        /* === 背景色 (BooAdmin light.css surface 系统) === */
        --passport-bg: #f2f3f5;
        --passport-card-bg: #ffffff;
        --passport-input-bg: #f3f4f6;

        /* === 文本色 (BooAdmin light.css text 系统) === */
        --passport-text: #2e3338;
        --passport-muted: #5c5e66;
        --passport-placeholder: #9ca3af;

        /* === 边框色 (BooAdmin light.css border) === */
        --passport-border: #e5e7eb;
        --passport-border-strong: #d1d5db;

        /* === 语义色 - 成功 (BooAdmin light.css --booadmin-success) === */
        --passport-success: #16a34a;
        --passport-success-bg: #def7ec;

        /* === 语义色 - 危险/错误 (BooAdmin light.css --booadmin-danger) === */
        --passport-danger: #dc2626;
        --passport-error: #dc2626;
        --passport-error-bg: #fde8e8;

        /* === 语义色 - 警告 (BooAdmin light.css --booadmin-warning) === */
        --passport-warning: #ea580c;
        --passport-warning-bg: #fff6bf;

        /* === 语义色 - 信息 (BooAdmin light.css --booadmin-info) === */
        --passport-info: #2563eb;
        --passport-info-bg: #eff6ff;

        /* === 按钮 - 亮色实心风格 === */
        --passport-btn-bg: var(--passport-primary);
        --passport-btn-text: #ffffff;
        --passport-btn-border: transparent;
        --passport-btn-hover-bg: var(--passport-primary-hover);
        --passport-btn-hover-text: #ffffff;

        /* === 矩形化设计规范 === */
        --passport-radius-sm: 0px;
        --passport-radius-md: 0px;
        --passport-radius-lg: 0px;
        --passport-radius-full: 0px;

        /* === 阴影规范 (亮色风格) === */
        --passport-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
        --passport-shadow-md: 0 2px 4px rgba(0, 0, 0, 0.08);

        /* === 焦点环 (BooAdmin light.css --booadmin-focus-ring) === */
        --passport-focus-ring: rgba(88, 101, 242, 0.22);
    }

    /* ========================================
     * 暗色模式覆盖 (BooAdmin dark.css 纯黑中性色板)
     * OLED 友好，护眼不刺眼
     * ======================================== */
    @media (prefers-color-scheme: dark) {
        :root {
            color-scheme: dark;

            /* === 主色调 深沉靛蓝 === */
            --passport-primary: #6366c7;
            --passport-primary-hover: #7c7fd6;
            --passport-primary-active: #9899e3;

            /* === 背景色 纯黑系统 === */
            --passport-bg: #000000;
            --passport-card-bg: #0d0d0d;
            --passport-input-bg: #151515;

            /* === 文本色 === */
            --passport-text: #e4e4e7;
            --passport-muted: #8a8a99;
            --passport-placeholder: #6b6b76;

            /* === 边框色 中性 === */
            --passport-border: #222222;
            --passport-border-strong: #333333;

            /* === 语义色 - 成功 柔和绿 === */
            --passport-success: #6bc78a;
            --passport-success-bg: #0a1f12;

            /* === 语义色 - 危险/错误 低饱和红 === */
            --passport-danger: #e07a7a;
            --passport-error: #e07a7a;
            --passport-error-bg: #2a0c10;

            /* === 语义色 - 警告 琥珀 === */
            --passport-warning: #d4a72a;
            --passport-warning-bg: #2a2208;

            /* === 语义色 - 信息 天蓝 === */
            --passport-info: #7aadf0;
            --passport-info-bg: #081420;

            /* === 按钮 - 暗底柔字风格 === */
            --passport-btn-bg: #3a3c6e;
            --passport-btn-text: #c4c8e8;
            --passport-btn-border: #484a78;
            --passport-btn-hover-bg: #46487d;
            --passport-btn-hover-text: #ddddef;

            /* === 阴影规范 (暗色风格) === */
            --passport-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.15);
            --passport-shadow-md: 0 2px 4px rgba(0, 0, 0, 0.18);

            /* === 焦点环 === */
            --passport-focus-ring: rgba(99, 102, 199, 0.18);
        }
    }

    /* === 基础重置 (BooAdmin 字体栈) === */
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", "Source Han Sans SC", sans-serif;
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
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* === 容器 === */
    .passport-container {
        width: 100%;
        max-width: 440px;
        padding: 20px;
        box-sizing: border-box;
    }

    @media (min-width: 1920px) {
        .passport-container { max-width: 520px; padding: 24px; }
    }

    @media (min-width: 2560px) {
        .passport-container { max-width: 600px; padding: 32px; }
    }

    /* === Logo 区域 === */
    .passport-logo {
        text-align: center;
        margin-bottom: 32px;
    }

    .passport-logo h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 700;
        color: var(--passport-primary);
        letter-spacing: -0.3px;
    }

    .passport-logo h1 a {
        text-decoration: none;
        color: inherit;
        transition: opacity 0.2s ease;
    }

    .passport-logo h1 a:hover {
        opacity: 0.8;
    }

    /* === 卡片容器 (矩形: border-radius: 0) === */
    .passport-card {
        background-color: var(--passport-card-bg);
        padding: 40px;
        border-radius: 0;
        border: 1px solid var(--passport-border);
        box-shadow: var(--passport-shadow-md);
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    /* === 标题区域 === */
    .passport-title {
        text-align: center;
        margin-bottom: 28px;
    }

    .passport-title h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        color: var(--passport-text);
        letter-spacing: -0.2px;
    }

    /* === 表单 === */
    .passport-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .passport-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
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

    /* === 输入框 (矩形: border-radius: 0) === */
    .passport-input {
        width: 100%;
        height: 46px;
        padding: 0 14px;
        font-size: 14px;
        color: var(--passport-text);
        background-color: var(--passport-input-bg);
        border: 1px solid var(--passport-border);
        border-radius: 0;
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
        box-shadow: 0 0 0 3px var(--passport-focus-ring);
    }

    .passport-input:hover:not(:focus) {
        border-color: var(--passport-border-strong);
    }

    .passport-description {
        font-size: 13px;
        color: var(--passport-muted);
        margin: 4px 0 0 0;
        line-height: 1.5;
    }

    /* === 验证码区域 === */
    .passport-captcha {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .passport-captcha-input {
        flex: 1;
        min-width: 0;
    }

    .passport-captcha-wrapper {
        position: relative;
        height: 46px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .passport-captcha-img {
        height: 46px;
        border: 1px solid var(--passport-border);
        border-radius: 0;
        cursor: pointer;
        transition: border-color 0.2s ease;
        flex-shrink: 0;
        display: block;
    }

    .passport-captcha-img:hover {
        border-color: var(--passport-primary);
    }

    .passport-captcha-img.loading {
        opacity: 0;
        pointer-events: none;
    }

    .passport-captcha-loader {
        position: absolute;
        width: 22px;
        height: 22px;
        border: 2.5px solid var(--passport-border);
        border-top-color: var(--passport-primary);
        border-radius: 50%;
        animation: passportRotate 0.8s linear infinite;
        display: none;
    }

    .passport-captcha-loader.active {
        display: block;
    }

    @keyframes passportRotate {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* === 按钮 (矩形: border-radius: 0, 暗底柔字风格) === */
    .passport-btn {
        width: 100%;
        height: 46px;
        padding: 0 24px;
        font-size: 15px;
        font-weight: 600;
        color: var(--passport-btn-text);
        background-color: var(--passport-btn-bg);
        border: 1px solid var(--passport-btn-border);
        border-radius: 0;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
        margin-top: 4px;
    }

    .passport-btn:hover {
        background-color: var(--passport-btn-hover-bg);
        color: var(--passport-btn-hover-text);
    }

    /* === 底部链接 === */
    .passport-links {
        text-align: center;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--passport-border);
    }

    .passport-links a {
        color: var(--passport-primary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .passport-links a:hover {
        color: var(--passport-primary-hover);
        text-decoration: underline;
    }

    .passport-links span {
        color: var(--passport-placeholder);
        margin: 0 8px;
    }

    /* === 页面内提示 Notice (左边框强调 + 语义背景色) === */
    .passport-notice {
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.5;
        border-radius: 0;
        border-left: 4px solid;
    }

    .passport-notice.success {
        background-color: var(--passport-success-bg);
        color: var(--passport-success);
        border-left-color: var(--passport-success);
    }

    .passport-notice.error {
        background-color: var(--passport-error-bg);
        color: var(--passport-error);
        border-left-color: var(--passport-error);
    }

    .passport-notice.warning {
        background-color: var(--passport-warning-bg);
        color: var(--passport-warning);
        border-left-color: var(--passport-warning);
    }

    /* === Toast 通知系统 === */
    .passport-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 14px 16px;
        border-radius: 0;
        font-size: 13px;
        z-index: 99999;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        min-width: 300px;
        max-width: 360px;
        opacity: 0;
        transform: translateX(400px);
        animation: passportSlideIn 0.35s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        border: 1px solid var(--passport-border);
        border-left: 3px solid;
        box-shadow: none;
        background: var(--passport-card-bg);
        color: var(--passport-text);
    }

    /* === 语义色样式 (左边框强调) === */
    .passport-toast.success {
        border-left-color: var(--passport-success);
    }

    .passport-toast.success .passport-toast-icon {
        color: var(--passport-success);
    }

    .passport-toast.success .passport-toast-title {
        color: var(--passport-success);
    }

    .passport-toast.error {
        border-left-color: var(--passport-danger);
    }

    .passport-toast.error .passport-toast-icon {
        color: var(--passport-danger);
    }

    .passport-toast.error .passport-toast-title {
        color: var(--passport-error);
    }

    .passport-toast.warning {
        border-left-color: var(--passport-warning);
    }

    .passport-toast.warning .passport-toast-icon {
        color: var(--passport-warning);
    }

    .passport-toast.warning .passport-toast-title {
        color: var(--passport-warning);
    }

    .passport-toast.info {
        border-left-color: var(--passport-info);
    }

    .passport-toast.info .passport-toast-icon {
        color: var(--passport-info);
    }

    .passport-toast.info .passport-toast-title {
        color: var(--passport-text);
    }

    /* === 通知图标 (18x18px) === */
    .passport-toast-icon {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        color: var(--passport-muted);
        margin-top: 2px;
    }

    .passport-toast-icon svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    /* === 通知内容区域 === */
    .passport-toast-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    /* === 通知标题 (font-size: 13px, font-weight: 600) === */
    .passport-toast-title {
        font-weight: 600;
        font-size: 13px;
        line-height: 1.4;
        color: var(--passport-text);
    }

    /* === 通知消息列表 === */
    .passport-toast-messages {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .passport-toast-messages li {
        font-size: 12px;
        line-height: 1.4;
        color: var(--passport-muted);
        margin-bottom: 0;
    }

    .passport-toast-messages li:not(:last-child) {
        margin-bottom: 2px;
    }

    .passport-toast-close {
        background: none;
        border: none;
        font-size: 16px;
        line-height: 1;
        cursor: pointer;
        color: var(--passport-border-strong);
        opacity: 1;
        padding: 0;
        margin-left: 8px;
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        transition: color 0.2s ease;
        margin-top: 2px;
    }

    .passport-toast-close:hover {
        color: var(--passport-muted);
    }

    .passport-toast.show {
        opacity: 1;
        transform: translateX(0);
    }

    .passport-toast.hide {
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.2s ease-out;
    }

    @keyframes passportSlideIn {
        from { opacity: 0; transform: translateX(400px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    @keyframes passportSlideOut {
        from { opacity: 1; transform: translateX(0); }
        to   { opacity: 0; transform: translateX(400px); }
    }

    /* === 响应式适配 === */
    @media (max-width: 480px) {
        .passport-container { padding: 16px; }
        .passport-card { padding: 28px 20px; }
        .passport-title h2 { font-size: 20px; }
        .passport-logo h1 { font-size: 22px; }
        .passport-input,
        .passport-btn { height: 44px; }
        .passport-captcha-img { height: 44px; }
        .passport-toast {
            left: 12px;
            right: 12px;
            min-width: auto;
            max-width: none;
        }
    }
</style>

<?php // --- JavaScript 脚本 --- ?>

<script>
    // SVG 图标库 (对齐 BooAdmin FontAwesome 图标风格)
    var PassportSVGIcons = {
        check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        alert: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    // 标题映射 (对齐 BooAdmin)
    var PassportTitles = {
        'success': '操作成功',
        'error': '出错了',
        'notice': '注意',
        'warning': '警告',
        'info': '提示'
    };

    // Toast 通知系统
    var PassportToast = {
        currentToast: null,
        hideTimer: null,

        show: function(messages, type, duration) {
            type = type || 'info';
            duration = (typeof duration !== 'undefined' && duration !== null) ? duration : 5000;

            if (this.currentToast) {
                this.currentToast.classList.add('hide');
                clearTimeout(this.hideTimer);
                var toast = this.currentToast;
                setTimeout(function() {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 200);
            }

            var toastEl = document.createElement('div');
            toastEl.className = 'passport-toast ' + type;

            // 构建消息列表 (对齐 BooAdmin: 单条或多条消息)
            var messagesHTML = '';
            if (Array.isArray(messages)) {
                if (messages.length === 1) {
                    messagesHTML = '<div class="passport-toast-messages">' + messages[0] + '</div>';
                } else {
                    messagesHTML = '<ul class="passport-toast-messages">';
                    messages.forEach(function(msg) {
                        messagesHTML += '<li>' + msg + '</li>';
                    });
                    messagesHTML += '</ul>';
                }
            } else {
                messagesHTML = '<div class="passport-toast-messages">' + messages + '</div>';
            }

            var iconSvg = PassportSVGIcons[type] || PassportSVGIcons.info;
            
            // 对齐 BooAdmin 结构: 图标 + 内容区域(标题+消息) + 关闭按钮
            toastEl.innerHTML =
                '<div class="passport-toast-icon">' + iconSvg + '</div>' +
                '<div class="passport-toast-content">' +
                    '<div class="passport-toast-title">' + PassportTitles[type] + '</div>' +
                    messagesHTML +
                '</div>' +
                '<button class="passport-toast-close" aria-label="关闭">×</button>';

            document.body.appendChild(toastEl);
            this.currentToast = toastEl;

            // 显示动画 (添加 show 类)
            setTimeout(function() {
                toastEl.classList.add('show');
            }, 10);

            var closeBtn = toastEl.querySelector('.passport-toast-close');
            closeBtn.addEventListener('click', function() {
                PassportToast.hide(toastEl);
            });

            if (duration > 0) {
                this.hideTimer = setTimeout(function() {
                    PassportToast.hide(toastEl);
                }, duration);
            }

            return toastEl;
        },

        hide: function(toastEl) {
            if (!toastEl) return;
            toastEl.classList.remove('show');
            toastEl.classList.add('hide');
            clearTimeout(this.hideTimer);
            setTimeout(function() {
                if (toastEl.parentNode) toastEl.parentNode.removeChild(toastEl);
                if (PassportToast.currentToast === toastEl) {
                    PassportToast.currentToast = null;
                }
            }, 200);
        },

        success: function(messages, duration) { return this.show(messages, 'success', duration); },
        error: function(messages, duration)   { return this.show(messages, 'error', duration); },
        warning: function(messages, duration) { return this.show(messages, 'warning', duration); },
        notice: function(messages, duration)  { return this.show(messages, 'notice', duration); },
        info: function(messages, duration)     { return this.show(messages, 'info', duration); }
    };

    // 全局输入内容检查与 Session 通知处理
    document.addEventListener('DOMContentLoaded', function() {
        // 检查是否有从 PHP Session 传递的通知信息
        <?php
        if (isset($_SESSION['passport_notice'])) {
            $notice = $_SESSION['passport_notice'];
            unset($_SESSION['passport_notice']);
            echo 'var passportNoticeData = ' . json_encode($notice, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';';
        } else {
            echo 'var passportNoticeData = null;';
        }
        ?>

        if (passportNoticeData) {
            var messages = passportNoticeData.message || '';
            var type = passportNoticeData.type || 'info';
            var countdown = passportNoticeData.countdown || 0;

            if (countdown > 0) {
                var minutes = Math.floor(countdown / 60);
                var seconds = countdown % 60;
                var countdownMsg = [
                    messages + ' 请在 <span id="countdown-min">' + minutes + '</span> 分 <span id="countdown-sec">' + (seconds < 10 ? '0' + seconds : seconds) + '</span> 秒后重试。'
                ];

                PassportToast.show(countdownMsg, type, 0);

                var remainingSeconds = countdown;
                var mEl = null, sEl = null;
                var countdownTimer = setInterval(function() {
                    remainingSeconds--;

                    if (remainingSeconds <= 0) {
                        clearInterval(countdownTimer);
                        var messageDiv = document.querySelector('.passport-toast-messages');
                        if (messageDiv) {
                            messageDiv.textContent = '现在可以刷新页面重试了。';
                        }
                        setTimeout(function() { PassportToast.hide(); }, 1000);
                        return;
                    }

                    if (!mEl) mEl = document.getElementById('countdown-min');
                    if (!sEl) sEl = document.getElementById('countdown-sec');

                    if (mEl && sEl) {
                        mEl.textContent = Math.floor(remainingSeconds / 60);
                        var rs = remainingSeconds % 60;
                        sEl.textContent = rs < 10 ? '0' + rs : '' + rs;
                    }
                }, 1000);
            } else {
                PassportToast.show(messages, type, 5000);
            }
        }

        // 为所有表单添加基本验证
        var forms = document.querySelectorAll('.passport-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var requiredInputs = form.querySelectorAll('[required]');
                var isValid = true;
                var errorMessage = '';

                requiredInputs.forEach(function(input) {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = 'var(--passport-danger)';
                        var label = input.closest('.passport-form-group').querySelector('.passport-label');
                        if (label) {
                            errorMessage = '\u8BF7\u586B\u5199' + label.textContent.replace(' *', '');
                        }
                    } else {
                        input.style.borderColor = 'var(--passport-border)';

                        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                            isValid = false;
                            input.style.borderColor = 'var(--passport-danger)';
                            errorMessage = '\u8BF7\u8F93\u5165\u6709\u6548\u7684\u90AE\u7BB1\u5730\u5740';
                        }

                        if (input.name === 'password' && input.value.length < 8) {
                            isValid = false;
                            input.style.borderColor = 'var(--passport-danger)';
                            errorMessage = '\u5BC6\u7801\u957F\u5EA6\u81F3\u5C11\u4E3A 4 \u4F4D';
                        }

                        if (input.name === 'confirm') {
                            var passwordInput = form.querySelector('input[name="password"]');
                            if (passwordInput && input.value !== passwordInput.value) {
                                isValid = false;
                                input.style.borderColor = 'var(--passport-danger)';
                                errorMessage = '\u4E24\u6B21\u5BC6\u7801\u8F93\u5165\u4E0D\u4E00\u81F4';
                            }
                        }
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    PassportToast.show(errorMessage, 'error');
                }
            });

            // 输入框焦点事件
            var inputs = form.querySelectorAll('.passport-input');
            inputs.forEach(function(input) {
                input.addEventListener('focus', function() {
                    this.style.borderColor = 'var(--passport-primary)';
                });

                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = 'var(--passport-border)';
                    } else {
                        if (this.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) {
                            this.style.borderColor = 'var(--passport-danger)';
                        } else if (this.name === 'password' && this.value.length < 8) {
                            this.style.borderColor = 'var(--passport-danger)';
                        } else if (this.name === 'confirm') {
                            var passwordInput = form.querySelector('input[name="password"]');
                            if (passwordInput && this.value !== passwordInput.value) {
                                this.style.borderColor = 'var(--passport-danger)';
                            } else {
                                this.style.borderColor = 'var(--passport-border)';
                            }
                        } else {
                            this.style.borderColor = 'var(--passport-border)';
                        }
                    }
                });
            });
        });
    });
</script>
