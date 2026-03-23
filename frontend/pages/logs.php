<?php
$pageTitle = 'History Logs';
require_once 'layout.php';
requireRole('Admin');
?>
<style>
.log-row{display:grid;grid-template-columns:180px 120px 120px 1fr 120px;gap:10px;align-items:center;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;margin-bottom:4px;font-size:13px}
.action-create{color:var(--accent3)}.action-update{color:var(--accent)}.action-delete{color:var(--danger)}
.action-login{color:var(--warn)}.action-export{color:var(--accent2)}
@media(max-width:768px){.log-row{grid-template-columns:1fr;gap:4px}}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <input id="fq" placeholder="Search user, action, table…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
  <select id="fac" onchange="load()" class="filter-input"><option value="">All Actions</option><option>create</option><option>update</option><option>delete</option><option>login</option><option>logout</option><option>export</option><option>import</option></select>
  <input id="ffrom" type="date" onchange="load()" class="filter-input">
  <input id="fto" type="date" onchange="load()" class="filter-input">
  <button onclick="exportLogs()" class="btn-secondary">⬇ Export CSV</button>
</div>
<div id="logCount" style="font-size:12px;color:var(--muted);margin-bottom:10px"></div>
<div id="logList"><div class="spinner"></div></div>
<div id="pagination" style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap"></div>
</main>

<script>
let page=1;const PER=50;let totalLogs=0;

async function load(pg=1){
  page=pg;
  const q=document.getElementById('fq').value,
        action=document.getElementById('fac').value,
        from=document.getElementById('ffrom').value,
        to=document.getElementById('fto').value;
  const d=await apiGet('utilities.php',{action:'get_logs',search:q,log_action:action,from_date:from,to_date:to,page,limit:PER});
  const logs=d?.logs||d?.data||[];
  totalLogs=d?.total||logs.length;
  document.getElementById('logCount').textContent=`Showing ${logs.length} of ${totalLogs} entries`;
  const el=document.getElementById('logList');
  if(!logs.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No log entries found.</p>';return;}
  el.innerHTML=`
    <div style="display:grid;grid-template-columns:180px 120px 120px 1fr 120px;gap:10px;padding:6px 12px;font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:4px">
      <span>Timestamp</span><span>User</span><span>Action</span><span>Details</span><span>Table</span>
    </div>
    ${logs.map(l=>`
    <div class="log-row">
      <span style="color:var(--muted)">${l.created_at||l.timestamp||''}</span>
      <span style="font-weight:600">${l.user_id||l.user||'—'}</span>
      <span class="action-${l.action?.toLowerCase()||''}" style="font-weight:600">${l.action||'—'}</span>
      <span style="color:var(--text2)">${l.details||l.description||''}</span>
      <span style="color:var(--muted)">${l.table_name||l.table||'—'}</span>
    </div>`).join('')}`;
  renderPagination(Math.ceil(totalLogs/PER),page);
}

function renderPagination(pages,cur){
  const el=document.getElementById('pagination');
  if(pages<=1){el.innerHTML='';return;}
  const start=Math.max(1,cur-3),end=Math.min(pages,cur+3);
  let html=cur>1?`<button onclick="load(${cur-1})" class="btn-secondary" style="padding:4px 10px;font-size:12px">‹ Prev</button>`:'';
  for(let p=start;p<=end;p++) html+=`<button onclick="load(${p})" class="${p===cur?'btn-primary':'btn-secondary'}" style="padding:4px 10px;font-size:12px">${p}</button>`;
  html+=cur<pages?`<button onclick="load(${cur+1})" class="btn-secondary" style="padding:4px 10px;font-size:12px">Next ›</button>`:'';
  el.innerHTML=html;
}

async function exportLogs(){
  const q=document.getElementById('fq').value,action=document.getElementById('fac').value;
  const d=await apiGet('utilities.php',{action:'export_csv',table:'activity_logs',search:q,log_action:action});
  if(!d?.data){showToast('Export failed','error');return;}
  const blob=new Blob([atob(d.data)],{type:'text/csv'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='activity_logs.csv';a.click();
  showToast('Logs exported','success');
}
document.addEventListener('DOMContentLoaded',()=>load());
</script>
