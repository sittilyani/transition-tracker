<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

// If edit, load record and infer id_number
if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_academics WHERE academic_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc = mysqli_real_escape_string($conn, $id_number);
    $fields = ['qualification_type','qualification_name','institution_name','course_name',
               'specialization','grade','award_year','start_date','end_date',
               'certificate_number','completion_status','verification_status','remarks'];
    $sets = [];
    foreach ($fields as $f) {
        $val = mysqli_real_escape_string($conn, trim($_POST[$f] ?? ''));
        $sets[] = "$f='$val'";
    }
    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_academics SET ".implode(',',$sets)." WHERE academic_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_academics (id_number,".implode(',',array_column(array_map(fn($s)=>explode('=',$s),$sets),0)).") VALUES ('$id_esc',".implode(',',array_column(array_map(fn($s)=>explode('=',$s,2),$sets),1)).")");
    }
    $_SESSION['success_message'] = "Academic record saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#academics"); exit;
}

$v   = fn($f) => htmlspecialchars($row[$f] ?? '');
$sel = fn($f,$o) => (($row[$f] ?? '')===$o)?'selected':'';
$back_tab = 'academics';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Academic – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-graduation-cap fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Academic Qualification</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id" value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-graduation-cap"></i> Qualification Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Qualification Type <span class="req">*</span></label>
                <select name="qualification_type" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['KCPE','KCSE','Certificate','Diploma','Higher Diploma','Degree','Masters','PhD','Post Graduate Diploma','Other'] as $q): ?>
                    <option value="<?=$q?>" <?= $sel('qualification_type',$q) ?>><?=$q?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Qualification Name</label>
                <input type="text" name="qualification_name" value="<?= $v('qualification_name') ?>" placeholder="e.g. Bachelor of Science in Nursing">
            </div>
            <div class="form-group">
                <label>Institution Name <span class="req">*</span></label>
                <input type="text" name="institution_name" value="<?= $v('institution_name') ?>" required placeholder="University / College / School">
            </div>
            <div class="form-group">
                <label>Course / Programme</label>
                <input type="text" name="course_name" value="<?= $v('course_name') ?>">
            </div>
            <div class="form-group">
                <label>Specialization</label>
                <input type="text" name="specialization" value="<?= $v('specialization') ?>">
            </div>
            <div class="form-group">
                <label>Grade / Class</label>
                <input type="text" name="grade" value="<?= $v('grade') ?>" placeholder="e.g. Second Class Upper, B+">
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= $v('start_date') ?>">
            </div>
            <div class="form-group">
                <label>End / Graduation Date</label>
                <input type="date" name="end_date" value="<?= $v('end_date') ?>">
            </div>
            <div class="form-group">
                <label>Award Year</label>
                <input type="number" name="award_year" value="<?= $v('award_year') ?>" min="1970" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
            </div>
            <div class="form-group">
                <label>Certificate Number</label>
                <input type="text" name="certificate_number" value="<?= $v('certificate_number') ?>">
            </div>
            <div class="form-group">
                <label>Completion Status</label>
                <select name="completion_status">
                    <?php foreach(['Completed','In Progress','Discontinued'] as $s): ?>
                    <option value="<?=$s?>" <?= $sel('completion_status',$s) ?: ($s==='Completed'&&!$edit_id?'selected':'') ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status">
                    <?php foreach(['Pending','Verified','Rejected'] as $s): ?>
                    <option value="<?=$s?>" <?= $sel('verification_status',$s) ?: ($s==='Pending'&&!$edit_id?'selected':'') ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Remarks</label>
                <textarea name="remarks"><?= $v('remarks') ?></textarea>
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div></body></html>
