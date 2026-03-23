<?php
$pageTitle = 'Maintenance Schedules';
require_once 'layout.php';
?>
<style>
.sched-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:8px;display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center}
.freq-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;background:var(--accent2)22;color:var(--accent2)}
.due-soon{border-color:var(--warn)!important}
.overdue{border-color:var(--danger)!important}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openModal('msched')">+ New Schedule</button>
  <?php endif; ?>
  <select id="ff" onchange="load()" class="filter-input"><option value="">All Frequencies</option><option>Daily</option><option>Weekly</option><option>Monthly</option><option>Quarterly</option><option>Annual</option><option>Once</option></select>
</div>
<div id="schedList"><div class="spinner"></div></div>

<div id="msched" class="modal-overlay">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header"><h3>New Maintenance Schedule</h3><button onclick="closeModal('msched')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="st" placeholder="Title *" class="form-input">
      <select id="stype" class="form-input"><option>Routine</option><option>Preventive</option><option>Corrective</option><option>Emergency</option></select>
      <select id="sfreq" class="form-input"><option>Daily</option><option>Weekly</option><option>Monthly</option><option>Quarterly</option><option>Annual</option><option>Once</option></select>
      <input id="snext" type="date" class="form-input" placeholder="Next Due Date">
      <input id="sinf" type="number" placeholder="Infrastructure ID (optional)" class="form-input">
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('msched')" class="btn-secondary">Cancel</button>
        <button onclick="submitSched()" class="btn-primary">Create</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
async function load(){
  const freq=document.getElementById('ff').value;
  const d=await apiGet('maintenance.php',{action:'get_schedules',frequency:freq});
  const el=document.getElementById('schedList');
  const scheds=d?.schedules||d?.data||[];
  if(!scheds.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No schedules found.</p>';return;}
  const today=new Date();
  el.innerHTML=scheds.map(s=>{
    const due=new Date(s.next_due);
    const diff=Math.ceil((due-today)/(1000*60*60*24));
    let cls='';
    if(diff<0) cls='overdue';
    else if(diff<=7) cls='due-soon';
    return `<div class="sched-row ${cls}">
      <div>
        <div style="font-weight:600;margin-bottom:4px">${s.title}</div>
        <div style="font-size:12px;color:var(--muted)">${s.type} ${s.infrastructure_id?'· Infrastructure #'+s.infrastructure_id:''}</div>
        ${s.assigned_to?`<div style="font-size:12px;color:var(--muted)">Assigned: ${s.assigned_to}</div>`:''}
      </div>
      <span class="freq-badge">${s.frequency}</span>
      <div style="text-align:right">
        <div style="font-size:13px;font-weight:600;${diff<0?'color:var(--danger)':diff<=7?'color:var(--warn)':''}">${s.next_due}</div>
        <div style="font-size:11px;color:var(--muted)">${diff<0?'Overdue '+Math.abs(diff)+'d':diff===0?'Today':'In '+diff+'d'}</div>
      </div>
    </div>`;
  }).join('');
}

async function submitSched(){
  const r=await apiPost('maintenance.php',{action:'save_schedule',
    title:document.getElementById('st').value,type:document.getElementById('stype').value,
    frequency:document.getElementById('sfreq').value,next_due:document.getElementById('snext').value,
    infrastructure_id:document.getElementById('sinf').value});
  if(r?.success||r?.id){closeModal('msched');load();showToast('Schedule created','success');}
  else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
