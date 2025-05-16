# Passport - Typecho 增强版密码找回插件

一个为 Typecho 博客系统设计的密码找回插件，经过增强，支持多种现代 CAPTCHA 验证方式、POP-before-SMTP、CSRF防护以及更灵活的邮件发送配置。

## 主要特性

*   **优化逻辑**: 优化了原始实现逻辑，符合现代编码规范，提高代码复用率和安全性。
*   **多种CAPTCHA支持**:
    *   Google reCAPTCHA v2 ("我不是机器人"复选框)
    *   Google reCAPTCHA v3 (隐形，基于分数评估)
    *   hCaptcha ("我是人类"复选框)
    *   可选择不使用任何 CAPTCHA。
*   **邮件发送增强**:
    *   支持 SMTP (SSL/TLS/无加密) 发送邮件。
    *   支持 POP-before-SMTP 认证（适用于部分邮件服务商）。
*   **自定义邮件模板**: 允许自定义密码找回邮件的样式和内容，支持占位符。
*   **最新 PHPMailer**: 集成 PHPMailer 6.9.3 版本，提供稳定可靠的邮件发送功能。
*   **兼容性**: 支持最新版 Typecho，并努力保持向下兼容。
*   **安全性增强**:
    *   内置 CSRF (跨站请求伪造) 防护。
    *   更严格的重置令牌处理。

## 系统需求

*   Typecho 1.x 或更高版本。
*   PHP 扩展：
    *   `intl` (推荐，用于 PHPMailer 的 IDN 支持)
    *   `mbstring` (推荐，用于 PHPMailer 的字符编码和 IDN 支持)
    *   `openssl` (必须，用于 SSL/TLS 加密连接和 PHPMailer 的部分功能)
*   如果使用 reCAPTCHA v3 或 hCaptcha，用户端的表单提交将依赖 JavaScript。

## 安装步骤

1.  **下载插件**:
    *   从此仓库下载最新版本的插件包 (通常是 `Passport.zip`)。
2.  **上传插件**:
    *   将下载的插件压缩包解压。
    *   将解压后的 `Passport` 文件夹完整上传到 Typecho 安装目录下的 `usr/plugins/` 目录中。
3.  **激活插件**:
    *   登录 Typecho 后台，进入 "控制台" -> "插件"。
    *   找到 "Passport" 插件，点击 "启用"。
4.  **配置插件**:
    *   启用后，点击 "Passport" 插件下方的 "设置" 链接，根据您的需求配置邮件服务器和 CAPTCHA 参数。
5.  **重要：修改 `login.php` 文件**:
    为了在登录页面显示 "忘记密码" 的链接，您需要手动修改您当前使用的主题（或 Typecho 默认后台）的 `login.php` 文件。
    通常，此文件位于：
    *   Typecho 默认后台: `/admin/login.php`
    *   部分自定义主题也可能在主题目录的 `admin/` 或类似路径下有自己的 `login.php`。

    打开 `login.php` 文件，找到类似以下的代码段：
    ```php
    <?php if($options->allowRegister): ?>
    •
    <a href="<?php $options->registerUrl(); ?>"><?php _e('用户注册'); ?></a>
    <?php endif; ?>
    ```
    在其 **下方** 插入以下代码：
    ```php
    <?php
       $passportWidget = Typecho_Widget::widget('Widget_Options')->plugin('Passport');
       if ($passportWidget) { // 检查插件是否已加载配置
           $activatedPlugins = Typecho_Plugin::export()['activated'];
           if (in_array('Passport', $activatedPlugins)) {
               echo '• <a href="' . Typecho_Common::url('passport/forgot', $options->index) . '">' . _t('忘记密码') . '</a>';
           }
       }
    ?>
    ```
    **提示**:
    *   如果您不确定 `login.php` 的位置，或者修改后链接未出现，请检查您是否修改了正确的文件（特别是如果您使用了高度自定义的主题）。
    *   您可以根据需要调整链接插入的位置和样式。

## 配置指南

插件激活后，请务必进入 "插件设置" 页面进行详细配置。

### 1. SMTP 设置

*   **SMTP 服务器**: 您的邮件发送服务器地址 (例如: `smtp.exmail.qq.com`, `smtp.gmail.com`)。
*   **SMTP 端口**: 您的邮件发送服务器端口 (通常 SSL 为 `465`, TLS 为 `587`, 非加密为 `25`)。
*   **SMTP 帐号**: 用于发送邮件的邮箱帐号 (例如: `user@example.com`)。
*   **SMTP 密码**: 上述邮箱帐号的密码或授权码。
*   **SMTP 安全类型**: 选择邮件服务器要求的加密类型 (SSL, TLS, 或 无)。推荐使用 SSL 或 TLS。

### 2. POP-before-SMTP 设置 (可选)

某些邮件服务商可能要求在发送 SMTP 邮件之前先进行一次 POP3 登录认证。
*   **启用 POP-before-SMTP**: 选择 "启用" 以开启此功能。
*   **POP3 服务器**: 您的 POP3 服务器地址 (例如: `pop.example.com`)。
*   **POP3 端口**: 您的 POP3 服务器端口 (通常非加密为 `110`, SSL 为 `995`)。
*   **POP3 帐号**: 通常与您的 SMTP 帐号相同。
*   **POP3 密码**: POP3 帐号的密码。

