<?php
// PUBLIC PAGE — No login required.
// Accessed via QR code: participant_form.php?token=XXXXXX

include '../includes/config.php';  // provides $conn

$token    = isset($_GET['token'])  ? trim($_GET['token'])  : '';
$training = null;
$error    = '';
$success  = false;
$step     = (int)($_POST['step'] ?? 1);

// ── Validate token ──
if (empty($token)) {
    $error = 'Invalid or missing QR code. Please scan again.';
} else {
    $safe_token = mysqli_real_escape_string($conn, $token);
    $q = mysqli_query($conn,
        "SELECT pt.*, c.course_name, tt.trainingtype_name, tl.location_name,
                co.county_name, sc.sub_county_name
         FROM planned_trainings pt
         LEFT JOIN courses            c  ON pt.course_id      = c.course_id
         LEFT JOIN trainingtypes      tt ON pt.trainingtype_id = tt.trainingtype_id
         LEFT JOIN training_locations tl ON pt.location_id    = tl.location_id
         LEFT JOIN counties           co ON pt.county_id      = co.county_id
         LEFT JOIN sub_counties       sc ON pt.subcounty_id   = sc.sub_county_id
         WHERE pt.qr_token = '$safe_token' AND pt.status IN ('planned','active')"
    );
    if (!$q || mysqli_num_rows($q) === 0) {
        $error = 'This training event is not accepting registrations or the QR code is invalid.';
    } else {
        $training = mysqli_fetch_assoc($q);
    }
}

// ── Check capacity ──
if ($training) {
    $cnt = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS n FROM training_registrations WHERE training_id={$training['training_id']}"
    ));
    if ((int)$cnt['n'] >= (int)$training['max_participants']) {
        $error = 'Sorry, this training has reached its maximum capacity.';
        $training = null;
    }
}

