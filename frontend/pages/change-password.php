<?php
$pageTitle = 'Change Password';
require_once 'layout.php';
?>
<main class="main" style="display:flex;justify-content:center;padding-top:40px">
<div style="width:100%;max-width:420px">
  <div class="card" style="padding:32px">
    <h2 style="font-size:20px;font-weight:700;margin-bottom:8px">Change Password</h2>
    <p style="font-size:13px;color:var(--muted);margin-bottom:24px">Update your account password. You'll remain logged in after changing.</p>
    <div style="display:flex;flex-direction:column;gap:14px">
      <div>
        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Current Password</label>
        <input id="currPass" type="password" placeholder="Enter current password" class="form-input" style="width:100%">
      </div>
      <div>
        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">New Password</label>
        <input id="newPass" type="password" placeholder="Minimum 8 characters" class="form-input" style="width:100%">
        <div id="strengthBar" style="height:4px;margin-top:6px;border-radius:2px;background:var(--border);overflow:hidden">
          <div id="strengthFill" style="height:100%;width:0;transition:width .3s,background .3s"></div>
        </div>
        <div id="strengthLabel" style="font-size:11px;color:var(--muted);margin-top:4px"></div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Confirm New Password</label>
        <input id="confPass" type="password" placeholder="Re-enter new password" class="form-input" style="width:100%">
        <div id="matchLabel" style="font-size:11px;margin-top:4px"></div>
      </div>
      <div id="errMsg" style="display:none;background:#dc262622;border:1px solid var(--danger);border-radius:6px;padding:10px;font-size:13px;color:var(--danger)"></div>
      <button onclick="changePassword()" id="submitBtn" class="btn-primary" style="width:100%;padding:12px;font-size:15px">Update Password</button>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px">
    <a href="dashboard.php" style="font-size:13px;color:var(--accent);text-decoration:none">← Back to Dashboard</a>
  </div>
</div>
</main>

<script>
const np=document.getElementById('newPass');
const cp=document.getElementById('confPass');

np.addEventListener('input',()=>{
  const v=np.value;
  let strength=0;
  if(v.length>=8)strength++;
  if(/[A-Z]/.test(v))strength++;
  if(/[0-9]/.test(v))strength++;
  if(/[^A-Za-z0-9]/.test(v))strength++;
  const colors=['','var(--danger)','var(--warn)','var(--accent2)','var(--accent3)'];
  const labels=['','Weak','Fair','Good','Strong'];
  document.getElementById('strengthFill').style.width=(strength*25)+'%';
  document.getElementById('strengthFill').style.background=colors[strength]||'var(--border)';
  document.getElementById('strengthLabel').textContent=labels[strength]||'';
  checkMatch();
});

cp.addEventListener('input',checkMatch);

function checkMatch(){
  const ml=document.getElementById('matchLabel');
  if(!cp.value){ml.textContent='';return;}
  if(np.value===cp.value){ml.style.color='var(--accent3)';ml.textContent='✓ Passwords match';}
  else{ml.style.color='var(--danger)';ml.textContent='✗ Passwords do not match';}
}

async function changePassword(){
  const curr=document.getElementById('currPass').value;
  const n=document.getElementById('newPass').value;
  const c=document.getElementById('confPass').value;
  const err=document.getElementById('errMsg');
  err.style.display='none';

  if(!curr||!n||!c){showErr('Please fill all fields.');return;}
  if(n.length<8){showErr('New password must be at least 8 characters.');return;}
  if(n!==c){showErr('New passwords do not match.');return;}

  const btn=document.getElementById('submitBtn');
  btn.disabled=true;btn.textContent='Updating…';
  const r=await apiPost('utilities.php',{action:'change_password',current_password:curr,new_password:n});
  btn.disabled=false;btn.textContent='Update Password';

  if(r?.success||r?.ok){
    showToast('Password updated successfully!','success');
    document.getElementById('currPass').value='';
    document.getElementById('newPass').value='';
    document.getElementById('confPass').value='';
    document.getElementById('strengthFill').style.width='0';
    document.getElementById('strengthLabel').textContent='';
    document.getElementById('matchLabel').textContent='';
  } else showErr(r?.error||'Failed to update password. Check your current password.');
}

function showErr(msg){
  const e=document.getElementById('errMsg');
  e.textContent=msg;e.style.display='block';
}
</script>
