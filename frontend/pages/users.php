<?php
$pageTitle = 'User Management';
require_once 'layout.php';
requireRole('Admin');
?>
<style>
.user-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.role-Admin{color:var(--danger);font-weight:600}
.role-Staff{color:var(--accent);font-weight:600}
.role-Consumer{color:var(--accent3);font-weight:600}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <button class="btn-primary" onclick="openModal('muser')">+ Add User</button>
  <input id="fq" placeholder="Search users…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
  <select id="frole" onchange="load()" class="filter-input"><option value="">All Roles</option><option>Admin</option><option>Staff</option><option>Consumer</option></select>
</div>
<div id="userList"><div class="spinner"></div></div>

<!-- Add/Edit User Modal -->
<div id="muser" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3 id="muser-title">Add User</h3><button onclick="closeModal('muser')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input type="hidden" id="uid">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="uname" placeholder="Full Name *" class="form-input">
        <input id="uusername" placeholder="Username *" class="form-input">
      </div>
      <input id="upassword" type="password" placeholder="Password (leave blank to keep)" class="form-input">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <select id="urole" class="form-input"><option>Admin</option><option>Staff</option><option>Consumer</option></select>
        <input id="usection" placeholder="Section/Department" class="form-input">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="uemail" placeholder="Email" type="email" class="form-input">
        <input id="uphome" placeholder="Phone" class="form-input">
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" id="uactive" checked style="width:16px;height:16px">
        <label for="uactive" style="font-size:13px">Active</label>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('muser')" class="btn-secondary">Cancel</button>
        <button onclick="submitUser()" class="btn-primary">Save User</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
async function load(){
  const q=document.getElementById('fq').value,role=document.getElementById('frole').value;
  const d=await apiGet('utilities.php',{action:'get_users',search:q,role});
  const users=d?.users||d?.data||[];
  const el=document.getElementById('userList');
  if(!users.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No users found.</p>';return;}
  el.innerHTML=users.map(u=>`
    <div class="user-row">
      <div>
        <div style="font-weight:600">${u.name}</div>
        <div style="font-size:12px;color:var(--muted)">@${u.username} · ${u.section||'—'}</div>
        <div style="font-size:12px;color:var(--muted)">${u.email||''} ${u.phone||''}</div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="role-${u.role}">${u.role}</span>
        <span style="font-size:11px;color:${u.is_active?'var(--accent3)':'var(--danger)'}">${u.is_active?'Active':'Inactive'}</span>
        <button onclick="editUser(${JSON.stringify(u).replace(/"/g,'&quot;')})" class="btn-secondary" style="font-size:12px">Edit</button>
      </div>
    </div>`).join('');
}

function editUser(u){
  document.getElementById('uid').value=u.id||'';
  document.getElementById('uname').value=u.name||'';
  document.getElementById('uusername').value=u.username||'';
  document.getElementById('upassword').value='';
  document.getElementById('urole').value=u.role||'Staff';
  document.getElementById('usection').value=u.section||'';
  document.getElementById('uemail').value=u.email||'';
  document.getElementById('uphome').value=u.phone||'';
  document.getElementById('uactive').checked=u.is_active!==false&&u.is_active!==0;
  document.getElementById('muser-title').textContent='Edit User: '+u.name;
  openModal('muser');
}

async function submitUser(){
  const data={action:'save_user',
    id:document.getElementById('uid').value||undefined,
    name:document.getElementById('uname').value,
    username:document.getElementById('uusername').value,
    password:document.getElementById('upassword').value||undefined,
    role:document.getElementById('urole').value,
    section:document.getElementById('usection').value,
    email:document.getElementById('uemail').value,
    phone:document.getElementById('uphome').value,
    is_active:document.getElementById('uactive').checked?1:0};
  if(!data.name||!data.username){showToast('Name and username required','error');return;}
  const r=await apiPost('utilities.php',data);
  if(r?.success||r?.id){closeModal('muser');load();showToast('User saved','success');}
  else showToast(r?.error||'Failed','error');
}
document.addEventListener('DOMContentLoaded',load);
</script>
