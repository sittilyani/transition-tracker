<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_work_experience WHERE experience_id=$edit_id")->fetch_assoc();
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
    $f = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $is_current   = $_POST['is_current'] ?? 'No';
    $end_date_val = ($is_current === 'Yes') ? 'NULL' : $f('end_date');
    $sup_count    = (int)($_POST['supervised_count'] ?? 0);

    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_work_experience SET
            employer_name={$f('employer_name')}, employer_type={$f('employer_type')},
            job_title={$f('job_title')}, job_grade={$f('job_grade')},
            department={$f('department')}, start_date={$f('start_date')},
            end_date=$end_date_val, is_current='{$is_current}',
            responsibilities={$f('responsibilities')}, achievements={$f('achievements')},
            supervising_role={$f('supervising_role')}, supervised_count=$sup_count,
            leaving_reason={$f('leaving_reason')},
            employer_contact_person={$f('employer_contact_person')},
            employer_phone={$f('employer_phone')}, employer_email={$f('employer_email')},
            verification_status={$f('verification_status')}
            WHERE experience_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_work_experience
            (id_number,employer_name,employer_type,job_title,job_grade,department,
             start_date,end_date,is_current,responsibilities,achievements,
             supervising_role,supervised_count,leaving_reason,
             employer_contact_person,employer_phone,employer_email,verification_status)
            VALUES ('$id_esc',{$f('employer_name')},{$f('employer_type')},
            {$f('job_title')},{$f('job_grade')},{$f('department')},
            {$f('start_date')},$end_date_val,'{$is_current}',
            {$f('responsibilities')},{$f('achievements')},
            {$f('supervising_role')},$sup_count,{$f('leaving_reason')},
            {$f('employer_contact_person')},{$f('employer_phone')},
            {$f('employer_email')},{$f('verification_status')})");
    }
    $_SESSION['success_message'] = "Work experience saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#experience"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$chk = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'checked':'';
$back_tab = 'experience';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Experience – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-briefcase fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Work Experience</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<!-- Employer -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-building"></i> Employer Information</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Employer / Organisation Name <span class="req">*</span></label>
                <input type="text" name="employer_name" value="<?= $v('employer_name') ?>" required>
            </div>
            <div class="form-group">
                <label>Employer Type <span class="req">*</span></label>
                <select name="employer_type" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['Government','Private','NGO','International Organization','Self-Employed','Other'] as $t): ?>
                    <option value="<?=$t?>" <?= $sel('employer_type',$t) ?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department / Division</label>
                <input type="text" name="department" value="<?= $v('department') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Role -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-tie"></i> Role Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Job Title / Position <span class="req">*</span></label>
                <input type="text" name="job_title" value="<?= $v('job_title') ?>" required>
            </div>
            <div class="form-group">
                <label>Job Grade / Scale</label>
                <input type="text" name="job_grade" value="<?= $v('job_grade') ?>" placeholder="e.g. Job Group K, Grade 5">
            </div>
            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status">
                    <?php foreach(['Pending','Verified','Rejected'] as $s): ?>
                    <option value="<?=$s?>" <?= $sel('verification_status',$s)?:($s==='Pending'&&!$edit_id?'selected':'') ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date <span class="req">*</span></label>
                <input type="date" name="start_date" value="<?= $v('start_date') ?>" required>
            </div>
            <div class="form-group" id="endDateGroup">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= $v('end_date') ?>" id="end_date">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;font-size:13.5px;">
                    <input type="checkbox" name="is_current" value="Yes" id="isCurrentChk"
                           <?= (($row['is_current'] ?? '') === 'Yes') ? 'checked' : '' ?>
                           onchange="toggleCurrent(this)">
                    Current / Present Employment
                </label>
            </div>
            <div class="form-group full">
                <label>Key Responsibilities</label>
                <textarea name="responsibilities"><?= $v('responsibilities') ?></textarea>
            </div>
            <div class="form-group full">
                <label>Key Achievements</label>
                <textarea name="achievements"><?= $v('achievements') ?></textarea>
            </div>
            <div class="form-group full">
                <label>Reason for Leaving <span class="field-hint">(leave blank if current)</span></label>
                <textarea name="leaving_reason"><?= $v('leaving_reason') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- Supervision -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-users-cog"></i> Supervisory Role</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Did you supervise others?</label>
                <select name="supervising_role" onchange="toggleSupervised(this)">
                    <option value="No" <?= $sel('supervising_role','No') ?>>No</option>
                    <option value="Yes" <?= $sel('supervising_role','Yes') ?>>Yes</option>
                </select>
            </div>
            <div class="form-group" id="supervisedCount" style="<?= (($row['supervising_role']??'No')==='Yes')?'':'display:none' ?>">
                <label>Number Supervised</label>
                <input type="number" name="supervised_count" min="0" value="<?= $v('supervised_count') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Employer Contact -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-address-card"></i> Employer Contact (for verification)</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="employer_contact_person" value="<?= $v('employer_contact_person') ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="employer_phone" value="<?= $v('employer_phone') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="employer_email" value="<?= $v('employer_email') ?>">
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>
<script>
function toggleCurrent(chk) {
    document.getElementById('endDateGroup').style.display = chk.checked ? 'none' : '';
    if (chk.checked) document.getElementById('end_date').value = '';
}
function toggleSupervised(sel) {
    document.getElementById('supervisedCount').style.display = sel.value==='Yes' ? '' : 'none';
}
// init
toggleCurrent(document.getElementById('isCurrentChk'));
</script>
</body></html>
