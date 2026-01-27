<?php
session_start();
// Check if user is logged in and is a dispatcher
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dispatch Calendar | Smart Track</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
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

        .sidebar.collapsed .link-text,
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

        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
        }

        .dropdown-chevron:hover {
            color: var(--accent);
        }

        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(90deg);
        }

        .dropdown-toggle::after {
            display: none;
        }
        
        /* Main content */
        .main-content {
            margin-left: 250px;
            margin-top: 20px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #dee2e6;
            z-index: 1100;
        }

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
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 0.95rem;
            color: #343a40;
            transition: all 0.3s ease;
            border-radius: 0.35rem;
        }

        .dropdown-menu .dropdown-item:hover {
            background-color: #001d3d;
            color: var(--accent);
            box-shadow: inset 2px 0 0 var(--accent);
        }

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

        /* Card styles */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* Calendar specific styles */
        #calendar {
            background: white;
            padding: 20px;
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .fc {
            /* FullCalendar customization */
        }

        .fc-event {
            cursor: pointer;
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 0.85rem;
        }

        .fc-event:hover {
            opacity: 0.8;
        }

        /* Ensure event text is black for readability */
        .fc .fc-event,
        .fc .fc-daygrid-event a,
        .fc .fc-event .fc-event-title,
        .fc .fc-event .fc-event-time,
        .fc .fc-event-main {
            color: #000 !important;
        }

        /* Status-based colors for calendar events */
        .event-pending {
            background-color: #ffc107 !important;
            border-color: #e0a800 !important;
            color: #000 !important;
        }

        .event-approved {
            background-color: #17a2b8 !important;
            border-color: #138496 !important;
        }

        .event-assigned {
            background-color: #007bff !important;
            border-color: #0056b3 !important;
        }

        .event-completed {
            background-color: #28a745 !important;
            border-color: #1e7e34 !important;
        }

        .event-cancelled {
            background-color: #dc3545 !important;
            border-color: #c82333 !important;
        }

        .legend {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 53, 102, 0.15);
        }

        .page-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Modal styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        /* Ensure modal shows above fixed navbar */
        .modal { z-index: 1202 !important; }
        .modal-backdrop { z-index: 1201 !important; }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 1rem 2rem;
        }

        .info-section-modal {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-section-modal h6 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .info-section-modal p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Mobile responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1101;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                padding: 16px;
            }

            .legend {
                flex-direction: column;
            }

            .filter-section .row > div {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="container-fluid py-0">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-calendar-alt me-2"></i>Dispatch Calendar</h2>
            <p>View and manage all vehicle reservations and dispatches in calendar view</p>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color event-pending"></div>
                <span>Pending</span>
            </div>
            <div class="legend-item">
                <div class="legend-color event-assigned"></div>
                <span>Assigned</span>
            </div>
            <div class="legend-item">
                <div class="legend-color event-completed"></div>
                <span>Completed</span>
            </div>
            <div class="legend-item">
                <div class="legend-color event-cancelled"></div>
                <span>Cancelled</span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-filter me-2"></i>Filter by Status:</label>
                    <select class="form-select" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="assigned">Assigned</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-user me-2"></i>My Assignments Only:</label>
                    <div class="form-check form-switch" style="font-size: 1.2rem; padding-top: 5px;">
                        <input class="form-check-input" type="checkbox" id="myAssignmentsOnly">
                        <label class="form-check-label" for="myAssignmentsOnly">
                            Show only my assignments
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="row">
            <div class="col-12">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Dispatch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventDetails">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <a href="#" id="viewReservationBtn" class="btn btn-primary">
                    <i class="fas fa-external-link-alt me-1"></i>View Full Details
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<script>
    let calendar;
    let allEvents = [];
    const currentUserId = <?php echo $_SESSION['user_id'] ?? 0; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            height: 'auto',
            navLinks: true,
            selectable: true,
            selectMirror: true,
            editable: false,
            dayMaxEvents: true,
            events: function(info, successCallback, failureCallback) {
                fetchEvents(successCallback, failureCallback);
            },
            eventClick: function(info) {
                showEventDetails(info.event);
            },
            eventDidMount: function(info) {
                // Add tooltip
                info.el.title = info.event.extendedProps.description;
            }
        });

        calendar.render();

        // Filter listeners
        document.getElementById('statusFilter').addEventListener('change', function() {
            calendar.refetchEvents();
        });

        document.getElementById('myAssignmentsOnly').addEventListener('change', function() {
            calendar.refetchEvents();
        });
    });

    function fetchEvents(successCallback, failureCallback) {
        const statusFilter = document.getElementById('statusFilter').value;
        const myAssignmentsOnly = document.getElementById('myAssignmentsOnly').checked;

        fetch('get_calendar_events.php?status=' + statusFilter + '&myOnly=' + (myAssignmentsOnly ? '1' : '0'))
            .then(response => response.json())
            .then(data => {
                allEvents = data;
                successCallback(data);
            })
            .catch(error => {
                console.error('Error fetching events:', error);
                failureCallback(error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load calendar events',
                    confirmButtonColor: '#003566'
                });
            });
    }

    function showEventDetails(event) {
        const props = event.extendedProps;
        
        const statusBadges = {
            'pending': '<span class="badge bg-warning text-dark">Pending</span>',
            'assigned': '<span class="badge bg-primary">Assigned</span>',
            'completed': '<span class="badge bg-success">Completed</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>'
        };

        const detailsHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="info-section-modal">
                        <h6><i class="fas fa-info-circle me-2"></i>Reservation Info</h6>
                        <p><strong>ID:</strong> #${props.id}</p>
                        <p><strong>Status:</strong> ${statusBadges[props.status]}</p>
                        <p><strong>Purpose:</strong> ${props.purpose || 'N/A'}</p>
                    </div>
                    <div class="info-section-modal">
                        <h6><i class="fas fa-user me-2"></i>Requester</h6>
                        <p><strong>Name:</strong> ${props.requester_name}</p>
                        <p><strong>Department:</strong> ${props.department || 'N/A'}</p>
                        <p><strong>Contact:</strong> ${props.contact || 'N/A'}</p>
                        <p><strong>Passengers:</strong> ${props.passengers || 'N/A'}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-section-modal">
                        <h6><i class="fas fa-route me-2"></i>Trip Details</h6>
                        <p><strong>Origin:</strong> ${props.origin || 'N/A'}</p>
                        <p><strong>Destination:</strong> ${props.destination || 'N/A'}</p>
                    </div>
                    <div class="info-section-modal">
                        <h6><i class="fas fa-clock me-2"></i>Schedule</h6>
                        <p><strong>Start:</strong> ${event.start.toLocaleString()}</p>
                        <p><strong>End:</strong> ${event.end ? event.end.toLocaleString() : 'N/A'}</p>
                    </div>
                    <div class="info-section-modal">
                        <h6><i class="fas fa-car me-2"></i>Vehicle</h6>
                        <p><strong>Info:</strong> ${props.vehicle_info || 'Not assigned yet'}</p>
                    </div>
                </div>
            </div>
            ${props.notes ? `
                <div class="info-section-modal mt-0">
                    <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                    <p>${props.notes}</p>
                </div>
            ` : ''}
        `;

        document.getElementById('eventDetails').innerHTML = detailsHTML;
        document.getElementById('viewReservationBtn').href = 'assigned_reservations.php?highlight=' + props.id;
        
        const modal = new bootstrap.Modal(document.getElementById('eventModal'));
        modal.show();
    }

    function changeView(viewName) {
        calendar.changeView(viewName);
    }

    // Logout functionality (handled by navbar include)
    // Sidebar toggle is handled by navbar include
</script>
</body>
</html>

