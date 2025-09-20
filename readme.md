# Passport - Typecho 密码找回插件

[![版本](https://img.shields.io/badge/version-0.0.4-blue.svg)](https://github.com/typecho-fans/plugins/tree/master/Passport)
[![兼容性](https://img.shields.io/badge/Typecho-1.1%2B-green.svg)](https://typecho.org/)
[![License](https://img.shields.io/badge/license-GPLv2-brightgreen.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

本插件为 Typecho 博客系统提供了一个安全、可靠的密码找回功能。用户可以通过注册邮箱接收密码重置链接，并在多种现代验证码的保护下，安全地重设账户密码。

![截图](/screenshot.png)

## 主要功能

*   **多验证码支持：**
    *   Google reCAPTCHA v2
    *   hCaptcha
    *   【新】Geetest v4 (极验行为验证)
    *   可选择不使用验证码
*   **动态配置界面：** 验证码设置项根据所选类型动态显示，后台配置界面干净、直观。
*   **高度安全：**
    *   通过邮件发送一次性、有时效（1小时）的密码重置链接。
    *   使用 HMAC-SHA256 签名验证重置令牌，防止链接被篡改。
*   **灵活的邮件服务：**
    *   支持自定义 SMTP 服务器，保证邮件发送的成功率。
    *   支持自定义邮件模板，可使用 `{username}`, `{sitename}`, `{requestTime}`, `{resetLink}` 等占位符。
*   **现代代码库：** 集成最新版本的 PHPMailer 库，确保兼容性和稳定性。

## 系统要求

*   Typecho 1.1 或更高版本
*   PHP 7.2 或更高版本 (推荐 7.4+)
*   服务器需开启 `allow_url_fopen=On` 或已安装 cURL 扩展

## 安装方法

1.  下载插件最新版本的压缩包并解压。
2.  将解压后的文件夹重命名为 `Passport`。
3.  将 `Passport` 文件夹上传至 Typecho 安装目录下的 `/usr/plugins/` 目录。
4.  登录 Typecho 后台，进入 **控制台** -> **插件**，找到“Passport”并点击 **激活**。

## 配置插件

激活后，请点击 **设置** 以配置插件：

1.  **SMTP 服务器信息：** 准确填写您的邮件服务商提供的 `服务器地址`、`端口`、`邮箱帐号`、`密码(或授权码)` 及 `安全类型(SSL/TLS)`。
2.  **验证码类型：** 从下拉菜单中选择您要使用的验证码服务。
    *   **Google reCAPTCHA v2：** 需填写下方的 `reCAPTCHA Site Key` 和 `Secret Key`。
    *   **hCaptcha：** 需填写下方的 `hCaptcha Site Key` 和 `Secret Key`。
    *   **Geetest v4：** 需填写下方的 `Geetest CAPTCHA ID` 和 `CAPTCHA KEY`。
    *   **不使用验证码：** 不进行人机验证（不推荐）。
    *   *(注意：对应的密钥输入框会根据您的选择自动显示或隐藏。)*
3.  **HMAC 密钥：** 建议填写一个 32 位以上的随机字符串，用于增强重置链接的安全性。留空将禁用签名验证。
4.  **邮件模板：** 按需修改邮件内容。
5.  点击 **保存设置**。

## 使用方法

1.  **添加入口链接 (推荐):**
    为方便用户，建议在登录页面（如 `admin/login.php`）添加找回密码的入口。在登录按钮附近加入以下代码：
    ```php
    <p>
      <a href="<?php $options->adminUrl('login.php'); ?>"><?php _e('返回登录'); ?></a>
      <?php
         $plugins = Typecho_Plugin::export();
         if (array_key_exists('Passport', $plugins['activated'])) {
             echo ' • <a href="' . Typecho_Common::url('passport/forgot', $options->index) . '">' . '忘记密码?' . '</a>';
         }
      ?>
    </p>
    ```

2.  **用户操作流程：**
    *   用户访问 `您的域名/passport/forgot` 页面，输入注册邮箱并完成人机验证。
    *   系统会向该邮箱发送一封包含密码重置链接的邮件（链接有效期1小时）。
    *   用户点击邮件中的链接，跳转至重置页面，输入新密码并完成人机验证。
    *   密码更新成功，页面自动跳转至登录页。

## 常见问题

*   **邮件发送失败：**
    *   请仔细核对 SMTP 配置是否完全正确。
    *   确认邮箱的 SMTP 服务已开启，且使用的是正确的密码或**授权码**。
    *   检查服务器防火墙是否开放了对应的 SMTP 端口。
*   **验证码无法显示/工作：**
    *   确认在插件设置中填写了与所选类型匹配的、正确的密钥。
    *   确保您的域名已在相应验证码服务商（Google/hCaptcha/Geetest）的后台进行了正确配置。
    *   若服务器位于中国大陆，访问 Google reCAPTCHA 可能受阻，插件已默认使用 `recaptcha.net` 节点以提高可用性。
*   **链接提示失效：**
    *   密码重置链接有效期为1小时，过期后需重新申请。
    *   链接为一次性有效，使用后即失效。

## 版本历史

*   **v0.0.4**
    *   **新增：** 集成 Geetest v4 (极验) 行为验证码，提供更多样化的安全选择。
    *   **优化：** 重构插件后台配置界面，实现验证码设置项的动态分组显示，界面更整洁直观。
*   **v0.0.3**
    *   **新增：** 添加 HMAC 签名验证机制，通过随机的 HMAC 密钥进行签名加密，防止链接篡改。
    *   **优化：** 令牌（Token）处理机制，在每次请求时清理过期令牌。
*   **v0.0.2**
    *   **新增：** 支持 hCaptcha，并可在多种验证码服务间自由切换。
    *   **优化：** 增强令牌生成与验证逻辑，提升安全性。
*   **v0.0.1**
    *   **新增：** 支持自定义邮件模版及 reCAPTCHA v2。
    *   **更新：** 升级 PHPMailer 核心库至最新版本。

## 许可证

本插件基于 [GNU General Public License 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html) 授权。
