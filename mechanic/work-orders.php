<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit;
}
$me = $_SESSION['user_id'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Work Orders - Mechanic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style> .card-icon{font-size:1.4rem;color:#00b4d8} </style>
</head>
<body>
<?php include __DIR__ . '/mechanic_sidebar.php'; /* your sidebar */ ?>
<?php include __DIR__ . '/mechanic_navbar.php'; /* your navbar */ ?>

<div class="container-fluid mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Work Orders</h3>
    <div>
      <button class="btn btn-primary" id="btnNewOrder"><i class="fas fa-plus"></i> New Order</button>
      <button class="btn btn-outline-secondary" id="btnRefresh">Refresh</button>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3" id="tabs">
    <li class="nav-item"><a class="nav-link active" data-status="new" href="#">New</a></li>
    <li class="nav-item"><a class="nav-link" data-status="my" href="#">Assigned to Me</a></li>
    <li class="nav-item"><a class="nav-link" data-status="assigned" href="#">Assigned (All)</a></li>
    <li class="nav-item"><a class="nav-link" data-status="completed" href="#">Completed</a></li>
  </ul>

  <div id="listArea"></div>
</div>

<!-- Modal: Create/Edit -->
<div class="modal fade" id="orderModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="orderForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderModalTitle">New Work Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="orderId">
        <div class="row g-2">
          <div class="col-md-6">
            <label>Vehicle</label>
            <select class="form-select" id="vehicle_id" name="vehicle_id" required></select>
          </div>
          <div class="col-md-6">
            <label>Priority</label>
            <select class="form-select" id="priority" name="priority">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="col-12">
            <label>Title</label>
            <input type="text" id="title" name="title" class="form-control" required>
          </div>
          <div class="col-12">
            <label>Description</label>
            <textarea id="description" name="description" class="form-control" rows="4"></textarea>
          </div>
          <div class="col-md-6">
            <label>Scheduled Start</label>
            <input type="datetime-local" id="scheduled_start" name="scheduled_start" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Scheduled End</label>
            <input type="datetime-local" id="scheduled_end" name="scheduled_end" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="saveOrder">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const api = 'mechanic_api.php';
let currentTab = 'new';

// load vehicles for select
async function loadVehicles(){
  const res = await fetch('../ajax_get_vehicles.php'); // you can create simple endpoint to return vehicles JSON
  const json = await res.json();
  const sel = document.getElementById('vehicle_id');
  sel.innerHTML = '<option value="">--Select Vehicle--</option>';
  json.data.forEach(v => {
    const opt = document.createElement('option');
    opt.value = v.id;
    opt.textContent = `${v.article} (${v.plate_number})`;
    sel.appendChild(opt);
  });
}

// render list
async function loadList(status){
  const url = new URL(api, location.href);
  url.searchParams.set('action','list');
  if(status) url.searchParams.set('status', status);
  const res = await fetch(url);
  const j = await res.json();
  if(!j.success) { document.getElementById('listArea').innerHTML = '<div class="alert alert-danger">Error loading</div>'; return; }
  const data = j.data;
  if(data.length === 0){
    document.getElementById('listArea').innerHTML = '<div class="alert alert-info">No work orders found</div>';
    return;
  }
  let html = '<div class="list-group">';
  data.forEach(item => {
    html += `<div class="list-group-item">
      <div class="d-flex justify-content-between">
        <div><strong>#${item.id}</strong> ${escapeHtml(item.title)} <br><small>${item.vehicle_article || 'Vehicle N/A'} • ${item.plate_number || ''}</small></div>
        <div>
          <span class="badge ${badgeByPriority(item.priority)}">${item.priority}</span>
          <span class="badge ${badgeByStatus(item.status)}">${item.status}</span>
        </div>
      </div>
      <div class="mt-2">
        <small>${escapeHtml(item.description || '')}</small>
      </div>
      <div class="mt-2 text-end">
        ${actionButtons(item)}
      </div>
    </div>`;
  });
  html += '</div>';
  document.getElementById('listArea').innerHTML = html;
}

function badgeByPriority(p){
  if(p=='high') return 'bg-danger text-white';
  if(p=='medium') return 'bg-warning text-dark';
  return 'bg-secondary text-white';
}
function badgeByStatus(s){
  if(s=='new') return 'bg-primary text-white';
  if(s=='assigned') return 'bg-info text-dark';
  if(s=='in_progress') return 'bg-warning text-dark';
  if(s=='completed') return 'bg-success text-white';
  if(s=='cancelled') return 'bg-danger text-white';
  return 'bg-secondary';
}
function actionButtons(item){
  const id = item.id;
  let btns = '';
  // view/edit allowed to creator/admin or assigned mechanic (server enforces)
  btns += `<button class="btn btn-sm btn-outline-secondary me-1" onclick="editOrder(${id})"><i class="fas fa-edit"></i> Edit</button>`;
  // if not assigned -> allow admin to assign (we'll open a small prompt to assign)
  btns += `<button class="btn btn-sm btn-outline-primary me-1" onclick="assignPrompt(${id})"><i class="fas fa-user-plus"></i> Assign</button>`;
  // status actions (start / complete)
  if(item.status !== 'completed'){
    btns += `<button class="btn btn-sm btn-success me-1" onclick="changeStatus(${id}, 'in_progress')"><i class="fas fa-play"></i> Start</button>`;
    btns += `<button class="btn btn-sm btn-danger me-1" onclick="changeStatus(${id}, 'completed')"><i class="fas fa-check"></i> Complete</button>`;
  }
  // delete (admin only on server)
  btns += `<button class="btn btn-sm btn-outline-danger" onclick="deleteOrder(${id})"><i class="fas fa-trash"></i> Delete</button>`;
  return btns;
}

function escapeHtml(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.getElementById('btnNewOrder').addEventListener('click', async () => {
  document.getElementById('orderForm').reset();
  document.getElementById('orderId').value = '';
  document.getElementById('orderModalTitle').textContent = 'New Work Order';
  await loadVehicles();
  new bootstrap.Modal(document.getElementById('orderModal')).show();
});

document.querySelectorAll('#tabs a').forEach(a=>{
  a.addEventListener('click', (e)=>{
    e.preventDefault();
    document.querySelectorAll('#tabs a').forEach(x=>x.classList.remove('active'));
    a.classList.add('active');
    currentTab = a.dataset.status;
    loadList(currentTab);
  });
});

document.getElementById('btnRefresh').addEventListener('click', ()=> loadList(currentTab));

// Save (create/update)
document.getElementById('orderForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = new FormData(e.target);
  const id = form.get('id');
  const action = id ? 'update' : 'create';
  const res = await fetch(api + '?action=' + action, { method:'POST', body: form });
  const j = await res.json();
  if(j.success){
    bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
    loadList(currentTab);
    alert('Saved');
  } else {
    alert(j.message || 'Error');
  }
});

async function editOrder(id){
  await loadVehicles();
  // fetch item
  const r = await fetch(api + '?action=get&id=' + id);
  const j = await r.json();
  if(!j.success) { alert('Not found'); return; }
  const data = j.data;
  document.getElementById('orderId').value = data.id;
  document.getElementById('vehicle_id').value = data.vehicle_id || '';
  document.getElementById('priority').value = data.priority || 'medium';
  document.getElementById('title').value = data.title || '';
  document.getElementById('description').value = data.description || '';
  if(data.scheduled_start) document.getElementById('scheduled_start').value = data.scheduled_start.replace(' ', 'T');
  if(data.scheduled_end) document.getElementById('scheduled_end').value = data.scheduled_end.replace(' ', 'T');
  document.getElementById('orderModalTitle').textContent = 'Edit Work Order #' + id;
  new bootstrap.Modal(document.getElementById('orderModal')).show();
}

async function assignPrompt(id){
  const user = prompt('Enter mechanic user_id to assign (example: 5)');
  if(!user) return;
  const fd = new FormData();
  fd.append('id', id);
  fd.append('assign_to', user);
  const res = await fetch(api + '?action=assign', { method:'POST', body: fd });
  const j = await res.json();
  if(j.success) { alert('Assigned'); loadList(currentTab); } else alert(j.message || 'Error');
}

async function changeStatus(id, status){
  const fd = new FormData();
  fd.append('id', id);
  fd.append('status', status);
  const res = await fetch(api + '?action=change_status', { method:'POST', body: fd });
  const j = await res.json();
  if(j.success) { loadList(currentTab); } else alert(j.message || 'Error');
}

async function deleteOrder(id){
  if(!confirm('Delete work order #' + id + '?')) return;
  const fd = new FormData();
  fd.append('id', id);
  const res = await fetch(api + '?action=delete', { method:'POST', body: fd });
  const j = await res.json();
  if(j.success) { loadList(currentTab); } else alert(j.message || 'Error');
}

loadList('new'); // initial
loadVehicles();
</script>
</body>
</html>
