<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_languages WHERE language_id=$edit_id")->fetch_assoc();
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
        $conn->query("UPDATE employee_languages SET
            language_name={$f('language_name')}, proficiency={$f('proficiency')},
            speaking={$f('speaking')}, writing={$f('writing')},
            reading={$f('reading')}, certification={$f('certification')}
            WHERE language_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_languages
            (id_number,language_name,proficiency,speaking,writing,reading,certification)
            VALUES ('$id_esc',{$f('language_name')},{$f('proficiency')},
            {$f('speaking')},{$f('writing')},{$f('reading')},{$f('certification')})");
    }
    $_SESSION['success_message'] = "Language saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#languages"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'languages';

$common_languages = ['English','Kiswahili','French','Arabic','German','Chinese (Mandarin)','Spanish','Portuguese','Somali','Luo','Kikuyu','Kamba','Luhya','Meru','Kalenjin','Other'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Language – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
<style>
.rating-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:4px; }
.rating-item { text-align:center; }
.rating-item label.rl { font-size:11px; color:#777; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:6px; }
.rating-pills { display:flex; flex-direction:column; gap:4px; }
.rp-radio { display:none; }
.rp-label { display:block; padding:6px 10px; border:2px solid #e0e8f0; border-radius:7px;
    font-size:12px; cursor:pointer; text-align:center; transition:all .15s; background:#fafcff; }
.rp-label:hover { border-color:#0D1A63; }
.rp-radio:checked + .rp-label { background:#0D1A63; color:#fff; border-color:#0D1A63; font-weight:700; }
</style>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-language fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Language</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-language"></i> Language Information</div>
    <div class="form-card-body">
        <div class="fg fg-3" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Language <span class="req">*</span></label>
                <select name="language_name" required>
                    <option value="">-- Select Language --</option>
                    <?php foreach($common_languages as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>" <?= (($row['language_name']??'')===$l)?'selected':'' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Overall Proficiency <span class="req">*</span></label>
                <select name="proficiency" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['Native','Fluent','Working Knowledge','Basic'] as $p): ?>
                    <option value="<?=$p?>" <?= $sel('proficiency',$p) ?>><?=$p?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Certification / Test <span class="field-hint">(optional)</span></label>
                <input type="text" name="certification" value="<?= $v('certification') ?>" placeholder="e.g. IELTS 7.5, DELF B2">
            </div>
        </div>

        <!-- Rating grid -->
        <div class="section-sub"><i class="fas fa-sliders-h"></i> Skill Ratings</div>
        <div class="rating-grid">
            <?php
            $skills = ['speaking' => 'Speaking', 'writing' => 'Writing', 'reading' => 'Reading'];
            $levels = ['Excellent','Good','Fair','Poor'];
            foreach ($skills as $sk => $label):
                $cur = $row[$sk] ?? 'Good';
            ?>
            <div class="rating-item">
                <label class="rl"><?= $label ?></label>
                <div class="rating-pills">
                    <?php foreach($levels as $lv): ?>
                    <input type="radio" name="<?=$sk?>" id="<?=$sk?>_<?=$lv?>" class="rp-radio" value="<?=$lv?>"
                           <?= ($cur===$lv)?'checked':(!$edit_id&&$lv==='Good'?'checked':'') ?> required>
                    <label class="rp-label" for="<?=$sk?>_<?=$lv?>"><?=$lv?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div></body></html>
