<?php
session_start();
include '../includes/config.php';

$msg = "";
$error = "";

// Check if ID parameter exists
if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {
    header("Location: update_staff.php");
    exit();
}

$staff_id = (int)$_GET['staff_id'];

// Fetch existing staff data
$staff_query = mysqli_query($conn, "SELECT * FROM county_staff WHERE staff_id = $staff_id");
if (mysqli_num_rows($staff_query) == 0) {
    header("Location: update_staff.php");
    exit();
}
$staff = mysqli_fetch_assoc($staff_query);

// Handle form submission
if (isset($_POST['submit'])) {
    // Get form data - use proper escaping for all fields
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $other_name = mysqli_real_escape_string($conn, $_POST['other_name']);
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $staff_phone = mysqli_real_escape_string($conn, $_POST['staff_phone']);
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Get facility details
    $facility_name = mysqli_real_escape_string($conn, $_POST['facility_name']);
    $county_name = mysqli_real_escape_string($conn, $_POST['county_name']);
    $subcounty_name = mysqli_real_escape_string($conn, $_POST['subcounty_name']);
    $level_of_care_name = mysqli_real_escape_string($conn, $_POST['level_of_care_name']);
    $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
    $cadre_name = mysqli_real_escape_string($conn, $_POST['cadre_name']);

    // Handle photo upload
    $photo_sql = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (in_array($_FILES['photo']['type'], $allowed_types) && $_FILES['photo']['size'] <= $max_size) {
            $photo_data = mysqli_real_escape_string($conn, file_get_contents($_FILES['photo']['tmp_name']));
            $photo_sql = ", photo = '$photo_data'";
        } else {
            $error = "Invalid photo format or size too large (max 5MB, JPG/PNG/GIF only)";
        }
    }

    // Update query
    if (empty($error)) {
        $update = "UPDATE county_staff SET
            first_name = '$first_name',
            last_name = '$last_name',
            other_name = '$other_name',
            sex = '$sex',
            staff_phone = '$staff_phone',
            id_number = '$id_number',
            email = '$email',
            facility_name = '$facility_name',
            county_name = '$county_name',
            subcounty_name = '$subcounty_name',
            level_of_care_name = '$level_of_care_name',
            department_name = '$department_name',
            cadre_name = '$cadre_name'
            $photo_sql
            WHERE staff_id = $staff_id";

        if (mysqli_query($conn, $update)) {
            $msg = "Staff updated successfully!";
            // Refresh staff data
            $staff_query = mysqli_query($conn, "SELECT * FROM county_staff WHERE staff_id = $staff_id");
            $staff = mysqli_fetch_assoc($staff_query);
        } else {
            $error = "Update Error: " . mysqli_error($conn);
        }
    }
}

// Fetch departments for dropdown
$departments = mysqli_query($conn, "SELECT department_name FROM departments ORDER BY department_name");

// Fetch cadres for dropdown
$cadres = mysqli_query($conn, "SELECT cadre_name FROM cadres ORDER BY cadre_name");

