<?php
/**
 * Typecho 核心初始化脚本片段
 *
 * 此脚本用于在 Typecho 环境中，尤其是后台或插件独立页面中，定义核心常量、
 * 初始化必要的 Typecho Widget 组件并获取配置，为后续页面逻辑做准备。
 */

// 严格安全检查: 防止文件被直接访问。Typecho 插件/模板的最佳实践。
// 注意: 此片段通常在 Typecho 的 index.php 或其他入口文件中被 include/require。
// 核心文件中通常不加此检查，但作为模块化代码，保留注释说明其作用。

// --- 核心常量定义 ---

// 兼容旧版本 PHP 或环境: 检查魔术常量 __DIR__ 是否已定义，如果未定义，则通过 dirname(__FILE__) 定义。
// __DIR__ 在 PHP 5.3.0 及以上版本中可用。
if (!defined('__DIR__')) {
    define('__DIR__', dirname(__FILE__));
}

// 确保核心常量已定义，表明当前环境是 Typecho 后台环境或需要后台组件支持的环境。
if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}

// 定义后台目录的完整路径。
// 依赖于 __TYPECHO_ROOT_DIR__ (Typecho 根目录) 和 __TYPECHO_ADMIN_DIR__ (后台目录名，通常是 /admin)。
if (!defined('__ADMIN_DIR__')) {
    // 最佳实践：使用 PHP 8 的字符串拼接方式。
    // 注意: Typecho 的约定是 __TYPECHO_ADMIN_DIR__ 包含斜杠（如 '/admin/'）。
    define('__ADMIN_DIR__', __TYPECHO_ROOT_DIR__ . __TYPECHO_ADMIN_DIR__);
}

// --- Typecho Widget 初始化 ---

/**
 * 初始化所有 Typecho 核心组件，包括数据库连接、路由、配置加载等。
 * Typecho_Widget::widget('Widget_Init') 是 Typecho 启动的起点。
 */
Typecho_Widget::widget('Widget_Init');

/**
 * 初始化并获取 Typecho 配置选项 Widget。
 * $options 变量保存了站点的所有配置。
 */
Typecho_Widget::widget('Widget_Options')->to($options);

/**
 * 初始化并获取 Typecho 安全 Widget。
 * $security 变量用于处理 CSRF Token、密码哈希等安全相关操作。
 */
Typecho_Widget::widget('Widget_Security')->to($security);

/**
 * 初始化并获取 Typecho 后台菜单 Widget。
 * $menu 变量用于渲染后台导航菜单。
 */
Typecho_Widget::widget('Widget_Menu')->to($menu);

// --- 版本号解析与兼容性处理 ---

/**
 * Typecho 版本号解析逻辑：
 * Typecho 版本号格式通常是 '主版本号/静态资源版本号' (e.g., '1.2.1/2022.10.20')
 * 此逻辑用于兼容新旧版本号格式，将版本号拆分为逻辑版本 ($prefixVersion) 和静态资源版本 ($suffixVersion)。
 */

// 检查 $options->version 属性是否存在且非空 (PHP 8 兼容性/健壮性检查)。
if (isset($options->version) && strpos($options->version, '/') !== false) {
    // 存在斜杠：按斜杠分割版本号。limit 参数 2 确保只进行一次分割。
    list($prefixVersion, $suffixVersion) = explode('/', $options->version, 2);
} else {
    // 不存在斜杠 (旧版本格式，如 '1.2.1') 或 $options->version 不存在/为空：
    // 将整个版本号（或空字符串）赋给 $prefixVersion，并设置 $suffixVersion 为空。
    // PHP 8 兼容性：确保 $options->version 存在，否则使用空字符串。
    $prefixVersion = $options->version ?? '';
    $suffixVersion = '';
}

// 注意: $prefixVersion 和 $suffixVersion 变量现在可以在后续的模板或脚本中使用。

// --- 后续代码 (如权限检查、页面逻辑等) 将在此处继续 ---