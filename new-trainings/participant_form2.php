<?php
// PUBLIC PAGE — No login required.
// Accessed via QR code: participant_form.php?token=XXXXXX

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

include '../includes/config.php';

// Check if connection exists
if (!isset($conn) || !$conn) {
    die("System error. Please try again later.");
}

$token    = isset($_GET['token'])  ? trim($_GET['token'])  : '';
$training = null;
$error    = '';
$success  = false;
$message_type = '';

// ── Validate token ──
if (empty($token)) {
    $error = 'Invalid or missing QR code. Please scan again.';
} else {
    $safe_token = mysqli_real_escape_string($conn, $token);

    $query = "SELECT pt.*, c.course_name, tt.trainingtype_name, tl.location_name,
                     co.county_name, sc.sub_county_name
              FROM planned_trainings pt
              LEFT JOIN courses c ON pt.course_id = c.course_id
              LEFT JOIN trainingtypes tt ON pt.trainingtype_id = tt.trainingtype_id
              LEFT JOIN training_locations tl ON pt.location_id = tl.location_id
              LEFT JOIN counties co ON pt.county_id = co.county_id
              LEFT JOIN sub_counties sc ON pt.subcounty_id = sc.sub_county_id
              WHERE pt.qr_token = '$safe_token' AND pt.status IN ('planned','active')";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        $error = 'System error. Please try again.';
    } else if (mysqli_num_rows($result) === 0) {
        $error = 'This training event is not accepting registrations or the QR code is invalid.';
    } else {
        $training = mysqli_fetch_assoc($result);

        // Check capacity
        $tid = (int)$training['training_id'];
        $count_query = mysqli_query($conn, "SELECT COUNT(*) AS n FROM training_registrations WHERE training_id = $tid");
        $count_result = mysqli_fetch_assoc($count_query);

        if ($count_result && (int)$count_result['n'] >= (int)$training['max_participants']) {
            $error = 'Sorry, this training has reached its maximum capacity (' . $training['max_participants'] . ' participants).';
            $training = null;
        }
    }
}

// ── AJAX: fetch participant by ID number ──
if (isset($_GET['check_id']) && isset($_GET['id_number']) && $training) {
    header('Content-Type: application/json');
    $id_number = trim($_GET['id_number']);
    if (preg_match('/^\d{8}$/', $id_number)) {
        $check_query = mysqli_query($conn, "SELECT * FROM training_registrations WHERE id_number = '$id_number' ORDER BY registration_id DESC LIMIT 1");
        if ($check_query && mysqli_num_rows($check_query) > 0) {
            echo json_encode(['exists' => true, 'data' => mysqli_fetch_assoc($check_query)]);
        } else {
            echo json_encode(['exists' => false]);
        }
    }
    exit();
}

// ── Fetch dropdown data ──
$organizations = mysqli_query($conn, "SELECT org_id, org_name FROM organizations ORDER BY org_name");
$counties = mysqli_query($conn, "SELECT county_id, county_name FROM counties ORDER BY county_name");
$departments = mysqli_query($conn, "SELECT department_id, department_name FROM departments ORDER BY department_name");
$cadres = mysqli_query($conn, "SELECT cadre_id, cadre_name FROM cadres ORDER BY cadre_name");
$education_levels = mysqli_query($conn, "SELECT edu_id, edu_name FROM education_levels ORDER BY edu_name");

