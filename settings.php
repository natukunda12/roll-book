<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';
requireLogin();

if (!isAdmin() && !isManager()) {
    header('Location: dashboard.php');
    exit;
}

$user   = currentUser();
$biz_id = $user['business_id'];
$msg = ''; $error = '';

// ── Helper function for permission toggle rows ────────────
function permToggleRow(array $u, string $field, string $label, string $desc): string {
    $checked = !empty($u[$field]) ? 'checked' : '';
    $val     = !empty($u[$field]) ? 0 : 1;
    return '
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--surface2);border-radius:8px;margin-bottom:6px">
      <div>
        <div style="font-size:12px;font-weight:500">'.$label.'</div>
        <div style="font-size:11px;color:var(--muted)">'.$desc.'</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="toggle_perm">
        <input type="hidden" name="user_id" value="'.$u['id'].'">
        <input type="hidden" name="field" value="'.$field.'">
        <input type="hidden" name="field_value" value="'.$val.'">
        <label class="toggle">
          <input type="checkbox" '.$checked.' onchange="this.form.submit()">
          <span class="slider"></span>
        </label>
      </form>
    </div>';
}

// ── Handle all POST actions ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'invite' && isAdmin()) {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'user';
        $pass  = $_POST['password'] ?? 'password';
        if ($name && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $chk = db()->prepare("SELECT id FROM users WHERE email=?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'Email already in use.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                db()->prepare("INSERT INTO users (business_id,name,email,password,role) VALUES (?,?,?,?,?)")
                   ->execute([$biz_id, $name, $email, $hash, $role]);
                // Send invite email
                $biz_n = db()->prepare("SELECT name FROM businesses WHERE id=?");
                $biz_n->execute([$biz_id]);
                $biz_name = $biz_n->fetchColumn();
                $email_sent = sendInviteEmail($biz_id, $biz_name, $email, $name, $role, $pass, APP_URL);
                $msg = "User {$name} added." . ($email_sent ? " Invite email sent to {$email}." : " (Email not sent — check SMTP settings.)");
            }
        } else { $error = 'Invalid name or email.'; }
    }

    if ($action === 'update_role' && isAdmin()) {
        $uid  = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'user';
        if ($uid !== $user['id']) {
            db()->prepare("UPDATE users SET role=? WHERE id=? AND business_id=?")
               ->execute([$role, $uid, $biz_id]);
            $msg = 'Role updated.';
        }
    }

    if ($action === 'toggle_status' && isAdmin()) {
        $uid    = (int)$_POST['user_id'];
        $status = $_POST['status'] ?? 'active';
        if ($uid !== $user['id']) {
            db()->prepare("UPDATE users SET status=? WHERE id=? AND business_id=?")
               ->execute([$status, $uid, $biz_id]);
            $msg = 'User status updated.';
        }
    }

    if ($action === 'remove_user' && isAdmin()) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $user['id']) {
            db()->prepare("DELETE FROM users WHERE id=? AND business_id=?")
               ->execute([$uid, $biz_id]);
            $msg = 'User removed.';
        }
    }

    // Single unified permission toggle — admin only
    if ($action === 'toggle_perm' && isAdmin()) {
        $uid   = (int)$_POST['user_id'];
        $field = $_POST['field'] ?? '';
        $val   = (int)$_POST['field_value'];
        $allowed = [
            'admin_access',
            'manager_access',
            'perm_edit_transactions',
            'perm_delete_transactions',
            'perm_edit_business',
            'perm_view_all_transactions',
        ];
        if (in_array($field, $allowed) && $uid !== $user['id']) {
            db()->prepare("UPDATE users SET `$field`=? WHERE id=? AND business_id=?")
               ->execute([$val, $uid, $biz_id]);
            $msg = 'Permission updated.';
        }
    }

    if ($action === 'update_business' && can('edit_business')) {
        $name     = trim($_POST['biz_name'] ?? '');
        $email    = trim($_POST['biz_email'] ?? '');
        $phone    = trim($_POST['biz_phone'] ?? '');
        $address  = trim($_POST['biz_address'] ?? '');
        $currency = trim($_POST['currency'] ?? 'RWF');
        if ($name) {
            db()->prepare("UPDATE businesses SET name=?,email=?,phone=?,address=?,currency=? WHERE id=?")
               ->execute([$name, $email, $phone, $address, $currency, $biz_id]);
            $_SESSION['business_name'] = $name;
            $_SESSION['currency']      = $currency;
            $msg = 'Business profile updated.';
        }
    }


    // Save SMTP settings
    if ($action === 'save_smtp' && isAdmin()) {
        $smtp_host      = trim($_POST['smtp_host'] ?? '');
        $smtp_port      = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user      = trim($_POST['smtp_user'] ?? '');
        $smtp_pass_new  = $_POST['smtp_pass'] ?? '';
        $smtp_from      = trim($_POST['smtp_from'] ?? '');
        $smtp_from_name = trim($_POST['smtp_from_name'] ?? 'Cashbook Pro');
        $smtp_secure    = $_POST['smtp_secure'] ?? 'tls';

        if ($smtp_pass_new) {
            db()->prepare("UPDATE businesses SET smtp_host=?,smtp_port=?,smtp_user=?,smtp_pass=?,smtp_from=?,smtp_from_name=?,smtp_secure=? WHERE id=?")
               ->execute([$smtp_host,$smtp_port,$smtp_user,$smtp_pass_new,$smtp_from,$smtp_from_name,$smtp_secure,$biz_id]);
        } else {
            db()->prepare("UPDATE businesses SET smtp_host=?,smtp_port=?,smtp_user=?,smtp_from=?,smtp_from_name=?,smtp_secure=? WHERE id=?")
               ->execute([$smtp_host,$smtp_port,$smtp_user,$smtp_from,$smtp_from_name,$smtp_secure,$biz_id]);
        }

        // Send test email if requested
        if (!empty($_POST['test_email'])) {
            $biz_n = db()->prepare("SELECT name FROM businesses WHERE id=?");
            $biz_n->execute([$biz_id]);
            $biz_name = $biz_n->fetchColumn();
            $mailer = Mailer::forBusiness($biz_id);
            $test_html = emailTemplate('Test Email', '<h2>Test Email</h2><p>Your SMTP settings are working correctly! Cashbook Pro can now send emails.</p>', APP_URL, $biz_name);
            $ok = $mailer->send($smtp_from ?: $smtp_user, 'Cashbook Pro — SMTP Test', $test_html);
            Mailer::log($biz_id, $smtp_from ?: $smtp_user, 'SMTP Test', $ok);
            $msg = $ok ? 'SMTP settings saved and test email sent successfully!' : 'Settings saved but test email failed. Check your SMTP credentials.';
        } else {
            $msg = 'SMTP settings saved.';
        }
    }

    if ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $stmt = db()->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($cur, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $conf) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Password changed.';
        }
    }
}

