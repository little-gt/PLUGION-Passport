<?php
/**
 * Passport 插件独立前端模板 - 静态资源
 *
 * 用于 Typecho 插件 Passport 的独立前端页面（如找回密码、重置密码）的静态资源。
 * 包含所有页面共享的 CSS 样式和 JavaScript 脚本。
 */

// 严格安全检查: 防止文件被直接访问。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- CSS 样式 --- ?>

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

    .passport-captcha-wrapper {
        position: relative;
        height: 48px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .passport-captcha-img {
        height: 48px;
        border: 2px solid var(--passport-border);
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
        width: 24px;
        height: 24px;
        border: 3px solid var(--passport-border);
        border-top-color: var(--passport-primary);
        border-radius: 50%;
        animation: passkeyRotate 0.8s linear infinite;
        display: none;
    }

    .passport-captcha-loader.active {
        display: block;
    }

    @keyframes passkeyRotate {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
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

    /* 页面内提示样式 - 与 Passkey 样式一致（扁平化设计） */
    .passport-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 16px;
        border-radius: 0;
        font-size: 14px;
        z-index: 99999;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transition: all 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        opacity: 0;
        transform: translateX(100px);
        animation: passkeySlideIn 0.3s ease forwards;
        border: 1px solid;
    }

    .passport-toast.success {
        background-color: rgba(16, 185, 129, 0.1);
        border-color: #10b981;
        color: #10b981;
    }

    .passport-toast.error {
        background-color: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #ef4444;
    }

    .passport-toast.warning {
        background-color: rgba(245, 158, 11, 0.1);
        border-color: #f59e0b;
        color: #f59e0b;
    }

    .passport-toast.info {
        background-color: rgba(70, 123, 150, 0.1);
        border-color: #467b96;
        color: #467b96;
    }

    .passport-toast-icon {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        color: inherit;
    }

    .passport-toast-icon.success {
        color: inherit;
    }

    .passport-toast-icon.error {
        color: inherit;
    }

    .passport-toast-icon.warning {
        color: inherit;
    }

    .passport-toast-icon.info {
        color: inherit;
    }

    .passport-toast-icon svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        fill: none;
    }

    .passport-toast-message {
        flex: 1;
        color: inherit;
        line-height: 1.5;
        word-break: break-word;
    }

    .passport-toast-close {
        background: none;
        border: none;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        color: inherit;
        opacity: 0.6;
        padding: 0;
        margin-left: 8px;
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        transition: opacity 0.2s ease;
    }

    .passport-toast-close:hover {
        opacity: 1;
    }

    .passport-toast.hiding {
        animation: passkeySlideOut 0.3s ease forwards;
    }

    @keyframes passkeySlideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes passkeySlideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
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

        .passport-toast {
            left: 10px;
            right: 10px;
            min-width: auto;
            max-width: none;
        }
    }
</style>

<?php // --- JavaScript 脚本 --- ?>

