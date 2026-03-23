<?php
$pageTitle = 'Request Portal';
require_once 'layout.php';
?>
<style>
.req-card{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:8px;cursor:pointer;transition:border-color .2s}
.req-card:hover{border-color:var(--accent)}
.req-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.Open{background:#0ea5e922;color:#0ea5e9}.InProgress{background:#ca8a0422;color:#ca8a04}
.Resolved{background:#16a34a22;color:#16a34a}.Closed{background:#71717a22;color:#71717a}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <button class="btn-primary" onclick="openModal('mreq')">+ Submit Request</button>
  <select id="fstatus" onchange="load()" class="filter-input"><option value="">All Statuses</option><option>Open</option><option>In Progress</option><option>Resolved</option><option>Closed</option></select>
  <select id="ftype" onchange="load()" class="filter-input"><option value="">All Types</option><option>New Connection</option><option>Disconnection</option><option>Reconnection</option><option>Repair</option><option>Billing Dispute</option><option>Other</option></select>
  <select id="fpri" onchange="load()" class="filter-input"><option value="">All Priorities</option><option>Critical</option><option>High</option><option>Medium</option><option>Low</option></select>
</div>
<div id="reqList"><div class="spinner"></div></div>

<!-- Request Detail Panel -->
<div id="panel" style="display:none;position:fixed;top:0;right:0;width:440px;height:100vh;background:var(--bg);border-left:1px solid var(--border);overflow-y:auto;z-index:300;padding:20px;box-shadow:-6px 0 24px rgba(0,0,0,.3)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3 id="ptitle" style="font-size:16px;font-weight:600"></h3>
    <button onclick="document.getElementById('panel').style.display='none'" style="background:none;border:none;color:var(--text);font-size:22px;cursor:pointer">✕</button>
  </div>
  <div id="pbody"></div>
</div>

<!-- Submit Request Modal -->
<div id="mreq" class="modal-overlay">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header"><h3>Submit Service Request</h3><button onclick="closeModal('mreq')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <select id="rtype" class="form-input"><option>New Connection</option><option>Disconnection</option><option>Reconnection</option><option>Repair</option><option>Billing Dispute</option><option>Other</option></select>
      <input id="rsubj" placeholder="Subject *" class="form-input">
      <textarea id="rdetails" placeholder="Details / Description *" class="form-input" rows="4"></textarea>
      <select id="rpri" class="form-input"><option value="Medium">Medium Priority</option><option value="Low">Low</option><option value="High">High</option><option value="Critical">Critical</option></select>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mreq')" class="btn-secondary">Cancel</button>
        <button onclick="submitReq()" class="btn-primary">Submit</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
const ROLE=<?php echo json_encode($_SESSION['role']); ?>;
const CAN_UPDATE=['Admin','Staff'].includes(ROLE);

async function load(){
  const status=document.getElementById('fstatus').value,
        type=document.getElementById('ftype').value,
        priority=document.getElementById('fpri').value;
  const d=await apiGet('consumer.php',{action:'get_requests',status,type,priority});
  const reqs=d?.requests||d?.data||[];
  const el=document.getElementById('reqList');
  if(!reqs.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No requests found.</p>';return;}
  el.innerHTML=reqs.map(r=>`
    <div class="req-card" onclick="openReq(${r.id})">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;gap:8px">
        <div>
          <div style="font-weight:600">${r.subject}</div>
          <div style="font-size:12px;color:var(--muted)">${r.request_type} · Consumer: ${r.consumer_name||r.consumer_id}</div>
        </div>
        <div style="display:flex;gap:4px;flex-shrink:0">
          <span class="req-badge ${(r.status||'Open').replace(/\s/g,'')}">${r.status||'Open'}</span>
        </div>
      </div>
      <div style="font-size:12px;color:var(--muted)">📅 ${r.created_at||''} ${r.assigned_to?'· Assigned: '+r.assigned_to:''}</div>
    </div>`).join('');
}

async function openReq(id){
  document.getElementById('panel').style.display='block';
  document.getElementById('pbody').innerHTML='<div class="spinner"></div>';
  const d=await apiGet('consumer.php',{action:'get_requests',id});
  const r=d?.request||d?.data?.[0]||d?.requests?.[0];
  if(!r){document.getElementById('pbody').innerHTML='<p>Failed.</p>';return;}
  document.getElementById('ptitle').textContent=r.subject;
  const statusBtns=CAN_UPDATE?['Open','In Progress','Resolved','Closed'].map(s=>
    `<button onclick="updateReq(${r.id},'${s}')" class="btn-secondary" style="font-size:11px;padding:3px 8px">${s}</button>`).join(''):'';
  document.getElementById('pbody').innerHTML=`
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
      <span class="req-badge ${(r.status||'Open').replace(/\s/g,'')}">${r.status||'Open'}</span>
      <span style="font-size:12px;color:var(--muted)">${r.request_type}</span>
      <span style="font-size:12px;color:var(--muted)">${r.priority||'Medium'} priority</span>
    </div>
    <div style="font-size:13px;line-height:1.6;margin-bottom:14px;color:var(--text2)">${r.details||''}</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:14px">
      Consumer: ${r.consumer_name||r.consumer_id||'—'}<br>
      Submitted: ${r.created_at||'—'}<br>
      ${r.assigned_to?'Assigned to: '+r.assigned_to:''}
    </div>
    ${statusBtns?`<div style="margin-bottom:14px"><div style="font-size:12px;color:var(--muted);margin-bottom:6px">Update Status</div><div style="display:flex;gap:6px;flex-wrap:wrap">${statusBtns}</div></div>`:''}`;
}

async function updateReq(id, status){
  const r=await apiPost('consumer.php',{action:'update_request',id,status});
  if(r?.success||r?.ok){showToast('Request updated','success');openReq(id);load();}
  else showToast(r?.error||'Failed','error');
}

async function submitReq(){
  if(!document.getElementById('rsubj').value||!document.getElementById('rdetails').value){showToast('Fill required fields','error');return;}
  const r=await apiPost('consumer.php',{action:'save_request',
    request_type:document.getElementById('rtype').value,
    subject:document.getElementById('rsubj').value,
    details:document.getElementById('rdetails').value,
    priority:document.getElementById('rpri').value});
  if(r?.success||r?.id){closeModal('mreq');load();showToast('Request submitted','success');}
  else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
