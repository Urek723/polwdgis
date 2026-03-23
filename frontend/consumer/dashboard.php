<?php
$pageTitle = 'Dashboard';
require_once 'layout.php';
?>
<div class="page-wrap">
  <div style="margin-bottom:20px">
    <h2 style="font-size:20px;font-weight:700">Welcome, <?= htmlspecialchars($consumer['name']) ?> 👋</h2>
    <p style="font-size:13px;color:var(--muted);margin-top:4px">Account No: <?= htmlspecialchars($consumer['account_number']) ?></p>
  </div>

  <!-- Quick actions -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:24px">
    <a href="report.php" style="text-decoration:none">
      <div class="card" style="cursor:pointer;transition:border-color .2s;text-align:center;padding:24px 16px" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:32px;margin-bottom:10px">📍</div>
        <div style="font-weight:600;margin-bottom:4px">Report Issue</div>
        <div style="font-size:12px;color:var(--muted)">Leak, low pressure, no water</div>
      </div>
    </a>
    <a href="track.php" style="text-decoration:none">
      <div class="card" style="cursor:pointer;transition:border-color .2s;text-align:center;padding:24px 16px" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:32px;margin-bottom:10px">📋</div>
        <div style="font-weight:600;margin-bottom:4px">Track Requests</div>
        <div style="font-size:12px;color:var(--muted)">Check your request status</div>
      </div>
    </a>
    <a href="inquiry.php" style="text-decoration:none">
      <div class="card" style="cursor:pointer;transition:border-color .2s;text-align:center;padding:24px 16px" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:32px;margin-bottom:10px">✉️</div>
        <div style="font-weight:600;margin-bottom:4px">Send Inquiry</div>
        <div style="font-size:12px;color:var(--muted)">Questions & concerns</div>
      </div>
    </a>
  </div>

  <!-- Active interruptions -->
  <div class="card">
    <div class="card-title">⚠️ Active Water Interruptions</div>
    <div id="intrList"><div class="spinner"></div></div>
  </div>

  <!-- Recent requests -->
  <div class="card">
    <div class="card-title">📋 My Recent Requests</div>
    <div id="recentList"><div class="spinner"></div></div>
    <a href="track.php" style="font-size:13px;color:var(--accent);text-decoration:none">View all →</a>
  </div>
</div>

<script>
async function loadInterruptions() {
  const d   = await apiGet(CONSUMER_API, { action: 'get_interruptions' });
  const el  = document.getElementById('intrList');
  const list = d?.data || [];
  if (!list.length) { el.innerHTML = '<p style="color:var(--muted);font-size:13px">No active interruptions at this time.</p>'; return; }
  el.innerHTML = list.map(i => `
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:8px">
      <div style="font-weight:600;margin-bottom:4px">${i.title}</div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:4px">📍 ${i.affected_barangays || '—'}</div>
      <div style="font-size:12px;color:var(--muted)">🕐 ${i.start_datetime} → ${i.end_datetime || 'TBD'}</div>
    </div>`).join('');
}

async function loadRecent() {
  const d   = await apiGet(CONSUMER_API, { action: 'get_my_requests' });
  const el  = document.getElementById('recentList');
  const list = (d?.data || []).slice(0, 3);
  if (!list.length) { el.innerHTML = '<p style="color:var(--muted);font-size:13px;margin-bottom:10px">No requests yet.</p>'; return; }
  const statusKey = s => s.toLowerCase().replace(/\s+/g, '');
  el.innerHTML = list.map(r => `
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-weight:600;font-size:13px">${r.request_type}</div>
        <div style="font-size:12px;color:var(--muted)">${r.created_at}</div>
      </div>
      <span class="badge badge-${statusKey(r.status)}">${r.status}</span>
    </div>`).join('');
}

loadInterruptions();
loadRecent();
</script>