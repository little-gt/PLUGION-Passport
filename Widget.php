<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 找回密码核心逻辑类 - Passport
 *
 * 负责处理前端路由请求、验证码生成与校验、邮件发送、
 * IP 速率限制及数据交互逻辑。
 *
 * @package Passport
 * @author GARFIELDTOM
 * @copyright Copyright (c) 2025 GARFIELDTOM & 小否先生
 * @version 0.1.5
 * @link https://garfieldtom.cool/
 * @license GNU General Public License 2.0
 */

// 引入 PHPMailer 依赖
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Typecho\Common;
use Typecho\Db\Exception as DbException;
use Typecho\Widget;
use Typecho\Http\Client as HttpClient;
use Typecho\Http\Client\Exception as HttpClientException;
use Typecho\Widget\Exception as WidgetException;
use Utils\PasswordHash;
use Widget\ActionInterface;
use Widget\Notice;

class Passport_Widget extends Widget implements ActionInterface
{
    /**
     * @var Widget_Options Typecho 配置组件
     */
    private $options;

    /**
     * @var mixed 插件私有配置
     */
    private $config;

    /**
     * @var Notice 通知组件
     */
    private $notice;

    /**
     * @var Typecho_Db 数据库实例
     */
    private $db;

    /**
     * 构造函数
     *
     * @param mixed $request
     * @param mixed $response
     * @param mixed $params
     */
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->notice = Notice::alloc();
        $this->options = Widget::widget('Widget_Options');
        $this->config = $this->options->plugin('Passport');
        $this->db = Typecho_Db::get();
    }

    /**
     * [Action 接口实现] 处理后台动作
     * 目前用于后台手动解封 IP 和 AJAX 日志管理
     *
     * @return void
     * @throws DbException
     */
    public function action()
    {
        // 鉴权：仅管理员可操作
        $this->user->pass('administrator');
        $security = Widget::widget('Widget_Security');
        $security->protect();

        if ($this->request->isPost()) {
            $action = $this->request->get('action');

            switch ($action) {
                case 'passport_load_logs':
                    $this->handleLoadLogs();
                    return;

                case 'passport_unblock_ip':
                    $this->handleAjaxUnblockIp();
                    return;

                case 'passport_batch_unblock':
                    $this->handleBatchUnblock();
                    return;

                default:
                    if (!empty($this->request->unblock_ip)) {
                        $this->handleUnblockIp((string) $this->request->unblock_ip);
                    }
                    break;
            }
        }

        $this->response->goBack();
    }

    /**
     * [验证码] 生成并输出内置验证码图片
     *
     * 路由: /passport/captcha
     * 使用 PHP GD 库生成，直接输出 PNG 图片流并终止脚本。
     *
     * @return void
     */
    public function renderCaptcha()
    {
        // 确保 Session 开启以存储验证码
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $width = 120;
        $height = 40;
        $image = imagecreatetruecolor($width, $height);

        // 背景填充
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);

        // 生成随机字符 (去除易混淆字符)
        $charset = '23456789abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $charset[mt_rand(0, strlen($charset) - 1)];
        }

        // 存入 Session (小写存储，不区分大小写)
        $_SESSION['passport_captcha_code'] = strtolower($code);

        // 添加干扰点
        for ($i = 0; $i < 100; $i++) {
            $pointColor = imagecolorallocate($image, mt_rand(50, 200), mt_rand(50, 200), mt_rand(50, 200));
            imagesetpixel($image, mt_rand(1, $width - 1), mt_rand(1, $height - 1), $pointColor);
        }

        // 添加干扰线
        for ($i = 0; $i < 3; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(80, 220), mt_rand(80, 220), mt_rand(80, 220));
            imageline($image, mt_rand(1, $width - 1), mt_rand(1, $height - 1), mt_rand(1, $width - 1), mt_rand(1, $height - 1), $lineColor);
        }

        // 写入文字 (分散排列，位置随机波动)
        for ($i = 0; $i < strlen($code); $i++) {
            $textColor = imagecolorallocate($image, mt_rand(0, 100), mt_rand(0, 150), mt_rand(0, 200));
            $x = ($i * 120 / 4) + mt_rand(5, 10);
            $y = mt_rand(5, 20);
            imagechar($image, 5, $x, $y, $code[$i], $textColor);
        }

        // 输出图片头和内容
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        imagepng($image);
        imagedestroy($image);
        exit; // 终止后续输出
    }

    /**
     * [页面逻辑] 忘记密码处理
     */
    public function doForgot()
    {
        // 导入模板
        require_once 'template/forgot.php';

        // 清理数据库中的过期令牌
        $this->cleanTokens();

        if ($this->request->isPost()) {
            try {
                // 1. 速率限制检查
                $this->handleRateLimiting();

                // 2. 表单验证
                if ($error = $this->forgotForm()->validate()) {
                    $this->pushNotice($error, 'error');
                    return;
                }

                // 4. 人机验证
                if (!$this->verifyCaptcha()) {
                    $this->pushNotice(_t('验证码错误或已失效，请重试。'), 'error');
                    return;
                }

                // 4. 业务逻辑：查找用户并发送邮件
                $mail = $this->request->filter('trim')->mail;
                $user = $this->db->fetchRow($this->db->select()->from('table.users')->where('mail = ?', $mail));

                $mailSentSuccessfully = true;

                // 防范用户枚举：无论用户是否存在，表面流程一致
                if (!empty($user)) {
                    $createdAt = $this->options->gmtTime;
                    $uid = (int)$user['uid'];
                    
                    // 检查该用户是否已有未使用的 token（只查询最近 24 小时）
                    $existingToken = $this->db->fetchRow($this->db->select()->from('table.password_reset_tokens')
                        ->where('uid = ? AND used = ? AND created_at > ?', $uid, 0, $createdAt - 86400));
                    
                    if (!empty($existingToken)) {
                        // 如果有未过期的未使用 token，使用现有的
                        $token = $existingToken['token'];
                        $signature = $this->generateSignature($token, $uid, (int)$existingToken['created_at']);
                    } else {
                        // 否则生成新的 token（包含时间因素，确保不重复）
                        $token = $this->generateTimeBasedToken($uid, $createdAt);
                        $signature = $this->generateSignature($token, $uid, $createdAt);
                        
                        $this->db->query($this->db->insert('table.password_reset_tokens')->rows([
                            'token' => $token, 
                            'uid' => $uid, 
                            'created_at' => $createdAt, 
                            'used' => 0
                        ]));
                    }

                    $resetLink = Common::url('/passport/reset?token=' . urlencode($token) . '&signature=' . urlencode($signature), $this->options->index);

                    if (!$this->sendResetEmail($user, $resetLink)) {
                         error_log('Passport: Email send failed to: ' . $user['mail']);
                         $mailSentSuccessfully = false;
                    }
                }

                if ($mailSentSuccessfully) {
                    $this->pushNotice(_t('如果您的邮箱在系统中存在，一封包含重置链接的邮件已发送，请查收。'), 'success');
                } else {
                    // 发送失败属于系统问题，不计入用户的错误尝试次数
                    $this->decrementAttemptCounter();
                    $this->pushNotice(_t('邮件发送服务暂不可用，请联系管理员。'), 'error');
                }

            } catch (WidgetException $e) {
                // 捕获速率限制抛出的特定异常码 429
                if ($e->getCode() === 429) {
                    $this->pushRateLimitNotice((int) $e->getMessage());
                } else {
                    $this->pushNotice($e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * [页面逻辑] 重置密码处理
     */
    public function doReset()
    {
        require_once 'template/reset.php';
        $this->cleanTokens();

        // 获取参数
        $token = $this->request->filter('strip_tags', 'trim')->token;
        $signature = $this->request->filter('strip_tags', 'trim')->signature;

        // 基础参数校验
        if (empty($token) || empty($signature)) {
            $this->pushNotice(_t('无效的重置链接'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 令牌查询
        $tokenRecord = $this->db->fetchRow($this->db->select()->from('table.password_reset_tokens')
            ->where('token = ? AND used = ?', $token, 0));

        // 令牌有效性检查 (过期时间: 1小时)
        if (empty($tokenRecord) || ($this->options->gmtTime - $tokenRecord['created_at']) > 3600) {
            $this->pushNotice(_t('链接已失效或已使用，请重新申请。'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        // 签名安全校验
        if (!$this->verifySignature($token, (int)$tokenRecord['uid'], (int)$tokenRecord['created_at'], $signature)) {
            $this->pushNotice(_t('安全签名验证失败，链接非法。'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        if ($this->request->isPost()) {
             try {
                // 1. 速率限制
                $this->handleRateLimiting();

                // 2. 表单校验
                if ($error = $this->resetForm()->validate()) {
                    $this->pushNotice($error, 'error');
                    return;
                }

                // 3. 密码复杂度校验
                $password = (string) $this->request->password;
                $complexityCheck = $this->validatePasswordComplexity($password);
                if ($complexityCheck !== true) {
                    $this->pushNotice($complexityCheck, 'error');
                    return;
                }

                // 4. 人机验证
                if (!$this->verifyCaptcha()) {
                    $this->pushNotice(_t('验证码错误或已失效，请重试。'), 'error');
                    return;
                }

                // 5. 执行重置
                $hasher = new PasswordHash(8, true);
                $passwordHash = $hasher->hashPassword($password);
                
                // 更新用户密码
                $this->db->query($this->db->update('table.users')
                    ->rows(['password' => $passwordHash])
                    ->where('uid = ?', $tokenRecord['uid']));
                
                // 标记 token 为已使用，保留记录以便审计
                $this->db->query($this->db->update('table.password_reset_tokens')
                    ->rows(['used' => 1])
                    ->where('token = ?', $token));

                $this->pushNotice(_t('密码重置成功，请使用新密码登录。'), 'success');
                $this->response->redirect($this->options->loginUrl);

            } catch (WidgetException $e) {
                if ($e->getCode() === 429) {
                    $this->pushRateLimitNotice((int) $e->getMessage());
                } else {
                    $this->pushNotice($e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * 统一的通知推送方法
     * 替代原有的 _setNotice，参数更加规范。
     *
     * @param string|array $message 消息内容
     * @param string $type 消息类型 (error, success, notice)
     */
    private function pushNotice($message, string $type = 'notice')
    {
        // 处理数组形式的错误消息（如表单验证返回的数组）
        if (is_array($message)) {
            $message = reset($message); // 取第一条
        }
        
        // 确保是字符串
        if (!is_string($message)) {
            $message = _t('未知错误');
        }

        $this->notice->set($message, $type);
    }

    /**
     * 专门用于速率限制（封禁）的通知推送
     * 生成带有倒计时 JS 的 HTML 提示。
     *
     * @param int $seconds 剩余封禁秒数
     */
    private function pushRateLimitNotice(int $seconds)
    {
        if ($seconds <= 0) {
            $this->pushNotice(_t('请求过于频繁，请稍后重试。'), 'error');
            return;
        }

        $minutes = floor($seconds / 60);
        $sec_part = $seconds % 60;

        $msg = sprintf(
            _t('您的请求过于频繁，已被暂时限制。请在 <span id="countdown-min">%d</span> 分 <span id="countdown-sec">%02d</span> 秒后重试。'),
            $minutes, $sec_part
        );

        // 嵌入简单的倒计时脚本
        $msg .= "<script>
            (function() {
                var s = {$seconds};
                var mEl = document.getElementById('countdown-min');
                var sEl = document.getElementById('countdown-sec');
                if(!mEl || !sEl) return;
                var timer = setInterval(function(){
                    s--;
                    if(s<=0){
                        clearInterval(timer);
                        mEl.parentNode.innerHTML = '" . _t('现在可以刷新页面重试了。') . "';
                        return;
                    }
                    mEl.innerText = Math.floor(s/60);
                    var rs = s%60;
                    sEl.innerText = rs<10?'0'+rs:rs;
                }, 1000);
            })();
        </script>";

        $this->notice->set($msg, 'error');
    }

    /**
     * 智能获取客户端 IP
     * 根据 Plugin.php 配置的策略获取 IP，支持反向代理。
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $strategy = $this->config->ipSource ?? 'default';
        $ip = '';

        switch ($strategy) {
            case 'custom':
                // 从自定义 Header 获取
                $header = strtoupper($this->config->customIpHeader ?? '');
                if (!empty($header) && isset($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                }
                break;

            case 'proxy':
                // 尝试标准的代理 Header
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // 可能包含多个IP，取第一个
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $ip = trim($ips[0]);
                } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                }
                break;

            case 'default':
            default:
                // 使用 Typecho 默认机制 (通常是 REMOTE_ADDR)
                $ip = $this->request->getIp();
                break;
        }

        // 如果上述方法都失败，回退到 request->getIp()
        if (empty($ip)) {
            $ip = $this->request->getIp();
        }

        // 基础清洗，防止注入
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * [核心逻辑] 处理请求速率限制
     * 检查 IP 记录，更新计数，若超过阈值则抛出异常。
     *
     * @throws WidgetException 如果被封禁，抛出异常码 429，消息为剩余秒数
     */
    private function handleRateLimiting()
    {
        if (empty($this->config->enableRateLimit) || !$this->config->enableRateLimit) {
            return;
        }

        $ip = $this->getClientIp(); // 使用新的 IP 获取方法
        $now = time();
        $failTable = $this->db->getPrefix() . 'passport_fails';

        // 查询记录
        $log = $this->db->fetchRow($this->db->select()->from($failTable)->where('ip = ?', $ip));

        if ($log) {
            // 检查是否在封禁期
            if ($log['locked_until'] > $now) {
                // 抛出异常，Code 429 表示 Too Many Requests
                throw new WidgetException((string)($log['locked_until'] - $now), 429);
            }

            // 更新计数逻辑：若距离上次尝试超过10分钟，计数重置
            $attempts = (($now - $log['last_attempt']) > 600) ? 1 : $log['attempts'] + 1;
            
            $updateData = ['attempts' => $attempts, 'last_attempt' => $now];

            // 阈值判定：每 20 次尝试触发封禁
            if ($attempts % 20 == 0) {
                $lockDuration = 300; // 封禁 5 分钟
                $updateData['locked_until'] = $now + $lockDuration;
                
                $this->db->query($this->db->update($failTable)->rows($updateData)->where('ip = ?', $ip));
                
                throw new WidgetException((string)$lockDuration, 429);
            } else {
                $this->db->query($this->db->update($failTable)->rows($updateData)->where('ip = ?', $ip));
            }
        } else {
            // 首次记录
            $this->db->query($this->db->insert($failTable)->rows([
                'ip' => $ip,
                'attempts' => 1,
                'last_attempt' => $now,
                'locked_until' => 0
            ]));
        }
    }

    /**
     * 减少尝试计数器
     * 用于系统级错误（如邮件发送失败），避免误伤用户。
     */
    private function decrementAttemptCounter()
    {
        if (empty($this->config->enableRateLimit) || !$this->config->enableRateLimit) {
            return;
        }
        $ip = $this->getClientIp();
        $failTable = $this->db->getPrefix() . 'passport_fails';
        $log = $this->db->fetchRow($this->db->select()->from($failTable)->where('ip = ?', $ip));

        if ($log && $log['attempts'] > 0) {
            $this->db->query($this->db->update($failTable)
                ->rows(['attempts' => $log['attempts'] - 1])
                ->where('ip = ?', $ip));
        }
    }

    /**
     * [验证码] 统一的人机验证逻辑
     * 支持 Default(内置), reCAPTCHA, hCaptcha, Geetest
     *
     * @return bool 验证通过返回 true
     */
    private function verifyCaptcha(): bool
    {
        $captchaType = $this->config->captchaType ?? 'default';
        
        // 理论上不允许 none，但为了兼容性保留判断
        if ($captchaType === 'none') return true;

        try {
            switch ($captchaType) {
                case 'default':
                    return $this->verifyDefaultCaptcha();
                
                case 'recaptcha':
                    if (empty($this->config->secretkeyRecaptcha)) throw new PHPMailerException(_t('reCAPTCHA 配置缺失'));
                    return $this->verifyRecaptcha(
                        (string) $this->request->get('g-recaptcha-response'), 
                        (string) $this->config->secretkeyRecaptcha
                    );

                case 'hcaptcha':
                    if (empty($this->config->secretkeyHcaptcha)) throw new PHPMailerException(_t('hCaptcha 配置缺失'));
                    return $this->verifyHcaptcha(
                        (string) $this->request->get('h-captcha-response'), 
                        (string) $this->config->secretkeyHcaptcha
                    );

                case 'geetest':
                    if (empty($this->config->captchaKeyGeetest)) throw new PHPMailerException(_t('Geetest 配置缺失'));
                    return $this->verifyGeetest(
                        (string) $this->request->get('lot_number'), 
                        (string) $this->request->get('captcha_output'),
                        (string) $this->request->get('pass_token'), 
                        (string) $this->request->get('gen_time')
                    );
                
                default:
                    // 默认为真，避免未知的配置导致死循环
                    return true;
            }
        } catch (PHPMailerException $e) {
            $this->pushNotice($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * [验证码] 校验内置图片验证码
     *
     * @return bool
     * @throws PHPMailerException
     */
    private function verifyDefaultCaptcha(): bool
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $inputCode = strtolower(trim($this->request->get('captcha', '')));
        $sessionCode = $_SESSION['passport_captcha_code'] ?? '';

        // 必须在校验后立即销毁 Session 中的验证码，防止重放
        unset($_SESSION['passport_captcha_code']);

        if (empty($inputCode) || empty($sessionCode)) {
            return false;
        }

        if ($inputCode !== $sessionCode) {
            return false;
        }

        return true;
    }

    /**
     * 后台解封 IP 的具体实现
     *
     * @param string $ip
     */
    private function handleUnblockIp(string $ip)
    {
        if (!$this->isValidIp($ip)) {
            $this->notice->set(_t('IP 地址格式不正确。'), 'error');
            return;
        }

        $this->db->query($this->db->update($this->db->getPrefix() . 'passport_fails')
            ->rows(['locked_until' => 0])
            ->where('ip = ?', $ip));

        $this->notice->set(_t('IP [%s] 已解封。', htmlspecialchars($ip)), 'success');
    }

    /**
     * AJAX 加载日志数据
     *
     * @return void
     */
    private function handleLoadLogs()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $filter = $this->request->get('filter', 'all');
        $search = $this->request->get('search', '');
        $pageSize = max(10, min(100, (int) $this->request->get('pageSize', 25)));

        $prefix = $this->db->getPrefix();
        $now = time();

        try {
            $query = $this->db->select()->from("{$prefix}passport_fails");

            // 搜索过滤
            if (!empty($search)) {
                $query->where('ip LIKE ?', '%' . $search . '%');
            }

            // 状态过滤
            switch ($filter) {
                case 'locked':
                    $query->where('locked_until > ?', $now);
                    break;
                case 'safe':
                    $query->where('locked_until = ?', 0);
                    break;
                case 'expired':
                    $query->where('locked_until > ?', 0)
                          ->where('locked_until < ?', $now);
                    break;
            }

            // 获取总数
            $totalQuery = clone $query;
            $totalResult = $this->db->fetchRow($totalQuery->select('COUNT(*) as total'));
            $total = isset($totalResult['total']) ? (int) $totalResult['total'] : 0;

            // 获取分页数据
            $offset = ($page - 1) * $pageSize;
            $logs = $this->db->fetchAll($query
                ->order("{$prefix}passport_fails.last_attempt", Typecho_Db::SORT_DESC)
                ->limit($pageSize)
                ->offset($offset));

            $this->response->setStatus(200);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'pagination' => [
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'total' => $total,
                        'totalPages' => (int) ceil($total / $pageSize)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->response->setStatus(500);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * AJAX 解封单个 IP
     *
     * @return void
     */
    private function handleAjaxUnblockIp()
    {
        $ip = $this->request->get('ip');

        if (!$this->isValidIp($ip)) {
            $this->response->setStatus(400);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => _t('IP 地址格式不正确。')
            ]);
            exit;
        }

        try {
            $prefix = $this->db->getPrefix();
            $this->db->query($this->db->update("{$prefix}passport_fails")
                ->rows(['locked_until' => 0])
                ->where('ip = ?', $ip));

            $this->response->setStatus(200);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => true,
                'message' => _t('IP [%s] 已解封。', htmlspecialchars($ip))
            ]);
        } catch (Exception $e) {
            $this->response->setStatus(500);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * AJAX 批量解封 IP
     *
     * @return void
     */
    private function handleBatchUnblock()
    {
        $ipsString = $this->request->get('ips', '');
        if (empty($ipsString)) {
            $this->response->setStatus(400);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => _t('请选择要解封的 IP。')
            ]);
            exit;
        }

        $ips = array_filter(array_map('trim', explode(',', $ipsString)));
        $validIps = [];
        $invalidIps = [];

        foreach ($ips as $ip) {
            if ($this->isValidIp($ip)) {
                $validIps[] = $ip;
            } else {
                $invalidIps[] = $ip;
            }
        }

        if (empty($validIps)) {
            $this->response->setStatus(400);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => _t('没有有效的 IP 地址。')
            ]);
            exit;
        }

        try {
            $prefix = $this->db->getPrefix();
            $placeholders = str_repeat('?,', count($validIps) - 1) . '?';
            
            $this->db->query($this->db->update("{$prefix}passport_fails")
                ->rows(['locked_until' => 0])
                ->where("ip IN ($placeholders)", ...$validIps));

            $message = _t('已成功解封 %d 个 IP。', count($validIps));
            if (!empty($invalidIps)) {
                $message .= ' ' . _t('跳过 %d 个无效的 IP。', count($invalidIps));
            }

            $this->response->setStatus(200);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } catch (Exception $e) {
            $this->response->setStatus(500);
            $this->response->setContentType('application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    // --- 辅助方法 ---

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    private function generateSignature(string $token, int $uid, int $createdAt): string
    {
        $secretKey = $this->config->secretKey;
        if (empty($secretKey)) return '';
        // HMAC 签名包含 token、uid 和创建时间，确保时间因素参与
        return hash_hmac('sha256', "$token.$uid.$createdAt", $secretKey);
    }

    private function generateTimeBasedToken(int $uid, int $createdAt): string
    {
        // 生成包含时间因素的 token，确保不重复
        // 使用：UID + 时间戳 + 随机字符串的组合
        $timePart = dechex($createdAt);
        $uidPart = dechex($uid);
        $randomPart = Common::randString(32);
        
        // 组合并哈希，确保唯一性和安全性
        $combined = $timePart . $uidPart . $randomPart;
        return hash('sha256', $combined);
    }

    private function verifySignature(string $token, int $uid, int $createdAt, string $signature): bool
    {
        $secretKey = $this->config->secretKey;
        if (empty($secretKey)) return false;
        $expected = $this->generateSignature($token, $uid, $createdAt);
        return hash_equals($expected, $signature);
    }

    private function cleanTokens()
    {
        $expire = $this->options->gmtTime - 3600;
        
        // 清理过期的未使用 token（1小时过期）
        $this->db->query($this->db->delete('table.password_reset_tokens')
            ->where('created_at < ? AND used = ?', $expire, 0));
        
        // 根据配置清理已使用的 token
        $retentionDays = 30;
        if (isset($this->config->tokenRetentionDays) && is_numeric($this->config->tokenRetentionDays)) {
            $retentionDays = (int)$this->config->tokenRetentionDays;
        }
        
        if ($retentionDays > 0) {
            $retentionExpire = $this->options->gmtTime - ($retentionDays * 86400);
            $this->db->query($this->db->delete('table.password_reset_tokens')
                ->where('created_at < ? AND used = ?', $retentionExpire, 1));
        }
    }

    private function validatePasswordComplexity(string $password): bool|string
    {
        $errors = [];
        if (Common::strLen($password) < 8) $errors[] = _t('长度至少8位');
        if (!preg_match('/[A-Z]/', $password)) $errors[] = _t('需包含大写字母');
        if (!preg_match('/[a-z]/', $password)) $errors[] = _t('需包含小写字母');
        if (!preg_match('/[0-9]/', $password)) $errors[] = _t('需包含数字');
        if (!preg_match('/[\W_]/', $password)) $errors[] = _t('需包含特殊字符');

        return empty($errors) ? true : implode('，', $errors) . '。';
    }

    // --- 表单生成 ---

    public function forgotForm(): Typecho_Widget_Helper_Form
    {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);
        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, NULL, _t('邮箱'), NULL);
        $form->addInput($mail);
        $mail->addRule('required', _t('请填写邮箱'));
        $mail->addRule('email', _t('邮箱格式错误'));
        return $form;
    }

    public function resetForm(): Typecho_Widget_Helper_Form
    {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);
        $pwd = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('新密码'), NULL);
        $form->addInput($pwd);
        $confirm = new Typecho_Widget_Helper_Form_Element_Password('confirm', NULL, NULL, _t('确认密码'), NULL);
        $form->addInput($confirm);
        $pwd->addRule('required', _t('请填写密码'));
        $confirm->addRule('confirm', _t('两次密码不一致'), 'password');
        return $form;
    }

    // --- 第三方验证码实现 ---

    private function verifyRecaptcha(string $response, string $secret): bool
    {
        return $this->httpCheck('https://www.recaptcha.net/recaptcha/api/siteverify', ['secret' => $secret, 'response' => $response]);
    }

    private function verifyHcaptcha(string $response, string $secret): bool
    {
        return $this->httpCheck('https://hcaptcha.com/siteverify', ['secret' => $secret, 'response' => $response]);
    }

    private function verifyGeetest(string $lot, string $output, string $pass, string $time): bool
    {
        if (empty($lot) || empty($output) || empty($pass) || empty($time)) return false;
        
        $sign = hash_hmac('sha256', $lot, (string) ($this->config->captchaKeyGeetest ?? ''));
        $url = 'https://gcaptcha4.geetest.com/validate?captcha_id=' . ($this->config->captchaIdGeetest ?? '');
        
        $json = $this->httpRequest($url, [
            "lot_number" => $lot, "captcha_output" => $output,
            "pass_token" => $pass, "gen_time" => $time, "sign_token" => $sign
        ]);
        
        if (!$json) return false;
        $res = json_decode($json, true);
        return isset($res['status']) && $res['status'] === 'success' && $res['result'] === 'success';
    }

    /**
     * 通用的 HTTP 验证请求检查 (返回布尔值)
     */
    private function httpCheck(string $url, array $data): bool
    {
        if (empty($data['response'])) return false;
        $json = $this->httpRequest($url, $data);
        if (!$json) return false;
        $res = json_decode($json, true);
        return isset($res['success']) && $res['success'];
    }

    /**
     * 基础 HTTP 请求封装 (Typecho HttpClient)
     */
    private function httpRequest(string $url, array $data): string|false
    {
        $client = HttpClient::get();
        if (!$client) return false;
        try {
            $client->setTimeout(10);
            $client->setData($data);
            $client->send($url);
            return $client->getResponseStatus() === 200 ? $client->getResponseBody() : false;
        } catch (HttpClientException $e) {
            error_log("Passport HTTP Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送邮件
     */
    private function sendResetEmail(array $user, string $url): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = "UTF-8";
            $mail->setLanguage('zh_cn', __DIR__ . '/PHPMailer/language/');
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = (string) ($this->config->host ?? 'smtp.example.com');
            $mail->SMTPSecure = (string) ($this->config->secure ?? 'ssl') === 'none' ? '' : ($this->config->secure ?? 'ssl');
            $mail->SMTPAuth = true;
            $mail->Username = (string) ($this->config->username ?? '');
            $mail->Password = (string) ($this->config->password ?? '');
            $mail->Port = (int) ($this->config->port ?? 465);

            $mail->setFrom((string) ($this->config->username ?? ''), (string) $this->options->title);
            $mail->addAddress((string) $user['mail'], (string) $user['name']);

            $content = str_replace(
                ['{username}', '{sitename}', '{requestTime}', '{resetLink}'],
                [
                    htmlspecialchars($user['name']), 
                    htmlspecialchars((string) $this->options->title), 
                    date('Y-m-d H:i:s'), 
                    htmlspecialchars($url)
                ],
                (string) ($this->config->emailTemplate ?? '')
            );

            $mail->isHTML(true);
            $mail->Subject = _t('确认 ') . (string) $this->options->title . _t(' 上的密码重置操作');
            $mail->Body = $content;
            $mail->AltBody = strip_tags($content);

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('Passport Mail Error: ' . $e->getMessage());
            return false;
        }
    }

    public function execute() {}

    /**
     * 兼容方法调用处理
     * 
     * 用于兼容 Typecho 1.2.1 和 1.3.0 之间的差异
     * 
     * @param string $name 方法名
     * @param array $args 参数
     * @return mixed
     * @throws Exception
     */
    public function __call(string $name, array $args)
    {
        switch ($name) {
            case 'doForgot':
                echo Common::url('/passport/forgot', $this->options->index);
                return;
            case 'doReset':
                echo Common::url('/passport/reset', $this->options->index);
                return;
            default:
                throw new Exception("Method {$name} not found");
        }
    }
}