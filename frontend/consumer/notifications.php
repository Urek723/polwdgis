<?php
$pageTitle = 'Notifications';
require_once 'layout.php';
?>
<div class="page-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px">
    <h2 style="font-size:18px;font-weight:700">🔔 My Notifications</h2>
    <button onclick="markAllRead()" class="btn-secondary" id="markAllBtn" style="font-size:13px;padding:7px 14px">
      ✓ Mark All Read
    </button>
  </div>

  <div id="notifStats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:20px"></div>

  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <select id="filterType" onchange="loadNotifications()" class="form-input" style="width:auto">
        <option value="">All Types</option>
        <option value="interruption">Interruptions</option>
        <option value="alert">Alerts</option>
        <option value="message">Messages</option>
        <option value="system">System</option>
        <option value="reminder">Reminders</option>
      </select>
      <select id="filterRead" onchange="loadNotifications()" class="form-input" style="width:auto">
        <option value="">All</option>
        <option value="0">Unread</option>
        <option value="1">Read</option>
      </select>
    </div>
    <div id="notifList"><div class="spinner"></div></div>
  </div>
</div>

<style>
.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  margin-bottom: 8px;
  background: var(--surface2);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.notif-item:hover { border-color: var(--accent); }
.notif-item.unread {
  background: rgba(0,87,255,0.04);
  border-left: 3px solid var(--accent);
}
.notif-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.notif-icon.interruption { background: rgba(255,184,0,0.15); }
.notif-icon.alert        { background: rgba(255,77,109,0.15); }
.notif-icon.message      { background: rgba(0,87,255,0.15); }
.notif-icon.system       { background: rgba(0,200,150,0.15); }
.notif-icon.reminder     { background: rgba(100,100,200,0.15); }
.notif-content { flex: 1; min-width: 0; }
.notif-title { font-weight: 600; font-size: 14px; margin-bottom: 3px; }
.notif-message { font-size: 12px; color: var(--text2); line-height: 1.5; }
.notif-time { font-size: 11px; color: var(--muted); margin-top: 5px; }
.unread-dot {
  width: 8px; height: 8px;
  background: var(--accent);
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 6px;
}
</style>

<script>
const PORTAL_API = '../../backend/api/consumer_portal.php';

const TYPE_ICONS = {
  interruption: { icon: '🚧', class: 'interruption' },
  alert:        { icon: '⚠️',  class: 'alert' },
  message:      { icon: '💬',  class: 'message' },
  system:       { icon: '⚙️',  class: 'system' },
  reminder:     { icon: '🔔',  class: 'reminder' },
};

async function loadNotifications() {
  const type    = document.getElementById('filterType').value;
  const isRead  = document.getElementById('filterRead').value;
  const params  = { action: 'get_my_notifications' };
  if (type)   params.type    = type;
  if (isRead !== '') params.is_read = isRead;

  const d    = await apiGet(PORTAL_API, params);
  const list = d?.data || [];
  const el   = document.getElementById('notifList');

  renderStats(list);

  if (!list.length) {
    el.innerHTML = `
      <div style="text-align:center;padding:40px 0">
        <div style="font-size:48px;margin-bottom:12px">🔔</div>
        <p style="color:var(--muted);font-size:14px">No notifications found.</p>
      </div>`;
    return;
  }

  el.innerHTML = list.map(n => {
    const ti = TYPE_ICONS[n.type] || { icon: '📢', class: 'system' };
    const isUnread = !parseInt(n.is_read);
    return `
      <div class="notif-item ${isUnread ? 'unread' : ''}" onclick="markRead(${n.id}, this)">
        <div class="notif-icon ${ti.class}">${ti.icon}</div>
        <div class="notif-content">
          <div class="notif-title">${escapeHtml(n.title)}</div>
          <div class="notif-message">${escapeHtml(n.message || '')}</div>
          <div class="notif-time">${formatTime(n.created_at)}</div>
        </div>
        ${isUnread ? '<div class="unread-dot"></div>' : ''}
      </div>`;
  }).join('');
}

function renderStats(list) {
  const unread = list.filter(n => !parseInt(n.is_read)).length;
  const total  = list.length;
  document.getElementById('notifStats').innerHTML = `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--accent)">${total}</div>
      <div style="font-size:11px;color:var(--muted)">Total</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:${unread ? 'var(--danger)' : 'var(--accent3)'}">${unread}</div>
      <div style="font-size:11px;color:var(--muted)">Unread</div>
    </div>`;

  // Update bell badge in nav
  const badge = document.getElementById('navNotifBadge');
  if (badge) {
    badge.textContent  = unread > 0 ? unread : '';
    badge.style.display = unread > 0 ? 'inline-flex' : 'none';
  }
}

async function markRead(notifId, el) {
  el.classList.remove('unread');
  const dot = el.querySelector('.unread-dot');
  if (dot) dot.remove();

  await apiPost(PORTAL_API, { action: 'mark_notification_read', id: notifId });
  await loadNotifications();
}

async function markAllRead() {
  const btn = document.getElementById('markAllBtn');
  btn.disabled = true; btn.textContent = 'Marking…';
  await apiPost(PORTAL_API, { action: 'mark_all_notifications_read' });
  btn.disabled = false; btn.textContent = '✓ Mark All Read';
  loadNotifications();
  showToast('All notifications marked as read', 'success');
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d    = new Date(dateStr.replace(' ', 'T'));
  const now  = new Date();
  const diff = Math.floor((now - d) / 1000);
  if (diff < 60)    return 'Just now';
  if (diff < 3600)  return `${Math.floor(diff / 60)} minutes ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

loadNotifications();
</script>