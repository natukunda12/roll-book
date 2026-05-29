<?php
require_once 'includes/config.php';
if(isLoggedIn()){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??'');
  $password=$_POST['password']??'';
  if($email&&$password){
    $stmt=db()->prepare("SELECT u.*,b.name AS business_name,b.currency FROM users u JOIN businesses b ON b.id=u.business_id WHERE u.email=? AND u.status='active' LIMIT 1");
    $stmt->execute([$email]);
    $user=$stmt->fetch();
    if($user&&password_verify($password,$user['password'])){
      $_SESSION['user_id']=$user['id'];$_SESSION['user_name']=$user['name'];$_SESSION['user_email']=$user['email'];
      $_SESSION['role']=$user['role'];$_SESSION['admin_access']=$user['admin_access'];
      $_SESSION['manager_access']=$user['manager_access']??0;
      $_SESSION['perm_edit_transaction']=$user['perm_edit_transaction']??0;
      $_SESSION['perm_delete_transaction']=$user['perm_delete_transaction']??0;
      $_SESSION['perm_edit_business']=$user['perm_edit_business']??0;
      $_SESSION['perm_view_all_transactions']=$user['perm_view_all_transactions']??0;
      $_SESSION['business_id']=$user['business_id'];$_SESSION['business_name']=$user['business_name'];$_SESSION['currency']=$user['currency'];
      header('Location: dashboard.php');exit;
    }else{$error='Invalid email or password.';}
  }else{$error='Please fill in all fields.';}
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Cashbook">
<meta name="theme-color" content="#3b82f6">
<title>Cashbook Pro — Sign In</title>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="icons/icon-32x32.png">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0f1117;--surface:#181c27;--border:#252a38;--accent:#3b82f6;--text:#f1f5f9;--muted:#64748b;}
html,body{height:100%;overscroll-behavior:none}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;padding-top:calc(20px + env(safe-area-inset-top));padding-bottom:calc(20px + env(safe-area-inset-bottom))}
.wrap{width:100%;max-width:400px}
.brand{text-align:center;margin-bottom:32px}
.brand-logo{width:72px;height:72px;margin:0 auto 16px;border-radius:18px;overflow:hidden;box-shadow:0 8px 32px rgba(59,130,246,.3)}
.brand-logo img{width:100%;height:100%}
.brand h1{font-family:'DM Serif Display',serif;font-size:28px;font-weight:400;letter-spacing:-.02em}
.brand p{color:var(--muted);font-size:14px;margin-top:5px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:28px}
.form-group{margin-bottom:16px}
label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:500;text-transform:uppercase;letter-spacing:.04em}
input{width:100%;padding:13px 14px;background:var(--bg);border:1px solid var(--border);border-radius:11px;color:var(--text);font-size:15px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;-webkit-appearance:none}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
input::placeholder{color:var(--muted)}
.btn{width:100%;padding:14px;background:var(--accent);color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:.2s;margin-top:6px;-webkit-tap-highlight-color:transparent}
.btn:active{transform:scale(.98);background:#2563eb}
.error-msg{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.demo{text-align:center;margin-top:20px;font-size:12px;color:var(--muted);line-height:1.9}
.demo strong{color:var(--text)}
.divider{text-align:center;font-size:12px;color:var(--muted);margin:18px 0;position:relative}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:38%;height:1px;background:var(--border)}
.divider::before{left:0}.divider::after{right:0}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-logo"><img src="icons/icon-192x192.png" alt="Cashbook Pro"></div>
    <h1>Cashbook Pro</h1>
    <p>Professional accounting, anywhere</p>
  </div>
  <div class="card">
    <?php if($error):?><div class="error-msg"><?=sanitize($error)?></div><?php endif;?>
    <form method="POST">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@company.com" value="<?=sanitize($_POST['email']??'')?>" required autocomplete="email" autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn">Sign in →</button>
    </form>
    <div class="divider">demo accounts</div>
    <div class="demo">
      Admin: <strong>admin@acme.com</strong><br>
      Manager: <strong>alice@acme.com</strong><br>
      Password: <strong>password</strong>
    </div>
  </div>
</div>
<script>
if('serviceWorker' in navigator){
  navigator.serviceWorker.register('sw.js').catch(()=>{});
}
</script>
</body>
</html>
