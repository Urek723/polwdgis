<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pol Web GIS — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0a0e1a;
  --surface: #111827;
  --border: #1e2d40;
  --accent: #00d4ff;
  --accent2: #0057ff;
  --text: #e2eaf4;
  --muted: #6b7fa3;
  --danger: #ff4d6d;
  --success: #00c896;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* Animated grid background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(0,212,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,212,255,0.04) 1px, transparent 1px);
  background-size: 48px 48px;
  pointer-events: none;
}
body::after {
  content: '';
  position: fixed;
  inset: 0;
  background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(0,87,255,0.08) 0%, transparent 70%);
  pointer-events: none;
}

.login-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  padding: 20px;
}

.logo-section {
  text-align: center;
  margin-bottom: 36px;
}
.logo-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px; height: 64px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border-radius: 18px;
  margin-bottom: 16px;
  font-size: 28px;
  box-shadow: 0 0 40px rgba(0,212,255,0.25);
}
.logo-title {
  font-size: 13px;
  font-family: 'Space Mono', monospace;
  color: var(--accent);
  letter-spacing: 0.3em;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.logo-sub {
  font-size: 11px;
  color: var(--muted);
  letter-spacing: 0.1em;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 36px 32px;
  box-shadow: 0 24px 80px rgba(0,0,0,0.4);
}

.card h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  letter-spacing: -0.02em;
}
.card p { font-size: 13px; color: var(--muted); margin-bottom: 28px; }

.field { margin-bottom: 20px; }
.field label {
  display: block;
  font-size: 11px;
  font-family: 'Space Mono', monospace;
  color: var(--muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.input-wrap {
  position: relative;
}
.input-wrap svg {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  width: 16px; height: 16px;
  pointer-events: none;
}
.input-wrap input {
  width: 100%;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 13px 14px 13px 42px;
  font-family: 'Sora', sans-serif;
  font-size: 14px;
  color: var(--text);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrap input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(0,212,255,0.1);
}
.eye-btn {
  position: absolute;
  right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  cursor: pointer; color: var(--muted);
  padding: 4px;
  transition: color 0.2s;
}
.eye-btn:hover { color: var(--accent); }

.btn-login {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border: none;
  border-radius: 12px;
  color: #fff;
  font-family: 'Sora', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  letter-spacing: 0.02em;
  transition: opacity 0.2s, transform 0.15s;
  margin-top: 8px;
}
.btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-login:active { transform: translateY(0); }
.btn-login:disabled { opacity: 0.5; cursor: not-allowed; }

.error-msg {
  background: rgba(255,77,109,0.1);
  border: 1px solid rgba(255,77,109,0.3);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--danger);
  margin-bottom: 18px;
  display: none;
}
.error-msg.show { display: block; }

.loading-dots {
  display: none;
  gap: 4px;
  justify-content: center;
}
.loading-dots span {
  width: 6px; height: 6px;
  background: #fff;
  border-radius: 50%;
  animation: dot-bounce 0.8s infinite;
}
.loading-dots span:nth-child(2) { animation-delay: 0.15s; }
.loading-dots span:nth-child(3) { animation-delay: 0.3s; }
@keyframes dot-bounce {
  0%,80%,100% { transform: scale(0.6); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}
.btn-login.loading .btn-text { display: none; }
.btn-login.loading .loading-dots { display: flex; }

.footer-note {
  text-align: center;
  font-size: 11px;
  color: var(--muted);
  margin-top: 20px;
  font-family: 'Space Mono', monospace;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.login-wrap { animation: fadeInUp 0.5s ease; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo-section">
    <div class="logo-icon">💧</div>
    <div class="logo-title">Pol Web GIS</div>
    <div class="logo-sub">Water District Management System</div>
  </div>

  <div class="card">
    <h2>Welcome back</h2>
    <p>Sign in to access the system</p>

    <div class="error-msg" id="errorMsg"></div>

    <form id="loginForm">
      <div class="field">
        <label>Username</label>
        <div class="input-wrap">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/>
          </svg>
          <input type="text" id="username" name="username" placeholder="Enter username" autocomplete="username" required>
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
          <button type="button" class="eye-btn" id="eyeBtn">
            <svg id="eyeIcon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <span class="btn-text">Sign In</span>
        <div class="loading-dots">
          <span></span><span></span><span></span>
        </div>
      </button>
    </form>
  </div>

  <div class="footer-note">Polomolok Water District © 2025</div>
</div>

<script>
const form   = document.getElementById('loginForm');
const btn    = document.getElementById('loginBtn');
const errMsg = document.getElementById('errorMsg');
const eyeBtn = document.getElementById('eyeBtn');
const pwInput = document.getElementById('password');

eyeBtn.addEventListener('click', () => {
  const isText = pwInput.type === 'text';
  pwInput.type = isText ? 'password' : 'text';
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errMsg.classList.remove('show');
  btn.classList.add('loading');
  btn.disabled = true;

  const fd = new FormData(form);
  fd.append('action', 'login');

  try {
    const res  = await fetch('../../backend/api/auth.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      window.location.href = 'dashboard.php';
    } else {
      errMsg.textContent = data.error || 'Login failed';
      errMsg.classList.add('show');
    }
  } catch {
    errMsg.textContent = 'Network error. Please try again.';
    errMsg.classList.add('show');
  } finally {
    btn.classList.remove('loading');
    btn.disabled = false;
  }
});
</script>
</body>
</html>
