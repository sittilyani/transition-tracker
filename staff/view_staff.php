<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../login.php');
    exit();
}

$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

// Fetch staff details
$staff_query = "SELECT * FROM county_staff WHERE staff_id = $staff_id";
$staff_result = mysqli_query($conn, $staff_query);
$staff = mysqli_fetch_assoc($staff_result);

if(!$staff){
    header('Location: staffslist.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Staff Details</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { width: 70%; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #011f88; margin-bottom: 25px; text-align: center; }
        .photo-section { text-align: center; margin-bottom: 30px; }
        .photo-section img { max-width: 250px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .no-photo-placeholder { width: 250px; height: 250px; background: #ddd; margin: 0 auto; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #666; }
        .details-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .detail-item { padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #011f88; }
        .detail-item label { font-weight: bold; color: #011f88; display: block; margin-bottom: 5px; font-size: 14px; }
        .detail-item p { color: #333; font-size: 16px; }
        .detail-item.full-width { grid-column: 1 / -1; }
        .btn { padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 5px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #011f88; color: #fff; }
        .btn-primary:hover { background: #013bb8; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-container { text-align: center; margin-top: 25px; }
        .staff-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #011f88; }
        .staff-name { color: #011f88; font-size: 24px; font-weight: bold; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; }
        .badge-primary { background: #011f88; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="staff-header">
            <div>
                <h2 style="margin: 0;">Staff Details</h2>
                <p class="staff-name"><?php echo $staff['first_name'] . ' ' . $staff['other_name'] . ' ' . $staff['last_name']; ?></p>
            </div>
            <div>
                <span class="badge badge-primary">ID: <?php echo $staff['staff_id']; ?></span>
            </div>
        </div>

        <div class="photo-section">
            <?php if($staff['photo']): ?>
                <img src="display_photo.php?staff_id=<?php echo $staff_id; ?>" alt="Staff Photo">
            <?php else: ?>
                <div class="no-photo-placeholder">No Photo Available</div>
            <?php endif; ?>
        </div>

        <div class="details-grid">
            <div class="detail-item">
                <label>First Name</label>
                <p><?php echo htmlspecialchars($staff['first_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Last Name</label>
                <p><?php echo htmlspecialchars($staff['last_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Other Name</label>
                <p><?php echo !empty($staff['other_name']) ? htmlspecialchars($staff['other_name']) : ''; ?></p>
            </div>

            <div class="detail-item">
                <label>Sex</label>
                <p><?php echo htmlspecialchars($staff['sex']); ?></p>
            </div>

            <div class="detail-item">
                <label>ID Number</label>
                <p><?php echo htmlspecialchars($staff['id_number']); ?></p>
            </div>

            <div class="detail-item">
                <label>Phone Number</label>
                <p><?php echo htmlspecialchars($staff['staff_phone']); ?></p>
            </div>

            <div class="detail-item full-width">
                <label>Email Address</label>
                <p><?php echo htmlspecialchars($staff['email']); ?></p>
            </div>

            <div class="detail-item">
                <label>Facility Name</label>
                <p><?php echo htmlspecialchars($staff['facility_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Level of Care</label>
                <p><?php echo htmlspecialchars($staff['level_of_care_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Sub-County</label>
                <p><?php echo htmlspecialchars($staff['subcounty_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>County</label>
                <p><?php echo htmlspecialchars($staff['county_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Department</label>
                <p><?php echo htmlspecialchars($staff['department_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Cadre</label>
                <p><?php echo htmlspecialchars($staff['cadre_name']); ?></p>
            </div>

            <div class="detail-item">
                <label>Created By</label>
                <p><?php echo htmlspecialchars($staff['created_by']); ?></p>
            </div>

            <div class="detail-item">
                <label>Created At</label>
                <p><?php echo date('F j, Y, g:i a', strtotime($staff['created_at'])); ?></p>
            </div>
        </div>

        <div class="btn-container">
            <a href="update_staff.php?staff_id=<?php echo $staff_id; ?>" class="btn btn-warning">Edit Staff</a>
            <a href="staffslist.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>
</body>
</html>
