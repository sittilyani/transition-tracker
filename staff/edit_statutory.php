<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_number = $_GET['id_number'] ?? '';
if (empty($id_number)) {
    header("Location: county_staff_list.php");
    exit;
}

// Fetch staff
$staff = $conn->query("SELECT * FROM county_staff WHERE id_number = '".mysqli_real_escape_string($conn, $id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }

// Fetch existing statutory
$statutory = $conn->query("SELECT * FROM employee_statutory WHERE id_number = '".mysqli_real_escape_string($conn, $id_number)."'")->fetch_assoc();

$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name']) ? ' '.$staff['other_name'] : ''));

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'kra_pin','nhif_number','nssf_number','huduma_number','passport_number',
        'alien_number','birth_cert_number','disability','disability_description',
        'disability_cert_number','nok_name','nok_relationship','nok_phone',
        'nok_email','nok_alternate_phone','nok_postal_address',
        'emergency_contact_name','emergency_contact_phone','emergency_contact_relationship'
    ];
    $sets = [];
    foreach ($fields as $f) {
        $val = mysqli_real_escape_string($conn, trim($_POST[$f] ?? ''));
        $sets[] = "$f = '$val'";
    }
    $sets[] = "updated_by = '".mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? 'Admin')."'";
    $sets_sql = implode(', ', $sets);
    $id_esc = mysqli_real_escape_string($conn, $id_number);

    if ($statutory) {
        $conn->query("UPDATE employee_statutory SET $sets_sql WHERE id_number = '$id_esc'");
    } else {
        $cols = implode(', ', $fields);
        $vals = [];
        foreach ($fields as $f) {
            $vals[] = "'".mysqli_real_escape_string($conn, trim($_POST[$f] ?? ''))."'";
        }
        $conn->query("INSERT INTO employee_statutory (id_number, $cols, updated_by) VALUES ('$id_esc', ".implode(', ',$vals).", '".mysqli_real_escape_string($conn,$_SESSION['full_name']??'Admin')."')");
    }
    $_SESSION['success_message'] = "Statutory details saved successfully.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#statutory");
    exit;
}

$v = fn($f) => htmlspecialchars($statutory[$f] ?? '');
$sel = fn($f,$opt) => (($statutory[$f] ?? '') === $opt) ? 'selected' : '';
$back_tab = 'statutory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statutory Details – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head>
<body>
<div class="container">

<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-file-contract fa-2x"></i>
        <div>
            <h1>Statutory Details</h1>
            <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p>
        </div>
    </div>
</div>

<form method="POST">

<!-- ── Statutory Numbers ─────────────────────────────────────────────────── -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-id-card"></i> Government Registration Numbers</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>KRA PIN</label>
                <input type="text" name="kra_pin" value="<?= $v('kra_pin') ?>" placeholder="e.g. A001234567B">
            </div>
            <div class="form-group">
                <label>NHIF / SHA Number</label>
                <input type="text" name="nhif_number" value="<?= $v('nhif_number') ?>">
            </div>
            <div class="form-group">
                <label>NSSF Number</label>
                <input type="text" name="nssf_number" value="<?= $v('nssf_number') ?>">
            </div>
            <div class="form-group">
                <label>Huduma Number</label>
                <input type="text" name="huduma_number" value="<?= $v('huduma_number') ?>">
            </div>
            <div class="form-group">
                <label>Passport Number</label>
                <input type="text" name="passport_number" value="<?= $v('passport_number') ?>">
            </div>
            <div class="form-group">
                <label>Alien Number <span class="field-hint">(if applicable)</span></label>
                <input type="text" name="alien_number" value="<?= $v('alien_number') ?>">
            </div>
            <div class="form-group">
                <label>Birth Certificate Number</label>
                <input type="text" name="birth_cert_number" value="<?= $v('birth_cert_number') ?>">
            </div>
        </div>
    </div>
</div>

<!-- ── Disability ─────────────────────────────────────────────────────────── -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-wheelchair"></i> Disability Information</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Person with Disability?</label>
                <select name="disability" id="disabilityToggle" onchange="toggleDisability()">
                    <option value="No"  <?= $sel('disability','No') ?>>No</option>
                    <option value="Yes" <?= $sel('disability','Yes') ?>>Yes</option>
                </select>
            </div>
            <div class="form-group" id="disabDesc" style="<?= ($v('disability')==='Yes')?'':'display:none' ?>">
                <label>Disability Description</label>
                <input type="text" name="disability_description" value="<?= $v('disability_description') ?>">
            </div>
            <div class="form-group" id="disabCert" style="<?= ($v('disability')==='Yes')?'':'display:none' ?>">
                <label>Disability Certificate Number</label>
                <input type="text" name="disability_cert_number" value="<?= $v('disability_cert_number') ?>">
            </div>
        </div>
    </div>
</div>

<!-- ── Next of Kin ────────────────────────────────────────────────────────── -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-friends"></i> Primary Next of Kin</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="nok_name" value="<?= $v('nok_name') ?>">
            </div>
            <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="nok_relationship" value="<?= $v('nok_relationship') ?>" placeholder="e.g. Spouse, Parent">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="nok_phone" value="<?= $v('nok_phone') ?>">
            </div>
            <div class="form-group">
                <label>Alternate Phone</label>
                <input type="tel" name="nok_alternate_phone" value="<?= $v('nok_alternate_phone') ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="nok_email" value="<?= $v('nok_email') ?>">
            </div>
            <div class="form-group">
                <label>Postal Address</label>
                <input type="text" name="nok_postal_address" value="<?= $v('nok_postal_address') ?>">
            </div>
        </div>
    </div>
</div>

<!-- ── Emergency Contact ──────────────────────────────────────────────────── -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-phone-alt"></i> Emergency Contact</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Contact Name</label>
                <input type="text" name="emergency_contact_name" value="<?= $v('emergency_contact_name') ?>">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="emergency_contact_phone" value="<?= $v('emergency_contact_phone') ?>">
            </div>
            <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="emergency_contact_relationship" value="<?= $v('emergency_contact_relationship') ?>" placeholder="e.g. Brother, Colleague">
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>

<script>
function toggleDisability() {
    const yes = document.getElementById('disabilityToggle').value === 'Yes';
    document.getElementById('disabDesc').style.display = yes ? '' : 'none';
    document.getElementById('disabCert').style.display = yes ? '' : 'none';
}
</script>
</body>
</html>
