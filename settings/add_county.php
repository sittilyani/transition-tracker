<?php
// Include the database configuration file at the top
include '../includes/config.php';
include '../includes/session_check.php';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $countyCode = trim($_POST["county_code"]);
    $countyName = trim($_POST["county_name"]);
    $region = trim($_POST["region"]);

    // Prepare an insert statement
    $sql = "INSERT INTO counties (county_code, county_name, region) VALUES (?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("sss", $countyCode, $countyName, $region);

        // Execute the prepared statement
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = 'County added successfully!';
            header('Location: add_county.php?success=1');
            exit();
        } else {
            $error = "Something went wrong. Please try again later.";
        }

        // Close statement
        $stmt->close();
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add County - Transition Tracker</title>
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
            margin-bottom: 20px;
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

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e4f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all .2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }

        /* Button */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
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

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: #999;
            font-size: 12px;
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
            <i class="fas fa-map-marker-alt"></i>
            Add New County
        </h1>
        <p>Add a new county to the system for transition benchmarking</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> County added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_county.php";
        }, 2000);
    </script>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <h2>
                <i class="fas fa-plus-circle"></i>
                County Information
            </h2>
        </div>
        <div class="form-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label><i class="fas fa-code"></i> County Code <span class="required">*</span></label>
                    <input type="text" name="county_code" class="form-control" placeholder="e.g., 001, 002" required>
                    <small style="color: #666; font-size: 11px;">Unique identifier for the county</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-pin"></i> County Name <span class="required">*</span></label>
                    <input type="text" name="county_name" class="form-control" placeholder="e.g., Nairobi, Mombasa" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-globe"></i> Region <span class="required">*</span></label>
                    <input type="text" name="region" class="form-control" placeholder="e.g., Central, Coast, Rift Valley" required>
                    <small style="color: #666; font-size: 11px;">Geographic region of the county</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save County
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add County
    </div>
</div>
</body>
</html>