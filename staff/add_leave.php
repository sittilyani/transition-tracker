<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_leave WHERE leave_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc      = mysqli_real_escape_string($conn, $id_number);
    $f           = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $days_req    = (int)($_POST['days_requested'] ?? 0);
    $days_appr   = !empty($_POST['days_approved']) ? (int)$_POST['days_approved'] : 'NULL';
    $pid         = (int)($_POST['edit_id'] ?? 0);

    if ($pid) {
        $conn->query("UPDATE employee_leave SET
            leave_type={$f('leave_type')}, start_date={$f('start_date')},
            end_date={$f('end_date')}, days_requested=$days_req,
            days_approved=$days_appr, reason={$f('reason')},
            approver_name={$f('approver_name')}, approval_date={$f('approval_date')},
            status={$f('status')}, remarks={$f('remarks')}
            WHERE leave_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_leave
            (id_number,leave_type,start_date,end_date,days_requested,days_approved,
             reason,approver_name,approval_date,status,remarks)
            VALUES ('$id_esc',{$f('leave_type')},{$f('start_date')},{$f('end_date')},
            $days_req,$days_appr,{$f('reason')},{$f('approver_name')},
            {$f('approval_date')},{$f('status')},{$f('remarks')})");
    }
    $_SESSION['success_message'] = "Leave record saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#leave"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'leave';

$leave_types = ['Annual','Sick','Maternity','Paternity','Compassionate','Study','Unpaid','Other'];
$statuses    = ['Pending','Approved','Rejected','Cancelled'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Leave – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
<style>
.leave-type-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:4px; }
.lt-radio { display:none; }
.lt-label {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:14px 8px; border:2px solid #e0e8f0; border-radius:10px;
    cursor:pointer; font-size:12px; color:#555; text-align:center;
    background:#fafcff; transition:all .15s; gap:6px;
}
.lt-label i { font-size:20px; color:#aaa; transition:color .15s; }
.lt-label:hover { border-color:#0D1A63; }
.lt-radio:checked + .lt-label { background:#0D1A63; color:#fff; border-color:#0D1A63; font-weight:700; }
.lt-radio:checked + .lt-label i { color:#fff; }
</style>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-calendar-minus fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Record' ?> Leave</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<!-- Leave Type -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-tag"></i> Leave Type <span style="font-weight:400;font-size:12px;opacity:.8;">— select one</span></div>
    <div class="form-card-body">
        <?php
        $lt_icons = [
            'Annual'=>'fa-umbrella-beach', 'Sick'=>'fa-procedures',
            'Maternity'=>'fa-baby', 'Paternity'=>'fa-male',
            'Compassionate'=>'fa-heart', 'Study'=>'fa-book',
            'Unpaid'=>'fa-hand-holding-usd', 'Other'=>'fa-ellipsis-h'
        ];
        ?>
        <div class="leave-type-grid">
            <?php foreach($leave_types as $lt): $cur_lt = $row['leave_type'] ?? ''; ?>
            <input type="radio" name="leave_type" id="lt_<?= str_replace(' ','_',$lt) ?>"
                   class="lt-radio" value="<?=$lt?>"
                   <?= ($cur_lt===$lt)?'checked':(!$edit_id&&$lt==='Annual'?'checked':'') ?> required>
            <label class="lt-label" for="lt_<?= str_replace(' ','_',$lt) ?>">
                <i class="fas <?= $lt_icons[$lt] ?? 'fa-tag' ?>"></i>
                <?=$lt?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Duration -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-calendar-alt"></i> Leave Duration</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Start Date <span class="req">*</span></label>
                <input type="date" name="start_date" id="startDate" value="<?= $v('start_date') ?>"
                       required onchange="calcDays()">
            </div>
            <div class="form-group">
                <label>End Date <span class="req">*</span></label>
                <input type="date" name="end_date" id="endDate" value="<?= $v('end_date') ?>"
                       required onchange="calcDays()">
            </div>
            <div class="form-group">
                <label>Days Requested <span class="field-hint">— auto-calculated</span></label>
                <input type="number" name="days_requested" id="daysReq" min="1"
                       value="<?= $v('days_requested') ?>" readonly
                       style="background:#f0f3fb;color:#0D1A63;font-weight:700;">
            </div>
            <div class="form-group full">
                <label>Reason / Purpose <span class="req">*</span></label>
                <textarea name="reason" required placeholder="State the reason for this leave request..."><?= $v('reason') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- Approval -->
<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-check"></i> Approval Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach($statuses as $s): ?>
                    <option value="<?=$s?>" <?= $sel('status',$s)?:($s==='Pending'&&!$edit_id?'selected':'') ?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Days Approved</label>
                <input type="number" name="days_approved" min="0" value="<?= $v('days_approved') ?>">
            </div>
            <div class="form-group">
                <label>Approval Date</label>
                <input type="date" name="approval_date" value="<?= $v('approval_date') ?>">
            </div>
            <div class="form-group" style="grid-column:1/3">
                <label>Approver Name</label>
                <input type="text" name="approver_name" value="<?= $v('approver_name') ?>">
            </div>
            <div class="form-group full">
                <label>Remarks</label>
                <textarea name="remarks" placeholder="Any additional remarks from the approver..."><?= $v('remarks') ?></textarea>
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>
<script>
function calcDays() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    if (s && e) {
        const diff = (new Date(e) - new Date(s)) / (1000*60*60*24) + 1;
        document.getElementById('daysReq').value = diff > 0 ? diff : '';
    }
}
// Init on page load if editing
calcDays();
</script>
</body></html>
