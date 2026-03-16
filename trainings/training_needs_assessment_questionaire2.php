<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// -- AJAX: live staff search --
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_staff') {
    header('Content-Type: application/json');

    $q = $_GET['q'] ?? '';
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit();
    }

    $q = mysqli_real_escape_string($conn, $q);
    $rows = [];

    // Get staff details
    $res = mysqli_query($conn,
        "SELECT staff_id, first_name, other_name, last_name, id_number,
                sex, staff_phone, email, cadre_name, department_name,
                facility_name, county_name, subcounty_name, employment_status,
                date_of_birth, date_of_joining
         FROM county_staff
         WHERE status = 'active'
           AND (first_name LIKE '%$q%' OR last_name LIKE '%$q%'
                OR other_name LIKE '%$q%' OR id_number LIKE '%$q%'
                OR staff_phone LIKE '%$q%')
         ORDER BY first_name, last_name LIMIT 15");

    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            // Format full name
            $r['full_name'] = trim($r['first_name'] . ' ' .
                (!empty($r['other_name']) ? $r['other_name'] . ' ' : '') .
                $r['last_name']);

            // Age from DOB
            if (!empty($r['date_of_birth'])) {
                $age = (new DateTime($r['date_of_birth']))->diff(new DateTime())->y;
                $r['age'] = $age;
                if      ($age < 26) $r['age_range'] = '18-25';
                elseif  ($age < 36) $r['age_range'] = '26-35';
                elseif  ($age < 46) $r['age_range'] = '36-45';
                elseif  ($age < 56) $r['age_range'] = '46-55';
                else                $r['age_range'] = '56 and above';
                $r['date_of_birth'] = date('d M Y', strtotime($r['date_of_birth']));
            } else {
                $r['age'] = '';
                $r['age_range'] = '';
                $r['date_of_birth'] = '';
            }

            // Years of service from date_of_joining
            if (!empty($r['date_of_joining'])) {
                $yrs = (new DateTime($r['date_of_joining']))->diff(new DateTime())->y;
                $r['years_of_service'] = $yrs;
                if      ($yrs <= 5)  $r['yos_band'] = '5 yrs or below';
                elseif  ($yrs <= 10) $r['yos_band'] = '6-10 yrs';
                elseif  ($yrs <= 15) $r['yos_band'] = '11-15 yrs';
                elseif  ($yrs <= 20) $r['yos_band'] = '16-20 yrs';
                else                 $r['yos_band'] = 'over 21 yrs';
                $r['date_of_joining'] = date('d M Y', strtotime($r['date_of_joining']));
            } else {
                $r['years_of_service'] = '';
                $r['yos_band'] = '';
                $r['date_of_joining'] = '';
            }

            $id_esc = mysqli_real_escape_string($conn, $r['id_number']);

            // Academics
            $r['academics'] = [];
            $ra = mysqli_query($conn, "SELECT qualification_type, qualification_name, institution_name, course_name, award_year, completion_status, verification_status FROM employee_academics WHERE id_number='$id_esc' ORDER BY award_year DESC");
            if ($ra) while ($a = mysqli_fetch_assoc($ra)) $r['academics'][] = $a;

            // Registrations
            $r['registrations'] = [];
            $rr = mysqli_query($conn, "SELECT regulatory_body, registration_number, license_number, registration_date, expiry_date, verification_status FROM employee_professional_registrations WHERE id_number='$id_esc' ORDER BY expiry_date DESC");
            if ($rr) while ($reg = mysqli_fetch_assoc($rr)) {
                $reg['is_expired']        = (!empty($reg['expiry_date']) && strtotime($reg['expiry_date']) < time());
                $reg['expiry_date']       = !empty($reg['expiry_date'])       ? date('d M Y', strtotime($reg['expiry_date'])) : '';
                $reg['registration_date'] = !empty($reg['registration_date']) ? date('d M Y', strtotime($reg['registration_date'])) : '';
                $r['registrations'][] = $reg;
            }

            // Experience
            $r['experience'] = [];
            $re = mysqli_query($conn, "SELECT employer_name, employer_type, job_title, start_date, end_date, is_current, verification_status FROM employee_work_experience WHERE id_number='$id_esc' ORDER BY CASE WHEN is_current='Yes' THEN 0 ELSE 1 END, end_date DESC");
            if ($re) while ($e = mysqli_fetch_assoc($re)) {
                $e['start_date'] = !empty($e['start_date']) ? date('M Y', strtotime($e['start_date'])) : '';
                $e['end_date']   = $e['is_current']==='Yes' ? 'Present' : (!empty($e['end_date']) ? date('M Y', strtotime($e['end_date'])) : '');
                $r['experience'][] = $e;
            }

            // Trainings
            $r['trainings'] = [];
            $rt = mysqli_query($conn, "SELECT training_name, training_provider, training_type, start_date, end_date, funding_source FROM employee_trainings WHERE id_number='$id_esc' ORDER BY end_date DESC");
            if ($rt) while ($t = mysqli_fetch_assoc($rt)) {
                $t['start_date'] = !empty($t['start_date']) ? date('d M Y', strtotime($t['start_date'])) : '';
                $t['end_date']   = !empty($t['end_date'])   ? date('d M Y', strtotime($t['end_date']))   : '';
                $r['trainings'][] = $t;
            }

            $rows[] = $r;
        }
    }

    echo json_encode($rows);
    exit();
}

