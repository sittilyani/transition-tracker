<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Check if user has verification permission (Admin or Training Coordinator)
$can_verify = in_array($_SESSION['userrole'], ['Admin', 'Training Coordinator', 'HR Manager']);

if (!$can_verify) {
    $_SESSION['error_msg'] = "You don't have permission to verify trainings.";
    header('Location: view_staff_trainings.php');
    exit();
}

$training_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

if ($training_id === 0 || !in_array($action, ['verify', 'reject'])) {
    $_SESSION['error_msg'] = "Invalid request.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Fetch training details
$query = "SELECT sst.*, cs.first_name, cs.last_name
          FROM staff_self_trainings sst
          JOIN county_staff cs ON sst.staff_id = cs.staff_id
          WHERE sst.self_training_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $training_id);
$stmt->execute();
$result = $stmt->get_result();
$training = $result->fetch_assoc();
$stmt->close();

if (!$training) {
    $_SESSION['error_msg'] = "Training record not found.";
    header('Location: view_staff_trainings.php');
    exit();
}

if ($training['status'] != 'submitted') {
    $_SESSION['error_msg'] = "Only submitted trainings can be verified.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Handle verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remarks = $_POST['remarks'] ?? '';
    $new_status = ($action == 'verify') ? 'verified' : 'rejected';

    $update_sql = "UPDATE staff_self_trainings SET
                   status = ?,
                   verified_by = ?,
                   verification_date = NOW(),
                   verification_remarks = ?
                   WHERE self_training_id = ?";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param(
        "sssi",
        $new_status,
        $_SESSION['full_name'],
        $remarks,
        $training_id
    );

    if ($update_stmt->execute()) {
        $_SESSION['success_msg'] = "Training " . ($action == 'verify' ? 'verified' : 'rejected') . " successfully!";
    } else {
        $_SESSION['error_msg'] = "Error updating training status.";
    }

    header('Location: view_staff_trainings.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Training</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header {
            background: #0D1A63;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 20px 0;
            min-height: 100px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h2><i class="fas fa-<?php echo $action == 'verify' ? 'check-circle' : 'times-circle'; ?>"></i>
                    <?php echo ucfirst($action); ?> Training</h2>
            </div>

            <p><strong>Staff:</strong> <?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></p>
            <p><strong>Course:</strong> <?php echo htmlspecialchars($training['course_name']); ?></p>
            <p><strong>Dates:</strong> <?php echo date('d/m/Y', strtotime($training['start_date'])) . ' - ' . date('d/m/Y', strtotime($training['end_date'])); ?></p>

            <form method="POST">
                <label for="remarks"><?php echo $action == 'verify' ? 'Verification' : 'Rejection'; ?> Remarks:</label>
                <textarea name="remarks" id="remarks" placeholder="Enter any remarks..."></textarea>

                <button type="submit" class="btn btn-<?php echo $action == 'verify' ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo $action == 'verify' ? 'check' : 'times'; ?>"></i>
                    Confirm <?php echo ucfirst($action); ?>
                </button>
                <a href="view_staff_trainings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </form>
        </div>
    </div>
</body>
</html>