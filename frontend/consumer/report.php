<?php
$pageTitle = 'Report Issue';
require_once 'layout.php';
?>
<div class="page-wrap">
  <h2 style="font-size:18px;font-weight:700;margin-bottom:4px">Report an Issue</h2>
  <p style="font-size:13px;color:var(--muted);margin-bottom:20px">Drop a pin on the map to mark the exact location</p>

  <div class="card">
    <div class="card-title">Issue Details</div>

    <div style="margin-bottom:14px">
      <label>Issue Type *</label>
      <select id="issueType" class="form-input">
        <option value="">— Select issue type —</option>
        <option value="Leak">💧 Leak</option>
        <option value="Low Pressure">📉 Low Pressure</option>
        <option value="No Water">🚫 No Water</option>
        <option value="General Inquiry">❓ General Inquiry</option>
      </select>
    </div>

    <div style="margin-bottom:14px">
      <label>Description *</label>
      <textarea id="description" class="form-input" rows="3" placeholder="Describe the issue in detail…"></textarea>
    </div>

    <div style="margin-bottom:14px">
      <label>Your Contact Number</label>
      <input type="tel" id="contact" class="form-input" placeholder="For follow-up" value="<?= htmlspecialchars($consumer['contact_number']) ?>">
    </div>
  </div>

  <div class="card">
    <div class="card-title">📍 Pin Location on Map <span style="color:var(--danger);font-size:12px">* Required</span></div>
    <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Click anywhere on the map to drop a pin at the issue location. You can reposition it by clicking again.</p>

    <div id="reportMap" style="height:380px;border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-bottom:12px"></div>

    <div id="coordDisplay" style="font-size:13px;color:var(--muted);margin-bottom:12px;min-height:20px">
      No location selected yet.
    </div>

    <div style="margin-bottom:4px">
      <label>Location Description (optional)</label>
      <input type="text" id="locationText" class="form-input" placeholder="e.g. Near purok 3 sitio, beside the school">
    </div>
  </div>

  <div id="errMsg" style="display:none;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:12px;font-size:13px;color:var(--danger);margin-bottom:14px"></div>
  <div id="okMsg"  style="display:none;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.3);border-radius:8px;padding:12px;font-size:13px;color:var(--accent3);margin-bottom:14px"></div>

  <button id="submitBtn" class="btn-primary" style="width:100%;padding:13px;font-size:14px" onclick="submitReport()">Submit Report</button>
</div>

<script>
let selectedLat = null;
let selectedLng = null;
let marker      = null;

const map = L.map('reportMap', {
  center: [6.2232, 125.0721],
  zoom:   14,
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap',
  maxZoom: 20,
}).addTo(map);

const pinIcon = L.divIcon({
  className: '',
  html: '<div style="font-size:28px;filter:drop-shadow(0 2px 4px rgba(0,0,0,.5));line-height:1">📍</div>',
  iconSize:   [28, 28],
  iconAnchor: [14, 28],
});

map.on('click', (e) => {
  selectedLat = e.latlng.lat;
  selectedLng = e.latlng.lng;

  if (marker) map.removeLayer(marker);
  marker = L.marker([selectedLat, selectedLng], { icon: pinIcon }).addTo(map);
  marker.bindPopup(`<b>Issue Location</b><br>${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)}`).openPopup();

  document.getElementById('coordDisplay').innerHTML =
    `<span style="color:var(--accent3)">✓ Location selected:</span> ${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)}`;
});

async function submitReport() {
  const issueType   = document.getElementById('issueType').value;
  const description = document.getElementById('description').value.trim();
  const contact     = document.getElementById('contact').value.trim();
  const locationText = document.getElementById('locationText').value.trim();
  const errEl = document.getElementById('errMsg');
  const okEl  = document.getElementById('okMsg');
  errEl.style.display = 'none';
  okEl.style.display  = 'none';

  if (!issueType)   { errEl.textContent = 'Please select an issue type.'; errEl.style.display = 'block'; return; }
  if (!description) { errEl.textContent = 'Please enter a description.'; errEl.style.display = 'block'; return; }
  if (!selectedLat || !selectedLng) { errEl.textContent = 'Please click on the map to set the issue location.'; errEl.style.display = 'block'; return; }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Submitting…';

  const fd = new FormData();
  fd.append('action', 'submit_request');
  fd.append('issue_type', issueType);
  fd.append('description', description);
  fd.append('contact', contact);
  fd.append('latitude', selectedLat);
  fd.append('longitude', selectedLng);
  fd.append('location_text', locationText);

  try {
    const res  = await fetch(CONSUMER_API, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    if (data.success) {
      okEl.textContent = `✅ Report submitted successfully! Request #${data.id}`;
      okEl.style.display = 'block';
      // Reset form
      document.getElementById('issueType').value   = '';
      document.getElementById('description').value = '';
      document.getElementById('locationText').value = '';
      if (marker) { map.removeLayer(marker); marker = null; }
      selectedLat = null; selectedLng = null;
      document.getElementById('coordDisplay').textContent = 'No location selected yet.';
    } else {
      errEl.textContent = data.error || 'Submission failed.';
      errEl.style.display = 'block';
    }
  } catch {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Submit Report';
  }
}
</script>