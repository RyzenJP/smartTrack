<?php
session_start();

// Check if user is logged in and redirect to appropriate help page
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to user help page
    header('Location: user/help.php');
    exit();
} else {
    // User is not logged in, show general help page
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .help-container {
            min-height: 100vh;
            padding-top: 80px;
        }

        .help-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 60px 0;
            text-align: center;
        }

        .help-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .help-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .help-section {
            padding: 40px 0;
        }

        .feature-card {
            border-left: 4px solid var(--accent);
            transition: transform 0.2s ease;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .contact-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 1px solid #dee2e6;
            border-radius: 16px;
            padding: 30px;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: #001d3d;
            border-color: #001d3d;
            transform: translateY(-1px);
        }

        .login-prompt {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }

        .login-prompt h4 {
            color: #856404;
            margin-bottom: 15px;
        }

        .login-prompt p {
            color: #856404;
            margin-bottom: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .help-header {
                padding: 40px 0;
            }
            
            .help-icon {
                font-size: 3rem;
            }
            
            .help-section {
                padding: 30px 0;
            }
            
            .feature-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .contact-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-car me-2"></i>Smart Track
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Help Header -->
    <div class="help-header">
        <div class="container">
            <div class="help-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <h1 class="display-4 mb-3">Help & Support</h1>
            <p class="lead mb-0">Get assistance with Smart Track Vehicle Management System</p>
        </div>
    </div>

    <div class="help-container">
        <div class="container">
            <!-- Login Prompt -->
            <div class="login-prompt">
                <h4><i class="fas fa-info-circle me-2"></i>Personalized Help Available</h4>
                <p>For personalized help and to access your account-specific features, please log in to your account.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                    </a>
                    <a href="register.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus me-2"></i>Create New Account
                    </a>
                </div>
            </div>

            <!-- General Help Sections -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="help-section">
                        <h2 class="mb-4"><i class="fas fa-rocket me-2"></i>Getting Started</h2>
                        
                        <div class="feature-card">
                            <h5 class="text-primary">
                                <i class="fas fa-user-plus me-2"></i>Creating an Account
                            </h5>
                            <p class="mb-0">Register for a new account to access vehicle reservation and tracking features. Choose the appropriate user type based on your role.</p>
                        </div>

                        <div class="feature-card">
                            <h5 class="text-success">
                                <i class="fas fa-car me-2"></i>Vehicle Reservations
                            </h5>
                            <p class="mb-0">Request vehicle reservations for official business, personal use, or emergency situations. Track your reservation status in real-time.</p>
                        </div>

                        <div class="feature-card">
                            <h5 class="text-info">
                                <i class="fas fa-map-marker-alt me-2"></i>Live Tracking
                            </h5>
                            <p class="mb-0">Monitor vehicle locations in real-time with GPS tracking. View routes, speeds, and location history.</p>
                        </div>

                        <div class="feature-card">
                            <h5 class="text-warning">
                                <i class="fas fa-tools me-2"></i>Maintenance Management
                            </h5>
                            <p class="mb-0">Schedule and track vehicle maintenance. Receive alerts for upcoming maintenance and emergency repairs.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Contact Information -->
                    <div class="contact-card">
                        <h5 class="mb-3">
                            <i class="fas fa-headset me-2"></i>Contact Support
                        </h5>
                        
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

                    <!-- Quick Links -->
                    <div class="help-card mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-link me-2"></i>Quick Links
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="fas fa-home me-2"></i>Home Page
                                </a>
                                <a href="login.php" class="btn btn-outline-success">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </a>
                                <a href="register.php" class="btn btn-outline-info">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="help-card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-question-circle me-2"></i>Frequently Asked Questions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Q: How do I create an account?</h6>
                                    <p class="small text-muted">A: Click the "Register" button in the top navigation and fill out the registration form with your details.</p>
                                    
                                    <h6 class="text-primary">Q: What user types are available?</h6>
                                    <p class="small text-muted">A: We support Super Admin, Motor Pool Admin, Dispatcher, Driver, Mechanic, and Regular User roles.</p>
                                    
                                    <h6 class="text-primary">Q: How do I request a vehicle?</h6>
                                    <p class="small text-muted">A: After logging in, navigate to "New Reservation" and fill out the vehicle request form.</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Q: Can I track my reservation status?</h6>
                                    <p class="small text-muted">A: Yes, you can view your reservation status in the "My Reservations" section after logging in.</p>
                                    
                                    <h6 class="text-primary">Q: What if I forget my password?</h6>
                                    <p class="small text-muted">A: Use the "Forgot Password" link on the login page to reset your password via SMS.</p>
                                    
                                    <h6 class="text-primary">Q: Is the system mobile-friendly?</h6>
                                    <p class="small text-muted">A: Yes, Smart Track is fully responsive and works great on mobile devices and tablets.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


