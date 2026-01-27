<?php
session_start();
require_once 'includes/db_connection.php';

$message = '';
$messageType = '';
$phone = isset($_SESSION['sms_phone']) ? $_SESSION['sms_phone'] : '';
$userId = isset($_SESSION['sms_user_id']) ? $_SESSION['sms_user_id'] : '';

// Redirect if no SMS session
if (empty($phone) || empty($userId)) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smsCode = trim($_POST['sms_code']);
    
    if (empty($smsCode)) {
        $message = 'Please enter the 6-digit verification code.';
        $messageType = 'error';
    } elseif (!preg_match('/^\d{6}$/', $smsCode)) {
        $message = 'Please enter a valid 6-digit code.';
        $messageType = 'error';
    } else {
        // Check if SMS code exists and is not expired
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND user_id = ? AND expires_at > NOW() AND used = 0");
        $stmt->bind_param("sss", $phone, $smsCode, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $resetData = $result->fetch_assoc();
            
            // Mark the SMS code as used
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->bind_param("i", $resetData['id']);
            $stmt->execute();
            
            // Store user info in session for password reset
            $_SESSION['sms_verified'] = true;
            $_SESSION['sms_user_id'] = $userId;
            $_SESSION['sms_phone'] = $phone;
            
            // Redirect to password reset page
            header("Location: reset_password_sms.php");
            exit();
        } else {
            // Check if code exists but is expired or used
            $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND user_id = ?");
            $stmt->bind_param("sss", $phone, $smsCode, $userId);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $checkData = $checkResult->fetch_assoc();
                if ($checkData['used'] == 1) {
                    $message = 'This verification code has already been used. Please request a new code.';
                } else {
                    $message = 'This verification code has expired. Please request a new code.';
                }
            } else {
                $message = 'Invalid verification code. Please check your SMS and try again.';
            }
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
    <title>Verify SMS Code - Smart Track System</title>
    
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

        .verify-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            position: relative;
        }

        .verify-container::before {
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

        .form-control {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--bg-light);
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 8px;
            font-weight: 600;
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

        .phone-display {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 10px 15px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .resend-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }

        .resend-section p {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .resend-link {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .resend-link:hover {
            color: var(--light-blue);
        }

        .resend-link.disabled {
            color: var(--text-light);
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="header-section">
            <img src="images/bago_city.jpg" alt="Bago Logo" class="logo">
            <h2>Verify SMS Code</h2>
            <p>Enter the 6-digit code sent to your phone</p>
        </div>
        
        <div class="form-section">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> d-flex align-items-center" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h6><i class="fas fa-mobile-alt me-2"></i>SMS Verification</h6>
                <p>We've sent a 6-digit verification code to your phone number. Please enter it below to continue with the password reset process.</p>
            </div>

            <div class="phone-display">
                <i class="fas fa-mobile-alt me-2"></i>
                <?= htmlspecialchars(substr($phone, 0, 4) . '****' . substr($phone, -3)) ?>
            </div>

            <form method="POST" action="" id="verifyForm">
                <div class="mb-4">
                    <label for="sms_code" class="form-label">
                        <i class="fas fa-key me-1"></i>Verification Code
                    </label>
                    <input type="text" class="form-control" id="sms_code" name="sms_code" 
                           placeholder="000000" maxlength="6" pattern="\d{6}" required>
                    <div class="form-text text-center mt-2">Enter the 6-digit code from your SMS</div>
                </div>

                <button type="submit" class="btn btn-primary" id="verifyBtn">
                    <i class="fas fa-check me-2"></i>Verify Code
                </button>
            </form>

            <div class="resend-section">
                <p>Didn't receive the code?</p>
                <a href="forgot_password.php?method=sms" class="resend-link" id="resendLink">
                    <i class="fas fa-redo me-1"></i>Resend Code
                </a>
            </div>

            <div class="back-link">
                <a href="forgot_password.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Forgot Password
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus on SMS code input
        document.getElementById('sms_code').focus();

        // Auto-format SMS code input
        document.getElementById('sms_code').addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });

        // Form validation
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            const smsCode = document.getElementById('sms_code').value.trim();
            
            if (!smsCode) {
                e.preventDefault();
                showAlert('Please enter the verification code.', 'error');
                return;
            }
            
            if (!/^\d{6}$/.test(smsCode)) {
                e.preventDefault();
                showAlert('Please enter a valid 6-digit code.', 'error');
                return;
            }
            
            // Show loading state
            const verifyBtn = document.getElementById('verifyBtn');
            const originalContent = verifyBtn.innerHTML;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
            verifyBtn.disabled = true;
            
            // Reset button after 5 seconds if form doesn't submit
            setTimeout(() => {
                verifyBtn.innerHTML = originalContent;
                verifyBtn.disabled = false;
            }, 5000);
        });

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
