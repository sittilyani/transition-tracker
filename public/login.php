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
    <title>Login - HPTU LMIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Page Setup */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #3F10CB;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 60px; /* Space to prevent footer from covering form content */
        }

        /* Login Container Styling */
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 480px;
            max-width: 60%;
            margin: auto; /* Centers the form vertically and horizontally */
            text-align: center;
        }

        .login-container img {
            width: 120px;
            margin-bottom: 20px;
        }

        /* STICKY FOOTER STYLING */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            height: 8%;
            width: 100%; /* Spans 100% device width */
            background-color: #ffffff;
            border-top: 1px solid #e0e0e0;
            padding: 33px 20px;
            z-index: 9999;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Helps on very small screens */
            gap: 10px;
        }

        .footer-text {
            font-size: 18px;
            color: #666;
            flex: 1;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            color: #4361ee;
            font-size: 20px;
            transition: transform 0.2s ease;
        }

        .social-links a:hover {
            transform: scale(1.2);
            color: #011f88;
        }

        /* Form Inputs */
        .form-group { margin-bottom: 20px; text-align: left; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-top: 5px; }
        .btn-submit {
            width: 100%; padding: 12px; background: #4361ee; color: white; border: none;
            border-radius: 6px; font-weight: bold; cursor: pointer;
        }

        @media (max-width: 600px) {
            .footer-content { justify-content: center; text-align: center; }
            .footer-text { margin-bottom: 5px; }
        }
    </style>
</head>
<body>

    <div class="login-container">

        <img src="../assets/images/Logo-round-nobg-2.png" width="102" height="102" alt="">
        <h2>Welcome Back</h2>
        <p style="color: #666; margin-bottom: 25px;">Please login to your account</p>

        <?php if (!empty($error_message)): ?>
            <div style="color: red; margin-bottom: 15px; font-size: 14px;"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            
            <div class="register" style="margin-top: 20px;">
                <p>Not registered Yet? Please contact the administrator</p>
            </div>
        </form>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-text">
                Trainings and mentorship monitoring - developed by LVCTHealth - Stawisha Pwani Project
                <?php echo date('Y');?> - &copy; LVCT@20
            </div>

            <div class="social-links">
                <a href="https://web.facebook.com/LVCTHealth/" target="_blank"><i class="fab fa-facebook"></i></a>
                <a href="https://www.youtube.com/user/TheLVCT" target="_blank"><i class="fab fa-youtube"></i></a>
                <a href="https://x.com/LVCTKe" target="_blank"><i class="fab fa-x-twitter"></i></a>
                <a href="https://www.instagram.com/lvct_health/" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://www.linkedin.com/company/lvcthealth/" target="_blank"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </footer>

</body>
</html>