<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

// Check login
if (!isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";
$draft_id = isset($_GET['draft']) ? (int)$_GET['draft'] : 0;
$session_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Load draft if exists
$draft_data = null;
if ($draft_id > 0) {
    $draft_query = mysqli_query($conn, "SELECT draft_data FROM training_drafts WHERE draft_id = $draft_id AND user_id = '{$_SESSION['full_name']}'");
    if ($draft_row = mysqli_fetch_assoc($draft_query)) {
        $draft_data = json_decode($draft_row['draft_data'], true);
    }
}

// Load existing session for editing
$session_data = null;
$selected_staff = [];
if ($session_id > 0) {
    $session_query = mysqli_query($conn, "SELECT * FROM training_sessions WHERE session_id = $session_id");
    $session_data = mysqli_fetch_assoc($session_query);

    if ($session_data) {
        // Get selected staff
        $staff_query = mysqli_query($conn, "SELECT staff_id FROM training_participants WHERE session_id = $session_id");
        while ($row = mysqli_fetch_assoc($staff_query)) {
            $selected_staff[] = $row['staff_id'];
        }
    }
}

// Fetch dropdown data
$courses = mysqli_query($conn, "SELECT * FROM courses ORDER BY course_name");
$durations = mysqli_query($conn, "SELECT * FROM course_durations ORDER BY duration_name");
$trainingtypes = mysqli_query($conn, "SELECT * FROM trainingtypes ORDER BY trainingtype_name");
$locations = mysqli_query($conn, "SELECT * FROM training_locations ORDER BY location_name");
$facilitator_levels = mysqli_query($conn, "SELECT * FROM facilitator_levels ORDER BY facilitator_level_name");
$counties = mysqli_query($conn, "SELECT * FROM counties ORDER BY county_name");

// Handle form submission
if (isset($_POST['action'])) {
    $action = $_POST['action']; // 'draft', 'continue', 'submit'

    // Get form data - REMOVED facility_id completely
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $duration_id = !empty($_POST['duration_id']) ? (int)$_POST['duration_id'] : 0;
    $trainingtype_id = !empty($_POST['trainingtype_id']) ? (int)$_POST['trainingtype_id'] : 0;
    $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : 0;
    $fac_level_id = !empty($_POST['fac_level_id']) ? (int)$_POST['fac_level_id'] : 0;
    $county_id = !empty($_POST['county_id']) ? (int)$_POST['county_id'] : 0;
    $subcounty_id = !empty($_POST['subcounty_id']) ? (int)$_POST['subcounty_id'] : 0;
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    $training_objectives = mysqli_real_escape_string($conn, $_POST['training_objectives'] ?? '');
    $materials_provided = mysqli_real_escape_string($conn, $_POST['materials_provided'] ?? '');
    $selected_staff = isset($_POST['selected_staff']) ? $_POST['selected_staff'] : [];

    // Validate required fields
    if (empty($course_id) || empty($duration_id) || empty($trainingtype_id) || empty($location_id) || empty($fac_level_id) || empty($county_id) || empty($subcounty_id) || empty($start_date) || empty($end_date)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($selected_staff)) {
        $error = "Please select at least one staff member.";
    } else {
        // Generate session code
        $session_code = 'TRN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Check if editing existing session
            if ($session_id > 0) {
                // Update existing session - REMOVED facility_id
                $session_sql = "UPDATE training_sessions SET
                    course_id = $course_id,
                    duration_id = $duration_id,
                    trainingtype_id = $trainingtype_id,
                    location_id = $location_id,
                    fac_level_id = $fac_level_id,
                    county_id = $county_id,
                    subcounty_id = $subcounty_id,
                    start_date = '$start_date',
                    end_date = '$end_date',
                    training_objectives = '$training_objectives',
                    materials_provided = '$materials_provided'";

                // Only update status if submitting
                if ($action == 'submit') {
                    $session_sql .= ", status = 'submitted', submitted_by = '{$_SESSION['full_name']}', submitted_at = NOW()";
                }

                $session_sql .= " WHERE session_id = $session_id";

                if (!mysqli_query($conn, $session_sql)) {
                    throw new Exception("Error updating session: " . mysqli_error($conn));
                }

                // Delete old participants
                if (!mysqli_query($conn, "DELETE FROM training_participants WHERE session_id = $session_id")) {
                    throw new Exception("Error deleting participants: " . mysqli_error($conn));
                }

            } else {
                // Insert new session - REMOVED facility_id
                $status = ($action == 'submit') ? 'submitted' : 'draft';

                $session_sql = "INSERT INTO training_sessions (
                    session_code, course_id, duration_id, trainingtype_id, location_id,
                    fac_level_id, county_id, subcounty_id, start_date, end_date,
                    training_objectives, materials_provided, status, created_by
                ) VALUES (
                    '$session_code', $course_id, $duration_id, $trainingtype_id, $location_id,
                    $fac_level_id, $county_id, $subcounty_id, '$start_date', '$end_date',
                    '$training_objectives', '$materials_provided', '$status', '{$_SESSION['full_name']}'
                )";

                if (!mysqli_query($conn, $session_sql)) {
                    throw new Exception("Error inserting session: " . mysqli_error($conn));
                }

                $session_id = mysqli_insert_id($conn);
            }

            // Insert participants
            foreach ($selected_staff as $staff_id) {
                $staff_id = (int)$staff_id;
                $participant_sql = "INSERT INTO training_participants (session_id, staff_id, attendance_status)
                                   VALUES ($session_id, $staff_id, 'registered')";
                if (!mysqli_query($conn, $participant_sql)) {
                    throw new Exception("Error inserting participant: " . mysqli_error($conn));
                }
            }

            // Clear draft after submission
            if ($action == 'submit' && $draft_id > 0) {
                mysqli_query($conn, "DELETE FROM training_drafts WHERE draft_id = $draft_id");
            }

            mysqli_commit($conn);

            // After successful submission, insert into staff_trainings table
            if ($action == 'submit') {
                // Get session details with all necessary joins
                $session_details_query = mysqli_query($conn, "
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
                    WHERE ts.session_id = $session_id
                ");

                if ($session_details = mysqli_fetch_assoc($session_details_query)) {
                    // Get county name
                    $county_name = '';
                    if (!empty($session_details['county_id'])) {
                        $county_query = mysqli_query($conn, "SELECT county_name FROM counties WHERE county_id = {$session_details['county_id']}");
                        if ($county_row = mysqli_fetch_assoc($county_query)) {
                            $county_name = $county_row['county_name'];
                        }
                    }

                    // Get subcounty name
                    $subcounty_name = '';
                    if (!empty($session_details['subcounty_id'])) {
                        $subcounty_query = mysqli_query($conn, "SELECT sub_county_name FROM sub_counties WHERE sub_county_id = {$session_details['subcounty_id']}");
                        if ($subcounty_row = mysqli_fetch_assoc($subcounty_query)) {
                            $subcounty_name = $subcounty_row['sub_county_name'];
                        }
                    }

                    // Get facilitator level name
                    $facilitator_level = $session_details['facilitator_level_name'] ?? '';

                    // Get all participants for this session with their staff details
                    $participants_query = mysqli_query($conn, "
                        SELECT tp.*, cs.*
                        FROM training_participants tp
                        JOIN county_staff cs ON tp.staff_id = cs.staff_id
                        WHERE tp.session_id = $session_id
                    ");

                    while ($participant = mysqli_fetch_assoc($participants_query)) {
                        $full_name = trim($participant['first_name'] . ' ' . $participant['last_name'] . (!empty($participant['other_name']) ? ' ' . $participant['other_name'] : ''));

                        // Insert into staff_trainings
                        $insert_staff_training = "INSERT INTO staff_trainings (
                            facility_id, facility_name, mflcode, county, subcounty,
                            staff_name, sex_name, staff_department, staff_designation, staff_p_no,
                            staff_phone, email, staff_cadre, course_id, course_name,
                            trainingtype_name, duration_id, duration_name, training_date,
                            location_id, location_name, facilitator_name, cadre_id, cadrename,
                            fac_level_id, facilitator_level, remarks, created_at
                        ) VALUES (
                            '{$participant['facility_id']}', '{$participant['facility_name']}', '{$participant['mfl_code']}', '$county_name', '$subcounty_name',
                            '$full_name', '{$participant['sex']}', '{$participant['department_name']}', '{$participant['designation']}', '{$participant['staff_number']}',
                            '{$participant['staff_phone']}', '{$participant['email']}', '{$participant['cadre_name']}', '{$session_details['course_id']}', '{$session_details['course_name']}',
                            '{$session_details['trainingtype_name']}', '{$session_details['duration_id']}', '{$session_details['duration_name']}', '{$session_details['start_date']}',
                            '{$session_details['location_id']}', '{$session_details['location_name']}', '{$session_details['facilitator_name']}', '{$participant['cadre_id']}', '{$participant['cadre_name']}',
                            '{$session_details['fac_level_id']}', '$facilitator_level', '{$participant['remarks']}', NOW()
                        )";

                        mysqli_query($conn, $insert_staff_training);
                    }
                }
            }

            if ($action == 'submit') {
                $msg = "Training session submitted successfully!";
                // Redirect to view page
                header("Location: view_training.php?id=$session_id");
                exit();
            } elseif ($action == 'draft') {
                $msg = "Draft saved successfully!";
                // Redirect to drafts page
                header("Location: training_drafts.php");
                exit();
            } else {
                $msg = "Training session saved successfully!";
                header("Location: training_list.php");
                exit();
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch staff list for selection
$staff_list = mysqli_query($conn, "SELECT staff_id, first_name, last_name, other_name,
                                   sex, staff_phone, id_number, email, facility_name,
                                   department_name, cadre_name, status
                                   FROM county_staff
                                   WHERE status = 'active'
                                   ORDER BY first_name, last_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Registration</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f4f7fc;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: #0D1A63;
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header h1 i {
            margin-right: 10px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .alert {
            padding: 15px 20px;
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

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .form-section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-section h2 i {
            color: #667eea;
            margin-right: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        /* Staff Selection Table */
        .staff-table-container {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-top: 15px;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #e0e0e0;
        }

        .staff-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .staff-table tr:hover {
            background: #f5f5f5;
        }

        .staff-table input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .selected-count {
            background: #667eea;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        /* Selected Staff List */
        .selected-staff-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .selected-staff-item {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
        }

        .selected-staff-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .selected-staff-info span {
            font-size: 13px;
            color: #666;
        }

        .selected-staff-info i {
            margin-right: 5px;
            color: #667eea;
        }

        .remove-staff {
            color: #dc3545;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
        }

        .remove-staff:hover {
            color: #bd2130;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .draft-info {
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #004085;
            border: 1px solid #b8daff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .draft-info i {
            margin-right: 8px;
        }

        .draft-actions {
            display: flex;
            gap: 10px;
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .filter-input {
            flex: 1;
            min-width: 200px;
        }

        .badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .draft-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Training Participants Registration Form</h1>
            <div class="header-actions">
                <a href="training_drafts.php" class="btn btn-primary">
                    <i class="fas fa-save"></i> My Drafts
                </a>
                <a href="training_list.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> All Sessions
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert success"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($draft_data): ?>
            <div class="draft-info">
                <div>
                    <i class="fas fa-info-circle"></i>
                    You have a saved draft from <?php echo date('F j, Y g:i a', strtotime($draft_data['last_updated'] ?? 'now')); ?>
                </div>
                <div class="draft-actions">
                    <a href="?clear_draft=<?php echo $draft_id; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Clear this draft?')">
                        <i class="fas fa-times"></i> Clear Draft
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($session_data && $session_data['status'] == 'submitted'): ?>
            <div class="alert info" style="background: #d1ecf1; color: #0c5460; border-color: #bee5eb;">
                <i class="fas fa-info-circle"></i>
                This session has been submitted and cannot be edited.
                <a href="view_training.php?id=<?php echo $session_id; ?>" class="btn btn-info btn-sm">View Details</a>
            </div>
        <?php endif; ?>

        <form method="POST" id="trainingForm">
            <!-- Training Details Section -->
            <div class="form-section">
                <h2><i class="fas fa-graduation-cap"></i> Training Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course <i>*</i></label>
                        <select name="course_id" id="course_id" required>
                            <option value="">Select Course</option>
                            <?php
                            $selected_course = $session_data['course_id'] ?? ($draft_data['course_id'] ?? '');
                            mysqli_data_seek($courses, 0);
                            while ($row = mysqli_fetch_assoc($courses)):
                            ?>
                                <option value="<?php echo $row['course_id']; ?>"
                                    <?php echo ($selected_course == $row['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['course_name']); ?>
                                    <?php if (!empty($row['course_section'])): ?>
                                        (<?php echo htmlspecialchars($row['course_section']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Duration <i>*</i></label>
                        <select name="duration_id" required>
                            <option value="">Select Duration</option>
                            <?php
                            mysqli_data_seek($durations, 0);
                            $selected_duration = $session_data['duration_id'] ?? ($draft_data['duration_id'] ?? '');
                            while ($row = mysqli_fetch_assoc($durations)):
                            ?>
                                <option value="<?php echo $row['duration_id']; ?>"
                                    <?php echo ($selected_duration == $row['duration_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['duration_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Training Type <i>*</i></label>
                        <select name="trainingtype_id" required>
                            <option value="">Select Type</option>
                            <?php
                            mysqli_data_seek($trainingtypes, 0);
                            $selected_type = $session_data['trainingtype_id'] ?? ($draft_data['trainingtype_id'] ?? '');
                            while ($row = mysqli_fetch_assoc($trainingtypes)):
                            ?>
                                <option value="<?php echo $row['trainingtype_id']; ?>"
                                    <?php echo ($selected_type == $row['trainingtype_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['trainingtype_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location <i>*</i></label>
                        <select name="location_id" required>
                            <option value="">Select Location</option>
                            <?php
                            mysqli_data_seek($locations, 0);
                            $selected_location = $session_data['location_id'] ?? ($draft_data['location_id'] ?? '');
                            while ($row = mysqli_fetch_assoc($locations)):
                            ?>
                                <option value="<?php echo $row['location_id']; ?>"
                                    <?php echo ($selected_location == $row['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['location_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Facilitator Level <i>*</i></label>
                        <select name="fac_level_id" required>
                            <option value="">Select Level</option>
                            <?php
                            mysqli_data_seek($facilitator_levels, 0);
                            $selected_level = $session_data['fac_level_id'] ?? ($draft_data['fac_level_id'] ?? '');
                            while ($row = mysqli_fetch_assoc($facilitator_levels)):
                            ?>
                                <option value="<?php echo $row['fac_level_id']; ?>"
                                    <?php echo ($selected_level == $row['fac_level_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['facilitator_level_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>County <i>*</i></label>
                        <select name="county_id" id="county" required>
                            <option value="">Select County</option>
                            <?php
                            mysqli_data_seek($counties, 0);
                            $selected_county = $session_data['county_id'] ?? ($draft_data['county_id'] ?? '');
                            while ($row = mysqli_fetch_assoc($counties)):
                            ?>
                                <option value="<?php echo $row['county_id']; ?>"
                                    <?php echo ($selected_county == $row['county_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['county_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subcounty <i>*</i></label>
                        <select name="subcounty_id" id="subcounty" required>
                            <option value="">Select Subcounty</option>
                            <?php
                            if ($selected_county) {
                                $selected_subcounty = $session_data['subcounty_id'] ?? ($draft_data['subcounty_id'] ?? '');
                                $subs = mysqli_query($conn, "SELECT sub_county_id, sub_county_name FROM sub_counties WHERE county_name = (SELECT county_name FROM counties WHERE county_id = $selected_county)");
                                while ($row = mysqli_fetch_assoc($subs)) {
                                    $selected = ($selected_subcounty == $row['sub_county_id']) ? 'selected' : '';
                                    echo '<option value="'.$row['sub_county_id'].'" '.$selected.'>'.htmlspecialchars($row['sub_county_name']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date <i>*</i></label>
                        <input type="date" name="start_date" required
                               value="<?php echo $session_data['start_date'] ?? ($draft_data['start_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>End Date <i>*</i></label>
                        <input type="date" name="end_date" required
                               value="<?php echo $session_data['end_date'] ?? ($draft_data['end_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Training Objectives</label>
                        <textarea name="training_objectives" rows="3" placeholder="Enter training objectives..."><?php echo $session_data['training_objectives'] ?? ($draft_data['training_objectives'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Materials Provided</label>
                        <textarea name="materials_provided" rows="2" placeholder="List materials provided..."><?php echo $session_data['materials_provided'] ?? ($draft_data['materials_provided'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Staff Selection Section -->
            <div class="form-section">
                <h2><i class="fas fa-users"></i> Select Staff for Training</h2>

                <div class="filter-bar">
                    <input type="text" id="staffSearch" class="filter-input" placeholder="Search by name, facility, department, or cadre...">
                    <select id="facilityFilter" class="filter-input">
                        <option value="">All Facilities</option>
                        <?php
                        $facilities_filter = mysqli_query($conn, "SELECT DISTINCT facility_name FROM county_staff WHERE status = 'active' ORDER BY facility_name");
                        while ($f = mysqli_fetch_assoc($facilities_filter)) {
                            echo '<option value="' . htmlspecialchars($f['facility_name']) . '">' . htmlspecialchars($f['facility_name']) . '</option>';
                        }
                        ?>
                    </select>
                    <select id="cadreFilter" class="filter-input">
                        <option value="">All Cadres</option>
                        <?php
                        $cadres_filter = mysqli_query($conn, "SELECT DISTINCT cadre_name FROM county_staff WHERE status = 'active' ORDER BY cadre_name");
                        while ($c = mysqli_fetch_assoc($cadres_filter)) {
                            echo '<option value="' . htmlspecialchars($c['cadre_name']) . '">' . htmlspecialchars($c['cadre_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="staff-table-container">
                    <table class="staff-table" id="staffTable">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th>Name</th>
                                <th>Sex</th>
                                <th>Phone</th>
                                <th>ID Number</th>
                                <th>Email</th>
                                <th>Facility</th>
                                <th>Department</th>
                                <th>Cadre</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $selected_staff_ids = $selected_staff;
                            while ($staff = mysqli_fetch_assoc($staff_list)):
                                $checked = in_array($staff['staff_id'], $selected_staff_ids) ? 'checked' : '';
                                $full_name = trim($staff['first_name'] . ' ' . $staff['last_name'] . (!empty($staff['other_name']) ? ' ' . $staff['other_name'] : ''));
                            ?>
                            <tr data-facility="<?php echo htmlspecialchars($staff['facility_name']); ?>"
                                data-cadre="<?php echo htmlspecialchars($staff['cadre_name']); ?>"
                                data-search="<?php echo strtolower($full_name . ' ' . $staff['facility_name'] . ' ' . $staff['department_name'] . ' ' . $staff['id_number'] . ' ' . $staff['cadre_name']); ?>">
                                <td>
                                    <input type="checkbox" name="selected_staff[]"
                                           value="<?php echo $staff['staff_id']; ?>"
                                           class="staff-checkbox" <?php echo $checked; ?>>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($staff['sex'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['staff_phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['id_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['facility_name']); ?></td>
                                <td><?php echo htmlspecialchars($staff['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($staff['cadre_name']); ?></td>
                                <td>
                                    <span style="background: <?php echo $staff['status'] == 'active' ? '#28a745' : '#dc3545'; ?>;
                                                       color: #fff; border-radius: 4px; padding: 3px 8px; font-size: 11px;">
                                        <?php echo ucfirst($staff['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="selected-staff-list" id="selectedStaffList">
                    <h4><i class="fas fa-check-circle"></i> Selected Staff <span class="selected-count" id="selectedCount">0</span></h4>
                    <div id="selectedStaffContainer">
                        <!-- Selected staff will be displayed here dynamically -->
                    </div>
                </div>
            </div>

            <!-- Hidden field for action -->
            <input type="hidden" name="action" id="action" value="">

            <!-- Button Group -->
            <div class="button-group">
                <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-info" onclick="saveContinue()">
                    <i class="fas fa-arrow-right"></i> Save & Continue Later
                </button>
                <button type="button" class="btn btn-success" onclick="submitForm()">
                    <i class="fas fa-check"></i> Submit Registration
                </button>
                <?php if ($session_id > 0 && isset($session_data['status']) && $session_data['status'] == 'draft'): ?>
                    <a href="training_list.php" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
    $(document).ready(function() {
        // Handle select all checkbox
        $('#select-all').change(function() {
            $('.staff-checkbox:visible').prop('checked', $(this).prop('checked'));
            updateSelectedStaff();
        });

        // Handle individual checkboxes
        $('.staff-checkbox').change(function() {
            updateSelectedStaff();
            updateSelectAll();
        });

        // Search and filter functionality
        function filterTable() {
            var searchTerm = $('#staffSearch').val().toLowerCase();
            var facilityFilter = $('#facilityFilter').val().toLowerCase();
            var cadreFilter = $('#cadreFilter').val().toLowerCase();

            $('#staffTable tbody tr').each(function() {
                var show = true;
                var $row = $(this);

                // Search filter
                if (searchTerm && $row.data('search').indexOf(searchTerm) === -1) {
                    show = false;
                }

                // Facility filter
                if (facilityFilter && $row.data('facility').toLowerCase() !== facilityFilter) {
                    show = false;
                }

                // Cadre filter
                if (cadreFilter && $row.data('cadre').toLowerCase() !== cadreFilter) {
                    show = false;
                }

                $row.toggle(show);
            });

            updateSelectAll();
        }

        $('#staffSearch, #facilityFilter, #cadreFilter').on('keyup change', filterTable);

        // County change - load subcounties
        $('#county').change(function() {
            var county_id = $(this).val();
            if (county_id) {
                $.ajax({
                    url: 'get_subcounties.php',
                    type: 'POST',
                    data: {county_id: county_id},
                    dataType: 'json',
                    success: function(data) {
                        var options = '<option value="">Select Subcounty</option>';
                        $.each(data, function(key, value) {
                            options += '<option value="' + value.sub_county_id + '">' + value.sub_county_name + '</option>';
                        });
                        $('#subcounty').html(options);

                        // Preselect if editing
                        <?php if (isset($selected_subcounty) && $selected_subcounty): ?>
                        $('#subcounty').val('<?php echo $selected_subcounty; ?>');
                        <?php endif; ?>
                    }
                });
            } else {
                $('#subcounty').html('<option value="">Select Subcounty</option>');
            }
        });

        // Initial update of selected staff
        updateSelectedStaff();

        // Form validation before submit
        validateDates();
    });

    function updateSelectedStaff() {
        var container = $('#selectedStaffContainer');
        var count = $('.staff-checkbox:checked').length;

        $('#selectedCount').text(count);
        container.empty();

        $('.staff-checkbox:checked').each(function() {
            var row = $(this).closest('tr');
            var name = row.find('td:eq(1)').text().trim();
            var facility = row.find('td:eq(6)').text().trim();
            var department = row.find('td:eq(7)').text().trim();
            var cadre = row.find('td:eq(8)').text().trim();
            var staffId = $(this).val();

            container.append(`
                <div class="selected-staff-item" data-staff-id="${staffId}">
                    <div class="selected-staff-info">
                        <span><i class="fas fa-user"></i> ${name}</span>
                        <span><i class="fas fa-hospital"></i> ${facility}</span>
                        <span><i class="fas fa-briefcase"></i> ${department} - ${cadre}</span>
                    </div>
                    <span class="remove-staff" onclick="removeStaff(this, ${staffId})">
                        <i class="fas fa-times-circle"></i>
                    </span>
                </div>
            `);
        });
    }

    function removeStaff(element, staffId) {
        $(element).closest('.selected-staff-item').remove();
        $('.staff-checkbox[value="' + staffId + '"]').prop('checked', false).trigger('change');
        updateSelectAll();
    }

    function updateSelectAll() {
        var visibleChecked = $('.staff-checkbox:visible:checked').length;
        var visibleTotal = $('.staff-checkbox:visible').length;

        if (visibleChecked === 0) {
            $('#select-all').prop('checked', false).prop('indeterminate', false);
        } else if (visibleChecked === visibleTotal) {
            $('#select-all').prop('checked', true).prop('indeterminate', false);
        } else {
            $('#select-all').prop('indeterminate', true);
        }
    }

    function saveDraft() {
        if (confirm('Save current progress as draft?')) {
            $('#action').val('draft');
            $('#trainingForm').submit();
        }
    }

    function saveContinue() {
        if (validateForm()) {
            $('#action').val('continue');
            $('#trainingForm').submit();
        }
    }

    function submitForm() {
        if (validateForm()) {
            if (confirm('Are you sure you want to submit this training registration?')) {
                $('#action').val('submit');
                $('#trainingForm').submit();
            }
        }
    }

    function validateForm() {
        // Check required fields
        var requiredFields = ['course_id', 'duration_id', 'trainingtype_id', 'location_id', 'fac_level_id', 'county_id', 'subcounty_id', 'start_date', 'end_date'];
        for (var i = 0; i < requiredFields.length; i++) {
            var field = $('[name="' + requiredFields[i] + '"]');
            if (!field.val()) {
                alert('Please fill in all required fields.');
                field.focus();
                return false;
            }
        }

        // Check if at least one staff is selected
        if ($('.staff-checkbox:checked').length === 0) {
            alert('Please select at least one staff member for training.');
            return false;
        }

        // Check if end date is after start date
        var start = new Date($('input[name="start_date"]').val());
        var end = new Date($('input[name="end_date"]').val());

        if (end < start) {
            alert('End date must be after start date.');
            return false;
        }

        return true;
    }

    function validateDates() {
        $('input[name="start_date"], input[name="end_date"]').change(function() {
            var start = new Date($('input[name="start_date"]').val());
            var end = new Date($('input[name="end_date"]').val());

            if (end < start) {
                alert('Warning: End date is before start date!');
            }
        });
    }
    </script>
</body>
</html>