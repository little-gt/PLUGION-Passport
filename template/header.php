<?php
/**
 * Passport 插件独立前端模板 - 头部
 *
 * 用于 Typecho 插件 Passport 的独立前端页面（如找回密码、重置密码）的头部模板。
 * 依赖于 common.php 中初始化的 $options, $security, $menu, $suffixVersion。
 */

// 严格安全检查: 防止文件被直接访问。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- 变量和配置获取 ---

/**
 * 安全地获取菜单标题，并确保它经过翻译。
 */
$menuTitle = property_exists($this, 'menu') && property_exists($this->menu, 'title') ? (string) $this->menu->title : _t('密码找回');
$siteTitle = (string) $options->title;
$charset = (string) $options->charset;
$lang = (string) $options->lang;

// --- HTML 头部资源 (CSS/JS) 字符串构建 ---

/**
 * 生成 CSS 文件的完整 URL，并加上版本号用于缓存控制。
 * @param string $file 文件名
 * @return string
 */
$cssUrl = function (string $file) use ($options, $suffixVersion): string {
    return Typecho_Common::url("{$file}?v={$suffixVersion}", $options->adminStaticUrl('css'));
};

/**
 * 生成 JS 文件的完整 URL，并加上版本号用于缓存控制。
 * @param string $file 文件名
 * @return string
 */
$jsUrl = function (string $file) use ($options, $suffixVersion): string {
    return Typecho_Common::url("{$file}?v={$suffixVersion}", $options->adminStaticUrl('js'));
};


$header = '<link rel="stylesheet" href="' . $cssUrl('normalize.css') . '">' . "\n" .
          '<link rel="stylesheet" href="' . $cssUrl('grid.css') . '">' . "\n" .
          '<link rel="stylesheet" href="' . $cssUrl('style.css') . '">' . "\n" .
          '<!--[if lt IE 9]>' . "\n" .
          '<script src="' . $jsUrl('html5shiv.js') . '"></script>' . "\n" .
          '<script src="' . $jsUrl('respond.js') . '"></script>' . "\n" .
          '<![endif]-->';

// --- HTML 文档开始 ---
?>
<!DOCTYPE HTML>
<html class="no-js" lang="<?php echo htmlspecialchars($lang); ?>">
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
        <title><?php _e('%s - %s - Powered by Typecho', $menuTitle, $siteTitle); ?></title>
        <!-- Favicon 图标 -->
        <link href="/favicon.ico" rel="icon" type="image/png">
        <!-- 告知搜索引擎不要索引或跟踪此页面 (适用于后台或功能性页面) -->
        <meta name="robots" content="noindex, nofollow">
        <!-- 引入前面构建的 CSS/JS 资源 -->
        <?php echo $header; ?>
    </head>
    <!-- body 标签，动态添加 class -->
    <body<?php
        $bodyClass = (string) ($this->bodyClass ?? '');
        if ($bodyClass) {
            echo ' class="' . htmlspecialchars($bodyClass) . '"'; // 使用 htmlspecialchars 提高安全性
        }
    ?>>
    <!-- 针对旧版本 IE 浏览器的提示 -->
    <!--[if lt IE 9]>
        <div class="message error browsehappy" role="dialog"><?php _e('当前网页 <strong>不支持</strong> 你正在使用的浏览器. 为了正常的访问, 请 <a href="https://www.microsoft.com/zh-cn/edge">升级你的浏览器</a>'); ?>.</div>
    <![endif]-->