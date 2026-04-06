<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

$error = '';
$success = '';

// Get logged-in user for created_by field
$created_by = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $professional_body_name = mysqli_real_escape_string($conn, trim($_POST['professional_body_name']));

    // Validate data
    if (empty($professional_body_name)) {
        $error = "Please enter the professional body name.";
    } else {
        // Check if professional body already exists
        $check_sql = "SELECT * FROM professional_bodies WHERE professional_body_name = '$professional_body_name'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error = "This professional body already exists.";
        } else {
            // Insert data into professional_bodies table
            $sql = "INSERT INTO professional_bodies (professional_body_name, create_by, date_created)
                    VALUES ('$professional_body_name', '$created_by', NOW())";

            if ($conn->query($sql) === TRUE) {
                $_SESSION['success_msg'] = 'Professional body added successfully!';
                header('Location: add_professional_body.php?success=1');
                exit();
            } else {
                $error = "Error: " . $sql . "<br>" . $conn->error;
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
    <title>Add Professional Body - Transition Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f7;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a3a9e 100%);
            color: #fff;
            padding: 22px 30px;
            border-radius: 14px;
            margin-bottom: 30px;
            box-shadow: 0 6px 24px rgba(13,26,99,.25);
        }

        .page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 5px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #0D1A63;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }

        .back-link:hover {
            background: #e8edf8;
            transform: translateX(-2px);
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Form Card */
        .form-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(90deg, #f8fafc, #fff);
            padding: 20px 25px;
            border-bottom: 1px solid #e8ecf5;
        }

        .form-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0D1A63;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-body {
            padding: 25px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 6px;
            color: #0D1A63;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e4f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all .2s;
            background: #fff;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }

        /* Button */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #0D1A63;
            color: #fff;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #1a2a7a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13,26,99,.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #666;
            flex: 1;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Required Field */
        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        small {
            color: #999;
            font-size: 11px;
            display: block;
            margin-top: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: #999;
            font-size: 12px;
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 3px solid #0D1A63;
        }

        .info-box p {
            font-size: 12px;
            color: #666;
            margin-bottom: 0;
        }

        .info-box i {
            color: #0D1A63;
            margin-right: 8px;
        }

        /* Professional Bodies List Preview */
        .preview-list {
            margin-top: 20px;
            border-top: 1px solid #e8ecf5;
            padding-top: 20px;
        }

        .preview-list h4 {
            font-size: 13px;
            color: #0D1A63;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-items {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .preview-tag {
            background: #e8edf8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #0D1A63;
            font-weight: 500;
        }

        /* Created By Info */
        .creator-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e8ecf5;
        }

        .creator-icon {
            width: 40px;
            height: 40px;
            background: #0D1A63;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
        }

        .creator-details {
            flex: 1;
        }

        .creator-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }

        .creator-name {
            font-size: 14px;
            font-weight: 600;
            color: #0D1A63;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="page-header">
        <h1>
            <i class="fas fa-id-card"></i>
            Add Professional Body
        </h1>
        <p>Add a new professional body or regulatory organization</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Professional body added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_professional_body.php";
        }, 2000);
    </script>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <h2>
                <i class="fas fa-plus-circle"></i>
                Professional Body Information
            </h2>
        </div>
        <div class="form-body">
            <!-- Created By Info -->
            <div class="creator-info">
                <div class="creator-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="creator-details">
                    <div class="creator-label">Record will be created by</div>
                    <div class="creator-name"><?= htmlspecialchars($created_by) ?></div>
                </div>
            </div>

            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Professional Body Name <span class="required">*</span></label>
                    <input type="text" name="professional_body_name" class="form-control"
                           placeholder="e.g., Kenya Medical Practitioners and Dentists Council (KMPDC), Nursing Council of Kenya (NCK), Clinical Officers Council (COC)" required>
                    <small>Full name of the professional body or regulatory organization</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Professional Body
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Professional bodies are regulatory organizations that oversee and license healthcare professionals.
                    Examples: Kenya Medical Practitioners and Dentists Council (KMPDC), Nursing Council of Kenya (NCK),
                    Clinical Officers Council (COC), Kenya Pharmaceutical Association (KPA), etc.
                </p>
            </div>

            <div class="preview-list">
                <h4>
                    <i class="fas fa-list"></i>
                    Existing Professional Bodies
                </h4>
                <div class="preview-items">
                    <?php
                    // Fetch existing professional bodies for preview
                    $preview_sql = "SELECT professional_body_name FROM professional_bodies ORDER BY professional_body_name LIMIT 15";
                    $preview_result = $conn->query($preview_sql);
                    if ($preview_result && $preview_result->num_rows > 0) {
                        while ($row = $preview_result->fetch_assoc()) {
                            echo '<span class="preview-tag">' . htmlspecialchars($row['professional_body_name']) . '</span>';
                        }
                    } else {
                        echo '<p style="color: #999; font-size: 12px;">No professional bodies added yet.</p>';
                    }
                    ?>
                </div>
                <?php if ($preview_result && $preview_result->num_rows >= 15): ?>
                <p style="font-size: 11px; color: #999; margin-top: 10px;">
                    <i class="fas fa-ellipsis-h"></i> Showing first 15 professional bodies
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add Professional Body
    </div>
</div>
</body>
</html>