// -- Administered by -----------------------------------------------------------
$administered_by = '';
$uid = intval($_SESSION['user_id']);
$urow = mysqli_query($conn, "SELECT full_name FROM tblusers WHERE user_id = $uid");
if ($urow && mysqli_num_rows($urow) > 0) {
    $administered_by = mysqli_fetch_assoc($urow)['full_name'];
}
if (empty($administered_by) && isset($_SESSION['full_name'])) {
    $administered_by = $_SESSION['full_name'];
}

// Fetch positions for dropdown
$positions = $conn->query("SELECT positionname FROM positions ORDER BY positionname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Needs Assessment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f3fb;
            padding: 20px;
        }
        .container { max-width: 1100px; margin: 0 auto; }

        .page-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a3a8f 100%);
            color: #fff; padding: 22px 30px; border-radius: 14px;
            margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 8px 24px rgba(13,26,99,.25);
        }
        .page-header h1 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .page-header .hdr-links a {
            color: #fff; text-decoration: none; background: rgba(255,255,255,.15);
            padding: 7px 14px; border-radius: 8px; font-size: 13px; margin-left: 8px;
            transition: background .2s;
        }
        .page-header .hdr-links a:hover { background: rgba(255,255,255,.28); }

        .alert {
            padding: 13px 18px; border-radius: 9px; margin-bottom: 18px; font-size: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card {
            background: #fff; border-radius: 14px; box-shadow: 0 2px 16px rgba(0,0,0,.06);
            margin-bottom: 24px;
        }
        .card-head {
            background: linear-gradient(90deg, #0D1A63, #1a3a8f);
            color: #fff; padding: 14px 22px; border-radius: 14px 14px 0 0;
            font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;
        }
        .card-body { padding: 22px; }

        /* Search + picker */
        .search-wrap { position: relative; }
        .search-wrap input {
            width: 100%; padding: 12px 42px 12px 16px;
            border: 2px solid #e0e0e0; border-radius: 9px; font-size: 14px;
            transition: border-color .25s;
        }
        .search-wrap input:focus {
            outline: none; border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }
        .search-wrap .search-icon {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            color: #aaa; font-size: 15px;
        }
        .search-wrap .spinner {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            color: #0D1A63; font-size: 14px; display: none;
        }

        .results-list {
            position: absolute; z-index: 999; width: 100%; background: #fff;
            border: 1.5px solid #dce3f5; border-radius: 10px; margin-top: 4px;
            box-shadow: 0 8px 28px rgba(13,26,99,.15); max-height: 300px; overflow-y: auto; display: none;
        }
        .results-list .result-item {
            padding: 11px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0;
            transition: background .15s;
        }
        .results-list .result-item:last-child { border-bottom: none; }
        .results-list .result-item:hover { background: #f0f3fb; }
        .results-list .result-item .ri-name  { font-weight: 700; color: #0D1A63; font-size: 13.5px; }
        .results-list .result-item .ri-meta  { font-size: 11.5px; color: #777; margin-top: 2px; }
        .results-list .result-item .ri-badge {
            display: inline-block; font-size: 10px; background: #e8edf8; color: #0D1A63;
            border-radius: 4px; padding: 1px 6px; margin-left: 6px; font-weight: 600;
        }
        .results-list .no-results { padding: 14px 15px; color: #999; font-size: 13px; text-align: center; }

        /* Selected card */
        .selected-card {
            border: 2px solid #0D1A63; border-radius: 11px; padding: 14px 18px;
            background: linear-gradient(135deg, #f0f3fb, #fff); margin-top: 10px; display: none;
        }
        .selected-card .sc-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .selected-card .sc-title { font-weight: 700; color: #0D1A63; font-size: 15px; }
        .selected-card .sc-clear { color: #dc3545; cursor: pointer; font-size: 13px; }
        .selected-card .sc-grid {
            display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin-top: 10px;
        }
        .selected-card .sc-field label {
            font-size: 10px; text-transform: uppercase; letter-spacing: .4px;
            color: #999; font-weight: 600; display: block;
        }
        .selected-card .sc-field span { font-size: 13px; color: #333; font-weight: 500; }

        /* Preview tables */
        .preview-table {
            width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 4px;
        }
        .preview-table th {
            background: #0D1A63; color: #fff; padding: 5px 8px;
            text-align: left; font-weight: 600;
        }
        .preview-table td {
            padding: 5px 8px; border-bottom: 1px solid #e8eaf0;
        }
        .preview-table tr:nth-child(even) { background: #f4f6fb; }
        .preview-table tr:nth-child(odd) { background: #fff; }

        /* Form elements */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; margin-bottom: 7px; font-weight: 600;
            color: #444; font-size: 14px;
        }
        .form-group label i.req { color: #dc3545; font-style: normal; }

        .form-control, .form-select {
            width: 100%; padding: 10px 13px;
            border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
            background: white; font-family: inherit;
        }
        .form-control:focus, .form-select:focus {
            outline: none; border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }
        textarea.form-control { min-height: 90px; resize: vertical; }

        .form-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .radio-group {
            display: flex; flex-wrap: wrap; gap: 18px; margin-top: 6px;
        }
        .radio-option {
            display: flex; align-items: center; gap: 7px; font-size: 14px; cursor: pointer;
        }
        .radio-option input[type="radio"] {
            accent-color: #0D1A63; width: 16px; height: 16px;
        }

        .checkbox-2col {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 8px 24px; margin-top: 10px;
        }
        .checkbox-2col label {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; font-weight: 400; color: #333;
            cursor: pointer; padding: 5px 0;
        }
        .checkbox-2col input[type="checkbox"] {
            accent-color: #0D1A63; width: 16px; height: 16px;
        }

        .training-entry {
            background: #f8f9fc; border: 1px solid #e0e4f0;
            border-radius: 8px; padding: 16px 18px; margin-bottom: 12px;
        }
        .training-entry-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;
        }
        .training-entry-num {
            font-size: 12px; font-weight: 700; color: #0D1A63;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .remove-training {
            background: #fee2e2; color: #dc2626; border: none;
            border-radius: 5px; padding: 4px 10px; font-size: 12px;
            font-weight: 600; cursor: pointer;
        }
        .remove-training:hover { background: #fecaca; }

        .training-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .add-training-btn {
            background: #e8f0ff; color: #0D1A63;
            border: 2px dashed #0D1A63; border-radius: 8px;
            padding: 11px 22px; font-size: 14px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center;
            gap: 8px; width: 100%; justify-content: center;
            margin-top: 4px; transition: background .2s;
        }
        .add-training-btn:hover { background: #d0ddff; }

        .administered-box {
            background: #f0f4ff; border: 1px solid #c5d0f0;
            border-radius: 8px; padding: 16px 20px;
            display: flex; align-items: center; gap: 16px;
        }
        .administered-icon {
            width: 46px; height: 46px; background: #0D1A63;
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 20px; color: white;
        }
        .administered-name {
            font-size: 17px; font-weight: 700; color: #0D1A63;
        }
        .administered-label {
            font-size: 11px; color: #666; text-transform: uppercase;
            letter-spacing: .6px; margin-bottom: 2px;
        }

        .btn-submit {
            background: #0D1A63; color: white;
            padding: 14px 40px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 16px; font-weight: 700;
            display: block; width: 100%; max-width: 320px;
            margin: 30px auto; transition: background .2s;
        }
        .btn-submit:hover { background: #1a2a7a; }
        .btn-submit:disabled { background: #ccc; cursor: not-allowed; }

        .divider { border: none; border-top: 1px dashed #dce3f5; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">

    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> Training Needs Assessment Questionnaire</h1>
        <div class="hdr-links">
            <a href="training_dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a>
            <a href="staff_training_form.php"><i class="fas fa-plus"></i> Add Training</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Training Needs Assessment submitted successfully!</div>
    <?php endif; ?>

    <form id="tnaForm" action="submit_training_needs.php" method="POST">
        <!-- Hidden fields -->
        <input type="hidden" name="id_number"       id="h_id_number">
        <input type="hidden" name="administered_by" value="<?php echo htmlspecialchars($administered_by); ?>">
        <input type="hidden" name="facility_name"   id="h_facility_name">
        <input type="hidden" name="county_name"     id="h_county_name">
        <input type="hidden" name="subcounty_name"  id="h_subcounty_name">

        <!-- SECTION 1: Staff Selection -->
        <div class="card">
            <div class="card-head"><i class="fas fa-user-search"></i> Step 1 — Select Staff Member</div>
            <div class="card-body">
                <div class="search-wrap" id="staffSearchWrap">
                    <input type="text" id="staffSearch"
                           placeholder="Type name, ID number or phone number to search..."
                           autocomplete="off">
                    <i class="fas fa-search search-icon" id="staffSearchIcon"></i>
                    <i class="fas fa-spinner fa-spin spinner" id="staffSpinner"></i>
                    <div class="results-list" id="staffResults"></div>
                </div>

                <!-- Staff preview card with full details -->
                <div class="selected-card" id="staffCard">
                    <div class="sc-header">
                        <div class="sc-title" id="sc_name"></div>
                        <span class="sc-clear" onclick="clearStaff()"><i class="fas fa-times-circle"></i> Change</span>
                    </div>
                    <div class="sc-grid">
                        <div class="sc-field"><label>ID Number</label><span id="sc_id"></span></div>
                        <div class="sc-field"><label>Phone</label><span id="sc_phone"></span></div>
                        <div class="sc-field"><label>Email</label><span id="sc_email"></span></div>
                        <div class="sc-field"><label>Facility</label><span id="sc_facility"></span></div>
                        <div class="sc-field"><label>Department</label><span id="sc_dept"></span></div>
                        <div class="sc-field"><label>Cadre</label><span id="sc_cadre"></span></div>
                        <div class="sc-field"><label>County</label><span id="sc_county"></span></div>
                        <div class="sc-field"><label>Sub-County</label><span id="sc_sub"></span></div>
                        <div class="sc-field"><label>Sex</label><span id="sc_sex"></span></div>
                        <div class="sc-field"><label>Employment</label><span id="sc_emp"></span></div>
                        <div class="sc-field"><label>Date of Birth</label><span id="sc_dob"></span></div>
                        <div class="sc-field"><label>Date Joined</label><span id="sc_doj"></span></div>
                    </div>

                    <!-- Academic qualifications table -->
                    <div id="pv_quals_wrap" style="display:none; margin-top:14px">
                        <div style="font-size:11px; font-weight:700; color:#0D1A63; margin-bottom:8px">
                            <i class="fas fa-graduation-cap"></i> Academic Qualifications on Record
                        </div>
                        <div id="pv_quals_table"></div>
                    </div>

                    <!-- Professional registrations table -->
                    <div id="pv_regs_wrap" style="display:none; margin-top:14px">
                        <div style="font-size:11px; font-weight:700; color:#0D1A63; margin-bottom:8px">
                            <i class="fas fa-certificate"></i> Professional Registrations on Record
                        </div>
                        <div id="pv_regs_table"></div>
                    </div>

                    <!-- Work experience table -->
                    <div id="pv_exp_wrap" style="display:none; margin-top:14px">
                        <div style="font-size:11px; font-weight:700; color:#0D1A63; margin-bottom:8px">
                            <i class="fas fa-briefcase"></i> Work Experience on Record
                        </div>
                        <div id="pv_exp_table"></div>
                    </div>

                    <!-- Past trainings table -->
                    <div id="pv_train_wrap" style="display:none; margin-top:14px">
                        <div style="font-size:11px; font-weight:700; color:#0D1A63; margin-bottom:8px">
                            <i class="fas fa-chalkboard-teacher"></i> Training Records on File
                        </div>
                        <div id="pv_train_table"></div>
                    </div>

                    <!-- No records notice -->
                    <div id="pv_no_records" style="display:none; margin-top:12px; font-size:13px; color:#888; font-style:italic">
                        No academic, registration, experience or training records found yet for this staff member.
                    </div>
                </div>

                <!-- Additional fields after staff selection -->
                <div style="margin-top:20px">
                    <div class="form-group">
                        <label>Position</label>
                        <select class="form-select" name="position" id="position">
                            <option value="">-- Select Position --</option>
                            <?php
                            if ($positions) {
                                while ($row = $positions->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['positionname']) . "'>"
                                       . htmlspecialchars($row['positionname']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" class="form-control" id="designation" placeholder="e.g. Senior Clinical Officer">
                    </div>

                    <div class="form-group">
                        <label>Years in Current Job Group</label>
                        <div class="radio-group">
                            <label class="radio-option"><input type="radio" name="years_current_job_group" value="Below 5 yrs"> Below 5 yrs</label>
                            <label class="radio-option"><input type="radio" name="years_current_job_group" value="6-10 yrs"> 6-10 yrs</label>
                            <label class="radio-option"><input type="radio" name="years_current_job_group" value="11-15 yrs"> 11-15 yrs</label>
                            <label class="radio-option"><input type="radio" name="years_current_job_group" value="over 16 years"> Over 16 yrs</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JOB CONTENT SECTION -->
        <div class="card">
            <div class="card-head"><i class="fas fa-tasks"></i> Job Content</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="duties_responsibilities">i. What are your duties and responsibilities?</label>
                    <textarea id="duties_responsibilities" name="duties_responsibilities" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>ii. Do you experience any knowledge/skills related challenges in carrying out the duties and responsibilities above?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="knowledge_skills_challenges" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="knowledge_skills_challenges" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group" id="challengingDutiesGroup" style="display:none">
                    <label for="challenging_duties">iii. If YES, please identify the duties that present the greatest knowledge/skills challenges</label>
                    <textarea id="challenging_duties" name="challenging_duties" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="other_challenges">iv. What other challenges affect the performance of your duties and responsibilities?</label>
                    <textarea id="other_challenges" name="other_challenges" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>v. Do you possess all the necessary skills to perform your duties?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="possess_necessary_skills" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="possess_necessary_skills" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="skills_explanation">Please explain your response</label>
                    <textarea id="skills_explanation" name="skills_explanation" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>vi. How did you acquire the skills that enable you perform your duties?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Experience"> Experience</label>
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Attachment"> Attachment</label>
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Training"> Training</label>
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Mentorship"> Mentorship</label>
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Induction"> Induction</label>
                        <label class="radio-option"><input type="radio" name="skills_acquisition" value="Research"> Research</label>
                    </div>
                </div>

                <!-- Challenge Scale -->
                <div class="form-group" style="margin-top:24px">
                    <label><b>vii. In a scale of 1-5, rate the level of challenge in each area (1 = Least, 5 = Most Challenging)</b></label>
                    <div style="background:#f0f4ff; padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:13px; color:#0D1A63;">
                        <div style="display:flex; justify-content:space-between; font-weight:700;">
                            <span>1 – Least Challenging</span>
                            <span>5 – Most Challenging</span>
                        </div>
                    </div>

                    <?php
                    $challenges = [
                        'challenge_knowledge'   => 'a. Inadequate knowledge and skills',
                        'challenge_equipment'   => 'b. Inadequate equipment/tools',
                        'challenge_workload'    => 'c. Heavy workload',
                        'challenge_motivation'  => 'd. Motivation',
                        'challenge_teamwork'    => 'e. Teamwork',
                        'challenge_management'  => 'f. Management Support',
                        'challenge_environment' => 'g. Conducive Environment',
                    ];
                    foreach ($challenges as $fname => $flabel): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 14px; background:#f9f9fb; border-radius:6px;">
                        <div style="flex:1; font-weight:500; font-size:14px;"><?php echo $flabel; ?></div>
                        <div style="display:flex; gap:14px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div style="display:flex; flex-direction:column; align-items:center;">
                                <input type="radio" name="<?php echo $fname; ?>" value="<?php echo $i; ?>" style="accent-color:#0D1A63; width:16px; height:16px;">
                                <label style="font-size:13px; color:#666; margin-top:4px;"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-group">
                    <label for="suggestions">viii. Suggest ways of addressing the challenges above</label>
                    <textarea id="suggestions" name="suggestions" class="form-control"></textarea>
                </div>
            </div>
        </div>

        <!-- PERFORMANCE MEASURES SECTION -->
        <div class="card">
            <div class="card-head"><i class="fas fa-chart-line"></i> Performance Measures</div>
            <div class="card-body">
                <div class="form-group">
                    <label>a. Do you set targets for your Unit/Division/Department?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="set_targets" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="set_targets" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="targets_explanation">b. If No, explain</label>
                    <textarea id="targets_explanation" name="targets_explanation" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>c. Do you set own targets?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="set_own_targets" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="set_own_targets" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="own_targets_areas">d. If Yes, which areas?</label>
                    <textarea id="own_targets_areas" name="own_targets_areas" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>ii. Do you perform duties unrelated to your job?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="unrelated_duties" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="unrelated_duties" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="skills_unrelated_explanation">iii. If Yes, please specify</label>
                    <textarea id="skills_unrelated_explanation" name="skills_unrelated_explanation" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>iv. Do you possess the skills to perform those duties?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="necessary_technical_skills1" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="necessary_technical_skills1" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="necessary_technical_skills_explanation1">Explain</label>
                    <textarea id="necessary_technical_skills_explanation1" name="necessary_technical_skills_explanation" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="performance_evaluation">v. How is your performance evaluated?</label>
                    <textarea id="performance_evaluation" name="performance_evaluation" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="least_score_aspects">vi. On what aspects did you score least during your last evaluation?</label>
                    <textarea id="least_score_aspects" name="least_score_aspects" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="score_reasons">vii. Please list reasons for those scores</label>
                    <textarea id="score_reasons" name="score_reasons" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="improvement_suggestions">viii. Suggest three (3) ways of improving your performance</label>
                    <textarea id="improvement_suggestions" name="improvement_suggestions" class="form-control"></textarea>
                </div>
            </div>
        </div>

        <!-- TECHNICAL SKILL LEVELS SECTION -->
        <div class="card">
            <div class="card-head"><i class="fas fa-cogs"></i> Technical Skill Levels</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="necessary_technical_skills">i. Identify the technical skills necessary for the performance of your job</label>
                    <textarea id="necessary_technical_skills" name="necessary_technical_skills" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>ii. Do you possess the skills identified above?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="possess_technical_skills" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="possess_technical_skills" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="technical_skills_list">iii. If Yes, please list any three (3) such skills</label>
                    <textarea id="technical_skills_list" name="technical_skills_list" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label style="font-weight:700;font-size:14px">iv. From the following core competences, please tick the ones you have been trained on:</label>
                    <div class="checkbox-2col">
                        <?php
                        $competences = [
                            'research_methods'                => 'Research Methods',
                            'training_needs_assessment'       => 'Training Needs Assessment',
                            'presentations'                   => 'Presentations',
                            'proposal_report_writing'         => 'Proposal & Report Writing',
                            'human_relations_skills'          => 'Human Relations Skills',
                            'financial_management'            => 'Financial Management',
                            'monitoring_evaluation'           => 'Monitoring & Evaluation',
                            'leadership_management'           => 'Leadership & Management',
                            'communication'                   => 'Communication',
                            'negotiation_networking'          => 'Negotiation Networking',
                            'policy_formulation'              => 'Policy Formulation & Implementation',
                            'report_writing'                  => 'Report Writing',
                            'minute_writing'                  => 'Minute Writing',
                            'speech_writing'                  => 'Speech Writing',
                            'time_management'                 => 'Time Management',
                            'negotiation_skills'              => 'Negotiation Skills',
                            'guidance_counseling'             => 'Guidance & Counseling',
                            'integrity'                       => 'Integrity',
                            'performance_management'          => 'Performance Management',
                        ];
                        foreach ($competences as $fname => $flabel): ?>
                        <label>
                            <input type="checkbox" name="<?php echo $fname; ?>" value="<?php echo htmlspecialchars($flabel); ?>">
                            <?php echo htmlspecialchars($flabel); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRAINING SECTION -->
        <div class="card">
            <div class="card-head"><i class="fas fa-chalkboard-teacher"></i> Training</div>
            <div class="card-body">
                <div class="form-group">
                    <label>i. (a) Have you attended any training sponsored by the County Government?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="attended_training" value="Yes"> Yes</label>
                        <label class="radio-option"><input type="radio" name="attended_training" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="training_details">(b) If yes, please specify the area of training, duration and year</label>
                    <textarea id="training_details" name="training_details" class="form-control"></textarea>
                </div>

                <!-- Proposed training repeater -->
                <div class="form-group" style="margin-top:24px">
                    <label style="font-size:15px;font-weight:700">
                        ii. Proposed areas of training for the next three years
                        <span style="font-weight:400;font-size:13px;color:#666"> – specify institution and duration</span>
                    </label>

                    <div class="training-repeater" id="trainingRepeater">
                        <!-- First entry -->
                        <div class="training-entry" data-entry="1">
                            <div class="training-entry-header">
                                <span class="training-entry-num">Training Option 1</span>
                                <button type="button" class="remove-training" onclick="removeTraining(this)" style="display:none">? Remove</button>
                            </div>
                            <div class="training-grid">
                                <div class="form-group">
                                    <label>Area of Training</label>
                                    <input type="text" name="proposed_training_area[]" class="form-control" placeholder="e.g. Advanced Clinical Care">
                                </div>
                                <div class="form-group">
                                    <label>Institution</label>
                                    <input type="text" name="proposed_training_institution[]" class="form-control" placeholder="e.g. Kenya Medical Training College">
                                </div>
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" name="proposed_training_duration[]" class="form-control" placeholder="e.g. 3 months / 2 weeks">
                                </div>
                                <div class="form-group">
                                    <label>Preferred Year</label>
                                    <select name="proposed_training_year[]" class="form-select">
                                        <option value="">-- Select Year --</option>
                                        <?php for ($y = date('Y'); $y <= date('Y') + 4; $y++): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="add-training-btn" id="addTrainingBtn">
                        <i class="fas fa-plus"></i> Add Another Training Option
                    </button>
                </div>

                <!-- Administered By -->
                <div class="form-group" style="margin-top:30px; border-top:2px solid #eef0f7; padding-top:22px">
                    <label style="font-size:14px;font-weight:700;color:#444">Administered By</label>
                    <div class="administered-box">
                        <div class="administered-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="administered-label">Logged-in Officer</div>
                            <div class="administered-name"><?php echo htmlspecialchars($administered_by ?: 'Not identified – please log in'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:18px">
                    <label for="submission_date">Submission Date</label>
                    <input type="date" name="submission_date" id="submission_date" class="form-control"
                           style="max-width:220px" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn" disabled>
            <i class="fas fa-save"></i> Submit Assessment
        </button>
    </form>
</div>

<script>
// Debounce function
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

let selectedStaff = null;

function checkSubmit() {
    document.getElementById('submitBtn').disabled = !selectedStaff;
}

// Helper: build mini table
function miniTable(headers, rows) {
    let t = '<table class="preview-table">';
    t += '<thead><tr>';
    headers.forEach(h => t += `<th>${h}</th>`);
    t += '</tr></thead><tbody>';
    rows.forEach((cells, ri) => {
        t += '<tr>';
        cells.forEach(c => t += `<td>${c || '—'}</td>`);
        t += '</tr>';
    });
    return t + '</tbody></table>';
}

// Staff search
const staffInput = document.getElementById('staffSearch');
const staffResults = document.getElementById('staffResults');
const staffSpinner = document.getElementById('staffSpinner');
const staffIcon = document.getElementById('staffSearchIcon');

staffInput.addEventListener('input', debounce(async function() {
    const q = staffInput.value.trim();

    if (q.length < 2) {
        staffResults.style.display = 'none';
        return;
    }

    staffSpinner.style.display = 'block';
    staffIcon.style.display = 'none';
    staffResults.innerHTML = '';

    try {
        const response = await fetch(`training_needs_assessment_questionaire.php?ajax=search_staff&q=${encodeURIComponent(q)}`);
        const data = await response.json();

        staffSpinner.style.display = 'none';
        staffIcon.style.display = 'block';

        if (!data || data.length === 0) {
            staffResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i> No active staff found</div>';
        } else {
            staffResults.innerHTML = data.map(r => {
                const name = [r.first_name, r.other_name, r.last_name].filter(Boolean).join(' ');
                return `<div class="result-item" onclick='selectStaff(${JSON.stringify(r).replace(/'/g, "\\'")})'>
                    <div class="ri-name">${name} <span class="ri-badge">${r.id_number || ''}</span></div>
                    <div class="ri-meta">
                        <i class="fas fa-hospital"></i> ${r.facility_name || '—'}
                        &nbsp;|&nbsp; <i class="fas fa-phone"></i> ${r.staff_phone || '—'}
                        &nbsp;|&nbsp; ${r.county_name || '—'}
                    </div>
                </div>`;
            }).join('');
        }
        staffResults.style.display = 'block';

    } catch (error) {
        console.error('Error:', error);
        staffSpinner.style.display = 'none';
        staffIcon.style.display = 'block';
        staffResults.innerHTML = '<div class="no-results"><i class="fas fa-exclamation-triangle"></i> Error loading results</div>';
        staffResults.style.display = 'block';
    }
}, 350));

window.selectStaff = function(r) {
    selectedStaff = r;
    document.getElementById('h_id_number').value = r.id_number;
    document.getElementById('h_facility_name').value = r.facility_name || '';
    document.getElementById('h_county_name').value = r.county_name || '';
    document.getElementById('h_subcounty_name').value = r.subcounty_name || '';

    const name = [r.first_name, r.other_name, r.last_name].filter(Boolean).join(' ');
    document.getElementById('sc_name').textContent = name;
    document.getElementById('sc_id').textContent = r.id_number || '—';
    document.getElementById('sc_phone').textContent = r.staff_phone || '—';
    document.getElementById('sc_email').textContent = r.email || '—';
    document.getElementById('sc_facility').textContent = r.facility_name || '—';
    document.getElementById('sc_dept').textContent = r.department_name || '—';
    document.getElementById('sc_cadre').textContent = r.cadre_name || '—';
    document.getElementById('sc_county').textContent = r.county_name || '—';
    document.getElementById('sc_sub').textContent = r.subcounty_name || '—';
    document.getElementById('sc_sex').textContent = r.sex || '—';
    document.getElementById('sc_emp').textContent = r.employment_status || '—';
    document.getElementById('sc_dob').textContent = r.date_of_birth || '—';
    document.getElementById('sc_doj').textContent = r.date_of_joining || '—';

    document.getElementById('staffCard').style.display = 'block';
    staffResults.style.display = 'none';
    staffInput.value = name;

    let anyRecord = false;

    // Academics
    if (r.academics && r.academics.length > 0) {
        anyRecord = true;
        const acRows = r.academics.map(a => [
            a.qualification_type || '—',
            a.course_name || a.qualification_name || '—',
            a.institution_name || '—',
            a.award_year || '—',
            a.completion_status || '—',
            a.verification_status || '—'
        ]);
        document.getElementById('pv_quals_table').innerHTML = miniTable(
            ['Qualification','Course / Name','Institution','Year','Status','Verified'],
            acRows
        );
        document.getElementById('pv_quals_wrap').style.display = 'block';
    } else {
        document.getElementById('pv_quals_wrap').style.display = 'none';
    }

    // Registrations
    if (r.registrations && r.registrations.length > 0) {
        anyRecord = true;
        const regRows = r.registrations.map(reg => {
            const expFlag = reg.is_expired ? ' (EXPIRED)' : '';
            return [
                reg.regulatory_body || '—',
                reg.registration_number || '—',
                reg.license_number || '—',
                reg.registration_date || '—',
                (reg.expiry_date || '—') + expFlag,
                reg.verification_status || '—'
            ];
        });
        document.getElementById('pv_regs_table').innerHTML = miniTable(
            ['Regulatory Body','Reg. No.','Licence No.','Reg. Date','Expiry','Verified'],
            regRows
        );
        document.getElementById('pv_regs_wrap').style.display = 'block';
    } else {
        document.getElementById('pv_regs_wrap').style.display = 'none';
    }

    // Experience
    if (r.experience && r.experience.length > 0) {
        anyRecord = true;
        const expRows = r.experience.map(e => [
            e.employer_name || '—',
            e.job_title || '—',
            e.employer_type || '—',
            (e.start_date || '—') + ' – ' + (e.end_date || '—'),
            e.verification_status || '—'
        ]);
        document.getElementById('pv_exp_table').innerHTML = miniTable(
            ['Employer','Job Title','Type','Period','Verified'],
            expRows
        );
        document.getElementById('pv_exp_wrap').style.display = 'block';
    } else {
        document.getElementById('pv_exp_wrap').style.display = 'none';
    }

    // Trainings
    if (r.trainings && r.trainings.length > 0) {
        anyRecord = true;
        const trnRows = r.trainings.map(tr => [
            tr.training_name || '—',
            tr.training_provider || '—',
            tr.training_type || '—',
            (tr.start_date || '—') + ' – ' + (tr.end_date || '—'),
            tr.funding_source || '—'
        ]);
        document.getElementById('pv_train_table').innerHTML = miniTable(
            ['Training','Provider','Type','Period','Funded By'],
            trnRows
        );
        document.getElementById('pv_train_wrap').style.display = 'block';
    } else {
        document.getElementById('pv_train_wrap').style.display = 'none';
    }

    document.getElementById('pv_no_records').style.display = anyRecord ? 'none' : 'block';

    checkSubmit();
};

function clearStaff() {
    selectedStaff = null;
    document.getElementById('h_id_number').value = '';
    document.getElementById('h_facility_name').value = '';
    document.getElementById('h_county_name').value = '';
    document.getElementById('h_subcounty_name').value = '';

    document.getElementById('staffCard').style.display = 'none';
    staffInput.value = '';

    document.getElementById('pv_quals_wrap').style.display = 'none';
    document.getElementById('pv_regs_wrap').style.display = 'none';
    document.getElementById('pv_exp_wrap').style.display = 'none';
    document.getElementById('pv_train_wrap').style.display = 'none';
    document.getElementById('pv_no_records').style.display = 'none';

    checkSubmit();
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#staffSearchWrap')) {
        staffResults.style.display = 'none';
    }
});

// Challenge duties toggle
document.querySelectorAll('input[name="knowledge_skills_challenges"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'Yes') {
            document.getElementById('challengingDutiesGroup').style.display = 'block';
        } else {
            document.getElementById('challengingDutiesGroup').style.display = 'none';
            document.getElementById('challenging_duties').value = '';
        }
    });
});

// Training repeater
let entryCount = 1;

document.getElementById('addTrainingBtn').addEventListener('click', function() {
    entryCount++;
    const yearOptions = buildYearOptions();
    const html = `
    <div class="training-entry" data-entry="${entryCount}">
        <div class="training-entry-header">
            <span class="training-entry-num">Training Option ${entryCount}</span>
            <button type="button" class="remove-training" onclick="removeTraining(this)">? Remove</button>
        </div>
        <div class="training-grid">
            <div class="form-group">
                <label>Area of Training</label>
                <input type="text" name="proposed_training_area[]" class="form-control" placeholder="e.g. Advanced Clinical Care">
            </div>
            <div class="form-group">
                <label>Institution</label>
                <input type="text" name="proposed_training_institution[]" class="form-control" placeholder="e.g. Kenya Medical Training College">
            </div>
            <div class="form-group">
                <label>Duration</label>
                <input type="text" name="proposed_training_duration[]" class="form-control" placeholder="e.g. 3 months / 2 weeks">
            </div>
            <div class="form-group">
                <label>Preferred Year</label>
                <select name="proposed_training_year[]" class="form-select">
                    <option value="">-- Select Year --</option>
                    ${yearOptions}
                </select>
            </div>
        </div>
    </div>`;

    document.getElementById('trainingRepeater').insertAdjacentHTML('beforeend', html);
});

function buildYearOptions() {
    let opts = '';
    const cur = new Date().getFullYear();
    for (let y = cur; y <= cur + 4; y++) {
        opts += `<option value="${y}">${y}</option>`;
    }
    return opts;
}

window.removeTraining = function(btn) {
    const entry = btn.closest('.training-entry');
    if (document.querySelectorAll('.training-entry').length <= 1) {
        alert('At least one training option is required.');
        return;
    }
    entry.remove();

    // Renumber remaining entries
    document.querySelectorAll('.training-entry').forEach((el, index) => {
        el.querySelector('.training-entry-num').textContent = 'Training Option ' + (index + 1);
        el.dataset.entry = index + 1;
    });
    entryCount = document.querySelectorAll('.training-entry').length;
};

// Form validation
document.getElementById('tnaForm').addEventListener('submit', function(e) {
    if (!selectedStaff) {
        e.preventDefault();
        alert('Please select a staff member before submitting.');
        staffInput.focus();
        return false;
    }
    return true;
});

// Initialize
document.querySelector('.training-entry .remove-training').style.display = 'none';
</script>
</body>
</html>