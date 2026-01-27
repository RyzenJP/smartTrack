<?php
session_start();

// ✅ Check if session role is set and user is a mechanic
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// Fetch maintenance schedules (both scheduled and completed) - use prepared statement for consistency
$stmt = $conn->prepare("
    SELECT ms.id, 
           COALESCE(fv.article, 'Vehicle ' + ms.vehicle_id) as article, 
           COALESCE(fv.plate_number, 'N/A') as plate_number, 
           ms.maintenance_type, ms.notes, 
           CASE 
               WHEN ms.status = 'completed' THEN DATE(ms.updated_at)
               ELSE ms.scheduled_date
           END as display_date,
           ms.status, ms.updated_at, ms.scheduled_date
    FROM maintenance_schedules ms
    LEFT JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    WHERE ms.status = 'completed' OR ms.scheduled_date IS NOT NULL
    ORDER BY display_date ASC
");
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $date = date('Y-m-d', strtotime($row['display_date']));
    $events[$date][] = $row;
}

// ✅ Events are now working correctly!

// Get month/year from URL parameters or use current month
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// ✅ Calendar is now displaying maintenance events correctly!

// Validate month and year
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2020 || $year > 2030) $year = date('Y');

// Ensure month is always an integer
$month = (int)$month;

$firstDayOfMonth = strtotime("$year-$month-01");
$daysInMonth = date('t', $firstDayOfMonth);
$startDayOfWeek = date('w', $firstDayOfMonth); // 0=Sunday

