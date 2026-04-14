<!DOCTYPE html>
<!-- layout.php — included by all dashboard pages -->
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireAuth();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$role        = $_SESSION['role'];
$name        = $_SESSION['name'];
?>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Pol Web GIS') ?> — Pol Web GIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
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
  --sidebar-w: 240px;
  --topbar-h: 56px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── SIDEBAR ────────────────────────── */
.sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sidebar-w);
  height: 100vh;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 100;
  transition: transform 0.3s ease;
}
.sidebar-header {
  padding: 20px 20px 16px;
  border-bottom: 1px solid var(--border);
}
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.brand-name {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -0.01em;
  line-height: 1.2;
}
.brand-sub {
  font-size: 10px;
  color: var(--text2);
  letter-spacing: 0.05em;
}

.nav-section {
  padding: 12px 12px 4px;
}
.nav-label {
  font-size: 9px;
  font-family: 'Space Mono', monospace;
  color: var(--muted);
  letter-spacing: 0.15em;
  text-transform: uppercase;
  padding: 0 8px;
  margin-bottom: 4px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 9px;
  text-decoration: none;
  color: var(--text2);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 2px;
  transition: all 0.15s;
  cursor: pointer;
}
.nav-item:hover {
  background: rgba(255,255,255,0.05);
  color: var(--text);
}
.nav-item.active {
  background: rgba(0,212,255,0.1);
  color: var(--accent);
  border: 1px solid rgba(0,212,255,0.2);
}
.nav-item svg {
  width: 16px; height: 16px;
  flex-shrink: 0;
}
.nav-badge {
  margin-left: auto;
  background: var(--danger);
  color: #fff;
  font-size: 10px;
  font-family: 'Space Mono', monospace;
  padding: 1px 6px;
  border-radius: 20px;
}

