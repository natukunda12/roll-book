<?php
$user = currentUser();
$biz  = sanitize($_SESSION['business_name'] ?? 'My Business');
$page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Cashbook">
<meta name="application-name" content="Cashbook Pro">
<meta name="theme-color" content="#3b82f6">
<meta name="msapplication-TileColor" content="#3b82f6">
<meta name="msapplication-TileImage" content="icons/icon-144x144.png">
<meta name="description" content="Professional multi-business cashbook accounting app">

<title><?= APP_NAME ?> — <?= ucfirst($page) ?></title>

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="icons/icon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="icons/icon-16x16.png">

<!-- Apple touch icons -->
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="144x144" href="icons/icon-144x144.png">
<link rel="apple-touch-icon" sizes="120x120" href="icons/icon-120x120.png">

<!-- PWA Manifest -->
<link rel="manifest" href="manifest.json">

<!-- Offline JS -->
<script src="offline.js"></script>

<!-- Fonts & Chart -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f1117;--surface:#181c27;--surface2:#1e2333;
  --border:#252a38;--border2:#2e3448;
  --accent:#3b82f6;--accent-dim:rgba(59,130,246,.12);
  --text:#f1f5f9;--muted:#64748b;--muted2:#94a3b8;
  --income:#10b981;--expense:#f43f5e;
  --income-dim:rgba(16,185,129,.12);--expense-dim:rgba(244,63,94,.12);
  --sidebar-w:220px;
  --safe-top:env(safe-area-inset-top,0px);
  --safe-bottom:env(safe-area-inset-bottom,0px);
}
html{font-size:16px;height:100%}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;min-height:100dvh}
a{text-decoration:none;color:inherit}

/* ── PWA Install Banner ── */
#installBanner{display:none;position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--surface);border-top:1px solid var(--border);padding:14px 20px;padding-bottom:calc(14px + var(--safe-bottom));flex-direction:row;align-items:center;gap:14px;box-shadow:0 -4px 24px rgba(0,0,0,.4)}
#installBanner.show{display:flex}
#installBanner .ib-icon{width:44px;height:44px;flex-shrink:0;border-radius:10px;overflow:hidden}
#installBanner .ib-icon img{width:100%;height:100%}
#installBanner .ib-text{flex:1;min-width:0}
#installBanner .ib-text strong{font-size:14px;display:block;margin-bottom:2px}
#installBanner .ib-text span{font-size:12px;color:var(--muted)}
#installBanner .ib-actions{display:flex;gap:8px;flex-shrink:0}
.ib-btn-install{padding:8px 16px;background:var(--accent);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit}
.ib-btn-dismiss{padding:8px 12px;background:transparent;color:var(--muted);border:1px solid var(--border2);border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit}

/* ── iOS install hint ── */
#iosHint{display:none;position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--surface);border-top:1px solid var(--border);padding:16px 20px;padding-bottom:calc(16px + var(--safe-bottom));text-align:center;box-shadow:0 -4px 24px rgba(0,0,0,.4)}
#iosHint.show{display:block}
#iosHint p{font-size:13px;color:var(--muted2);line-height:1.7}
#iosHint strong{color:var(--text)}
#iosHint button{margin-top:10px;padding:7px 18px;background:transparent;color:var(--muted);border:1px solid var(--border2);border-radius:8px;font-size:12px;cursor:pointer;font-family:inherit}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;height:100dvh;z-index:200;transition:transform .25s cubic-bezier(.4,0,.2,1);padding-top:var(--safe-top)}
.sidebar-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.brand-icon{width:34px;height:34px;border-radius:9px;overflow:hidden;flex-shrink:0}
.brand-icon img{width:100%;height:100%}
.brand-text .biz-name{font-weight:500;font-size:14px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
.brand-text .app-name{font-size:11px;color:var(--muted);margin-top:2px}
.sidebar-nav{padding:10px 8px;flex:1;overflow-y:auto}
.nav-section{font-size:10px;font-weight:500;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;padding:10px 8px 5px}
.nav-link{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;font-size:13.5px;color:var(--muted2);transition:.15s;margin-bottom:2px;white-space:nowrap;-webkit-tap-highlight-color:transparent}
.nav-link:hover,.nav-link:active{background:var(--surface2);color:var(--text)}
.nav-link.active{background:var(--accent-dim);color:var(--accent)}
.nav-link .icon{width:20px;text-align:center;font-size:16px;flex-shrink:0}
.sidebar-footer{padding:10px 8px;border-top:1px solid var(--border);padding-bottom:calc(10px + var(--safe-bottom))}
.user-chip{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px}
.avatar{width:32px;height:32px;border-radius:50%;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:500;color:var(--accent);flex-shrink:0}
.uname{font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px}
.urole{font-size:11px;color:var(--muted)}
.logout-link{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);padding:6px 10px;margin-top:4px;border-radius:8px;transition:.15s;-webkit-tap-highlight-color:transparent}
.logout-link:hover{color:var(--expense);background:var(--expense-dim)}

