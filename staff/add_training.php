<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_trainings WHERE training_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc = mysqli_real_escape_string($conn, $id_number);
    $f = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_trainings SET
            training_name={$f('training_name')}, training_provider={$f('training_provider')},
            training_type={$f('training_type')}, start_date={$f('start_date')},
            end_date={$f('end_date')}, certificate_number={$f('certificate_number')},
            certificate_issue_date={$f('certificate_issue_date')},
            certificate_expiry_date={$f('certificate_expiry_date')},
            skills_acquired={$f('skills_acquired')}, funding_source={$f('funding_source')}
            WHERE training_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_trainings
            (id_number,training_name,training_provider,training_type,start_date,end_date,
             certificate_number,certificate_issue_date,certificate_expiry_date,
             skills_acquired,funding_source)
            VALUES ('$id_esc',{$f('training_name')},{$f('training_provider')},
            {$f('training_type')},{$f('start_date')},{$f('end_date')},
            {$f('certificate_number')},{$f('certificate_issue_date')},
            {$f('certificate_expiry_date')},{$f('skills_acquired')},{$f('funding_source')})");
    }
    $_SESSION['success_message'] = "Training record saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#trainings"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'trainings';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Training – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-chalkboard-teacher fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Training / Certification</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-chalkboard-teacher"></i> Training Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group full" style="grid-column:1/3">
                <label>Training / Course Name <span class="req">*</span></label>
                <input type="text" name="training_name" value="<?= $v('training_name') ?>" required placeholder="Full name of training programme">
            </div>
            <div class="form-group">
                <label>Training Type <span class="req">*</span></label>
                <select name="training_type" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['In-house','External','Online','International'] as $t): ?>
                    <option value="<?=$t?>" <?= $sel('training_type',$t) ?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Training Provider / Institution <span class="req">*</span></label>
                <input type="text" name="training_provider" value="<?= $v('training_provider') ?>" required>
            </div>
            <div class="form-group">
                <label>Funding Source</label>
                <input type="text" name="funding_source" value="<?= $v('funding_source') ?>" placeholder="e.g. Government, Donor, Self-Funded">
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= $v('start_date') ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= $v('end_date') ?>">
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-award"></i> Certificate Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Certificate Number</label>
                <input type="text" name="certificate_number" value="<?= $v('certificate_number') ?>">
            </div>
            <div class="form-group">
                <label>Certificate Issue Date</label>
                <input type="date" name="certificate_issue_date" value="<?= $v('certificate_issue_date') ?>">
            </div>
            <div class="form-group">
                <label>Certificate Expiry Date <span class="field-hint">(if applicable)</span></label>
                <input type="date" name="certificate_expiry_date" value="<?= $v('certificate_expiry_date') ?>">
            </div>
            <div class="form-group full">
                <label>Skills Acquired / Learning Outcomes</label>
                <textarea name="skills_acquired" placeholder="List key skills or competencies gained..."><?= $v('skills_acquired') ?></textarea>
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div></body></html>