### 3. CAPTCHA 设置

选择一种人机验证服务来防止机器人滥用密码找回功能。
*   **选择 CAPTCHA 提供商**:
    *   **无**: 不使用任何 CAPTCHA (不推荐)。
    *   **Google reCAPTCHA v2**: 显示 "我不是机器人" 复选框。
        *   **reCAPTCHA v2 Site Key (站点密钥)**: 从 Google reCAPTCHA 管理后台获取。
        *   **reCAPTCHA v2 Secret Key (密钥)**: 从 Google reCAPTCHA 管理后台获取。
    *   **Google reCAPTCHA v3**: 隐形验证，基于用户行为评分。
        *   **reCAPTCHA v3 Site Key (站点密钥)**: 从 Google reCAPTCHA 管理后台获取。
        *   **reCAPTCHA v3 Secret Key (密钥)**: 从 Google reCAPTCHA 管理后台获取。
        *   **reCAPTCHA v3 分数阈值**: 输入 0 到 1 之间的小数 (例如 `0.5`)。只有当用户得分高于此阈值时，验证才通过。
    *   **hCaptcha**: 显示 "我是人类" 复选框。
        *   **hCaptcha Site Key (站点密钥)**: 从 hCaptcha 管理后台获取。
        *   **hCaptcha Secret Key (密钥)**: 从 hCaptcha 管理后台获取。

    **注意**: 您只需为所选的 CAPTCHA 提供商填写对应的 Key 和 Secret。确保您在相应的 CAPTCHA 服务提供商处注册了您的网站域名。

### 4. 邮件模板设置

*   **邮件模板**: 自定义发送给用户的密码重置邮件内容。支持以下占位符：
    *   `{username}`: 用户名
    *   `{sitename}`: 网站名称
    *   `{requestTime}`: 请求重置的时间
    *   `{resetLink}`: 密码重置链接

## 使用流程

1.  用户在登录页面点击 "忘记密码" 链接。
2.  进入密码找回页面，输入注册时使用的邮箱地址，并根据配置完成 CAPTCHA 验证。
3.  系统验证邮箱和 CAPTCHA 后，会向该邮箱发送一封包含密码重置链接的邮件。
4.  用户点击邮件中的重置链接（链接通常有1小时的时效性）。
5.  进入新密码设置页面，输入新密码并确认，可能需要再次完成 CAPTCHA 验证。
6.  密码更新成功后，用户可以使用新密码登录。

## 故障排除

*   **邮件发送失败**:
    *   仔细检查 SMTP 和 POP-before-SMTP (如果启用) 的服务器地址、端口、用户名、密码和加密方式是否正确。
    *   部分邮件服务商（如 Gmail）可能需要您开启 "允许不够安全的应用" 或生成 "应用专用密码"。
    *   检查服务器防火墙是否允许到邮件服务器端口的出站连接。
    *   查看 Typecho 运行环境的 PHP 错误日志，以及邮件服务商的发送日志，可能会有更详细的错误信息。
    *   如果您的 Typecho 开启了调试模式，PHPMailer 的调试信息可能会输出到 PHP 错误日志中。
*   **CAPTCHA 不显示或验证失败**:
    *   确认您填写的 Site Key 和 Secret Key 是否正确，且与所选的 CAPTCHA 提供商和版本匹配。
    *   确保您已在 CAPTCHA 服务提供商的管理后台正确添加了您的网站域名。
    *   检查浏览器控制台是否有 JavaScript 错误。
    *   部分地区的网络环境可能无法顺畅访问 Google reCAPTCHA 服务。
*   **"忘记密码" 链接未出现**:
    *   确认您已正确修改了 `login.php` 文件，并且修改的是当前主题正在使用的 `login.php`。
    *   清除浏览器缓存和 Typecho 缓存（如果您的 Typecho 安装了缓存插件）。
*   **CSRF 错误或 Token 验证失败**:
    *   尝试清除浏览器缓存和 Cookie。
    *   检查是否有其他插件或自定义代码干扰了表单提交流程。

## 安全注意事项

*   **密钥安全**: 妥善保管您的邮件服务器密码、授权码以及 CAPTCHA 的 Secret Keys，不要泄露给第三方。
*   **reCAPTCHA v3 阈值**: 如果使用 reCAPTCHA v3，请根据您网站的实际机器人活动情况和用户体验，在 Google reCAPTCHA 管理后台和插件设置中调整合适的分数阈值。过高可能误伤正常用户，过低则可能无法有效阻止机器人。
*   **定期更新**: 及时更新 Typecho 程序、此插件以及服务器上的 PHP 版本，以获取最新的安全修复。

## 贡献

欢迎通过 Pull Requests 或 Issues 为此插件做出贡献。

## 许可协议

[GNU General Public License 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## 致谢

*   原始插件由 GARFIELDTOM 和 小否先生 开发。
*   此增强版本在原始插件基础上进行了功能扩展和安全加固。
*   感谢 PHPMailer 团队提供的优秀邮件库。