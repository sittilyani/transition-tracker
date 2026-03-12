<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_appraisals WHERE appraisal_id=$edit_id")->fetch_assoc();
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
    $rating = !empty($_POST['overall_rating']) ? (float)$_POST['overall_rating'] : 'NULL';
    $year   = (int)($_POST['appraisal_year'] ?? date('Y'));
    $pid    = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_appraisals SET
            appraisal_period={$f('appraisal_period')}, appraisal_year=$year,
            appraisal_date={$f('appraisal_date')}, supervisor_name={$f('supervisor_name')},
            supervisor_id={$f('supervisor_id')}, overall_rating=$rating,
            comments={$f('comments')}, next_appraisal_date={$f('next_appraisal_date')}
            WHERE appraisal_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_appraisals
            (id_number,appraisal_period,appraisal_year,appraisal_date,supervisor_name,
             supervisor_id,overall_rating,comments,next_appraisal_date)
            VALUES ('$id_esc',{$f('appraisal_period')},$year,
            {$f('appraisal_date')},{$f('supervisor_name')},{$f('supervisor_id')},
            $rating,{$f('comments')},{$f('next_appraisal_date')})");
    }
    $_SESSION['success_message'] = "Appraisal record saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#appraisals"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'appraisals';
$cur_rating = $row['overall_rating'] ?? '';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Appraisal – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
<style>
.star-rating { display:flex; flex-direction:row-reverse; gap:4px; justify-content:flex-end; }
.star-rating input { display:none; }
.star-rating label { font-size:28px; color:#ddd; cursor:pointer; transition:color .15s; }
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label { color:#ffc107; }
.rating-display { font-size:22px; font-weight:800; color:#0D1A63; margin-top:6px; }
.rating-bar { height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden; margin-top:6px; }
.rating-fill { height:100%; background:linear-gradient(90deg,#0D1A63,#28a745); border-radius:4px; transition:width .3s; }
</style>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-chart-line fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Performance Appraisal</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-calendar-check"></i> Appraisal Period</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Appraisal Period <span class="req">*</span></label>
                <select name="appraisal_period" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['Q1 (Jan–Mar)','Q2 (Apr–Jun)','Q3 (Jul–Sep)','Q4 (Oct–Dec)','Mid-Year','Annual','Probation Review','Other'] as $p): ?>
                    <option value="<?=$p?>" <?= (($row['appraisal_period']??'')===$p)?'selected':'' ?>><?=$p?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Appraisal Year <span class="req">*</span></label>
                <select name="appraisal_year" required>
                    <?php for($y=date('Y');$y>=2010;$y--): ?>
                    <option value="<?=$y?>" <?= (($row['appraisal_year']??date('Y'))==$y)?'selected':'' ?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Appraisal Date</label>
                <input type="date" name="appraisal_date" value="<?= $v('appraisal_date') ?>">
            </div>
            <div class="form-group">
                <label>Next Appraisal Date</label>
                <input type="date" name="next_appraisal_date" value="<?= $v('next_appraisal_date') ?>">
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-check"></i> Supervisor / Appraiser</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group">
                <label>Supervisor Name <span class="req">*</span></label>
                <input type="text" name="supervisor_name" value="<?= $v('supervisor_name') ?>" required>
            </div>
            <div class="form-group">
                <label>Supervisor ID Number</label>
                <input type="text" name="supervisor_id" value="<?= $v('supervisor_id') ?>">
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-star"></i> Overall Rating</div>
    <div class="form-card-body">
        <p style="color:#888;font-size:13px;margin-bottom:14px;">Rate overall performance on a scale of 1–5 (1 = Poor, 5 = Exceptional). The score is stored as a decimal (e.g. 3.75).</p>
        <div class="fg fg-2">
            <div class="form-group">
                <label>Score (0.00 – 5.00)</label>
                <input type="number" name="overall_rating" min="0" max="5" step="0.01"
                       value="<?= $cur_rating ?>" id="ratingInput" oninput="updateBar()">
                <div class="rating-bar" style="margin-top:10px;">
                    <div class="rating-fill" id="ratingFill" style="width:<?= $cur_rating ? ($cur_rating/5*100) : 0 ?>%"></div>
                </div>
                <div style="margin-top:6px;">
                    <span id="ratingLabel" style="font-size:13px;font-weight:700;color:#0D1A63;">
                        <?php
                        $r = (float)$cur_rating;
                        if($r==0) echo '—';
                        elseif($r<=1) echo '⚠️ Unsatisfactory';
                        elseif($r<=2) echo '📋 Needs Improvement';
                        elseif($r<=3) echo '✅ Meets Expectations';
                        elseif($r<=4) echo '🌟 Exceeds Expectations';
                        else echo '🏆 Exceptional';
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="form-group" style="margin-top:16px;">
            <label>Comments / Observations</label>
            <textarea name="comments" rows="5" placeholder="Supervisor's observations, strengths, areas for improvement, development goals..."><?= $v('comments') ?></textarea>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div>
<script>
function updateBar() {
    const val = parseFloat(document.getElementById('ratingInput').value) || 0;
    document.getElementById('ratingFill').style.width = Math.min(val/5*100, 100) + '%';
    const labels = ['—','⚠️ Unsatisfactory','📋 Needs Improvement','✅ Meets Expectations','🌟 Exceeds Expectations','🏆 Exceptional'];
    const idx = val === 0 ? 0 : Math.min(Math.ceil(val), 5);
    document.getElementById('ratingLabel').textContent = labels[idx];
}
</script>
</body></html>
