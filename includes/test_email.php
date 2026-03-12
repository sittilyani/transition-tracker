<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_config.php';

// Only allow access to admins
if (!isset($_SESSION['user_id']) || $_SESSION['userrole'] !== 'Admin') {
    die("Access denied. Admin only.");
}

$result = null;
$test_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $result = testEmailConfiguration($test_email);
    } else {
        $result = ['success' => false, 'message' => 'Invalid email address'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Configuration</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body { background: #f4f7fc; padding: 50px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card-header { background: #0D1A63; color: white; border-radius: 15px 15px 0 0 !important; padding: 20px; }
        .card-body { padding: 30px; }
        .alert { border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Test Email Configuration</h3>
            </div>
            <div class="card-body">
                <?php if ($result): ?>
                    <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?>">
                        <strong><?php echo $result['success'] ? '? Success!' : '? Error!'; ?></strong><br>
                        <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="test_email"
                               value="<?php echo htmlspecialchars($test_email); ?>" required>
                        <small class="text-muted">Enter an email address to receive a test login email</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                    <a href="userslist.php" class="btn btn-secondary">Back to Users</a>
                </form>

                <hr class="my-4">

                <div class="alert alert-info">
                    <strong>Current Email Settings:</strong><br>
                    Host: mail.the-touch-haven-investments.store<br>
                    Port: 465 (SSL) or 587 (TLS)<br>
                    Username: admin@the-touch-haven-investments.store<br>
                    From: admin@the-touch-haven-investments.store
                </div>
            </div>
        </div>
    </div>
</body>
</html>