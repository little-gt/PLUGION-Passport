<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 密码找回插件
 *
 * 为 Typecho 提供安全可靠的密码找回和重置功能。
 *
 * @package Passport
 * @author GARFIELDTOM
 * @version 0.1.2-fix
 * @link https://garfieldtom.cool/
 * @license GNU General Public License 2.0
 */

class Passport_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 路由名称常量：忘记密码
     */
    const ROUTE_FORGOT_NAME = 'passport_forgot';
    /**
     * 路由名称常量：重置密码
     */
    const ROUTE_RESET_NAME = 'passport_reset';

    /**
     * 路由路径常量：忘记密码
     */
    const ROUTE_FORGOT_PATH = '/passport/forgot';
    /**
     * 路由路径常量：重置密码
     */
    const ROUTE_RESET_PATH = '/passport/reset';
    /**
     * Action 路径常量：IP 解封操作
     */
    const ROUTE_UNBLOCK_IP_PATH = '/action/passport-unblock';

    /**
     * 插件激活方法
     *
     * 激活流程：
     * 1. 创建必要的数据库表。
     * 2. 注册路由和动作。
     * 3. 返回激活提示。
     *
     * @return string 激活成功提示
     * @throws Typecho_Plugin_Exception 如果操作失败
     */
    public static function activate(): string
    {
        try {
            // 创建数据库表 - 密码重置令牌表
            self::createTokenTable();
            // 创建数据库表 - 失败日志表 (用于速率限制)
            self::createFailLogTable();

            // 注册前端路由
            Helper::addRoute(self::ROUTE_FORGOT_NAME, self::ROUTE_FORGOT_PATH, 'Passport_Widget', 'doForgot');
            Helper::addRoute(self::ROUTE_RESET_NAME, self::ROUTE_RESET_PATH, 'Passport_Widget', 'doReset');
            // 注册后台 Action 路由 (用于 IP 解封)
            Helper::addAction('passport-unblock', 'Passport_Widget');

            // 返回激活提示, 引导用户配置关键参数
            return _t('插件已激活，请配置此插件的SMTP、验证码参数和HMAC密钥, 以使您的找回密码插件生效！');
        } catch (Exception $e) {
            error_log('Passport activate failed: ' . $e->getMessage());
            throw new Typecho_Plugin_Exception(_t('激活失败：%s。请检查数据库权限和日志。', $e->getMessage()));
        }
    }

    /**
     * 插件禁用方法
     *
     * 禁用流程：
     * 1. 根据配置决定是否清理数据和配置。
     * 2. 移除所有注册的路由和动作。
     *
     * @return void
     */
    public static function deactivate()
    {
        try {
            // 尝试获取插件配置
            $config = NULL;
            try {
                $config = Helper::options()->plugin('Passport');
            } catch (Typecho_Plugin_Exception $e) {
                // 配置不存在是正常情况，继续执行清理
            }

            // 检查是否勾选了禁用时删除数据
            if (isset($config->deleteDataOnDeactivate) && $config->deleteDataOnDeactivate == '1') {
                $db = Typecho_Db::get();
                $prefix = $db->getPrefix();

                // 删除插件创建的数据库表
                $db->query("DROP TABLE IF EXISTS `{$prefix}password_reset_tokens`", Typecho_Db::WRITE);
                $db->query("DROP TABLE IF EXISTS `{$prefix}passport_fails`", Typecho_Db::WRITE);

                // 删除插件在 options 表中的所有配置项
                $removeQuery = $db->delete($prefix . 'options')->where('name LIKE ?', 'plugin:Passport:%');
                $db->query($removeQuery);
            }

            // 移除路由和动作
            Helper::removeRoute(self::ROUTE_RESET_NAME);
            Helper::removeRoute(self::ROUTE_FORGOT_NAME);
            Helper::removeAction('passport-unblock');
        } catch (Exception $e) {
            error_log('Passport deactivate failed: ' . $e->getMessage());
            // 禁用流程不应抛出异常，记录错误后继续
        }
    }

    /**
     * 插件配置面板
     *
     * @param Typecho_Widget_Helper_Form $form 配置表单对象
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // IP 解封请求的完整 URL
        $actionUrl = Helper::security()->getIndex(Helper::url(self::ROUTE_UNBLOCK_IP_PATH, Helper::options()->index));

        // --- SMTP设置组 ---

        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.example.com', _t('<h2>邮件服务器SMTP配置</h2>服务器(SMTP)'), _t('<span style="color: red;">必须</span>如: smtp.exmail.qq.com'));
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('端口'), _t('<span style="color: red;">必须</span>如: 25、465(SSL)、587(SSL)'));
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, 'noreply@example.com', _t('帐号'), _t('<span style="color: red;">必须</span>如: hello@example.com'));
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, '', _t('密码'), _t('<span style="color: green;">可选</span>注：如果为无需密码验证，可以留空。推荐配置客户端密码，以保护数据安全。'));
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure', ['ssl' => _t('SSL'), 'tls' => _t('TLS'), 'none' => _t('无')], 'ssl', _t('安全类型'));

        $form->addInput($host);
        $form->addInput($port);
        $form->addInput($username);
        $form->addInput($password);
        $form->addInput($secure);

        // --- CAPTCHA设置组 ---

        $captchaType = new Typecho_Widget_Helper_Form_Element_Select('captchaType', ['none' => _t('不使用验证码（不推荐）'), 'recaptcha' => _t('Google reCAPTCHA v2'), 'hcaptcha' => _t('hCaptcha'), 'geetest' => _t('Geetest v4 (极验验证)')], 'none', _t('<h2>人机验证配置</h2>验证码类型'), _t('选择您要使用的验证码服务。'));
        $form->addInput($captchaType);

        $sitekeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyRecaptcha', NULL, '', _t('reCAPTCHA Site Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));
        $secretkeyRecaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyRecaptcha', NULL, '', _t('reCAPTCHA Secret Key'), _t('访问 <a href="https://www.google.com/recaptcha/admin" target="_blank">reCAPTCHA 控制台</a> 获取。'));
        $form->addInput($sitekeyRecaptcha);
        $form->addInput($secretkeyRecaptcha);

        $sitekeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('sitekeyHcaptcha', NULL, '', _t('hCaptcha Site Key'), _t('访问 <a href="https://dashboard.hcaptcha.com/login/" target="_blank">hCaptcha 控制台</a> 获取。'));
        $secretkeyHcaptcha = new Typecho_Widget_Helper_Form_Element_Text('secretkeyHcaptcha', NULL, '', _t('hCaptcha Secret Key'), _t('访问 <a href="https://dashboard.hcaptcha.com/login/" target="_blank">hCaptcha 控制台</a> 获取。'));
        $form->addInput($sitekeyHcaptcha);
        $form->addInput($secretkeyHcaptcha);

        $captchaIdGeetest = new Typecho_Widget_Helper_Form_Element_Text('captchaIdGeetest', NULL, '', _t('Geetest CAPTCHA ID'), _t('访问 <a href="https://auth.geetest.com/login/" target="_blank">GEETEST 控制台</a> 获取。'));
        $captchaKeyGeetest = new Typecho_Widget_Helper_Form_Element_Text('captchaKeyGeetest', NULL, '', _t('Geetest CAPTCHA KEY'), _t('访问 <a href="https://auth.geetest.com/login/" target="_blank">GEETEST 控制台</a> 获取。'));
        $form->addInput($captchaIdGeetest);
        $form->addInput($captchaKeyGeetest);

        // --- 安全与高级设置组 ---

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey', NULL, self::generateStrongRandomKey(32), _t('<h2>安全与高级设置</h2>HMAC 密钥'), _t('<span style="color: blue;">推荐</span>用于令牌签名验证的密钥，建议使用 32 位随机含数字、大小写字母的字符串。首次激活时已自动生成，<b>留空将禁用签名验证</b>。'));
        $form->addInput($secretKey);

        $enableRateLimit = new Typecho_Widget_Helper_Form_Element_Radio('enableRateLimit', ['1' => _t('启用'), '0' => _t('禁用')], '1', _t('启用请求速率限制？'), _t('<span style="color: blue;">推荐</span>可防止暴力破解和邮件滥用，并自动临时封禁风险IP。'));
        $form->addInput($enableRateLimit);

        $deleteDataOnDeactivate = new Typecho_Widget_Helper_Form_Element_Radio('deleteDataOnDeactivate', ['1' => _t('是，删除所有数据'), '0' => _t('否，保留数据')], '0', _t('禁用插件删除数据？'), _t('<span style="color: green;">可选</span>选择“是”将在禁用插件时，永久删除此插件创建的所有数据库表和设置。'));
        $form->addInput($deleteDataOnDeactivate);

        // --- 邮件模板组 ---

        $defaultTemplate = '<h3>{username}，您好：</h3>
                           <p>您在 {sitename} 提交了密码重置操作于：{requestTime}。</p>
                           <hr/>
                           <p>请在 1 小时内点击此链接以完成重置 <a href="{resetLink}">{resetLink}</a></p>
                           <hr/>
                           <p>技术支持：GARFIELDTOM</p>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL, $defaultTemplate, _t('<h2>邮件模板配置</h2>邮件内容'), _t('请使用 {username} {sitename} {requestTime} {resetLink} 作为占位符'));
        $form->addInput($emailTemplate);

        // --- 风险管理标题和表格 ---
        try {
            echo '<h2>' . _t('IP请求日志与封禁状态') . '</h2>';
            echo self::renderFailLogTable($actionUrl);
            echo '<p>' . _t('出于性能考虑，只展示最近的25条记录，如果需要完整记录请查询数据库，后续将支持一键导出。') . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">' . _t('风险日志加载失败：%s', htmlspecialchars($e->getMessage())) . '</p>';
            error_log('Passport config risk log failed: ' . $e->getMessage());
        }

        // --- 动态JS：验证码切换 ---
        echo self::getCaptchaToggleJs();
    }

    /**
     * 个人配置面板（空实现）
     *
     * @param Typecho_Widget_Helper_Form $form 个人配置表单
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 渲染失败日志表格
     *
     * 查询最近25条日志，渲染HTML表格。
     *
     * @param string $actionUrl IP解封的POST目标地址
     * @return string HTML表格字符串
     */
    private static function renderFailLogTable(string $actionUrl): string
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $logs = $db->fetchAll($db->select()->from("{$prefix}passport_fails")
            ->order("{$prefix}passport_fails.last_attempt", Typecho_Db::SORT_DESC)
            ->limit(25));

        $html = '<table class="typecho-list-table">
            <colgroup><col width="20%"><col width="10%"><col width="25%"><col width="25%"><col width="20%"></colgroup>
            <thead><tr>
                <th>' . _t('IP 地址') . '</th><th>' . _t('尝试次数') . '</th><th>' . _t('最后尝试时间') . '</th><th>' . _t('状态') . '</th><th>' . _t('操作') . '</th>
            </tr></thead>
            <tbody>';

        if (empty($logs)) {
            $html .= '<tr><td colspan="5"><h6 class="typecho-list-table-title">' . _t('当前没有风险记录') . '</h6></td></tr>';
        } else {
            $now = time();
            foreach ($logs as $log) {
                // 确保数据类型正确且经过安全过滤
                $ip = htmlspecialchars((string) ($log['ip'] ?? ''));
                $attempts = (int) ($log['attempts'] ?? 0);
                $lastAttempt = (int) ($log['last_attempt'] ?? 0);
                $lockedUntil = (int) ($log['locked_until'] ?? 0);

                $status = '<span style="color: green;">' . _t('安全') . '</span>';
                $action = '<span>-</span>';

                if ($lockedUntil > $now) {
                    $remaining_time = $lockedUntil - $now;
                    $remaining_minutes = (int) ceil($remaining_time / 60);
                    $status = '<span style="color: red; font-weight: bold;">' . _t('封禁中') . '</span> (' . _t('剩余约') . ' ' . $remaining_minutes . ' ' . _t('分钟') . ')';
                    $action = '<form method="post" action="' . $actionUrl . '" style="margin:0; padding:0;">' .
                              '<input type="hidden" name="unblock_ip" value="' . $ip . '">' .
                              '<button type="submit" class="btn btn-s btn-warn">' . _t('立即解封') . '</button>' .
                              '</form>';
                } elseif ($lockedUntil > 0) {
                    $status = '<span style="color: grey;">' . _t('封禁已过期') . '</span>';
                }

                $html .= '<tr>' .
                         '<td>' . $ip . '</td>' .
                         '<td>' . $attempts . '</td>' .
                         '<td>' . date('Y-m-d H:i:s', $lastAttempt) . '</td>' .
                         '<td>' . $status . '</td>' .
                         '<td>' . $action . '</td>' .
                         '</tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * 生成验证码切换的JavaScript
     *
     * @return string JavaScript代码
     */
    private static function getCaptchaToggleJs(): string
    {
        return <<<JS
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const settingsMap = {
                    recaptcha: ['sitekeyRecaptcha', 'secretkeyRecaptcha'],
                    hcaptcha: ['sitekeyHcaptcha', 'secretkeyHcaptcha'],
                    geetest: ['captchaIdGeetest', 'captchaKeyGeetest']
                };

                const captchaTypeSelector = document.querySelector('[name="captchaType"]');

                function toggleCaptchaSettings() {
                    if (!captchaTypeSelector) return;
                    const selectedType = captchaTypeSelector.value;

                    // 隐藏所有CAPTCHA设置
                    for (const type in settingsMap) {
                        settingsMap[type].forEach(name => {
                            const input = document.querySelector('[name="' + name + '"]');
                            if (input) {
                                const parentLi = input.closest('li');
                                if (parentLi) {
                                    parentLi.style.display = 'none';
                                }
                            }
                        });
                    }

                    // 显示选中的CAPTCHA设置
                    if (settingsMap[selectedType]) {
                        settingsMap[selectedType].forEach(name => {
                            const input = document.querySelector('[name="' + name + '"]');
                            if (input) {
                                const parentLi = input.closest('li');
                                if (parentLi) {
                                    parentLi.style.display = '';
                                }
                            }
                        });
                    }
                }

                if (captchaTypeSelector) {
                    toggleCaptchaSettings();
                    captchaTypeSelector.addEventListener('change', toggleCaptchaSettings);
                }
            });
        </script>
JS;
    }

    /**
     * 生成安全的随机HMAC密钥
     *
     * @param int $length 密钥长度（字节）
     * @return string 十六进制编码的随机字符串
     */
    private static function generateStrongRandomKey(int $length): string
    {
        // 优先使用 PHP 7+ 的安全方法
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes($length));
            } catch (Exception $e) {
                error_log('random_bytes failed: ' . $e->getMessage());
                // Fallback
            }
        }

        // 旧版本 PHP 的 fallback 方法
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return hash('sha256', $randomString); // 使用 hash 确保长度和伪随机性
    }

    /**
     * 创建密码重置令牌表
     *
     * 表结构：token (主键), uid, created_at, used。
     *
     * @return void
     * @throws Typecho_Db_Exception
     */
    private static function createTokenTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'password_reset_tokens';
        $adapterName = $db->getAdapterName();

        // 默认通用 SQL (SQLite / PostgreSQL)
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `token` VARCHAR(64) NOT NULL,
            `uid` INT(10) NOT NULL,
            `created_at` INT(10) NOT NULL,
            `used` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`token`),
            INDEX `uid` (`uid`),
            INDEX `created_at` (`created_at`)
        )";

        // MySQL 专用 SQL
        if (false !== strpos($adapterName, 'Mysql')) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `token` VARCHAR(64) NOT NULL,
                `uid` INT(10) UNSIGNED NOT NULL,
                `created_at` INT(10) UNSIGNED NOT NULL,
                `used` TINYINT(1) DEFAULT 0,
                PRIMARY KEY (`token`),
                INDEX `uid` (`uid`),
                INDEX `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $db->query($sql);
    }

    /**
     * 创建失败日志表
     *
     * 表结构：ip (主键), attempts, last_attempt, locked_until。
     *
     * @return void
     * @throws Typecho_Db_Exception
     */
    private static function createFailLogTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'passport_fails';
        $adapterName = $db->getAdapterName();

        // 默认通用 SQL (SQLite / PostgreSQL)
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `ip` VARCHAR(45) NOT NULL,
            `attempts` INT(10) NOT NULL DEFAULT 0,
            `last_attempt` INT(10) NOT NULL,
            `locked_until` INT(10) NOT NULL DEFAULT 0,
            PRIMARY KEY (`ip`),
            INDEX `locked_until` (`locked_until`)
        )";

        // MySQL 专用 SQL
        if (false !== strpos($adapterName, 'Mysql')) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `ip` VARCHAR(45) NOT NULL,
                `attempts` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `last_attempt` INT(10) UNSIGNED NOT NULL,
                `locked_until` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`ip`),
                INDEX `locked_until` (`locked_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $db->query($sql);
    }
}