<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\POP3;

class Passport_Widget extends Typecho_Widget
{
    private $options;
    private $config;
    private $notice;
    private $security;
    public $show_fallback_captcha = false; // To signal template

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->notice = parent::widget('Widget_Notice');
        $this->options = parent::widget('Widget_Options');
        $this->config = $this->options->plugin('Passport');
        $this->security = parent::widget('Widget_Security');
    }

    public function execute(){}

    private function handleFormSubmission($formType) {
        $this->security->checkToken();

        $formValidator = ($formType == 'forgot') ? $this->forgotForm() : $this->resetForm();
        if ($error = $formValidator->validate()) {
            $this->notice->set($error, 'error');
            $this->response->goBack();
            return false; // Indicates validation failure, stop further processing
        }

        $is_fallback_submission = $this->request->get('is_fallback_submission', 0);
        if (!$this->verifyCaptcha($is_fallback_submission)) {
            if ($this->show_fallback_captcha) {
                // Fallback is now required. The template will be re-rendered by the calling method.
                return null; // Special return to indicate re-render with fallback
            }
            // Regular CAPTCHA failure
            $this->response->goBack();
            return false; // Indicates CAPTCHA failure, stop further processing
        }
        return true; // All initial checks passed
    }

    public function doForgot()
    {
        if ($this->request->isPost()) {
            $formCheckResult = $this->handleFormSubmission('forgot');
            if ($formCheckResult === false) return; // Basic/CAPTCHA validation failed
            if ($formCheckResult === null) { // Null means show_fallback_captcha is true, re-render
                require_once 'template/forgot.php';
                return;
            }

            // Proceed with forgot password logic
            $db = Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($user)) {
                $this->notice->set(_t('请检查您的邮箱地址是否拼写错误或者是否注册'), 'error');
                $this->response->goBack();
                return;
            }

            $hashString = $user['name'] . $user['mail'] . $user['password'];
            $hashValidate = Typecho_Common::hash($hashString);
            $token = base64_encode($user['uid'] . '.' . $hashValidate . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/passport/reset?token=' . urlencode($token), $this->options->index);

            if ($this->sendResetEmail($user, $url)) {
                $this->notice->set(_t('邮件成功发送, 请注意查收。如果您启用了回退验证，这表示所有验证均已通过。'), 'success');
                // Optionally redirect to login or a success message page
            } else {
                $this->response->goBack(); // Mail send failed
            }
            // After successful POST or if re-rendering for fallback, don't fall through to showing the form again unless it's a direct re-render call
            return; // End POST processing
        }
        // Initial GET request or re-render due to fallback
        require_once 'template/forgot.php';
    }

    public function doReset()
    {
        $token_from_url = $this->request->get('token');
        if (empty($token_from_url)) {
            $this->notice->set(_t('无效的重置链接 (Token缺失)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }
        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token; // Use filtered token

        $decoded_token_parts = explode('.', base64_decode($token));
        if (count($decoded_token_parts) !== 3) {
            $this->notice->set(_t('无效的重置链接 (Token格式错误)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }
        list($uid, $hashValidateToken, $timeStamp) = $decoded_token_parts;

        $currentTimeStamp = $this->options->gmtTime;
        if (($currentTimeStamp - (int)$timeStamp) > 3600) {
            $this->notice->set(_t('该链接已失效或已过期, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));
        if (empty($user)) {
            $this->notice->set(_t('无效的用户信息 (UID不存在)'), 'error');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        $hashString = $user['name'] . $user['mail'] . $user['password'];
        if (!Typecho_Common::hashValidate($hashString, $hashValidateToken)) {
            $this->notice->set(_t('该链接校验失败, 可能已失效或密码已更改, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
            return;
        }

        if ($this->request->isPost()) {
            $formCheckResult = $this->handleFormSubmission('reset');
            if ($formCheckResult === false) return;
            if ($formCheckResult === null) { // show_fallback_captcha is true
                require_once 'template/reset.php'; // Pass current user token to template
                return;
            }

            // Proceed with reset password logic
            $hasher = new PasswordHash(8, true);
            $new_password_hashed = $hasher->HashPassword($this->request->password);

            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $new_password_hashed))
                ->where('uid = ?', $user['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败, 请稍后重试'), 'error');
                $this->response->goBack();
            } else {
                $this->notice->set(_t('重置密码成功, 请使用新密码登录。如果您启用了回退验证，这表示所有验证均已通过。'), 'success');
                $this->response->redirect($this->options->loginUrl);
            }
            return; // End POST processing
        }
        require_once 'template/reset.php';
    }

    private function verifyCaptcha($is_fallback_submission = 0) {
        $primaryProvider = $this->config->captchaProvider;
        $v3_fallbackProvider = $this->config->v3_fallbackProvider;

        if ($primaryProvider == 'recaptcha_v3') {
            if ($is_fallback_submission && $v3_fallbackProvider != 'none') {
                // This is a submission for the fallback CAPTCHA
                return $this->executeStandardCaptchaVerification($v3_fallbackProvider, true);
            } else {
                // Primary v3 verification
                $v3_token = $this->request->get('recaptcha_v3_token'); // Name of hidden field
                if (empty($v3_token) && !$is_fallback_submission) { // Only require v3 token if not a fallback submission
                    $this->notice->set(_t('reCAPTCHA v3 令牌缺失'), 'error');
                    return false;
                }
                // If it's a fallback submission, we don't need v3 token anymore.
                // If not fallback, and token is present, verify it.
                if (!$is_fallback_submission && !empty($v3_token)) {
                    if ($this->verifyReCaptchaV3($v3_token)) {
                        return true; // v3 passed with good score
                    } else {
                        // v3 failed (low score or error). Check if fallback is configured.
                        // The notice for v3 failure is already set in verifyReCaptchaV3
                        if ($v3_fallbackProvider != 'none') {
                            $this->show_fallback_captcha = true; // Signal template to show fallback
                            // Notice for low score is set in verifyReCaptchaV3, add specific fallback instruction
                            $this->notice->set(_t('安全评分较低，请完成以下额外验证以继续。'), 'error', true); // Append to existing notices
                            return false; // Indicate failure for now, but triggers fallback display in calling method
                        }
                        return false; // v3 failed, no fallback, notice already set
                    }
                } elseif ($is_fallback_submission) {
                    // This case should be handled by the first if block in this v3 section
                    // but as a safeguard, if it's a fallback and we somehow got here, it means
                    // we are expecting to verify the fallback, which is done by executeStandardCaptchaVerification.
                    // However, this path implies v3_token was empty, which is fine for fallback.
                    // This path should ideally not be hit if logic is correct.
                    return $this->executeStandardCaptchaVerification($v3_fallbackProvider, true);
                }
                // If primary is v3, but no token and not fallback, it's an error (already handled by empty check)
                // If primary is v3, token is empty, and IS fallback, means we proceed to verify fallback
            }
        }
        // Primary provider is v2 or hCaptcha or none (or v3 path determined it's a fallback)
        return $this->executeStandardCaptchaVerification($primaryProvider);
    }

    private function executeStandardCaptchaVerification($providerToVerify, $isFallback = false) {
        $response = '';
        $logPrefix = $isFallback ? _t("回退验证: ") : _t("主要验证: ");

        switch ($providerToVerify) {
            case 'recaptcha_v2':
                $response = $this->request->get('g-recaptcha-response');
                if (empty($response)) {
                    $this->notice->set($logPrefix . _t('请完成 reCAPTCHA v2 验证'), 'error');
                    return false;
                }
                return $this->verifyReCaptchaV2($response);
            case 'hcaptcha':
                $response = $this->request->get('h-captcha-response');
                if (empty($response)) {
                    $this->notice->set($logPrefix . _t('请完成 hCaptcha 验证'), 'error');
                    return false;
                }
                return $this->verifyHCaptcha($response);
            case 'none': // This applies if primary is 'none' or fallback is 'none'
                if ($isFallback && $this->config->captchaProvider == 'recaptcha_v3' && $this->config->v3_fallbackProvider == 'none') {
                    // If primary was v3 and failed, and fallback is 'none', this is effectively a failure.
                    // The notice for v3 failure would have been set already.
                    return false;
                }
                return true;
            default:
                if ($this->config->captchaProvider != 'none' && $providerToVerify != 'none') { // Avoid error if primary is 'none'
                    $this->notice->set(_t('未知的 CAPTCHA 提供商配置: ') . $providerToVerify, 'error');
                }
                return false; // Or true if 'none' was intended but not explicitly handled above. Let's assume false for safety.
        }
    }

    // verifyReCaptchaV2, verifyReCaptchaV3, verifyHCaptcha, sendResetEmail, forgotForm, resetForm, send_post methods remain largely the same
    // Small adjustments might be needed in forgotForm/resetForm for action URL if using fallback,
    // but Typecho's form helper should retain GET parameters in action URL if not overridden.

    private function verifyReCaptchaV2($response)
    {
        if (empty($this->config->recaptcha_v2_secretkey)) {
            $this->notice->set(_t('reCAPTCHA v2 密钥未配置'), 'error');
            return false;
        }
        $post_data = ['secret' => $this->config->recaptcha_v2_secretkey, 'response' => $response, 'remoteip' => $this->request->getIp()];
        $result = json_decode($this->send_post('https://www.recaptcha.net/recaptcha/api/siteverify', $post_data), true);
        if (isset($result['success']) && $result['success']) return true;
        $this->notice->set(_t('reCAPTCHA v2 验证失败: ') . (isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown error'), 'error');
        return false;
    }

    private function verifyReCaptchaV3($token)
    {
        if (empty($this->config->recaptcha_v3_secretkey)) {
            $this->notice->set(_t('reCAPTCHA v3 密钥未配置'), 'error');
            return false;
        }
        $post_data = ['secret' => $this->config->recaptcha_v3_secretkey, 'response' => $token, 'remoteip' => $this->request->getIp()];
        $result = json_decode($this->send_post('https://www.recaptcha.net/recaptcha/api/siteverify', $post_data), true);
        $threshold = (float) $this->config->recaptcha_v3_threshold;
        if (empty($threshold) || $threshold < 0 || $threshold > 1) $threshold = 0.5;

        if (isset($result['success']) && $result['success'] && isset($result['score']) && $result['score'] >= $threshold) return true;

        $reason = _t('未知原因');
        if (!$result['success']) $reason = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : _t('API通信错误');
        else if (isset($result['score'])) $reason = _t('分数过低: ') . $result['score'];
        else $reason = _t('未能获取分数');
        $this->notice->set(_t('reCAPTCHA v3 初步验证失败: ') . $reason, 'error'); // Don't clear existing notices for fallback
        return false;
    }

    private function verifyHCaptcha($response)
    {
        if (empty($this->config->hcaptcha_secretkey)) {
            $this->notice->set(_t('hCaptcha 密钥未配置'), 'error');
            return false;
        }
        $post_data = ['secret' => $this->config->hcaptcha_secretkey, 'response' => $response, 'remoteip' => $this->request->getIp(), 'sitekey' => $this->config->hcaptcha_sitekey];
        $result = json_decode($this->send_post('https://hcaptcha.com/siteverify', $post_data), true);
        if (isset($result['success']) && $result['success']) return true;
        $this->notice->set(_t('hCaptcha 验证失败: ') . (isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown error'), 'error');
        return false;
    }

    private function sendResetEmail($user, $url)
    {
        if ($this->config->enable_pop_before_smtp == 1) {
            if (empty($this->config->pop3_host) || empty($this->config->pop3_username) || empty($this->config->pop3_password)) {
                $this->notice->set(_t('POP-before-SMTP 配置不完整'), 'error'); return false;
            }
            require_once 'PHPMailer/POP3.php';
            $pop = new POP3();
            $pop_port = empty($this->config->pop3_port) ? false : (int)$this->config->pop3_port;
            if (!$pop->authorise($this->config->pop3_host, $pop_port, 30, $this->config->pop3_username, $this->config->pop3_password, ($this->options->debug ? POP3::DEBUG_CLIENT : POP3::DEBUG_OFF))) {
                $this->notice->set(_t('POP-before-SMTP 认证失败: ') . implode('; ', $pop->getErrors()), 'error'); return false;
            }
        }
        require_once 'PHPMailer/Exception.php'; require_once 'PHPMailer/PHPMailer.php'; require_once 'PHPMailer/SMTP.php';
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = "UTF-8"; $mail->isSMTP(); $mail->Host = $this->config->host; $mail->SMTPAuth = true;
            $mail->Username = $this->config->username; $mail->Password = $this->config->password; $mail->Port = (int)$this->config->port;
            if (!empty($this->config->secure) && in_array($this->config->secure, ['ssl', 'tls'])) $mail->SMTPSecure = $this->config->secure;
            if (isset($this->options->debug) && $this->options->debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) { error_log("PHPMailer debug [$level]: $str"); };
            }
            $mail->setFrom($this->config->username, $this->options->title . ' ' ._t('管理员'));
            $mail->addAddress($user['mail'], $user['name']);
            $userTime = $this->options->gmtTime + ($this->options->timezoneOffset - $this->options->serverTimezoneOffset);
            $emailBody = str_replace(['{username}', '{sitename}', '{requestTime}', '{resetLink}'], [$user['name'], $this->options->title, date('Y-m-d H:i:s', $userTime), $url], $this->config->emailTemplate);
            $mail->isHTML(true); $mail->Subject = $this->options->title . ' - ' . _t('密码重置请求'); $mail->Body = $emailBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", preg_replace('/<hr\s*\/?>/i', "\n----\n", $emailBody)));
            return $mail->send();
        } catch (Exception $e) {
            $this->notice->set(_t('邮件发送失败: ') . $mail->ErrorInfo . ' (Details: ' . $e->getMessage() . ')', 'error');
            error_log('PHPMailer Exception (Passport): ' . $e->getMessage() . ' | Info: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function forgotForm() {
        $form = new Typecho_Widget_Helper_Form($this->options->forgotUrl, Typecho_Widget_Helper_Form::POST_METHOD);
        $mailInput = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, $this->request->mail, _t('邮箱'), _t('请输入您忘记密码的账号所对应的邮箱地址'));
        $mailInput->addRule('required', _t('必须填写电子邮箱')); $mailInput->addRule('email', _t('电子邮箱格式错误'));
        $form->addInput($mailInput);
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('发送重置邮件'));
        $submit->input->setAttribute('class', 'btn primary'); $form->addItem($submit);
        return $form;
    }

    public function resetForm() {
        $resetUrlWithToken = Typecho_Common::url('/passport/reset?token=' . urlencode($this->request->get('token')), $this->options->index);
        $form = new Typecho_Widget_Helper_Form($resetUrlWithToken, Typecho_Widget_Helper_Form::POST_METHOD);
        $passwordInput = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('新密码'), _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $passwordInput->input->setAttribute('class', 'w-100'); $passwordInput->addRule('required', _t('必须填写密码'));
        $passwordInput->addRule('minLength', _t('为了保证账户安全, 请输入至少 %d 位的密码'), 6); $form->addInput($passwordInput);
        $confirmInput = new Typecho_Widget_Helper_Form_Element_Password('confirm', NULL, NULL, _t('密码确认'), _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirmInput->input->setAttribute('class', 'w-100'); $confirmInput->addRule('confirm', _t('两次输入的密码不一致'), 'password'); $form->addInput($confirmInput);
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'btn primary'); $form->addItem($submit);
        return $form;
    }

    private function send_post($url, $post_data) {
        $postdata = http_build_query($post_data);
        $options = ['http' => ['method' => 'POST', 'header' => "Content-type:application/x-www-form-urlencoded\r\nUser-Agent:Typecho Passport Plugin\r\n", 'content' => $postdata, 'timeout' => 20, 'ignore_errors' => true]];
        $context = stream_context_create($options); $result = file_get_contents($url, false, $context);
        if ($result === false) { $error = error_get_last(); error_log("Passport: send_post to $url failed. Error: " . ($error['message'] ?? 'Unknown error')); }
        return $result;
    }
}