// ── Fetch data ────────────────────────────────────────────
$team = db()->prepare("SELECT * FROM users WHERE business_id=? ORDER BY role, name");
$team->execute([$biz_id]);
$all_users = $team->fetchAll();

// FIX: was "WHERE business_id=?" — wrong column, businesses table uses "id"
$biz = db()->prepare("SELECT * FROM businesses WHERE id=?");
$biz->execute([$biz_id]);
$business = $biz->fetch();

$active_tab = $_GET['tab'] ?? 'team';

include 'includes/header.php';
?>

<div class="main">
  <div class="topbar">
    <h1>Settings</h1>
  </div>
  <div class="page-content">

    <?php if($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <!-- Tab nav -->
    <div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:0">
      <?php
      $tabs = ['team' => '👥 Team', 'security' => '🔒 Security', 'email' => '📧 Email & SMTP'];
      if (can('edit_business')) $tabs['business'] = '🏢 Business';
      foreach($tabs as $k => $label):
      ?>
      <a href="?tab=<?= $k ?>" style="padding:10px 18px;font-size:13.5px;border-bottom:2px solid <?= $active_tab===$k?'var(--accent)':'transparent' ?>;color:<?= $active_tab===$k?'var(--accent)':'var(--muted)' ?>;margin-bottom:-1px;transition:.15s"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php if($active_tab === 'team'): ?>
    <!-- ====== TEAM TAB ====== -->
    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start">

      <!-- Left: Members list -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <div style="font-size:15px;font-weight:500">Team Members</div>
          <?php if(isAdmin()): ?>
          <button class="btn btn-primary btn-sm" onclick="openModal('inviteModal')">+ Add Member</button>
          <?php endif; ?>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <?php foreach($all_users as $u): ?>
          <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid var(--border)">
            <div class="avatar" style="width:38px;height:38px;font-size:13px"><?= strtoupper(substr($u['name'],0,2)) ?></div>
            <div style="flex:1">
              <div style="font-size:14px;font-weight:500">
                <?= sanitize($u['name']) ?>
                <?php if(!empty($u['admin_access']) && $u['role']!=='admin'): ?>
                <span style="font-size:10px;background:rgba(16,185,129,.15);color:var(--income);padding:2px 7px;border-radius:20px;margin-left:6px;vertical-align:middle">ADMIN ACCESS</span>
                <?php endif; ?>
                <?php if(!empty($u['perm_view_all_transactions'])): ?>
                <span style="font-size:10px;background:rgba(59,130,246,.15);color:var(--accent);padding:2px 7px;border-radius:20px;margin-left:4px;vertical-align:middle">ALL TXN</span>
                <?php endif; ?>
                <?php if(!empty($u['perm_edit_business'])): ?>
                <span style="font-size:10px;background:rgba(186,117,23,.15);color:#f59e0b;padding:2px 7px;border-radius:20px;margin-left:4px;vertical-align:middle">BIZ EDIT</span>
                <?php endif; ?>
              </div>
              <div style="font-size:11px;color:var(--muted)"><?= sanitize($u['email']) ?></div>
            </div>
            <span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
            <span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span>
            <?php if(isAdmin() && $u['id'] != $user['id']): ?>
            <button class="btn btn-ghost btn-sm" onclick="openManage(<?= htmlspecialchars(json_encode($u)) ?>)">Manage</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right: Permission Grants -->
      <?php if(isAdmin()): ?>
      <div>
        <div style="font-size:15px;font-weight:500;margin-bottom:14px">Permission Grants</div>
        <div class="card" style="padding:0;overflow:hidden">
          <div style="padding:14px 18px;font-size:12px;color:var(--muted);border-bottom:1px solid var(--border);line-height:1.6">
            Toggle individual permissions. Changes apply immediately.
          </div>

          <?php foreach($all_users as $u): ?>
          <?php if($u['role'] === 'admin') continue; ?>
          <div style="padding:16px 18px;border-bottom:1px solid var(--border)">

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
              <div class="avatar" style="width:28px;height:28px;font-size:11px;flex-shrink:0"><?= strtoupper(substr($u['name'],0,2)) ?></div>
              <div style="font-size:13px;font-weight:500"><?= sanitize($u['name']) ?></div>
              <span class="badge badge-<?= $u['role'] ?>" style="font-size:10px"><?= ucfirst($u['role']) ?></span>
            </div>

            <!-- View all transactions: everyone -->
            <?php echo permToggleRow($u, 'perm_view_all_transactions', 'View all transactions', "See all members' transactions, not just own"); ?>

            <!-- Edit & delete: users only (managers already have these) -->
            <?php if($u['role'] === 'user'): ?>
            <?php echo permToggleRow($u, 'perm_edit_transaction',   'Edit transactions',   'Can edit their own transactions'); ?>
            <?php echo permToggleRow($u, 'perm_delete_transaction', 'Delete transactions', 'Can delete their own transactions'); ?>
            <?php endif; ?>

            <!-- Edit business: managers only -->
            <?php if($u['role'] === 'manager'): ?>
            <?php echo permToggleRow($u, 'perm_edit_business', 'Edit business profile', 'Can change name, currency, address'); ?>
            <?php endif; ?>

            <!-- Full admin access: everyone -->
            <?php echo permToggleRow($u, 'admin_access', 'Full admin access', 'Complete control — same level as admin'); ?>

          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <?php elseif($active_tab === 'business' && can('edit_business')): ?>
    <!-- ====== BUSINESS TAB ====== -->
    <div style="max-width:560px">
      <div class="card">
        <div style="font-size:15px;font-weight:500;margin-bottom:20px">Business Profile</div>
        <form method="POST">
          <input type="hidden" name="action" value="update_business">
          <div class="form-row">
            <div class="form-group">
              <label>Business Name</label>
              <input name="biz_name" class="form-control" value="<?= sanitize($business['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Currency Code</label>
              <select name="currency" class="form-control">
                <?php foreach(['RWF','USD','EUR','GBP','KES','UGX','TZS','NGN','GHS','ZAR'] as $c): ?>
                <option value="<?= $c ?>" <?= ($business['currency']??'')===$c?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="biz_email" class="form-control" value="<?= sanitize($business['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input name="biz_phone" class="form-control" value="<?= sanitize($business['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <input name="biz_address" class="form-control" value="<?= sanitize($business['address'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <?php elseif($active_tab === 'security'): ?>
    <!-- ====== SECURITY TAB ====== -->
    <div style="max-width:440px">
      <div class="card">
        <div style="font-size:15px;font-weight:500;margin-bottom:20px">Change Password</div>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
      </div>
    </div>

    <?php elseif($active_tab === 'email'): ?>
    <!-- ====== EMAIL & SMTP TAB ====== -->
    <div style="max-width:580px;display:flex;flex-direction:column;gap:18px">

      <!-- SMTP Settings -->
      <div class="card">
        <div style="font-size:15px;font-weight:500;margin-bottom:6px">SMTP Configuration</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:18px;line-height:1.6">
          Configure your email server so Cashbook Pro can send invite emails and admin notifications.<br>
          Works with Gmail, Outlook, SendGrid, Mailgun, or any SMTP provider.
        </div>
        <?php
        $smtp_biz = db()->prepare("SELECT * FROM businesses WHERE id=?");
        $smtp_biz->execute([$biz_id]);
        $smtp_data = $smtp_biz->fetch();
        ?>
        <form method="POST">
          <input type="hidden" name="action" value="save_smtp">
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Host</label>
              <input name="smtp_host" class="form-control" value="<?= sanitize($smtp_data['smtp_host']??'') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
              <label>SMTP Port</label>
              <input name="smtp_port" type="number" class="form-control" value="<?= (int)($smtp_data['smtp_port']??587) ?>" placeholder="587">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Username</label>
              <input name="smtp_user" class="form-control" value="<?= sanitize($smtp_data['smtp_user']??'') ?>" placeholder="you@gmail.com">
            </div>
            <div class="form-group">
              <label>SMTP Password / App Password</label>
              <input type="password" name="smtp_pass" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>From Email</label>
              <input name="smtp_from" type="email" class="form-control" value="<?= sanitize($smtp_data['smtp_from']??'') ?>" placeholder="noreply@yourcompany.com">
            </div>
            <div class="form-group">
              <label>From Name</label>
              <input name="smtp_from_name" class="form-control" value="<?= sanitize($smtp_data['smtp_from_name']??'Cashbook Pro') ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Encryption</label>
            <select name="smtp_secure" class="form-control" style="width:200px">
              <option value="tls" <?= ($smtp_data['smtp_secure']??'tls')==='tls'?'selected':'' ?>>TLS (port 587) — recommended</option>
              <option value="ssl" <?= ($smtp_data['smtp_secure']??'')==='ssl'?'selected':'' ?>>SSL (port 465)</option>
              <option value="none" <?= ($smtp_data['smtp_secure']??'')==='none'?'selected':'' ?>>None (port 25)</option>
            </select>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
            <button type="submit" name="test_email" value="1" class="btn btn-ghost">📧 Send Test Email</button>
          </div>
        </form>
      </div>

      <!-- Quick setup guides -->
      <div class="card">
        <div style="font-size:14px;font-weight:500;margin-bottom:14px">Quick Setup Guides</div>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="padding:12px 14px;background:var(--surface2);border-radius:9px">
            <div style="font-size:13px;font-weight:500;margin-bottom:4px">📧 Gmail</div>
            <div style="font-size:12px;color:var(--muted);line-height:1.7">
              Host: <code style="background:var(--bg);padding:1px 6px;border-radius:4px">smtp.gmail.com</code> &nbsp;
              Port: <code style="background:var(--bg);padding:1px 6px;border-radius:4px">587</code> &nbsp;
              Encryption: TLS<br>
              Use an <strong>App Password</strong> (not your main password) — create one at myaccount.google.com → Security → App Passwords
            </div>
          </div>
          <div style="padding:12px 14px;background:var(--surface2);border-radius:9px">
            <div style="font-size:13px;font-weight:500;margin-bottom:4px">📧 Outlook / Office 365</div>
            <div style="font-size:12px;color:var(--muted);line-height:1.7">
              Host: <code style="background:var(--bg);padding:1px 6px;border-radius:4px">smtp.office365.com</code> &nbsp;
              Port: <code style="background:var(--bg);padding:1px 6px;border-radius:4px">587</code> &nbsp;
              Encryption: TLS
            </div>
          </div>
          <div style="padding:12px 14px;background:var(--surface2);border-radius:9px">
            <div style="font-size:13px;font-weight:500;margin-bottom:4px">📧 SendGrid / Mailgun (recommended for production)</div>
            <div style="font-size:12px;color:var(--muted);line-height:1.7">
              Use their SMTP relay — check their dashboard for host/port/credentials. Much higher delivery rates than Gmail.
            </div>
          </div>
        </div>
      </div>

      <!-- Email log -->
      <div class="card">
        <div style="font-size:14px;font-weight:500;margin-bottom:14px">Recent Email Log</div>
        <?php
        try {
            $log = db()->prepare("SELECT * FROM email_log WHERE business_id=? ORDER BY sent_at DESC LIMIT 20");
            $log->execute([$biz_id]);
            $emails = $log->fetchAll();
        } catch(Exception $e){ $emails=[]; }
        ?>
        <?php if(empty($emails)): ?>
        <div style="color:var(--muted);font-size:13px">No emails sent yet.</div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>To</th><th>Subject</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
              <?php foreach($emails as $em): ?>
              <tr>
                <td><?= sanitize($em['to_email']) ?></td>
                <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($em['subject']) ?></td>
                <td><span class="badge badge-<?= $em['status']==='sent'?'active':'inactive' ?>"><?= $em['status'] ?></span></td>
                <td style="white-space:nowrap;color:var(--muted)"><?= date('M j, H:i', strtotime($em['sent_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>

  </div>
</div>

<!-- Invite Modal -->
<div class="modal-overlay" id="inviteModal">
  <div class="modal">
    <h2>Add Team Member</h2>
    <form method="POST">
      <input type="hidden" name="action" value="invite">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input name="name" class="form-control" required placeholder="John Doe">
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" class="form-control">
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required placeholder="john@company.com">
      </div>
      <div class="form-group">
        <label>Initial Password</label>
        <input type="text" name="password" class="form-control" value="password" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('inviteModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Manage User Modal -->
<div class="modal-overlay" id="manageModal">
  <div class="modal">
    <h2>Manage User</h2>
    <div id="manage_name" style="font-size:13px;color:var(--muted);margin-bottom:20px"></div>

    <form method="POST" style="margin-bottom:14px">
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="user_id" id="manage_uid_role">
      <div class="form-group">
        <label>Change Role</label>
        <select name="role" id="manage_role" class="form-control">
          <option value="user">User</option>
          <option value="manager">Manager</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Update Role</button>
    </form>

    <form method="POST" style="margin-bottom:14px">
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="user_id" id="manage_uid_status">
      <div class="form-group">
        <label>Account Status</label>
        <select name="status" id="manage_status" class="form-control">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Update Status</button>
    </form>

    <div class="modal-footer" style="margin-top:0;border-top:1px solid var(--border);padding-top:16px">
      <button type="button" class="btn btn-ghost" onclick="closeModal('manageModal')">Close</button>
      <form method="POST" style="display:inline" onsubmit="return confirm('Remove this user from the team?')">
        <input type="hidden" name="action" value="remove_user">
        <input type="hidden" name="user_id" id="manage_uid_del">
        <button type="submit" class="btn btn-danger">Remove User</button>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open') }
function closeModal(id){ document.getElementById(id).classList.remove('open') }
function openManage(u){
  document.getElementById('manage_name').textContent  = u.name + ' — ' + u.email;
  document.getElementById('manage_uid_role').value    = u.id;
  document.getElementById('manage_uid_status').value  = u.id;
  document.getElementById('manage_uid_del').value     = u.id;
  document.getElementById('manage_role').value        = u.role;
  document.getElementById('manage_status').value      = u.status;
  openModal('manageModal');
}
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open') });
});
</script>
</body>
</html>
