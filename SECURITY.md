# 🔐 Security Policy

## Supported Versions

| Version   | Supported |
|-----------|------------|
| 0.1.1-pre | ✅ Yes |
| 0.0.4     | ✅ Yes |
| < 0.0.4   | ❌ No |

---

## 📢 Reporting a Vulnerability

如果您在使用中发现任何安全漏洞，请 **不要公开发布**，而是通过以下方式报告：

- 📧 邮件至作者：`coolerxde@gt.ac.cn`
- 或在 GitHub 提交 “Security Advisory”

我们承诺：
- 72 小时内回复确认漏洞；
- 7 日内提供修复计划；
- 重大漏洞将在修复后公开披露。

---

## 🛡️ 安全设计概览

### 防御措施
- **请求速率限制**：基于 IP 的尝试计数与封禁。
- **HMAC 签名验证**：防止重置令牌被伪造。
- **CAPTCHA 验证**：防止机器人滥用。
- **强密码策略**：密码复杂度检查机制。
- **隐私防护**：防止邮箱枚举攻击。

### 数据安全
- 所有数据库表前缀均由 Typecho 自定义前缀决定；
- 插件不会记录密码，仅存储加密散列；
- 邮件模板与配置可安全导出；
- 插件卸载可选择删除所有数据。

---

## ⚠️ Responsible Disclosure

公开漏洞前，请确保：
- 已通过官方渠道报告；
- 已等待作者确认与修复；
- 未造成生产系统损害。

感谢每一位报告安全问题的用户 ❤️

---

**License:** GPLv2  
**Author:** GARFIELDTOM  
**Homepage:** [https://garfieldtom.cool/](https://garfieldtom.cool/)
