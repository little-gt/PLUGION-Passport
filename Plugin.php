<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 密码找回插件
 *
 * 为 Typecho 提供安全可靠的密码找回和重置功能。
 * 集成多种人机验证机制，支持防暴力破解和 IP 速率限制。
 *
 * @package Passport
 * @author GARFIELDTOM
 * @copyright Copyright (c) 2025 GARFIELDTOM
 * @version 0.1.4
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
     * 路由名称常量：验证码图片
     */
    const ROUTE_CAPTCHA_NAME = 'passport_captcha';

    /**
     * 路由路径常量：忘记密码
     */
    const ROUTE_FORGOT_PATH = '/passport/forgot';

    /**
     * 路由路径常量：重置密码
     */
    const ROUTE_RESET_PATH = '/passport/reset';

    /**
     * 路由路径常量：验证码图片
     */
    const ROUTE_CAPTCHA_PATH = '/passport/captcha';

    /**
     * Action 路径常量：IP 解封操作
     */
    const ROUTE_UNBLOCK_IP_PATH = '/action/passport-unblock';

    /**
     * 插件激活方法
     *
     * 激活流程：
     * 1. 检测并创建必要的数据库表，防止数据覆盖。
     * 2. 注册前端页面路由、验证码路由和后台动作。
     * 3. 返回激活成功提示。
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

            // 注册前端页面路由
            Helper::addRoute(self::ROUTE_FORGOT_NAME, self::ROUTE_FORGOT_PATH, 'Passport_Widget', 'doForgot');
            Helper::addRoute(self::ROUTE_RESET_NAME, self::ROUTE_RESET_PATH, 'Passport_Widget', 'doReset');
            
            // 注册内置验证码图片路由
            Helper::addRoute(self::ROUTE_CAPTCHA_NAME, self::ROUTE_CAPTCHA_PATH, 'Passport_Widget', 'renderCaptcha');

            // 注册后台 Action 路由 (用于 IP 解封)
            Helper::addAction('passport-unblock', 'Passport_Widget');

            return _t('插件已激活！默认启用内置图片验证码。请根据需要配置 SMTP 和 IP 获取策略。');
        } catch (Exception $e) {
            error_log('Passport activate failed: ' . $e->getMessage());
            throw new Typecho_Plugin_Exception(_t('激活失败：%s。请检查数据库权限和日志。', $e->getMessage()));
        }
    }

    /**
     * 插件禁用方法
     *
     * 禁用流程：
     * 1. 检查配置，仅在用户明确勾选“删除数据”时清理数据库。
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
                // 配置不存在是正常情况（如插件未配置过），继续执行清理
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
            Helper::removeRoute(self::ROUTE_CAPTCHA_NAME);
            Helper::removeAction('passport-unblock');

        } catch (Exception $e) {
            error_log('Passport deactivate failed: ' . $e->getMessage());
            // 禁用流程不应抛出异常阻断用户操作，记录错误后继续
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

        // --- SMTP 设置组 ---

        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.example.com', _t('<h2>邮件服务配置</h2>服务器 (SMTP)'), _t('<span style="color: #ce5252;">必须</span> 如: smtp.163.com'));
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465', _t('端口'), _t('<span style="color: #ce5252;">必须</span> 如: 25、465(SSL)、587(TLS)'));
        $username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, 'noreply@example.com', _t('帐号'), _t('<span style="color: #ce5252;">必须</span> 如: example@163.com'));
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, '', _t('密码'), _t('<span style="color: #5e6db3;">说明</span> 如账号为无需密码验证的账号，可以留空。<span style="color: #35a937;">推荐配置客户端授权码。</span>'));
        $secure = new Typecho_Widget_Helper_Form_Element_Select('secure', ['ssl' => _t('SSL'), 'tls' => _t('TLS'), 'none' => _t('无')], 'ssl', _t('加密类型'));

        $form->addInput($host);
        $form->addInput($port);
        $form->addInput($username);
        $form->addInput($password);
        $form->addInput($secure);

        // --- 邮件模板组 ---

        $defaultTemplate = '<!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset="UTF-8">
                                <title>{sitename} 密码重置指引</title>
                            </head>
                            <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #2b2b2b; color: #ffffff;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #2b2b2b; color: #ffffff;">
                                    <tr>
                                        <td style="padding: 20px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #333333;">
                                                <tr>
                                                    <td style="padding: 20px; background-color: #222222;">
                                                        <h3 style="margin: 0; color: #ffffff;">密码重置指引</h3>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 25px;">
                                                        <h3 style="margin-top: 0; color: #ffffff;">{username}，您好：</h3>
                                                        <p style="color: #dddddd; margin: 10px 0;">您在 {sitename} 提交了密码重置操作于：{requestTime}。</p>
                                                        <hr style="border: none; height: 1px; background-color: #555555; margin: 20px 0;">
                                                        <p style="color: #ffffff; margin: 10px 0;"><strong>请在 1 小时内点击此链接以完成重置：</strong></p>
                                                        <a href="{resetLink}" style="display: inline-block; background-color: #444444; color: #ffffff; text-decoration: none; padding: 12px 24px; margin: 15px 0; border: 1px solid #666666;">点击重置密码</a>
                                                        <p style="color: #aaaaaa; margin: 15px 0;">如果按钮无法点击，可复制以下链接：<br><a href="{resetLink}" style="color: #cccccc;">{resetLink}</a></p>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 15px; background-color: #222222; color: #999999; font-size: 14px;">
                                                        <p style="margin: 0;">技术支持：GARFIELDTOM</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </body>
                            </html>';
        $emailTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailTemplate', NULL, $defaultTemplate, _t('<h2>邮件模板配置</h2>邮件内容'), _t('<span style="color: #5e6db3;">说明</span> 请使用 {username} {sitename} {requestTime} {resetLink} 作为占位符'));
        $form->addInput($emailTemplate);

        // --- CAPTCHA 设置组 ---

        // 移除了 'none' 选项，强制开启验证
        $captchaType = new Typecho_Widget_Helper_Form_Element_Select(
            'captchaType',
            [
                'default'   => _t('内置图片验证码 (默认)'),
                'recaptcha' => _t('Google reCAPTCHA v2'),
                'hcaptcha'  => _t('hCaptcha'),
                'geetest'   => _t('Geetest v4 (极验验证)')
            ],
            'default',
            _t('<h2>人机验证配置</h2>验证码类型'),
            _t('<span style="color: #ce5252;">必须</span> 为保障安全，验证码功能无法关闭。默认使用内置图片验证码，无需额外配置，开箱即用。')
        );
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

        // --- 高级设置组 ---

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey', NULL, self::generateStrongRandomKey(32), _t('<h2>高级功能设置</h2>HMAC 密钥'), _t('<span style="color: #5e6db3;">说明</span> 用于令牌签名验证的密钥。首次激活时已自动生成，<span style="color: #ce5252;">留空将禁用签名验证（极不推荐）</span>。'));
        $form->addInput($secretKey);

        $enableRateLimit = new Typecho_Widget_Helper_Form_Element_Radio('enableRateLimit', ['1' => _t('启用'), '0' => _t('禁用')], '1', _t('启用请求速率限制？'), _t('<span style="color: #5e6db3;">说明</span> 可防止暴力破解和邮件滥用，并自动临时封禁风险IP。'));
        $form->addInput($enableRateLimit);

        $deleteDataOnDeactivate = new Typecho_Widget_Helper_Form_Element_Radio('deleteDataOnDeactivate', ['1' => _t('是，删除所有数据'), '0' => _t('否，保留数据')], '0', _t('禁用插件删除数据？'), _t('<span style="color: #5e6db3;">说明</span> 选择"是"将在禁用插件时，永久删除此插件创建的所有数据库表和设置。'));
        $form->addInput($deleteDataOnDeactivate);

        // --- 日志管理设置组 ---
        $logPageSize = new Typecho_Widget_Helper_Form_Element_Text('logPageSize', NULL, '25', _t('<h2>日志管理设置</h2>每页显示记录数'), _t('<span style="color: #5e6db3;">说明</span> 设置请求日志每页显示的记录数量，建议设置为 10-50 之间的数值。'));
        $form->addInput($logPageSize);

        $logRetentionDays = new Typecho_Widget_Helper_Form_Element_Text('logRetentionDays', NULL, '0', _t('日志保留天数'), _t('<span style="color: #5e6db3;">说明</span> 自动清理超过指定天数的日志记录，设置为 0 表示不自动清理。'));
        $form->addInput($logRetentionDays);

        // --- IP 策略设置组 ---
        $ipSource = new Typecho_Widget_Helper_Form_Element_Select(
            'ipSource',
            [
                'default' => _t('默认 (REMOTE_ADDR)'),
                'proxy'   => _t('代理头 (X-Forwarded-For / Client-IP)'),
                'custom'  => _t('自定义请求头')
            ],
            'default',
            _t('<h2>IP 识别策略</h2>IP 地址获取方式'),
            _t('<span style="color: #ce5252;">必须</span> 如果您的站点位于 CDN (如 Cloudflare) 或反向代理之后，请选择“代理头”或配置“自定义请求头”，否则速率限制功能将无法正确识别用户 IP。')
        );
        $form->addInput($ipSource);

        $customIpHeader = new Typecho_Widget_Helper_Form_Element_Text(
            'customIpHeader',
            NULL,
            'HTTP_CF_CONNECTING_IP',
            _t('自定义 IP 请求头名称'),
            _t('<span style="color: #5e6db3;">说明</span> 仅当上面的选项选择“自定义请求头”时生效。例如 Cloudflare 用户可填写 <code>HTTP_CF_CONNECTING_IP</code>。')
        );
        $form->addInput($customIpHeader);

        // --- 风险管理标题和表格 ---
        try {
            echo '<h2>' . _t('请求日志与封禁状态') . '</h2>';
            echo self::renderFailLogTable($actionUrl);
        } catch (Exception $e) {
            echo '<p style="color: #ce5252;">' . _t('风险日志加载失败：%s', htmlspecialchars($e->getMessage())) . '</p>';
            error_log('Passport config risk log failed: ' . $e->getMessage());
        }

        // --- 动态JS：验证码与IP策略切换 ---
        echo self::getDynamicSettingsJs();
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
     * @param string $actionUrl IP解封的POST目标地址
     * @return string HTML表格字符串
     */
    private static function renderFailLogTable(string $actionUrl): string
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 获取配置
        $config = Helper::options()->plugin('Passport');
        $pageSize = isset($config->logPageSize) && is_numeric($config->logPageSize) 
            ? (int) $config->logPageSize 
            : 25;
        $retentionDays = isset($config->logRetentionDays) && is_numeric($config->logRetentionDays)
            ? (int) $config->logRetentionDays
            : 30;

        // 自动清理过期日志
        if ($retentionDays > 0) {
            $expireTime = time() - ($retentionDays * 86400);
            try {
                $db->query($db->delete("{$prefix}passport_fails")
                    ->where('last_attempt < ?', $expireTime));
            } catch (Exception $e) {
                error_log('Passport: Failed to clean old logs - ' . $e->getMessage());
            }
        }

        // 检查表是否存在，避免首次启用时报错
        try {
            // 获取总记录数
            $totalQuery = $db->select()->from("{$prefix}passport_fails");
            $totalResult = $db->fetchRow($totalQuery->select('COUNT(*) as total'));
            $total = isset($totalResult['total']) ? (int) $totalResult['total'] : 0;

            // 计算分页
            $page = isset($_GET['passport_page']) ? max(1, (int) $_GET['passport_page']) : 1;
            $offset = ($page - 1) * $pageSize;
            $totalPages = (int) ceil($total / $pageSize);

            // 获取当前页数据
            $logs = $db->fetchAll($db->select()->from("{$prefix}passport_fails")
                ->order("{$prefix}passport_fails.last_attempt", Typecho_Db::SORT_DESC)
                ->limit($pageSize)
                ->offset($offset));
        } catch (Typecho_Db_Exception $e) {
            return '<p>' . _t('日志表尚未创建，保存配置后将会自动创建。') . '</p>';
        }

        $html = '<div id="passport-log-container">';
        
        // 搜索和筛选栏
        $html .= '<div class="passport-log-toolbar">';
        $html .= '<div class="passport-log-toolbar-inner">';
        $html .= '<div class="toolbar-group">';
        $html .= '<label class="toolbar-label" for="passport-log-search">' . _t('搜索') . ':</label>';
        $html .= '<input type="text" id="passport-log-search" placeholder="' . _t('IP 地址') . '">';
        $html .= '</div>';
        $html .= '<div class="toolbar-group">';
        $html .= '<label class="toolbar-label" for="passport-log-filter">' . _t('状态') . ':</label>';
        $html .= '<select id="passport-log-filter">';
        $html .= '<option value="all">' . _t('全部状态') . '</option>';
        $html .= '<option value="locked">' . _t('封禁中') . '</option>';
        $html .= '<option value="safe">' . _t('安全') . '</option>';
        $html .= '<option value="expired">' . _t('封禁已过期') . '</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="toolbar-group">';
        $html .= '<button type="button" id="passport-log-refresh" class="btn btn-s">' . _t('刷新') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="passport-log-batch-actions">';
        $html .= '<button type="button" id="passport-log-batch-unblock" class="btn btn-s btn-warn" style="display: none;">' . _t('批量解封') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // 表格
        $html .= '<table class="typecho-list-table" id="passport-log-table">
            <colgroup><col width="5%"><col width="20%"><col width="10%"><col width="25%"><col width="25%"><col width="15%"></colgroup>
            <thead><tr>
                <th><input type="checkbox" id="passport-log-select-all"></th>
                <th>' . _t('IP 地址') . '</th><th>' . _t('尝试次数') . '</th><th>' . _t('最后尝试时间') . '</th><th>' . _t('状态') . '</th><th>' . _t('操作') . '</th>
            </tr></thead>
            <tbody>';

        if (empty($logs)) {
            $html .= '<tr><td colspan="6"><h6 class="typecho-list-table-title">' . _t('当前没有风险记录') . '</h6></td></tr>';
        } else {
            $now = time();
            foreach ($logs as $log) {
                $ip = htmlspecialchars((string) ($log['ip'] ?? ''));
                $attempts = (int) ($log['attempts'] ?? 0);
                $lastAttempt = (int) ($log['last_attempt'] ?? 0);
                $lockedUntil = (int) ($log['locked_until'] ?? 0);

                $statusClass = 'safe';
                $statusText = _t('安全');
                $action = '<span>-</span>';

                if ($lockedUntil > $now) {
                    $remaining_time = $lockedUntil - $now;
                    $remaining_minutes = (int) ceil($remaining_time / 60);
                    $statusClass = 'locked';
                    $statusText = _t('封禁中') . ' (' . _t('剩余约') . ' ' . $remaining_minutes . ' ' . _t('分钟') . ')';
                    $action = '<form method="post" action="' . $actionUrl . '" style="margin:0; padding:0;" class="unblock-form" data-ip="' . $ip . '">' .
                              '<input type="hidden" name="unblock_ip" value="' . $ip . '">' .
                              '<button type="submit" class="btn btn-s btn-warn">' . _t('立即解封') . '</button>' .
                              '</form>';
                } elseif ($lockedUntil > 0) {
                    $statusClass = 'expired';
                    $statusText = _t('封禁已过期');
                }

                $html .= '<tr data-status="' . $statusClass . '" data-ip="' . $ip . '">' .
                         '<td><input type="checkbox" class="passport-log-checkbox" value="' . $ip . '"></td>' .
                         '<td>' . $ip . '</td>' .
                         '<td>' . $attempts . '</td>' .
                         '<td>' . date('Y-m-d H:i:s', $lastAttempt) . '</td>' .
                         '<td><span class="passport-log-status passport-log-status-' . $statusClass . '">' . $statusText . '</span></td>' .
                         '<td>' . $action . '</td>' .
                         '</tr>';
            }
        }

        $html .= '</tbody></table>';

        // 分页信息
        if ($total > 0) {
            $html .= '<div class="passport-log-pagination">';
            $html .= '<div class="passport-log-pagination-inner">';
            $html .= '<div class="page-info">' . _t('共 %s 条记录', $total) . '</div>';
            
            if ($totalPages > 1) {
                $html .= '<div class="pagination">';
                
                // 上一页
                if ($page > 1) {
                    $prevPage = $page - 1;
                    $html .= '<a href="#" class="page-link" data-page="' . $prevPage . '">&laquo; ' . _t('上一页') . '</a>';
                }
                
                // 页码
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1) {
                    $html .= '<a href="#" class="page-link" data-page="1">1</a>';
                    if ($startPage > 2) {
                        $html .= '<span class="page-ellipsis">...</span>';
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $activeClass = $i == $page ? ' active' : '';
                    $html .= '<a href="#" class="page-link' . $activeClass . '" data-page="' . $i . '">' . $i . '</a>';
                }
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        $html .= '<span class="page-ellipsis">...</span>';
                    }
                    $html .= '<a href="#" class="page-link" data-page="' . $totalPages . '">' . $totalPages . '</a>';
                }
                
                // 下一页
                if ($page < $totalPages) {
                    $nextPage = $page + 1;
                    $html .= '<a href="#" class="page-link" data-page="' . $nextPage . '">' . _t('下一页') . ' &raquo;</a>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '<div class="page-info">' . _t('每页 %s 条', $pageSize) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // 添加样式和脚本
        $html .= self::getLogTableStyles();
        $html .= self::getLogTableScripts($actionUrl, $pageSize);

        return $html;
    }

    /**
     * 生成动态交互的 JavaScript
     * 处理验证码类型切换和 IP 策略切换时的表单项显示/隐藏
     *
     * @return string JavaScript代码
     */
    private static function getDynamicSettingsJs(): string
    {
        return <<<JS
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- 验证码设置切换逻辑 ---
                const captchaMap = {
                    default: [], // 内置验证码没有额外配置
                    recaptcha: ['sitekeyRecaptcha', 'secretkeyRecaptcha'],
                    hcaptcha: ['sitekeyHcaptcha', 'secretkeyHcaptcha'],
                    geetest: ['captchaIdGeetest', 'captchaKeyGeetest']
                };
                const captchaSelector = document.querySelector('[name="captchaType"]');

                function toggleCaptcha() {
                    if (!captchaSelector) return;
                    const type = captchaSelector.value;
                    
                    // 隐藏所有特定配置
                    Object.values(captchaMap).flat().forEach(name => {
                        const el = document.querySelector('[name="' + name + '"]');
                        if (el) el.closest('li').style.display = 'none';
                    });

                    // 显示当前选中类型的配置
                    if (captchaMap[type]) {
                        captchaMap[type].forEach(name => {
                            const el = document.querySelector('[name="' + name + '"]');
                            if (el) el.closest('li').style.display = '';
                        });
                    }
                }

                // --- IP 策略切换逻辑 ---
                const ipSourceSelector = document.querySelector('[name="ipSource"]');
                const customIpHeaderInput = document.querySelector('[name="customIpHeader"]');

                function toggleIpSettings() {
                    if (!ipSourceSelector || !customIpHeaderInput) return;
                    const type = ipSourceSelector.value;
                    const container = customIpHeaderInput.closest('li');
                    
                    if (type === 'custom') {
                        container.style.display = '';
                    } else {
                        container.style.display = 'none';
                    }
                }

                // 绑定事件
                if (captchaSelector) {
                    captchaSelector.addEventListener('change', toggleCaptcha);
                    toggleCaptcha(); // 初始化
                }
                
                if (ipSourceSelector) {
                    ipSourceSelector.addEventListener('change', toggleIpSettings);
                    toggleIpSettings(); // 初始化
                }
            });
        </script>
