<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_referees WHERE referee_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

// Count existing referees
$ref_count = $conn->query("SELECT COUNT(*) c FROM employee_referees WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc()['c'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc = mysqli_real_escape_string($conn, $id_number);
    $f = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $yrs = (int)($_POST['years_known'] ?? 0);
    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_referees SET
            referee_name={$f('referee_name')}, referee_title={$f('referee_title')},
            referee_organization={$f('referee_organization')}, referee_position={$f('referee_position')},
            referee_phone={$f('referee_phone')}, referee_email={$f('referee_email')},
            referee_relationship={$f('referee_relationship')}, years_known=$yrs,
            referee_address={$f('referee_address')}, can_contact={$f('can_contact')}
            WHERE referee_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_referees
            (id_number,referee_name,referee_title,referee_organization,referee_position,
             referee_phone,referee_email,referee_relationship,years_known,
             referee_address,can_contact)
            VALUES ('$id_esc',{$f('referee_name')},{$f('referee_title')},
            {$f('referee_organization')},{$f('referee_position')},
            {$f('referee_phone')},{$f('referee_email')},{$f('referee_relationship')},
            $yrs,{$f('referee_address')},{$f('can_contact')})");
    }
    $_SESSION['success_message'] = "Referee saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#referees"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'referees';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Referee – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-address-book fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Referee</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?>
        <?php if (!$edit_id): ?>&nbsp;·&nbsp; <span style="opacity:.75;"><?= $ref_count ?> referee(s) already added</span><?php endif; ?></p></div>
    </div>
</div>

<?php if (!$edit_id && $ref_count >= 3): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
    <i class="fas fa-exclamation-triangle"></i>
    This employee already has <?= $ref_count ?> referees. Most positions require a maximum of 3.
</div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-tie"></i> Referee Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Title <span class="field-hint">(Mr/Mrs/Dr/Prof)</span></label>
                <select name="referee_title">
                    <option value="">-- Select --</option>
                    <?php foreach(['Mr','Mrs','Ms','Dr','Prof','Eng','Hon','Rev','Other'] as $t): ?>
                    <option value="<?=$t?>" <?= (($row['referee_title']??'')===$t)?'selected':'' ?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:2/4">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="referee_name" value="<?= $v('referee_name') ?>" required>
            </div>
            <div class="form-group">
                <label>Position / Job Title <span class="req">*</span></label>
                <input type="text" name="referee_position" value="<?= $v('referee_position') ?>" required>
            </div>
            <div class="form-group" style="grid-column:2/4">
                <label>Organisation / Employer <span class="req">*</span></label>
                <input type="text" name="referee_organization" value="<?= $v('referee_organization') ?>" required>
            </div>
            <div class="form-group">
                <label>Relationship to Employee <span class="req">*</span></label>
                <input type="text" name="referee_relationship" value="<?= $v('referee_relationship') ?>" required placeholder="e.g. Supervisor, Colleague, Mentor">
            </div>
            <div class="form-group">
                <label>Years Known</label>
                <input type="number" name="years_known" min="0" max="50" value="<?= $v('years_known') ?>">
            </div>
            <div class="form-group">
                <label>Can We Contact?</label>
                <select name="can_contact">
                    <option value="Yes" <?= $sel('can_contact','Yes') ?>>Yes</option>
                    <option value="No"  <?= $sel('can_contact','No') ?>>No – contact after offer</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-phone"></i> Contact Information</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Phone Number <span class="req">*</span></label>
                <input type="tel" name="referee_phone" value="<?= $v('referee_phone') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="referee_email" value="<?= $v('referee_email') ?>">
            </div>
            <div class="form-group">
                <label>Postal / Physical Address</label>
                <input type="text" name="referee_address" value="<?= $v('referee_address') ?>">
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div></body></html>
