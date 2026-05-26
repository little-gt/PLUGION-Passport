<?php
/**
 * Passport 插件独立前端模板 - 头部
 *
 * 用于 Typecho 插件 Passport 的独立前端页面（如找回密码、重置密码）的头部模板。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- 变量和配置获取 ---

/**
 * 安全地获取页面标题，并确保它经过翻译。
 * $menuTitle 应该在调用此头部文件之前定义（如在 forgot.php 或 reset.php 中）
 */
$menuTitle = isset($menuTitle) ? (string) $menuTitle : _t('密码找回');
$siteTitle = (string) $options->title;
$charset = (string) $options->charset;
$lang = (string) $options->lang;

// --- HTML 文档开始 ---
?>
<!DOCTYPE HTML>
<html lang="<?php echo htmlspecialchars($lang); ?>">
    <head>
        <!-- 字符集设置 -->
        <meta charset="<?php echo htmlspecialchars($charset); ?>">
        <!-- 兼容性设置 -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!-- 渲染器设置 (强制使用 webkit 内核) -->
        <meta name="renderer" content="webkit">
        <!-- 移动端视口设置 (响应式设计基础) -->
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- 页面标题：使用安全检查过的 $menuTitle -->
        <title><?php _e('%s - %s', $menuTitle, $siteTitle); ?></title>
        <!-- Favicon 图标 -->
        <link href="/favicon.ico" rel="icon" type="image/png">
        <!-- 告知搜索引擎不要索引或跟踪此页面 (适用于后台或功能性页面) -->
        <meta name="robots" content="noindex, nofollow">
    </head>
    <!-- body 标签 -->
    <body>