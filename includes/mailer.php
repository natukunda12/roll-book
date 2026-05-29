<?php
// ============================================================
// Cashbook Pro — Mailer
// Uses PHP's built-in mail() OR SMTP via socket (no library)
// ============================================================

class Mailer {

    private array $smtp;

    public function __construct(array $smtp = []) {
        $this->smtp = $smtp;
    }

    // ── Load SMTP config from database for a business ────────
    public static function forBusiness(int $biz_id): self {
        try {
            $stmt = db()->prepare("SELECT smtp_host,smtp_port,smtp_user,smtp_pass,smtp_from,smtp_from_name,smtp_secure FROM businesses WHERE id=?");
            $stmt->execute([$biz_id]);
            $row = $stmt->fetch();
            return new self($row ?: []);
        } catch (Exception $e) {
            return new self([]);
        }
    }

    // ── Send email ───────────────────────────────────────────
    public function send(string $to, string $subject, string $html, string $plain = ''): bool {
        $from      = $this->smtp['smtp_from']      ?? '';
        $from_name = $this->smtp['smtp_from_name'] ?? 'Cashbook Pro';
        $host      = $this->smtp['smtp_host']      ?? '';
        $port      = (int)($this->smtp['smtp_port'] ?? 587);
        $user      = $this->smtp['smtp_user']      ?? '';
        $pass      = $this->smtp['smtp_pass']      ?? '';
        $secure    = $this->smtp['smtp_secure']    ?? 'tls';

        if (!$plain) $plain = strip_tags($html);

        // Use SMTP if configured
        if ($host && $user && $pass && $from) {
            return $this->sendSmtp($to, $subject, $html, $plain, $from, $from_name, $host, $port, $user, $pass, $secure);
        }

        // Fallback to PHP mail()
        if ($from) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$from_name} <{$from}>\r\n";
            return mail($to, $subject, $html, $headers);
        }

        return false; // No mail config set
    }

    // ── SMTP sender via socket ───────────────────────────────
    private function sendSmtp(
        string $to, string $subject, string $html, string $plain,
        string $from, string $from_name,
        string $host, int $port, string $user, string $pass, string $secure
    ): bool {
        try {
            $prefix = $secure === 'ssl' ? 'ssl://' : '';
            $conn = fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$conn) return false;

            $read = function() use ($conn) {
                $data = '';
                while ($line = fgets($conn, 515)) {
                    $data .= $line;
                    if (substr($line, 3, 1) === ' ') break;
                }
                return $data;
            };

            $cmd = function(string $c, array $okCodes = [250]) use ($conn, $read) {
    fputs($conn, $c . "\r\n");
    $resp = $read();

    $code = (int)substr($resp, 0, 3);

    if (!in_array($code, $okCodes)) {
        throw new Exception("SMTP Error: $resp");
    }

    return $resp;
};

            $read(); // greeting
            $cmd("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

            if ($secure === 'tls') {
                $cmd("STARTTLS");
               if (!stream_socket_enable_crypto(
    $conn,
    true,
    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
)) {
    return false;
}
                $cmd("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            }

           $cmd("AUTH LOGIN", [334]);
          $cmd(base64_encode($user), [334]);
           $cmd(base64_encode($pass), [235]);
            $cmd("MAIL FROM: <{$from}>", [250]);
            $cmd("RCPT TO: <{$to}>", [250, 251]);
           $cmd("DATA", [354]);

            $boundary = md5(uniqid());
            $msg  = "From: {$from_name} <{$from}>\r\n";
            $msg .= "To: {$to}\r\n";
            $msg .= "Subject: {$subject}\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            $msg .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n\r\n";
            $msg .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n";
            $msg .= "--{$boundary}--";

            fputs($conn, $msg . "\r\n.\r\n");

$response = $read();

if ((int)substr($response, 0, 3) !== 250) {
    throw new Exception("Email rejected: $response");
}
            $read();
            $cmd("QUIT");
            fclose($conn);
            return true;

        } catch (Exception $e) {
    error_log("MAIL ERROR: " . $e->getMessage());
    return false;
}
    }

    // ── Log result ───────────────────────────────────────────
    public static function log(int $biz_id, string $to, string $subject, bool $ok, string $err = ''): void {
        try {
            db()->prepare("INSERT INTO email_log (business_id,to_email,subject,status,error_msg) VALUES (?,?,?,?,?)")
               ->execute([$biz_id, $to, $subject, $ok ? 'sent' : 'failed', $err]);
        } catch (Exception $e) {}
    }
}


// ── Email Templates ──────────────────────────────────────────

function emailTemplate(string $title, string $body, string $appUrl, string $bizName): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif}
  .wrap{max-width:560px;margin:40px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .header{background:#0f1117;padding:28px 32px;text-align:center}
  .header img{width:52px;height:52px;border-radius:12px;margin-bottom:12px}
  .header h1{color:#f1f5f9;font-size:20px;font-weight:600;margin:0}
  .header p{color:#64748b;font-size:13px;margin:4px 0 0}
  .body{padding:32px}
  .body h2{font-size:18px;color:#0f172a;margin:0 0 12px}
  .body p{font-size:14px;color:#475569;line-height:1.7;margin:0 0 16px}
  .cred-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 22px;margin:20px 0}
  .cred-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #e2e8f0;font-size:14px}
  .cred-row:last-child{border-bottom:none}
  .cred-label{color:#64748b;font-weight:500}
  .cred-value{color:#0f172a;font-weight:600;font-family:monospace;background:#e2e8f0;padding:2px 8px;border-radius:5px}
  .btn{display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:10px;font-size:15px;font-weight:600;margin:8px 0}
  .btn:hover{background:#2563eb}
  .divider{border:none;border-top:1px solid #e2e8f0;margin:24px 0}
  .footer{background:#f8fafc;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
  .notif-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9}
  .notif-icon{font-size:22px}
  .notif-body .ntitle{font-size:14px;font-weight:600;color:#0f172a}
  .notif-body .nmsg{font-size:13px;color:#64748b;margin-top:3px}
  .notif-body .ntime{font-size:11px;color:#94a3b8;margin-top:4px}
  @media(max-width:600px){.wrap{margin:0;border-radius:0}.body{padding:24px}.cred-row{flex-direction:column;align-items:flex-start;gap:4px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>📒 Cashbook Pro</h1>
    <p>{$bizName}</p>
  </div>
  <div class="body">
    {$body}
  </div>
  <div class="footer">
    <p>This email was sent by Cashbook Pro on behalf of <strong>{$bizName}</strong>.</p>
    <p>© {$appUrl}</p>
  </div>
</div>
</body>
</html>
HTML;
}


// ── Send invite email to new user ────────────────────────────
function sendInviteEmail(
    int    $biz_id,
    string $biz_name,
    string $to_email,
    string $to_name,
    string $role,
    string $password,
    string $app_url
): bool {
    $login_url = rtrim($app_url, '/') . '/index.php';
    $role_cap  = ucfirst($role);

    $body = <<<HTML
    <h2>Welcome to Cashbook Pro, {$to_name}!</h2>
    <p>You've been added to <strong>{$biz_name}</strong> as a <strong>{$role_cap}</strong>. Here are your login details:</p>
    <div class="cred-box">
      <div class="cred-row"><span class="cred-label">Email</span><span class="cred-value">{$to_email}</span></div>
      <div class="cred-row"><span class="cred-label">Password</span><span class="cred-value">{$password}</span></div>
      <div class="cred-row"><span class="cred-label">Role</span><span class="cred-value">{$role_cap}</span></div>
    </div>
    <p>Click the button below to sign in and get started:</p>
    <p style="text-align:center"><a href="{$login_url}" class="btn">Sign in to Cashbook Pro →</a></p>
    <hr class="divider">
    <p style="font-size:13px;color:#94a3b8">For security, please change your password after your first login via Settings → Security.</p>
HTML;

    $html    = emailTemplate("You've been invited", $body, $app_url, $biz_name);
    $subject = "You've been added to {$biz_name} on Cashbook Pro";
    $mailer  = Mailer::forBusiness($biz_id);
    $ok      = $mailer->send($to_email, $subject, $html);
    Mailer::log($biz_id, $to_email, $subject, $ok);
    return $ok;
}


// ── Send notification digest email to admin ──────────────────
function sendNotificationEmail(
    int    $biz_id,
    string $admin_email,
    string $admin_name,
    string $biz_name,
    array  $notifications,
    string $app_url
): bool {
    if (empty($notifications)) return false;

    $items_html = '';
    foreach ($notifications as $n) {
        $icon = match($n['type']) {
            'transaction_add'    => '💰',
            'transaction_edit'   => '✏️',
            'transaction_delete' => '🗑️',
            'book_add'           => '📚',
            'book_edit'          => '📝',
            'book_delete'        => '🗑️',
            'client_add'         => '👤',
            'book_member_add'    => '👥',
            default              => '🔔',
        };
        $time = date('M j, g:i A', strtotime($n['created_at']));
        $items_html .= <<<HTML
        <div class="notif-item">
          <div class="notif-icon">{$icon}</div>
          <div class="notif-body">
            <div class="ntitle">{$n['title']}</div>
            <div class="nmsg">{$n['message']}</div>
            <div class="ntime">{$time}</div>
          </div>
        </div>
HTML;
    }

    $count    = count($notifications);
    $dash_url = rtrim($app_url, '/') . '/dashboard.php';
    $body = <<<HTML
    <h2>Hello, {$admin_name} 👋</h2>
    <p>Here is a summary of <strong>{$count} new action(s)</strong> made by your team in <strong>{$biz_name}</strong>:</p>
    {$items_html}
    <hr class="divider">
    <p style="text-align:center"><a href="{$dash_url}" class="btn">Open Dashboard →</a></p>
HTML;

    $subject = "{$count} new team action(s) in {$biz_name} — Cashbook Pro";
    $html    = emailTemplate("Team Activity", $body, $app_url, $biz_name);
    $mailer  = Mailer::forBusiness($biz_id);
    $ok      = $mailer->send($admin_email, $subject, $html);
    Mailer::log($biz_id, $admin_email, $subject, $ok);
    return $ok;
}