// ── Handle form POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training && !$error) {
    $id_number = trim($_POST['id_number'] ?? '');
    if (preg_match('/^\d{8}$/', $id_number)) {
        // Sanitize all inputs
        $first_name       = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
        $last_name        = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
        $gender           = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
        $dob              = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $phone            = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $email            = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $facility         = mysqli_real_escape_string($conn, $_POST['facility_name'] ?? '');
        $department       = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
        $cadre            = mysqli_real_escape_string($conn, $_POST['cadre'] ?? '');
        $employment_type  = mysqli_real_escape_string($conn, $_POST['employment_type'] ?? '');
        $education        = mysqli_real_escape_string($conn, $_POST['highest_education'] ?? '');
        $county           = mysqli_real_escape_string($conn, $_POST['county'] ?? '');
        $subcounty        = mysqli_real_escape_string($conn, $_POST['subcounty_id'] ?? '');
        $disability       = mysqli_real_escape_string($conn, $_POST['disability_status'] ?? 'No');
        $disability_type  = ($disability === 'Yes') ? mysqli_real_escape_string($conn, $_POST['disability_type'] ?? '') : '';
        $consent          = isset($_POST['consent']) ? 1 : 0;
        $tid              = (int)$training['training_id'];

        $dup_check = mysqli_query($conn, "SELECT registration_id FROM training_registrations WHERE training_id = $tid AND id_number = '$id_number'");

        if (mysqli_num_rows($dup_check) > 0) {
            $sql = "UPDATE training_registrations SET first_name='$first_name', last_name='$last_name', gender='$gender', date_of_birth=".($dob?"'$dob'":"NULL").", phone='$phone', email='$email', facility_name='$facility', department='$department', cadre='$cadre', employment_type='$employment_type', highest_education='$education', county='$county', subcounty='$subcounty', disability_status='$disability', disability_type='$disability_type', updated_at=NOW() WHERE training_id=$tid AND id_number='$id_number'";
            $message_type = 'updated';
        } else {
            $sql = "INSERT INTO training_registrations (training_id, first_name, last_name, gender, date_of_birth, id_number, phone, email, facility_name, department, cadre, employment_type, highest_education, county, subcounty, disability_status, disability_type, consent_given) VALUES ($tid, '$first_name', '$last_name', '$gender', ".($dob?"'$dob'":"NULL").", '$id_number', '$phone', '$email', '$facility', '$department', '$cadre', '$employment_type', '$education', '$county', '$subcounty', '$disability', '$disability_type', $consent)";
            $message_type = 'registered';
        }

        if (mysqli_query($conn, $sql)) { $success = true; } else { $error = 'Database error: ' . mysqli_error($conn); }
    } else { $error = 'ID Number must be 8 digits.'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $training ? 'Register — ' . htmlspecialchars($training['course_name']) : 'Registration'; ?></title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #f0f4f8; padding: 15px; color: #1a202c; }
        .container { max-width: 700px; margin: 0 auto; }
        .header { background: #0D1A63; color: white; padding: 25px; text-align: center; border-radius: 12px 12px 0 0; }
        .form-card, .training-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section-title { font-weight: 700; color: #0D1A63; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; }
        .btn-submit { width: 100%; background: #0D1A63; color: white; padding: 15px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .loading { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; color: white; }
        .loading.show { display: flex; }
    </style>
</head>
<body>
<div class="container">
    <div class="header"><h1>Training Registration</h1></div>

    <?php if ($error): ?><div class="form-card" style="color:#c53030; border-left:4px solid #c53030;"><?php echo $error; ?></div><?php endif; ?>

    <?php if ($success): ?>
        <div class="form-card" style="text-align: center; padding: 40px;">
            <i class="fas fa-check-circle fa-4x" style="color: #48bb78; margin-bottom: 20px;"></i>
            <h2><?php echo $message_type == 'updated' ? 'Information Updated!' : 'Success!'; ?></h2>
            <p>Registration for <strong><?php echo htmlspecialchars($training['course_name']); ?></strong> is complete.</p>
        </div>
    <?php elseif ($training): ?>
        <form method="POST" id="registrationForm">
            <div class="form-card">
                <div class="section-title"><i class="fas fa-id-card"></i> Identification</div>
                <div class="form-group">
                    <label>ID Number (8 digits) *</label>
                    <input type="text" name="id_number" id="id_number" maxlength="8" required>
                </div>

                <div class="section-title"><i class="fas fa-user"></i> Personal Details</div>
                <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="first_name" required></div>
                <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="last_name" required></div>
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" id="gender" required>
                        <option value="">Select...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group"><label>Date of Birth</label>
                <input type="date" name="date_of_birth" id="date_of_birth" max="<?php echo date('Y-m-d'); ?>"></div>

                <div class="section-title"><i class="fas fa-phone"></i> Contact Details</div>
                <div class="form-group"><label>Phone Number *</label><input type="tel" name="phone" id="phone" placeholder="+254XXXXXXXXX" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" id="email"></div>

                <div class="section-title"><i class="fas fa-hospital"></i> Workplace & Location</div>
                <div class="form-group">
                    <label>County *</label>
                    <select name="county" id="county_sel" required>
                        <option value="">Select County</option>
                        <?php while ($row = mysqli_fetch_assoc($counties)): ?>
                            <option value="<?php echo $row['county_id']; ?>"><?php echo htmlspecialchars($row['county_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subcounty *</label>
                    <select name="subcounty_id" id="subcounty_sel" required><option value="">Select Subcounty</option></select>
                </div>
                <div class="form-group">
                    <label>Organization/Facility *</label>
                    <select name="facility_name" id="facility_name" required>
                        <option value="">Select Organization</option>
                        <?php while ($row = mysqli_fetch_assoc($organizations)): ?>
                            <option value="<?php echo htmlspecialchars($row['org_name']); ?>"><?php echo htmlspecialchars($row['org_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" id="department">
                        <option value="">Select Department</option>
                        <?php while ($row = mysqli_fetch_assoc($departments)): ?>
                            <option value="<?php echo $row['department_id']; ?>"><?php echo htmlspecialchars($row['department_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="section-title"><i class="fas fa-briefcase"></i> Professional Information</div>
                <div class="form-group">
                    <label>Cadre/Job Title *</label>
                    <select name="cadre" id="cadre" required>
                        <option value="">Select Cadre</option>
                        <?php while ($row = mysqli_fetch_assoc($cadres)): ?>
                            <option value="<?php echo $row['cadre_id']; ?>"><?php echo htmlspecialchars($row['cadre_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type" id="employment_type">
                        <option value="">Select...</option>
                        <option value="Permanent">Permanent</option>
                        <option value="Contract">Contract</option>
                        <option value="Volunteer">Volunteer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Highest Education Level</label>
                    <select name="highest_education" id="highest_education">
                        <option value="">Select Education</option>
                        <?php while ($row = mysqli_fetch_assoc($education_levels)): ?>
                            <option value="<?php echo $row['edu_id']; ?>"><?php echo htmlspecialchars($row['edu_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="section-title"><i class="fas fa-wheelchair"></i> Inclusion</div>
                <div class="form-group">
                    <label>Do you have a disability?</label>
                    <input type="radio" name="disability_status" value="No" id="no_dis" checked> No
                    <input type="radio" name="disability_status" value="Yes" id="yes_dis"> Yes
                </div>
                <div id="disability_type_div" style="display:none;">
                    <div class="form-group"><label>Type of Disability</label><input type="text" name="disability_type" id="disability_type"></div>
                </div>

                <div class="form-group"><label><input type="checkbox" name="consent" required> I consent to data collection</label></div>
                <button type="submit" class="btn-submit">Complete Registration</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="loading" id="loading"><div><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading...</p></div></div>

<script>
$(document).ready(function() {
    function loadSubcounties(countyId, selectedId = null) {
        if(!countyId) return;
        $('#subcounty_sel').html('<option>Loading...</option>');
        $.ajax({
            url: 'get_subcounties.php',
            type: 'POST',
            data: { county_id: countyId },
            dataType: 'json',
            success: function(data) {
                let html = '<option value="">Select Subcounty</option>';
                data.forEach(function(item) {
                    let sel = (selectedId == item.sub_county_id) ? 'selected' : '';
                    html += `<option value="${item.sub_county_id}" ${sel}>${item.sub_county_name}</option>`;
                });
                $('#subcounty_sel').html(html);
            }
        });
    }

    $('#county_sel').on('change', function() { loadSubcounties($(this).val()); });

    $('input[name="disability_status"]').on('change', function() {
        $('#disability_type_div').toggle($(this).val() === 'Yes');
    });

    $('#id_number').on('blur', function() {
        let id = $(this).val();
        if (id.length === 8) {
            $('#loading').addClass('show');
            $.getJSON(window.location.href, { token:'<?php echo $token; ?>', check_id:1, id_number:id }, function(res) {
                $('#loading').removeClass('show');
                if (res.exists) {
                    let d = res.data;
                    $('#first_name').val(d.first_name);
                    $('#last_name').val(d.last_name);
                    $('#phone').val(d.phone);
                    $('#email').val(d.email);
                    $('#gender').val(d.gender);
                    $('#date_of_birth').val(d.date_of_birth);
                    $('#facility_name').val(d.facility_name);
                    $('#department').val(d.department);
                    $('#cadre').val(d.cadre);
                    $('#employment_type').val(d.employment_type);
                    $('#highest_education').val(d.highest_education);
                    if(d.disability_status === 'Yes') {
                        $('#yes_dis').prop('checked', true).trigger('change');
                        $('#disability_type').val(d.disability_type);
                    }
                    if(d.county) {
                        $('#county_sel').val(d.county);
                        loadSubcounties(d.county, d.subcounty);
                    }
                }
            });
        }
    });
});
</script>
</body>
</html>