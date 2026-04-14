<!DOCTYPE html>
<?php
require_once __DIR__ . '/auth_guard.php';
requireConsumerAuth();
$consumer    = getConsumerSession();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Fetch unread notification count for badge
$unreadCount = 0;
try {
    $db   = getDB();
    // Check if consumer_auth_id column exists
    $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('consumer_auth_id', $cols)) {
        $nStmt = $db->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE consumer_auth_id = ? AND is_read = 0"
        );
        $nStmt->execute([$consumer['id']]);
        $unreadCount = (int)$nStmt->fetchColumn();
    }
} catch (Throwable $e) {
    // Non-fatal — badge just won't show
}
?>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Consumer Portal') ?> — Pol Web GIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<style>
:root {
  --bg: #0c1120;
  --surface: #111827;
  --surface2: #1a2436;
  --border: #1e2d40;
  --accent: #00d4ff;
  --accent2: #0057ff;
  --accent3: #00c896;
  --warn: #ffb800;
  --danger: #ff4d6d;
  --text: #e2eaf4;
  --text2: #94a3b8;
  --muted: #4a5a72;
  --nav-h: 60px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

.topnav {
  position: fixed; top: 0; left: 0; right: 0;
  height: var(--nav-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  padding: 0 16px; gap: 10px;
  z-index: 100;
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 15px; text-decoration: none; color: var(--text); }
.brand-icon { width: 34px; height: 34px; background: linear-gradient(135deg,var(--accent2),var(--accent)); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.nav-links { display: flex; gap: 2px; margin-left: auto; }
.nav-link {
  padding: 7px 12px; border-radius: 8px; text-decoration: none;
  color: var(--text2); font-size: 13px; font-weight: 500;
  transition: all .15s; position: relative;
}
.nav-link:hover { background: rgba(255,255,255,.05); color: var(--text); }
.nav-link.active { background: rgba(0,212,255,.1); color: var(--accent); }
.nav-user { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.nav-logout { background: none; border: 1px solid var(--border); border-radius: 8px; color: var(--text2); font-size: 12px; padding: 6px 12px; cursor: pointer; font-family: 'Sora', sans-serif; transition: all .15s; white-space: nowrap; }
.nav-logout:hover { border-color: var(--danger); color: var(--danger); }

/* Notification badge */
.notif-badge {
  position: absolute;
  top: 2px; right: 2px;
  background: var(--danger);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  min-width: 16px;
  height: 16px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  line-height: 1;
}

.page-wrap { margin-top: var(--nav-h); padding: 24px 16px; max-width: 900px; margin-left: auto; margin-right: auto; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
.card-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; color: var(--accent); }

.form-input, select.form-input, textarea.form-input {
  width: 100%; background: var(--surface2); border: 1px solid var(--border);
  border-radius: 9px; padding: 10px 12px; font-family: 'Sora', sans-serif;
  font-size: 13px; color: var(--text); outline: none;
  transition: border-color .2s; -webkit-text-fill-color: var(--text);
}
.form-input:focus, textarea.form-input:focus { border-color: var(--accent); }
select.form-input {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%234a5a72' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-color: var(--surface2) !important;
  padding-right: 32px !important;
}
select.form-input option { background-color: #1a2436; color: #e2eaf4; }

.btn-primary {
  background: linear-gradient(135deg,var(--accent2),var(--accent));
  border: none; border-radius: 9px; color: #fff;
  font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600;
  padding: 10px 20px; cursor: pointer; transition: opacity .15s; text-decoration: none; display: inline-flex; align-items: center;
}
.btn-primary:hover { opacity: .9; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }

.btn-secondary {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 9px; color: var(--text); font-family: 'Sora', sans-serif;
  font-size: 13px; padding: 10px 20px; cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center;
}
.btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

.badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-Submitted, .badge-submitted   { background: rgba(100,100,120,.2); color: var(--text2); }
.badge-underreview                   { background: rgba(255,184,0,.15); color: var(--warn); }
.badge-pending                       { background: rgba(255,184,0,.15); color: var(--warn); }
.badge-inprogress                    { background: rgba(0,87,255,.15); color: var(--accent2); }
.badge-resolved                      { background: rgba(0,200,150,.15); color: var(--accent3); }
.badge-closed                        { background: rgba(100,100,120,.2); color: var(--muted); }

.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; margin: 30px auto; }
@keyframes spin { to { transform: rotate(360deg); } }

.toast-container { position: fixed; bottom: 24px; right: 24px; display: flex; flex-direction: column; gap: 8px; z-index: 9999; }
.toast { background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 10px 16px; font-size: 13px; min-width: 240px; animation: toastIn .3s ease; }
.toast.success { border-color: rgba(0,200,150,.4); }
.toast.error   { border-color: rgba(255,77,109,.4); }
@keyframes toastIn { from { opacity:0; transform: translateX(30px); } to { opacity:1; transform: translateX(0); } }

label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 5px; }

@media (max-width: 640px) {
  .nav-links .nav-link span { display: none; }
  .nav-user { display: none; }
  .page-wrap { padding: 14px 10px; }
}
</style>
</head>
<body>

<nav class="topnav">
  <a href="dashboard.php" class="nav-brand">
    <div class="brand-icon">💧</div>
    <span>Consumer Portal</span>
  </a>
  <div class="nav-links">
    <a href="dashboard.php"   class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>">🏠 <span>Home</span></a>
    <a href="report.php"      class="nav-link <?= $currentPage==='report'?'active':'' ?>">📍 <span>Report</span></a>
    <a href="track.php"       class="nav-link <?= $currentPage==='track'?'active':'' ?>">📋 <span>Track</span></a>
    <a href="inquiry.php"     class="nav-link <?= $currentPage==='inquiry'?'active':'' ?>">✉️ <span>Inquiry</span></a>
    <a href="chatbot.php"     class="nav-link <?= $currentPage==='chatbot'?'active':'' ?>">🤖 <span>Chat</span></a>
    <a href="notifications.php" class="nav-link <?= $currentPage==='notifications'?'active':'' ?>" style="position:relative">
      🔔 <span>Alerts</span>
      <?php if ($unreadCount > 0): ?>
      <span class="notif-badge" id="navNotifBadge"><?= min($unreadCount, 99) ?></span>
      <?php else: ?>
      <span class="notif-badge" id="navNotifBadge" style="display:none"></span>
      <?php endif; ?>
    </a>
  </div>
  <span class="nav-user">👤 <?= htmlspecialchars($consumer['name']) ?></span>
  <button class="nav-logout" onclick="logout()">Logout</button>
</nav>

<div class="toast-container" id="toastContainer"></div>

<script>
const CONSUMER_API = '../../backend/api/consumer_portal.php';
const AUTH_API     = '../../backend/api/consumer_auth.php';

async function apiPost(url, data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => {
    if (v !== undefined && v !== null) fd.append(k, v);
  });
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    return err;
  }
  return res.json().catch(() => ({}));
}

async function apiGet(url, params = {}) {
  const u = new URL(url, window.location.href);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null) u.searchParams.set(k, v);
  });
  const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    return err;
  }
  return res.json().catch(() => ({}));
}

function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = (type === 'success' ? '✅ ' : type === 'error' ? '❌ ' : 'ℹ️ ') + msg;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

async function logout() {
  await apiPost(AUTH_API, { action: 'logout' });
  window.location.href = 'login.php';
}

// Poll for unread notification count every 60 seconds
async function refreshNotifBadge() {
  try {
    const d = await apiGet(CONSUMER_API, { action: 'get_unread_count' });
    const count = d?.count ?? 0;
    const badge = document.getElementById('navNotifBadge');
    if (badge) {
      badge.textContent   = count > 0 ? Math.min(count, 99) : '';
      badge.style.display = count > 0 ? 'inline-flex' : 'none';
    }
  } catch {}
}

// Initial + periodic refresh
document.addEventListener('DOMContentLoaded', () => {
  refreshNotifBadge();
  setInterval(refreshNotifBadge, 60000);
});
</script>
</body>
</html>