<?php
/**
 * Typecho 核心初始化脚本片段
 *
 * 此脚本用于在 Typecho 插件独立页面中（如找回密码、重置密码），
 * 定义核心常量、初始化必要的 Typecho Widget 组件并获取全局配置。
 *
 * @package Passport
 * @author GARFIELDTOM
 */

// 严格安全检查: 防止文件被直接访问。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- 1. 核心常量定义 ---

// 确保核心常量已定义，表明当前环境是 Typecho 后台环境或需要后台组件支持的环境。
if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}

// 定义后台目录的完整路径（依赖于 Typecho 核心的 __TYPECHO_ADMIN_DIR__）
if (!defined('__ADMIN_DIR__')) {
    define('__ADMIN_DIR__', __TYPECHO_ROOT_DIR__ . (defined('__TYPECHO_ADMIN_DIR__') ? __TYPECHO_ADMIN_DIR__ : '/admin/'));
}

// --- 2. Typecho Widget 初始化 ---

try {
    /**
     * 尝试调用 Widget_Init 进行初始化。
     * 这会处理自动加载、数据库连接等基础工作。
     */
    Typecho_Widget::widget('Widget_Init');
} catch (Throwable $e) {
    // 忽略初始化异常，确保在特殊环境下也能继续尝试加载后续组件
}

/**
 * 初始化并获取 Typecho 配置选项 Widget。
 * $options 变量保存了站点的所有配置 (如 siteUrl, title 等)。
 *
 * @var \Widget\Options $options
 */
Typecho_Widget::widget('Widget_Options')->to($options);

/**
 * 初始化并获取 Typecho 安全 Widget。
 * $security 变量用于处理 CSRF Token、密码哈希验证等安全操作。
 *
 * @var \Widget\Security $security
 */
Typecho_Widget::widget('Widget_Security')->to($security);

/**
 * 尝试初始化并获取 Typecho 后台菜单 Widget。
 * 主要用于获取页面标题 ($menu->title) 或生成后台导航结构。
 *
 * @var \Widget\Menu $menu
 */
Typecho_Widget::widget('Widget_Menu')->to($menu);

// --- 3. 版本号解析与兼容性处理 ---

/**
 * 获取 Typecho 版本号
 * 格式可能是 "1.2.1/17.10.27" (带构建日期) 或 "1.3.0" (纯版本号)
 */
$version = $options->version;

// 初始化前后缀变量
$prefixVersion = $version;
$suffixVersion = $version;

// 尝试解析带斜杠的旧版/开发版格式
if (strpos($version, '/') !== false) {
    $parts = explode('/', $version, 2);
    $prefixVersion = $parts[0];
    
    // 确保后缀存在且不为空，否则回退到前缀
    if (!empty($parts[1])) {
        $suffixVersion = $parts[1];
    }
}

// --- 4. 版本兼容性辅助函数 ---

/**
 * 生成路由 URL（兼容 Typecho 1.2.1 和 1.3.0）
 * 
 * 在 Typecho 1.2.1 中，Options 类通过 __call() 魔术方法处理未定义的方法
 * 在 Typecho 1.3.0 中，Options 类的 __call() 实现为 echo $this->currentConfig[$name]
 * 
 * 为了确保兼容性，我们使用 Common::url() 直接生成路由 URL
 * 
 * @param string $route 路由路径，如 '/passport/forgot'
 * @return string 完整的 URL
 */
function passport_route_url(string $route): string
{
    try {
        $options = Typecho_Widget::widget('Widget_Options');
        return \Typecho\Common::url($route, $options->index);
    } catch (Throwable $e) {
        return \Typecho\Common::url($route, '/');
    }
}