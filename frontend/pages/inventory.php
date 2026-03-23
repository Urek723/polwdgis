<?php
$pageTitle = 'Inventory';
require_once 'layout.php';
?>
<style>
.inv-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:8px;display:grid;grid-template-columns:1fr 120px 120px auto;gap:12px;align-items:center}
.stock-bar{height:6px;background:var(--border);border-radius:3px;margin-top:4px}
.stock-fill{height:6px;border-radius:3px;transition:width .3s}
.low-stock{border-color:var(--warn)!important}
</style>

<main class="main">
<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openModal('minv')">+ Add Item</button>
  <button class="btn-secondary" onclick="openModal('mtxn')">📦 Record Transaction</button>
  <?php endif; ?>
  <input id="fq" placeholder="Search inventory…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
  <select id="fc" onchange="load()" class="filter-input"><option value="">All Categories</option><option>Pipes</option><option>Fittings</option><option>Valves</option><option>Tools</option><option>Chemicals</option><option>Equipment</option><option>Other</option></select>
</div>
<div id="invStats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px"></div>
<div id="invList"><div class="spinner"></div></div>

<!-- Add Item Modal -->
<div id="minv" class="modal-overlay">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header"><h3>Add Inventory Item</h3><button onclick="closeModal('minv')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="iname" placeholder="Item name *" class="form-input">
      <select id="icat" class="form-input"><option>Pipes</option><option>Fittings</option><option>Valves</option><option>Tools</option><option>Chemicals</option><option>Equipment</option><option>Other</option></select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="iunit" placeholder="Unit (e.g. pcs, m)" class="form-input">
        <input id="iqty" type="number" placeholder="Initial Qty" class="form-input" value="0">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="ireorder" type="number" placeholder="Reorder Level" class="form-input">
        <input id="icost" type="number" step="0.01" placeholder="Unit Cost" class="form-input">
      </div>
      <input id="isupplier" placeholder="Supplier" class="form-input">
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('minv')" class="btn-secondary">Cancel</button>
        <button onclick="submitItem()" class="btn-primary">Add Item</button>
      </div>
    </div>
  </div>
</div>

<!-- Transaction Modal -->
<div id="mtxn" class="modal-overlay">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header"><h3>Record Transaction</h3><button onclick="closeModal('mtxn')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <select id="txitem" class="form-input"><option value="">Select Item…</option></select>
      <select id="txtype" class="form-input"><option value="In">Stock In</option><option value="Out">Stock Out</option><option value="Adjustment">Adjustment</option></select>
      <input id="txqty" type="number" placeholder="Quantity *" class="form-input">
      <input id="txref" placeholder="Reference (WO#, PO#, etc.)" class="form-input">
      <textarea id="txnotes" placeholder="Notes" class="form-input" rows="2"></textarea>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mtxn')" class="btn-secondary">Cancel</button>
        <button onclick="submitTxn()" class="btn-primary">Record</button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
let itemsCache=[];

async function load(){
  const q=document.getElementById('fq').value,cat=document.getElementById('fc').value;
  const d=await apiGet('maintenance.php',{action:'get_inventory',category:cat});
  let items=d?.items||d?.data||[];
  if(q) items=items.filter(i=>(i.name+' '+(i.supplier||'')).toLowerCase().includes(q.toLowerCase()));
  itemsCache=items;
  // Populate txn select
  document.getElementById('txitem').innerHTML='<option value="">Select Item…</option>'+
    items.map(i=>`<option value="${i.id}">${i.name} (${i.quantity_in_stock} ${i.unit})</option>`).join('');
  const el=document.getElementById('invList');
  if(!items.length){el.innerHTML='<p style="color:var(--muted);padding:20px 0">No inventory items.</p>';renderStats([]);return;}
  renderStats(items);
  el.innerHTML=items.map(i=>{
    const pct=i.reorder_level>0?Math.min(100,Math.round(i.quantity_in_stock/i.reorder_level*50)):100;
    const low=i.quantity_in_stock<=i.reorder_level;
    return `<div class="inv-row ${low?'low-stock':''}">
      <div>
        <div style="font-weight:600">${i.name} ${low?'⚠️':''}</div>
        <div style="font-size:12px;color:var(--muted)">${i.category} · ${i.supplier||''}</div>
        ${i.reorder_level?`<div class="stock-bar"><div class="stock-fill" style="width:${pct}%;background:${low?'var(--danger)':'var(--accent3)'}"></div></div>`:''}
      </div>
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:700;color:${low?'var(--danger)':'var(--text)'}">${i.quantity_in_stock}</div>
        <div style="font-size:11px;color:var(--muted)">${i.unit}</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:13px;color:var(--muted)">Reorder: ${i.reorder_level||'—'}</div>
        <div style="font-size:12px;color:var(--muted)">${i.unit_cost?'₱'+parseFloat(i.unit_cost).toFixed(2):''}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px">
        <button onclick="quickTxn(${i.id},'In')" class="btn-secondary" style="font-size:11px;padding:3px 8px">+In</button>
        <button onclick="quickTxn(${i.id},'Out')" class="btn-secondary" style="font-size:11px;padding:3px 8px">-Out</button>
      </div>
    </div>`;
  }).join('');
}

function renderStats(items){
  const low=items.filter(i=>i.quantity_in_stock<=i.reorder_level).length;
  const val=items.reduce((s,i)=>s+(i.quantity_in_stock*(parseFloat(i.unit_cost)||0)),0);
  document.getElementById('invStats').innerHTML=`
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--accent)">${items.length}</div>
      <div style="font-size:11px;color:var(--muted)">Total Items</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:${low?'var(--danger)':'var(--accent3)'}">${low}</div>
      <div style="font-size:11px;color:var(--muted)">Low Stock</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:18px;font-weight:700;color:var(--accent3)">₱${val.toLocaleString('en',{maximumFractionDigits:0})}</div>
      <div style="font-size:11px;color:var(--muted)">Stock Value</div>
    </div>`;
}

async function submitItem(){
  const r=await apiPost('maintenance.php',{action:'save_inventory_item',
    name:document.getElementById('iname').value,category:document.getElementById('icat').value,
    unit:document.getElementById('iunit').value,quantity_in_stock:document.getElementById('iqty').value,
    reorder_level:document.getElementById('ireorder').value,unit_cost:document.getElementById('icost').value,
    supplier:document.getElementById('isupplier').value});
  if(r?.success||r?.id){closeModal('minv');load();showToast('Item added','success');}
  else showToast(r?.error||'Failed','error');
}

function quickTxn(id,type){
  document.getElementById('txitem').value=id;
  document.getElementById('txtype').value=type;
  openModal('mtxn');
}

async function submitTxn(){
  const r=await apiPost('maintenance.php',{action:'inventory_transaction',
    item_id:document.getElementById('txitem').value,
    transaction_type:document.getElementById('txtype').value,
    quantity:document.getElementById('txqty').value,
    reference:document.getElementById('txref').value,
    notes:document.getElementById('txnotes').value});
  if(r?.success||r?.ok){closeModal('mtxn');load();showToast('Transaction recorded','success');}
  else showToast(r?.error||'Failed','error');
}

document.addEventListener('DOMContentLoaded',load);
</script>
