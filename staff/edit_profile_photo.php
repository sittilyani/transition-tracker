<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

if (!$staff_id) {
    $_SESSION['error_msg'] = "Invalid staff ID";
    header('Location: employee_profile.php');
    exit();
}

// Fetch staff details
$stmt = $conn->prepare("SELECT * FROM county_staff WHERE staff_id = ?");
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

if (!$staff) {
    $_SESSION['error_msg'] = "Staff not found";
    header('Location: employee_profile.php');
    exit();
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_type = $_FILES['photo']['type'];
        $file_size = $_FILES['photo']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_msg'] = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
        } elseif ($file_size > $max_size) {
            $_SESSION['error_msg'] = "File size exceeds 5MB limit.";
        } else {
            $photo_blob = file_get_contents($file_tmp);

            $update = $conn->prepare("UPDATE county_staff SET photo = ? WHERE staff_id = ?");
            $update->bind_param('bi', $photo_blob, $staff_id);

            if ($update->execute()) {
                $_SESSION['success_msg'] = "Photo updated successfully!";
            } else {
                $_SESSION['error_msg'] = "Error updating photo: " . $update->error;
            }
            $update->close();
        }
    } else {
        $_SESSION['error_msg'] = "Please select a photo to upload.";
    }

    header("Location: employee_profile.php?staff_id=$staff_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile Photo</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: #0D1A63;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .card-header h2 {
            margin: 0;
            font-size: 24px;
        }
        .card-body {
            padding: 30px;
        }
        .current-photo {
            text-align: center;
            margin-bottom: 30px;
        }
        .current-photo img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #f0f0f0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .photo-preview {
            text-align: center;
            margin: 20px 0;
        }
        #preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            display: none;
            margin: 0 auto;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #0D1A63;
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,26,99,0.3);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-camera"></i> Update Profile Photo</h2>
            </div>
            <div class="card-body">
                <div class="current-photo">
                    <?php if (!empty($staff['photo'])): ?>
                        <img src="display_photo.php?staff_id=<?php echo $staff_id; ?>" alt="Current Photo">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/150?text=No+Photo" alt="No Photo">
                    <?php endif; ?>
                    <p class="mt-2 text-muted">Current Photo</p>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select New Photo</label>
                        <input type="file" class="form-control" name="photo" id="photoInput" accept="image/jpeg,image/png,image/gif" required>
                        <small class="text-muted">Max size: 5MB. Allowed: JPG, PNG, GIF</small>
                    </div>

                    <div class="photo-preview">
                        <img id="preview" src="#" alt="Preview">
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Photo
                        </button>
                        <a href="employee_profile.php?staff_id=<?php echo $staff_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('photoInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('preview').style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>