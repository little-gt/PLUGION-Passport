<?php
if (!defined('__DIR__')) {
    define('__DIR__', dirname(__FILE__));
}

// 确保核心常量已定义
if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}
if (!defined('__ADMIN_DIR__')) {
    define('__ADMIN_DIR__', __TYPECHO_ROOT_DIR__ . __TYPECHO_ADMIN_DIR__);
}

/** 初始化组件 */
Typecho_Widget::widget('Widget_Init');
Typecho_Widget::widget('Widget_Options')->to($options);
Typecho_Widget::widget('Widget_Security')->to($security);
Typecho_Widget::widget('Widget_Menu')->to($menu);

/**
 * [已修复] 兼容不带斜杠的 Typecho 版本号 (e.g., 1.2.1)
 * 检查版本字符串中是否存在'/'，如果不存在，则将整个版本号赋给 $prefixVersion
 * 并将 $suffixVersion 设为空字符串，从而避免数组越界错误。
 */
if (strpos($options->version, '/') !== false) {
    list($prefixVersion, $suffixVersion) = explode('/', $options->version, 2);
} else {
    $prefixVersion = $options->version;
    $suffixVersion = '';
}