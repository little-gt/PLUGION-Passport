# Passport 密码找回插件 for Typecho

Passport 是为 Typecho 博客系统设计的专业密码找回与重置插件，集成多种人机验证机制、防暴力破解和 IP 速率限制功能。支持 MySQL、SQLite 和 PostgreSQL 数据库，提供完整的密码重置历史审计和请求日志管理。本项目遵循 GPLv2 协议，完全免费开源。

[![Passport Version](https://img.shields.io/badge/Passport-v1.1.3-007EC6?style=for-the-badge&logo=securityscorecard&logoColor=white)](https://github.com/little-gt/PLUGION-Passport)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-007EC6?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2.0-green?style=for-the-badge)](https://opensource.org/licenses/GPL-2.0)

## 📷 预览图

### 后台管理界面
![后台管理界面](https://cnb.cool/little-gt/Passport/-/git/raw/main/screenshots/screenshot1.png)

### 密码找回页面
![密码找回页面](https://cnb.cool/little-gt/Passport/-/git/raw/main/screenshots/screenshot2.png)

## 📦 项目简介

**Passport** 是为 Typecho 博客系统开发的专业密码找回插件，提供安全可靠的密码重置功能。支持 MySQL、SQLite 和 PostgreSQL 数据库，集成多种人机验证机制和完善的防暴力破解体系，提供完整的审计日志和管理员后台管理功能。

### 🚀 核心特性

| 特性 | 说明 |
|:---|:---|
| 🔐 **安全可靠** | HMAC 签名验证，Token 1小时过期，密码复杂度强制校验 |
| 🎯 **多种验证码** | 内置图片验证码、Google reCAPTCHA v2、hCaptcha、Geetest v4 |
| 🛡️ **防暴力破解** | IP 速率限制（20次尝试触发5分钟封禁），支持 CDN 环境 |
| 📊 **审计日志** | 完整的密码重置历史和请求日志记录，支持搜索和分页 |
| ✉️ **邮件模板** | 可自定义邮件模板，支持变量替换，暗黑风格默认模板 |
| 🎨 **主题适配** | 完美适配 BooAdmin 主题，支持亮色/暗色模式 |
| 📱 **响应式设计** | 移动端、桌面端、4K 屏幕完美适配 |
| 🔔 **独立通知** | Session 通知系统 + PRG 模式，防表单重复提交 |
| 🌐 **多数据库** | 原生支持 MySQL、SQLite、PostgreSQL |
| 🔧 **管理功能** | 后台 IP 解封、批量操作、日志筛选、Token 清理策略 |

## 📖 快速开始

### 安装方法

1. **下载插件**：从 GitHub 下载最新版本
2. **上传文件**：将插件目录 `passport` 解压到 Typecho 插件目录 `usr/plugins/`
3. **激活插件**：在 Typecho 后台插件管理中激活 Passport
4. **配置插件**：填写 SMTP 信息和其他配置项
5. **添加链接**：在 Typecho 后台 login.php 添加如下代码
```php
// 找到这里
<?php if($options->allowRegister): ?>
&bull;
<a href="<?php $options->registerUrl(); ?>"><?php _e('用户注册'); ?></a>
<?php endif; ?>

// 在它下面插入以下代码
<?php
   $activates = array_keys(Typecho_Plugin::export()['activated']);
   if (in_array('Passport', $activates)) {
       echo '<a href="' . Typecho_Common::url('passport/forgot', $options->index) . '">' . '忘记密码' . '</a>';
   }
?>
```

### 基本配置

| 配置项 | 说明 | 默认值 |
|-------|------|-------|
| SMTP 服务器 | 邮件发送服务器地址 | smtp.example.com |
| SMTP 端口 | 邮件发送服务器端口 | 465 |
| SMTP 帐号 | 邮件发送账号 | noreply@example.com |
| SMTP 密码 | 邮件发送密码（推荐使用客户端授权码） | - |
| 加密类型 | SMTP 加密方式 | SSL |
| 验证码类型 | 人机验证方式 | 内置图片验证码 |
| HMAC 签名密钥 | 用于签名验证的密钥 | 自动生成（32字节随机） |
| 启用请求速率限制 | 防止暴力破解 | 启用 |
| IP 获取策略 | 支持默认、代理头、自定义 Header | 默认 (REMOTE_ADDR) |
| 自定义 IP 请求头 | CDN 用户可配置（如 HTTP_CF_CONNECTING_IP） | HTTP_CF_CONNECTING_IP |
| Token 保留天数 | 已使用 Token 的保留天数 | 30 天 |
| 禁用时删除数据 | 禁用插件时是否删除所有数据 | 否 |

## 🛠 技术架构

### 系统流程图

```mermaid
sequenceDiagram
    participant User as 用户
    participant Frontend as 前端页面
    participant Backend as 后端逻辑
    participant DB as 数据库
    participant SMTP as 邮件服务器

    User->>Frontend: 访问忘记密码页面
    Frontend->>User: 显示邮箱表单+验证码
    User->>Frontend: 提交邮箱和验证码
    Frontend->>Backend: /passport/forgot POST
    
    Backend->>Backend: IP速率限制检查
    Backend->>Backend: 验证码验证(Verify-and-Destroy)
    Backend->>DB: 查询用户(防枚举)
    DB-->>Backend: 返回用户数据
    
    alt 用户存在
        Backend->>Backend: 生成时间戳Token+HMAC签名
        Backend->>DB: 存储Token(1小时过期)
        Backend->>SMTP: 发送重置邮件
        SMTP-->>User: 接收重置邮件
    end
    
    Backend-->>Frontend: 统一响应(PRG模式)
    
    User->>Frontend: 点击邮件重置链接
    Frontend->>Backend: /passport/reset?token=xxx&sig=xxx
    
    Backend->>DB: 查询Token并验证HMAC
    DB-->>Backend: Token有效
    
    Backend-->>Frontend: 显示密码重置表单
    User->>Frontend: 提交新密码+验证码
    Frontend->>Backend: /passport/reset POST
    
    Backend->>Backend: 密码复杂度校验
    Backend->>DB: 更新密码+标记Token已使用
    Backend-->>User: 密码重置成功
```

### 组件架构

```mermaid
flowchart TD
    A[用户浏览器] -->|HTTP请求| B[Typecho路由层]
    B -->|/passport/forgot| C[Passport_Widget]
    B -->|/passport/reset| C
    B -->|/passport/captcha| C
    B -->|/action/passport-unblock| C
    
    C --> D[验证码服务]
    C --> E[速率限制服务]
    C --> F[邮件发送服务]
    C --> G[Token管理服务]
    
    D --> H[内置图片验证码]
    D --> I[reCAPTCHA]
    D --> J[hCaptcha]
    D --> K[Geetest]
    
    F --> L[PHPMailer]
    L --> M[SMTP服务器]
    
    G --> N[(password_reset_tokens表)]
    E --> O[(passport_fails表)]
    
    C --> P[(users表)]
```

### 目录结构

```
Passport/
├── Plugin.php           # 插件主文件，负责路由注册和配置
├── Widget.php           # 核心逻辑，处理密码重置流程
├── Template/            # 前端模板
│   ├── forgot.php       # 忘记密码页面
│   ├── reset.php        # 重置密码页面
│   └── partial/         # 公共模板
│       ├── common.php   # 公共变量和初始化
│       ├── header.php   # HTML 头部
│       └── resource.php # 静态资源（CSS/JS）
└── PHPMailer/           # 邮件发送库
```

### 核心技术实现

#### 1. 通知系统架构

Passport v1.0.2 采用独立的 Session 通知系统，完全脱离 Typecho 后台依赖：

**后端实现** (`Widget.php`)：
```php
// 存储通知到 Session
protected function pushNotice($message, $type = 'success', $countdown = null) {
    $_SESSION['passport_notice'] = [
        'message' => $message,
        'type' => $type,
        'countdown' => $countdown
    ];
}

// POST-Redirect-GET 模式防止表单重复提交
$this->pushNotice('操作成功', 'success');
$this->response->redirect($redirectUrl);
```

**前端渲染** (`resource.php`)：
- 使用 JSON 编码安全传递数据（`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`）
- 采用 Passkey 风格设计：半透明背景 + 主题色文本
- 从上往下滑入动画（`translateY(-10px) → translateY(0)`）
- 智能倒计时：倒计时期间不自动关闭，结束后延迟 1 秒自动关闭

**CSS 特性**：
```css
.passport-toast.success {
    background-color: rgba(16, 185, 129, 0.1);  /* 半透明绿色背景 */
    border-color: #10b981;
    color: #10b981;  /* 主题色文本 */
}

@keyframes passkeySlideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
```

#### 2. 验证码加载机制

**智能加载指示器** (v1.0.2 新增)：

**HTML 结构**：
```html
<div class="passport-captcha-wrapper">
    <!-- 圆形加载指示器 -->
    <div class="passport-captcha-loader active"></div>
    <!-- 验证码图片（初始隐藏） -->
    <img class="passport-captcha-img loading" onclick="refreshCaptcha(this);">
</div>
```

**加载流程**：
1. 页面首次加载：显示空白占位 + 旋转加载器
2. DOMContentLoaded 触发：自动调用 `refreshCaptcha()`
3. 图片开始加载：加载器保持显示，图片透明度为 0
4. 图片加载完成：移除 `.loading` 类，图片淡入显示，隐藏加载器
5. 点击刷新：重复步骤 2-4

**JavaScript 实现**：
```javascript
function refreshCaptcha(imgElement) {
    const wrapper = imgElement.parentElement;
    const loader = wrapper.querySelector('.passport-captcha-loader');
    
    // 显示加载器，隐藏图片
    imgElement.classList.add('refreshing', 'loading');
    loader.classList.add('active');
    
    // 动态设置图片源（支持首次加载）
    const baseUrl = imgElement.src || '/passport/captcha';
    imgElement.src = baseUrl + '?' + Math.random();
    
    // 加载完成：隐藏加载器，显示图片
    imgElement.onload = () => {
        imgElement.classList.remove('refreshing', 'loading');
        loader.classList.remove('active');
    };
}
```

**CSS 动画**：
```css
/* 圆形加载器 */
.passport-captcha-loader {
    width: 24px;
    height: 24px;
    border: 3px solid var(--passport-border);
    border-top-color: var(--passport-primary);
    border-radius: 50%;
    animation: passkeyRotate 0.8s linear infinite;
}

/* 图片加载状态 */
.passport-captcha-img.loading {
    opacity: 0;  /* 加载时透明 */
    pointer-events: none;  /* 禁用点击 */
}
```

#### 3. 前端技术栈

```mermaid
flowchart TD
    A[前端技术栈] --> B[CSS变量系统]
    A --> C[原生JavaScript]
    A --> D[SVG图标]
    A --> E[响应式布局]
    A --> F[CSS3动画]
    
    B --> B1[亮色/暗色自动切换]
    C --> C1[无外部依赖]
    D --> D1[currentColor继承]
    E --> E1[移动端/桌面/4K]
    F --> F1[流畅过渡动画]
```

Passport 前端采用现代技术栈：CSS 变量系统实现主题切换，原生 JavaScript 确保轻量高效，SVG 图标继承主题色，响应式布局适配多端，CSS3 动画提供流畅体验。

## 🔌 API 接口

### 1. 验证码接口

#### 请求信息
- **URL**: `/passport/captcha`
- **方法**: GET
- **参数**: 无
- **Headers**: 
  - `Cookie`: 必须携带 Session Cookie

#### 响应
- **类型**: `image/png`
- **大小**: 约 2-5 KB
- **尺寸**: 120x48 px
- **有效期**: Session 生命周期（默认 24 分钟）

#### 技术细节
- 使用 PHP GD 库动态生成
- 随机 4-6 位字符（数字 + 字母）
- 包含干扰线和噪点，防止 OCR
- 验证码存储在 `$_SESSION['captcha']`
- 验证后立即销毁（Verify-and-Destroy）

#### 示例请求
```bash
curl -X GET 'https://example.com/passport/captcha' \
  -H 'Cookie: PHPSESSID=abc123...' \
  --output captcha.png
```

#### 加载优化（v1.0.2）
- 首次访问自动加载（DOMContentLoaded 触发）
- 加载过程显示圆形旋转指示器
- 点击图片刷新，支持防重复点击
- 刷新时不旋转图片，仅显示加载指示器

### 2. 忘记密码接口

#### 请求信息
- **URL**: `/passport/forgot`
- **方法**: GET（显示表单） / POST（提交表单）
- **Content-Type**: `application/x-www-form-urlencoded`

#### POST 参数
| 字段名 | 类型 | 必填 | 验证规则 | 描述 |
|-------|------|------|----------|------|
| mail | string | 是 | Email 格式 | 用户注册邮箷 |
| captcha | string | 是 | 4-6 位字符 | 验证码（不区分大小写） |
| do | string | 是 | 固定值 `mail` | 操作类型标识 |

#### 响应类型
**成功情况**：
- **HTTP 状态**: 302 Found
- **Location**: `/passport/forgot`（重定向回表单页面）
- **Session**: 存储成功通知数据
- **通知消息**: “密码重置邮件已发送，请查收邮箱。”

**失败情况**：
- **HTTP 状态**: 302 Found
- **Session**: 存储错误通知数据
- **通知类型**: `error`
- **常见错误**：
  - “邮箱地址不存在”
  - “验证码错误”
  - “请求过于频繁，请 X 分钟后再试”（包含倒计时）

#### 技术流程
```
1. 验证请求频率（IP 速率限制，20次触发5分钟封禁）
2. 验证验证码（Session 匹配，验证后立即销毁）
3. 验证邮箱格式（filter_var）
4. 查询用户是否存在（防范用户枚举）
5. 生成时间戳基 Token（SHA256 + UID + 时间戳 + 随机数）
6. 生成 HMAC 签名（包含 token、uid、createdAt）
7. 存储 Token 到数据库（1小时过期）
8. 发送重置邮件（PHPMailer，支持自定义模板）
9. 存储通知到 Session
10. 重定向回表单页面（PRG 模式）
```

#### 示例请求
```bash
curl -X POST 'https://example.com/passport/forgot' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'Cookie: PHPSESSID=abc123...' \
  -d 'mail=user@example.com' \
  -d 'captcha=A3B9' \
  -d 'do=mail'
```

#### 安全机制
- 验证码验证后立即销毁
- IP 速率限制（5分钟3次）
- 邮箱枚举防护（不显示具体错误）
- Token 一次性使用
- HMAC 签名防篡改

### 3. 重置密码接口

#### 请求信息
- **URL**: `/passport/reset`
- **方法**: GET（验证链接并显示表单） / POST（提交新密码）
- **Content-Type**: `application/x-www-form-urlencoded`

#### GET 参数（邮件链接）
| 字段名 | 类型 | 必填 | 描述 |
|-------|------|------|------|
| token | string | 是 | 64 位重置令牌（SHA256） |
| signature | string | 是 | 64 位 HMAC 签名（SHA256） |

#### POST 参数
| 字段名 | 类型 | 必填 | 验证规则 | 描述 |
|-------|------|------|----------|------|
| token | string | 是 | 64 位十六进制 | 重置令牌 |
| signature | string | 是 | 64 位十六进制 | HMAC 签名 |
| password | string | 是 | 6-32 位 | 新密码 |
| confirm | string | 是 | 与 password 一致 | 确认密码 |
| captcha | string | 是 | 4-6 位字符 | 验证码 |
| do | string | 是 | 固定值 `password` | 操作类型标识 |

#### 响应类型
**成功情况**：
- **HTTP 状态**: 302 Found
- **Location**: 登录页面（Typecho 默认）
- **通知消息**: “密码重置成功，请使用新密码登录。”

**失败情况**：
- **HTTP 状态**: 302 Found
- **Location**: `/passport/reset?token=...&signature=...`
- **通知类型**: `error`
- **常见错误**：
  - “无效的重置链接”（Token 不存在）
  - “链接已失效”（Token 已过期或已使用）
  - “签名验证失败”（HMAC 不匹配）
  - “两次密码输入不一致”
  - “验证码错误”

#### 技术流程
```
GET 请求：
1. 验证 Token 和 Signature 参数存在
2. 验证 HMAC 签名（hash_equals 防止时序攻击）
3. 查询 Token 是否存在且未使用
4. 检查 Token 是否过期（1小时）
5. 显示密码重置表单

POST 请求：
1. 重复 GET 请求的验证步骤
2. 验证验证码（验证后立即销毁）
3. 验证新密码复杂度（8位以上，包含大小写字母、数字、特殊字符）
4. 验证两次密码一致性
5. 更新数据库中的用户密码（PasswordHash 哈希）
6. 标记 Token 为已使用（保留记录供审计）
7. 存储成功通知
8. 重定向到登录页面
```

#### 示例请求
**GET 请求（邮件链接）**：
```
https://example.com/passport/reset?
  token=a1b2c3d4e5f6...&
  signature=1a2b3c4d5e6f...
```

**POST 请求**：
```bash
curl -X POST 'https://example.com/passport/reset' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'Cookie: PHPSESSID=abc123...' \
  -d 'token=a1b2c3d4e5f6...' \
  -d 'signature=1a2b3c4d5e6f...' \
  -d 'password=NewPass123' \
  -d 'confirm=NewPass123' \
  -d 'captcha=X7Y9' \
  -d 'do=password'
```

#### 安全机制
- HMAC 签名验证（防篡改）
- Token 一次性使用（防重放）
- Token 24 小时过期
- 验证码二次验证
- 密码长度限制（6-32 位）
- 两次密码一致性检查

### 4. IP 解封接口

#### 请求信息
- **URL**: `/action/passport-unblock`
- **方法**: POST
- **参数**:
  | 字段名 | 类型 | 必填 | 描述 |
  |-------|------|------|------|
  | unblock_ip | string | 是 | 要解封的 IP 地址 |
  | _ | string | 是 | CSRF 令牌 |

#### 响应
- **类型**: JSON
- **描述**: 返回操作结果

#### 响应示例
```json
{
  "success": true,
  "message": "IP [192.168.1.1] 已解封。"
}
```

## 🎨 主题适配

### BooAdmin 主题适配

Passport 插件完美适配 [BooAdmin](https://github.com/little-gt/THEME-BooAdmin) 主题，提供一致的视觉体验。

### Passkey 插件适配

v1.0.2 版本通知系统采用与 [Passkey](https://github.com/little-gt/PLUGION-Passkey) 插件完全一致的视觉设计：
- 半透明主题色背景（`rgba(16, 185, 129, 0.1)` 用于成功通知）
- 从上往下滑入动画（`translateY(-10px) → translateY(0)`）
- 扁平化设计，无圆角无阴影
- 图标、文本、关闭按钮统一使用主题色

### 自定义主题适配

如果您使用的是自定义主题，可以通过以下方式确保 Passport 页面与主题风格一致：

1. **CSS 变量**：Passport 使用 CSS 变量定义颜色和样式，您可以在主题中覆盖这些变量
   ```css
   :root {
       --passport-primary: #your-color;
       --passport-success: #your-success-color;
       --passport-error: #your-error-color;
   }
   ```

2. **模板修改**：如需深度定制，可以修改 `Template/partial/resource.php` 中的样式
   - 通知系统样式从第 299 行开始
   - 验证码样式从第 202 行开始
   - CSS 变量定义从第 15 行开始

3. **通知系统**：Passport 使用 Session 通知系统，完全独立于主题
   - 前端使用 `PassportToast.show()` 方法显示通知
   - 支持四种类型：`success`、`error`、`warning`、`info`
   - 自动处理倒计时和自动关闭逻辑

4. **响应式断点**：
   - 移动端：`@media (max-width: 480px)`
   - 平板端：默认样式（481px - 1919px）
   - 高清屏：`@media (min-width: 1920px)`
   - 4K 屏幕：`@media (min-width: 2560px)`

## 🔒 安全特性

```mermaid
flowchart LR
    subgraph L1[网络层]
        A1[IP速率限制]
        A2[CDN/代理适配]
        A3[智能封禁]
    end
    
    subgraph L2[应用层]
        B1[人机验证]
        B2[用户枚举防护]
        B3[XSS防护]
        B4[CSRF防护]
        B5[PRG模式]
    end
    
    subgraph L3[数据层]
        C1[HMAC签名]
        C2[Token管理]
        C3[密码复杂度校验]
        C4[参数化查询]
    end
    
    subgraph L4[审计层]
        D1[重置历史记录]
        D2[请求日志]
        D3[Token清理]
    end
    
    L1 --> L2 --> L3 --> L4
```

Passport 采用四层纵深防御架构：**网络层**通过 IP 速率限制和智能封禁抵御暴力破解；**应用层**通过人机验证、枚举防护和输入过滤防止攻击；**数据层**通过 HMAC 签名、Token 管理和密码校验确保数据安全；**审计层**通过完整日志记录和自动清理实现可追溯性。

**核心安全机制**：HMAC-SHA256 签名（包含 token、uid、时间戳）、Token 1 小时过期且一次性使用、Verify-and-Destroy 验证码机制、密码复杂度强制校验（8 位+大小写+数字+特殊字符）、用户枚举防护（统一响应）、参数化查询防 SQL 注入。

## 📊 日志系统

```mermaid
flowchart TD
    A[日志系统] --> B[密码重置历史]
    A --> C[请求日志]
    
    B --> B1[用户ID/邮箱]
    B --> B2[令牌创建时间]
    B --> B3[令牌状态]
    B --> B4[操作结果]
    
    C --> C1[IP地址]
    C --> C2[尝试次数]
    C --> C3[最后尝试时间]
    C --> C4[封禁状态]
    
    A --> D[后台管理]
    D --> D1[搜索筛选]
    D --> D2[分页浏览]
    D --> D3[IP解封]
    D --> D4[批量操作]
```

Passport 提供完整的审计日志，包括密码重置历史（记录用户、令牌状态和操作结果）和请求日志（记录 IP、尝试次数和封禁状态），支持搜索、筛选和批量操作。

## 📝 邮件模板

Passport 支持自定义邮件模板，您可以在后台配置中修改邮件内容。

### 默认模板变量

| 变量 | 描述 |
|------|------|
| {username} | 用户名称 |
| {sitename} | 网站名称 |
| {requestTime} | 请求时间 |
| {resetLink} | 重置链接 |

## 🚩 版本历史

| 版本 | 日期 | 主要变更 |
|------|------|----------|
| 1.1.3 | 2026-07-01 | 适配 MySQL、SQLite 和 PostgreSQL 数据库；升级通知系统样式，与 BooAdmin 主题保持一致 |
| 1.1.2 | 2026-05-26 | 适配 BooAdmin 主题，提升整体体验 |
| 1.1.1 | 2026-05-26 | 更新 PHPMailer 到 7.1.1 版本 |
| 1.1.0 | 2026-05-26 | 质量文档更新 |
| 1.0.4 | 2026-05-17 | 修复解封 IP 时提示 404 的问题 |
| 1.0.3 | 2026-03-26 | 适配 BooAdmin 暗色模式 |
| 1.0.2 | 2026-03-03 | 优化通知视觉设计，改进验证码加载体验 |
| 1.0.1 | 2026-03-01 | 适配 BooAdmin 主题，优化通知系统，提升 SVG 图标质量 |
| 0.1.5 | 2025-12-01 | 增强 Token 安全性，添加密码重置历史审计功能 |
| 0.1.4 | 2025-10-15 | 新增 IP 拦截管理功能 |
| 0.1.3 | 2025-09-01 | 优化 IP 获取策略，增强验证码安全性 |
| 0.1.2-fix | 2025-08-10 | 修复安全漏洞，增强密码学安全性 |

## 🤝 贡献指南

我们欢迎社区贡献，包括：

- 代码优化和 bug 修复
- 新功能开发
- 文档完善
- 安全审计

请提交 Pull Request 或创建 Issue 来参与项目开发。

## 📄 许可证

Passport 插件采用 GNU General Public License v2.0 许可证。

- **作者**: GARFIELDTOM
- **网站**: https://garfieldtom.cool/
- **GitHub**: https://github.com/little-gt/PLUGION-Passport

---

**感谢使用 Passport 插件！** 如有任何问题或建议，请随时联系我们。