<?php
session_start();
include '../includes/config.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        $sql = "SELECT user_id, password, userrole, first_name, last_name, full_name, sex, status,
                       photo, login_attempts, account_locked_until, id_number
                FROM tblusers WHERE status = 'Active' AND username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
                $error_message = "Account temporarily locked. Please try again later.";
            }
            elseif ($user['status'] == 'Inactive') {
                $error_message = "Account is deactivated. Please contact administrator.";
            }
            elseif (password_verify($password, $user['password'])) {
                $update_sql = "UPDATE tblusers SET
                              last_login = NOW(),
                              login_attempts = 0,
                              account_locked_until = NULL
                              WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();

                session_regenerate_id(true);

                // Get staff_id from county_staff using id_number
                $staff_id = null;
                if (!empty($user['id_number'])) {
                    $staff_stmt = $conn->prepare("SELECT staff_id FROM county_staff WHERE id_number = ?");
                    $staff_stmt->bind_param('s', $user['id_number']);
                    $staff_stmt->execute();
                    $staff_result = $staff_stmt->get_result();
                    if ($staff_row = $staff_result->fetch_assoc()) {
                        $staff_id = $staff_row['staff_id'];
                    }
                    $staff_stmt->close();
                }

                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['username']      = $username;
                $_SESSION['userrole']      = $user['userrole'];
                $_SESSION['first_name']    = $user['first_name'];
                $_SESSION['last_name']     = $user['last_name'];
                $_SESSION['full_name']     = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['sex']            = $user['sex'];
                $_SESSION['status']         = $user['status'];
                $_SESSION['photo']          = $user['photo'];
                $_SESSION['role']           = $user['userrole']; // Add this for layout.php compatibility
                $_SESSION['id_number']      = $user['id_number']; // Store id_number in session
                $_SESSION['staff_id']       = $staff_id; // Store staff_id in session
                $_SESSION['last_activity']  = time();

                // FIXED: Redirect to layout with correct path to welcome.php
                header("Location: ../includes/layout.php?page=../public/welcome.php");
                exit();
            } else {
                $new_attempts = $user['login_attempts'] + 1;
                $lock_until = null;

                if ($new_attempts >= 5) {
                    $lock_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $error_message = "Too many failed login attempts. Account locked for 30 minutes.";
                } else {
                    $error_message = "Invalid username or password. Attempts: {$new_attempts}/5";
                }

                $update_sql = "UPDATE tblusers SET
                              login_attempts = ?,
                              account_locked_until = ?
                              WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("isi", $new_attempts, $lock_until, $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } else {
            $error_message = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/bootstrap.css" type="text/css">
    <title>TM-monitoring - Login</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(10px);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-container img {
            width: 120px;
            height: auto;
            border-radius: 10px;
        }

        .logo-container h2 {
            color: #0D1A63;
            font-size: 24px;
            margin-top: 15px;
            font-weight: 600;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #0D1A63;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: #0D1A63;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #1a2a7a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(13, 26, 99, 0.3);
        }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }

        .footer-links a {
            color: #0D1A63;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }

        .info-box i {
            margin-right: 5px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            input, .btn-submit {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../assets/images/Logo-globe.png" width="156" height="152" alt="Logo">
            <h2>Training Management System</h2>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="info-box">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username"
                       placeholder="Enter your username" required
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="footer-links">
            <p>Default password for new users: <strong>123456</strong></p>
            <p>Forgot your password? Contact system administrator</p>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>