// Fetch facilities for dropdown
$facilities = mysqli_query($conn, "SELECT facility_name FROM facilities ORDER BY facility_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Staff</title>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Font Awesome for camera icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 70%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: #0D1A63;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h2 {
            font-size: 28px;
            font-weight: 600;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        label i {
            color: #ff4d4d;
            font-style: normal;
            margin-left: 3px;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
            color: #495057;
            border-color: #dee2e6;
        }

        /* Photo Section */
        .photo-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
        }

        .current-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .current-photo img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .current-photo p {
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }

        .photo-upload {
            text-align: center;
        }

        .camera-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #0D1A63;
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 16px;
        }

        .camera-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .camera-btn i {
            font-size: 20px;
        }

        #file-input {
            display: none;
        }

        .preview-container {
            margin-top: 20px;
            display: none;
        }

        .preview-container.show {
            display: block;
        }

        .preview-container img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 3px solid #667eea;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #0D1A63;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-cancel {
            display: inline-block;
            padding: 12px 25px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .header h2 {
                font-size: 24px;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✏️ Update Staff Member</h2>
        </div>

        <div class="content">
            <?php if ($msg): ?>
                <div class="alert success"><?php echo $msg; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="staffForm" enctype="multipart/form-data">
                <!-- Photo Section -->
                <div class="photo-section">
                    <div class="current-photo">
                        <?php if (!empty($staff['photo'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($staff['photo']); ?>" alt="Current Photo">
                            <p>Current Photo</p>
                        <?php else: ?>
                            <img src="https://via.placeholder.com/150?text=No+Photo" alt="No Photo">
                            <p>No photo available</p>
                        <?php endif; ?>
                    </div>

                    <div class="photo-upload">
                        <label for="file-input" class="camera-btn">
                            <i class="fas fa-camera"></i>
                            <?php echo !empty($staff['photo']) ? 'Change Photo' : 'Take/Upload Photo'; ?>
                        </label>
                        <input type="file" id="file-input" name="photo" accept="image/*" capture="environment">

                        <div class="preview-container" id="preview-container">
                            <p>New Photo Preview:</p>
                            <img id="image-preview" src="#" alt="Preview">
                        </div>
                        <p style="color: #666; font-size: 12px; margin-top: 10px;">
                            Max size: 5MB. Allowed: JPG, PNG, GIF
                        </p>
                    </div>
                </div>

                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-group">
                        <label>First Name <i>*</i></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name <i>*</i></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Other Name</label>
                        <input type="text" name="other_name" value="<?php echo !empty($staff['other_name']) ? htmlspecialchars($staff['other_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Sex <i>*</i></label>
                        <select name="sex" required>
                            <option value="">Select Sex</option>
                            <option value="Male" <?php echo ($staff['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($staff['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="staff_phone" value="<?php echo htmlspecialchars($staff['staff_phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" name="id_number" value="<?php echo htmlspecialchars($staff['id_number']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>">
                    </div>

                    <!-- Facility Information -->
                    <div class="form-group full-width">
                        <label>Facility <i>*</i></label>
                        <select name="facility_name" id="facility" required>
                            <option value="">Select Facility</option>
                            <?php while ($row = mysqli_fetch_assoc($facilities)): ?>
                                <option value="<?php echo htmlspecialchars($row['facility_name']); ?>"
                                    <?php echo ($staff['facility_name'] == $row['facility_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['facility_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>County</label>
                        <input type="text" name="county_name" id="county" value="<?php echo htmlspecialchars($staff['county_name']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Subcounty</label>
                        <input type="text" name="subcounty_name" id="subcounty" value="<?php echo htmlspecialchars($staff['subcounty_name']); ?>" readonly>
                    </div>

                    <div class="form-group full-width">
                        <label>Level of Care</label>
                        <input type="text" name="level_of_care_name" id="level" value="<?php echo htmlspecialchars($staff['level_of_care_name']); ?>" readonly>
                    </div>

                    <!-- Job Information -->
                    <div class="form-group">
                        <label>Department <i>*</i></label>
                        <select name="department_name" required>
                            <option value="">Select Department</option>
                            <?php
                            mysqli_data_seek($departments, 0);
                            while ($row = mysqli_fetch_assoc($departments)):
                            ?>
                                <option value="<?php echo htmlspecialchars($row['department_name']); ?>"
                                    <?php echo ($staff['department_name'] == $row['department_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['department_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cadre <i>*</i></label>
                        <select name="cadre_name" required>
                            <option value="">Select Cadre</option>
                            <?php
                            mysqli_data_seek($cadres, 0);
                            while ($row = mysqli_fetch_assoc($cadres)):
                            ?>
                                <option value="<?php echo htmlspecialchars($row['cadre_name']); ?>"
                                    <?php echo ($staff['cadre_name'] == $row['cadre_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['cadre_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="button-group">
                    <a href="manage_staff.php" class="btn-cancel">← Cancel</a>
                    <button type="submit" name="submit" class="btn-submit">
                        💾 Update Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Facility change handler
        $('#facility').change(function() {
            var facility_name = $(this).val();

            if (facility_name) {
                $.ajax({
                    url: 'get_facility_details.php',
                    type: 'POST',
                    data: {facility_name: facility_name},
                    dataType: 'json',
                    success: function(data) {
                        if (!data.error) {
                            $('#county').val(data.county_name);
                            $('#subcounty').val(data.subcounty_name);
                            $('#level').val(data.level_of_care_name);
                        } else {
                            alert('Error: ' + data.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error:', error);
                        alert('Error fetching facility details. Please check console.');
                    }
                });
            }
        });

        // Photo preview
        $('#file-input').change(function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#image-preview').attr('src', e.target.result);
                    $('#preview-container').addClass('show');
                }
                reader.readAsDataURL(file);
            }
        });

        // Trigger facility change if needed (uncomment if you want to reload details)
        // var selectedFacility = $('#facility').val();
        // if (selectedFacility) {
        //     $('#facility').trigger('change');
        // }
    });
    </script>
</body>
</html>