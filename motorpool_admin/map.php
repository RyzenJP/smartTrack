<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vehicle Overview | Smart Track</title>
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 250px;
      height: 100vh;
      background-color: var(--primary);
      color: #fff;
      transition: all 0.3s ease;
      z-index: 1000;
      padding-top: 60px;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #ffffff20 #001d3d;
    }

    .sidebar::-webkit-scrollbar {
      width: 6px;
    }

    .sidebar::-webkit-scrollbar-thumb {
      background-color: #ffffffcc;
      border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }

    .sidebar.collapsed {
      width: 70px;
    }

    /* Hide chevrons when sidebar is collapsed */
    .sidebar.collapsed .dropdown-chevron {
      display: none;
    }


    .sidebar a {
      display: block;
      padding: 14px 20px;
      color: #d9d9d9;
      text-decoration: none;
      transition: background 0.2s;
      white-space: nowrap;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #001d3d;
      color: var(--accent);
    }

    /* Dropdown submenu links design */
    .sidebar .collapse a {
      color: #d9d9d9;
      font-size: 0.95rem;
      padding: 10px 16px;
      margin: 4px 8px;
      border-radius: 0.35rem;
      display: block;
      text-decoration: none;
      transition: background 0.2s, color 0.2s;
    }

    .sidebar .collapse a:hover {
      background-color: #002855;
      color: var(--accent);
    }

    /* Custom chevron icon for dropdown */
    .dropdown-chevron {
    color: #ffffff;
    transition: transform 0.3s ease, color 0.2s ease;
    }

    .dropdown-chevron:hover {
    color: var(--accent);
    }

    /* Rotate chevron when dropdown is expanded */
    .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
      transform: rotate(90deg);
    }

    .dropdown-toggle::after {
      display: none;
    }

    .main-content {
      margin-left: 250px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    .navbar {
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      border-bottom: 1px solid #dee2e6;
      z-index: 1100;
    }

    /* Admin Dropdown Menu Styling */
    .dropdown-menu {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    min-width: 190px;
    padding: 0.4rem 0;
    background-color: #ffffff;
    animation: fadeIn 0.25s ease-in-out;
    }

    @keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
    }

    /* Dropdown items */
    .dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    font-size: 0.95rem;
    color: #343a40;
    transition: all 0.3s ease;
    border-radius: 0.35rem;
    }

    /* Hover effect */
    .dropdown-menu .dropdown-item:hover {
    background-color: #001d3d; /* deep navy like sidebar */
    color: var(--accent); /* aqua blue */
    box-shadow: inset 2px 0 0 var(--accent); /* accent highlight */
    }

    /* Icon transition */
    .dropdown-menu .dropdown-item i {
    margin-right: 10px;
    color: #6c757d;
    transition: color 0.3s ease;
    }

    .dropdown-menu .dropdown-item:hover i {
    color: var(--accent);
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }

    .card-icon {
      font-size: 2rem;
      color: var(--accent);
    }

    .card h6 {
      font-weight: 500;
    }

    .card h4 {
      font-weight: bold;
    }

    #map {
      width: 100%;
      height: 400px;
      background-color: #e9ecef;
      border-radius: 0.5rem;
    }
     .leaflet-control-attribution {
    font-size: 9px;
    }
  
    .vehicle-marker {
      background-color: var(--accent);
      border-radius: 50%;
      width: 16px;
      height: 16px;
      display: block;
      border: 2px solid white;
      box-shadow: 0 0 5px rgba(0,0,0,0.3);
    }
    
    /* Vehicle Overview Specific Styles */
    .vehicle-card {
      transition: transform 0.2s, box-shadow 0.2s;
      cursor: pointer;
    }
    
    .vehicle-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .vehicle-status {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .status-active {
      background-color: rgba(0, 180, 216, 0.1);
      color: #00b4d8;
    }
    
    .status-idle {
      background-color: rgba(255, 193, 7, 0.1);
      color: #ffc107;
    }
    
    .status-maintenance {
      background-color: rgba(220, 53, 69, 0.1);
      color: #dc3545;
    }
    
    .status-offline {
      background-color: rgba(108, 117, 125, 0.1);
      color: #6c757d;
    }
    
    .vehicle-img-container {
      height: 120px;
      background-color: #f8f9fa;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px 8px 0 0;
      overflow: hidden;
    }
    
    .vehicle-img {
      max-height: 100%;
      max-width: 100%;
      object-fit: contain;
    }
    
    .vehicle-info {
      padding: 15px;
    }
    
    .vehicle-model {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .vehicle-id {
      color: #6c757d;
      font-size: 14px;
      margin-bottom: 10px;
    }
    
    .vehicle-specs {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: #6c757d;
      margin-top: 10px;
    }
    
    .vehicle-spec {
      display: flex;
      align-items: center;
    }
    
    .vehicle-spec i {
      margin-right: 5px;
      font-size: 14px;
    }
    
    .search-container {
      position: relative;
      margin-bottom: 20px;
    }
    
    .search-container i {
      position: absolute;
      left: 15px;
      top: 12px;
      color: #6c757d;
    }
    
    .search-container input {
      padding-left: 40px;
      border-radius: 30px;
      border: 1px solid #dee2e6;
    }
    
    .filter-btn {
      border-radius: 30px;
      padding: 8px 20px;
      font-size: 14px;
      background-color: white;
      border: 1px solid #dee2e6;
      color: #495057;
    }
    
    .filter-btn:hover {
      background-color: #f8f9fa;
    }
    
    .filter-btn i {
      margin-right: 5px;
    }
    
    .map-container {
      position: relative;
    }
    
    .map-controls {
      position: absolute;
      top: 15px;
      right: 15px;
      z-index: 1000;
      background: white;
      border-radius: 5px;
      padding: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .map-control-btn {
      background: none;
      border: none;
      padding: 5px 10px;
      font-size: 14px;
      color: #495057;
      border-radius: 4px;
    }
    
    .map-control-btn:hover {
      background-color: #f8f9fa;
    }
    
    .map-control-btn.active {
      background-color: #00b4d8;
      color: white;
    }
    
    .map-control-btn i {
      margin-right: 5px;
    }
    
    .vehicle-table th {
      font-weight: 600;
      color: #495057;
      background-color: #f8f9fa;
    }
    
    .vehicle-table td {
      vertical-align: middle;
    }
    
    .badge-pill {
      padding: 5px 10px;
      font-weight: 500;
    }
    
    .view-toggle {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
    }
    
    .view-toggle-btn {
      background: none;
      border: none;
      padding: 8px 15px;
      font-size: 14px;
      color: #6c757d;
      border-radius: 4px;
      margin-left: 5px;
    }
    
    .view-toggle-btn.active {
      background-color: #00b4d8;
      color: white;
    }
    
    .view-toggle-btn i {
      margin-right: 5px;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="container-fluid">
      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary">Vehicle Overview</h2>
        <div class="view-toggle">
          <button class="view-toggle-btn active" id="gridViewBtn">
            <i class="fas fa-th-large"></i> Grid
          </button>
          <button class="view-toggle-btn" id="tableViewBtn">
            <i class="fas fa-table"></i> Table
          </button>
        </div>
      </div>
      
      <!-- Stats Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-car"></i></div>
              <div>
                <h6>Total Vehicles</h6>
                <h4>24</h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-satellite-dish"></i></div>
              <div>
                <h6>Active Vehicles</h6>
                <h4>18</h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-parking"></i></div>
              <div>
                <h6>Idle Vehicles</h6>
                <h4>4</h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-tools"></i></div>
              <div>
                <h6>In Maintenance</h6>
                <h4>2</h4>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Search and Filters -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="search-container" style="width: 300px;">
          <i class="fas fa-search"></i>
          <input type="text" class="form-control" placeholder="Search vehicles...">
        </div>
        <div>
          <button class="filter-btn me-2">
            <i class="fas fa-filter"></i> Status
          </button>
          <button class="filter-btn">
            <i class="fas fa-sort"></i> Sort
          </button>
        </div>
      </div>
      
      <!-- Grid View -->
      <div class="row g-4" id="gridView">
        <!-- Vehicle Card 1 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Toyota+Innova" class="vehicle-img" alt="Toyota Innova">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Toyota Innova</h5>
                  <div class="vehicle-id">ID: VH-001</div>
                </div>
                <span class="vehicle-status status-active">Active</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 45,320 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 2 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Honda+City" class="vehicle-img" alt="Honda City">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Honda City</h5>
                  <div class="vehicle-id">ID: VH-002</div>
                </div>
                <span class="vehicle-status status-idle">Idle</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Petrol
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 32,150 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 3 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Mitsubishi+Montero" class="vehicle-img" alt="Mitsubishi Montero">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Mitsubishi Montero</h5>
                  <div class="vehicle-id">ID: VH-003</div>
                </div>
                <span class="vehicle-status status-maintenance">Maintenance</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 78,450 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 4 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Isuzu+D-Max" class="vehicle-img" alt="Isuzu D-Max">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Isuzu D-Max</h5>
                  <div class="vehicle-id">ID: VH-004</div>
                </div>
                <span class="vehicle-status status-active">Active</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 56,780 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 5 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Toyota+Hiace" class="vehicle-img" alt="Toyota Hiace">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Toyota Hiace</h5>
                  <div class="vehicle-id">ID: VH-005</div>
                </div>
                <span class="vehicle-status status-active">Active</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 102,340 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 6 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Ford+Ranger" class="vehicle-img" alt="Ford Ranger">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Ford Ranger</h5>
                  <div class="vehicle-id">ID: VH-006</div>
                </div>
                <span class="vehicle-status status-idle">Idle</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 67,890 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 7 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Nissan+Navara" class="vehicle-img" alt="Nissan Navara">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Nissan Navara</h5>
                  <div class="vehicle-id">ID: VH-007</div>
                </div>
                <span class="vehicle-status status-maintenance">Maintenance</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 89,120 km
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Card 8 -->
        <div class="col-md-4 col-lg-3">
          <div class="card vehicle-card shadow-sm">
            <div class="vehicle-img-container">
              <img src="https://via.placeholder.com/300x150?text=Toyota+Fortuner" class="vehicle-img" alt="Toyota Fortuner">
            </div>
            <div class="vehicle-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="vehicle-model">Toyota Fortuner</h5>
                  <div class="vehicle-id">ID: VH-008</div>
                </div>
                <span class="vehicle-status status-active">Active</span>
              </div>
              <div class="vehicle-specs">
                <div class="vehicle-spec">
                  <i class="fas fa-gas-pump"></i> Diesel
                </div>
                <div class="vehicle-spec">
                  <i class="fas fa-tachometer-alt"></i> 34,560 km
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Table View (Hidden by default) -->
      <div class="card shadow-sm mb-4 d-none" id="tableView">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table vehicle-table">
              <thead>
                <tr>
                  <th>Vehicle</th>
                  <th>ID</th>
                  <th>Status</th>
                  <th>Type</th>
                  <th>Fuel</th>
                  <th>Mileage</th>
                  <th>Last Activity</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://via.placeholder.com/50x30?text=Toyota+Innova" class="rounded me-3" style="width: 50px; height: 30px; object-fit: cover;">
                      <div>Toyota Innova</div>
                    </div>
                  </td>
                  <td>VH-001</td>
                  <td><span class="vehicle-status status-active">Active</span></td>
                  <td>MPV</td>
                  <td>Diesel</td>
                  <td>45,320 km</td>
                  <td>5 min ago</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://via.placeholder.com/50x30?text=Honda+City" class="rounded me-3" style="width: 50px; height: 30px; object-fit: cover;">
                      <div>Honda City</div>
                    </div>
                  </td>
                  <td>VH-002</td>
                  <td><span class="vehicle-status status-idle">Idle</span></td>
                  <td>Sedan</td>
                  <td>Petrol</td>
                  <td>32,150 km</td>
                  <td>2 hours ago</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://via.placeholder.com/50x30?text=Mitsubishi+Montero" class="rounded me-3" style="width: 50px; height: 30px; object-fit: cover;">
                      <div>Mitsubishi Montero</div>
                    </div>
                  </td>
                  <td>VH-003</td>
                  <td><span class="vehicle-status status-maintenance">Maintenance</span></td>
                  <td>SUV</td>
                  <td>Diesel</td>
                  <td>78,450 km</td>
                  <td>1 day ago</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://via.placeholder.com/50x30?text=Isuzu+D-Max" class="rounded me-3" style="width: 50px; height: 30px; object-fit: cover;">
                      <div>Isuzu D-Max</div>
                    </div>
                  </td>
                  <td>VH-004</td>
                  <td><span class="vehicle-status status-active">Active</span></td>
                  <td>Pickup</td>
                  <td>Diesel</td>
                  <td>56,780 km</td>
                  <td>15 min ago</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://via.placeholder.com/50x30?text=Toyota+Hiace" class="rounded me-3" style="width: 50px; height: 30px; object-fit: cover;">
                      <div>Toyota Hiace</div>
                    </div>
                  </td>
                  <td>VH-005</td>
                  <td><span class="vehicle-status status-active">Active</span></td>
                  <td>Van</td>
                  <td>Diesel</td>
                  <td>102,340 km</td>
                  <td>30 min ago</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- Map Section -->
      <div class="row mt-4">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title text-primary">Vehicle Locations</h5>
              <div class="map-container">
                <div id="map"></div>
                <div class="map-controls">
                  <button class="map-control-btn active">
                    <i class="fas fa-car"></i> All
                  </button>
                  <button class="map-control-btn">
                    <i class="fas fa-satellite-dish"></i> Active
                  </button>
                  <button class="map-control-btn">
                    <i class="fas fa-parking"></i> Idle
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
    const isCollapsed = sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');

    // Toggle text visibility
    linkTexts.forEach(text => {
      text.style.display = isCollapsed ? 'none' : 'inline';
    });

    // Manage dropdown chevron interactivity
    dropdownToggles.forEach(toggle => {
      const chevron = toggle.querySelector('.dropdown-chevron');
      if (isCollapsed) {
        chevron.classList.add('disabled-chevron');
        chevron.style.cursor = 'not-allowed';
        chevron.setAttribute('title', 'Expand sidebar to activate');
        toggle.setAttribute('data-bs-toggle', ''); // disable collapse
      } else {
        chevron.classList.remove('disabled-chevron');
        chevron.style.cursor = 'pointer';
        chevron.removeAttribute('title');
        toggle.setAttribute('data-bs-toggle', 'collapse'); // enable collapse
      }
    });

    // 🚨 Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  });
  
  // View Toggle
  const gridViewBtn = document.getElementById('gridViewBtn');
  const tableViewBtn = document.getElementById('tableViewBtn');
  const gridView = document.getElementById('gridView');
  const tableView = document.getElementById('tableView');
  
  gridViewBtn.addEventListener('click', () => {
    gridViewBtn.classList.add('active');
    tableViewBtn.classList.remove('active');
    gridView.classList.remove('d-none');
    tableView.classList.add('d-none');
  });
  
  tableViewBtn.addEventListener('click', () => {
    tableViewBtn.classList.add('active');
    gridViewBtn.classList.remove('active');
    tableView.classList.remove('d-none');
    gridView.classList.add('d-none');
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['login_success'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Login Successful',
        text: '<?= $_SESSION['login_success'] ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();

        Swal.fire({
          title: 'Log out?',
          text: "Are you sure you want to log out?",
          icon: 'question',
          iconColor: '#00b4d8',
          showCancelButton: true,
          confirmButtonColor: '#003566',
          cancelButtonColor: '#6c757d',
          confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Yes',
          cancelButtonText: '<i class="fas fa-times-circle me-1"></i> Cancel',
          reverseButtons: true,
          background: '#f8f9fa',
          color: '#212529',
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn',
            cancelButton: 'swal-btn'
          },
          didRender: () => {
            const buttons = document.querySelectorAll('.swal-btn');
            buttons.forEach(btn => {
              btn.style.minWidth = '120px';
              btn.style.padding = '10px 16px';
              btn.style.fontSize = '15px';
            });
          }
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire({
              title: 'Logging out...',
              icon: 'info',
              showConfirmButton: false,
              timer: 1000,
              willClose: () => {
                window.location.href = '/tracking/logout.php';
              }
            });
          }
        });
      });
    }
  });