/* Sidebar overlay */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}
.sidebar-overlay.open{display:block}

/* ── Main layout ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;min-height:100dvh;min-width:0}
.topbar{padding:14px 24px;padding-top:calc(14px + var(--safe-top));border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);position:sticky;top:0;z-index:100;gap:12px}
.topbar h1{font-family:'DM Serif Display',serif;font-size:20px;font-weight:400;white-space:nowrap}
.topbar .actions{display:flex;gap:8px;flex-shrink:0}
.hamburger{display:none;background:none;border:none;color:var(--text);font-size:22px;cursor:pointer;padding:4px 8px;border-radius:7px;line-height:1;-webkit-tap-highlight-color:transparent}
.page-content{padding:20px;flex:1}

/* ── Buttons ── */
.btn{padding:8px 16px;border-radius:9px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;transition:.15s;display:inline-flex;align-items:center;gap:6px;-webkit-tap-highlight-color:transparent}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover,.btn-primary:active{background:#2563eb}
.btn-ghost{background:transparent;color:var(--muted2);border:1px solid var(--border2)}.btn-ghost:hover,.btn-ghost:active{background:var(--surface2);color:var(--text)}
.btn-danger{background:rgba(244,63,94,.15);color:#fb7185;border:1px solid rgba(244,63,94,.3)}.btn-danger:hover{background:rgba(244,63,94,.25)}
.btn-sm{padding:5px 11px;font-size:12px}

/* ── Cards ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px}

/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px}
.stat-label{font-size:11px;color:var(--muted);margin-bottom:7px;font-weight:500;text-transform:uppercase;letter-spacing:.04em}
.stat-value{font-size:20px;font-weight:500;letter-spacing:-.02em;word-break:break-all}
.stat-value.income{color:var(--income)}.stat-value.expense{color:var(--expense)}
.stat-sub{font-size:11px;color:var(--muted);margin-top:4px}

/* ── Tables ── */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:480px}
th{text-align:left;padding:9px 12px;font-size:10px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:11px 12px;border-bottom:1px solid var(--border);color:var(--muted2)}
td:first-child,th:first-child{color:var(--text)}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.02)}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;white-space:nowrap}
.badge-income{background:var(--income-dim);color:var(--income)}
.badge-expense{background:var(--expense-dim);color:var(--expense)}
.badge-admin{background:rgba(139,92,246,.15);color:#a78bfa}
.badge-manager{background:var(--accent-dim);color:var(--accent)}
.badge-user{background:rgba(100,116,139,.12);color:var(--muted2)}
.badge-active{background:var(--income-dim);color:var(--income)}
.badge-inactive{background:var(--expense-dim);color:var(--expense)}

/* ── Forms ── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;color:var(--muted);margin-bottom:5px;font-weight:500}
.form-control{width:100%;padding:10px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;-webkit-appearance:none}
.form-control:focus{border-color:var(--accent)}
.form-control::placeholder{color:var(--muted)}
select.form-control option{background:var(--surface)}

/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:300;align-items:flex-end;justify-content:center;padding:0}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:20px 20px 0 0;width:100%;max-width:560px;padding:24px;padding-bottom:calc(24px + var(--safe-bottom));max-height:92vh;overflow-y:auto;margin:0 auto}
.modal-handle{width:40px;height:4px;background:var(--border2);border-radius:4px;margin:0 auto 20px}
.modal h2{font-family:'DM Serif Display',serif;font-size:19px;font-weight:400;margin-bottom:18px}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}

/* ── Toggle ── */
.toggle{position:relative;width:40px;height:22px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle .slider{position:absolute;inset:0;background:var(--border2);border-radius:22px;cursor:pointer;transition:.2s}
.toggle .slider:before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle input:checked+.slider{background:var(--accent)}
.toggle input:checked+.slider:before{transform:translateX(18px)}

/* ── Alerts ── */
.alert{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}
.alert-error{background:var(--expense-dim);color:#fca5a5;border:1px solid rgba(244,63,94,.3)}
.alert-success{background:var(--income-dim);color:#6ee7b7;border:1px solid rgba(16,185,129,.3)}

/* ── Filter bar ── */
.filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:flex-end}
.filter-bar .fgroup{display:flex;flex-direction:column;gap:4px}
.filter-bar .flabel{font-size:11px;color:var(--muted)}

/* ── Layout grids ── */
.dash-grid{display:grid;grid-template-columns:1fr 300px;gap:18px}
.books-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.settings-grid{display:grid;grid-template-columns:1fr 360px;gap:18px;align-items:start}

/* ════════════════════════════════
   RESPONSIVE
   ════════════════════════════════ */
@media(max-width:900px){
  :root{--sidebar-w:60px}
  .brand-text,.nav-link span,.nav-section,.user-chip .info,.logout-link span{display:none}
  .sidebar-brand{justify-content:center;padding:14px 0}
  .nav-link{justify-content:center;padding:10px}
  .user-chip{justify-content:center;padding:8px}
  .logout-link{justify-content:center}
  .dash-grid,.settings-grid{grid-template-columns:1fr}
}

@media(max-width:640px){
  :root{--sidebar-w:240px}
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .brand-text,.nav-link span,.nav-section,.user-chip .info,.logout-link span{display:block}
  .sidebar-brand{justify-content:flex-start;padding:18px 16px 14px}
  .nav-link{justify-content:flex-start;padding:10px 12px}
  .user-chip{justify-content:flex-start;padding:8px 10px}
  .logout-link{justify-content:flex-start}
  .main{margin-left:0}
  .hamburger{display:flex}
  .topbar{padding:12px 16px;padding-top:calc(12px + var(--safe-top))}
  .topbar h1{font-size:17px}
  .page-content{padding:14px}
  .stat-grid{grid-template-columns:1fr 1fr}
  .stat-value{font-size:16px}
  .form-row{grid-template-columns:1fr}
  .dash-grid,.settings-grid,.books-grid{grid-template-columns:1fr}
  .filter-bar .fgroup{width:calc(50% - 5px)}
  .btn{font-size:12px;padding:8px 13px}
  /* Bottom sheet modal on mobile */
  .modal-overlay{align-items:flex-end}
  .modal{border-radius:20px 20px 0 0;max-height:88vh}
}

@media(max-width:380px){
  .stat-grid{grid-template-columns:1fr}
  .filter-bar .fgroup{width:100%}
}

/* Prevent overscroll bounce on PWA */
html,body{overscroll-behavior:none}
/* Spinner for loading */
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
/* Notif detail flex fix */
#notifDetail{ flex-direction:column; }
#notifListView{ flex-direction:column; }
</style>
</head>
<body>

<!-- PWA Install Banner (Android/Desktop) -->
<div id="installBanner">
  <div class="ib-icon"><img src="icons/icon-192x192.png" alt=""></div>
  <div class="ib-text">
    <strong>Install Cashbook Pro</strong>
    <span>Add to your home screen for the best experience</span>
  </div>
  <div class="ib-actions">
    <button class="ib-btn-install" id="installBtn">Install</button>
    <button class="ib-btn-dismiss" id="dismissBtn">✕</button>
  </div>
</div>

<!-- iOS Install Hint -->
<div id="iosHint">
  <p>📲 To install: tap <strong>Share</strong> then <strong>"Add to Home Screen"</strong></p>
  <button onclick="document.getElementById('iosHint').classList.remove('show');localStorage.setItem('iosHintDismissed','1')">Got it</button>
</div>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar navigation -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><img src="icons/icon-96x96.png" alt="Cashbook Pro"></div>
    <div class="brand-text">
      <div class="biz-name"><?= $biz ?></div>
      <div class="app-name">Cashbook Pro</div>
    </div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"    class="nav-link <?= $page==='dashboard'?'active':'' ?>"    onclick="closeSidebar()"><span class="icon">📊</span><span>Dashboard</span></a>
    <a href="books.php"        class="nav-link <?= $page==='books'?'active':'' ?>"        onclick="closeSidebar()"><span class="icon">📚</span><span>Books</span></a>
    <a href="transactions.php" class="nav-link <?= $page==='transactions'?'active':'' ?>" onclick="closeSidebar()"><span class="icon">💰</span><span>Transactions</span></a>
    <a href="reports.php"      class="nav-link <?= $page==='reports'?'active':'' ?>"      onclick="closeSidebar()"><span class="icon">📤</span><span>Reports</span></a>
    <!-- Notification Panel -->
<div class="notif-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>
<div class="notif-panel" id="notifPanel">

  <!-- List view -->
  <div id="notifListView" style="display:flex;flex-direction:column;overflow:hidden;flex:1">
    <div class="notif-header">
      <h3>🔔 Notifications</h3>
      <div style="display:flex;gap:6px;align-items:center">
        <button class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
        <button class="notif-mark-all" onclick="closeNotifPanel()" style="color:var(--muted)">✕</button>
      </div>
    </div>
    <div class="notif-list" id="notifList">
      <div class="notif-empty"><div class="ne-icon">🔔</div>Loading…</div>
    </div>
  </div>

  <!-- Detail view -->
  <div class="notif-detail" id="notifDetail">
    <div class="notif-header">
      <button class="notif-mark-all" onclick="showNotifList()" style="color:var(--muted2)">← Back</button>
      <button class="notif-mark-all" onclick="closeNotifPanel()" style="color:var(--muted)">✕</button>
    </div>
    <div class="notif-detail-body" id="notifDetailBody"></div>
  </div>

</div>
    <?php if(isAdmin() || isManager()): ?>
    <div class="nav-section">Manage</div>
    <a href="settings.php" class="nav-link <?= $page==='settings'?'active':'' ?>" onclick="closeSidebar()"><span class="icon">⚙️</span><span>Settings</span></a>
    <?php endif; ?>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
      <div class="info">
        <div class="uname"><?= sanitize($user['name']) ?></div>
        <div class="urole"><?= ucfirst($user['role']) ?><?= (!empty($user['admin_access']) && $user['role']!=='admin') ? ' · Admin' : '' ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-link"><span>⬅</span><span>Sign out</span></a>
  </div>
</nav>

<script>
/* ── Sidebar ── */
function openSidebar(){
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.overflow='';
}

/* ── Service Worker ── */
if('serviceWorker' in navigator){
  window.addEventListener('load', ()=>{
    navigator.serviceWorker.register('sw.js')
      .then(reg=>{ console.log('SW registered', reg.scope); })
      .catch(err=>{ console.log('SW failed', err); });
  });
}
/* ── PWA Install prompt ── */
let deferredPrompt = null;

// This fires when browser decides app is installable
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  deferredPrompt = e;
  console.log('✅ Install prompt ready');
  const dismissedUntil = localStorage.getItem('pwaDismissedUntil');
  const installed      = localStorage.getItem('pwaInstalled');
  const dismissedToday = dismissedUntil && new Date().toDateString() === dismissedUntil;
  if (!installed && !dismissedToday) {
    setTimeout(() => document.getElementById('installBanner').classList.add('show'), 1500);
  }
});

document.getElementById('installBtn').addEventListener('click', async () => {
  const banner = document.getElementById('installBanner');
  if (!deferredPrompt) {
    // Browser didn't fire beforeinstallprompt — show manual instructions
    showManualInstall();
    return;
  }
  banner.classList.remove('show');
  deferredPrompt.prompt();
  const { outcome } = await deferredPrompt.userChoice;
  console.log('Install outcome:', outcome);
  if (outcome === 'accepted') {
    localStorage.setItem('pwaInstalled', '1');
  }
  deferredPrompt = null;
});

document.getElementById('dismissBtn').addEventListener('click', () => {
  document.getElementById('installBanner').classList.remove('show');
  localStorage.setItem('pwaDismissedUntil', new Date().toDateString());
});

window.addEventListener('appinstalled', () => {
  document.getElementById('installBanner').classList.remove('show');
  localStorage.setItem('pwaInstalled', '1');
  console.log('✅ PWA installed successfully');
});

// Manual install instructions when browser doesn't support auto-prompt
function showManualInstall() {
  const isIos     = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isAndroid = /android/i.test(navigator.userAgent);
  const isChrome  = /chrome/i.test(navigator.userAgent);
  const isEdge    = /edg/i.test(navigator.userAgent);
  let msg = '';
  if (isIos) {
    msg = '📲 On iPhone/iPad: tap the <strong>Share</strong> button (□↑) at the bottom of Safari, then tap <strong>"Add to Home Screen"</strong>';
  } else if (isAndroid && isChrome) {
    msg = '📲 Tap the <strong>3-dot menu</strong> (⋮) in Chrome, then tap <strong>"Add to Home screen"</strong> or <strong>"Install app"</strong>';
  } else if (isEdge) {
    msg = '💻 Click the <strong>install icon</strong> (⊕) in the address bar, or go to the 3-dot menu → <strong>"Apps" → "Install this site as an app"</strong>';
  } else if (isChrome) {
    msg = '💻 Click the <strong>install icon</strong> (⊕) in the address bar on the right side, then click <strong>Install</strong>';
  } else {
    msg = '📲 To install: open this page in <strong>Chrome</strong> or <strong>Edge</strong> and look for the install option in the browser menu';
  }
  document.getElementById('iosHint').innerHTML = `<p>${msg}</p><button onclick="document.getElementById('iosHint').classList.remove('show')">Got it</button>`;
  document.getElementById('iosHint').classList.add('show');
  document.getElementById('installBanner').classList.remove('show');
}

/* ── iOS install hint (Safari only) ── */
const isIosSafari = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.navigator.standalone;
if (isIosSafari && !localStorage.getItem('iosHintDismissed')) {
  setTimeout(() => {
    document.getElementById('iosHint').innerHTML = `
      <p>📲 To install on iPhone/iPad: tap <strong>Share</strong> (□↑) then <strong>"Add to Home Screen"</strong></p>
      <button onclick="this.parentElement.classList.remove('show');localStorage.setItem('iosHintDismissed','1')">Got it</button>
    `;
    document.getElementById('iosHint').classList.add('show');
  }, 3000);
}
/* ── Modal helpers ── */
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click', e=>{ if(e.target===o){ o.classList.remove('open'); document.body.style.overflow=''; }});
  });
});
</script>

