<?php
// Include the database configuration file at the top
include '../includes/config.php';
include '../includes/session_check.php';

$error = '';
$success = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $facilityname = trim($_POST["facilityname"]);
    $mflcode = trim($_POST["mflcode"]);
    $countyname = trim($_POST["countyname"]);
    $subcountyname = trim($_POST["subcountyname"]);
    $owner = trim($_POST["owner"]);
    $sdp = trim($_POST["sdp"]);
    $agency = trim($_POST["agency"]);
    $emr = trim($_POST["emr"]);
    $emrstatus = trim($_POST["emrstatus"]);
    $infrastructuretype = trim($_POST["infrastructuretype"]);
    $latitude = trim($_POST["latitude"]);
    $longitude = trim($_POST["longitude"]);

    // Prepare an insert statement
    $sql = "INSERT INTO facilities (facilityname, mflcode, countyname, subcountyname, owner, sdp, agency, emr, emrstatus, infrastructuretype, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssssssss", $facilityname, $mflcode, $countyname, $subcountyname, $owner, $sdp, $agency, $emr, $emrstatus, $infrastructuretype, $latitude, $longitude);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = 'Facility added successfully!';
            header('Location: add_facility.php?success=1');
            exit();
        } else {
            $error = "Something went wrong. Please try again later.";
        }

        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Facility - Transition Tracker</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 3;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 12px;
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

        small {
            color: #999;
            font-size: 10px;
            display: block;
            margin-top: 4px;
        }

        /* Required Field */
        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full-width {
                grid-column: span 2;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
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
            <i class="fas fa-hospital"></i>
            Add New Facility
        </h1>
        <p>Add a new health facility to the system for transition assessment</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Facility added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_facility.php";
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
                Facility Information
            </h2>
        </div>
        <div class="form-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Facility Name <span class="required">*</span></label>
                        <input type="text" name="facilityname" class="form-control" placeholder="e.g., Mbagathi Hospital" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-qrcode"></i> MFL Code <span class="required">*</span></label>
                        <input type="number" name="mflcode" class="form-control" placeholder="e.g., 12345" required>
                        <small>Master Facility List Code</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> County <span class="required">*</span></label>
                        <select name="countyname" class="form-select" required>
                            <option value="">Select County</option>
                            <?php
                            $sql = "SELECT county_name FROM counties ORDER BY county_name";
                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['county_name']) . "'>" . htmlspecialchars($row['county_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-location-dot"></i> Sub-County <span class="required">*</span></label>
                        <select name="subcountyname" class="form-select" required>
                            <option value="">Select Sub-County</option>
                            <?php
                            $sql = "SELECT sub_county_name FROM sub_counties ORDER BY sub_county_name";
                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['sub_county_name']) . "'>" . htmlspecialchars($row['sub_county_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-handshake"></i> Ownership</label>
                        <input type="text" name="owner" class="form-control" placeholder="e.g., MOH, FBO, Private">
                        <small>e.g., Ministry of Health, Faith Based Organization</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Service Delivery Partner</label>
                        <input type="text" name="sdp" class="form-control" placeholder="e.g., Stawisha Pwani, ICRH">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-flag-checkered"></i> Agency</label>
                        <input type="text" name="agency" class="form-control" placeholder="e.g., CDC, USAID, PEPFAR">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-laptop-medical"></i> TaifaCare Type</label>
                        <input type="text" name="emr" class="form-control" placeholder="e.g., KenyaEMR, AfyaKE, Tiberbu">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-chart-line"></i> TaifaCare Status</label>
                        <input type="text" name="emrstatus" class="form-control" placeholder="e.g., Active, Standalone, Inactive">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-cloud"></i> Infrastructure Type</label>
                        <input type="text" name="infrastructuretype" class="form-control" placeholder="e.g., Local, Cloud-based">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Latitude</label>
                        <input type="text" name="latitude" class="form-control" placeholder="e.g., -1.2921">
                        <small>Geographic coordinate</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Longitude</label>
                        <input type="text" name="longitude" class="form-control" placeholder="e.g., 36.8219">
                        <small>Geographic coordinate</small>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Facility
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add Facility
    </div>
</div>
</body>
</html>