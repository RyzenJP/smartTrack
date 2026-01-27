<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'includes/db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config/security.php';
$security = Security::getInstance();

$message = '';
$messageType = '';
$resetMethod = $security->getGet('method', 'string', 'email'); // Default to email

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resetMethod = $security->getPost('reset_method', 'string', 'email');
    
    if ($resetMethod === 'email') {
        $email = $security->getPost('email', 'email', '');
        
        if (empty($email)) {
            $message = 'Please enter your email address.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } else {
            // Check if email exists in the user_table
            $stmt = $conn->prepare("SELECT user_id, username, role FROM user_table WHERE email = ? AND status = 'Active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                // Use database timezone for consistency - use prepared statement for consistency
                $expiry_stmt = $conn->prepare("SELECT DATE_ADD(NOW(), INTERVAL 1 HOUR) as expiry");
                $expiry_stmt->execute();
                $expiry = $expiry_stmt->get_result()->fetch_assoc()['expiry'];
                $expiry_stmt->close();
                
                // Store reset token
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, user_type, user_id, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $token, $user['role'], $user['user_id'], $expiry);
                
                if ($stmt->execute()) {
                    // Send email using PHPMailer
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.hostinger.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'smarttrack_info@bccbsis.com';
                        $mail->Password = 'G;q@Di$1';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port = 465;
                        
                        // Recipients
                        $mail->setFrom('smarttrack_info@bccbsis.com', 'Smart Track System');
                        $mail->addAddress($email, $user['username']);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request - Smart Track System';
                        
                        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                        
                        $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <div style='background: linear-gradient(135deg, #003566 0%, #001d3d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                                <h2 style='margin: 0;'>Smart Track System</h2>
                                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Password Reset Request</p>
                            </div>
                            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                <h3 style='color: #003566; margin-top: 0;'>Hello {$user['username']},</h3>
                                <p>We received a request to reset your password for your Smart Track System account.</p>
                                <p>If you didn't make this request, you can safely ignore this email.</p>
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='{$resetLink}' style='background: linear-gradient(135deg, #00b4d8 0%, #0096c7 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: bold;'>Reset Password</a>
                                </div>
                                <p style='font-size: 14px; color: #666;'>This link will expire in 1 hour for security reasons.</p>
                                <p style='font-size: 14px; color: #666;'>If the button doesn't work, copy and paste this link into your browser:</p>
                                <p style='font-size: 12px; color: #999; word-break: break-all;'>{$resetLink}</p>
                                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                                <p style='font-size: 12px; color: #666; text-align: center;'>
                                    This is an automated message from the Smart Track System.<br>
                                    Please do not reply to this email.
                                </p>
                            </div>
                        </div>";
                        
                        $mail->send();
                        $message = 'Password reset instructions have been sent to your email address. Please check your inbox and follow the link to reset your password.';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Sorry, there was an error sending the email. Please try again later.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Sorry, there was an error processing your request. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'No active account found with this email address.';
                $messageType = 'error';
            }
        }
    } elseif ($resetMethod === 'sms') {
        $phone = trim($_POST['phone']);
        
        if (empty($phone)) {
            $message = 'Please enter your phone number.';
            $messageType = 'error';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $message = 'Please enter a valid Philippine mobile number (e.g., 09123456789).';
            $messageType = 'error';
        } else {
            // Check if phone exists in the user_table
            $stmt = $conn->prepare("SELECT user_id, username, role FROM user_table WHERE phone = ? AND status = 'Active'");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate SMS code (6 digits)
                $smsCode = sprintf('%06d', mt_rand(0, 999999));
                // Use prepared statement for consistency and security best practices
                $expiry_stmt = $conn->prepare("SELECT DATE_ADD(NOW(), INTERVAL 15 MINUTE) as expiry");
                $expiry_stmt->execute();
                $expiry_result = $expiry_stmt->get_result();
                $expiry = $expiry_result->fetch_assoc()['expiry'];
                $expiry_stmt->close();
                
                // Store SMS reset code
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, user_type, user_id, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $phone, $smsCode, $user['role'], $user['user_id'], $expiry);
                
                if ($stmt->execute()) {
                    // Include SMS API functions
                    require_once 'sms api.php';
                    
                    // Send SMS using the new API function
                    $smsResult = sendPasswordResetSMS($phone, $smsCode);
                    
                    // Log SMS attempt for debugging
                    error_log("SMS API Call - Phone: {$phone}, Result: " . json_encode($smsResult));
                    
                    // Check if SMS was sent successfully
                    if ($smsResult['success']) {
                        // SMS sent successfully
                        $message = 'A 6-digit verification code has been sent to your phone number. Please check your SMS and enter the code to reset your password.';
                        $messageType = 'success';
                        $_SESSION['sms_phone'] = $phone;
                        $_SESSION['sms_user_id'] = $user['user_id'];
                        
                        // Redirect to SMS verification page
                        header("Location: verify_sms_code.php");
                        exit();
                    } else {
                        // SMS failed to send
                        $message = 'Sorry, there was an error sending the SMS. Please try again later.';
                        $messageType = 'error';
                        error_log("SMS Error: " . $smsResult['error']);
                    }
                } else {
                    $message = 'Sorry, there was an error processing your request. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'No active account found with this phone number.';
                $messageType = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Smart Track System</title>
    
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

        .forgot-password-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
            position: relative;
        }

        .forgot-password-container::before {
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
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.9);
            margin-bottom: 20px;
            object-fit: cover;
        }

        .header-section h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .header-section p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .form-section {
            padding: 40px 30px;
        }

        .method-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: var(--bg-light);
            border-radius: 12px;
            padding: 5px;
        }

        .method-option {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .method-option.active {
            background: var(--accent-blue);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 180, 216, 0.3);
        }

        .method-option:not(.active) {
            color: var(--text-light);
        }

        .method-option:not(.active):hover {
            background: rgba(0, 180, 216, 0.1);
            color: var(--accent-blue);
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
            width: 100%;
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
            border-left: 4px solid var(--accent-blue);
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

        .form-group {
            display: none;
        }

        .form-group.active {
            display: block;
        }

        .phone-format {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header-section">
            <img src="images/bago_city.jpg" alt="Bago Logo" class="logo">
            <h2>Forgot Password</h2>
            <p>Choose your preferred reset method</p>
        </div>
        
        <div class="form-section">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> d-flex align-items-center" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h6><i class="fas fa-info-circle me-2"></i>Reset Options</h6>
                <p>You can reset your password using either your email address or phone number. Choose the method that works best for you.</p>
            </div>

            <form method="POST" action="" id="forgotForm">
                <div class="method-selector">
                    <button type="button" class="method-option <?= $resetMethod === 'email' ? 'active' : '' ?>" data-method="email">
                        <i class="fas fa-envelope"></i>
                        Email
                    </button>
                    <button type="button" class="method-option <?= $resetMethod === 'sms' ? 'active' : '' ?>" data-method="sms">
                        <i class="fas fa-mobile-alt"></i>
                        SMS
                    </button>
                </div>

                <input type="hidden" name="reset_method" id="resetMethod" value="<?= $resetMethod ?>">

                <!-- Email Form -->
                <div class="form-group <?= $resetMethod === 'email' ? 'active' : '' ?>" id="emailForm">
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Enter your registered email address" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                </div>

                <!-- SMS Form -->
                <div class="form-group <?= $resetMethod === 'sms' ? 'active' : '' ?>" id="smsForm">
                    <div class="mb-4">
                        <label for="phone" class="form-label">
                            <i class="fas fa-mobile-alt me-1"></i>Phone Number
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="Enter your registered phone number" 
                               value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                        <div class="phone-format">Format: 09123456789 (Philippine mobile number)</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i>
                    <span id="submitText">Send Reset Instructions</span>
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
        // Method selector functionality
        document.querySelectorAll('.method-option').forEach(option => {
            option.addEventListener('click', function() {
                const method = this.dataset.method;
                
                // Update active state
                document.querySelectorAll('.method-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                // Update hidden input
                document.getElementById('resetMethod').value = method;
                
                // Show/hide form groups
                document.querySelectorAll('.form-group').forEach(group => group.classList.remove('active'));
                document.getElementById(method + 'Form').classList.add('active');
                
                // Update submit button text
                const submitText = document.getElementById('submitText');
                if (method === 'email') {
                    submitText.textContent = 'Send Reset Email';
                } else {
                    submitText.textContent = 'Send SMS Code';
                }
            });
        });

        // Form validation
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const method = document.getElementById('resetMethod').value;
            let isValid = true;
            
            if (method === 'email') {
                const email = document.getElementById('email').value.trim();
                if (!email) {
                    showAlert('Please enter your email address.', 'error');
                    isValid = false;
                } else if (!isValidEmail(email)) {
                    showAlert('Please enter a valid email address.', 'error');
                    isValid = false;
                }
            } else if (method === 'sms') {
                const phone = document.getElementById('phone').value.trim();
                if (!phone) {
                    showAlert('Please enter your phone number.', 'error');
                    isValid = false;
                } else if (!isValidPhone(phone)) {
                    showAlert('Please enter a valid Philippine mobile number (e.g., 09123456789).', 'error');
                    isValid = false;
                }
            }
            
            if (isValid) {
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalContent = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                submitBtn.disabled = true;
                
                // Reset button after 5 seconds if form doesn't submit
                setTimeout(() => {
                    submitBtn.innerHTML = originalContent;
                    submitBtn.disabled = false;
                }, 5000);
            } else {
                e.preventDefault();
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidPhone(phone) {
            const phoneRegex = /^09\d{9}$/;
            return phoneRegex.test(phone);
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
    </script>
</body>
</html>
