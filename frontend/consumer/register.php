<?php
require_once __DIR__ . '/auth_guard.php';
requireConsumerGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consumer Portal — Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root { --bg:#0a0e1a; --surface:#111827; --border:#1e2d40; --accent:#00d4ff; --accent2:#0057ff; --text:#e2eaf4; --muted:#6b7fa3; --danger:#ff4d6d; --success:#00c896; }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
body::before { content:''; position:fixed; inset:0; background-image: linear-gradient(rgba(0,212,255,.03) 1px,transparent 1px), linear-gradient(90deg,rgba(0,212,255,.03) 1px,transparent 1px); background-size:48px 48px; pointer-events:none; }
.wrap { position:relative; z-index:1; width:100%; max-width:420px; }
.logo { text-align:center; margin-bottom:28px; }
.logo-icon { display:inline-flex; align-items:center; justify-content:center; width:54px; height:54px; background:linear-gradient(135deg,var(--accent2),var(--accent)); border-radius:14px; font-size:24px; margin-bottom:12px; }
.logo h1 { font-size:14px; color:var(--accent); letter-spacing:.2em; text-transform:uppercase; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:28px; }
.card h2 { font-size:18px; font-weight:700; margin-bottom:6px; }
.card > p { font-size:13px; color:var(--muted); margin-bottom:22px; }
.field { margin-bottom:16px; }
.field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
.field input { width:100%; background:rgba(255,255,255,.04); border:1px solid var(--border); border-radius:10px; padding:11px 14px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .2s; }
.field input:focus { border-color:var(--accent); }
.btn { width:100%; padding:13px; background:linear-gradient(135deg,var(--accent2),var(--accent)); border:none; border-radius:11px; color:#fff; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; margin-top:4px; transition:opacity .15s; }
.btn:hover { opacity:.9; }
.btn:disabled { opacity:.5; cursor:not-allowed; }
.err { background:rgba(255,77,109,.1); border:1px solid rgba(255,77,109,.3); border-radius:8px; padding:10px 14px; font-size:13px; color:var(--danger); margin-bottom:14px; display:none; }
.ok  { background:rgba(0,200,150,.1); border:1px solid rgba(0,200,150,.3); border-radius:8px; padding:10px 14px; font-size:13px; color:var(--success); margin-bottom:14px; display:none; }
.footer { text-align:center; margin-top:16px; font-size:12px; color:var(--muted); }
.footer a { color:var(--accent); text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">💧</div>
    <h1>Consumer Portal</h1>
  </div>
  <div class="card">
    <h2>Create Account</h2>
    <p>Register with your water account number</p>
    <div class="err" id="errMsg"></div>
    <div class="ok"  id="okMsg"></div>
    <div class="field"><label>Full Name</label><input type="text" id="name" placeholder="Juan Dela Cruz"></div>
    <div class="field"><label>Account Number</label><input type="text" id="acct" placeholder="Found on your water bill"></div>
    <div class="field"><label>Contact Number</label><input type="tel" id="contact" placeholder="09XXXXXXXXX"></div>
    <div class="field"><label>Password</label><input type="password" id="pass" placeholder="At least 6 characters"></div>
    <div class="field"><label>Confirm Password</label><input type="password" id="pass2" placeholder="Re-enter password"></div>
    <button class="btn" id="regBtn" onclick="doRegister()">Create Account</button>
  </div>
  <div class="footer">Already have an account? <a href="login.php">Sign in</a></div>
</div>
<script>
async function doRegister() {
  const name    = document.getElementById('name').value.trim();
  const acct    = document.getElementById('acct').value.trim();
  const contact = document.getElementById('contact').value.trim();
  const pass    = document.getElementById('pass').value;
  const pass2   = document.getElementById('pass2').value;
  const err = document.getElementById('errMsg');
  const ok  = document.getElementById('okMsg');
  err.style.display = 'none'; ok.style.display = 'none';

  if (!name || !acct || !contact || !pass || !pass2) { err.textContent = 'All fields are required.'; err.style.display = 'block'; return; }
  if (pass !== pass2) { err.textContent = 'Passwords do not match.'; err.style.display = 'block'; return; }
  if (pass.length < 6) { err.textContent = 'Password must be at least 6 characters.'; err.style.display = 'block'; return; }

  const btn = document.getElementById('regBtn');
  btn.disabled = true; btn.textContent = 'Creating…';

  const fd = new FormData();
  fd.append('action', 'register');
  fd.append('name', name);
  fd.append('account_number', acct);
  fd.append('contact_number', contact);
  fd.append('password', pass);

  try {
    const res  = await fetch('../../backend/api/consumer_auth.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      ok.textContent = 'Account created! Redirecting to login…';
      ok.style.display = 'block';
      setTimeout(() => window.location.href = 'login.php', 1800);
    } else {
      err.textContent = data.error || 'Registration failed.';
      err.style.display = 'block';
    }
  } catch {
    err.textContent = 'Network error. Please try again.';
    err.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Create Account';
  }
}
</script>
</body>
</html>