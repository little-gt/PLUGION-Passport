<?php
/**
 * Passport 插件独立前端模板 - 头部
 *
 * 用于 Typecho 插件 Passport 的独立前端页面（如找回密码、注册等）的头部模板。
 */

// 严格安全检查: 防止文件被直接访问。这是 Typecho 插件/模板的最佳实践。
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// --- 变量和配置获取 ---

// 从当前 Widget 实例中获取 Typecho 全局配置 $options。
// Typecho_Widget_Options 类型
$options = $this->options;

/**
 * 安全地获取菜单标题。
 * 针对 PHP 8.x 的兼容性/健壮性处理:
 * 确保 $this->menu 存在且 $this->menu->title 存在，避免因属性不存在而产生的 Notice 或 Fatal Error。
 * 如果不存在，则使用默认值 '密码找回'。
 */
$menu_title = property_exists($this, 'menu') && property_exists($this->menu, 'title') ? $this->menu->title : _t('密码找回');

// Typecho 静态资源版本号（通常用于缓存控制，Typecho 后台模板中使用）
// 假设 $suffixVersion 在此上下文环境中已定义或可访问。
if (!isset($suffixVersion)) {
    // 最佳实践：如果 $suffixVersion 未定义，为其设置一个默认或空值。
    $suffixVersion = '';
}

// --- HTML 头部资源 (CSS/JS) 字符串构建 ---

// Typecho_Common::url() 用于生成完整的静态资源 URL。
// $options->adminStaticUrl() 指向 Typecho 后台的静态资源目录。
$header = '<link rel="stylesheet" href="' . Typecho_Common::url('normalize.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<link rel="stylesheet" href="' . Typecho_Common::url('grid.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<link rel="stylesheet" href="' . Typecho_Common::url('style.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<!--[if lt IE 9]>
<script src="' . Typecho_Common::url('html5shiv.js?v=' . $suffixVersion, $options->adminStaticUrl('js')) . '"></script>
<script src="' . Typecho_Common::url('respond.js?v=' . $suffixVersion, $options->adminStaticUrl('js')) . '"></script>
<![endif]-->';

// --- HTML 文档开始 ---
?>
<!DOCTYPE HTML>
<html class="no-js" lang="<?php $options->lang(); // 输出语言属性，符合最佳实践 ?>">
    <head>
        <!-- 字符集设置 -->
        <meta charset="<?php $options->charset(); ?>">
        <!-- 兼容性设置 -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!-- 渲染器设置 (强制使用 webkit 内核) -->
        <meta name="renderer" content="webkit">
        <!-- 移动端视口设置 (响应式设计基础) -->
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- 页面标题：使用安全检查过的 $menu_title -->
        <title><?php _e('%s - %s', $menu_title, $options->title); ?></title>
        <!-- Favicon 图标 -->
        <link href="/favicon.ico" rel="icon" type="image/png">
        <!-- 告知搜索引擎不要索引或跟踪此页面 (适用于后台或功能性页面) -->
        <meta name="robots" content="noindex, nofollow">
        <!-- 引入前面构建的 CSS/JS 资源 -->
        <?php echo $header; ?>
    </head>
    <!-- body 标签，动态添加 class -->
    <body<?php
        // PHP 8 兼容性：使用 isset 确保变量存在且非 null
        if (isset($bodyClass) && $bodyClass) {
            echo ' class="' . htmlspecialchars($bodyClass) . '"'; // 使用 htmlspecialchars 提高安全性
        }
    ?>>
    <!-- 针对旧版本 IE 浏览器的提示 -->
    <!--[if lt IE 9]>
        <div class="message error browsehappy" role="dialog"><?php _e('当前网页 <strong>不支持</strong> 你正在使用的浏览器. 为了正常的访问, 请 <a href="https://www.microsoft.com/zh-cn/edge">升级你的浏览器</a>'); ?>.</div>
    <![endif]-->