<?php
session_start();
include('../includes/config.php');

// Include Composer autoloader and email config
require_once __DIR__ . '/../../vendor/autoload.php';
require_once('../includes/email_config.php');

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

    // Get staff details from county_staff table
    $staff_stmt = $conn->prepare("SELECT * FROM county_staff WHERE id_number = ? AND status = 'active'");
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

    // Generate random password
    $generated_password = generateRandomPassword(12); // 12 characters long
    $hashed_password = password_hash($generated_password, PASSWORD_BCRYPT);

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

    // Create full name
    $full_name = trim($staff['first_name'] . ' ' . $staff['last_name'] . (!empty($staff['other_name']) ? ' ' . $staff['other_name'] : ''));

    // Check if email exists
    if (empty($staff['email'])) {
        $_SESSION['error_message'] = "Staff member does not have an email address. Cannot send login credentials.";
        header("Location: user_registration.php");
        exit;
    }

    // Insert user with prepared statement - including all fields
    $sql = "INSERT INTO tblusers (
        username,
        first_name,
        last_name,
        full_name,
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";

    $stmt = $conn->prepare($sql);
    $created_by = $_SESSION['full_name'] ?? 'Admin';

    $stmt->bind_param("ssssssssssss",
        $username,
        $staff['first_name'],
        $staff['last_name'],
        $full_name,
        $staff['email'],
        $hashed_password,
        $staff['sex'],
        $staff['staff_phone'],
        $staff['id_number'],
        $photo_blob,
        $userrole,
        $created_by
    );

    if ($stmt->execute()) {
        // Send email with login credentials
        $email_result = sendWelcomeEmail(
            $staff['email'],
            $full_name,
            $username,
            $generated_password
        );

        if ($email_result['success']) {
            $_SESSION['success_message'] = "User account created successfully for {$staff['first_name']} {$staff['last_name']}. Login credentials have been sent to {$staff['email']}";
        } else {
            // User created but email failed
            $_SESSION['success_message'] = "User account created successfully for {$staff['first_name']} {$staff['last_name']}. BUT email could not be sent. Please provide these credentials manually:";
            $_SESSION['warning_message'] = "Username: $username, Password: $generated_password";
        }

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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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

        .email-note {
            grid-column: span 2;
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 8px;
            color: #004085;
            border: 1px solid #b8daff;
            font-size: 13px;
        }

        .email-note i {
            margin-right: 5px;
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

                <?php if (isset($_SESSION['warning_message'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
                        <?php unset($_SESSION['warning_message']); ?>
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
                    <!-- Staff Preview -->
                    <div class="staff-preview">
                        <h4><i class="fas fa-user-check"></i> Staff Details Found</h4>
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
                        </div>
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

                        <!-- Email Notification Note -->
                        <div class="email-note">
                            <i class="fas fa-envelope"></i>
                            <strong>Email Notification:</strong> Login credentials will be sent to the staff member's email address:
                            <?php echo isset($staff_data['email']) && !empty($staff_data['email']) ? '<span style="color: #0D1A63; font-weight: 600;">' . htmlspecialchars($staff_data['email']) . '</span>' : '<span style="color: #dc3545;">No email address found!</span>'; ?>
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
                    <strong>Note:</strong> A random password will be generated and sent to the staff member's email. The user will be prompted to change it on first login.
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
</body>
</html>