<style>
/* ── Notification Bell ── */
.notif-btn{position:relative;background:none;border:none;color:var(--muted2);font-size:20px;cursor:pointer;padding:6px 8px;border-radius:9px;transition:.15s;-webkit-tap-highlight-color:transparent;display:flex;align-items:center}
.notif-btn:hover,.notif-btn:active{background:var(--surface2);color:var(--text)}
.notif-badge{position:absolute;top:2px;right:2px;min-width:17px;height:17px;background:var(--expense);color:#fff;border-radius:20px;font-size:10px;font-weight:700;display:none;align-items:center;justify-content:center;padding:0 4px;line-height:1;border:2px solid var(--surface)}
.notif-badge.show{display:flex}

/* ── Notification Panel ── */
.notif-panel{display:none;position:fixed;top:60px;right:16px;width:360px;max-width:calc(100vw - 32px);background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.5);z-index:500;flex-direction:column;max-height:520px;overflow:hidden}
.notif-panel.open{display:flex}
.notif-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.notif-header h3{font-size:14px;font-weight:500}
.notif-mark-all{font-size:12px;color:var(--accent);background:none;border:none;cursor:pointer;font-family:inherit;padding:4px 8px;border-radius:6px}
.notif-mark-all:hover{background:var(--accent-dim)}
.notif-list{overflow-y:auto;flex:1}
.notif-item{display:flex;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border);cursor:pointer;transition:.15s;position:relative}
.notif-item:hover{background:var(--surface2)}
.notif-item.unread{background:rgba(59,130,246,.05)}
.notif-item.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent);border-radius:0 3px 3px 0}
.notif-icon{width:36px;height:36px;border-radius:10px;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;margin-top:2px}
.notif-body{flex:1;min-width:0}
.notif-title{font-size:13px;font-weight:500;color:var(--text);margin-bottom:3px}
.notif-msg{font-size:12px;color:var(--muted);line-height:1.5;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.notif-meta{font-size:11px;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:6px}
.notif-role{padding:1px 7px;border-radius:20px;font-size:10px;font-weight:500}
.role-manager{background:var(--accent-dim);color:var(--accent)}
.role-user{background:rgba(100,116,139,.12);color:var(--muted2)}
.notif-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.notif-empty .ne-icon{font-size:32px;margin-bottom:10px}
.notif-overlay{display:none;position:fixed;inset:0;z-index:499}
.notif-overlay.open{display:block}

/* Detail view inside panel */
.notif-detail{display:none;flex-direction:column;flex:1;overflow:hidden}
.notif-detail.open{display:flex}
.notif-detail-body{padding:18px;overflow-y:auto;flex:1}
.notif-detail-title{font-size:15px;font-weight:500;color:var(--text);margin-bottom:8px}
.notif-detail-msg{font-size:13px;color:var(--muted2);line-height:1.7;margin-bottom:14px}
.notif-detail-meta{display:flex;flex-direction:column;gap:6px}
.notif-detail-row{display:flex;gap:8px;font-size:12px}
.notif-detail-row .dl{color:var(--muted);width:60px;flex-shrink:0}
.notif-detail-row .dv{color:var(--text);font-weight:500}
@media(max-width:640px){
  .notif-panel{top:0;right:0;left:0;width:100%;max-width:100%;border-radius:0 0 16px 16px;max-height:70vh}
}
</style>

<?php
require_once __DIR__ . '/notifications.php';
$_notif_count = isAdmin() ? getUnreadCount($user['id']) : 0;
?>

<!-- Notification Panel -->
<div class="notif-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>
<div class="notif-panel" id="notifPanel">
  <div class="notif-header">
    <h3>🔔 Notifications</h3>
    <button class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
  </div>
  <div class="notif-list" id="notifList">
    <div class="notif-empty"><div class="ne-icon">🔔</div>Loading…</div>
  </div>
</div>

<!-- Bell button — injected into topbar -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  <?php if(isAdmin()): ?>
  // Try .topbar .actions first, fallback to just .topbar
  const actions = document.querySelector('.topbar .actions') || document.querySelector('.topbar');
  if (actions) {
    const bell = document.createElement('button');
    bell.className = 'notif-btn';
    bell.id = 'notifBtn';
    bell.setAttribute('aria-label','Notifications');
    bell.onclick = toggleNotifPanel;
    const count = <?= $_notif_count ?>;
    bell.innerHTML = `🔔<span class="notif-badge" id="notifBadge">${count > 0 ? Math.min(count,99) : ''}</span>`;
    if (count > 0) bell.querySelector('.notif-badge').classList.add('show');
    if (document.querySelector('.topbar .actions')) {
      document.querySelector('.topbar .actions').prepend(bell);
    } else {
      document.querySelector('.topbar').appendChild(bell);
    }
  }
  <?php endif; ?>
});
</script>

