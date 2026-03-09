<?php
ob_start();
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$user_id = (int)$user_id;

// Restrict non-admins to their own profile
if ($_SESSION['userrole'] !== 'Admin' && $_SESSION['user_id'] != $user_id) {
    header("Location: ../public/login.php?error=access_denied");
    exit();
}

// Fetch user
$sql = "SELECT * FROM tblusers WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->num_rows > 0 ? $result->fetch_assoc() : null;

if (!$user) {
    header("Location: userslist.php?error=user_not_found");
    exit();
}

// Default photo path
$default_photo = '../assets/images/LOGO_HEALTH_PNG-removebg-preview.png';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        // Sanitize inputs
        $username       = trim($_POST['username'] ?? '');
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $gender         = $_POST['gender'] ?? '';
        $mobile         = trim($_POST['mobile'] ?? '');
        $cadre          = $_POST['cadre'] ?? '';
        $department     = $_POST['department'] ?? '';
        $position       = $_POST['position'] ?? '';
        $supervisor     = $_POST['supervisor'] ?? '';
        $facilityname   = $_POST['facilityname'] ?? '';
        $mflcode        = $_POST['mflcode'] ?? '';
        $subcountyname  = $_POST['subcountyname'] ?? '';
        $countyname     = $_POST['countyname'] ?? '';
        $userrole       = $_SESSION['userrole'] === 'Admin' ? ($_POST['userrole'] ?? $user['userrole']) : $user['userrole'];

        if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($facilityname)) {
            $error = "Required fields are missing.";
        } else {
            $photo = $user['photo'];

            // Handle photo upload
            $upload_dir = '../photos/users/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                $file = $_FILES['photo'];
                if (!in_array($file['type'], $allowed) || $file['size'] > 5 * 1024 * 1024) {
                    $error = "Invalid file. Use JPEG/PNG/GIF, max 5MB.";
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = preg_replace('/[^a-zA-Z0-9_-]/,]/', '_', $first_name . '_' . $last_name) . "_{$user_id}_" . date('Ymd') . ".{$ext}";
                    $filepath = $upload_dir . $filename;

                    // Delete old photo
                    if ($photo && file_exists($upload_dir . $photo)) {
                        unlink($upload_dir . $photo);
                    }

                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $photo = $filename;
                    } else {
                        $error = "Failed to upload photo.";
                    }
                }
            }
            // Handle webcam
            elseif (!empty($_POST['webcam_photo'])) {
                $data = $_POST['webcam_photo'];
                if (preg_match('/^data:image\/(jpeg|png);base64,/', $data)) {
                    $data = preg_replace('/^data:image\/(jpeg|png);base64,/', '', $data);
                    $data = str_replace(' ', '+', $data);
                    $image = base64_decode($data);
                    if ($image !== false) {
                        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $first_name . '_' . $last_name) . "_{$user_id}_" . date('Ymd') . ".jpg";
                        $filepath = $upload_dir . $filename;

                        if ($photo && file_exists($upload_dir . $photo)) unlink($upload_dir . $photo);
                        if (file_put_contents($filepath, $image)) {
                            $photo = $filename;
                        } else {
                            $error = "Failed to save webcam photo.";
                        }
                    }
                }
            }

            if (!isset($error)) {
                $sql = "UPDATE tblusers SET
                    username=?, first_name=?, last_name=?, email=?, gender=?, mobile=?,
                    cadre=?, department=?, position=?, supervisor=?, facilityname=?, level_of_care=?, owner=?,
                    mflcode=?, subcountyname=?, countyname=?, userrole=?, photo=?
                    WHERE user_id=?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssisssssisissssi",
                    $username, $first_name, $last_name, $email, $gender, $mobile,
                    $cadre, $department, $position, $supervisor, $facilityname, $level_of_care, $owner,
                    $mflcode, $subcountyname, $countyname, $userrole, $photo, $user_id
                );

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Profile updated successfully.";
                    header("Location: profile.php?user_id=$user_id");
                    exit();
                } else {
                    $error = "Update failed: " . $stmt->error;
                }
            }
        }
    }
    $_SESSION['error_message'] = $error ?? "Unknown error.";
    header("Location: profile.php?user_id=$user_id");
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?= htmlspecialchars($user['full_name'] ?? '') ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/fontawesome/css/font-awesome.min.css" type="text/css">
    <style>
        :root {
            --primary: #4B0082;
            --primary-dark: #3a0066;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #dee2e6;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            font-family: 'Segoe UI', sans-serif;
            color: #333;
        }
        .profile-card {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-header {
            background: var(--primary);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .form-container {
            padding: 30px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
        }
        .form-control, select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-control:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(75, 0, 130, 0.2);
        }
        .readonly {
            background: #e9ecef;
            cursor: not-allowed;
        }
        .btn-submit {
            background: var(--primary);
            color: white;
            padding: 14px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            text-transform: uppercase;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .webcam-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        #video, #canvas, #preview {
            width: 100%;
            max-height: 200px;
            border-radius: 6px;
        }
        .btn-webcam {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="profile-card">
    <div class="profile-header">
        <?php if(!empty($_SESSION['photo'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo $_SESSION['photo']; ?>" class="photo-thumb" alt="User Photo">
                        <?php else: ?>
                            <img src="<?php echo $default_photo; ?>" class="photo-thumb" alt="Default Photo">
                        <?php endif; ?>
        <h3><?= htmlspecialchars($user['username']) ?></h3>
        <p><strong><?= htmlspecialchars($user['userrole']) ?></strong></p>
    </div>

    <div class="form-container">

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="webcam_photo" id="webcam_photo">

            <div class="form-grid">

                <!-- Username -->
                <div class="form-group">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>

                <!-- First Name -->
                <div class="form-group">
                    <label>First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label>Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label>Gender <span class="text-danger">*</span></label>
                    <select class="form-control" name="gender" required>
                        <option value="">-- Select --</option>
                        <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <!-- Mobile -->
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" class="form-control" name="mobile" value="<?= htmlspecialchars($user['mobile'] ?? '') ?>">
                </div>

                <!-- Cadre -->
                <div class="form-group">
                    <label>Cadre</label>
                    <select class="form-control" name="cadre">
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT cadrename FROM cadres ORDER BY cadrename");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['cadre'] ?? '') === $row['cadrename'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['cadrename']) . "' $selected>" . htmlspecialchars($row['cadrename']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Department -->
                <div class="form-group">
                    <label>Department</label>
                    <select class="form-control" name="department">
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT departmentname FROM departments ORDER BY departmentname");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['department'] ?? '') === $row['departmentname'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['departmentname']) . "' $selected>" . htmlspecialchars($row['departmentname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Position -->
                <div class="form-group">
                    <label>Position</label>
                    <select class="form-control" name="position">
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT positionname FROM positions ORDER BY positionname");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['position'] ?? '') === $row['positionname'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['positionname']) . "' $selected>" . htmlspecialchars($row['positionname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Supervisor -->
                <div class="form-group">
                    <label>Supervisor</label>
                    <select class="form-control" name="supervisor">
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT full_name FROM tblusers WHERE full_name IS NOT NULL AND full_name != '' AND user_id != $user_id ORDER BY full_name");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['supervisor'] ?? '') === $row['full_name'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['full_name']) . "' $selected>" . htmlspecialchars($row['full_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Facility -->
                <div class="form-group">
                    <label>Facility <span class="text-danger">*</span></label>
                    <select class="form-control" name="facilityname" id="facilityname" required>
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT facilityname FROM facilities ORDER BY facilityname");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['facilityname'] ?? '') === $row['facilityname'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['facilityname']) . "' $selected>" . htmlspecialchars($row['facilityname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!--Level of Care-->
                <div class="form-group">
                    <label>Level Of Care<span class="text-danger"></span></label>
                    <select class="form-control" name="level_of_care" id="facilityname">
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT locname FROM level_of_care ORDER BY locname");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['locname'] ?? '') === $row['locname'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['locname']) . "' $selected>" . htmlspecialchars($row['locname']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Facility Ownership -->
                <div class="form-group">
                    <label>Facility Ownership<span class="text-danger">*</span></label>
                    <select class="form-control" name="ownername" id="ownername" required>
                        <option value="">-- Select --</option>
                        <?php
                        $res = $conn->query("SELECT ownername FROM ownership");
                        while ($row = $res->fetch_assoc()) {
                            $selected = ($user['ownername'] ?? '') === $row['ownername'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row['ownername']) . "' $selected>" . htmlspecialchars($row['ownername']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- MFL Code -->
                <div class="form-group">
                    <label>MFL Code</label>
                    <input type="text" class="form-control readonly" id="mflcode" name="mflcode" value="<?= htmlspecialchars($user['mflcode'] ?? '') ?>" readonly>
                </div>

                <!-- County -->
                <div class="form-group">
                    <label>County</label>
                    <input type="text" class="form-control readonly" id="countyname" name="countyname" value="<?= htmlspecialchars($user['countyname'] ?? '') ?>" readonly>
                </div>

                <!-- Sub-County -->
                <div class="form-group">
                    <label>Sub-County</label>
                    <input type="text" class="form-control readonly" id="subcountyname" name="subcountyname" value="<?= htmlspecialchars($user['subcountyname'] ?? '') ?>" readonly>
                </div>

                <!-- User Role (Admin Only) -->
                <?php if ($_SESSION['userrole'] === 'Admin'): ?>
                <div class="form-group">
                    <label>User Role</label>
                    <select class="form-control" name="userrole">
                        <?php
                        $roles = ['User', 'Admin'];
                        foreach ($roles as $role) {
                            $selected = ($user['userrole'] ?? '') === $role ? 'selected' : '';
                            echo "<option value='$role' $selected>$role</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="userrole" value="<?= htmlspecialchars($user['userrole']) ?>">
                <?php endif; ?>

                <!-- Photo Upload -->
                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control" name="photo" accept="image/*">
                    <small class="text-muted">Max 5MB. JPEG/PNG/GIF</small>
                </div>

                <!-- Webcam -->
                <div class="form-group webcam-container">
                    <label>Or Take Photo</label>
                    <video id="video" autoplay style="display:none;"></video>
                    <canvas id="canvas" style="display:none;"></canvas>
                    <img id="preview" style="display:none;">
                    <button type="button" id="start-webcam" class="btn-webcam">Start Webcam</button>
                    <button type="button" id="capture-btn" class="btn-webcam" style="display:none;">Capture</button>
                </div>

                <!-- Submit -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" name="submit" class="btn-submit w-100">
                        Update Profile
                    </button>
                </div>
            </div>
        </form>

        <div class="text-center mt-3">
            <a href="../views/userslist.php" class="text-decoration-none">
                Back to Users List
            </a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Facility auto-fill
    $('#facilityname').change(function() {
        const facility = $(this).val();
        if (facility) {
            $.post('fetch_facility.php', { facilityname: facility }, function(data) {
                if (data.mflcode) {
                    $('#mflcode').val(data.mflcode);
                    $('#countyname').val(data.countyname);
                    $('#subcountyname').val(data.subcountyname);
                }
            }, 'json');
        } else {
            $('#mflcode, #countyname, #subcountyname').val('');
        }
    });

    // Webcam
    const video = $('#video')[0];
    const canvas = $('#canvas')[0];
    const preview = $('#preview')[0];
    const startBtn = $('#start-webcam');
    const captureBtn = $('#capture-btn');
    const webcamInput = $('#webcam_photo');

    startBtn.click(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            video.style.display = 'block';
            captureBtn.show();
            startBtn.hide();
            preview.style.display = 'none';
        } catch (e) {
            alert('Webcam access denied.');
        }
    });

    captureBtn.click(() => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg');
        preview.src = dataUrl;
        preview.style.display = 'block';
        webcamInput.val(dataUrl);
        video.style.display = 'none';
        captureBtn.hide();
        startBtn.show();
        video.srcObject.getTracks().forEach(t => t.stop());
    });
});
</script>

</body>
</html>