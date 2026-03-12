<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_disciplinary WHERE disciplinary_id=$edit_id")->fetch_assoc();
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

    $closed_date = !empty($_POST['closed_date']) ? $f('closed_date') : 'NULL';

    if ($pid) {
        $conn->query("UPDATE employee_disciplinary SET
            case_number={$f('case_number')}, case_type={$f('case_type')},
            incident_date={$f('incident_date')}, report_date={$f('report_date')},
            description={$f('description')}, action_taken={$f('action_taken')},
            action_date={$f('action_date')}, penalty={$f('penalty')},
            status={$f('status')}, closed_date=$closed_date
            WHERE disciplinary_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_disciplinary
            (id_number,case_number,case_type,incident_date,report_date,
             description,action_taken,action_date,penalty,status,closed_date)
            VALUES ('$id_esc',{$f('case_number')},{$f('case_type')},
            {$f('incident_date')},{$f('report_date')},{$f('description')},
            {$f('action_taken')},{$f('action_date')},{$f('penalty')},
            {$f('status')},$closed_date)");
    }
    $_SESSION['success_message'] = "Disciplinary record saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#disciplinary"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'disciplinary';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Disciplinary – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header" style="background:linear-gradient(135deg,#721c24,#a72030);">
    <div class="fph-left">
        <i class="fas fa-gavel fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Record' ?> Disciplinary Case</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head" style="background:linear-gradient(90deg,#721c24,#a72030);"><i class="fas fa-folder-open"></i> Case Information</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Case Reference Number</label>
                <input type="text" name="case_number" value="<?= $v('case_number') ?>" placeholder="e.g. DISC/2024/001">
            </div>
            <div class="form-group" style="grid-column:2/4">
                <label>Case Type / Offence <span class="req">*</span></label>
                <input type="text" name="case_type" value="<?= $v('case_type') ?>" required placeholder="e.g. Gross Misconduct, Absenteeism, Insubordination">
            </div>
            <div class="form-group">
                <label>Incident Date</label>
                <input type="date" name="incident_date" value="<?= $v('incident_date') ?>">
            </div>
            <div class="form-group">
                <label>Report / Complaint Date</label>
                <input type="date" name="report_date" value="<?= $v('report_date') ?>">
            </div>
            <div class="form-group">
                <label>Case Status</label>
                <select name="status" onchange="toggleClosed(this)">
                    <?php foreach(['Open','Under Investigation','Appealed','Closed'] as $s): ?>
                    <option value="<?=$s?>" <?= $sel('status',$s) ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Description / Details <span class="req">*</span></label>
                <textarea name="description" required placeholder="Full description of the incident or offence..."><?= $v('description') ?></textarea>
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head" style="background:linear-gradient(90deg,#721c24,#a72030);"><i class="fas fa-hammer"></i> Action Taken</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group full">
                <label>Action Taken</label>
                <textarea name="action_taken" placeholder="Describe the disciplinary action, hearing, or outcome..."><?= $v('action_taken') ?></textarea>
            </div>
            <div class="form-group">
                <label>Action Date</label>
                <input type="date" name="action_date" value="<?= $v('action_date') ?>">
            </div>
            <div class="form-group">
                <label>Penalty Imposed</label>
                <input type="text" name="penalty" value="<?= $v('penalty') ?>" placeholder="e.g. Written Warning, Suspension 2 weeks, Demotion">
            </div>
            <div class="form-group" id="closedDateGroup" style="<?= (($row['status']??'')==='Closed')?'':'display:none' ?>">
                <label>Date Closed / Resolved</label>
                <input type="date" name="closed_date" value="<?= $v('closed_date') ?>">
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>
<script>
function toggleClosed(sel) {
    document.getElementById('closedDateGroup').style.display = sel.value==='Closed' ? '' : 'none';
}
</script>
</body></html>
