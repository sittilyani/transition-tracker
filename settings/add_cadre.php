<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cadre_name = mysqli_real_escape_string($conn, trim($_POST['cadre_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    // Validate data
    if (empty($cadre_name)) {
        $error = "Please enter the cadre name.";
    } else {
        // Check if cadre already exists
        $check_sql = "SELECT * FROM cadres WHERE cadre_name = '$cadre_name'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error = "This cadre already exists.";
        } else {
            // Insert data into cadres table
            $sql = "INSERT INTO cadres (cadre_name, description, date_created)
                    VALUES ('$cadre_name', '$description', NOW())";

            if ($conn->query($sql) === TRUE) {
                $_SESSION['success_msg'] = 'Cadre added successfully!';
                header('Location: add_cadre.php?success=1');
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
    <title>Add Cadre - Transition Tracker</title>
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

        .form-control, .form-select, textarea.form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e4f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all .2s;
            background: #fff;
        }

        .form-control:focus, .form-select:focus, textarea.form-control:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
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

        /* Cadre List Preview */
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
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="page-header">
        <h1>
            <i class="fas fa-user-tag"></i>
            Add New Cadre
        </h1>
        <p>Add a new professional cadre for staff classification</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Cadre added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_cadre.php";
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
                Cadre Information
            </h2>
        </div>
        <div class="form-body">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-briefcase"></i> Cadre Name <span class="required">*</span></label>
                    <input type="text" name="cadre_name" class="form-control"
                           placeholder="e.g., Clinical Officer, Nurse, Medical Specialist, Lab Technician" required>
                    <small>Professional designation or job category</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" class="form-control"
                              placeholder="Brief description of this cadre's roles, responsibilities, and qualifications..."></textarea>
                    <small>Optional: Provide a brief description of the cadre</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Cadre
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Cadres are used to classify staff by their professional qualifications and job roles.
                    Examples: Clinical Officer, Registered Nurse, Laboratory Technician, Pharmaceutical Technologist, etc.
                </p>
            </div>

            <div class="preview-list">
                <h4>
                    <i class="fas fa-list"></i>
                    Existing Cadres
                </h4>
                <div class="preview-items">
                    <?php
                    // Fetch existing cadres for preview
                    $preview_sql = "SELECT cadre_name FROM cadres ORDER BY cadre_name LIMIT 10";
                    $preview_result = $conn->query($preview_sql);
                    if ($preview_result && $preview_result->num_rows > 0) {
                        while ($row = $preview_result->fetch_assoc()) {
                            echo '<span class="preview-tag">' . htmlspecialchars($row['cadre_name']) . '</span>';
                        }
                    } else {
                        echo '<p style="color: #999; font-size: 12px;">No cadres added yet.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add Cadre
    </div>
</div>
</body>
</html>