</script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
  // Initialize the map centered on Bago City
  const map = L.map('map').setView([10.5333, 122.8333], 13);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // Add some sample vehicle markers (you would replace these with real data)
  const vehicleLocations = [
    { lat: 10.538, lng: 122.838, id: 'VH-001', model: 'Toyota Innova', status: 'active', lastUpdate: '5 min ago', speed: '45 km/h' },
    { lat: 10.531297, lng: 122.842783, id: 'VH-002', model: 'Honda City', status: 'idle', lastUpdate: '2 hours ago', speed: '0 km/h' },
    { lat: 10.529793, lng: 122.838619, id: 'VH-003', model: 'Mitsubishi Montero', status: 'maintenance', lastUpdate: '1 day ago', speed: '0 km/h' },
    { lat: 10.538434, lng: 122.831759, id: 'VH-004', model: 'Isuzu D-Max', status: 'active', lastUpdate: '15 min ago', speed: '32 km/h' },
    { lat: 10.535, lng: 122.835, id: 'VH-005', model: 'Toyota Hiace', status: 'active', lastUpdate: '30 min ago', speed: '28 km/h' },
    { lat: 10.537, lng: 122.830, id: 'VH-006', model: 'Ford Ranger', status: 'idle', lastUpdate: '3 hours ago', speed: '0 km/h' }
  ];

  vehicleLocations.forEach(vehicle => {
    const marker = L.marker([vehicle.lat, vehicle.lng], {
      icon: L.divIcon({
        className: `vehicle-icon ${vehicle.status}`,
        html: `<div class="vehicle-marker" style="background-color: ${
          vehicle.status === 'active' ? '#00b4d8' : 
          vehicle.status === 'idle' ? '#ffc107' : 
          '#dc3545'
        }"></div>`,
        iconSize: [20, 20]
      })
    }).addTo(map);
    
    marker.bindPopup(`
      <div class="vehicle-popup">
        <strong>${vehicle.model}</strong><br>
        <strong>ID:</strong> ${vehicle.id}<br>
        <strong>Status:</strong> ${vehicle.status.charAt(0).toUpperCase() + vehicle.status.slice(1)}<br>
        <strong>Last update:</strong> ${vehicle.lastUpdate}<br>
        <strong>Speed:</strong> ${vehicle.speed}
      </div>
    `);
  });
</script>
</body>
</html>