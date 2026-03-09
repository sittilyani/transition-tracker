<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

// Only allow admin access
if (!isset($_SESSION['userrole']) || $_SESSION['userrole'] != 'Admin') {
    die("Access denied. Admin only.");
}

$success = 0;
$errors = 0;
$error_messages = [];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // First, let's check what columns actually exist in county_staff
    $columns_query = mysqli_query($conn, "SHOW COLUMNS FROM county_staff");
    $staff_columns = [];
    while ($col = mysqli_fetch_assoc($columns_query)) {
        $staff_columns[] = $col['Field'];
    }
    
    // Check what columns exist in training_sessions
    $sessions_columns_query = mysqli_query($conn, "SHOW COLUMNS FROM training_sessions");
    $sessions_columns = [];
    while ($col = mysqli_fetch_assoc($sessions_columns_query)) {
        $sessions_columns[] = $col['Field'];
    }

    // Get all submitted training sessions
    $sessions_query = mysqli_query($conn, "
        SELECT ts.*, 
               c.course_name,
               tt.trainingtype_name,
               cd.duration_name,
               tl.location_name,
               fl.facilitator_level_name
        FROM training_sessions ts
        LEFT JOIN courses c ON ts.course_id = c.course_id
        LEFT JOIN trainingtypes tt ON ts.trainingtype_id = tt.trainingtype_id
        LEFT JOIN course_durations cd ON ts.duration_id = cd.duration_id
        LEFT JOIN training_locations tl ON ts.location_id = tl.location_id
        LEFT JOIN facilitator_levels fl ON ts.fac_level_id = fl.fac_level_id
        WHERE ts.status = 'submitted' OR ts.status = 'completed'
        ORDER BY ts.session_id
    ");

    if (!$sessions_query) {
        throw new Exception("Error fetching sessions: " . mysqli_error($conn));
    }

    $total_sessions = mysqli_num_rows($sessions_query);
    $total_participants = 0;

    while ($session = mysqli_fetch_assoc($sessions_query)) {
        // Get participants for this session
        $participants_query = mysqli_query($conn, "
            SELECT tp.*, cs.*
            FROM training_participants tp
            JOIN county_staff cs ON tp.staff_id = cs.staff_id
            WHERE tp.session_id = {$session['session_id']}
        ");

        if (!$participants_query) {
            throw new Exception("Error fetching participants for session {$session['session_id']}: " . mysqli_error($conn));
        }

        while ($participant = mysqli_fetch_assoc($participants_query)) {
            // Prepare data for staff_trainings
            $full_name = trim($participant['first_name'] . ' ' . $participant['last_name'] . (!empty($participant['other_name']) ? ' ' . $participant['other_name'] : ''));
            
            // Get facility details
            $facility_id = $participant['facility_id'] ?? 0;
            $facility_name = $participant['facility_name'] ?? '';
            $mflcode = $participant['mfl_code'] ?? '';
            
            // Get county name
            $county = '';
            if (!empty($session['county_id'])) {
                $county_query = mysqli_query($conn, "SELECT county_name FROM counties WHERE county_id = {$session['county_id']}");
                if ($county_row = mysqli_fetch_assoc($county_query)) {
                    $county = mysqli_real_escape_string($conn, $county_row['county_name']);
                }
            }
            
            // Get subcounty name
            $subcounty = '';
            if (!empty($session['subcounty_id'])) {
                $subcounty_query = mysqli_query($conn, "SELECT sub_county_name FROM sub_counties WHERE sub_county_id = {$session['subcounty_id']}");
                if ($subcounty_row = mysqli_fetch_assoc($subcounty_query)) {
                    $subcounty = mysqli_real_escape_string($conn, $subcounty_row['sub_county_name']);
                }
            }

            // Get cadre details - IMPORTANT: Verify cadre_id exists in cadres table
            $cadre_id = $participant['cadre_id'] ?? 0;
            $cadrename = mysqli_real_escape_string($conn, $participant['cadre_name'] ?? '');
            
            // Verify cadre_id exists in cadres table
            if ($cadre_id > 0) {
                $check_cadre = mysqli_query($conn, "SELECT cadre_id FROM cadres WHERE cadre_id = $cadre_id");
                if (mysqli_num_rows($check_cadre) == 0) {
                    // Cadre_id doesn't exist, set to NULL or find matching cadre by name
                    $cadre_id = 'NULL';
                }
            } else {
                $cadre_id = 'NULL';
            }

            // Get facilitator level name
            $facilitator_level = '';
            if (!empty($session['fac_level_id'])) {
                $fac_level_query = mysqli_query($conn, "SELECT facilitator_level_name FROM facilitator_levels WHERE fac_level_id = {$session['fac_level_id']}");
                if ($fac_level_row = mysqli_fetch_assoc($fac_level_query)) {
                    $facilitator_level = mysqli_real_escape_string($conn, $fac_level_row['facilitator_level_name']);
                }
            }

            // Get department name
            $department_name = mysqli_real_escape_string($conn, $participant['department_name'] ?? '');
            
            // Get sex
            $sex = mysqli_real_escape_string($conn, $participant['sex'] ?? '');
            
            // Get phone
            $staff_phone = mysqli_real_escape_string($conn, $participant['staff_phone'] ?? '');
            
            // Get email
            $email = mysqli_real_escape_string($conn, $participant['email'] ?? '');
            
            // Get staff number/id_number
            $staff_p_no = mysqli_real_escape_string($conn, $participant['id_number'] ?? $participant['staff_number'] ?? '');
            
            // Get remarks
            $remarks = mysqli_real_escape_string($conn, $participant['remarks'] ?? '');

            // Escape all string values
            $full_name = mysqli_real_escape_string($conn, $full_name);
            $facility_name = mysqli_real_escape_string($conn, $facility_name);
            $mflcode = mysqli_real_escape_string($conn, $mflcode);
            $course_name = mysqli_real_escape_string($conn, $session['course_name'] ?? '');
            $trainingtype_name = mysqli_real_escape_string($conn, $session['trainingtype_name'] ?? '');
            $duration_name = mysqli_real_escape_string($conn, $session['duration_name'] ?? '');
            $location_name = mysqli_real_escape_string($conn, $session['location_name'] ?? '');

            // Insert into staff_trainings - REMOVED designation and staff_number columns
            $insert_sql = "INSERT INTO staff_trainings (
                facility_id, facility_name, mflcode, county, subcounty,
                staff_name, sex_name, staff_department, 
                staff_phone, email, staff_cadre, course_id, course_name,
                trainingtype_name, duration_id, duration_name, training_date,
                location_id, location_name, 
                cadre_id, cadrename,
                fac_level_id, facilitator_level, remarks, created_at
            ) VALUES (
                $facility_id, '$facility_name', '$mflcode', '$county', '$subcounty',
                '$full_name', '$sex', '$department_name',
                '$staff_phone', '$email', '$cadrename', {$session['course_id']}, '$course_name',
                '$trainingtype_name', {$session['duration_id']}, '$duration_name', '{$session['start_date']}',
                {$session['location_id']}, '$location_name',
                $cadre_id, '$cadrename',
                {$session['fac_level_id']}, '$facilitator_level', '$remarks', NOW()
            )";

            if (!mysqli_query($conn, $insert_sql)) {
                $error_msg = "Error inserting record: " . mysqli_error($conn) . " for staff: $full_name";
                throw new Exception($error_msg);
            }

            $total_participants++;
        }
    }

    mysqli_commit($conn);
    $success = $total_participants;

} catch (Exception $e) {
    mysqli_rollback($conn);
    $errors = 1;
    $error_messages[] = $e->getMessage();
}

