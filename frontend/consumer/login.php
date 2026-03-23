<?php
require_once __DIR__ . '/auth_guard.php';
requireConsumerGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consumer Portal — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root { --bg:#0a0e1a; --surface:#111827; --border:#1e2d40; --accent:#00d4ff; --accent2:#0057ff; --text:#e2eaf4; --muted:#6b7fa3; --danger:#ff4d6d; }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; }
body::before { content:''; position:fixed; inset:0; background-image: linear-gradient(rgba(0,212,255,.03) 1px,transparent 1px), linear-gradient(90deg,rgba(0,212,255,.03) 1px,transparent 1px); background-size:48px 48px; pointer-events:none; }
.wrap { position:relative; z-index:1; width:100%; max-width:400px; padding:20px; }
.logo { text-align:center; margin-bottom:32px; }
.logo-icon { display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; background:linear-gradient(135deg,var(--accent2),var(--accent)); border-radius:16px; font-size:26px; margin-bottom:14px; box-shadow:0 0 36px rgba(0,212,255,.2); }
.logo h1 { font-size:15px; color:var(--accent); letter-spacing:.2em; text-transform:uppercase; }
.logo p { font-size:11px; color:var(--muted); margin-top:4px; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:32px 28px; }
.card h2 { font-size:18px; font-weight:700; margin-bottom:6px; }
.card > p { font-size:13px; color:var(--muted); margin-bottom:24px; }
.field { margin-bottom:18px; }
.field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:7px; }
.field input { width:100%; background:rgba(255,255,255,.04); border:1px solid var(--border); border-radius:10px; padding:12px 14px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .2s; }
.field input:focus { border-color:var(--accent); }
.btn { width:100%; padding:13px; background:linear-gradient(135deg,var(--accent2),var(--accent)); border:none; border-radius:11px; color:#fff; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; margin-top:4px; transition:opacity .15s; }
.btn:hover { opacity:.9; }
.btn:disabled { opacity:.5; cursor:not-allowed; }
.err { background:rgba(255,77,109,.1); border:1px solid rgba(255,77,109,.3); border-radius:8px; padding:10px 14px; font-size:13px; color:var(--danger); margin-bottom:16px; display:none; }
.footer { text-align:center; margin-top:18px; font-size:12px; color:var(--muted); }
.footer a { color:var(--accent); text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">💧</div>
    <h1>Consumer Portal</h1>
    <p>Polomolok Water District</p>
  </div>
  <div class="card">
    <h2>Sign In</h2>
    <p>Use your water account number to log in</p>
    <div class="err" id="errMsg"></div>
    <div class="field">
      <label>Account Number</label>
      <input type="text" id="acct" placeholder="e.g. 1001-0001" autocomplete="username">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" id="pass" placeholder="Enter your password" autocomplete="current-password">
    </div>
    <button class="btn" id="loginBtn" onclick="doLogin()">Sign In</button>
  </div>
  <div class="footer">
    Don't have an account? <a href="register.php">Register here</a>
  </div>
</div>
<script>
document.getElementById('pass').addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
document.getElementById('acct').addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

async function doLogin() {
  const acct = document.getElementById('acct').value.trim();
  const pass = document.getElementById('pass').value;
  const err  = document.getElementById('errMsg');
  const btn  = document.getElementById('loginBtn');
  err.style.display = 'none';
  if (!acct || !pass) { err.textContent = 'Please fill in all fields.'; err.style.display = 'block'; return; }
  btn.disabled = true; btn.textContent = 'Signing in…';
  const fd = new FormData();
  fd.append('action', 'login');
  fd.append('account_number', acct);
  fd.append('password', pass);
  try {
    const res  = await fetch('../../backend/api/consumer_auth.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'dashboard.php';
    } else {
      err.textContent = data.error || 'Login failed.';
      err.style.display = 'block';
    }
  } catch {
    err.textContent = 'Network error. Please try again.';
    err.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Sign In';
  }
}
</script>
</body>
</html>