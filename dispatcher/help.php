<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}

$currentPage = 'help.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Guide | Dispatcher - Smart Track</title>
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

        .main-content {
            margin-left: 250px;
            margin-top: 10px;
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

        /* Help Page Specific Styles */
        .help-header {
            background: linear-gradient(135deg, var(--primary) 0%, #001d3d 100%);
            color: white;
            padding: 3rem 0;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 53, 102, 0.15);
        }

        .help-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .help-card .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #002855 100%);
            color: white;
            font-weight: 600;
            padding: 1.25rem;
            border: none;
        }

        .help-card .card-header i {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }

        .help-card .card-body {
            padding: 1.5rem;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent) 0%, #0096c7 100%);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .feature-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 500;
            margin: 0.25rem;
        }

        .info-alert {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid var(--accent);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .warning-alert {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border-left: 4px solid #ffc107;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .help-list {
            list-style: none;
            padding-left: 0;
        }

        .help-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: start;
        }

        .help-list li:last-child {
            border-bottom: none;
        }

        .help-list li i {
            color: var(--accent);
            margin-right: 0.75rem;
            margin-top: 0.25rem;
        }

        .contact-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="help-header text-center">
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-question-circle me-3"></i>Dispatcher Help Guide
            </h1>
            <p class="lead mb-0">Complete guide to managing vehicle reservations, routes, and fleet operations</p>
        </div>

        <!-- Dashboard Overview -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                    </div>
                    <div class="card-body">
                        <p class="mb-3">The Dispatcher Dashboard provides a comprehensive overview of all fleet operations and reservations assigned to you.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Key Metrics:</h6>
                        <ul class="help-list">
                            <li>
                                <i class="fas fa-clipboard-list"></i>
                                <div>
                                    <strong>Total Reservations:</strong> Shows all vehicle reservations assigned to you
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <div>
                                    <strong>Pending Assignments:</strong> Reservations waiting for vehicle and driver assignment
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-play-circle"></i>
                                <div>
                                    <strong>Active Trips:</strong> Currently ongoing trips that you're monitoring
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong>Completed:</strong> Successfully finished trips
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Managing Reservations -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i>Managing Assigned Reservations
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Learn how to efficiently manage vehicle reservations from assignment to completion.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Step-by-Step Process:</h6>
                        
                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">1</span><strong>View Assigned Reservations</strong></p>
                            <p class="ms-5 text-muted">Navigate to "Assigned Reservations" to see all reservations assigned to you by the admin.</p>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">2</span><strong>Assign Vehicle & Driver</strong></p>
                            <p class="ms-5 text-muted">For each reservation with "assigned" status:</p>
                            <ul class="ms-5">
                                <li>Click the <strong>"Assign Vehicle & Driver"</strong> button</li>
                                <li>Select from active vehicle-driver pairs (1:1 ratio)</li>
                                <li>The system shows only available assignments</li>
                                <li>Click "Assign" to confirm</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">3</span><strong>Change Assignment (if needed)</strong></p>
                            <p class="ms-5 text-muted">If you need to reassign a different vehicle-driver pair:</p>
                            <ul class="ms-5">
                                <li>Click the <strong>"Change Assignment"</strong> button</li>
                                <li>Select a new vehicle-driver pair</li>
                                <li>Previous assignment will be updated</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">4</span><strong>Start the Trip</strong></p>
                            <p class="ms-5 text-muted">When the driver is ready to begin:</p>
                            <ul class="ms-5">
                                <li>Click the <strong>"Start"</strong> button</li>
                                <li>You'll be redirected to the Route Planning page</li>
                                <li>The system auto-fills the destination from the reservation</li>
                                <li>Review and assign the route to the driver</li>
                            </ul>
                        </div>

                        <div class="info-alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> You can only assign vehicles and drivers from active vehicle-driver pairs created in the "Assign Vehicles" page.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Route Planning -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-route"></i>Intelligent Route Planning
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Create and manage optimal routes for your fleet vehicles.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">How to Plan Routes:</h6>
                        
                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">1</span><strong>Set Start Point (Point A)</strong></p>
                            <ul class="ms-5">
                                <li><strong>Automatic:</strong> System fetches GPS location from ESP32 device</li>
                                <li><strong>Manual:</strong> Click on the map if GPS is unavailable</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">2</span><strong>Set Destination (Point B)</strong></p>
                            <ul class="ms-5">
                                <li><strong>Search by Address:</strong> Type any Philippine address and click search</li>
                                <li><strong>Click on Map:</strong> Click directly on the map to set destination</li>
                                <li><strong>Auto-filled:</strong> When starting from a reservation, destination is auto-filled</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">3</span><strong>Review Route Alternatives</strong></p>
                            <p class="ms-5 text-muted">The system calculates multiple route options:</p>
                            <ul class="ms-5">
                                <li><strong>Best Route:</strong> Fastest and most efficient path</li>
                                <li><strong>Alternative Route:</strong> Different path avoiding main roads</li>
                                <li>Compare distance and duration for each route</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">4</span><strong>Assign the Route</strong></p>
                            <ul class="ms-5">
                                <li>Click "Assign Route" button</li>
                                <li>Select driver and vehicle</li>
                                <li>Give the route a name</li>
                                <li>Click "Save Route"</li>
                            </ul>
                        </div>

                        <div class="warning-alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Address Search Issues?</strong> If address search fails (especially in restricted networks like schools):
                            <ul class="mb-0 mt-2">
                                <li>Click directly on the map to set your destination</li>
                                <li>Try a more specific address (e.g., "Bago City Hall, Bago City")</li>
                                <li>Check the browser console (F12) for detailed error information</li>
                            </ul>
                        </div>

                        <h6 class="fw-bold mt-4 mb-3">Map Features:</h6>
                        <ul class="help-list">
                            <li>
                                <i class="fas fa-layer-group"></i>
                                <div>
                                    <strong>Map Layers:</strong> Switch between Street, Satellite, and Hybrid views using the layer control
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-search-plus"></i>
                                <div>
                                    <strong>Zoom Controls:</strong> Use + and - buttons or mouse wheel to zoom in/out
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-hand-pointer"></i>
                                <div>
                                    <strong>Click to Set Points:</strong> Click anywhere on the map to set start or destination points
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Assignments -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-car-side"></i>Vehicle & Driver Assignments
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Manage vehicle-driver pairings for efficient fleet operations.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Creating Assignments:</h6>
                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">1</span><strong>Navigate to "Assign Vehicles"</strong></p>
                            <p class="ms-5 text-muted">Go to the Assign Vehicles page from the sidebar</p>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">2</span><strong>Create New Assignment</strong></p>
                            <ul class="ms-5">
                                <li>Click "Assign Vehicle to Driver" button</li>
                                <li>Select a driver from the dropdown</li>
                                <li>Select a vehicle from available fleet</li>
                                <li>Click "Assign" to create the pairing</li>
                            </ul>
                        </div>

                        <div class="mb-4">
                            <p class="mb-2"><span class="step-number">3</span><strong>Manage Existing Assignments</strong></p>
                            <ul class="ms-5">
                                <li><strong>View:</strong> See all current vehicle-driver pairs</li>
                                <li><strong>Unassign:</strong> Remove a pairing when no longer needed</li>
                                <li><strong>Status:</strong> Monitor active vs inactive assignments</li>
                            </ul>
                        </div>

                        <div class="info-alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Important:</strong> Only active vehicle-driver pairs will appear in the reservation assignment dropdown. Make sure to create assignments before assigning reservations.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monitoring & Alerts -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>Monitoring & Alerts
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Stay informed about fleet activities and potential issues.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Alert Types:</h6>
                        <ul class="help-list">
                            <li>
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                <div>
                                    <strong>Route Deviation Alerts:</strong> Notifies you when a driver deviates from the assigned route during an active trip
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-car text-danger"></i>
                                <div>
                                    <strong>Unauthorized Movement:</strong> Alerts when a vehicle moves after trip completion without authorization
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-clock text-info"></i>
                                <div>
                                    <strong>Trip Status Updates:</strong> Real-time notifications about trip start, completion, and delays
                                </div>
                            </li>
                        </ul>

                        <h6 class="fw-bold mt-4 mb-3">Managing Alerts:</h6>
                        <div class="mb-3">
                            <p class="mb-2"><span class="step-number">1</span><strong>View Alerts</strong></p>
                            <p class="ms-5 text-muted">Navigate to "Alerts" page to see all system notifications</p>
                        </div>

                        <div class="mb-3">
                            <p class="mb-2"><span class="step-number">2</span><strong>Review Alert Details</strong></p>
                            <p class="ms-5 text-muted">Each alert shows priority level, timestamp, and detailed information</p>
                        </div>

                        <div class="mb-3">
                            <p class="mb-2"><span class="step-number">3</span><strong>Resolve Alerts</strong></p>
                            <p class="ms-5 text-muted">Click "Resolve" button after addressing the issue</p>
                        </div>

                        <div class="warning-alert">
                            <i class="fas fa-mobile-alt me-2"></i>
                            <strong>SMS Notifications:</strong> High-priority alerts are also sent to your registered phone number via SMS for immediate attention.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Driver Status -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-users"></i>Driver Status Monitoring
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Track driver availability and current assignments.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Status Indicators:</h6>
                        <ul class="help-list">
                            <li>
                                <i class="fas fa-circle text-success"></i>
                                <div>
                                    <strong>Available:</strong> Driver is ready for assignment but not currently on a trip
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-circle text-warning"></i>
                                <div>
                                    <strong>On Duty:</strong> Driver is currently on an active trip
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-circle text-primary"></i>
                                <div>
                                    <strong>Active:</strong> Driver has an active vehicle assignment
                                </div>
                            </li>
                        </ul>

                        <div class="info-alert mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tip:</strong> Use the Driver Status page to quickly identify available drivers before assigning new reservations.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i>Dispatcher Calendar
                    </div>
                    <div class="card-body">
                        <p class="mb-3">View and manage all scheduled reservations in a calendar format.</p>
                        
                        <h6 class="fw-bold mt-4 mb-3">Calendar Features:</h6>
                        <ul class="help-list">
                            <li>
                                <i class="fas fa-eye"></i>
                                <div>
                                    <strong>View Reservations:</strong> See all reservations organized by date
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Event Details:</strong> Click on any reservation to view full details
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-filter"></i>
                                <div>
                                    <strong>Color Coding:</strong> Different colors represent different reservation statuses
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-calendar-day"></i>
                                <div>
                                    <strong>Multiple Views:</strong> Switch between month, week, and day views
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Features -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-cogs"></i>Key System Features
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-satellite-dish me-1"></i>Real-time GPS Tracking</span>
                                <p class="text-muted small mt-2">Track vehicle locations in real-time using ESP32 GPS devices</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-route me-1"></i>Intelligent Routing</span>
                                <p class="text-muted small mt-2">AI-powered route optimization with multiple alternatives</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-bell me-1"></i>Smart Alerts</span>
                                <p class="text-muted small mt-2">Automated notifications for deviations and issues</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-chart-line me-1"></i>Analytics Dashboard</span>
                                <p class="text-muted small mt-2">Comprehensive metrics and performance insights</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-mobile-alt me-1"></i>SMS Integration</span>
                                <p class="text-muted small mt-2">Instant SMS notifications for critical alerts</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="feature-badge"><i class="fas fa-map-marked-alt me-1"></i>Interactive Maps</span>
                                <p class="text-muted small mt-2">Multiple map layers with street, satellite, and hybrid views</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Frequently Asked Questions -->
        <div class="row">
            <div class="col-12">
                <div class="help-card">
                    <div class="card-header">
                        <i class="fas fa-question-circle"></i>Frequently Asked Questions
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: What should I do if address search fails?</h6>
                            <p class="text-muted mb-0">A: If address search fails (common in restricted networks), simply click directly on the map to set your destination point. You can also check the browser console (F12) for detailed error information.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: How do I know which drivers are available?</h6>
                            <p class="text-muted mb-0">A: Navigate to the "Driver Status" page to see real-time availability of all drivers. Available drivers are shown in green, while those on duty are shown in yellow.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: Can I change a vehicle-driver assignment after starting a trip?</h6>
                            <p class="text-muted mb-0">A: No, once a trip is started (status changes to "active"), you cannot change the vehicle or driver assignment. You can only change assignments for reservations with "assigned" status.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: What happens if a driver deviates from the route?</h6>
                            <p class="text-muted mb-0">A: The system automatically detects route deviations and sends you an alert via SMS and in-system notification. You can then contact the driver to address the issue.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: How do I create vehicle-driver pairs?</h6>
                            <p class="text-muted mb-0">A: Go to "Assign Vehicles" page, click "Assign Vehicle to Driver", select a driver and vehicle, then click "Assign". Only active pairs will appear in reservation assignments.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: Why can't I see any vehicle-driver pairs when assigning a reservation?</h6>
                            <p class="text-muted mb-0">A: This means you haven't created any active vehicle-driver assignments yet. Go to "Assign Vehicles" page first to create assignments before assigning reservations.</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Q: What does "No GPS data" mean?</h6>
                            <p class="text-muted mb-0">A: This means the ESP32 GPS device hasn't acquired a satellite signal yet. The device needs a clear view of the sky to get GPS coordinates. You can manually set the start point by clicking on the map.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="row">
            <div class="col-12">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Need Additional Help?</h4>
                    <p class="text-muted mb-4">If you have questions not covered in this guide, please contact the system administrator or IT support team.</p>
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <span class="fw-bold">support@smarttrack.gov</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <span class="fw-bold">(034) 123-4567</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
    const isCollapsed = sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
    
    linkTexts.forEach(text => {
      text.style.display = isCollapsed ? 'none' : 'inline';
    });
    
    dropdownToggles.forEach(toggle => {
      const chevron = toggle.querySelector('.dropdown-chevron');
      if (isCollapsed) {
        chevron.classList.add('disabled-chevron');
        chevron.style.cursor = 'not-allowed';
        chevron.setAttribute('title', 'Expand sidebar to activate');
        toggle.setAttribute('data-bs-toggle', '');
      } else {
        chevron.classList.remove('disabled-chevron');
        chevron.style.cursor = 'pointer';
        chevron.removeAttribute('title');
        toggle.setAttribute('data-bs-toggle', 'collapse');
      }
    });
    
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  });

  // Logout confirmation
  document.addEventListener("DOMContentLoaded", function() {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function(e) {
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
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn',
            cancelButton: 'swal-btn'
          }
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = '../logout.php';
          }
        });
      });
    }
  });
</script>
</body>
</html>