// Get statistics
$stats_query = mysqli_query($conn, "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT staff_name) as unique_staff,
    COUNT(DISTINCT facility_name) as unique_facilities,
    COUNT(DISTINCT course_name) as unique_courses
    FROM staff_trainings");
$stats = mysqli_fetch_assoc($stats_query) ?: ['total_records' => 0, 'unique_staff' => 0, 'unique_facilities' => 0, 'unique_courses' => 0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Migration - Staff Trainings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            padding: 40px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
        }
        .header {
            background: linear-gradient(135deg, #011f88, #3498db);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
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
            border-left: 4px solid #011f88;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #011f88;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 600;
        }
        .btn {
            background: #011f88;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #001166;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1,31,136,0.3);
            color: white;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .error-list {
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1><i class="fas fa-database"></i> Staff Trainings Migration</h1>
                <p class="mb-0">Migrate data from training_sessions and training_participants to staff_trainings</p>
            </div>

            <?php if (!empty($error_messages)): ?>
                <div class="error">
                    <strong><i class="fas fa-exclamation-triangle"></i> Migration Completed with Errors:</strong>
                    <div class="error-list">
                        <ul class="mb-0">
                            <?php foreach ($error_messages as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success > 0): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Migration Completed Successfully!</strong><br>
                    <span class="fw-bold"><?php echo number_format($success); ?></span> participant records have been migrated to staff_trainings table.
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_records']); ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_staff']); ?></div>
                    <div class="stat-label">Unique Staff</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_facilities']); ?></div>
                    <div class="stat-label">Facilities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['unique_courses']); ?></div>
                    <div class="stat-label">Courses</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="training_list.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Training List
                </a>
                <a href="training_dashboard.php" class="btn btn-success">
                    <i class="fas fa-chart-bar"></i> View Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>