<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="px-3 py-4">
        <div class="d-flex flex-column">
            <!-- Dashboard -->
            <a href="dispatcher-dashboard.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'dispatcher-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-3"></i>
                <span class="link-text">Dashboard</span>
            </a>

            <!-- Live Tracking -->
            <a href="live-tracking.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'live-tracking.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt me-3"></i>
                <span class="link-text">Live Tracking</span>
            </a>

            <!-- Fleet Overview -->
            <a href="fleet-overview.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'fleet-overview.php' ? 'active' : '' ?>">
                <i class="fas fa-truck me-3"></i>
                <span class="link-text">Fleet Overview</span>
            </a>

            <!-- Driver Status -->
            <a href="driver-status.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'driver-status.php' ? 'active' : '' ?>">
                <i class="fas fa-user-tie me-3"></i>
                <span class="link-text">Driver Status</span>
            </a>

            <!-- Communication Center -->
            <a href="communication-center.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'communication-center.php' ? 'active' : '' ?>">
                <i class="fas fa-comments me-3"></i>
                <span class="link-text">Communication</span>
            </a>

            <!-- Schedule Trips -->
            <a href="schedule-trips.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'schedule-trips.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-3"></i>
                <span class="link-text">Schedule Trips</span>
            </a>

            <!-- Trip History -->
            <a href="trip-history.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'trip-history.php' ? 'active' : '' ?>">
                <i class="fas fa-history me-3"></i>
                <span class="link-text">Trip History</span>
            </a>

            <!-- Active Routes -->
            <a href="active-routes.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'active-routes.php' ? 'active' : '' ?>">
                <i class="fas fa-route me-3"></i>
                <span class="link-text">Active Routes</span>
            </a>

            <!-- Incident Reports -->
            <a href="incident-reports.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'incident-reports.php' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle me-3"></i>
                <span class="link-text">Incident Reports</span>
            </a>

            <!-- Assign Vehicles -->
            <a href="assign-vehicles.php" class="d-flex align-items-center mb-3 <?= $currentPage == 'assign-vehicles.php' ? 'active' : '' ?>">
                <i class="fas fa-tasks me-3"></i>
                <span class="link-text">Assign Vehicles</span>
            </a>
        </div>
    </div>
</div>