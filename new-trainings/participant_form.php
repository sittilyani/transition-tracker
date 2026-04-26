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

// ── Fetch dropdown data (now fetching names directly) ──
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

        // Facility name is already a name from dropdown
        $facility         = mysqli_real_escape_string($conn, $_POST['facility_name'] ?? '');

        // Get department name from the selected option text (not ID)
        $department_id    = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
        $department_name  = '';
        if (!empty($department_id)) {
            $dept_query = mysqli_query($conn, "SELECT department_name FROM departments WHERE department_id = '$department_id'");
            if ($dept_row = mysqli_fetch_assoc($dept_query)) {
                $department_name = $dept_row['department_name'];
            }
        }

        // Get cadre name from selected ID
        $cadre_id         = mysqli_real_escape_string($conn, $_POST['cadre'] ?? '');
        $cadre_name       = '';
        if (!empty($cadre_id)) {
            $cadre_query = mysqli_query($conn, "SELECT cadre_name FROM cadres WHERE cadre_id = '$cadre_id'");
            if ($cadre_row = mysqli_fetch_assoc($cadre_query)) {
                $cadre_name = $cadre_row['cadre_name'];
            }
        }

        $employment_type  = mysqli_real_escape_string($conn, $_POST['employment_type'] ?? '');

        // Get education name from selected ID
        $education_id     = mysqli_real_escape_string($conn, $_POST['highest_education'] ?? '');
        $education_name   = '';
        if (!empty($education_id)) {
            $edu_query = mysqli_query($conn, "SELECT edu_name FROM education_levels WHERE edu_id = '$education_id'");
            if ($edu_row = mysqli_fetch_assoc($edu_query)) {
                $education_name = $edu_row['edu_name'];
            }
        }

        // Get county name from selected ID
        $county_id        = mysqli_real_escape_string($conn, $_POST['county'] ?? '');
        $county_name      = '';
        if (!empty($county_id)) {
            $county_query = mysqli_query($conn, "SELECT county_name FROM counties WHERE county_id = '$county_id'");
            if ($county_row = mysqli_fetch_assoc($county_query)) {
                $county_name = $county_row['county_name'];
            }
        }

        // Get subcounty name from selected ID
        $subcounty_id     = mysqli_real_escape_string($conn, $_POST['subcounty_id'] ?? '');
        $subcounty_name   = '';
        if (!empty($subcounty_id)) {
            $sub_query = mysqli_query($conn, "SELECT sub_county_name FROM sub_counties WHERE sub_county_id = '$subcounty_id'");
            if ($sub_row = mysqli_fetch_assoc($sub_query)) {
                $subcounty_name = $sub_row['sub_county_name'];
            }
        }

        $disability       = mysqli_real_escape_string($conn, $_POST['disability_status'] ?? 'No');
        $disability_type  = ($disability === 'Yes') ? mysqli_real_escape_string($conn, $_POST['disability_type'] ?? '') : '';
        $consent          = isset($_POST['consent']) ? 1 : 0;
        $tid              = (int)$training['training_id'];

        $dup_check = mysqli_query($conn, "SELECT registration_id FROM training_registrations WHERE training_id = $tid AND id_number = '$id_number'");

        if (mysqli_num_rows($dup_check) > 0) {
            // UPDATE: store names instead of IDs
            $sql = "UPDATE training_registrations SET
                        first_name='$first_name',
                        last_name='$last_name',
                        gender='$gender',
                        date_of_birth=".($dob?"'$dob'":"NULL").",
                        phone='$phone',
                        email='$email',
                        facility_name='$facility',
                        department='$department_name',
                        cadre='$cadre_name',
                        employment_type='$employment_type',
                        highest_education='$education_name',
                        county='$county_name',
                        subcounty='$subcounty_name',
                        disability_status='$disability',
                        disability_type='$disability_type',
                        updated_at=NOW()
                    WHERE training_id=$tid AND id_number='$id_number'";
            $message_type = 'updated';
        } else {
            // INSERT: store names instead of IDs
            $sql = "INSERT INTO training_registrations (
                        training_id, first_name, last_name, gender, date_of_birth, id_number,
                        phone, email, facility_name, department, cadre, employment_type,
                        highest_education, county, subcounty, disability_status, disability_type, consent_given
                    ) VALUES (
                        $tid, '$first_name', '$last_name', '$gender', ".($dob?"'$dob'":"NULL").", '$id_number',
                        '$phone', '$email', '$facility', '$department_name', '$cadre_name', '$employment_type',
                        '$education_name', '$county_name', '$subcounty_name', '$disability', '$disability_type', $consent
                    )";
            $message_type = 'registered';
        }

        if (mysqli_query($conn, $sql)) {
            $success = true;
        } else {
            $error = 'Database error: ' . mysqli_error($conn);
        }
    } else {
        $error = 'ID Number must be 8 digits.';
    }
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
                    <select name="subcounty_id" id="subcounty_sel" required>
                        <option value="">Select Subcounty</option>
                    </select>
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
    // Function to load subcounties based on county ID
    function loadSubcounties(countyId, selectedSubcountyName = null) {
        if (!countyId || countyId === '') {
            $('#subcounty_sel').html('<option value="">Select Subcounty</option>');
            return;
        }

        $('#subcounty_sel').html('<option value="">Loading...</option>');

        $.ajax({
            url: 'get_subcounties.php',
            type: 'POST',
            data: { county_id: countyId },
            dataType: 'json',
            success: function(response) {
                console.log('Subcounties loaded:', response);
                let html = '<option value="">Select Subcounty</option>';

                if (response && response.length > 0) {
                    $.each(response, function(index, subcounty) {
                        let selected = (selectedSubcountyName && subcounty.sub_county_name === selectedSubcountyName) ? 'selected' : '';
                        html += `<option value="${subcounty.sub_county_id}" ${selected}>${subcounty.sub_county_name}</option>`;
                    });
                } else {
                    html = '<option value="">No subcounties found</option>';
                }

                $('#subcounty_sel').html(html);
            },
            error: function(xhr, status, error) {
                console.error('Error loading subcounties:', error);
                $('#subcounty_sel').html('<option value="">Error loading subcounties</option>');
            }
        });
    }

    // When county selection changes, load subcounties
    $('#county_sel').on('change', function() {
        var countyId = $(this).val();
        loadSubcounties(countyId);
    });

    // Disability status toggle
    $('input[name="disability_status"]').on('change', function() {
        $('#disability_type_div').toggle($(this).val() === 'Yes');
    });

    // Auto-fill form when ID number is entered (blur event)
    $('#id_number').on('blur', function() {
        let id = $(this).val();
        if (id.length === 8 && /^\d+$/.test(id)) {
            $('#loading').addClass('show');

            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: {
                    token: '<?php echo $token; ?>',
                    check_id: 1,
                    id_number: id
                },
                dataType: 'json',
                success: function(res) {
                    $('#loading').removeClass('show');

                    if (res.exists && res.data) {
                        let d = res.data;

                        // Fill basic fields
                        $('#first_name').val(d.first_name || '');
                        $('#last_name').val(d.last_name || '');
                        $('#phone').val(d.phone || '');
                        $('#email').val(d.email || '');
                        $('#gender').val(d.gender || '');
                        $('#date_of_birth').val(d.date_of_birth || '');
                        $('#facility_name').val(d.facility_name || '');
                        $('#employment_type').val(d.employment_type || '');

                        // Set disability status
                        if (d.disability_status === 'Yes') {
                            $('#yes_dis').prop('checked', true);
                            $('#disability_type_div').show();
                            $('#disability_type').val(d.disability_type || '');
                        } else {
                            $('#no_dis').prop('checked', true);
                            $('#disability_type_div').hide();
                        }

                        // Set department by matching name
                        if (d.department) {
                            let found = false;
                            $('#department option').each(function() {
                                if ($(this).text() === d.department) {
                                    $(this).prop('selected', true);
                                    found = true;
                                    return false;
                                }
                            });
                            if (!found) $('#department').val('');
                        }

                        // Set cadre by matching name
                        if (d.cadre) {
                            let found = false;
                            $('#cadre option').each(function() {
                                if ($(this).text() === d.cadre) {
                                    $(this).prop('selected', true);
                                    found = true;
                                    return false;
                                }
                            });
                            if (!found) $('#cadre').val('');
                        }

                        // Set education by matching name
                        if (d.highest_education) {
                            let found = false;
                            $('#highest_education option').each(function() {
                                if ($(this).text() === d.highest_education) {
                                    $(this).prop('selected', true);
                                    found = true;
                                    return false;
                                }
                            });
                            if (!found) $('#highest_education').val('');
                        }

                        // Set county by matching name and load subcounties
                        if (d.county) {
                            let countyId = null;
                            $('#county_sel option').each(function() {
                                if ($(this).text() === d.county) {
                                    $(this).prop('selected', true);
                                    countyId = $(this).val();
                                    return false;
                                }
                            });

                            if (countyId) {
                                // Load subcounties and select the matching one
                                loadSubcounties(countyId, d.subcounty);
                            }
                        }
                    }
                },
                error: function() {
                    $('#loading').removeClass('show');
                    console.error('Error fetching participant data');
                }
            });
        }
    });
});
</script>
</body>
</html>