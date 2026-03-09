<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

// Only allow admin access
if (!isset($_SESSION['userrole']) || $_SESSION['userrole'] != 'Admin') {
    die("Access denied. Admin only.");
}

$updated = 0;
$errors = 0;

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get all records in staff_trainings that need updating
    $records_query = mysqli_query($conn, "SELECT * FROM staff_trainings");
    
    while ($record = mysqli_fetch_assoc($records_query)) {
        $training_id = $record['training_id'];
        $updates = [];
        
        // Try to find matching participant to get additional details
        if (!empty($record['staff_name']) && !empty($record['course_name']) && !empty($record['training_date'])) {
            // Find matching participant
            $participant_query = mysqli_query($conn, "
                SELECT tp.*, cs.*, ts.*
                FROM training_participants tp
                JOIN county_staff cs ON tp.staff_id = cs.staff_id
                JOIN training_sessions ts ON tp.session_id = ts.session_id
                WHERE CONCAT(cs.first_name, ' ', cs.last_name) LIKE '%{$record['staff_name']}%'
                AND ts.start_date = '{$record['training_date']}'
                LIMIT 1
            ");
            
            if ($participant = mysqli_fetch_assoc($participant_query)) {
                // Get county name
                if (!empty($participant['county_id'])) {
                    $county_query = mysqli_query($conn, "SELECT county_name FROM counties WHERE county_id = {$participant['county_id']}");
                    if ($county_row = mysqli_fetch_assoc($county_query)) {
                        $updates[] = "county = '{$county_row['county_name']}'";
                    }
                }
                
                // Get subcounty name
                if (!empty($participant['subcounty_id'])) {
                    $subcounty_query = mysqli_query($conn, "SELECT sub_county_name FROM sub_counties WHERE sub_county_id = {$participant['subcounty_id']}");
                    if ($subcounty_row = mysqli_fetch_assoc($subcounty_query)) {
                        $updates[] = "subcounty = '{$subcounty_row['sub_county_name']}'";
                    }
                }
                
                // Update facility details
                if (!empty($participant['facility_id'])) {
                    $updates[] = "facility_id = '{$participant['facility_id']}'";
                    $updates[] = "facility_name = '{$participant['facility_name']}'";
                    $updates[] = "mflcode = '{$participant['mfl_code']}'";
                }
                
                // Update cadre details
                if (!empty($participant['cadre_id'])) {
                    $updates[] = "cadre_id = '{$participant['cadre_id']}'";
                    $updates[] = "cadrename = '{$participant['cadre_name']}'";
                }
                
                // Update facilitator level
                if (!empty($participant['fac_level_id'])) {
                    $fac_level_query = mysqli_query($conn, "SELECT facilitator_level_name FROM facilitator_levels WHERE fac_level_id = {$participant['fac_level_id']}");
                    if ($fac_level_row = mysqli_fetch_assoc($fac_level_query)) {
                        $updates[] = "facilitator_level = '{$fac_level_row['facilitator_level_name']}'";
                    }
                }
                
                // Update location
                if (!empty($participant['location_id'])) {
                    $location_query = mysqli_query($conn, "SELECT location_name FROM training_locations WHERE location_id = {$participant['location_id']}");
                    if ($location_row = mysqli_fetch_assoc($location_query)) {
                        $updates[] = "location_name = '{$location_row['location_name']}'";
                    }
                }
            }
        }
        
        // Apply updates if any
        if (!empty($updates)) {
            $update_sql = "UPDATE staff_trainings SET " . implode(", ", $updates) . " WHERE training_id = $training_id";
            if (mysqli_query($conn, $update_sql)) {
                $updated++;
            }
        }
    }
    
    mysqli_commit($conn);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $errors = 1;
    $error_message = $e->getMessage();
}

// Get updated statistics
$stats_query = mysqli_query($conn, "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT staff_name) as unique_staff,
    COUNT(DISTINCT facility_name) as unique_facilities,
    COUNT(DISTINCT course_name) as unique_courses
    FROM staff_trainings");
$stats = mysqli_fetch_assoc($stats_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Staff Trainings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f7fc;
            padding: 40px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #011f88, #3498db);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #011f88;
        }
        .btn {
            background: #011f88;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Update Staff Trainings</h1>
                <p>Enhance existing records with additional details</p>
            </div>

            <?php if ($errors > 0): ?>
                <div class="error">
                    Error: <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <div class="success">
                    <strong>Update Completed!</strong><br>
                    <?php echo number_format($updated); ?> records have been updated successfully.
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_records']); ?></div>
                    <div>Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_staff']); ?></div>
                    <div>Unique Staff</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_facilities']); ?></div>
                    <div>Facilities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_courses']); ?></div>
                    <div>Courses</div>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="training_dashboard.php" class="btn">View Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>