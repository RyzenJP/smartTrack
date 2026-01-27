<?php
// user_sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <!-- Dashboard -->
    <a href="user_dashboard.php" class="<?= $currentPage == 'user_dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span class="link-text">Dashboard</span>
    </a>

    <!-- New Reservation -->
    <a href="vehicle_reservation.php" class="<?= $currentPage == 'vehicle_reservation.php' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i>
        <span class="link-text">New Reservation</span>
    </a>

    <!-- My Reservations -->
    <a href="my_reservations.php" class="<?= $currentPage == 'my_reservations.php' ? 'active' : '' ?>">
        <i class="fas fa-calendar-check"></i>
        <span class="link-text">My Reservations</span>
    </a>


    <!-- Help -->
    <a href="help.php" class="<?= $currentPage == 'help.php' ? 'active' : '' ?>">
        <i class="fas fa-question-circle"></i>
        <span class="link-text">Help</span>
    </a>

    <!-- Logout -->
    <a href="../logout.php" class="logout-link">
        <i class="fas fa-sign-out-alt"></i>
        <span class="link-text">Logout</span>
    </a>
</div>
