<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information for personalized help (support both reservation users and system users)
$user_id = $_SESSION['user_id'];
$isReservationUser = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'reservation';

if ($isReservationUser) {
    $sql = "SELECT full_name FROM reservation_users WHERE id = ?";
} else {
    $sql = "SELECT full_name, role FROM user_table WHERE user_id = ?";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Help & Support | Smart Track</title>
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
      z-index: 2000;
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }

    .help-card {
      border: 0;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      overflow: hidden;
      margin-bottom: 2rem;
    }

    .help-header {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
      padding: 2rem;
      text-align: center;
    }

    .help-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.9;
    }

    .accordion-button {
      font-weight: 600;
      color: var(--primary);
    }

    .accordion-button:not(.collapsed) {
      background-color: rgba(0, 53, 102, 0.1);
      color: var(--primary);
    }

    .feature-card {
      border-left: 4px solid var(--accent);
      transition: transform 0.2s ease;
    }

    .feature-card:hover {
      transform: translateY(-2px);
    }

    .contact-card {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      border: 1px solid #dee2e6;
    }

    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .btn-primary:hover {
      background-color: #001d3d;
      border-color: #001d3d;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 250px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
      
      .navbar {
        z-index: 2000;
      }
      
      .burger-btn {
        display: block;
      }
      
      /* Mobile layout adjustments */
      .col-md-8,
      .col-md-4 {
        margin-bottom: 20px;
      }
      
      .help-header {
        padding: 1.5rem;
      }
      
      .help-icon {
        font-size: 3rem;
        margin-bottom: 0.8rem;
      }
      
      h3 {
        font-size: 1.5rem;
      }
      
      /* Mobile accordion */
      .accordion-button {
        padding: 12px 16px;
        font-size: 14px;
      }
      
      .accordion-body {
        padding: 16px;
      }
      
      /* Mobile feature cards */
      .feature-card {
        padding: 12px;
        margin-bottom: 12px;
      }
      
      .feature-card h6 {
        font-size: 14px;
        margin-bottom: 6px;
      }
      
      .feature-card p {
        font-size: 12px;
      }
      
      /* Mobile contact card */
      .contact-card .card-body {
        padding: 16px;
      }
      
      .contact-card .mb-3 {
        margin-bottom: 12px;
      }
      
      /* Mobile quick actions */
      .d-grid .btn {
        padding: 10px 16px;
        font-size: 13px;
      }
    }

    @media (max-width: 575.98px) {
      .main-content {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.5rem;
      }
      
      .help-header {
        padding: 1rem;
      }
      
      .help-icon {
        font-size: 2.5rem;
        margin-bottom: 0.6rem;
      }
      
      h3 {
        font-size: 1.25rem;
      }
      
      p {
        font-size: 14px;
      }
      
      /* Mobile accordion */
      .accordion-button {
        padding: 10px 14px;
        font-size: 13px;
      }
      
      .accordion-button i {
        font-size: 12px;
      }
      
      .accordion-body {
        padding: 14px;
      }
      
      .accordion-body ol,
      .accordion-body ul {
        padding-left: 20px;
      }
      
      .accordion-body li {
        margin-bottom: 8px;
        font-size: 13px;
      }
      
      /* Mobile feature cards */
      .feature-card {
        padding: 10px;
        margin-bottom: 10px;
      }
      
      .feature-card h6 {
        font-size: 13px;
        margin-bottom: 4px;
      }
      
      .feature-card p {
        font-size: 11px;
      }
      
      /* Mobile contact info */
      .contact-card .mb-3 {
        margin-bottom: 10px;
      }
      
      .contact-card strong {
        font-size: 13px;
      }
      
      .contact-card small {
        font-size: 11px;
      }
      
      /* Mobile buttons */
      .d-grid .btn {
        padding: 8px 14px;
        font-size: 12px;
      }
      
      /* Mobile FAQ */
      .col-md-6 {
        margin-bottom: 20px;
      }
      
      .col-md-6 h6 {
        font-size: 13px;
        margin-bottom: 6px;
      }
      
      .col-md-6 p {
        font-size: 12px;
        margin-bottom: 12px;
      }
    }

    @media (max-width: 375px) {
      .help-header {
        padding: 0.8rem;
      }
      
      .help-icon {
        font-size: 2rem;
      }
      
      h3 {
        font-size: 1.1rem;
      }
      
      /* Very small screen accordion */
      .accordion-button {
        padding: 8px 12px;
        font-size: 12px;
      }
      
      .accordion-body {
        padding: 12px;
      }
      
      .accordion-body li {
        font-size: 12px;
        margin-bottom: 6px;
      }
      
      /* Very small screen cards */
      .feature-card {
        padding: 8px;
      }
      
      .feature-card h6 {
        font-size: 12px;
      }
      
      .feature-card p {
        font-size: 10px;
      }
      
      .contact-card .card-body {
        padding: 12px;
      }
      
      .contact-card strong {
        font-size: 12px;
      }
      
      .d-grid .btn {
        padding: 6px 12px;
        font-size: 11px;
      }
      
      .col-md-6 h6 {
        font-size: 12px;
      }
      
      .col-md-6 p {
        font-size: 11px;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/user_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/user_navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0"><i class="fas fa-question-circle"></i> Help & Support</h2>
    </div>

    <!-- Welcome Message -->
    <div class="card help-card">
      <div class="help-header">
        <div class="help-icon">
          <i class="fas fa-hands-helping"></i>
        </div>
        <h3 class="mb-2">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h3>
        <p class="mb-0 opacity-75">How can we help you today?</p>
      </div>
    </div>

    <div class="row">
      <!-- Quick Help -->
      <div class="col-md-8">
        <div class="card help-card">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
              <i class="fas fa-rocket me-2"></i>Quick Help Guide
            </h5>
          </div>
          <div class="card-body">
            <div class="accordion" id="helpAccordion">
              <!-- Making Reservations -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    <i class="fas fa-plus-circle me-2 text-success"></i>
                    How to Make a Vehicle Reservation
                  </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                  <div class="accordion-body">
                    <ol>
                      <li><strong>Click "New Reservation"</strong> from the sidebar or dashboard</li>
                      <li><strong>Fill out the form:</strong>
                        <ul>
                          <li>Select your purpose (business, personal, emergency)</li>
                          <li>Choose origin and destination</li>
                          <li>Set start and end date/time</li>
                          <li>Specify number of passengers</li>
                          <li>Add any special notes or requirements</li>
                        </ul>
                      </li>
                      <li><strong>Submit your request</strong> and wait for approval</li>
                      <li><strong>Check your reservations</strong> in "My Reservations" to track status</li>
                    </ol>
                    <div class="alert alert-info mt-3">
                      <i class="fas fa-lightbulb me-2"></i>
                      <strong>Tip:</strong> Submit reservations at least 24 hours in advance for better availability.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Checking Status -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                    <i class="fas fa-search me-2 text-info"></i>
                    How to Check Reservation Status
                  </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                  <div class="accordion-body">
                    <p>You can check your reservation status in several ways:</p>
                    <ul>
                      <li><strong>Dashboard:</strong> View quick stats and recent reservations</li>
                      <li><strong>My Reservations:</strong> See detailed list with status badges</li>
                      <li><strong>Status Meanings:</strong>
                        <ul>
                          <li><span class="badge bg-warning">Pending</span> - Waiting for approval</li>
                          <li><span class="badge bg-success">Approved</span> - Request approved, awaiting assignment</li>
                          <li><span class="badge bg-info">Assigned</span> - Vehicle and dispatcher assigned</li>
                          <li><span class="badge bg-primary">Completed</span> - Trip finished</li>
                          <li><span class="badge bg-danger">Cancelled</span> - Request cancelled</li>
                        </ul>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Cancelling Reservations -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                    <i class="fas fa-times-circle me-2 text-danger"></i>
                    How to Cancel a Reservation
                  </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                  <div class="accordion-body">
                    <ol>
                      <li><strong>Go to "My Reservations"</strong> from the sidebar</li>
                      <li><strong>Find your reservation</strong> in the table</li>
                      <li><strong>Click the red "Cancel" button</strong> (only available for pending reservations)</li>
                      <li><strong>Confirm cancellation</strong> in the popup dialog</li>
                    </ol>
                    <div class="alert alert-warning mt-3">
                      <i class="fas fa-exclamation-triangle me-2"></i>
                      <strong>Note:</strong> You can only cancel reservations that are still pending. Contact support for approved reservations.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Contacting Support -->
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                    <i class="fas fa-headset me-2 text-primary"></i>
                    Getting Additional Help
                  </button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                  <div class="accordion-body">
                    <p>If you need additional assistance:</p>
                    <ul>
                      <li><strong>IT Support:</strong> Contact your department's IT administrator</li>
                      <li><strong>Fleet Management:</strong> Reach out to the motor pool office</li>
                      <li><strong>Emergency:</strong> Call the emergency hotline for urgent vehicle needs</li>
                      <li><strong>Technical Issues:</strong> Report system problems to the system administrator</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar Help -->
      <div class="col-md-4">
        <!-- System Features -->
        <div class="card help-card">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">
              <i class="fas fa-star me-2"></i>System Features
            </h5>
          </div>
          <div class="card-body">
            <div class="feature-card p-3 mb-3">
              <h6 class="text-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
              </h6>
              <p class="mb-0 small">Quick overview of your reservations and status</p>
            </div>
            
            <div class="feature-card p-3 mb-3">
              <h6 class="text-success">
                <i class="fas fa-plus-circle me-2"></i>New Reservation
              </h6>
              <p class="mb-0 small">Submit new vehicle reservation requests</p>
            </div>
            
            <div class="feature-card p-3 mb-3">
              <h6 class="text-info">
                <i class="fas fa-calendar-check me-2"></i>My Reservations
              </h6>
              <p class="mb-0 small">View and manage all your reservations</p>
            </div>
            
            <div class="feature-card p-3 mb-3">
              <h6 class="text-warning">
                <i class="fas fa-user me-2"></i>Profile
              </h6>
              <p class="mb-0 small">Update your personal information</p>
            </div>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="card contact-card">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0">
              <i class="fas fa-phone me-2"></i>Contact Support
            </h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong><i class="fas fa-phone me-2"></i>Emergency Hotline:</strong><br>
              <a href="tel:+1234567890" class="text-decoration-none">(123) 456-7890</a>
            </div>
            
            <div class="mb-3">
              <strong><i class="fas fa-envelope me-2"></i>Email Support:</strong><br>
              <a href="mailto:support@smarttrack.com" class="text-decoration-none">support@smarttrack.com</a>
            </div>
            
            <div class="mb-3">
              <strong><i class="fas fa-clock me-2"></i>Support Hours:</strong><br>
              <small class="text-muted">Monday - Friday: 8:00 AM - 5:00 PM</small>
            </div>
            
            <div class="d-grid">
              <button class="btn btn-primary" onclick="window.location.href='mailto:support@smarttrack.com'">
                <i class="fas fa-envelope me-2"></i>Send Email
              </button>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card help-card mt-3">
          <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
              <i class="fas fa-bolt me-2"></i>Quick Actions
            </h5>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="vehicle_reservation.php" class="btn btn-outline-success">
                <i class="fas fa-plus-circle me-2"></i>Make New Reservation
              </a>
              <a href="my_reservations.php" class="btn btn-outline-info">
                <i class="fas fa-calendar-check me-2"></i>View My Reservations
              </a>
              <a href="user_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card help-card">
          <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">
              <i class="fas fa-question-circle me-2"></i>Frequently Asked Questions
            </h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <h6 class="text-primary">Q: How far in advance can I make a reservation?</h6>
                <p class="small text-muted">A: You can make reservations up to 30 days in advance. For urgent requests, contact support directly.</p>
                
                <h6 class="text-primary">Q: Can I modify my reservation after submission?</h6>
                <p class="small text-muted">A: You can only cancel pending reservations. For approved reservations, contact the assigned dispatcher.</p>
                
                <h6 class="text-primary">Q: What if I need a vehicle urgently?</h6>
                <p class="small text-muted">A: Use the emergency hotline or contact the motor pool office directly for urgent vehicle needs.</p>
              </div>
              <div class="col-md-6">
                <h6 class="text-primary">Q: How will I know when my reservation is approved?</h6>
                <p class="small text-muted">A: Check your dashboard regularly or visit "My Reservations" to see status updates in real-time.</p>
                
                <h6 class="text-primary">Q: What should I do if I can't access the system?</h6>
                <p class="small text-muted">A: Contact your IT administrator or try clearing your browser cache and cookies.</p>
                
                <h6 class="text-primary">Q: Can I request specific vehicles?</h6>
                <p class="small text-muted">A: Vehicle assignment is handled by dispatchers based on availability and requirements. You can add special requests in the notes section.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
    // Check if we're on mobile
    const isMobile = window.innerWidth <= 991.98;
    
    if (isMobile) {
      // Mobile behavior - toggle sidebar visibility
      sidebar.classList.toggle('show');
    } else {
      // Desktop behavior - toggle sidebar collapse
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

      // Collapse all sidebar dropdowns when sidebar is collapsed
      if (isCollapsed) {
        const openMenus = sidebar.querySelectorAll('.collapse.show');
        openMenus.forEach(menu => {
          const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
          collapseInstance.hide();
        });
      }
    }
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    const isMobile = window.innerWidth <= 991.98;
    if (isMobile && sidebar.classList.contains('show')) {
      if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    }
  });

  // Handle window resize
  window.addEventListener('resize', () => {
    const isMobile = window.innerWidth <= 991.98;
    if (!isMobile) {
      // Reset mobile sidebar state when switching to desktop
      sidebar.classList.remove('show');
    }
  });
</script>

</body>
</html>
