<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_professional_registrations WHERE registration_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

// Common Kenyan regulatory bodies
$bodies = [
    'Nursing Council of Kenya (NCK)',
    'Kenya Medical Practitioners & Dentists Council (KMPDC)',
    'Kenya Pharmacy and Poisons Board (PPB)',
    'Clinical Officers Council (COC)',
    'Kenya Nutritionists & Dieticians Institute (KNDI)',
    'Kenya Medical Laboratory Technicians & Technologists Board (KMLTTB)',
    'Kenya Physiotherapists Board',
    'Occupational Therapists Board',
    'Kenya Optometrists & Dispensing Opticians Board',
    'Radiographers & Radiotherapists Board',
    'Kenya Orthopaedic Technologists Board',
    'Kenya Dental Technologists & Therapists Board',
    'Institute of Human Resource Management (IHRM)',
    'Kenya Institute of Management (KIM)',
    'Engineers Board of Kenya (EBK)',
    'Law Society of Kenya (LSK)',
    'Institute of Certified Public Accountants (ICPAK)',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc = mysqli_real_escape_string($conn, $id_number);
    $f = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_professional_registrations SET
            regulatory_body={$f('regulatory_body')}, registration_number={$f('registration_number')},
            registration_date={$f('registration_date')}, expiry_date={$f('expiry_date')},
            license_number={$f('license_number')}, license_grade={$f('license_grade')},
            specialization={$f('specialization')}, verification_status={$f('verification_status')}
            WHERE registration_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_professional_registrations
            (id_number,regulatory_body,registration_number,registration_date,expiry_date,
             license_number,license_grade,specialization,verification_status)
            VALUES ('$id_esc',{$f('regulatory_body')},{$f('registration_number')},
            {$f('registration_date')},{$f('expiry_date')},{$f('license_number')},
            {$f('license_grade')},{$f('specialization')},{$f('verification_status')})");
    }
    $_SESSION['success_message'] = "Professional registration saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#professional"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'professional';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Registration – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-certificate fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Professional Registration</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-certificate"></i> Registration Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group full">
                <label>Regulatory Body / Professional Body <span class="req">*</span></label>
                <select name="regulatory_body" id="regBodySel" onchange="toggleOther()" required>
                    <option value="">-- Select Regulatory Body --</option>
                    <?php foreach ($bodies as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>" <?= (($row['regulatory_body']??'')===$b)?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                    <?php
                    // If edited value not in list, add it
                    if ($edit_id && !in_array($row['regulatory_body'] ?? '', $bodies) && !empty($row['regulatory_body'])):
                    ?><option value="<?= $v('regulatory_body') ?>" selected><?= $v('regulatory_body') ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group full" id="otherBodyWrap" style="display:none;">
                <label>Specify Regulatory Body</label>
                <input type="text" id="otherBody" placeholder="Enter regulatory body name...">
            </div>
            <div class="form-group">
                <label>Registration Number <span class="req">*</span></label>
                <input type="text" name="registration_number" value="<?= $v('registration_number') ?>" required>
            </div>
            <div class="form-group">
                <label>Registration Date</label>
                <input type="date" name="registration_date" value="<?= $v('registration_date') ?>">
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?= $v('expiry_date') ?>">
            </div>
            <div class="form-group">
                <label>License Number</label>
                <input type="text" name="license_number" value="<?= $v('license_number') ?>">
            </div>
            <div class="form-group">
                <label>License Grade / Category</label>
                <input type="text" name="license_grade" value="<?= $v('license_grade') ?>" placeholder="e.g. Grade A, Full License">
            </div>
            <div class="form-group">
                <label>Specialization</label>
                <input type="text" name="specialization" value="<?= $v('specialization') ?>">
            </div>
            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status">
                    <?php foreach(['Pending','Verified','Rejected'] as $s): ?>
                    <option value="<?=$s?>" <?= $sel('verification_status',$s)?:($s==='Pending'&&!$edit_id?'selected':'') ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>
<script>
function toggleOther() {
    const sel = document.getElementById('regBodySel');
    const wrap = document.getElementById('otherBodyWrap');
    if (sel.value === 'Other') {
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
        // Sync custom text back to select value
        document.getElementById('otherBody').oninput = function() {
            const opt = sel.querySelector('option[value="Other"]');
            // do nothing — handled server-side if needed
        };
    }
}
// If loaded with "Other"
if (document.getElementById('regBodySel').value === 'Other') toggleOther();
</script>
</body></html>