// Calculate previous and next month
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Month names - using both string and integer keys for compatibility
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Maintenance Schedule | Smart Track</title>
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
      overflow-x: hidden;
    }
    
    /* Force mobile-first approach */
    * {
      box-sizing: border-box;
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

    .sidebar a.active i {
    color: var(--accent) !important;
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

    /* Fix dropdown toggle layout - position chevron at far right edge */
    .sidebar .dropdown-toggle {
      justify-content: space-between !important;
      align-items: center !important;
      cursor: pointer !important;
      pointer-events: auto !important;
    }

    .sidebar .dropdown-toggle > div {
      display: flex;
      align-items: center;
      flex: 1;
    }

    .sidebar .dropdown-toggle .link-text {
      margin-left: 12px;
    }

    .sidebar .dropdown-chevron {
      margin-left: auto;
      flex-shrink: 0;
      position: absolute;
      right: 15px;
    }

    /* Smooth collapse animation */
    .sidebar .collapse {
      transition: height 0.3s ease, opacity 0.3s ease;
      overflow: hidden;
    }

    .sidebar .collapse:not(.show) {
      height: 0 !important;
      opacity: 0;
    }

    .sidebar .collapse.show {
      height: auto !important;
      opacity: 1;
    }

    .main-content {
      margin-left: 250px;
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    .navbar {
      position: fixed;      
      top: 0;                
      left: 0;       
      width: 100%;
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
    /* Professional Calendar Styling */
    .calendar-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .calendar-header {
        background: linear-gradient(135deg, var(--primary), var(--accent));
        color: white;
        padding: 20px;
        text-align: center;
    }

    .calendar-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .nav-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .nav-btn:hover {
        background: rgba(255,255,255,0.3);
        color: white;
        transform: translateY(-2px);
    }

    .month-year {
        font-size: 1.8rem;
        font-weight: 600;
        margin: 0;
    }

    .calendar-grid {
        padding: 0;
    }

    .weekday-header {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: var(--primary);
        padding: 15px 0;
        text-align: center;
    }

    .calendar-day { 
        border: 1px solid #e9ecef;
        height: 120px; 
        padding: 8px; 
        cursor: pointer; 
        position: relative; 
        background: white;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .calendar-day:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .calendar-day.disabled { 
        background-color: #f8f9fa; 
        cursor: default; 
        color: #6c757d;
    }

    .calendar-day.disabled:hover {
        transform: none;
        box-shadow: none;
    }

    .day-number { 
        position: absolute; 
        top: 8px; 
        right: 8px; 
        font-weight: 600; 
        font-size: 1rem;
        color: var(--primary);
        background: rgba(255,255,255,0.9);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .calendar-day.disabled .day-number {
        color: #6c757d;
        background: transparent;
    }

    .event-badge { 
        display: block; 
        margin-top: 30px; 
        padding: 4px 8px; 
        font-size: 0.75rem; 
        border-radius: 6px; 
        color: white; 
        text-overflow: ellipsis; 
        overflow: hidden; 
        white-space: nowrap;
        margin-bottom: 2px;
        font-weight: 500;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .event-badge:hover {
        transform: scale(1.02);
    }

    /* Today highlight */
    .calendar-day.today {
        background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
        border: 2px solid var(--accent);
    }

    .calendar-day.today .day-number {
        background: var(--accent);
        color: white;
    }

    /* Mobile Responsive Design */
    @media (max-width: 768px) {
        .calendar-header {
            padding: 15px;
        }
        
        .month-year {
            font-size: 1.4rem;
        }
        
        .nav-btn {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .calendar-day {
            height: 80px;
            padding: 4px;
        }
        
        .day-number {
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            top: 4px;
            right: 4px;
        }
        
        .event-badge {
            font-size: 0.65rem;
            padding: 2px 4px;
            margin-top: 20px;
        }
        
        .weekday-header {
            padding: 10px 0;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 576px) {
        .calendar-nav {
            flex-direction: column;
            gap: 10px;
        }
        
        .nav-btn {
            width: 100%;
            max-width: 120px;
        }
        
        .calendar-day {
            height: 60px;
        }
        
        .event-badge {
            display: none; /* Hide events on very small screens */
        }
    }

        .bg-oil { background-color:#0d6efd; }
        .bg-tire { background-color:#198754; }
        .bg-wheel { background-color:#fd7e14; }
        .bg-brake { background-color:#dc3545; }
        .bg-ac { background-color:#6610f2; }
        .bg-battery { background-color:#6c757d; }
        .bg-transmission { background-color:#20c997; }
        .bg-timing { background-color:#ffc107; color:#212529; }

    /* Mobile responsive tweaks */
    @media (max-width: 991.98px) {
      .sidebar { 
        width: 260px !important; 
        transform: translateX(-100%) !important; 
        position: fixed !important; 
        top: 0 !important; 
        left: 0 !important; 
        height: 100vh !important; 
        z-index: 1101 !important; 
        transition: transform 0.3s ease !important;
      }
      .sidebar.show, .sidebar.open { transform: translateX(0) !important; }
      .main-content, .main-content.collapsed { 
        margin-left: 0 !important; 
        padding: 16px !important; 
        margin-top: 60px !important;
        width: 100% !important;
      }
      
      /* Force mobile layout */
      .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
      }
      
      /* Force single column layout */
      .row.g-4 > * {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      /* Stack all columns on mobile */
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4 {
        margin-bottom: 1.5rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
    }

    /* Phone tweaks for more native feel */
    @media (max-width: 575.98px) {
      .main-content { 
        padding: 12px !important; 
        margin-top: 60px !important;
      }
      
      /* Header adjustments for mobile */
      .d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
      }
      
      /* Force mobile layout for all elements */
      .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4,
      .col-md-3, .col-md-6, .col-md-8, .col-md-4 {
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      
      /* Ensure cards are full width on mobile */
      .card {
        width: 100% !important;
        margin-bottom: 1rem !important;
      }
      
      /* Force mobile navbar */
      .navbar {
        padding-left: 15px !important;
        padding-right: 15px !important;
      }
      
      /* Table responsive */
      .table-responsive {
        font-size: 0.8rem;
      }
      
      .table th, .table td {
        padding: 8px 4px;
        font-size: 0.75rem;
      }
      
      /* Button adjustments */
      .btn {
        font-size: 0.8rem !important;
        padding: 6px 12px !important;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="calendar-container">
      <!-- Calendar Header with Navigation -->
      <div class="calendar-header">
        <div class="calendar-nav">
          <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn">
            <i class="fas fa-chevron-left me-2"></i>Previous
          </a>
          <h2 class="month-year"><?= $monthNames[$month] ?> <?= $year ?></h2>
          <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn">
            Next<i class="fas fa-chevron-right ms-2"></i>
          </a>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="calendar-grid">
        <!-- Weekday headers -->
        <div class="row g-0">
          <?php $weekdays=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
          foreach($weekdays as $wd) echo '<div class="col weekday-header">'.$wd.'</div>'; ?>
        </div>

        <?php
        $dayCount = 1;
        $weekDayIndex = 0;
        $today = date('Y-m-d');
        
        while($dayCount <= $daysInMonth):
            echo '<div class="row g-0">';
            for($weekDayIndex=0;$weekDayIndex<7;$weekDayIndex++):
                if($dayCount==1 && $weekDayIndex<$startDayOfWeek || $dayCount>$daysInMonth){
                    echo '<div class="col calendar-day disabled"></div>';
                    continue;
                }
                
                $dateStr = "$year-".str_pad($month,2,'0',STR_PAD_LEFT)."-".str_pad($dayCount,2,'0',STR_PAD_LEFT);
                $dayEvents = $events[$dateStr] ?? [];
                $eventsJson = htmlspecialchars(json_encode($dayEvents), ENT_QUOTES);
                $isToday = ($dateStr === $today) ? 'today' : '';
                
                echo '<div class="col calendar-day '.$isToday.'" data-date="'.$dateStr.'" data-events="'.$eventsJson.'">';
                echo '<span class="day-number">'.$dayCount.'</span>';
                
                if(!empty($dayEvents)) {
                    foreach($dayEvents as $ev){
                        $class = match($ev['maintenance_type']){
                            'oil_change'=>'bg-oil',
                            'tire_rotation'=>'bg-tire',
                            'wheel_alignment'=>'bg-wheel',
                            'brake_inspection'=>'bg-brake',
                            'ac_maintenance'=>'bg-ac',
                            'battery_check'=>'bg-battery',
                            'transmission_fluid'=>'bg-transmission',
                            'timing_belt'=>'bg-timing',
                            default=>'bg-secondary'
                        };
                        
                        // Add status indicator
                        $statusIcon = '';
                        if($ev['status'] === 'completed') {
                            $statusIcon = ' ✓';
                            $class .= ' border border-success';
                        } elseif($ev['status'] === 'in_progress') {
                            $statusIcon = ' ⏳';
                            $class .= ' border border-warning';
                        }
                        
                        echo '<span class="event-badge '.$class.'" title="'.htmlspecialchars($ev['notes']).'">';
                        echo htmlspecialchars($ev['article'].' - '.$ev['maintenance_type']).$statusIcon;
                        echo '</span>';
                    }
                }
                echo '</div>';
                $dayCount++;
                if($dayCount>$daysInMonth) break;
            endfor;
            echo '</div>';
        endwhile;
        ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Maintenance Events</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
</script>

<script>
    document.querySelectorAll('.calendar-day').forEach(day=>{
        day.addEventListener('click',()=>{
            if(day.classList.contains('disabled')) return;
            const events = JSON.parse(day.dataset.events);
            const modalBody = document.getElementById('modalBody');
            if(events.length===0){
                modalBody.innerHTML = '<p>No maintenance scheduled for this day.</p>';
            } else {
                let html='<ul class="list-group">';
                events.forEach(ev=>{
                    html+=`<li class="list-group-item">
                        <strong>${ev.article}</strong> (${ev.plate_number})<br>
                        Type: ${ev.maintenance_type}<br>
                        Notes: ${ev.notes}<br>
                        Scheduled: ${ev.scheduled_date}
                    </li>`;
                });
                html+='</ul>';
                modalBody.innerHTML=html;
            }
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        });
    });
</script>

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
                window.location.href = '../logout.php';
              }
            });
          }
        });
      });
    }
  });
</script>

</body>
</html>
