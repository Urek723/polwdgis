<?php
$pageTitle = 'Communication History';
require_once 'layout.php';
?>
<style>
.comm-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:6px;cursor:pointer;transition:border-color .2s}
.comm-row:hover{border-color:var(--accent)}
.ch-in{border-left:3px solid var(--accent3)}.ch-out{border-left:3px solid var(--accent2)}
.ch-badge{display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px}
.unread{font-weight:600}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openModal('mcomm')">+ Log Communication</button>
  <?php endif; ?>
  <input id="fq" placeholder="Search consumer, subject…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
  <select id="fch" onchange="load()" class="filter-input"><option value="">All Channels</option><option>Email</option><option>SMS</option><option>Phone</option><option>In-person</option><option>System</option></select>
  <select id="fdir" onchange="load()" class="filter-input"><option value="">Both Directions</option><option value="inbound">Inbound</option><option value="outbound">Outbound</option></select>
</div>
<div id="commList"><div class="spinner"></div></div>

<!-- Side Panel -->
<div id="panel" style="display:none;position:fixed;top:0;right:0;width:440px;height:100vh;background:var(--bg);border-left:1px solid var(--border);overflow-y:auto;z-index:300;padding:20px;box-shadow:-6px 0 24px rgba(0,0,0,.3)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3 id="ptitle" style="font-size:16px;font-weight:600">Communication</h3>
    <button onclick="document.getElementById('panel').style.display='none'" style="background:none;border:none;color:var(--text);font-size:22px;cursor:pointer">✕</button>
  </div>
  <div id="pbody"></div>
</div>

<!-- Log Communication Modal -->
<div id="mcomm" class="modal-overlay">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header"><h3>Log Communication</h3><button onclick="closeModal('mcomm')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="ccon" placeholder="Consumer Account ID *" class="form-input">
      <select id="cch" class="form-input"><option>Email</option><option>SMS</option><option>Phone</option><option>In-person</option><option>System</option></select>
      <select id="cdir" class="form-input"><option value="outbound">Outbound (to consumer)</option><option value="inbound">Inbound (from consumer)</option></select>
      <input id="csubj" placeholder="Subject" class="form-input">
      <textarea id="cmsg" placeholder="Message *" class="form-input" rows="4"></textarea>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mcomm')" class="btn-secondary">Cancel</button>
        <button onclick="submitComm()" class="btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
async function load(){
  const q=document.getElementById('fq').value,
        channel=document.getElementById('fch').value,
        direction=document.getElementById('fdir').value;
  const d=await apiGet('consumer.php',{action:'get_comms',search:q,channel,direction});
  const comms=d?.communications||d?.data||[];
  const el=document.getElementById('commList');
  if(!comms.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No communications found.</p>';return;}
  el.innerHTML=comms.map(c=>`
    <div class="comm-row ch-${c.direction||'out'} ${c.is_read?'':'unread'}" onclick="openComm(${c.id})">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;gap:8px">
        <div>
          <span style="font-size:13px">${c.subject||'(no subject)'}</span>
          ${!c.is_read?'<span style="font-size:10px;background:var(--accent);color:#000;border-radius:4px;padding:0 4px;margin-left:4px">NEW</span>':''}
        </div>
        <span class="ch-badge" style="background:var(--surface);border:1px solid var(--border)">${c.channel||'—'}</span>
      </div>
      <div style="font-size:11px;color:var(--muted)">Consumer: ${c.consumer_name||c.consumer_id||'—'} · ${c.direction||'—'} · ${c.created_at||''}</div>
    </div>`).join('');
}

async function openComm(id){
  document.getElementById('panel').style.display='block';
  document.getElementById('pbody').innerHTML='<div class="spinner"></div>';
  const d=await apiGet('consumer.php',{action:'get_comms',id});
  const c=d?.communication||d?.data?.[0];
  if(!c){document.getElementById('pbody').innerHTML='<p>Failed.</p>';return;}
  // Mark as read
  await apiPost('consumer.php',{action:'mark_notification_read',comm_id:id});
  document.getElementById('ptitle').textContent=c.subject||'Communication';
  document.getElementById('pbody').innerHTML=`
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;display:grid;gap:4px">
      <div>📱 Channel: ${c.channel||'—'}</div>
      <div>↔️ Direction: ${c.direction||'—'}</div>
      <div>👤 Consumer: ${c.consumer_name||c.consumer_id||'—'}</div>
      <div>🕐 ${c.created_at||'—'}</div>
    </div>
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:13px;line-height:1.6">${c.message||''}</div>
    <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
    <div style="margin-top:14px">
      <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Reply</div>
      <textarea id="replyMsg" placeholder="Type reply…" class="form-input" rows="3" style="font-size:13px"></textarea>
      <button onclick="sendReply('${c.consumer_id||c.consumer_account_id}','${c.channel||'Email'}')" class="btn-primary" style="width:100%;margin-top:6px;font-size:13px">Send Reply</button>
    </div>
    <?php endif; ?>`;
}

async function sendReply(consumerId, channel){
  const msg=document.getElementById('replyMsg').value.trim();
  if(!msg)return;
  const r=await apiPost('consumer.php',{action:'add_comm',consumer_id:consumerId,channel,direction:'outbound',subject:'Re: Reply',message:msg});
  if(r?.success||r?.id){showToast('Reply sent','success');document.getElementById('replyMsg').value='';}
  else showToast(r?.error||'Failed','error');
}

async function submitComm(){
  const r=await apiPost('consumer.php',{action:'add_comm',
    consumer_id:document.getElementById('ccon').value,
    channel:document.getElementById('cch').value,
    direction:document.getElementById('cdir').value,
    subject:document.getElementById('csubj').value,
    message:document.getElementById('cmsg').value});
  if(r?.success||r?.id){closeModal('mcomm');load();showToast('Communication logged','success');}
  else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
