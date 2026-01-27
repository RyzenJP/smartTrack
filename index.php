<?php
session_start();
// Include security headers
require_once __DIR__ . '/includes/security_headers.php';

$loginError = '';
$passwordResetSuccess = '';

if (isset($_SESSION['login_error'])) {
  $loginError = $_SESSION['login_error'];
  unset($_SESSION['login_error']); // Clear error after showing
}

if (isset($_SESSION['password_reset_success'])) {
  $passwordResetSuccess = $_SESSION['password_reset_success'];
  unset($_SESSION['password_reset_success']); // Clear success message after showing
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bago City | Vehicle Tracking System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <!-- Bootstrap Icons CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Include FontAwesome for icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  
  <style>
    :root {
      --primary-blue: #003566;
      --accent-blue: #00b4d8;
      --light-blue: #0096c7;
      --dark-blue: #001d3d;
      --text-dark: #2d3748;
      --text-light: #718096;
      --bg-light: #f7fafc;
      --white: #ffffff;
      --success: #48bb78;
      --warning: #ed8936;
      --danger: #f56565;
      --border-light: #e2e8f0;
      --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.6;
      overflow-x: hidden;
      width: 100%;
      max-width: 100%;
    }

    img, video { max-width: 100%; height: auto; }

    /* Prevent horizontal scroll on nested grids */
    .container, .container-fluid, .section, .hero { overflow-x: hidden; }
    .row { margin-right: 0; margin-left: 0; }

    /* Alert Styling */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .alert-success {
      background-color: rgba(72, 187, 120, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
    }

    .alert-danger {
      background-color: rgba(245, 101, 101, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }

    /* Enhanced Navbar */
    .navbar {
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 1rem 0;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-lg);
    }

    .navbar.scrolled {
      padding: 0.5rem 0;
      background: rgba(0, 53, 102, 0.95);
    }

    .navbar .nav-link {
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
      padding: 0.75rem 1.25rem;
      border-radius: 0.5rem;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .navbar .nav-link::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
      transition: left 0.5s ease;
    }

    .navbar .nav-link:hover::before {
      left: 100%;
    }

    .navbar .nav-link:hover {
      color: var(--accent-blue);
      background-color: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }

    .navbar .nav-link.active {
      color: var(--accent-blue);
      background-color: rgba(0, 180, 216, 0.15);
      border: 1px solid rgba(0, 180, 216, 0.3);
      box-shadow: 0 0 20px rgba(0, 180, 216, 0.3);
    }
    
    /* Keep navbar above content on mobile */
    .navbar { z-index: 1000; }
    
    /* Ensure modal appears above navbar */
    .modal { z-index: 1055 !important; }
    .modal-backdrop { z-index: 1050 !important; }

    .nav-logo {
      width: 50px;
      height: 50px;
      margin-right: 15px;
      border-radius: 50%;
      border: 3px solid rgba(255, 255, 255, 0.8);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
      object-fit: cover;
    }

    .nav-logo:hover {
      transform: scale(1.1) rotate(5deg);
      box-shadow: 0 6px 25px rgba(0, 180, 216, 0.4);
    }

    .navbar-brand {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      color: white !important;
      font-size: 1.5rem;
      text-decoration: none;
      display: flex;
      align-items: center;
    }

    .typing-animation {
      background: linear-gradient(45deg, #00b4d8, #0096c7, #48cae4);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    /* Enhanced Hero Section */
    .hero {
      position: relative;
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 50%, #001122 100%);
      min-height: 100vh;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 20px;
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('images/bago_coli.jpg') center center / cover no-repeat;
      opacity: 0.15;
      z-index: 1;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at 30% 70%, rgba(0, 180, 216, 0.3) 0%, transparent 50%),
                  radial-gradient(circle at 70% 30%, rgba(72, 202, 228, 0.2) 0%, transparent 50%);
      z-index: 2;
    }

    .hero-content {
      position: relative;
      z-index: 3;
      max-width: 900px;
      animation: heroFadeUp 1.2s ease-out both;
    }

    .hero-logo {
      width: 120px;
      height: 120px;
      margin-bottom: 30px;
      border-radius: 50%;
      border: 4px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      transition: all 0.5s ease;
      object-fit: cover;
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    .hero h1 {
      font-size: 3.5rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
      background: linear-gradient(45deg, #ffffff, #48cae4, #00b4d8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .hero .lead {
      font-size: 1.4rem;
      margin-bottom: 2.5rem;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 400;
      line-height: 1.8;
    }

    .btn-login {
      background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
      color: white;
      padding: 16px 40px;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 50px;
      border: none;
      text-decoration: none;
      display: inline-block;
      transition: all 0.4s ease;
      box-shadow: 0 8px 25px rgba(0, 180, 216, 0.4);
      position: relative;
      overflow: hidden;
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.6s ease;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 12px 35px rgba(0, 180, 216, 0.6);
      color: white;
    }

    .btn-login:active {
      transform: translateY(-1px) scale(1.02);
    }

    @keyframes heroFadeUp {
      from {
        opacity: 0;
        transform: translateY(60px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Enhanced Sections */
    .section {
      padding: 100px 0;
      position: relative;
    }

    .section-title {
      font-weight: 700;
      color: var(--primary-blue);
      font-size: 2.5rem;
      margin-bottom: 1rem;
      position: relative;
      display: inline-block;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
      border-radius: 2px;
    }

    .section p {
      color: var(--text-light);
      font-size: 1.1rem;
      line-height: 1.8;
    }

    /* Enhanced Feature Cards */
    .feature-card {
      background: white;
      border-radius: 20px;
      padding: 2.5rem;
      text-align: center;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-sm);
      height: 100%;
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .feature-card:hover::before {
      transform: scaleX(1);
    }

    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-xl);
      border-color: var(--accent-blue);
    }

    .feature-card .feature-icon {
      font-size: 4rem;
      color: var(--accent-blue);
      margin-bottom: 1.5rem;
      transition: all 0.3s ease;
      display: inline-block;
    }

    .feature-card:hover .feature-icon {
      transform: scale(1.1);
      color: var(--light-blue);
    }

    .feature-card h5 {
      font-size: 1.3rem;
      font-weight: 600;
      color: var(--primary-blue);
      margin-bottom: 1rem;
    }

    .feature-card p {
      color: var(--text-light);
      font-size: 1rem;
      line-height: 1.6;
    }

    /* Enhanced Contact Section */
    .contact-section {
      background: linear-gradient(135deg, var(--bg-light) 0%, #edf2f7 100%);
      position: relative;
    }

    .contact-info {
      background: white;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--border-light);
    }

    .contact-info h4 {
      color: var(--primary-blue);
      font-weight: 600;
      margin-bottom: 2rem;
    }

    .contact-info ul li {
      padding: 1rem 0;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .contact-info ul li:last-child {
      border-bottom: none;
    }

    .contact-info ul li i {
      color: var(--accent-blue);
      font-size: 1.2rem;
      width: 20px;
    }

    .contact-form {
      background: white;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--border-light);
    }

    .contact-form h4 {
      color: var(--primary-blue);
      font-weight: 600;
      margin-bottom: 2rem;
    }

    .form-control {
      border: 2px solid var(--border-light);
      border-radius: 12px;
      padding: 15px 20px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background-color: var(--bg-light);
    }

    .form-control:focus {
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
      background-color: white;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
      border: none;
      font-size: 1.1rem;
      font-weight: 600;
      padding: 15px 30px;
      border-radius: 50px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 180, 216, 0.4);
      background: linear-gradient(135deg, var(--light-blue) 0%, var(--accent-blue) 100%);
    }

    /* Forgot Password Link Styling */
    .forgot-password-link {
      color: var(--accent-blue) !important;
      text-decoration: none !important;
      font-weight: 500;
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      z-index: 10;
    }

    .forgot-password-link:hover {
      color: var(--light-blue) !important;
      text-decoration: underline !important;
      transform: translateY(-1px);
    }

    /* Enhanced Modal */
    .modal-content {
      border-radius: 20px;
      border: none;
      box-shadow: var(--shadow-xl);
      overflow: hidden;
    }

    .modal-body {
      padding: 3rem;
      background: linear-gradient(135deg, white 0%, var(--bg-light) 100%);
      position: relative;
    }

    .modal-body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
    }

    .btn-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      border: none;
      background: rgba(0, 0, 0, 0.1);
      color: var(--text-dark);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      transition: all 0.3s ease;
      opacity: 0.7;
    }

    .btn-close:hover {
      background: rgba(239, 68, 68, 0.1);
      color: var(--danger);
      opacity: 1;
      transform: scale(1.1);
    }

    .login-container .logo {
      width: 80px;
      height: 80px;
      margin-bottom: 1.5rem;
      border-radius: 50%;
      border: 3px solid var(--accent-blue);
      box-shadow: 0 4px 20px rgba(0, 180, 216, 0.3);
      object-fit: cover;
    }

    .login-container h4 {
      color: var(--primary-blue);
      font-weight: 600;
      margin-bottom: 2rem;
    }

    /* Enhanced Footer */
    footer {
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      color: rgba(255, 255, 255, 0.9);
      text-align: center;
      padding: 2rem 0;
      font-size: 1rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Enhanced Animations */
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .pulse-animation {
      animation: pulse 2s infinite;
    }

    /* Responsive Design */
    @media (max-width: 991.98px) {
      .navbar-brand { font-size: 1.25rem; }
      .navbar { padding: 0.75rem 0; }
      #mainNavbar .container { position: relative !important; }
      #mainNavbar .navbar-brand {
        position: fixed !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        right: auto !important;
        margin: 0 !important;
        display: inline-block !important;
        text-align: center !important;
        width: auto !important;
        z-index: 1001 !important;
        top: 2rem !important;
        color: white !important;
      }
      .navbar-toggler { z-index: 1002; }
      .nav-logo { display: none; }
      .hero { min-height: 80vh; }
      .hero-logo { width: 96px; height: 96px; }
      .btn-login { padding: 14px 28px; font-size: 1rem; }
      .section { padding: 72px 0; }
      
      /* Burger menu styling */
      .navbar-collapse {
        background: rgba(0, 53, 102, 0.95) !important;
        margin-top: 10px;
        padding: 1rem;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 1000 !important;
      }
      
      /* Ensure navbar-brand is not affected by collapse */
      .navbar-brand {
        position: fixed !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        top: 2rem !important;
        z-index: 1001 !important;
        color: white !important;
        pointer-events: none !important;
      }
      
      .navbar-nav {
        text-align: center;
      }
      
      .nav-link {
        color: white !important;
        padding: 0.75rem 0 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }
      
      .nav-link:last-child {
        border-bottom: none;
      }
      
      .nav-link:hover {
        color: var(--accent-blue) !important;
      }
    }

    @media (max-width: 768px) {
      .hero h1 {
        font-size: 2.5rem;
      }

      .hero .lead {
        font-size: 1.1rem;
      }

      .section-title {
        font-size: 2rem;
      }

      .section { padding: 60px 0; }

      .feature-card {
        margin-bottom: 2rem;
      }

      .modal-body {
        padding: 2rem;
      }

      .contact-info,
      .contact-form {
        margin-bottom: 2rem;
      }
    }

    @media (max-width: 575.98px) {
      .hero { min-height: 70vh; padding: 16px; }
      .hero-logo { width: 84px; height: 84px; }
      .hero h1 { font-size: 2rem; }
      .hero .lead { font-size: 0.98rem; }
      .btn-login { padding: 12px 22px; font-size: 0.98rem; }
    }

    /* Loading Animation */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.5s ease;
    }

    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--accent-blue);
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Scroll Progress Bar */
    .scroll-progress {
      position: fixed;
      top: 0;
      left: 0;
      width: 0%;
      height: 3px;
      background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
      z-index: 9999;
      transition: width 0.3s ease;
    }
    /* Base styles (mobile first) */
body {
  font-size: 14px;
}

/* Small devices (landscape phones, 576px and up) */
@media (min-width: 576px) {
  body {
    font-size: 15px;
  }
}

/* Medium devices (tablets, 768px and up) */
@media (min-width: 768px) {
  body {
    font-size: 15.5px;
  }
}

/* Large devices (desktops, 992px and up) */
@media (min-width: 992px) {
  body {
    font-size: 16px;
  }
}

/* Extra large devices (large desktops, 1200px and up) */
@media (min-width: 1200px) {
  body {
    font-size: 16px;
  }
}
  </style>
</head>
<body>
  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <!-- Scroll Progress Bar -->
  <div class="scroll-progress" id="scrollProgress"></div>

  <!-- Enhanced Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNavbar">
    <div class="container">
      <img src="images/GSO.jpg" alt="Bago Logo" class="nav-logo">
      <a class="navbar-brand" href="#" data-aos="zoom-in" data-aos-delay="400">
        <span class="typing-animation">Smart Track</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="#hero">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#about">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#features">Features</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contact">Contact</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="mobile_app.php">
              <i class="fas fa-mobile-alt me-1"></i>Download App
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
              <i class="fas fa-sign-in-alt me-1"></i>Login
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Enhanced Hero Section -->
  <section class="hero" id="hero">
    <div class="hero-content" data-aos="fade-up" data-aos-duration="1200">
        <img src="images/bago_city.jpg" alt="Bago Logo" class="hero-logo pulse-animation">
        <h1>City Government of Bago</h1>
        <p class="lead">Vehicle Tracking and Real-Time Location Monitoring System with Predictive Analytics</p>
        <div class="d-flex flex-column flex-md-row gap-3 justify-content-center align-items-center">
          <button type="button" class="btn-login" data-bs-toggle="modal" data-bs-target="#loginModal">
              <i class="fas fa-rocket me-2"></i>Access System
          </button>
        </div>
    </div>
  </section>

  <!-- Enhanced About Section -->
  <section class="section bg-light text-center" id="about">
    <div class="container" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
      <h2 class="section-title">About Smart Track</h2>
        <p class="mt-4 mx-auto" style="max-width: 900px;">
          The City Government of Bago's Smart Track system represents the future of fleet management. Our cutting-edge platform combines real-time GPS technology, advanced analytics, and intuitive design to deliver unprecedented visibility and control over government vehicle operations.
        </p>
        <div class="row mt-5 g-4">
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="text-center">
              <div class="feature-icon mb-3">
                <i class="fas fa-shield-alt" style="font-size: 3rem; color: var(--accent-blue);"></i>
              </div>
              <h5>Enhanced Security</h5>
              <p>Advanced encryption and secure access controls protect your data</p>
            </div>
          </div>
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
            <div class="text-center">
              <div class="feature-icon mb-3">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--success);"></i>
              </div>
              <h5>Improved Efficiency</h5>
              <p>Optimize routes and reduce operational costs with smart analytics</p>
            </div>
          </div>
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
            <div class="text-center">
              <div class="feature-icon mb-3">
                <i class="fas fa-users" style="font-size: 3rem; color: var(--warning);"></i>
              </div>
              <h5>Better Service</h5>
              <p>Deliver exceptional public service with reliable fleet management</p>
            </div>
          </div>
        </div>
    </div>
  </section>

  <!-- Enhanced Features Section -->
  <section class="section text-center" id="features">
    <div class="container" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
      <h2 class="section-title">Powerful Features</h2>
      <p class="mt-4 mx-auto mb-5" style="max-width: 800px;">
        Discover the comprehensive suite of features designed to transform your fleet management experience with cutting-edge technology and intuitive controls.
      </p>
      <div class="row g-4 justify-content-center">
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
          <div class="feature-card">
            <i class="bi bi-geo-alt feature-icon"></i>
            <h5>Real-Time GPS Tracking</h5>
            <p>Monitor vehicle locations with precision using advanced GPS technology and interactive mapping interfaces.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
          <div class="feature-card">
            <i class="bi bi-map feature-icon"></i>
            <h5>Smart Route Optimization</h5>
            <p>Route planning reduces fuel consumption and improves response times for maximum efficiency.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
          <div class="feature-card">
            <i class="bi bi-bar-chart feature-icon"></i>
            <h5>Advanced Analytics</h5>
            <p>Comprehensive reports and insights for data-driven decision making and performance optimization.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
          <div class="feature-card">
            <i class="bi bi-shield-lock feature-icon"></i>
            <h5>Geofencing & Alerts</h5>
            <p>Set virtual boundaries and receive instant notifications for unauthorized movements or zone violations.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
          <div class="feature-card">
            <i class="bi bi-shield-shaded feature-icon"></i>
            <h5>Enterprise Security</h5>
            <p>Bank-level encryption and role-based access controls ensure complete data protection and privacy.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="700">
          <div class="feature-card">
            <i class="bi bi-truck feature-icon"></i>
            <h5>Fleet Management</h5>
            <p>Centralized vehicle maintenance, assignment tracking, and lifecycle management in one platform.</p>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- Enhanced Contact Section -->
  <section class="section contact-section" id="contact">
    <div class="container" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
      <div class="row text-center mb-5">
        <div class="col">
          <h2 class="section-title">Get In Touch</h2>
          <p class="lead">Ready to transform your fleet management? Contact our team for personalized assistance.</p>
        </div>
      </div>

      <div class="row g-4">
        <!-- Contact Info -->
        <div class="col-lg-6" data-aos="fade-right" data-aos-delay="200">
          <div class="contact-info">
            <h4><i class="fas fa-info-circle me-2"></i>Contact Information</h4>
            <ul class="list-unstyled">
              <li>
                <i class="fas fa-map-marker-alt"></i>
                <div>
                  <strong>Address:</strong><br>
                  A. Gonzaga St., Brgy. Poblacion, 6101<br>
                  Bago City, Negros Occidental
                </div>
              </li>
              <li>
                <i class="fas fa-phone-alt"></i>
                <div>
                  <strong>Phone:</strong><br>
                  (034) 447 8181
                </div>
              </li>
              <li>
                <i class="fas fa-envelope"></i>
                <div>
                  <strong>Email:</strong><br>
                  gso.bagocity@gmail.com
                </div>
              </li>
              <li>
                <i class="fas fa-clock"></i>
                <div>
                  <strong>Office Hours:</strong><br>
                  Monday - Friday: 8:00 AM - 5:00 PM
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Contact Form -->
        <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
          <div class="contact-form">
            <h4><i class="fas fa-paper-plane me-2"></i>Send Us a Message</h4>
            <form action="submit_form.php" method="POST" id="contactForm">
              <div class="row">
                <div class="col-md-6 mb-4">
                  <label for="name" class="form-label">Full Name *</label>
                  <input type="text" class="form-control" id="name" name="name" required placeholder="Enter your full name">
                </div>
                <div class="col-md-6 mb-4">
                  <label for="email" class="form-label">Email Address *</label>
                  <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                </div>
              </div>
              <div class="mb-4">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="subject" name="subject" placeholder="Message subject">
              </div>
              <div class="mb-4">
                <label for="message" class="form-label">Message *</label>
                <textarea class="form-control" id="message" name="message" rows="5" required placeholder="Write your message here..."></textarea>
              </div>
              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-paper-plane me-2"></i>Send Message
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Enhanced Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body">
              <!-- Close Button -->
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                <i class="fas fa-times"></i>
              </button>
              
              <div class="login-container text-center">
                <!-- Logo -->
                <img src="images/bago_city.jpg" alt="Logo" class="logo" />
                <h4>Welcome Back!</h4>
                <p class="text-muted mb-4">Sign in to access your Smart Track dashboard</p>

                <?php if (!empty($loginError)): ?>
                  <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div><?= htmlspecialchars($loginError); ?></div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($passwordResetSuccess)): ?>
                  <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <div><?= htmlspecialchars($passwordResetSuccess); ?></div>
                  </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="text-start" id="loginForm">
                    <div class="mb-3">
                      <label for="username" class="form-label">
                        <i class="fas fa-user me-1"></i>Username
                      </label>
                      <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required />
                    </div>
                    <div class="mb-3">
                      <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Password
                      </label>
                      <div class="position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required />
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="togglePassword">
                          <i class="fas fa-eye"></i>
                        </button>
                      </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                      <div class="form-check">
                          <input type="checkbox" class="form-check-input" id="remember" name="remember" />
                          <label class="form-check-label text-muted" for="remember">Remember Me</label>
                      </div>
                      <a href="forgot_password.php" class="text-decoration-none forgot-password-link" style="color: var(--accent-blue); position: relative; z-index: 10;">Forgot Password?</a>
                    </div>
                    <div class="d-grid mb-3">
                      <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                      </button>
                    </div>
                    <div class="text-center">
                      <small class="text-muted">
                        Need help? Contact your system administrator
                      </small>
                      <div class="mt-3">
                        <a href="register.php" class="btn btn-outline-primary btn-sm">
                          <i class="fas fa-user-plus me-1"></i>Create Account
                        </a>
                      </div>
                    </div>
                </form>
              </div>
          </div>
        </div>
    </div>
  </div>

  <!-- Enhanced Footer -->
  <footer>
    <div class="container">
      <div class="row">
        <div class="col-md-6 text-md-start text-center mb-3 mb-md-0">
          <p class="mb-0">
            <i class="fas fa-copyright me-1"></i>
            <?php echo date("Y"); ?> City Government of Bago. All rights reserved.
          </p>
        </div>
        <div class="col-md-6 text-md-end text-center">
          <p class="mb-0">
            Powered by <strong>Smart Track</strong> - Advanced Fleet Management
          </p>
        </div>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <script>
    // Initialize AOS
    AOS.init({
      duration: 1000,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    });

    // Enhanced page loading
    document.addEventListener("DOMContentLoaded", function () {
      // Auto-hide alerts after 5 seconds
      setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(function() {
            alert.remove();
          }, 500);
        });
      }, 5000);

      // Hide loading overlay
      setTimeout(() => {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
          loadingOverlay.style.opacity = '0';
          setTimeout(() => {
            loadingOverlay.style.display = 'none';
          }, 500);
        }
      }, 1000);

      // Initialize all features
      initializeTypingAnimation();
      initializeNavigation();
      initializeScrollProgress();
      initializePasswordToggle();
      initializeFormValidation();
      initializeContactForm();
      
      // Auto-show login modal if there's an error
      <?php if (!empty($loginError)): ?>
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
      <?php endif; ?>
    });

    // Enhanced typing animation
    function initializeTypingAnimation() {
      const element = document.querySelector('.typing-animation');
      const text = "Smart Track";
      let i = 0;

      function typeLetter() {
        if (i < text.length) {
          const span = document.createElement("span");
          span.textContent = text.charAt(i);
          span.style.opacity = 0;
          span.style.display = "inline-block";
          span.style.transition = "opacity 0.3s ease";
          element.appendChild(span);

          requestAnimationFrame(() => {
            span.style.opacity = 1;
          });

          i++;
          setTimeout(typeLetter, 120 + Math.random() * 100);
        }
      }

      element.innerHTML = "";
      setTimeout(typeLetter, 500);
    }

    // Enhanced navigation
    function initializeNavigation() {
      const navbar = document.getElementById('mainNavbar');
      const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
      const sections = document.querySelectorAll("section");

      // Navbar scroll effect
      window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
          navbar.classList.add('scrolled');
        } else {
          navbar.classList.remove('scrolled');
        }
      });

      // Smooth scrolling and active link management
      navLinks.forEach(link => {
        link.addEventListener("click", function (e) {
          if (this.getAttribute("href").startsWith("#")) {
            e.preventDefault();
            const targetId = this.getAttribute("href").substring(1);
            const targetSection = document.getElementById(targetId);

            if (targetSection) {
              const offsetTop = targetSection.offsetTop - 80;
              window.scrollTo({
                top: offsetTop,
                behavior: "smooth"
              });
            }

            // Update active link
            navLinks.forEach(l => l.classList.remove("active"));
            this.classList.add("active");

            // Close mobile menu
            const navbarCollapse = document.getElementById('navbarNav');
            if (navbarCollapse.classList.contains('show')) {
              bootstrap.Collapse.getInstance(navbarCollapse).hide();
            }
          }
        });
      });

      // Highlight active section on scroll
      window.addEventListener("scroll", function () {
        let current = "";
        sections.forEach(section => {
          const sectionTop = section.offsetTop - 100;
          if (scrollY >= sectionTop) {
            current = section.getAttribute("id");
          }
        });

        navLinks.forEach(link => {
          link.classList.remove("active");
          if (link.getAttribute("href") === "#" + current) {
            link.classList.add("active");
          }
        });
      });
    }

    // Scroll progress bar
    function initializeScrollProgress() {
      const progressBar = document.getElementById('scrollProgress');
      
      window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset;
        const docHeight = document.body.scrollHeight - window.innerHeight;
        const scrollPercent = (scrollTop / docHeight) * 100;
        progressBar.style.width = scrollPercent + '%';
      });
    }

    // Password toggle functionality
    function initializePasswordToggle() {
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');

      if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
          const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', type);
          
          const icon = this.querySelector('i');
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      }
    }

    // Enhanced form validation
    function initializeFormValidation() {
      const loginForm = document.getElementById('loginForm');
      
      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          const username = document.getElementById('username').value.trim();
          const password = document.getElementById('password').value.trim();
          
          if (!username || !password) {
            e.preventDefault();
            showAlert('error', 'Please fill in all required fields.');
            return;
          }
          
          // Show loading state
          const loginBtn = document.getElementById('loginBtn');
          const originalContent = loginBtn.innerHTML;
          loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
          loginBtn.disabled = true;
          
          // Reset button after 3 seconds if form doesn't submit
          setTimeout(() => {
            loginBtn.innerHTML = originalContent;
            loginBtn.disabled = false;
          }, 3000);
        });
      }
    }

    // Contact form handling
    function initializeContactForm() {
      const contactForm = document.getElementById('contactForm');
      
      if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          // Show success message
          showAlert('success', 'Thank you for your message! We\'ll get back to you soon.');
          
          // Reset form
          this.reset();
        });
      }
    }

    // Enhanced alert system
    function showAlert(type, message) {
      const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-exclamation-triangle'
      };

      Swal.fire({
        icon: type,
        title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Information',
        text: message,
        confirmButtonColor: 'var(--accent-blue)',
        confirmButtonText: 'OK',
        customClass: {
          popup: 'swal-popup-custom'
        }
      });
    }

    // Intersection observer for animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-in');
        }
      });
    }, observerOptions);

    // Observe all feature cards
    document.querySelectorAll('.feature-card').forEach(card => {
      observer.observe(card);
    });

    // Add custom styles for SweetAlert
    const style = document.createElement('style');
    style.textContent = `
      .swal-popup-custom {
        border-radius: 15px !important;
        font-family: 'Inter', sans-serif !important;
      }
    `;
    document.head.appendChild(style);

    // Parallax effect for hero section
    window.addEventListener('scroll', function() {
      const scrolled = window.pageYOffset;
      const hero = document.getElementById('hero');
      if (hero) {
        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
      }
    });

    // Add hover sound effects (optional)
    document.querySelectorAll('.btn, .nav-link, .feature-card').forEach(element => {
      element.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
      });
    });

    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
      // Escape key closes modal
      if (e.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        if (modal) {
          modal.hide();
        }
      }
    });

    // Add smooth reveal animations
    const revealElements = document.querySelectorAll('.section');
    const revealObserver = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    });

    revealElements.forEach(element => {
      element.style.opacity = '0';
      element.style.transform = 'translateY(30px)';
      element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
      revealObserver.observe(element);
    });
  </script>
</body>
</html>