<script>
    // SVG 图标库（与 Passkey 一致）
    var PassportSVGIcons = {
        check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        alert: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    // 页面内提示系统（重构版，与 Passkey 样式一致）
    var PassportToast = {
        currentToast: null,
        
        show: function(message, type, duration) {
            type = type || 'info';
            duration = typeof duration !== 'undefined' ? duration : 5000;
            
            // 移除已存在的提示
            this.hide();

            // 创建提示元素
            var toast = document.createElement('div');
            toast.className = 'passport-toast passport-toast-' + type;
            toast.id = 'passport-toast';

            // 选择图标
            var iconSvg = '';
            switch(type) {
                case 'success':
                    iconSvg = PassportSVGIcons.check;
                    break;
                case 'error':
                    iconSvg = PassportSVGIcons.x;
                    break;
                case 'warning':
                    iconSvg = PassportSVGIcons.alert;
                    break;
                default:
                    iconSvg = PassportSVGIcons.info;
            }

            // 图标容器
            var iconSpan = document.createElement('span');
            iconSpan.className = 'passport-toast-icon ' + type;
            iconSpan.innerHTML = iconSvg;

            // 消息容器
            var messageDiv = document.createElement('div');
            messageDiv.className = 'passport-toast-message';
            messageDiv.innerHTML = message;

            // 关闭按钮
            var closeBtn = document.createElement('button');
            closeBtn.className = 'passport-toast-close';
            closeBtn.innerHTML = '×';
            closeBtn.onclick = function() {
                PassportToast.hide();
            };

            toast.appendChild(iconSpan);
            toast.appendChild(messageDiv);
            toast.appendChild(closeBtn);
            document.body.appendChild(toast);

            this.currentToast = toast;

            // 自动隐藏（如果 duration > 0）
            if (duration > 0) {
                setTimeout(function() {
                    PassportToast.hide();
                }, duration);
            }

            return toast;
        },
        
        hide: function() {
            if (this.currentToast && this.currentToast.parentNode) {
                this.currentToast.classList.add('hiding');
                var toastToRemove = this.currentToast;
                setTimeout(function() {
                    if (toastToRemove.parentNode) {
                        toastToRemove.parentNode.removeChild(toastToRemove);
                    }
                }, 300);
                this.currentToast = null;
            }
        }
    };

    // 全局输入内容检查
    document.addEventListener('DOMContentLoaded', function() {
        // 检查是否有从 PHP Session 传递的通知信息
        <?php
        if (isset($_SESSION['passport_notice'])) {
            $notice = $_SESSION['passport_notice'];
            // 清除 Session 中的通知
            unset($_SESSION['passport_notice']);
            // 使用 JSON 安全地传递数据到 JavaScript
            echo 'var passportNoticeData = ' . json_encode($notice, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';';
        } else {
            echo 'var passportNoticeData = null;';
        }
        ?>

        // 处理通知显示
        if (passportNoticeData) {
            var message = passportNoticeData.message || '';
            var type = passportNoticeData.type || 'info';
            var countdown = passportNoticeData.countdown || 0;

            if (countdown > 0) {
                // 有倒计时的情况 - 不自动关闭
                var minutes = Math.floor(countdown / 60);
                var seconds = countdown % 60;
                var countdownMsg = message + ' 请在 <span id="countdown-min">' + minutes + '</span> 分 <span id="countdown-sec">' + (seconds < 10 ? '0' + seconds : seconds) + '</span> 秒后重试。';
                
                // duration = 0 表示不自动关闭
                PassportToast.show(countdownMsg, type, 0);
                
                // 启动倒计时
                var remainingSeconds = countdown;
                var countdownTimer = setInterval(function() {
                    remainingSeconds--;
                    
                    if (remainingSeconds <= 0) {
                        clearInterval(countdownTimer);
                        
                        // 倒计时结束，更新消息
                        var messageDiv = document.querySelector('.passport-toast-message');
                        if (messageDiv) {
                            messageDiv.textContent = '现在可以刷新页面重试了。';
                        }
                        
                        // 延时1秒后自动关闭
                        setTimeout(function() {
                            PassportToast.hide();
                        }, 1000);
                        return;
                    }
                    
                    // 更新倒计时显示
                    var mEl = document.getElementById('countdown-min');
                    var sEl = document.getElementById('countdown-sec');
                    if (mEl && sEl) {
                        mEl.textContent = Math.floor(remainingSeconds / 60);
                        var rs = remainingSeconds % 60;
                        sEl.textContent = rs < 10 ? '0' + rs : rs;
                    }
                }, 1000);
            } else {
                // 普通提示 - 5秒后自动关闭
                PassportToast.show(message, type, 5000);
            }
        }

        // 为所有表单添加基本验证
        const forms = document.querySelectorAll('.passport-form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // 基本的客户端验证
                const requiredInputs = form.querySelectorAll('[required]');
                let isValid = true;
                let errorMessage = '';
                
                requiredInputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = 'var(--passport-error)';
                        const label = input.closest('.passport-form-group').querySelector('.passport-label');
                        if (label) {
                            errorMessage = `请填写${label.textContent.replace(' *', '')}`;
                        }
                    } else {
                        input.style.borderColor = 'var(--passport-border)';
                        
                        // 邮箱格式验证
                        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                            isValid = false;
                            input.style.borderColor = 'var(--passport-error)';
                            errorMessage = '请输入有效的邮箱地址';
                        }
                        
                        // 密码长度验证
                        if (input.name === 'password' && input.value.length < 8) {
                            isValid = false;
                            input.style.borderColor = 'var(--passport-error)';
                            errorMessage = '密码长度至少8位';
                        }
                        
                        // 密码确认验证
                        if (input.name === 'confirm') {
                            const passwordInput = form.querySelector('input[name="password"]');
                            if (passwordInput && input.value !== passwordInput.value) {
                                isValid = false;
                                input.style.borderColor = 'var(--passport-error)';
                                errorMessage = '两次输入的密码不一致';
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
            const inputs = form.querySelectorAll('.passport-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = 'var(--passport-primary)';
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = 'var(--passport-border)';
                    } else {
                        // 实时验证
                        if (this.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) {
                            this.style.borderColor = 'var(--passport-error)';
                        } else if (this.name === 'password' && this.value.length < 8) {
                            this.style.borderColor = 'var(--passport-error)';
                        } else if (this.name === 'confirm') {
                            const passwordInput = form.querySelector('input[name="password"]');
                            if (passwordInput && this.value !== passwordInput.value) {
                                this.style.borderColor = 'var(--passport-error)';
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