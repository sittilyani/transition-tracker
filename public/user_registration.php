<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}

// Check if user has permission (Admin only)
if ($_SESSION['userrole'] !== 'Admin') {
    $_SESSION['error_message'] = "You don't have permission to add users.";
    header("Location: userslist.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "User Registration";
$staff_data = null;
$id_number = isset($_GET['id_number']) ? trim($_GET['id_number']) : '';

// If ID number is provided, fetch staff details
if (!empty($id_number) && isset($_GET['fetch'])) {
    $stmt = $conn->prepare("SELECT * FROM county_staff WHERE id_number = ? AND status = 'active'");
    $stmt->bind_param('s', $id_number);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $staff_data = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Staff with ID Number $id_number not found or inactive.";
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: user_registration.php");
        exit;
    }

    $id_number = trim($_POST['id_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $userrole = $_POST['userrole'] ?? '';

    // Validate required fields
    if (empty($id_number) || empty($username) || empty($userrole)) {
        $_SESSION['error_message'] = "ID Number, Username, and User Role are required";
        header("Location: user_registration.php");
        exit;
    }

    // Check if username already exists
    $check_sql = "SELECT user_id FROM tblusers WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Username already exists! Please choose a different username.";
        header("Location: user_registration.php");
        exit;
    }
    $check_stmt->close();

    // Check if ID number already has a user account
    $check_id_sql = "SELECT user_id FROM tblusers WHERE id_number = ?";
    $check_id_stmt = $conn->prepare($check_id_sql);
    $check_id_stmt->bind_param('s', $id_number);
    $check_id_stmt->execute();
    $check_id_result = $check_id_stmt->get_result();

    if ($check_id_result->num_rows > 0) {
        $_SESSION['error_message'] = "A user account already exists for ID Number $id_number.";
        header("Location: user_registration.php");
        exit;
    }
    $check_id_stmt->close();

    // Get staff details from county_staff table (just to verify existence, we don't need to store all fields)
    $staff_stmt = $conn->prepare("SELECT first_name, last_name, email, sex, staff_phone, photo FROM county_staff WHERE id_number = ? AND status = 'active'");
    $staff_stmt->bind_param('s', $id_number);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();

    if ($staff_result->num_rows === 0) {
        $_SESSION['error_message'] = "Staff with ID Number $id_number not found or inactive.";
        header("Location: user_registration.php");
        exit;
    }

    $staff = $staff_result->fetch_assoc();
    $staff_stmt->close();

    // Set default password and hash it
    $default_password = '123456';
    $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);

    // Handle photo from county_staff or upload
    $photo_blob = $staff['photo']; // Use existing photo from county_staff

    // Handle new photo upload if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_type = $_FILES['photo']['type'];
        $file_size = $_FILES['photo']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $photo_blob = file_get_contents($file_tmp);
        }
    }

    // Handle webcam photo
    if (isset($_POST['webcam_photo']) && !empty($_POST['webcam_photo'])) {
        $webcam_data = $_POST['webcam_photo'];
        $webcam_data = str_replace('data:image/jpeg;base64,', '', $webcam_data);
        $webcam_data = str_replace(' ', '+', $webcam_data);
        $image_data = base64_decode($webcam_data);

        if ($image_data !== false) {
            $photo_blob = $image_data;
        }
    }

    // Create full name (for display purposes only, not stored)
    $full_name = trim($staff['first_name'] . ' ' . $staff['last_name']);

    // Format mobile number to ensure it fits in database column
    $mobile = $staff['staff_phone'] ?? '';

    // If mobile number is too long, truncate it (assuming column is VARCHAR(15) or similar)
    if (strlen($mobile) > 15) {
        $mobile = substr($mobile, 0, 15);
    }

    // Insert user with ONLY essential fields for authentication
    // All other staff information will be accessed via JOIN with county_staff using id_number
    $sql = "INSERT INTO tblusers (
        username,
        first_name,
        last_name,
        email,
        password,
        sex,
        mobile,
        id_number,
        photo,
        userrole,
        status,
        date_created,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";

    $stmt = $conn->prepare($sql);
    $created_by = $_SESSION['full_name'] ?? 'Admin';

    $stmt->bind_param("sssssssssss",
        $username,              // 1
        $staff['first_name'],   // 2
        $staff['last_name'],    // 3
        $staff['email'],        // 4
        $hashed_password,       // 5
        $staff['sex'],          // 6
        $mobile,                // 7
        $id_number,             // 8
        $photo_blob,            // 9
        $userrole,              // 10
        $created_by             // 11
    );

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User account created successfully for {$staff['first_name']} {$staff['last_name']}. Default password is 123456.";
        header("Location: userslist.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Registration failed: " . $stmt->error;
        header("Location: user_registration.php");
        exit;
    }

    $stmt->close();
}

