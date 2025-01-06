# PASSPORT

-----------------

Typecho 的邮件密码找回插件


# 插件信息

-----------------

1. 优化了原本的实现逻辑，使得更加符合现代编码规范，提高代码复用率以及安全性；
2. 加入了reCAPTCHA v2.0验证机制；
3. 加入了自定义找回密码模版的功能和机制，允许自定义邮件样式。
4. 支持最新版本的Typecho使用，并且可以向下兼容。

原始仓库地址：https://github.com/typecho-fans/plugins/tree/master/Passport


# 使用帮助

-----------------

1. 上传插件包；
2. 打开管理后台的login.php 文件，做以下修改：
```
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
提示：其他地方也可以，自己根据需要进行调整。