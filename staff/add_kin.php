<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../public/login.php"); exit; }

$id_number = $_GET['id_number'] ?? '';
$edit_id   = (int)($_GET['id'] ?? 0);

if ($edit_id) {
    $row = $conn->query("SELECT * FROM employee_next_of_kin WHERE kin_id=$edit_id")->fetch_assoc();
    if (!$row) { header("Location: county_staff_list.php"); exit; }
    $id_number = $row['id_number'];
}
if (!$id_number) { header("Location: county_staff_list.php"); exit; }

$staff = $conn->query("SELECT * FROM county_staff WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc();
if (!$staff) { header("Location: county_staff_list.php"); exit; }
$full_name = trim($staff['first_name'].' '.$staff['last_name'].(!empty($staff['other_name'])?' '.$staff['other_name']:''));

// Get current max priority
$max_pri = $conn->query("SELECT MAX(priority_order) m FROM employee_next_of_kin WHERE id_number='".mysqli_real_escape_string($conn,$id_number)."'")->fetch_assoc()['m'] ?? 0;
$next_pri = $max_pri + 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_esc = mysqli_real_escape_string($conn, $id_number);
    $f = fn($k) => "'".mysqli_real_escape_string($conn, trim($_POST[$k] ?? ''))."'";
    $pri = (int)($_POST['priority_order'] ?? 1);
    $pid = (int)($_POST['edit_id'] ?? 0);
    if ($pid) {
        $conn->query("UPDATE employee_next_of_kin SET
            kin_name={$f('kin_name')}, kin_relationship={$f('kin_relationship')},
            kin_phone={$f('kin_phone')}, kin_alternate_phone={$f('kin_alternate_phone')},
            kin_email={$f('kin_email')}, kin_address={$f('kin_address')},
            kin_city_town={$f('kin_city_town')}, kin_county={$f('kin_county')},
            is_emergency_contact={$f('is_emergency_contact')}, priority_order=$pri
            WHERE kin_id=$pid");
    } else {
        $conn->query("INSERT INTO employee_next_of_kin
            (id_number,kin_name,kin_relationship,kin_phone,kin_alternate_phone,
             kin_email,kin_address,kin_city_town,kin_county,is_emergency_contact,priority_order)
            VALUES ('$id_esc',{$f('kin_name')},{$f('kin_relationship')},
            {$f('kin_phone')},{$f('kin_alternate_phone')},{$f('kin_email')},
            {$f('kin_address')},{$f('kin_city_town')},{$f('kin_county')},
            {$f('is_emergency_contact')},$pri)");
    }
    $_SESSION['success_message'] = "Next of kin saved.";
    header("Location: employee_profile.php?id_number=".urlencode($id_number)."#kin"); exit;
}

$v   = fn($fld) => htmlspecialchars($row[$fld] ?? '');
$sel = fn($fld,$o) => (($row[$fld] ?? '')===$o)?'selected':'';
$back_tab = 'kin';

$ke_counties = ['Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay','Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu','Machakos','Makueni','Mandera','Marsabit','Meru','Migori','Mombasa','Murang\'a','Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu','Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans Nzoia','Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $edit_id?'Edit':'Add' ?> Next of Kin – <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'form_style.php'; ?>
</head><body><div class="container">
<?php include 'profile_back_bar.php'; ?>

<div class="form-page-header">
    <div class="fph-left">
        <i class="fas fa-users fa-2x"></i>
        <div><h1><?= $edit_id?'Edit':'Add' ?> Next of Kin</h1>
        <p><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($id_number) ?></p></div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">
<input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number) ?>">

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-user-friends"></i> Kin Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group" style="grid-column:1/3">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="kin_name" value="<?= $v('kin_name') ?>" required>
            </div>
            <div class="form-group">
                <label>Relationship <span class="req">*</span></label>
                <select name="kin_relationship" required>
                    <option value="">-- Select --</option>
                    <?php foreach(['Spouse','Parent','Child','Sibling','Guardian','Uncle','Aunt','Nephew','Niece','Cousin','Friend','Other'] as $r): ?>
                    <option value="<?=$r?>" <?= (($row['kin_relationship']??'')===$r)?'selected':'' ?>><?=$r?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Primary Phone <span class="req">*</span></label>
                <input type="tel" name="kin_phone" value="<?= $v('kin_phone') ?>" required>
            </div>
            <div class="form-group">
                <label>Alternate Phone</label>
                <input type="tel" name="kin_alternate_phone" value="<?= $v('kin_alternate_phone') ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="kin_email" value="<?= $v('kin_email') ?>">
            </div>
            <div class="form-group">
                <label>Is Emergency Contact?</label>
                <select name="is_emergency_contact">
                    <option value="Yes" <?= $sel('is_emergency_contact','Yes') ?>>Yes</option>
                    <option value="No"  <?= $sel('is_emergency_contact','No') ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label>Priority Order <span class="field-hint">(1 = first contact)</span></label>
                <input type="number" name="priority_order" min="1" max="10"
                       value="<?= $edit_id ? $v('priority_order') : $next_pri ?>">
            </div>
        </div>
    </div>
</div>

<div class="form-card">
    <div class="form-card-head"><i class="fas fa-map-marker-alt"></i> Address Details</div>
    <div class="form-card-body">
        <div class="fg fg-3">
            <div class="form-group full">
                <label>Physical / Postal Address</label>
                <input type="text" name="kin_address" value="<?= $v('kin_address') ?>" placeholder="Street, estate, P.O. Box...">
            </div>
            <div class="form-group">
                <label>Town / City</label>
                <input type="text" name="kin_city_town" value="<?= $v('kin_city_town') ?>">
            </div>
            <div class="form-group">
                <label>County</label>
                <select name="kin_county">
                    <option value="">-- Select County --</option>
                    <?php foreach($ke_counties as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= (($row['kin_county']??'')===$c)?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<?php include 'form_actions.php'; ?>
</form>
</div></body></html>
