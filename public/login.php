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
                       photo, login_attempts, account_locked_until
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

                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['username']      = $username;
                $_SESSION['userrole']      = $user['userrole'];
                $_SESSION['first_name']     = $user['first_name'];
                $_SESSION['last_name']     = $user['last_name'];
                $_SESSION['sex']     = $user['sex'];
                $_SESSION['status']      = $user['status'];
                $_SESSION['photo']         = $user['photo'];
                $_SESSION['last_activity'] = time();

                header("Location: ../dashboard/dashboard.php");
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
    <title>TM-monitoring</title>
     <style>
    body { background:#9CCFFF; }
    .container { margin-top:7%; }
    .grid-container {display:grid; grid-template-columns:repeat(3,1fr);  gap:20px; }
    .container-item {display:flex; justify-content:center; align-items:center; width:100%; margin-bottom:20px; text-align:center;}
    #errorMessage { color:red; }
    h2, label { color:#0D1A63; font-family:Tahoma, Geneva, sans-serif;}
    label { font-weight:bold;  font-size:22px; margin:10px; }
    input, .btn-submit { width:400px; height:50px; font-size:22px; border-radius:5px; text-align:center; }
    .btn-submit {background:#0D1A63;  color:#fff; font-weight:bold;  border:none; }
    .btn-submit:hover { cursor:pointer; }
    .footer { background:#0D1A63; color:#fff; height:80px; display:flex; justify-content:center; align-items:center; position:fixed; bottom:0; left:0; width:100%;}
    .footer-content {display:flex;   align-items:center; gap:15px;font-size:22px; }
    .social-links { display:flex; gap:12px; }
    .social-links a {color:#fff; font-size:24px; text-decoration:none; }
    input {background-color: #9CCFFF;}

</style>
</head>
<body>
        <div class="container">
                    <center>
                        <div id="errorMessage" style="color: red;">
                            <?php echo $error_message; ?>
                        </div>
                    </center>

            <div class="container-item">
                <div class="logo">
                    <img src="../assets/images/Logo-globe.png" width="156" height="152" alt="">
                </div>
            </div>
            <div class="container-item">
                    <form action="login.php" method="post">
                        <label for="username">User Name:</label> <br><br>
                        <input type="text" id="username" name="username" required>
                        <br><br>
                        <label for="password">Password:</label> <br><br>
                        <input type="password" id="password" name="password" required>
                        <br><br>
                        <button type="submit" class="btn-submit">Login</button>
                    </form>
            </div>

        </div>

<?php include '../includes/footer.php'; ?>

</body>
</html>