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

        /* 页面内提示样式 */
        .passport-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 0;
            font-size: 14px;
            font-weight: 600;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: none;
            min-width: 280px;
            opacity: 1;
        }

        .passport-toast.success {
            background-color: #059669;
            color: #ffffff;
        }

        .passport-toast.error {
            background-color: #dc2626;
            color: #ffffff;
        }

        .passport-toast.warning {
            background-color: #d97706;
            color: #ffffff;
        }

        .passport-toast.info {
            background-color: #3a6378;
            color: #ffffff;
        }

        .passport-toast svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
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
                width: 90%;
                max-width: 300px;
            }
        }
    </style>

<?php // --- JavaScript 脚本 --- ?>

<script>
    // 页面内提示系统
    const PassportToast = {
        show: function(message, type = 'info', duration = 3000) {
            // 移除已存在的提示
            this.hide();

            // 创建提示元素
            const toast = document.createElement('div');
            toast.className = `passport-toast ${type}`;
            toast.id = 'passport-toast';

            // 添加图标
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<svg t="1772371546847" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2606" width="24" height="24"><path d="M960 256L448 896 64 576l128-128 234.112 187.328L832 128z" fill="currentColor" p-id="2607"></path></svg>';
                    break;
                case 'error':
                    icon = '<svg t="1772371590078" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2767" width="24" height="24"><path d="M828.32 828.32a448 448 0 1 1 0-633.6 448 448 0 0 1 0 633.6z m-565.76-67.84a352.288 352.288 0 0 0 463.68 29.76L232.8 296.8a352.288 352.288 0 0 0 29.76 463.68z m497.92-497.92a352.288 352.288 0 0 0-463.68-29.76l493.44 493.44a352.288 352.288 0 0 0-29.76-463.68z" fill="currentColor" p-id="2768"></path></svg>';
                    break;
                case 'warning':
                    icon = '<svg t="1772371661629" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2928" width="24" height="24"><path d="M64 960V320h192V64h512v256h192v640H64zM640 192h-256v128h256V192z m192 256H192v384h640V448z m-256 320h-128v-256h128v256z" fill="currentColor" p-id="2929"></path></svg>';
                    break;
                default:
                    icon = '<svg t="1772371680367" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3089" width="24" height="24"><path d="M1001.216 858.688a168.096 168.096 0 0 1-231.296 62.816 172.8 172.8 0 0 1-61.984-234.432 168.16 168.16 0 0 1 146.464-85.088c0.832-10.016 2.464-19.84 2.464-30.016a348.288 348.288 0 0 0-105.6-251.424l70.4-74.144a450.656 450.656 0 0 1 136.832 325.568 466.4 466.4 0 0 1-4.608 62.976 172.448 172.448 0 0 1 47.328 223.744zM512.224 342.88a168.256 168.256 0 0 1-146.624-87.232 345.952 345.952 0 0 0-189.184 237.44l-98.976-23.072a447.328 447.328 0 0 1 267.264-317.248 169.12 169.12 0 1 1 167.52 190.112zM316.416 686.4a172.8 172.8 0 0 1-0.704 171.424 341.408 341.408 0 0 0 196.512 63.36 335.488 335.488 0 0 0 102.4-16l30.56 98.304a438.656 438.656 0 0 1-132.992 20.64 443.296 443.296 0 0 1-274.08-96 167.648 167.648 0 0 1-215.04-70.4 172.8 172.8 0 0 1 61.984-234.432 168.128 168.128 0 0 1 231.36 63.136z" fill="currentColor" p-id="3090"></path></svg>';
            }

            toast.innerHTML = `${icon} ${message}`;
            document.body.appendChild(toast);

            // 显示动画
            setTimeout(() => {
                toast.style.opacity = '1';
            }, 10);

            // 自动隐藏
            if (duration > 0) {
                setTimeout(() => {
                    this.hide();
                }, duration);
            }

            return toast;
        },
        hide: function() {
            const toast = document.getElementById('passport-toast');
            if (toast) {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }
    };

    // 全局输入内容检查
    document.addEventListener('DOMContentLoaded', function() {
        // 拦截Typecho的提示
        if (typeof Typecho !== 'undefined' && Typecho.Notification) {
            const originalNotify = Typecho.Notification.notify;
            Typecho.Notification.notify = function(type, message) {
                PassportToast.show(message, type);
                return null;
            };
        }

        // 替换已有的Typecho通知
        setTimeout(function() {
            // 处理旧版本的Typecho通知
            const oldTypechoNotices = document.querySelectorAll('.message.popup');
            oldTypechoNotices.forEach(notice => {
                let message = '';
                let type = 'info';
                
                // 提取消息内容
                const messageElement = notice.querySelector('li');
                if (messageElement) {
                    message = messageElement.textContent.trim();
                }
                
                // 确定消息类型
                if (notice.classList.contains('success')) {
                    type = 'success';
                } else if (notice.classList.contains('error')) {
                    type = 'error';
                } else if (notice.classList.contains('notice')) {
                    type = 'warning';
                }
                
                // 显示自定义通知
                if (message) {
                    PassportToast.show(message, type);
                }
                
                // 移除原始通知
                notice.remove();
            });

            // 处理新版本的Typecho通知
            const newTypechoNotices = document.querySelectorAll('.typecho-notification');
            newTypechoNotices.forEach(notice => {
                let message = '';
                let type = 'info';
                
                // 提取消息内容
                const messageElement = notice.querySelector('.typecho-notification-messages');
                if (messageElement) {
                    message = messageElement.textContent.trim();
                }
                
                // 确定消息类型
                if (notice.classList.contains('success')) {
                    type = 'success';
                } else if (notice.classList.contains('error')) {
                    type = 'error';
                } else if (notice.classList.contains('notice')) {
                    type = 'warning';
                }
                
                // 显示自定义通知
                if (message) {
                    PassportToast.show(message, type);
                }
                
                // 移除原始通知
                notice.remove();
            });

            // 移除通知容器
            const notificationContainer = document.getElementById('typecho-notification-container');
            if (notificationContainer) {
                notificationContainer.remove();
            }
        }, 100);

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