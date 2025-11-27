# 🔐 Security Policy

感谢您关注 **Passport** 的安全性！为了保护用户与开发环境，我们制定了以下安全策略。我们非常欢迎来自安全研究人员与开发者的负责任披露。

---

## 📌 Supported Versions

我强烈建议所有用户使用最新版本，对于 0.1.1-pre 之后的版本都是可以直接覆盖更新的。目前插件已经稳定并且没有重大更新的计划，您可以放心地下载最新且最稳定的 0.1.2-fix 版本使用。

| Version | Status |
| :------ | :----- |
| **0.1.2-fix** | ✅ Latest     |
| 0.1.2         | 🟡 Inaccurate |
| 0.1.1-pre     | 🟡 Inaccurate |
| < 0.1.1-pre   | ❌ Out of Scope |

---

## 📮 Reporting a Vulnerability

我们欢迎社区对本项目进行安全审计。如果您发现了任何安全漏洞，请遵循负责任的披露原则，**不要在公开场合（如 GitHub Issues）发布**。

### 📧 Security Contact

[coolerxde@gt.ac.cn](mailto:coolerxde@gt.ac.cn)

请在邮件标题中注明：
`[Security] PluginPassport - <brief summary>`

### 🔐 Need encryption?

如果您倾向于加密沟通，请在邮件中说明，维护者将提供 **PGP 公钥** 以便您安全发送详细内容。

---

## 🧾 Information to Include

为加快问题定位、复现与修复，建议在您的报告中尽可能包含以下信息：

* 影响版本（如：`v1.2.4` 或 commit hash）
* 环境信息：Typecho / PHP / MySQL / 浏览器 / OS 等
* 漏洞类型（XSS / SQL Injection / Privilege Escalation / CSRF / Info Leak 等）
* 清晰的复现步骤（step-by-step）
* PoC 或 Minimal Repro（可选但强烈推荐）
* HTTP 请求/响应（如适用，可脱敏处理）
* 影响级别评估（高 / 中 / 低）
* 修复建议或 patch（如果已有）

我们非常感谢任何形式的协助，但请勿提供超出您愿意分享的敏感内容。

---

## 📢 Disclosure Policy

我们遵循 **Responsible Disclosure**：

* 请勿在未修复前公开漏洞细节或 PoC
* 维护者将尽快确认收到您的报告
* 修复后将发布安全公告或补丁版本
* 如您希望在公告中署名致谢，请在邮件中告知

---

## 📦 Scope

本安全策略适用于：

* Passport 原始代码（PHP 模版和核心功能文件 / JS / CSS）

不适用以下情况：

* 非主题相关的服务端环境问题
* 外部 CDN 资源

---

## 🛠 Temporary Mitigation

如漏洞无法立即修复，管理员可采用以下短期缓解措施：

* 临时禁用本插件直到修复
* 对输出内容进行严格转义
* 暂停加载不信任的外部资源

---

## 💬 Maintainer Commitment

我们承诺：

* 及时、友善地回应安全报告
* 优先处理高风险漏洞
* 保护研究人员的隐私与合法权益
* 在修复后公开透明地发布安全说明

需要更安全的沟通方式（如 PGP 全链路加密或其他渠道）？请在邮件中说明您的偏好，我们将尽可能配合。

---

## ⚖️ Legal & Ethics

我们鼓励善意的安全研究，但请遵从：

* 不进行未授权访问
* 不滥用漏洞
* 不破坏数据或侵犯隐私
* 遵守相关法律法规

我们欢迎所有善意的安全贡献者，共同让 Passport 插件 更加安全。

---

## 🚨 v0.1.2-fix 规范化修复说明

因为注意到了 fork 版本当中提出的潜在的不规范问题，我对 0.1.2 正式版本进行了加固。建议使用 0.1.2-fix 版本。当然使用其他收到支持的版本也是可以的，这些问题都是 AI 根据最新的严格规范得出的，即使不修复也不存在严重的安全风险，只是不推荐而已，因为利用的成本还是很高的。

| 编号 | 级别 | 问题简述 | 修复方案 |
|------|---------|----------|----------|
| P0-1 | 🔴 不规范 | 令牌生成使用弱伪随机数 (`mt_rand`) | 升级为 **`random_bytes`** (密码学安全) 生成令牌。|
| P0-2 | 🔴 不规范 | Geetest 验证请求使用 **HTTP** 协议 | 强制改为 **HTTPS** 协议，并统一使用 Typecho `HttpClient`。|
| P0-3 | 🔴 不规范 | 邮件模板变量缺乏过滤，导致**存储型 XSS** | 对所有邮件模板占位符内容进行 **`htmlspecialchars()`** 转义。|
| P1-4 | 🟡 不建议 | HMAC 密钥生成回退逻辑不安全 | 移除不安全的 `mt_rand` 回退，确保只有安全的随机源被用于 HMAC 密钥。|
| P1-5 | 🟡 不建议 | 后台 IP 解封操作缺乏 **CSRF 保护** | 在后台解封表单中添加了 **Typecho CSRF Token** 验证。|

### 详细代码实现中的修复点：

#### 1. 弱随机数修复 (P0-1 & P1-4)

在 `Passport_Plugin::generateStrongRandomKey` (用于 HMAC 密钥) 和 `Passport_Widget::doForgot` (用于重置令牌) 中，移除了依赖 `mt_rand` 的弱随机数生成逻辑，确保了密码学安全。

#### 2. 安全通讯协议修复 (P0-2 & HTTP 客户端)

在 `Passport_Widget` 类中：
- 统一使用 `\Typecho\Http\Client` 替代不安全的 `@file_get_contents`。
- 确保 Geetest 的 URL 强制使用 **`https://`**。

#### 3. 邮件模板 XSS 修复 (P0-3)

在 `Passport_Widget::sendResetEmail` 方法中，对所有传入 `str_replace` 的 `$emailBody` 变量值使用了 `htmlspecialchars()` 进行转义：

```php
$emailBody = str_replace(
    ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
    [
        htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'),
        // ... (其他变量同样被转义)
        htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
    ],
    // ...
);
```

#### 4. CSRF 保护修复 (P1-5)

在 `Passport_Widget::handleUnblockIp` 方法中，加入了对 Typecho CSRF token 的验证。

---

感谢你可以看到这里，因为有你，Passport 才能有今天的成就。