// ── Handle form POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training && !$error) {

    $esc = fn($v) => mysqli_real_escape_string($conn, trim($v ?? ''));

    $first_name       = $esc($_POST['first_name']);
    $last_name        = $esc($_POST['last_name']);
    $gender           = $esc($_POST['gender']);
    $dob              = $esc($_POST['date_of_birth']);
    $id_number        = $esc($_POST['id_number']);
    $phone            = $esc($_POST['phone']);
    $email            = $esc($_POST['email']);
    $facility         = $esc($_POST['facility_name']);
    $department       = $esc($_POST['department']);
    $cadre            = $esc($_POST['cadre']);
    $employment_type  = $esc($_POST['employment_type']);
    $education        = $esc($_POST['highest_education']);
    $county           = $esc($_POST['county']);
    $subcounty        = $esc($_POST['subcounty']);
    $disability       = $esc($_POST['disability_status']);
    $disability_type  = ($disability === 'Yes') ? $esc($_POST['disability_type']) : '';
    $consent          = isset($_POST['consent']) ? 1 : 0;

    $ip          = $_SERVER['REMOTE_ADDR'] ?? '';
    $device_info = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $tid         = (int)$training['training_id'];

    // Basic validation
    $form_errors = [];
    if (!$first_name)  $form_errors[] = 'First name is required.';
    if (!$last_name)   $form_errors[] = 'Last name is required.';
    if (!$gender)      $form_errors[] = 'Gender is required.';
    if (!$id_number)   $form_errors[] = 'ID number is required.';
    if (!$facility)    $form_errors[] = 'Facility / organisation is required.';
    if (!$cadre)       $form_errors[] = 'Cadre / role is required.';
    if (!$consent)     $form_errors[] = 'You must consent to data collection.';

    // Check duplicate
    if (!$form_errors && $id_number) {
        $dup = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT registration_id FROM training_registrations WHERE training_id=$tid AND id_number='$id_number'"
        ));
        if ($dup) $form_errors[] = 'A registration with this ID number already exists for this training.';
    }

    if (!$form_errors) {
        $sql = "INSERT INTO training_registrations (
                    training_id, first_name, last_name, gender, date_of_birth,
                    id_number, phone, email, facility_name, department, cadre,
                    employment_type, highest_education, county, subcounty,
                    disability_status, disability_type, consent_given,
                    ip_address, device_info
                ) VALUES (
                    $tid, '$first_name', '$last_name', '$gender',
                    " . ($dob ? "'$dob'" : 'NULL') . ",
                    '$id_number', '$phone', '$email', '$facility', '$department',
                    '$cadre', '$employment_type', '$education', '$county', '$subcounty',
                    '$disability', '$disability_type', $consent,
                    '$ip', '" . mysqli_real_escape_string($conn, $device_info) . "'
                )";

        if (mysqli_query($conn, $sql)) {
            $success = true;
        } else {
            $error = 'Database error. Please try again.';
        }
    } else {
        $error = implode('<br>', $form_errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $training ? 'Register — ' . htmlspecialchars($training['course_name']) : 'Training Registration'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy:    #0D1A63;
            --accent:  #00C2FF;
            --success: #10b981;
            --danger:  #ef4444;
            --warn:    #f59e0b;
            --bg:      #f0f4ff;
            --card:    #ffffff;
            --border:  #dde3f0;
            --text:    #1a2340;
            --muted:   #6b7aa1;
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

        body {
            background: var(--bg);
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: var(--text);
            min-height: 100vh;
        }

        /* ── HERO HEADER ── */
        .odk-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1a2f99 100%);
            color: #fff;
            padding: 28px 20px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .odk-header::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 60% 80% at 80% 20%, rgba(0,194,255,.15) 0%, transparent 70%);
        }
        .odk-logo {
            width: 56px; height: 56px;
            background: rgba(255,255,255,.15);
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 14px;
            backdrop-filter: blur(8px);
        }
        .odk-header h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 6px; position:relative;z-index:1; }
        .odk-header .subtitle { font-size: .85rem; opacity: .75; position:relative;z-index:1; }

        /* ── TRAINING CARD ── */
        .training-info-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(13,26,99,.15);
            margin: -44px 16px 24px;
            padding: 20px;
            position: relative; z-index: 2;
        }
        .training-code {
            display: inline-block;
            background: #f0f4ff; color: var(--navy);
            border-radius: 6px; padding: 3px 10px;
            font-size: .72rem; font-weight: 700; letter-spacing: .5px;
            margin-bottom: 10px;
        }
        .training-info-row {
            display: flex; align-items: center; gap: 8px;
            font-size: .82rem; color: var(--muted); margin-bottom: 7px;
        }
        .training-info-row i { color: var(--navy); width: 16px; text-align: center; }
        .training-info-row strong { color: var(--text); }

        /* ── PAGE WRAPPER ── */
        .page-wrap { max-width: 560px; margin: 0 auto; padding: 0 16px 40px; }

        /* ── PROGRESS ── */
        .progress-wrap { margin-bottom: 24px; }
        .progress-label {
            display: flex; justify-content: space-between;
            font-size: .75rem; color: var(--muted); margin-bottom: 6px; font-weight: 600;
        }
        .progress-bar {
            height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; border-radius: 4px;
            background: linear-gradient(90deg, var(--navy), var(--accent));
            transition: width .4s ease;
        }

        /* ── FORM SECTION CARDS ── */
        .form-section {
            background: var(--card); border-radius: 16px;
            padding: 24px 20px; margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(13,26,99,.07);
            border: 1.5px solid rgba(13,26,99,.05);
        }
        .section-title {
            font-size: .8rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--navy);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 20px; padding-bottom: 12px;
            border-bottom: 2px solid #f0f4ff;
        }
        .section-title i { opacity: .7; }

        /* ── FORM ELEMENTS ── */
        .form-group { margin-bottom: 18px; }
        .form-group:last-child { margin-bottom: 0; }
        label {
            display: block; font-size: .78rem; font-weight: 600;
            color: var(--muted); margin-bottom: 7px;
            text-transform: uppercase; letter-spacing: .4px;
        }
        label .req { color: var(--danger); margin-left: 2px; }
        .form-control {
            width: 100%;
            padding: 13px 15px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: .9rem; color: var(--text);
            background: #fff; outline: none;
            transition: border-color .2s, box-shadow .2s;
            -webkit-appearance: none;
        }
        .form-control:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(13,26,99,.12);
        }
        select.form-control { cursor: pointer; }
        .form-control::placeholder { color: #aab0c0; }

        /* ── RADIO / TOGGLE GROUP ── */
        .radio-group {
            display: flex; gap: 10px; flex-wrap: wrap;
        }
        .radio-option { flex: 1; min-width: 100px; }
        .radio-option input { display: none; }
        .radio-option label {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 11px 10px; border: 2px solid var(--border);
            border-radius: 11px; cursor: pointer; font-size: .85rem;
            font-weight: 600; color: var(--muted); text-transform: none;
            letter-spacing: 0; transition: all .2s;
        }
        .radio-option input:checked + label {
            border-color: var(--navy);
            background: #f0f4ff; color: var(--navy);
        }

        /* ── DISABILITY TOGGLE ── */
        #disability-extra { display: none; margin-top: 14px; }

        /* ── CONSENT BOX ── */
        .consent-box {
            background: #f8faff; border: 2px solid #d0d9f5;
            border-radius: 12px; padding: 16px;
        }
        .consent-check {
            display: flex; gap: 12px; align-items: flex-start; cursor: pointer;
        }
        .consent-check input[type="checkbox"] {
            width: 20px; height: 20px; margin-top: 2px;
            accent-color: var(--navy); cursor: pointer; flex-shrink: 0;
        }
        .consent-text { font-size: .82rem; color: var(--muted); line-height: 1.6; }
        .consent-text strong { color: var(--text); }

        /* ── SUBMIT BUTTON ── */
        .submit-btn {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, var(--navy), #1a2f99);
            color: #fff; border: none; border-radius: 14px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(13,26,99,.3);
            margin-top: 24px;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(13,26,99,.35); }
        .submit-btn:active { transform: translateY(0); }

        /* ── ERROR / SUCCESS ── */
        .alert {
            padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
            font-size: .875rem; line-height: 1.6;
            display: flex; gap: 10px;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-error   { background: #fef2f2; color: #991b1b; border: 1.5px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1.5px solid #a7f3d0; }

        /* ── SUCCESS SCREEN ── */
        .success-screen {
            text-align: center; padding: 40px 20px;
        }
        .success-circle {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg, var(--success), #34d399);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: #fff; margin-bottom: 20px;
            box-shadow: 0 8px 30px rgba(16,185,129,.3);
        }
        .success-screen h2 { font-size: 1.5rem; color: var(--navy); margin-bottom: 10px; }
        .success-screen p  { color: var(--muted); line-height: 1.7; font-size: .9rem; margin-bottom: 6px; }
        .reg-summary {
            background: #f0f4ff; border-radius: 12px; padding: 16px 20px;
            margin: 20px 0; text-align: left;
        }
        .reg-summary-row {
            display: flex; justify-content: space-between; align-items: center;
            font-size: .82rem; padding: 5px 0; border-bottom: 1px solid #dde3f0;
        }
        .reg-summary-row:last-child { border-bottom: none; }
        .reg-summary-row .label { color: var(--muted); }
        .reg-summary-row .val   { font-weight: 600; color: var(--navy); }

        /* ── ERROR SCREEN ── */
        .error-screen {
            text-align: center; padding: 40px 20px;
        }
        .error-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: #fef2f2; display: inline-flex;
            align-items: center; justify-content: center;
            font-size: 2rem; color: var(--danger); margin-bottom: 16px;
        }

        /* ── FOOTER ── */
        .odk-footer {
            text-align: center; padding: 24px 20px;
            color: var(--muted); font-size: .75rem;
        }
        .odk-footer img { height: 20px; opacity: .4; vertical-align: middle; margin-right: 6px; }

        @media (max-width: 400px) {
            .form-section { padding: 18px 14px; }
            .radio-group { gap: 7px; }
        }
    </style>
</head>
<body>

<!-- ── HEADER ── -->
<div class="odk-header">
    <div class="odk-logo" style="position:relative;z-index:1;"><i class="fas fa-clipboard-list"></i></div>
    <h1><?php echo $training ? htmlspecialchars($training['course_name']) : 'Training Registration'; ?></h1>
    <div class="subtitle">Participant Self-Registration Form</div>
</div>

<div class="page-wrap">

    <!-- ── TRAINING INFO CARD ── -->
    <?php if ($training): ?>
    <div class="training-info-card">
        <div class="training-code"><?php echo htmlspecialchars($training['training_code']); ?></div>
        <div class="training-info-row">
            <i class="fas fa-graduation-cap"></i>
            <span><strong><?php echo htmlspecialchars($training['course_name']); ?></strong></span>
        </div>
        <div class="training-info-row">
            <i class="fas fa-tag"></i>
            <span><?php echo htmlspecialchars($training['trainingtype_name'] ?? '—'); ?></span>
        </div>
        <div class="training-info-row">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('d M Y', strtotime($training['start_date'])); ?> – <?php echo date('d M Y', strtotime($training['end_date'])); ?></span>
        </div>
        <div class="training-info-row">
            <i class="fas fa-map-marker-alt"></i>
            <span><?php echo htmlspecialchars(($training['location_name'] ?? '') . ($training['venue_details'] ? ', ' . $training['venue_details'] : '')); ?></span>
        </div>
        <?php if ($training['county_name']): ?>
        <div class="training-info-row">
            <i class="fas fa-globe-africa"></i>
            <span><?php echo htmlspecialchars($training['county_name'] . ($training['sub_county_name'] ? ' · ' . $training['sub_county_name'] : '')); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($training['facilitator_name']): ?>
        <div class="training-info-row">
            <i class="fas fa-chalkboard-teacher"></i>
            <span><?php echo htmlspecialchars($training['facilitator_name']); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── ERROR SCREEN ── -->
    <?php if ($error && !$success): ?>
    <div class="form-section">
        <div class="error-screen">
            <div class="error-circle"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 style="color:#991b1b;margin-bottom:10px;">Registration Unavailable</h3>
            <p style="color:var(--muted);"><?php echo $error; ?></p>
        </div>
    </div>

    <!-- ── SUCCESS SCREEN ── -->
    <?php elseif ($success): ?>
    <div class="form-section">
        <div class="success-screen">
            <div class="success-circle"><i class="fas fa-check"></i></div>
            <h2>Registration Complete!</h2>
            <p>You have been successfully registered for this training.</p>
            <div class="reg-summary">
                <div class="reg-summary-row"><span class="label">Name</span><span class="val"><?php echo htmlspecialchars($_POST['first_name'] . ' ' . $_POST['last_name']); ?></span></div>
                <div class="reg-summary-row"><span class="label">ID Number</span><span class="val"><?php echo htmlspecialchars($_POST['id_number'] ?? '—'); ?></span></div>
                <div class="reg-summary-row"><span class="label">Training</span><span class="val"><?php echo htmlspecialchars($training['course_name']); ?></span></div>
                <div class="reg-summary-row"><span class="label">Dates</span><span class="val"><?php echo date('d M Y', strtotime($training['start_date'])) . ' – ' . date('d M Y', strtotime($training['end_date'])); ?></span></div>
            </div>
            <p style="font-size:.8rem;">Please report to the venue at the specified time. You do not need to bring a printed copy.</p>
        </div>
    </div>

    <!-- ── REGISTRATION FORM ── -->
    <?php elseif ($training): ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
    <?php endif; ?>

    <form method="POST" action="?token=<?php echo urlencode($token); ?>" id="regForm" novalidate>

        <!-- ── Step 1: Personal Info ── -->
        <div class="form-section">
            <div class="section-title"><i class="fas fa-user"></i> Personal Information</div>

            <div class="form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" class="form-control" placeholder="e.g. Amina" required
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" autocomplete="given-name">
            </div>

            <div class="form-group">
                <label>Last Name / Surname <span class="req">*</span></label>
                <input type="text" name="last_name" class="form-control" placeholder="e.g. Ochieng" required
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" autocomplete="family-name">
            </div>

            <div class="form-group">
                <label>Gender <span class="req">*</span></label>
                <div class="radio-group">
                    <?php foreach (['Male','Female','Prefer not to say'] as $g): ?>
                    <div class="radio-option">
                        <input type="radio" name="gender" id="g_<?php echo $g; ?>" value="<?php echo $g; ?>"
                               <?php echo (($_POST['gender'] ?? '') === $g) ? 'checked' : ''; ?> required>
                        <label for="g_<?php echo $g; ?>">
                            <i class="fas fa-<?php echo $g==='Male' ? 'mars' : ($g==='Female' ? 'venus' : 'circle'); ?>"></i>
                            <?php echo $g; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"
                       max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label>National ID / Passport Number <span class="req">*</span></label>
                <input type="text" name="id_number" class="form-control" placeholder="e.g. 12345678" required
                       value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>" inputmode="numeric">
            </div>
        </div>

        <!-- ── Step 2: Contact ── -->
        <div class="form-section">
            <div class="section-title"><i class="fas fa-phone"></i> Contact Details</div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="+254 7XX XXX XXX"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" autocomplete="tel">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email">
            </div>

            <div class="form-group">
                <label>County</label>
                <input type="text" name="county" class="form-control" placeholder="e.g. Nairobi"
                       value="<?php echo htmlspecialchars($_POST['county'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Sub-County</label>
                <input type="text" name="subcounty" class="form-control" placeholder="e.g. Westlands"
                       value="<?php echo htmlspecialchars($_POST['subcounty'] ?? ''); ?>">
            </div>
        </div>

        <!-- ── Step 3: Professional ── -->
        <div class="form-section">
            <div class="section-title"><i class="fas fa-briefcase"></i> Professional Details</div>

            <div class="form-group">
                <label>Facility / Organisation <span class="req">*</span></label>
                <input type="text" name="facility_name" class="form-control"
                       placeholder="e.g. Kenyatta National Hospital" required
                       value="<?php echo htmlspecialchars($_POST['facility_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" class="form-control"
                       placeholder="e.g. Maternity, Community Health"
                       value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Cadre / Job Title <span class="req">*</span></label>
                <input type="text" name="cadre" class="form-control"
                       placeholder="e.g. Clinical Officer, Nurse, CHW" required
                       value="<?php echo htmlspecialchars($_POST['cadre'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Employment Type</label>
                <select name="employment_type" class="form-control">
                    <option value="">Select…</option>
                    <?php foreach (['Permanent','Contract','Volunteer','Other'] as $e): ?>
                    <option value="<?php echo $e; ?>" <?php echo (($_POST['employment_type'] ?? '')===$e)?'selected':''; ?>>
                        <?php echo $e; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Highest Level of Education</label>
                <select name="highest_education" class="form-control">
                    <option value="">Select…</option>
                    <?php foreach (["Certificate","Diploma","Bachelor's Degree","Master's Degree","PhD","Other"] as $ed): ?>
                    <option value="<?php echo $ed; ?>" <?php echo (($_POST['highest_education'] ?? '')===$ed)?'selected':''; ?>>
                        <?php echo $ed; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ── Step 4: Inclusion ── -->
        <div class="form-section">
            <div class="section-title"><i class="fas fa-universal-access"></i> Inclusion Data</div>

            <div class="form-group">
                <label>Do you live with a disability? <span class="req">*</span></label>
                <div class="radio-group">
                    <?php foreach (['No','Yes'] as $d): ?>
                    <div class="radio-option">
                        <input type="radio" name="disability_status" id="dis_<?php echo $d; ?>" value="<?php echo $d; ?>"
                               <?php echo (($_POST['disability_status'] ?? 'No')===$d)?'checked':''; ?>
                               onchange="toggleDisability(this.value)">
                        <label for="dis_<?php echo $d; ?>">
                            <i class="fas fa-<?php echo $d==='Yes' ? 'check' : 'times'; ?>"></i> <?php echo $d; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="disability-extra">
                    <label style="margin-top:12px;">Please describe the type of disability</label>
                    <input type="text" name="disability_type" class="form-control"
                           placeholder="e.g. Visual impairment, Mobility"
                           value="<?php echo htmlspecialchars($_POST['disability_type'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- ── Consent ── -->
        <div class="form-section">
            <div class="section-title"><i class="fas fa-shield-alt"></i> Data Consent</div>
            <div class="consent-box">
                <label class="consent-check">
                    <input type="checkbox" name="consent" id="consent" required
                           <?php echo isset($_POST['consent']) ? 'checked' : ''; ?>>
                    <div class="consent-text">
                        I consent to the collection and use of my personal data for the purpose of training administration and M&E reporting by <strong><?php echo htmlspecialchars($training['county_name'] ?? 'the programme team'); ?></strong>. My data will be kept securely and not shared with unauthorised parties.
                    </div>
                </label>
            </div>
        </div>

        <input type="hidden" name="step" value="2">
        <button type="submit" class="submit-btn" id="submitBtn">
            <i class="fas fa-paper-plane"></i> Submit Registration
        </button>

    </form>
    <?php endif; ?>

</div><!-- /page-wrap -->

<div class="odk-footer">
    Powered by <strong>Vuqa</strong> · Secure & Paperless
</div>

<script>
function toggleDisability(val) {
    document.getElementById('disability-extra').style.display = val === 'Yes' ? 'block' : 'none';
}

// Pre-show if page reloaded with Yes selected
(function(){
    const checked = document.querySelector('input[name="disability_status"]:checked');
    if (checked && checked.value === 'Yes') toggleDisability('Yes');
})();

// Prevent double-submit
const form = document.getElementById('regForm');
if (form) {
    form.addEventListener('submit', function(){
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    });
}
</script>
</body>
</html>