// Get user roles
$roles_result = $conn->query("SELECT role FROM userroles ORDER BY role");
$roles = [];
while ($row = $roles_result->fetch_assoc()) {
    $roles[] = $row['role'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .card-header {
            background: #0d1a63;
            color: white;
            padding: 25px 30px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .card-header h2 i {
            margin-right: 10px;
        }

        .card-body {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
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

        .form-control, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .staff-preview {
            grid-column: span 2;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }

        .staff-preview h4 {
            color: #0d1a63;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .preview-item {
            font-size: 13px;
        }

        .preview-item strong {
            color: #666;
            display: block;
            font-size: 11px;
            text-transform: uppercase;
        }

        .preview-item span {
            color: #333;
            font-weight: 500;
        }

        .photo-section {
            grid-column: span 2;
            display: flex;
            gap: 30px;
            align-items: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .current-photo {
            text-align: center;
        }

        .current-photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .photo-preview {
            text-align: center;
        }

        #photo-preview-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            display: none;
            margin: 0 auto 10px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #0d1a63;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .id-search {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .id-search .form-control {
            flex: 1;
        }

        .id-search .btn {
            padding: 12px 25px;
        }

        .photo-hint {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }

        .info-box i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .preview-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars($page_title); ?></h2>
            </div>

            <div class="card-body">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <!-- ID Number Search Form -->
                <form method="GET" action="" class="id-search">
                    <input type="text" name="id_number" class="form-control"
                           placeholder="Enter ID Number to fetch staff details"
                           value="<?php echo htmlspecialchars($id_number); ?>" required>
                    <button type="submit" name="fetch" class="btn btn-info">
                        <i class="fas fa-search"></i> Fetch Details
                    </button>
                    <?php if ($staff_data): ?>
                        <a href="user_registration.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>

                <?php if ($staff_data): ?>
                    <!-- Staff Preview - Shows all staff details for confirmation -->
                    <div class="staff-preview">
                        <h4><i class="fas fa-user-check"></i> Staff Details Found - Confirmation Only</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <strong>Full Name</strong>
                                <span><?php echo htmlspecialchars($staff_data['first_name'] . ' ' . $staff_data['last_name'] . (!empty($staff_data['other_name']) ? ' ' . $staff_data['other_name'] : '')); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>ID Number</strong>
                                <span><?php echo htmlspecialchars($staff_data['id_number']); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Email</strong>
                                <span><?php echo htmlspecialchars($staff_data['email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Phone</strong>
                                <span><?php echo htmlspecialchars($staff_data['staff_phone'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Sex</strong>
                                <span><?php echo htmlspecialchars($staff_data['sex'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Facility</strong>
                                <span><?php echo htmlspecialchars($staff_data['facility_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Department</strong>
                                <span><?php echo htmlspecialchars($staff_data['department_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Cadre</strong>
                                <span><?php echo htmlspecialchars($staff_data['cadre_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>County</strong>
                                <span><?php echo htmlspecialchars($staff_data['county_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Subcounty</strong>
                                <span><?php echo htmlspecialchars($staff_data['subcounty_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Level of Care</strong>
                                <span><?php echo htmlspecialchars($staff_data['level_of_care_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Staff Status</strong>
                                <span><?php echo htmlspecialchars($staff_data['staff_status'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="preview-item">
                                <strong>Employment Status</strong>
                                <span><?php echo htmlspecialchars($staff_data['employment_status'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box explaining what will be stored -->
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Only essential login information will be stored in the users table.
                        All other staff details will be linked via ID Number and accessed from the staff records when needed.
                        The information above is shown for confirmation purposes only.
                    </div>
                <?php endif; ?>

                <form method="post" action="user_registration.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="webcam_photo" id="webcam_photo">
                    <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($staff_data['id_number'] ?? $id_number); ?>">

                    <div class="form-grid">
                        <!-- ID Number (Read-only) -->
                        <div class="form-group full-width">
                            <label>ID Number <i>*</i></label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($staff_data['id_number'] ?? $id_number); ?>"
                                   readonly>
                            <small class="text-muted">ID Number cannot be edited as it's linked to staff records</small>
                        </div>

                        <!-- Username -->
                        <div class="form-group full-width">
                            <label>Username <i>*</i></label>
                            <input type="text" class="form-control" name="username"
                                   value="<?php echo isset($staff_data) ? strtolower($staff_data['first_name'] . '.' . $staff_data['last_name']) : ''; ?>"
                                   required>
                        </div>

                        <!-- User Role -->
                        <div class="form-group full-width">
                            <label>User Role <i>*</i></label>
                            <select class="form-control" name="userrole" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>">
                                        <?php echo htmlspecialchars($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Brief summary of what will be stored -->
                        <div class="form-group full-width" style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                            <h5 style="color: #0d1a63; margin-bottom: 10px;"><i class="fas fa-database"></i> Information to be stored in users table:</h5>
                            <ul style="margin-bottom: 0; color: #555;">
                                <li>Username, Password, User Role</li>
                                <li>Basic personal info: First Name, Last Name, Email, Sex, Mobile</li>
                                <li>ID Number (as link to staff records)</li>
                                <li>Photo (optional)</li>
                                <li>Account status and creation details</li>
                            </ul>
                            <p class="mt-2 mb-0 text-muted"><small>All other staff information remains in the staff database and is linked via ID Number.</small></p>
                        </div>

                        <!-- Photo Section -->
                        <div class="photo-section">
                            <div class="current-photo">
                                <?php if ($staff_data && !empty($staff_data['photo'])): ?>
                                    <img src="display_staff_photo.php?id_number=<?php echo urlencode($staff_data['id_number']); ?>"
                                         alt="Staff Photo"
                                         onerror="this.src='https://via.placeholder.com/120?text=No+Photo'">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/120?text=No+Photo" alt="No Photo">
                                <?php endif; ?>
                                <p>Staff Photo</p>
                            </div>

                            <div style="flex: 1;">
                                <div class="photo-preview">
                                    <img id="photo-preview-img" src="#" alt="New Photo Preview">
                                </div>

                                <div class="form-group">
                                    <label>Upload New Photo (Optional)</label>
                                    <input type="file" class="form-control" id="photo" name="photo"
                                           accept="image/jpeg,image/png,image/gif">
                                    <div class="photo-hint">
                                        <i class="fas fa-info-circle"></i> Max size: 5MB. Leave empty to use staff photo
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="userslist.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create User Account
                        </button>
                    </div>
                </form>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Default password is <strong>123456</strong>. User will be prompted to change it on first login.
                </div>
            </div>
        </div>
    </div>

    <script>
    // File input preview
    document.getElementById('photo').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photo-preview-img').src = e.target.result;
                document.getElementById('photo-preview-img').style.display = 'block';
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>