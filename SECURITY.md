# 🛡️ 安全政策

**项目版本**: v0.1.2-fix

**发布日期**: 2025-11-07

**项目地址**: https://github.com/little-gt/PLUGION-Passport/

---

# 💬 安全报告

我们欢迎社区对本项目进行安全审计。如果您发现了任何安全漏洞，请遵循负责任的披露原则，**不要在公开场合（如 GitHub Issues）发布**。

📧 **邮件报告：** 发送详细信息至 `coolerxde@gt.ac.cn`

---

# 📦 支持版本

我强烈建议所有用户使用最新版本，对于0.1.1之后的版本都是可以直接覆盖更新的，并且在完成了稳定性测试和实现规范化之后，更新频率会显著降低，也不用担心一直频繁更新，毕竟大版本更新还需要完善一些小细节嘛。

| Version | Status |
| :------ | :----- |
| **0.1.2-fix** | ✅ Latest     |
| 0.1.2         | 🟡 Inaccurate |
| 0.1.1-pre     | 🟡 Inaccurate |
| < 0.1.1-pre   | ❌ Out of Scope |

---

# 🚨 v0.1.2-fix 规范化修复说明

因为注意到了 fork 版本当中提出的潜在的不规范问题，我对 0.1.2 正式版本进行了加固。建议使用 0.1.2-fix 版本。当然使用其他收到支持的版本也是可以的，这些问题都是 AI 根据最新的严格规范得出的，即使不修复也不存在严重的安全风险，只是不推荐而已，因为利用的成本还是很高的。

| 编号 | 级别 | 问题简述 | 修复方案 |
|------|---------|----------|----------|
| P0-1 | 🔴 不规范 | 令牌生成使用弱伪随机数 (`mt_rand`) | 升级为 **`random_bytes`** (密码学安全) 生成令牌。|
| P0-2 | 🔴 不规范 | Geetest 验证请求使用 **HTTP** 协议 | 强制改为 **HTTPS** 协议，并统一使用 Typecho `HttpClient`。|
| P0-3 | 🔴 不规范 | 邮件模板变量缺乏过滤，导致**存储型 XSS** | 对所有邮件模板占位符内容进行 **`htmlspecialchars()`** 转义。|
| P1-4 | 🟡 不建议 | HMAC 密钥生成回退逻辑不安全 | 移除不安全的 `mt_rand` 回退，确保只有安全的随机源被用于 HMAC 密钥。|
| P1-5 | 🟡 不建议 | 后台 IP 解封操作缺乏 **CSRF 保护** | 在后台解封表单中添加了 **Typecho CSRF Token** 验证。|

## 详细代码实现中的修复点：

### 1. 弱随机数修复 (P0-1 & P1-4)

在 `Passport_Plugin::generateStrongRandomKey` (用于 HMAC 密钥) 和 `Passport_Widget::doForgot` (用于重置令牌) 中，移除了依赖 `mt_rand` 的弱随机数生成逻辑，确保了密码学安全。

### 2. 安全通讯协议修复 (P0-2 & HTTP 客户端)

在 `Passport_Widget` 类中：
- 统一使用 `\Typecho\Http\Client` 替代不安全的 `@file_get_contents`。
- 确保 Geetest 的 URL 强制使用 **`https://`**。

### 3. 邮件模板 XSS 修复 (P0-3)

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

### 4. CSRF 保护修复 (P1-5)

在 `Passport_Widget::handleUnblockIp` 方法中，加入了对 Typecho CSRF token 的验证。

---

感谢你可以看到这里。