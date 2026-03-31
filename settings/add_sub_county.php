<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sub_county_name = mysqli_real_escape_string($conn, trim($_POST['sub_county_name']));
    $county_name = mysqli_real_escape_string($conn, trim($_POST['county_name']));

    // Validate data
    if (empty($sub_county_name) || empty($county_name)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if sub-county already exists
        $check_sql = "SELECT * FROM sub_counties WHERE sub_county_name = '$sub_county_name' AND county_name = '$county_name'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error = "This sub-county already exists in the selected county.";
        } else {
            // Insert data into sub_counties table
            $sql = "INSERT INTO sub_counties (sub_county_name, county_name) VALUES ('$sub_county_name', '$county_name')";

            if ($conn->query($sql) === TRUE) {
                $_SESSION['success_msg'] = 'Sub-county added successfully!';
                header('Location: add_sub_county.php?success=1');
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
    <title>Add Sub-County - Transition Tracker</title>
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
            Add New Sub-County
        </h1>
        <p>Add a new sub-county to the system for facility location mapping</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Sub-county added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_sub_county.php";
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
                Sub-County Information
            </h2>
        </div>
        <div class="form-body">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Sub-County Name <span class="required">*</span></label>
                    <input type="text" id="sub_county_name" name="sub_county_name" class="form-control"
                           placeholder="e.g., Embakasi, Kasarani, Kilifi North" required>
                    <small>Official name of the sub-county</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-pin"></i> County Name <span class="required">*</span></label>
                    <select id="county_name" name="county_name" class="form-select" required>
                        <option value="">Select County</option>
                        <?php
                        // Fetch counties from the database
                        $sql = "SELECT county_name FROM counties ORDER BY county_name";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['county_name']) . "'>" . htmlspecialchars($row['county_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <small>Select the county where this sub-county is located</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Sub-County
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add Sub-County
    </div>
</div>
</body>
</html>