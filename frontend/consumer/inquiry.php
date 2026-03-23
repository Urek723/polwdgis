<?php
$pageTitle = 'Send Inquiry';
require_once 'layout.php';
?>
<div class="page-wrap">
  <h2 style="font-size:18px;font-weight:700;margin-bottom:4px">Send an Inquiry</h2>
  <p style="font-size:13px;color:var(--muted);margin-bottom:20px">Ask questions or raise concerns. Staff will respond and you can view replies here.</p>

  <div class="card">
    <div class="card-title">New Inquiry</div>
    <div style="margin-bottom:14px">
      <label>Subject *</label>
      <input type="text" id="subject" class="form-input" placeholder="e.g. Question about my bill">
    </div>
    <div style="margin-bottom:14px">
      <label>Message *</label>
      <textarea id="message" class="form-input" rows="4" placeholder="Write your question or concern here…"></textarea>
    </div>
    <div id="errMsg" style="display:none;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:10px;font-size:13px;color:var(--danger);margin-bottom:10px"></div>
    <div id="okMsg"  style="display:none;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.3);border-radius:8px;padding:10px;font-size:13px;color:var(--accent3);margin-bottom:10px"></div>
    <button id="submitBtn" class="btn-primary" onclick="submitInquiry()">Send Inquiry</button>
  </div>

  <div class="card">
    <div class="card-title">📬 My Inquiries</div>
    <div id="inquiryList"><div class="spinner"></div></div>
  </div>
</div>

<script>
async function submitInquiry() {
  const subject = document.getElementById('subject').value.trim();
  const message = document.getElementById('message').value.trim();
  const errEl = document.getElementById('errMsg');
  const okEl  = document.getElementById('okMsg');
  errEl.style.display = 'none'; okEl.style.display = 'none';

  if (!subject || !message) { errEl.textContent = 'Subject and message are required.'; errEl.style.display = 'block'; return; }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Sending…';

  const fd = new FormData();
  fd.append('action', 'submit_inquiry');
  fd.append('subject', subject);
  fd.append('message', message);

  try {
    const res  = await fetch(CONSUMER_API, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    if (data.success) {
      okEl.textContent = '✅ Inquiry sent successfully!';
      okEl.style.display = 'block';
      document.getElementById('subject').value = '';
      document.getElementById('message').value = '';
      loadInquiries();
    } else {
      errEl.textContent = data.error || 'Failed to send.';
      errEl.style.display = 'block';
    }
  } catch {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Send Inquiry';
  }
}

async function loadInquiries() {
  const d  = await apiGet(CONSUMER_API, { action: 'get_my_inquiries' });
  const el = document.getElementById('inquiryList');
  const list = d?.data || [];

  if (!list.length) { el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:16px 0">No inquiries yet.</p>'; return; }

  el.innerHTML = list.map(i => `
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:600;font-size:14px">${i.subject}</div>
        <span class="badge badge-${i.status.toLowerCase().replace(/\s+/g,'')}">${i.status}</span>
      </div>
      <div style="font-size:13px;color:var(--text2);margin-bottom:8px;line-height:1.5">${i.details}</div>
      <div style="font-size:11px;color:var(--muted)">📅 ${i.created_at}</div>
      ${i.staff_reply ? `
      <div style="margin-top:10px;background:rgba(0,87,255,.08);border:1px solid rgba(0,87,255,.2);border-radius:7px;padding:10px">
        <div style="font-size:11px;color:var(--accent2);font-weight:600;margin-bottom:4px">💬 Staff Reply · ${i.reply_at || ''}</div>
        <div style="font-size:13px;color:var(--text2)">${i.staff_reply}</div>
      </div>` : '<div style="font-size:11px;color:var(--muted);margin-top:8px">Awaiting staff response…</div>'}
    </div>`).join('');
}

loadInquiries();
</script>