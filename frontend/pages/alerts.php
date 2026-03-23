<?php
$pageTitle = 'Deterioration Alerts';
require_once 'layout.php';
?>
<style>
.alert-card{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:10px}
.sev-Critical{border-left:4px solid var(--danger)}.sev-High{border-left:4px solid #ea580c}.sev-Medium{border-left:4px solid var(--warn)}.sev-Low{border-left:4px solid var(--accent3)}
.sev-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.sev-Critical{background:#dc262622;color:#dc2626}.sev-badge.sev-High{background:#ea580c22;color:#ea580c}
.sev-badge.sev-Medium{background:#ca8a0422;color:#ca8a04}.sev-badge.sev-Low{background:#16a34a22;color:#16a34a}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="generate()">⚡ Generate Alerts</button>
  <?php endif; ?>
  <select id="fs" onchange="load()" class="filter-input"><option value="">All Severities</option><option>Critical</option><option>High</option><option>Medium</option><option>Low</option></select>
</div>
<div id="alertSummary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:18px"></div>
<div id="alertList"><div class="spinner"></div></div>
</main>

<script>
async function load(){
  const sev=document.getElementById('fs').value;
  const d=await apiGet('maintenance.php',{action:'get_alerts',severity:sev});
  const alerts=d?.alerts||d?.data||[];
  const el=document.getElementById('alertList');
  if(!alerts.length){el.innerHTML='<p style="color:var(--accent3);padding:20px 0">✅ No active deterioration alerts.</p>';renderSummary([]);return;}
  renderSummary(alerts);
  el.innerHTML=alerts.map(a=>`
    <div class="alert-card sev-${a.severity}">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <div style="font-weight:600;margin-bottom:2px">${a.alert_type||'Deterioration Alert'}</div>
          <div style="font-size:12px;color:var(--muted)">${a.pipeline_id?'Pipeline #'+a.pipeline_id:'Infrastructure #'+a.infrastructure_id}</div>
        </div>
        <span class="sev-badge sev-${a.severity}">${a.severity}</span>
      </div>
      <p style="font-size:13px;color:var(--text2);margin-bottom:10px">${a.description||''}</p>
      <div style="font-size:12px;color:var(--muted);margin-bottom:10px">
        📅 Installed: ${a.installation_date||'N/A'} &nbsp;·&nbsp; 🕐 Age: ${a.age_years||'?'} years
      </div>
      <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
      <button onclick="resolve(${a.id})" class="btn-secondary" style="font-size:12px">✓ Mark Resolved</button>
      <?php endif; ?>
    </div>`).join('');
}

function renderSummary(alerts){
  const counts={Critical:0,High:0,Medium:0,Low:0};
  alerts.forEach(a=>counts[a.severity]=(counts[a.severity]||0)+1);
  document.getElementById('alertSummary').innerHTML=Object.entries(counts).map(([sev,cnt])=>`
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:${sev==='Critical'?'var(--danger)':sev==='High'?'#ea580c':sev==='Medium'?'var(--warn)':'var(--accent3)'}">${cnt}</div>
      <div style="font-size:11px;color:var(--muted)">${sev}</div>
    </div>`).join('');
}

async function generate(){
  const btn=event.target; btn.disabled=true; btn.textContent='Generating…';
  const r=await apiPost('maintenance.php',{action:'generate_alerts'});
  btn.disabled=false; btn.textContent='⚡ Generate Alerts';
  if(r?.success||r?.generated>=0){showToast(`Generated ${r.generated||0} new alert(s)`,'success');load();}
  else showToast(r?.error||'Failed','error');
}

async function resolve(id){
  const r=await apiPost('maintenance.php',{action:'resolve_alert',id});
  if(r?.success||r?.ok){showToast('Alert resolved','success');load();}
  else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
