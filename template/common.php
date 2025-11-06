<?php
/**
 * Typecho 核心初始化脚本片段 - Passport 插件专用
 *
 * 此脚本用于在 Typecho 插件独立页面中，定义核心常量、
 * 初始化必要的 Typecho Widget 组件并获取配置。
 */

// 严格安全检查: 防止文件被直接访问。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- 核心常量定义 ---

// 确保核心常量已定义，表明当前环境是 Typecho 后台环境或需要后台组件支持的环境。
if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}

// 定义后台目录的完整路径（依赖于 Typecho 核心的 __TYPECHO_ADMIN_DIR__）
if (!defined('__ADMIN_DIR__')) {
    define('__ADMIN_DIR__', __TYPECHO_ROOT_DIR__ . (defined('__TYPECHO_ADMIN_DIR__') ? __TYPECHO_ADMIN_DIR__ : '/admin/'));
}

// --- Typecho Widget 初始化 ---

try {
    /**
     * 尝试调用 Widget_Init 进行初始化。
     */
    Typecho_Widget::widget('Widget_Init');
} catch (Throwable $e) {
    // 忽略异常，确保在 Typecho 环境中能继续运行
}


/**
 * 初始化并获取 Typecho 配置选项 Widget。
 * $options 变量保存了站点的所有配置。
 * @var \Widget\Options $options
 */
Typecho_Widget::widget('Widget_Options')->to($options);

/**
 * 初始化并获取 Typecho 安全 Widget。
 * $security 变量用于处理 CSRF Token、密码哈希等安全相关操作。
 * @var \Widget\Security $security
 */
Typecho_Widget::widget('Widget_Security')->to($security);

/**
 * 尝试初始化并获取 Typecho 后台菜单 Widget。
 * 仅用于兼容后台页面的依赖，实际前端页面可能不需要完整的菜单结构。
 * @var \Widget\Menu $menu
 */
Typecho_Widget::widget('Widget_Menu')->to($menu);

// --- 版本号解析与兼容性处理 ---

// 安全获取版本号，并分割为逻辑版本和静态资源版本
$version = $options->version ?? '1.2.1/17.10.27';
$parts = explode('/', $version, 2);
$prefixVersion = $parts[0] ?? '';
$suffixVersion = $parts[1] ?? '';

// 注意: $prefixVersion 和 $suffixVersion 变量现在可以在后续的模板或脚本中使用。

// --- 结束 ---