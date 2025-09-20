# Passport - Typecho 密码找回插件

[![版本](https://img.shields.io/badge/version-0.0.4-blue.svg)](https://github.com/typecho-fans/plugins/tree/master/Passport)
[![兼容性](https://img.shields.io/badge/Typecho-1.1%2B-green.svg)](https://forum.typecho.org/viewtopic.php?p=61523)
[![License](https://img.shields.io/badge/license-GPLv2-brightgreen.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

![截图](/screenshot.png)

本插件为 Typecho 博客系统提供密码找回功能。用户可以通过注册邮箱接收密码重置链接，从而重新设置账户密码。

**原始仓库地址：** [typecho-fans/plugins/Passport](https://github.com/typecho-fans/plugins/tree/master/Passport)

此版本在原版基础上进行了功能增强和 UI 优化。

## 结构树

        ```
        /usr/plugins/
        └── Passport/
            ├── Plugin.php // 插件激活与卸载
            ├── Widget.php // 插件主逻辑模块
            ├── template/
            │   ├── forgot.php // 页面 - 请求找回密码
            │   ├── reset.php  // 页面 - 设置新的密码
            │   ├── common.php
            │   └── header.php
            └── PHPMailer/       <-- PHPMailer 库文件夹
                ├── Exception.php
                ├── PHPMailer.php
                └── SMTP.php
        ```

## 主要功能

*   通过邮件发送密码重置链接。
*   支持 SMTP 服务器配置，保证邮件发送的可靠性。
*   可自定义密码重置邮件模板。
*   支持多种验证码服务：
    *   Google reCAPTCHA v2
    *   hCaptcha
    *   Geetest 4.0（极验4.0）
    *   可选择不使用验证码
*   验证码配置项根据所选类型动态显示，界面更简洁。（修复）
*   找回密码和重置密码页面的验证码 UI 左对齐，更美观。
*   使用最新的 PHPMailer 库特性。
*   安全可靠的 Token 生成与验证机制。

## 系统要求

*   Typecho 1.1 或更高版本。
*   PHP 7.2 或更高版本 (推荐 PHP 7.4+)。
*   服务器支持 `file_get_contents` (需开启 `allow_url_fopen=On`) 或 cURL 扩展 (用于验证码服务)。

## 安装方法

1.  **下载插件：**
    *   从本仓库（或您获取插件的来源）下载插件的最新版本。
    *   解压下载的文件。
2.  **重命名文件夹：**
    *   将解压后的文件夹重命名为 `Passport`。
3.  **上传插件：**
    *   `Passport` 文件夹完整上传到您的 Typecho 安装目录下的 `/usr/plugins/` 目录。
4.  **激活插件：**
    *   登录 Typecho 后台，进入“控制台” -> “插件”管理页面。
    *   找到“Passport”插件，点击“激活”。

## 配置插件

激活插件后，请点击“设置”进行配置：

1.  **SMTP 服务器信息：**
    *   **服务器(SMTP)：** 您的邮件服务提供商的 SMTP 服务器地址 (例如: `smtp.example.com`)。
    *   **端口：** SMTP 服务器端口 (例如: `465` for SSL, `587` for TLS, `25` for non-secure)。
    *   **帐号：** 用于发送邮件的邮箱地址。
    *   **密码：** 上述邮箱的密码或授权码。
    *   **安全类型：** 选择 `SSL`, `TLS` 或 `无`。
2.  **验证码类型：**
    *   从下拉列表中选择您希望使用的验证码服务：
        *   **不使用验证码：** 不启用任何验证码，请忽略展示的填写验证码的输入框。
        *   **Google reCAPTCHA v2：** 如果选择此项，请填写 reCAPTCHA Site Key 和 Secret Key 输入框。
        *   **hCaptcha：** 如果选择此项，请填写 hCaptcha Site Key 和 Secret Key 输入框。
3.  **reCAPTCHA/hCaptcha 密钥：**
    *   **Site Key：** 填入您从 reCAPTCHA 或 hCaptcha 官网获取的 Site Key。
    *   **Secret Key：** 填入您从 reCAPTCHA 或 hCaptcha 官网获取的 Secret Key。
    *   *(注意：只有当选择了对应的验证码类型时，这些输入框才会显示。)*
4.  **邮件模板：**
    *   您可以自定义发送给用户的密码重置邮件内容。可用的占位符有：
        *   `{username}`：用户名
        *   `{sitename}`：网站名称
        *   `{requestTime}`：请求重置的时间
        *   `{resetLink}`：密码重置链接
5.  点击“保存设置”。

## 使用方法

1.  **添加找回密码入口 (推荐)：**
    *   为了方便用户，建议您在网站的登录页面 (通常是 `admin/login.php` 或主题的登录模板) 添加一个“忘记密码？”的链接，指向 `您的域名/passport/forgot`。
    *   例如，在 `admin/login.php` 文件中，您可以在登录表单附近添加：
        ```php
        <?php
           $activates = array_keys(Typecho_Plugin::export()['activated']);
           if (in_array('Passport', $activates)) {
               echo '<a href="' . Typecho_Common::url('passport/forgot', $options->index) . '">' . '忘记密码' . '</a>';
           }
        ?>
        ```
2.  **用户找回密码流程：**
    *   用户访问 `您的域名/passport/forgot` 页面。
    *   输入注册时使用的邮箱地址。
    *   如果启用了验证码，用户需要完成人机验证。
    *   点击“提交”。
    *   系统会向该邮箱发送一封包含密码重置链接的邮件。
3.  **用户重置密码流程：**
    *   用户点击邮件中的重置链接，该链接有效期为 1 小时。
    *   用户将被引导至 `您的域名/passport/reset?token=xxx` 页面。
    *   输入新密码并确认新密码。
    *   如果启用了验证码，用户需要完成人机验证。
    *   点击“更新密码”。
    *   密码重置成功后，用户将被引导至登录页面。

## 常见问题与排查

*   **邮件发送失败：**
    *   检查 SMTP 配置是否正确（服务器、端口、用户名、密码、安全类型）。
    *   确认您的邮箱账户已开启 SMTP 服务，并且密码或授权码正确。
    *   检查服务器防火墙是否阻止了到 SMTP 服务器端口的连接。
    *   查看垃圾邮件箱，邮件可能被误判。
    *   尝试更换其他 SMTP 服务提供商。
*   **验证码不显示或工作不正常：**
    *   确认在插件设置中选择了正确的验证码类型，并填写了对应的 Site Key 和 Secret Key。
    *   确保您的域名已在 reCAPTCHA/hCaptcha 后台正确注册。
    *   检查浏览器控制台是否有 JavaScript 错误。
    *   如果您的服务器在国内，访问 Google reCAPTCHA 可能会受限，可以尝试使用 `recaptcha.net` (插件已默认使用)。
*   **链接失效：**
    *   密码重置链接的有效期为1小时。过期后需要重新申请。
    *   如果用户在申请链接后更改了邮箱或密码，旧的重置链接可能会失效。

## 版本历史

*   **v0.0.4**
    *   新增：集成 Geetest v4 (极验) 行为验证码。
    *   优化：动态分组的插件后台配置界面。
    *   优化：优化了人机验证的流程逻辑，更快更安全。
*   **v0.0.3**
    *   新增：添加 HMAC 签名验证机制，通过随机的 HMAC 字符串进行签名加密。
    *   优化：优化 Token 的处理机制，在每次访问forgot时检查 Token 并且清除过期的 Token 。
*   **v0.0.2**
    *   新增：支持 hCaptcha。
    *   新增：可在 reCAPTCHA v2、hCaptcha、不使用验证码之间选择。
    *   优化：Token 生成与验证逻辑，增强安全性。
    *   更新：PHPMailer 最新特性。
*   **v0.0.1**
    *   新增：支持自定义邮件模版；
    *   更新：密码找回功能的结构；
    *   更新：PHPMailer 为最新版本。
    *   添加：reCAPTCHA v2 支持。

## 许可证

本插件基于 [GNU General Public License 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html) 授权。
