<!DOCTYPE html>
<?php
require_once __DIR__ . '/auth_guard.php';
requireConsumerAuth();
$consumer = getConsumerSession();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
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
  padding: 0 20px; gap: 16px;
  z-index: 100;
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 15px; }
.brand-icon { width: 34px; height: 34px; background: linear-gradient(135deg,var(--accent2),var(--accent)); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.nav-links { display: flex; gap: 4px; margin-left: auto; }
.nav-link {
  padding: 7px 14px; border-radius: 8px; text-decoration: none;
  color: var(--text2); font-size: 13px; font-weight: 500;
  transition: all .15s;
}
.nav-link:hover { background: rgba(255,255,255,.05); color: var(--text); }
.nav-link.active { background: rgba(0,212,255,.1); color: var(--accent); }
.nav-user { font-size: 12px; color: var(--muted); margin-left: 8px; }
.nav-logout { background: none; border: 1px solid var(--border); border-radius: 8px; color: var(--text2); font-size: 12px; padding: 6px 12px; cursor: pointer; font-family: 'Sora', sans-serif; transition: all .15s; }
.nav-logout:hover { border-color: var(--danger); color: var(--danger); }

.page-wrap { margin-top: var(--nav-h); padding: 28px 20px; max-width: 900px; margin-left: auto; margin-right: auto; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
.card-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; color: var(--accent); }

.form-input, select.form-input {
  width: 100%; background: var(--surface2); border: 1px solid var(--border);
  border-radius: 9px; padding: 10px 12px; font-family: 'Sora', sans-serif;
  font-size: 13px; color: var(--text); outline: none;
  transition: border-color .2s;
}
.form-input:focus { border-color: var(--accent); }
select.form-input { appearance: none; cursor: pointer; }

.btn-primary {
  background: linear-gradient(135deg,var(--accent2),var(--accent));
  border: none; border-radius: 9px; color: #fff;
  font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600;
  padding: 10px 20px; cursor: pointer; transition: opacity .15s;
}
.btn-primary:hover { opacity: .9; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }

.btn-secondary {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 9px; color: var(--text); font-family: 'Sora', sans-serif;
  font-size: 13px; padding: 10px 20px; cursor: pointer; transition: all .15s;
}
.btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

.badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-Submitted, .badge-submitted    { background: rgba(100,100,120,.2); color: var(--text2); }
.badge-pending    { background: rgba(255,184,0,.15); color: var(--warn); }
.badge-inprogress { background: rgba(0,87,255,.15); color: var(--accent2); }
.badge-resolved   { background: rgba(0,200,150,.15); color: var(--accent3); }
.badge-closed     { background: rgba(100,100,120,.2); color: var(--muted); }

.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; margin: 30px auto; }
@keyframes spin { to { transform: rotate(360deg); } }

.toast-container { position: fixed; bottom: 24px; right: 24px; display: flex; flex-direction: column; gap: 8px; z-index: 9999; }
.toast { background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 10px 16px; font-size: 13px; min-width: 240px; animation: toastIn .3s ease; }
.toast.success { border-color: rgba(0,200,150,.4); }
.toast.error   { border-color: rgba(255,77,109,.4); }
@keyframes toastIn { from { opacity:0; transform: translateX(30px); } to { opacity:1; transform: translateX(0); } }

label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 5px; }

@media (max-width: 600px) {
  .nav-links .nav-link span { display: none; }
  .page-wrap { padding: 16px 12px; }
}
</style>
</head>
<body>

<nav class="topnav">
  <div class="nav-brand">
    <div class="brand-icon">💧</div>
    <span>Consumer Portal</span>
  </div>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>">🏠 <span>Home</span></a>
    <a href="report.php"    class="nav-link <?= $currentPage==='report'?'active':'' ?>">📍 <span>Report</span></a>
    <a href="track.php"     class="nav-link <?= $currentPage==='track'?'active':'' ?>">📋 <span>Track</span></a>
    <a href="inquiry.php"   class="nav-link <?= $currentPage==='inquiry'?'active':'' ?>">✉️ <span>Inquiry</span></a>
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
  Object.entries(data).forEach(([k,v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
  const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  return res.json();
}

async function apiGet(url, params = {}) {
  const u = new URL(url, window.location.href);
  Object.entries(params).forEach(([k,v]) => u.searchParams.set(k, v));
  const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  return res.json();
}

function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = (type === 'success' ? '✅ ' : type === 'error' ? '❌ ' : 'ℹ️ ') + msg;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

async function logout() {
  await apiPost(AUTH_API, { action: 'logout' });
  window.location.href = 'login.php';
}
</script>
</body>
</html>