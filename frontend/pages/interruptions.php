<?php
$pageTitle = 'Water Interruptions';
require_once 'layout.php';
?>
<style>
.int-card{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:10px}
.Scheduled{border-left:4px solid var(--accent2)}.Ongoing{border-left:4px solid var(--danger);animation:pulse 1.5s infinite}
.Resolved{border-left:4px solid var(--accent3)}
.s-Scheduled{background:var(--accent2)22;color:var(--accent2)}.s-Ongoing{background:#dc262622;color:#dc2626}
.s-Resolved{background:#16a34a22;color:#16a34a}
.stat-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600}
@keyframes pulse{0%,100%{border-left-color:var(--danger)}50%{border-left-color:#ff000066}}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openModal('mint')">+ Schedule Interruption</button>
  <?php endif; ?>
  <select id="fs" onchange="load()" class="filter-input"><option value="">All Statuses</option><option>Scheduled</option><option>Ongoing</option><option>Resolved</option></select>
</div>
<div id="intStats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:18px"></div>
<div id="intList"><div class="spinner"></div></div>

<div id="mint" class="modal-overlay">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header"><h3>Schedule Water Interruption</h3><button onclick="closeModal('mint')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="ititle" placeholder="Title *" class="form-input">
      <textarea id="idesc" placeholder="Description (reason, affected areas, etc.)" class="form-input" rows="3"></textarea>
      <input id="ibarangays" placeholder="Affected Barangays (comma-separated) *" class="form-input">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div><label style="font-size:12px;color:var(--muted)">Start Date/Time</label>
          <input id="istart" type="datetime-local" class="form-input" style="margin-top:4px"></div>
        <div><label style="font-size:12px;color:var(--muted)">End Date/Time</label>
          <input id="iend" type="datetime-local" class="form-input" style="margin-top:4px"></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--warn);border-radius:8px;padding:10px;font-size:13px;color:var(--warn)">
        📢 Sending this interruption will push notifications to all active consumers in the affected barangays.
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mint')" class="btn-secondary">Cancel</button>
        <button onclick="submitInt()" class="btn-primary">Schedule & Notify</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
async function load(){
  const status=document.getElementById('fs').value;
  const d=await apiGet('consumer.php',{action:'get_interruptions',status});
  const ints=d?.interruptions||d?.data||[];
  const el=document.getElementById('intList');
  renderStats(ints);
  if(!ints.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No interruptions found.</p>';return;}
  el.innerHTML=ints.map(i=>`
    <div class="int-card ${i.status}">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;gap:8px">
        <div style="font-weight:600;font-size:15px">${i.title}</div>
        <span class="stat-badge s-${i.status}">${i.status}</span>
      </div>
      <p style="font-size:13px;color:var(--text2);margin-bottom:10px">${i.description||''}</p>
      <div style="font-size:12px;color:var(--muted);display:grid;gap:3px">
        <div>🗺 Affected: ${i.affected_barangays||'—'}</div>
        <div>⏰ ${i.start_datetime||'—'} → ${i.end_datetime||'TBD'}</div>
        ${i.notification_sent?'<div style="color:var(--accent3)">📢 Notifications sent</div>':''}
      </div>
      <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        ${i.status!=='Resolved'?`<button onclick="changeStatus(${i.id},'Resolved')" class="btn-secondary" style="font-size:12px">✓ Mark Resolved</button>`:''}
        ${i.status==='Scheduled'?`<button onclick="changeStatus(${i.id},'Ongoing')" class="btn-secondary" style="font-size:12px">▶ Start</button>`:''}
        ${!i.notification_sent?`<button onclick="sendNotif(${i.id})" class="btn-primary" style="font-size:12px">📢 Send Notifications</button>`:''}
      </div>
      <?php endif; ?>
    </div>`).join('');
}

function renderStats(ints){
  const s={Scheduled:0,Ongoing:0,Resolved:0};
  ints.forEach(i=>s[i.status]=(s[i.status]||0)+1);
  document.getElementById('intStats').innerHTML=Object.entries(s).map(([k,v])=>`
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:${k==='Ongoing'?'var(--danger)':k==='Scheduled'?'var(--accent2)':'var(--accent3)'}">${v}</div>
      <div style="font-size:11px;color:var(--muted)">${k}</div>
    </div>`).join('');
}

async function changeStatus(id, status){
  const r=await apiPost('consumer.php',{action:'save_interruption',id,status});
  if(r?.success||r?.ok){showToast('Status updated','success');load();}
  else showToast(r?.error||'Failed','error');
}

async function sendNotif(id){
  const r=await apiPost('consumer.php',{action:'send_interruption',id});
  if(r?.success){showToast(`Notifications sent to ${r.notified||'all'} consumers`,'success');load();}
  else showToast(r?.error||'Failed','error');
}

async function submitInt(){
  const title=document.getElementById('ititle').value;
  const barangays=document.getElementById('ibarangays').value;
  if(!title||!barangays){showToast('Title and barangays are required','error');return;}
  const r=await apiPost('consumer.php',{action:'save_interruption',
    title,description:document.getElementById('idesc').value,
    affected_barangays:barangays,
    start_datetime:document.getElementById('istart').value,
    end_datetime:document.getElementById('iend').value});
  if(r?.success||r?.id){
    closeModal('mint');
    const notif=await apiPost('consumer.php',{action:'send_interruption',id:r.id});
    load();
    showToast(`Interruption scheduled. Notifications sent to ${notif?.notified||0} consumers`,'success');
  } else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
