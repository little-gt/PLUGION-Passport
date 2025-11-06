# 🛡️ Passport - Typecho 密码找回插件

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg?style=flat)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.2-5c5c5c.svg?style=flat&logo=php)](https://www.php.net/)
[![Typecho](https://img.shields.io/badge/Typecho-1.2%2B-orange.svg?style=flat&logo=typecho)](https://typecho.org/)
[![Version](https://img.shields.io/badge/Version-0.1.2-007EC6.svg?style=flat)]()
[![Author](https://img.shields.io/badge/Author-GARFIELDTOM-6f42c1.svg?style=flat&logo=github)](https://garfieldtom.cool/)

---

> ✉️ **Passport** 是一个安全可靠的 Typecho 插件，用于为你的博客系统提供 **找回密码、重置密码** 功能，支持多种验证码系统与 SMTP 邮件配置。
> 插件遵循 GPLv2 协议，免费开源。

---

## 📸 界面预览

| 后台配置 | 前端重置 |
| :---: | :---: |
| ![screenshot_background](./screenshot1.png) | ![screenshot_frontground](./screenshot2.png) |

---

## ✨ 核心特性 (Features)

| 图标 | 特性 | 描述 |
| :---: | :--- | :--- |
| 🔒 | **安全令牌系统** | 基于 HMAC-SHA256 签名的密码重置令牌，确保链接的不可篡改和安全性。 |
| 📧 | **SMTP 邮件发送** | 内嵌 PHPMailer，支持 SSL/TLS 安全传输，配置灵活。|
| 🧩 | **多重验证码支持** | 无缝集成 Google reCAPTCHA v2, hCaptcha, 和 Geetest v4 等主流人机验证服务。|
| ⏱️ | **智能速率限制** | 基于 IP 的请求尝试计数与自动封禁，有效防止自动化暴力破解和邮件滥用。|
| 🎨 | **高度可定制** | 支持自定义邮件模板，灵活适应各种主题风格。|
| 🧑‍💻 | **可视化日志** | 后台管理界面提供风险日志（失败尝试、封禁状态）可视化，支持一键解封。|
| 🧠 | **强密码策略** | 在重置密码时强制进行复杂度检测（大写、小写、数字、特殊字符）。|

---

## 📦 安装指南 (Installation)

1.  下载最新版本 `Passport.zip`。
2.  解压后将文件夹放置于 Typecho 插件目录 `/usr/plugins/`，并命名为 `Passport`。
3.  登录 Typecho 后台，进入「控制台」->「插件」，启用 **Passport - 密码找回插件**。
4.  根据提示进入插件配置页面，完成以下关键设置：
    *   SMTP 服务信息 (用于发送重置邮件)。
    *   选择并配置人机验证类型与密钥 (强烈推荐启用)。
    *   检查并保存自动生成的 **HMAC 安全密钥**。
5.  保存配置，找回密码功能即可通过路由 `/passport/forgot` 和 `/passport/reset` 访问。

### 📌 提示：添加到登录页

若要在 Typecho 默认登录页 (`admin/login.php`) 添加“忘记密码”链接，请参考以下代码并手动插入到相应模板文件：

```php
// 找到这里 (位于 admin/login.php 底部)
<?php if($options->allowRegister): ?>
&bull;
<a href="<?php $options->registerUrl(); ?>"><?php _e('用户注册'); ?></a>
<?php endif; ?>

// 在其下方插入以下代码
<?php
   $activates = array_keys(Typecho_Plugin::export()['activated']);
   if (in_array('Passport', $activates)) {
       echo '&bull; <a href="' . Typecho_Common::url('passport/forgot', $options->index) . '">' . '忘记密码' . '</a>';
   }
?>
```

---

## ⚙️ 核心配置参考 (Configuration Reference)

| 分类 | 参数 | 说明 |
| :--- | :--- | :--- |
| **SMTP** | `host, port, username, password, secure` | 邮件发送服务配置（推荐使用 SSL/TLS）。 |
| **验证码** | `captchaType` | 选择人机验证类型：`none`, `recaptcha`, `hcaptcha`, `geetest`。 |
| **安全性** | `secretKey` | 用于令牌签名的 HMAC-SHA256 密钥，确保令牌防篡改。 |
| **高级** | `enableRateLimit` | 启用基于 IP 的请求速率限制和封禁，推荐开启。 |
| **数据管理** | `deleteDataOnDeactivate` | 禁用插件时是否同时清空所有数据（包括令牌和日志）。 |

---

## 🧠 技术优势 (Technical Highlights)

-   **数据库兼容性**：支持 MySQL, SQLite, PostgreSQL 等 Typecho 支持的数据库。
-   **安全机制**：内置安全封禁机制 (`passport_fails` 表) 和加密签名令牌。
-   **零依赖**：内置 `PHPMailer` 库，无需额外的 PHP 扩展或 Composer 依赖。

---

## 🔐 安全建议 (Security Best Practices)

-   ✅ 始终启用 **请求速率限制**。
-   ✅ 确保您的 **HMAC 密钥** 是强随机且保密的。
-   ✅ 优先开启 **SMTP SSL/TLS 传输**。
-   ✅ 配置 **reCAPTCHA / hCaptcha / Geetest** 以防御机器人攻击。

---

## 🧾 许可证 (License)

本项目采用 [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) 协议。

Copyright (c) 2025 GARFIELDTOM

---

## 💬 反馈与支持 (Feedback & Support)

-   作者博客：[https://blog.garfieldtom.cool/](https://blog.garfieldtom.cool/)
-   GitHub Issues：[提交问题或建议](https://github.com/GARFIELDTOM/Passport/issues)
-   安全报告：请参考 `SECURITY.md` 文档。

---

> **Passport v0.1.2** · “守护你的网站登录安全的第一道防线。”