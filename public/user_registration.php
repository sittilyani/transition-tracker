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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: user_registration.php");
        exit;
    }

    // Sanitize and validate input
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $mobile = trim($_POST['mobile'] ?? '');
    $userrole = $_POST['userrole'] ?? '';

    // Validate required fields
    if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($sex) || empty($mobile) || empty($userrole)) {
        $_SESSION['error_message'] = "All fields except photo are required";
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

    // Handle photo upload as BLOB
    $photo_blob = null;

    // Handle file upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_type = $_FILES['photo']['type'];
        $file_size = $_FILES['photo']['size'];

        // Validate file type and size
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_message'] = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
            header("Location: user_registration.php");
            exit;
        }

        if ($file_size > $max_size) {
            $_SESSION['error_message'] = "File size exceeds 5MB limit.";
            header("Location: user_registration.php");
            exit;
        }

        // Read file content as BLOB
        $photo_blob = file_get_contents($file_tmp);
        // Don't escape here, we'll use prepared statement
    }

    // Handle webcam photo
    if (empty($error) && isset($_POST['webcam_photo']) && !empty($_POST['webcam_photo'])) {
        // Decode base64 image from webcam
        $webcam_data = $_POST['webcam_photo'];
        $webcam_data = str_replace('data:image/jpeg;base64,', '', $webcam_data);
        $webcam_data = str_replace(' ', '+', $webcam_data);
        $image_data = base64_decode($webcam_data);

        if ($image_data !== false) {
            $photo_blob = $image_data;
        } else {
            $_SESSION['error_message'] = "Failed to process webcam photo.";
            header("Location: user_registration.php");
            exit;
        }
    }

    // Set default password and hash it securely
    $default_password = '123456';
    $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);

    // Insert user with prepared statement to handle BLOB
    if ($photo_blob) {
        $sql = "INSERT INTO tblusers (username, first_name, last_name, email, password, sex, mobile, photo, userrole, status, date_created, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";

        $stmt = $conn->prepare($sql);
        $created_by = $_SESSION['full_name'] ?? 'Admin';

        // For BLOB data, we need to use 'b' type and send null for now
        $stmt->bind_param("ssssssssss",
            $username, $first_name, $last_name, $email, $hashed_password,
            $sex, $mobile, $photo_blob, $userrole, $created_by
        );

        // Now send the BLOB data using send_long_data
        if ($photo_blob) {
            $stmt->send_long_data(7, $photo_blob); // Index 7 is the photo column
        }
    } else {
        $sql = "INSERT INTO tblusers (username, first_name, last_name, email, password, sex, mobile, userrole, status, date_created, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";

        $stmt = $conn->prepare($sql);
        $created_by = $_SESSION['full_name'] ?? 'Admin';

        $stmt->bind_param("sssssssss",
            $username, $first_name, $last_name, $email, $hashed_password,
            $sex, $mobile, $userrole, $created_by
        );
    }

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User added successfully. Default password is 123456 - please change it after login.";
        header("Location: userslist.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Registration failed: " . $stmt->error;
        header("Location: user_registration.php");
        exit;
    }

    $stmt->close();
}

// Get genders
$gender_result = $conn->query("SELECT gender_name FROM tblgender ORDER BY gender_name");
$genders = [];
while ($row = $gender_result->fetch_assoc()) {
    $genders[] = $row['gender_name'];
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .photo-preview {
            grid-column: span 2;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }

        #photo-preview-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            display: none;
            margin: 0 auto 10px;
        }

        .webcam-container {
            grid-column: span 2;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }

        #video, #canvas {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border-radius: 10px;
            display: none;
        }

        #preview {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border-radius: 10px;
            display: none;
            border: 3px solid #667eea;
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

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .photo-hint {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
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

                <form method="post" action="user_registration.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="webcam_photo" id="webcam_photo">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username <i>*</i></label>
                            <input type="text" class="form-control" name="username" required>
                        </div>

                        <div class="form-group">
                            <label>First Name <i>*</i></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>

                        <div class="form-group">
                            <label>Last Name <i>*</i></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>

                        <div class="form-group">
                            <label>Email <i>*</i></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>

                        <div class="form-group">
                            <label>Sex <i>*</i></label>
                            <select class="form-control" name="sex" required>
                                <option value="">Select Sex</option>
                                <?php foreach ($genders as $gender): ?>
                                    <option value="<?php echo htmlspecialchars($gender); ?>">
                                        <?php echo htmlspecialchars($gender); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Mobile <i>*</i></label>
                            <input type="text" class="form-control" name="mobile" required>
                        </div>

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

                        <!-- Photo Preview -->
                        <div class="photo-preview">
                            <img id="photo-preview-img" src="#" alt="Preview">
                            <p>Photo Preview</p>
                        </div>

                        <div class="form-group">
                            <label>Upload Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                            <div class="photo-hint">
                                <i class="fas fa-info-circle"></i> Max size: 5MB. Allowed: JPG, PNG, GIF
                            </div>
                        </div>

                        <div class="webcam-container">
                            <label>Or Take a Photo</label>
                            <video id="video" width="400" height="300" autoplay></video>
                            <canvas id="canvas"></canvas>
                            <img id="preview" src="#" alt="Preview">
                            <div style="margin-top: 10px;">
                                <button type="button" id="start-webcam" class="btn btn-info btn-sm">
                                    <i class="fas fa-camera"></i> Start Webcam
                                </button>
                                <button type="button" id="capture-btn" class="btn btn-success btn-sm" style="display: none;">
                                    <i class="fas fa-camera-retro"></i> Capture Photo
                                </button>
                                <button type="button" id="retake-btn" class="btn btn-warning btn-sm" style="display: none;">
                                    <i class="fas fa-redo"></i> Retake
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="userslist.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Register User
                        </button>
                    </div>
                </form>
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
                document.getElementById('preview').style.display = 'none';
                document.getElementById('video').style.display = 'none';
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Webcam capture functionality
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const preview = document.getElementById('preview');
    const startWebcamBtn = document.getElementById('start-webcam');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const webcamPhotoInput = document.getElementById('webcam_photo');
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photo-preview-img');

    let stream = null;

    startWebcamBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            video.style.display = 'block';
            captureBtn.style.display = 'inline-block';
            startWebcamBtn.style.display = 'none';
            retakeBtn.style.display = 'none';
            preview.style.display = 'none';
            photoPreview.style.display = 'none';
            photoInput.value = ''; // Clear file input
        } catch (err) {
            alert('Error accessing webcam: ' + err.message);
        }
    });

    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg');
        preview.src = dataUrl;
        preview.style.display = 'block';
        webcamPhotoInput.value = dataUrl;
        video.style.display = 'none';
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-block';
        photoInput.value = ''; // Clear file input

        // Stop webcam stream
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });

    retakeBtn.addEventListener('click', () => {
        startWebcamBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        preview.style.display = 'none';
        webcamPhotoInput.value = '';
    });

    // Clear webcam photo if file input is used
    photoInput.addEventListener('change', () => {
        if (photoInput.files.length > 0) {
            webcamPhotoInput.value = '';
            preview.style.display = 'none';
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.style.display = 'none';
                captureBtn.style.display = 'none';
                startWebcamBtn.style.display = 'inline-block';
            }
        }
    });
    </script>
</body>
</html>