.sidebar-footer {
  margin-top: auto;
  padding: 16px;
  border-top: 1px solid var(--border);
}
.user-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: var(--surface2);
  border-radius: 10px;
  border: 1px solid var(--border);
}
.user-avatar {
  width: 30px; height: 30px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: #fff;
  flex-shrink: 0;
}
.user-info { flex: 1; min-width: 0; }
.user-name {
  font-size: 12px; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role { font-size: 10px; color: var(--text2); }
.logout-btn {
  background: none; border: none; cursor: pointer; color: var(--muted);
  padding: 4px; border-radius: 5px; transition: color 0.2s;
}
.logout-btn:hover { color: var(--danger); }

/* ── TOPBAR ────────────────────────── */
.topbar {
  position: fixed;
  top: 0;
  left: var(--sidebar-w);
  right: 0;
  height: var(--topbar-h);
  background: rgba(17,24,39,0.9);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 16px;
  z-index: 90;
}
.page-title {
  font-size: 15px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.topbar-icon-btn {
  width: 36px; height: 36px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--text2);
  transition: all 0.15s;
  position: relative;
}
.topbar-icon-btn:hover { border-color: var(--accent); color: var(--accent); }
.notif-dot {
  position: absolute;
  top: 6px; right: 6px;
  width: 7px; height: 7px;
  background: var(--danger);
  border-radius: 50%;
  border: 2px solid var(--surface);
}

/* ── MAIN ────────────────────────── */
.main {
  margin-left: var(--sidebar-w);
  margin-top: var(--topbar-h);
  padding: 24px;
  min-height: calc(100vh - var(--topbar-h));
}

/* ── CARDS & PANELS ────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
}
.card-title {
  font-size: 13px;
  font-family: 'Space Mono', monospace;
  color: var(--text2);
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title::before {
  content: '';
  display: inline-block;
  width: 3px; height: 14px;
  background: var(--accent);
  border-radius: 2px;
}

/* ── BUTTONS ────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  border-radius: 9px; border: none;
  font-family: 'Sora', sans-serif;
  font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all 0.15s;
  text-decoration: none;
}
.btn-primary { background: linear-gradient(135deg, var(--accent2), var(--accent)); color: #fff; }
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-secondary { background: var(--surface2); border: 1px solid var(--border); color: var(--text); }
.btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
.btn-danger { background: rgba(255,77,109,0.15); border: 1px solid rgba(255,77,109,0.3); color: var(--danger); }
.btn-danger:hover { background: rgba(255,77,109,0.25); }
.btn-success { background: rgba(0,200,150,0.15); border: 1px solid rgba(0,200,150,0.3); color: var(--accent3); }
.btn-warn { background: rgba(255,184,0,0.15); border: 1px solid rgba(255,184,0,0.3); color: var(--warn); }
.btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 7px; }
.btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 8px; }

/* ── FORM ELEMENTS ────────────────────────── */
.form-group { margin-bottom: 16px; }
.form-label {
  display: block;
  font-size: 11px;
  font-family: 'Space Mono', monospace;
  color: var(--text2);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

/* ────────────────────────────────────────────────────────────
   GLOBAL FORM INPUT + SELECT STYLES
   Fixes white-text-on-white-background dropdown bug
   ──────────────────────────────────────────────────────────── */
.form-input,
.filter-input,
.form-control {
  width: 100%;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 9px 12px;
  font-family: 'Sora', sans-serif;
  font-size: 13px;
  color: var(--text);
  -webkit-text-fill-color: var(--text); /* Safari / Chrome autofill fix */
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.form-input:focus,
.filter-input:focus,
.form-control:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(0,212,255,0.08);
}

/* Select-specific overrides */
select.form-input,
select.filter-input,
select.form-control,
select {
  cursor: pointer;
  color: var(--text) !important;
  -webkit-text-fill-color: var(--text) !important;
  background-color: var(--surface2) !important;
  /* Custom dropdown arrow — replaces browser default which ignores dark themes */
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%234a5a72' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 34px !important;
}

/* Option elements — dark background, readable text */
select option,
select.form-input option,
select.filter-input option,
select.form-control option {
  background-color: #1a2436 !important; /* --surface2 hex fallback */
  color: #e2eaf4 !important;            /* --text hex fallback */
  font-family: 'Sora', sans-serif;
}

/* Highlighted / selected option */
select option:checked,
select option:hover {
  background-color: #0057ff !important; /* --accent2 */
  color: #ffffff !important;
}

/* Disabled / placeholder options */
select option[value=""],
select option:disabled {
  color: #4a5a72 !important; /* --muted */
}

/* Textarea */
textarea.form-input,
textarea.filter-input,
textarea.form-control {
  resize: vertical;
  min-height: 70px;
  -webkit-text-fill-color: var(--text);
}

/* Filter input (search bars / compact selects) */
.filter-input {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
  font-family: 'Sora', sans-serif;
  font-size: 13px;
  color: var(--text);
  -webkit-text-fill-color: var(--text);
  outline: none;
}
.filter-input:focus { border-color: var(--accent); }

/* ── Spinner ────────────────────────── */
.spinner {
  width: 32px; height: 32px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 40px auto;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── MODAL ────────────────────────── */
.modal-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  width: 92%;
  max-height: 92vh;
  overflow-y: auto;
  transform: scale(0.95) translateY(10px);
  transition: transform 0.2s;
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  font-size: 15px; font-weight: 600;
}
.modal-header button {
  background: none; border: none; cursor: pointer;
  color: var(--muted); font-size: 20px; line-height: 1;
  transition: color 0.2s;
}
.modal-header button:hover { color: var(--danger); }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  z-index: 200;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.2s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 28px;
  width: 90%; max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  transform: scale(0.95) translateY(10px);
  transition: transform 0.2s;
}
.modal-overlay.open .modal { transform: scale(1) translateY(0); }
.modal-title { font-size: 16px; font-weight: 700; }
.modal-close {
  background: none; border: none; cursor: pointer;
  color: var(--muted); padding: 4px; border-radius: 6px; transition: color 0.2s;
}
.modal-close:hover { color: var(--danger); }

/* ── TABLE ────────────────────────── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
  padding: 10px 14px;
  text-align: left;
  font-size: 11px;
  font-family: 'Space Mono', monospace;
  color: var(--muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
tbody tr {
  border-bottom: 1px solid rgba(255,255,255,0.04);
  transition: background 0.1s;
}
tbody tr:hover { background: rgba(255,255,255,0.02); }
tbody td { padding: 10px 14px; color: var(--text2); }
tbody td:first-child { color: var(--text); }

/* ── BADGES ────────────────────────── */
.badge {
  display: inline-flex; align-items: center;
  padding: 2px 8px; border-radius: 20px;
  font-size: 11px; font-weight: 600;
  letter-spacing: 0.04em;
}
.badge-active, .badge-completed  { background: rgba(0,200,150,0.15); color: var(--accent3); }
.badge-pending, .badge-scheduled { background: rgba(255,184,0,0.15); color: var(--warn); }
.badge-inactive, .badge-cancelled { background: rgba(100,100,120,0.2); color: var(--muted); }
.badge-critical, .badge-disconnected { background: rgba(255,77,109,0.15); color: var(--danger); }
.badge-high    { background: rgba(255,140,0,0.15); color: #ff8c00; }
.badge-medium  { background: rgba(0,212,255,0.1);  color: var(--accent); }
.badge-low     { background: rgba(100,100,120,0.15); color: var(--text2); }
.badge-ongoing, .badge-in-progress { background: rgba(0,87,255,0.15); color: var(--accent2); }

/* ── ALERT/TOAST ────────────────────────── */
.toast-container {
  position: fixed; bottom: 24px; right: 24px;
  display: flex; flex-direction: column; gap: 10px;
  z-index: 300;
}
.toast {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 16px;
  display: flex; align-items: center; gap: 12px;
  min-width: 280px; max-width: 360px;
  font-size: 13px;
  animation: toastIn 0.3s ease;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.toast.success { border-color: rgba(0,200,150,0.3); }
.toast.error   { border-color: rgba(255,77,109,0.3); }
.toast.info    { border-color: rgba(0,212,255,0.3); }
.toast.warn    { border-color: rgba(255,184,0,0.3); }
.toast-icon { font-size: 18px; flex-shrink: 0; }
@keyframes toastIn {
  from { opacity: 0; transform: translateX(40px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ── PAGINATION ────────────────────────── */
.pagination {
  display: flex; align-items: center; gap: 6px;
  margin-top: 16px; font-size: 12px;
}
.page-btn {
  min-width: 32px; height: 32px;
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 8px; color: var(--text2);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.15s; font-size: 12px;
}
.page-btn:hover, .page-btn.active { border-color: var(--accent); color: var(--accent); }
.page-btn.active { background: rgba(0,212,255,0.1); }

/* ── MISC ────────────────────────── */
.grid { display: grid; gap: 16px; }
.grid-2 { grid-template-columns: repeat(2, 1fr); }
.grid-3 { grid-template-columns: repeat(3, 1fr); }
.grid-4 { grid-template-columns: repeat(4, 1fr); }
.flex { display: flex; }
.flex-center { align-items: center; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }
.ml-auto { margin-left: auto; }
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: var(--text2); }
.text-accent { color: var(--accent); }
.text-danger { color: var(--danger); }
.text-success { color: var(--accent3); }
.text-sm { font-size: 12px; }
.fw-600 { font-weight: 600; }

/* Stat card */
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px;
}
.stat-label {
  font-size: 11px;
  font-family: 'Space Mono', monospace;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 8px;
}
.stat-value {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1;
}
.stat-sub { font-size: 11px; color: var(--text2); margin-top: 4px; }

/* Search box */
.search-box { position: relative; }
.search-box svg {
  position: absolute; left: 12px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted); width: 14px; height: 14px;
  pointer-events: none;
}
.search-box input {
  width: 100%;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 8px 12px 8px 36px;
  font-family: 'Sora', sans-serif;
  font-size: 13px; color: var(--text);
  outline: none; transition: border-color 0.2s;
}
.search-box input:focus { border-color: var(--accent); }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.mobile-open { transform: translateX(0); }
  .main { margin-left: 0; }
  .topbar { left: 0; }
  .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ── SIDEBAR ────────────────────────── -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <div class="brand-icon">💧</div>
      <div>
        <div class="brand-name">Pol Web GIS</div>
        <div class="brand-sub">Water District</div>
      </div>
    </div>
  </div>

  <div style="overflow-y:auto;flex:1;padding-bottom:8px;">
    <div class="nav-section">
      <div class="nav-label">Main</div>
      <a href="dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="map.php" class="nav-item <?= $currentPage==='map'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        GIS Map
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Maintenance</div>
      <a href="work-orders.php" class="nav-item <?= $currentPage==='work-orders'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
        Work Orders
        <span class="nav-badge" id="woCount"></span>
      </a>
      <a href="equipment-history.php" class="nav-item <?= $currentPage==='equipment-history'?'active':'' ?>">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
  Equipment History
</a>
      <a href="schedules.php" class="nav-item <?= $currentPage==='schedules'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Schedules
      </a>
      <a href="alerts.php" class="nav-item <?= $currentPage==='alerts'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Deterioration Alerts
      </a>
      <a href="inventory.php" class="nav-item <?= $currentPage==='inventory'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        Inventory
      </a>
      <a href="infrastructure-list.php" class="nav-item <?= $currentPage==='infrastructure-list'?'active':'' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
    </svg>
    Infrastructure
</a>
    </div>
    
    <div class="nav-section">
      <div class="nav-label">Consumers</div>
      <a href="consumers.php" class="nav-item <?= $currentPage==='consumers'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Consumers
      </a>
      <a href="requests.php" class="nav-item <?= $currentPage==='requests'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        Request Portal
      </a>
      <a href="interruptions.php" class="nav-item <?= $currentPage==='interruptions'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.36 6.64a9 9 0 11-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
        Interruptions
      </a>
      <a href="communications.php" class="nav-item <?= $currentPage==='communications'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Comms History
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <a href="logs.php" class="nav-item <?= $currentPage==='logs'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        History Logs
      </a>
      <a href="import-export.php" class="nav-item <?= $currentPage==='import-export'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        CSV Import/Export
      </a>
      <?php if ($role === 'Admin'): ?>
      <a href="users.php" class="nav-item <?= $currentPage==='users'?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Users
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="user-pill">
      <div class="user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($name) ?></div>
        <div class="user-role"><?= htmlspecialchars($role) ?></div>
      </div>
      <button class="logout-btn" onclick="logout()" title="Logout">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
      </button>
    </div>
  </div>
</nav>

<!-- ── TOPBAR ────────────────────────── -->
<header class="topbar">
  <button id="menuBtn" style="display:none;background:none;border:none;cursor:pointer;color:var(--text2);margin-right:8px;" onclick="document.getElementById('sidebar').classList.toggle('mobile-open')">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <span class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
  <div class="topbar-actions">
    <div class="topbar-icon-btn" id="notifBtn" title="Notifications" onclick="toggleNotifPanel()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span class="notif-dot" id="notifDot" style="display:none;"></span>
    </div>
    <a href="change-password.php" class="topbar-icon-btn" title="Settings">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
    </a>
  </div>
</header>

<!-- ── TOAST CONTAINER ────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ── Shared utilities ────────────────────────────
async function apiGet(endpoint, params = {}) {
  const url = new URL('../../backend/api/' + endpoint, window.location.href);
  Object.keys(params).forEach(k => url.searchParams.set(k, params[k]));
  const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  return res.json();
}

async function apiPost(endpoint, data = {}) {
  const fd = new FormData();
  Object.keys(data).forEach(k => fd.append(k, data[k]));
  const res = await fetch('../../backend/api/' + endpoint, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  return res.json();
}

async function apiJson(endpoint, data = {}, method = 'POST') {
  const res = await fetch('../../backend/api/' + endpoint, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify(data)
  });
  return res.json();
}

function showToast(message, type = 'info') {
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warn: '⚠️' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type] || '•'}</span><span>${message}</span>`;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

async function logout() {
  await apiPost('auth.php', { action: 'logout' });
  window.location.href = 'login.php';
}

function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Load pending work orders count
async function loadWOCount() {
  try {
    const r = await apiGet('maintenance.php', { action: 'get_work_orders', status: 'Pending' });
    const count = (r.data || []).filter(w => ['Critical','High'].includes(w.priority)).length;
    const badge = document.getElementById('woCount');
    if (badge && count > 0) { badge.textContent = count; }
    else if (badge) badge.style.display = 'none';
  } catch {}
}
loadWOCount();

// Notification dot
async function checkNotifications() {
  try {
    const r = await apiGet('consumer.php', { action: 'get_notifications' });
    const unread = (r.data || []).filter(n => !n.is_read).length;
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = unread > 0 ? 'block' : 'none';
  } catch {}
}
checkNotifications();

function toggleNotifPanel() {
  showToast('Check the Notifications panel', 'info');
}

// Mobile menu button
if (window.innerWidth <= 768) {
  document.getElementById('menuBtn').style.display = 'block';
}
window.addEventListener('resize', () => {
  const btn = document.getElementById('menuBtn');
  if (btn) btn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
});

document.addEventListener('click', (e) => {
  const sidebar = document.getElementById('sidebar');
  if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open') && !sidebar.contains(e.target)) {
    sidebar.classList.remove('mobile-open');
  }
});
</script>