<script>
/* ── Notification Panel JS ── */
let notifOpen = false;

function toggleNotifPanel() {
  notifOpen ? closeNotifPanel() : openNotifPanel();
}

function openNotifPanel() {
  notifOpen = true;
  document.getElementById('notifPanel').classList.add('open');
  document.getElementById('notifOverlay').classList.add('open');
  loadNotifications();
}
function closeNotifPanel() {
  notifOpen = false;
  document.getElementById('notifPanel').classList.remove('open');
  document.getElementById('notifOverlay').classList.remove('open');
  // Reset to list view for next open
  setTimeout(() => showNotifList(), 300);
}
let _notifData = [];

function loadNotifications() {
  const list = document.getElementById('notifList');
  list.innerHTML = '<div class="notif-empty"><div class="ne-icon" style="animation:spin 1s linear infinite;display:inline-block">🔄</div><br>Loading…</div>';
  fetch('api_notifications.php?action=list')
    .then(r => r.json())
    .then(data => {
      _notifData = data.items || [];
      updateBadge(data.count || 0);
      if (!_notifData.length) {
        list.innerHTML = '<div class="notif-empty"><div class="ne-icon">✅</div>All caught up! No notifications yet.</div>';
        return;
      }
      list.innerHTML = _notifData.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="openNotifDetail(${n.id})">
          <div class="notif-icon">${escHtml(n.icon)}</div>
          <div class="notif-body">
            <div class="notif-title">${escHtml(n.title)}</div>
            <div class="notif-msg">${escHtml(n.message)}</div>
            <div class="notif-meta">
              <span class="notif-role role-${escHtml(n.role)}">${escHtml(n.role)}</span>
              <span>${escHtml(n.actor)}</span>
              <span>·</span>
              <span>${escHtml(n.time)}</span>
            </div>
          </div>
          ${!n.is_read ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:6px"></div>' : ''}
        </div>
      `).join('');
    })
    .catch(() => {
      list.innerHTML = '<div class="notif-empty">⚠️ Could not load. Check connection.</div>';
    });
}

function openNotifDetail(id) {
  const n = _notifData.find(x => x.id === id);
  if (!n) return;

  // Mark read
  if (!n.is_read) {
    n.is_read = true;
    const fd = new FormData(); fd.append('id', id);
    fetch('api_notifications.php?action=mark_read', { method:'POST', body:fd });
    const badge = document.getElementById('notifBadge');
    if (badge) updateBadge(Math.max(0, (parseInt(badge.textContent)||0) - 1));
    const el = document.querySelector(`.notif-item[onclick="openNotifDetail(${id})"]`);
    if (el) { el.classList.remove('unread'); el.querySelector('div[style*="border-radius:50%"]')?.remove(); }
  }

  // Build detail
  const typeLabels = {
    'transaction_add':'Transaction Added','transaction_edit':'Transaction Edited',
    'transaction_delete':'Transaction Deleted','book_add':'Book Created',
    'book_edit':'Book Edited','book_delete':'Book Deleted',
    'client_add':'Client Added','book_member_add':'Member Added',
  };
  document.getElementById('notifDetailBody').innerHTML = `
    <div style="font-size:32px;margin-bottom:14px">${escHtml(n.icon)}</div>
    <div class="notif-detail-title">${escHtml(n.title)}</div>
    <div class="notif-detail-msg">${escHtml(n.message)}</div>
    <div class="notif-detail-meta">
      <div class="notif-detail-row"><span class="dl">Type</span><span class="dv">${escHtml(typeLabels[n.type]||n.type)}</span></div>
      <div class="notif-detail-row"><span class="dl">By</span><span class="dv">${escHtml(n.actor)}</span></div>
      <div class="notif-detail-row"><span class="dl">Role</span><span class="dv" style="text-transform:capitalize">${escHtml(n.role)}</span></div>
      <div class="notif-detail-row"><span class="dl">When</span><span class="dv">${escHtml(n.time)}</span></div>
    </div>
  `;

  // Switch views
  document.getElementById('notifListView').style.display = 'none';
  document.getElementById('notifDetail').style.display = 'flex';
}

function showNotifList() {
  document.getElementById('notifListView').style.display = 'flex';
  document.getElementById('notifDetail').style.display = 'none';
}
function markAllRead() {
  fetch('api_notifications.php?action=mark_all_read', { method: 'POST' })
    .then(() => {
      document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
      updateBadge(0);
    });
}

function updateBadge(count) {
  const badge = document.getElementById('notifBadge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.classList.add('show');
  } else {
    badge.textContent = '';
    badge.classList.remove('show');
  }
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

// Poll for new notifications every 30 seconds
<?php if(isAdmin()): ?>
setInterval(() => {
  if (notifOpen) { loadNotifications(); return; }
  fetch('api_notifications.php?action=count')
    .then(r => r.json())
    .then(d => updateBadge(d.count || 0))
    .catch(() => {});
}, 30000);
<?php endif; ?>
</script>