JS;
    }

    /**
     * 生成安全的随机 HMAC 密钥
     *
     * @param int $length 密钥长度（字节）
     * @return string 十六进制编码的随机字符串
     */
    private static function generateStrongRandomKey(int $length): string
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes($length));
            } catch (Exception $e) {
                // Ignore
            }
        }
        // Fallback
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return hash('sha256', $randomString);
    }

    /**
     * 创建密码重置令牌表
     * 使用 IF NOT EXISTS 确保已存在时不会报错或覆盖
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

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `token` VARCHAR(64) NOT NULL,
            `uid` INT(10) NOT NULL,
            `created_at` INT(10) NOT NULL,
            `used` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`token`),
            INDEX `uid` (`uid`),
            INDEX `created_at` (`created_at`)
        )";

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
     * 使用 IF NOT EXISTS 确保已存在时不会报错或覆盖
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

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `ip` VARCHAR(45) NOT NULL,
            `attempts` INT(10) NOT NULL DEFAULT 0,
            `last_attempt` INT(10) NOT NULL,
            `locked_until` INT(10) NOT NULL DEFAULT 0,
            PRIMARY KEY (`ip`),
            INDEX `locked_until` (`locked_until`)
        )";

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

    /**
     * 获取日志表格的样式
     *
     * @return string CSS样式
     */
    private static function getLogTableStyles(): string
    {
        return <<<CSS
        <style>
            .passport-log-toolbar {
                background: #f5f5f5;
                padding: 12px 15px;
                border-radius: 4px;
                border: 1px solid #ddd;
                margin-bottom: 15px;
            }
            .passport-log-toolbar-inner {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .passport-log-toolbar .toolbar-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .passport-log-toolbar .toolbar-label {
                color: #666;
                font-size: 13px;
                white-space: nowrap;
            }
            .passport-log-toolbar input[type="text"],
            .passport-log-toolbar select {
                height: 28px;
                padding: 4px 8px;
                border: 1px solid #ccc;
                border-radius: 3px;
                font-size: 13px;
                color: #333;
                background: white;
                transition: all 0.2s;
            }
            .passport-log-toolbar input[type="text"]:focus,
            .passport-log-toolbar select:focus {
                outline: none;
                border-color: #5e6db3;
                box-shadow: 0 0 3px rgba(94, 109, 179, 0.3);
            }
            .passport-log-toolbar .btn {
                height: 28px;
                padding: 4px 12px;
                font-size: 13px;
                line-height: 18px;
            }
            .passport-log-pagination {
                background: #f9f9f9;
                padding: 10px 15px;
                border-radius: 4px;
                border: 1px solid #e5e5e5;
                margin-top: 15px;
            }
            .passport-log-pagination-inner {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .passport-log-pagination .pagination {
                display: flex;
                gap: 5px;
                align-items: center;
            }
            .passport-log-pagination .page-link {
                padding: 4px 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                text-decoration: none;
                color: #333;
                background: white;
                font-size: 12px;
                transition: all 0.2s;
                display: inline-block;
            }
            .passport-log-pagination .page-link:hover {
                background: #f0f0f0;
                border-color: #ccc;
                text-decoration: none;
            }
            .passport-log-pagination .page-link.active {
                background: #5e6db3;
                color: white;
                border-color: #5e6db3;
            }
            .passport-log-pagination .page-ellipsis {
                padding: 4px 8px;
                color: #999;
            }
            .passport-log-pagination .page-info {
                color: #666;
                font-size: 12px;
            }
            .passport-log-status-safe {
                color: #35a937;
                font-weight: 500;
            }
            .passport-log-status-locked {
                color: #ce5252;
                font-weight: bold;
            }
            .passport-log-status-expired {
                color: #999;
            }
            .passport-log-checkbox {
                cursor: pointer;
                width: 16px;
                height: 16px;
                margin: 0;
                vertical-align: middle;
            }
            .passport-log-table tbody tr:hover {
                background-color: #f9f9f9;
            }
            .passport-log-table tbody tr.selected {
                background-color: #e8f0fe;
            }
            .passport-log-table td {
                vertical-align: middle;
            }
            .passport-log-loading {
                text-align: center;
                padding: 40px 20px;
                color: #666;
                font-size: 14px;
            }
            .passport-log-loading::after {
                content: '...';
                animation: dots 1.5s steps(4, end) infinite;
            }
            @keyframes dots {
                0%, 20% { content: '.'; }
                40% { content: '..'; }
                60%, 100% { content: '...'; }
            }
            .passport-log-batch-actions {
                margin-top: 10px;
            }
            .passport-log-batch-actions .btn {
                margin-right: 8px;
            }
            @media (max-width: 768px) {
                .passport-log-toolbar-inner {
                    flex-direction: column;
                    align-items: stretch;
                }
                .passport-log-toolbar .toolbar-group {
                    flex-wrap: wrap;
                }
                .passport-log-toolbar input[type="text"],
                .passport-log-toolbar select {
                    flex: 1;
                    min-width: 120px;
                }
                .passport-log-pagination-inner {
                    flex-direction: column;
                    align-items: stretch;
                    text-align: center;
                }
                .passport-log-pagination .pagination {
                    justify-content: center;
                }
            }
        </style>
CSS;
    }

    /**
     * 获取日志表格的 JavaScript 脚本
     *
     * @param string $actionUrl 解封操作的目标 URL
     * @param int $pageSize 每页显示数量
     * @return string JavaScript代码
     */
    private static function getLogTableScripts(string $actionUrl, int $pageSize): string
    {
        return <<<JS
        <script>
        (function() {
            var currentPage = 1;
            var currentFilter = 'all';
            var currentSearch = '';
            var selectedIps = [];
            var isLoading = false;
            
            var actionUrl = '$actionUrl';
            var pageSize = $pageSize;
            
            var console = window.console || {
                log: function() {},
                error: function() {},
                warn: function() {}
            };
            
            function init() {
                try {
                    initEventListeners();
                } catch (e) {
                    console.error('初始化失败:', e);
                }
            }
            
            function initEventListeners() {
                var searchInput = document.getElementById('passport-log-search');
                var filterSelect = document.getElementById('passport-log-filter');
                var refreshBtn = document.getElementById('passport-log-refresh');
                var selectAllCheckbox = document.getElementById('passport-log-select-all');
                var batchUnblockBtn = document.getElementById('passport-log-batch-unblock');
                
                if (searchInput) {
                    addEvent(searchInput, 'input', debounce(function() {
                        currentSearch = this.value.trim();
                        currentPage = 1;
                        loadLogs();
                    }, 300));
                }
                
                if (filterSelect) {
                    addEvent(filterSelect, 'change', function() {
                        currentFilter = this.value;
                        currentPage = 1;
                        loadLogs();
                    });
                }
                
                if (refreshBtn) {
                    addEvent(refreshBtn, 'click', function(e) {
                        if (e) e.preventDefault();
                        currentPage = 1;
                        currentSearch = '';
                        currentFilter = 'all';
                        if (searchInput) searchInput.value = '';
                        if (filterSelect) filterSelect.value = 'all';
                        loadLogs();
                    });
                }
                
                if (selectAllCheckbox) {
                    addEvent(selectAllCheckbox, 'change', function() {
                        var checkboxes = document.querySelectorAll('.passport-log-checkbox');
                        for (var i = 0; i < checkboxes.length; i++) {
                            checkboxes[i].checked = this.checked;
                            toggleRowSelection(checkboxes[i]);
                        }
                        updateBatchButton();
                    });
                }
                
                var checkboxes = document.querySelectorAll('.passport-log-checkbox');
                for (var i = 0; i < checkboxes.length; i++) {
                    if (!checkboxes[i].id || checkboxes[i].id !== 'passport-log-select-all') {
                        addEvent(checkboxes[i], 'change', function() {
                            toggleRowSelection(this);
                            updateBatchButton();
                        });
                    }
                }
                
                if (batchUnblockBtn) {
                    addEvent(batchUnblockBtn, 'click', batchUnblock);
                }
                
                bindPaginationLinks();
                bindUnblockForms();
            }
            
            function bindPaginationLinks() {
                var links = document.querySelectorAll('.page-link');
                for (var i = 0; i < links.length; i++) {
                    addEvent(links[i], 'click', function(e) {
                        if (e) e.preventDefault();
                        var page = parseInt(this.getAttribute('data-page'));
                        if (page && !isNaN(page) && page !== currentPage) {
                            currentPage = page;
                            loadLogs();
                        }
                    });
                }
            }
            
            function bindUnblockForms() {
                var forms = document.querySelectorAll('.unblock-form');
                for (var i = 0; i < forms.length; i++) {
                    addEvent(forms[i], 'submit', function(e) {
                        if (e) e.preventDefault();
                        var ip = this.getAttribute('data-ip');
                        if (ip) {
                            unblockIp(ip, this);
                        }
                    });
                }
            }
            
            function addEvent(element, eventType, handler) {
                if (!element) return;
                
                if (element.addEventListener) {
                    element.addEventListener(eventType, handler, false);
                } else if (element.attachEvent) {
                    element.attachEvent('on' + eventType, handler);
                } else {
                    element['on' + eventType] = handler;
                }
            }
            
            function toggleRowSelection(checkbox) {
                var row = checkbox.closest ? checkbox.closest('tr') : getParentByTagName(checkbox, 'tr');
                if (row) {
                    if (checkbox.checked) {
                        addClass(row, 'selected');
                    } else {
                        removeClass(row, 'selected');
                    }
                }
            }
            
            function updateBatchButton() {
                var checkboxes = document.querySelectorAll('.passport-log-checkbox:checked');
                var batchBtn = document.getElementById('passport-log-batch-unblock');
                if (batchBtn) {
                    batchBtn.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
                }
            }
            
            function loadLogs() {
                if (isLoading) return;
                isLoading = true;
                
                var container = document.getElementById('passport-log-container');
                if (!container) {
                    isLoading = false;
                    return;
                }
                
                var tbody = container.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="passport-log-loading">加载中</td></tr>';
                }
                
                var params = 'action=passport_load_logs' +
                    '&page=' + encodeURIComponent(currentPage) +
                    '&filter=' + encodeURIComponent(currentFilter) +
                    '&search=' + encodeURIComponent(currentSearch) +
                    '&pageSize=' + encodeURIComponent(pageSize);
                
                sendAjaxRequest(actionUrl, params, function(data) {
                    isLoading = false;
                    if (data && data.success) {
                        updateTable(data.data);
                    } else {
                        showError(data && data.message ? data.message : '加载失败');
                    }
                }, function(error) {
                    isLoading = false;
                    showError('网络错误：' + (error && error.message ? error.message : '未知错误'));
                });
            }
            
            function sendAjaxRequest(url, params, successCallback, errorCallback) {
                var xhr = createXHR();
                if (!xhr) {
                    if (errorCallback) {
                        errorCallback({ message: '浏览器不支持 AJAX' });
                    }
                    return;
                }
                
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (successCallback) {
                                    successCallback(data);
                                }
                            } catch (e) {
                                if (errorCallback) {
                                    errorCallback({ message: '解析响应失败: ' + e.message });
                                }
                            }
                        } else {
                            if (errorCallback) {
                                errorCallback({ message: '请求失败，状态码: ' + xhr.status });
                            }
                        }
                    }
                };
                
                xhr.onerror = function() {
                    if (errorCallback) {
                        errorCallback({ message: '网络错误' });
                    }
                };
                
                xhr.ontimeout = function() {
                    if (errorCallback) {
                        errorCallback({ message: '请求超时' });
                    }
                };
                
                xhr.timeout = 30000;
                xhr.send(params);
            }
            
            function createXHR() {
                var xhr = null;
                if (window.XMLHttpRequest) {
                    xhr = new XMLHttpRequest();
                } else if (window.ActiveXObject) {
                    try {
                        xhr = new ActiveXObject('Msxml2.XMLHTTP');
                    } catch (e) {
                        try {
                            xhr = new ActiveXObject('Microsoft.XMLHTTP');
                        } catch (e) {
                            xhr = null;
                        }
                    }
                }
                return xhr;
            }
            
            function updateTable(data) {
                var container = document.getElementById('passport-log-container');
                if (!container || !data) return;
                
                var tbody = container.querySelector('tbody');
                if (tbody && data.logs) {
                    if (!data.logs || data.logs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6"><h6 class="typecho-list-table-title">没有找到匹配的记录</h6></td></tr>';
                    } else {
                        var html = '';
                        for (var i = 0; i < data.logs.length; i++) {
                            html += renderLogRow(data.logs[i]);
                        }
                        tbody.innerHTML = html;
                    }
                }
                
                var pagination = container.querySelector('.passport-log-pagination');
                if (pagination && data.pagination) {
                    var paginationInner = pagination.querySelector('.passport-log-pagination-inner');
                    if (paginationInner) {
                        paginationInner.innerHTML = renderPagination(data.pagination);
                    }
                }
                
                bindPaginationLinks();
                bindUnblockForms();
                updateBatchButton();
            }
            
            function renderLogRow(log) {
                if (!log) return '';
                
                var statusClass = 'safe';
                var statusText = '安全';
                var action = '<span>-</span>';
                
                if (log.locked_until > 0) {
                    var now = Math.floor(Date.now() / 1000);
                    if (log.locked_until > now) {
                        var remaining = log.locked_until - now;
                        var minutes = Math.ceil(remaining / 60);
                        statusClass = 'locked';
                        statusText = '封禁中 (剩余约 ' + minutes + ' 分钟)';
                        action = '<form method="post" action="' + actionUrl + '" style="margin:0; padding:0;" class="unblock-form" data-ip="' + escapeHtml(log.ip) + '">' +
                                 '<input type="hidden" name="unblock_ip" value="' + escapeHtml(log.ip) + '">' +
                                 '<button type="submit" class="btn btn-s btn-warn">立即解封</button>' +
                                 '</form>';
                    } else {
                        statusClass = 'expired';
                        statusText = '封禁已过期';
                    }
                }
                
                return '<tr data-status="' + statusClass + '" data-ip="' + escapeHtml(log.ip) + '">' +
                       '<td><input type="checkbox" class="passport-log-checkbox" value="' + escapeHtml(log.ip) + '"></td>' +
                       '<td>' + escapeHtml(log.ip) + '</td>' +
                       '<td>' + (log.attempts || 0) + '</td>' +
                       '<td>' + formatTimestamp(log.last_attempt) + '</td>' +
                       '<td><span class="passport-log-status passport-log-status-' + statusClass + '">' + escapeHtml(statusText) + '</span></td>' +
                       '<td>' + action + '</td>' +
                       '</tr>';
            }
            
            function renderPagination(pagination) {
                if (!pagination) return '';
                
                var html = '<div class="page-info">共 ' + (pagination.total || 0) + ' 条记录</div>';
                
                if (pagination.totalPages > 1) {
                    html += '<div class="pagination">';
                    
                    if (pagination.page > 1) {
                        html += '<a href="#" class="page-link" data-page="' + (pagination.page - 1) + '">&laquo; 上一页</a>';
                    }
                    
                    var startPage = Math.max(1, pagination.page - 2);
                    var endPage = Math.min(pagination.totalPages, pagination.page + 2);
                    
                    if (startPage > 1) {
                        html += '<a href="#" class="page-link" data-page="1">1</a>';
                        if (startPage > 2) {
                            html += '<span class="page-ellipsis">...</span>';
                        }
                    }
                    
                    for (var i = startPage; i <= endPage; i++) {
                        var activeClass = i === pagination.page ? ' active' : '';
                        html += '<a href="#" class="page-link' + activeClass + '" data-page="' + i + '">' + i + '</a>';
                    }
                    
                    if (endPage < pagination.totalPages) {
                        if (endPage < pagination.totalPages - 1) {
                            html += '<span class="page-ellipsis">...</span>';
                        }
                        html += '<a href="#" class="page-link" data-page="' + pagination.totalPages + '">' + pagination.totalPages + '</a>';
                    }
                    
                    if (pagination.page < pagination.totalPages) {
                        html += '<a href="#" class="page-link" data-page="' + (pagination.page + 1) + '">下一页 &raquo;</a>';
                    }
                    
                    html += '</div>';
                }
                
                html += '<div class="page-info">每页 ' + (pagination.pageSize || pageSize) + ' 条</div>';
                return html;
            }
            
            function unblockIp(ip, form) {
                var params = 'action=passport_unblock_ip' +
                    '&ip=' + encodeURIComponent(ip);
                
                sendAjaxRequest(actionUrl, params, function(data) {
                    if (data && data.success) {
                        loadLogs();
                    } else {
                        alert(data && data.message ? data.message : '解封失败');
                    }
                }, function(error) {
                    alert('网络错误：' + (error && error.message ? error.message : '未知错误'));
                });
            }
            
            function batchUnblock() {
                var checkboxes = document.querySelectorAll('.passport-log-checkbox:checked');
                if (!checkboxes || checkboxes.length === 0) {
                    alert('请选择要解封的 IP');
                    return;
                }
                
                if (!confirm('确定要解封选中的 ' + checkboxes.length + ' 个 IP 吗？')) {
                    return;
                }
                
                var ips = [];
                for (var i = 0; i < checkboxes.length; i++) {
                    if (checkboxes[i].value) {
                        ips.push(checkboxes[i].value);
                    }
                }
                
                var params = 'action=passport_batch_unblock' +
                    '&ips=' + encodeURIComponent(ips.join(','));
                
                sendAjaxRequest(actionUrl, params, function(data) {
                    if (data && data.success) {
                        loadLogs();
                    } else {
                        alert(data && data.message ? data.message : '批量解封失败');
                    }
                }, function(error) {
                    alert('网络错误：' + (error && error.message ? error.message : '未知错误'));
                });
            }
            
            function formatTimestamp(timestamp) {
                if (!timestamp) return '-';
                
                var date = new Date(timestamp * 1000);
                if (isNaN(date.getTime())) return '-';
                
                var year = date.getFullYear();
                var month = padZero(date.getMonth() + 1);
                var day = padZero(date.getDate());
                var hours = padZero(date.getHours());
                var minutes = padZero(date.getMinutes());
                var seconds = padZero(date.getSeconds());
                
                return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
            }
            
            function padZero(num) {
                return num < 10 ? '0' + num : num;
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function showError(message) {
                var tbody = document.querySelector('#passport-log-table tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" style="color: #ce5252; text-align: center; padding: 20px;">' + escapeHtml(message) + '</td></tr>';
                }
            }
            
            function debounce(func, wait) {
                var timeout;
                return function() {
                    var context = this;
                    var args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        func.apply(context, args);
                    }, wait);
                };
            }
            
            function addClass(element, className) {
                if (!element) return;
                if (element.classList) {
                    element.classList.add(className);
                } else {
                    var classes = element.className.split(' ');
                    if (classes.indexOf(className) === -1) {
                        classes.push(className);
                    }
                    element.className = classes.join(' ');
                }
            }
            
            function removeClass(element, className) {
                if (!element) return;
                if (element.classList) {
                    element.classList.remove(className);
                } else {
                    var classes = element.className.split(' ');
                    var index = classes.indexOf(className);
                    if (index !== -1) {
                        classes.splice(index, 1);
                    }
                    element.className = classes.join(' ');
                }
            }
            
            function getParentByTagName(element, tagName) {
                if (!element) return null;
                tagName = tagName.toLowerCase();
                var parent = element.parentNode;
                while (parent && parent.tagName) {
                    if (parent.tagName.toLowerCase() === tagName) {
                        return parent;
                    }
                    parent = parent.parentNode;
                }
                return null;
            }
            
            if (document.readyState === 'loading') {
                addEvent(document, 'DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
JS;
    }
}