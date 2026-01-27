<?php
session_start();
require_once 'includes/db_connection.php';

$message = '';
$messageType = '';
$phone = isset($_SESSION['sms_phone']) ? $_SESSION['sms_phone'] : '';
$userId = isset($_SESSION['sms_user_id']) ? $_SESSION['sms_user_id'] : '';
$smsVerified = isset($_SESSION['sms_verified']) ? $_SESSION['sms_verified'] : false;

// Redirect if not verified via SMS
if (!$smsVerified || empty($phone) || empty($userId)) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password in the user_table
        $stmt = $conn->prepare("UPDATE user_table SET password = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $hashed_password, $userId);
        
        if ($stmt->execute()) {
            // Clear SMS session data
            unset($_SESSION['sms_verified']);
            unset($_SESSION['sms_phone']);
            unset($_SESSION['sms_user_id']);
            
            // Redirect to login page with success message
            $_SESSION['password_reset_success'] = 'Password has been successfully reset via SMS verification. You can now login with your new password.';
            header("Location: index.php");
            exit();
        } else {
            $message = 'Sorry, there was an error updating your password. Please try again.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Track System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome -->
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-password-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            position: relative;
            display: flex;
            min-height: 500px;
        }

        @media (max-width: 768px) {
            .reset-password-container {
                max-width: 100%;
                min-height: auto;
                flex-direction: column;
            }
        }

        .reset-password-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        @media (max-width: 768px) {
            .header-section {
                flex: none;
                padding: 30px 20px;
            }
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.9);
            margin-bottom: 20px;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .logo {
                width: 60px;
                height: 60px;
                margin-bottom: 15px;
            }
        }

        .header-section h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        @media (max-width: 768px) {
            .header-section h2 {
                font-size: 1.3rem;
            }
        }

        .header-section p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-section p {
                font-size: 0.8rem;
            }
        }

        .form-section {
            padding: 40px 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .form-section {
                flex: none;
                padding: 30px 20px;
            }
        }

        .form-control {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--bg-light);
        }

        @media (max-width: 768px) {
            .form-control {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
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
            width: 100%;
        }

        @media (max-width: 768px) {
            .btn-primary {
                font-size: 1rem;
                padding: 12px 25px;
            }
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 180, 216, 0.4);
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--accent-blue) 100%);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--border-light);
            color: var(--text-dark);
            font-weight: 600;
            padding: 15px 30px;
            border-radius: 50px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 15px;
        }

        .btn-secondary:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
            background-color: rgba(0, 180, 216, 0.05);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
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

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--light-blue);
        }

        .info-box {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success);
        }

        @media (max-width: 768px) {
            .info-box {
                padding: 15px;
                margin-bottom: 15px;
            }
        }

        .info-box h6 {
            color: var(--primary-blue);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .info-box p {
            color: var(--text-light);
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .password-strength {
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .password-strength.weak {
            color: var(--danger);
        }

        .password-strength.medium {
            color: var(--warning);
        }

        .password-strength.strong {
            color: var(--success);
        }

        .password-requirements {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .password-requirements {
                padding: 12px;
                font-size: 0.8rem;
            }
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: var(--text-light);
        }

        .password-requirements li {
            margin-bottom: 5px;
        }

        .requirement-met {
            color: var(--success);
        }

        .requirement-not-met {
            color: var(--text-light);
        }

        .phone-display {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 10px 15px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--primary-blue);
        }

        @media (max-width: 768px) {
            .phone-display {
                padding: 8px 12px;
                margin-bottom: 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <div class="header-section">
            <img src="images/bago_city.jpg" alt="Bago Logo" class="logo">
            <h2>Reset Password</h2>
            <p>Create a new secure password</p>
        </div>
        
        <div class="form-section">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> d-flex align-items-center" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h6><i class="fas fa-check-circle me-2"></i>SMS Verification Complete</h6>
                <p>Your phone number has been verified successfully. You can now create a new password for your account.</p>
            </div>

            <div class="phone-display">
                <i class="fas fa-mobile-alt me-2"></i>
                Verified: <?= htmlspecialchars(substr($phone, 0, 4) . '****' . substr($phone, -3)) ?>
            </div>

            <form method="POST" action="" id="resetForm">
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i>New Password
                    </label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your new password" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-requirements" id="passwordRequirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li id="req-length" class="requirement-not-met">At least 8 characters long</li>
                            <li id="req-uppercase" class="requirement-not-met">At least one uppercase letter</li>
                            <li id="req-lowercase" class="requirement-not-met">At least one lowercase letter</li>
                            <li id="req-number" class="requirement-not-met">At least one number</li>
                            <li id="req-special" class="requirement-not-met">At least one special character</li>
                        </ul>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Confirm New Password
                    </label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm your new password" required>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="resetBtn">
                    <i class="fas fa-key me-2"></i>Reset Password
                </button>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
        function initializePasswordToggles() {
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }

            if (toggleConfirmPassword && confirmPasswordInput) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            // Update requirement indicators
            document.getElementById('req-length').className = requirements.length ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('req-uppercase').className = requirements.uppercase ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('req-lowercase').className = requirements.lowercase ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('req-number').className = requirements.number ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('req-special').className = requirements.special ? 'requirement-met' : 'requirement-not-met';

            // Calculate strength
            Object.values(requirements).forEach(met => {
                if (met) strength++;
            });

            const strengthElement = document.getElementById('passwordStrength');
            if (password.length === 0) {
                strengthElement.textContent = '';
                strengthElement.className = 'password-strength';
            } else if (strength <= 2) {
                strengthElement.textContent = 'Weak password';
                strengthElement.className = 'password-strength weak';
            } else if (strength <= 4) {
                strengthElement.textContent = 'Medium strength password';
                strengthElement.className = 'password-strength medium';
            } else {
                strengthElement.textContent = 'Strong password';
                strengthElement.className = 'password-strength strong';
            }

            return strength;
        }

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchElement.textContent = '';
                matchElement.className = 'password-match';
            } else if (password === confirmPassword) {
                matchElement.textContent = 'Passwords match';
                matchElement.className = 'password-match text-success';
            } else {
                matchElement.textContent = 'Passwords do not match';
                matchElement.className = 'password-match text-danger';
            }
        }

        // Form validation
        function initializeFormValidation() {
            const form = document.getElementById('resetForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    checkPasswordMatch();
                });
            }

            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    checkPasswordMatch();
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (!password || !confirmPassword) {
                        e.preventDefault();
                        showAlert('Please fill in all fields.', 'error');
                        return;
                    }
                    
                    if (checkPasswordStrength(password) < 3) {
                        e.preventDefault();
                        showAlert('Please create a stronger password that meets the requirements.', 'error');
                        return;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        showAlert('Passwords do not match.', 'error');
                        return;
                    }
                    
                    // Show loading state
                    const resetBtn = document.getElementById('resetBtn');
                    const originalContent = resetBtn.innerHTML;
                    resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting Password...';
                    resetBtn.disabled = true;
                    
                    // Reset button after 5 seconds if form doesn't submit
                    setTimeout(() => {
                        resetBtn.innerHTML = originalContent;
                        resetBtn.disabled = false;
                    }, 5000);
                });
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} d-flex align-items-center`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
                <div>${message}</div>
            `;
            
            const formSection = document.querySelector('.form-section');
            formSection.insertBefore(alertDiv, formSection.firstChild);
            
            setTimeout(function() {
                alertDiv.style.transition = 'opacity 0.5s ease';
                alertDiv.style.opacity = '0';
                setTimeout(function() {
                    alertDiv.remove();
                }, 500);
            }, 3000);
        }

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

        // Initialize all functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializePasswordToggles();
            initializeFormValidation();
        });
    </script>
</body>
</html>
