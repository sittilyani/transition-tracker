<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// -- Edit mode: load existing asset -------------------------------------------
$edit_id   = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$edit_data = null;
if ($edit_id > 0) {
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM assets WHERE asset_id = $edit_id"));
    if (!$edit_data) {
        $_SESSION['error_msg'] = "Asset not found.";
        header('Location: assets_dashboard.php');
        exit();
    }
}

// -- Auto-generate next asset code (AST-YYYY-NNNNN) ---------------------------
$year     = date('Y');
$last_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT asset_code FROM assets
     WHERE asset_code LIKE 'AST-$year-%'
     ORDER BY asset_id DESC LIMIT 1"));
if ($last_row) {
    $last_num    = (int)substr($last_row['asset_code'], -5);
    $next_code   = 'AST-' . $year . '-' . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);
} else {
    $next_code   = 'AST-' . $year . '-00001';
}
$suggested_code = $edit_data['asset_code'] ?? $next_code;

// -- Fetch categories ----------------------------------------------------------
$categories = mysqli_query($conn,
    "SELECT * FROM asset_categories ORDER BY category_name");

// -- Handle form submission ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_asset'])) {

    $asset_code     = trim($_POST['asset_code']     ?? '');
    $category_id    = (int)($_POST['category_id']   ?? 0);
    $asset_name     = trim($_POST['asset_name']     ?? '');
    $make           = trim($_POST['make']           ?? '');
    $model          = trim($_POST['model']          ?? '');
    $serial_number  = trim($_POST['serial_number']  ?? '');
    $color          = trim($_POST['color']          ?? '');
    $purchase_date  = !empty($_POST['purchase_date'])  ? $_POST['purchase_date']  : null;
    $purchase_price = !empty($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;
    $supplier       = trim($_POST['supplier']       ?? '');
    $warranty_expiry= !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $condition_state= $_POST['condition_state']     ?? 'Good';
    $current_status = $_POST['current_status']      ?? 'Available';
    $notes          = trim($_POST['notes']          ?? '');
    $created_by     = $_SESSION['full_name']        ?? 'Admin';
    $is_edit        = (int)($_POST['edit_id']       ?? 0);

    // -- Validation ------------------------------------------------------------
    $errors = [];
    if (!$asset_code)  $errors[] = "Asset code is required.";
    if (!$category_id) $errors[] = "Please select a category.";
    if (!$asset_name)  $errors[] = "Asset name is required.";

    // Check asset_code uniqueness (skip own record when editing)
    if ($asset_code) {
        $code_esc = mysqli_real_escape_string($conn, $asset_code);
        $dupe_q   = "SELECT asset_id FROM assets WHERE asset_code='$code_esc'";
        if ($is_edit) $dupe_q .= " AND asset_id != $is_edit";
        $dupe = mysqli_fetch_assoc(mysqli_query($conn, $dupe_q));
        if ($dupe) $errors[] = "Asset code <strong>$asset_code</strong> already exists.";
    }

    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode('<br>', $errors);
        header('Location: add_asset.php' . ($is_edit ? "?asset_id=$is_edit" : ''));
        exit();
    }

    // -- Escape all strings ----------------------------------------------------
    $esc = function($v) use ($conn) {
        return $v !== null ? "'" . mysqli_real_escape_string($conn, (string)$v) . "'" : 'NULL';
    };

    $f_code     = $esc($asset_code);
    $f_name     = $esc($asset_name);
    $f_make     = $esc($make);
    $f_model    = $esc($model);
    $f_serial   = $esc($serial_number);
    $f_color    = $esc($color);
    $f_pdate    = $esc($purchase_date);
    $f_price    = $purchase_price !== null ? $purchase_price : 'NULL';
    $f_supplier = $esc($supplier);
    $f_warranty = $esc($warranty_expiry);
    $f_cond     = $esc($condition_state);
    $f_status   = $esc($current_status);
    $f_notes    = $esc($notes);
    $f_by       = $esc($created_by);

    if ($is_edit) {
        $sql = "UPDATE assets SET
                    asset_code      = $f_code,
                    category_id     = $category_id,
                    asset_name      = $f_name,
                    make            = $f_make,
                    model           = $f_model,
                    serial_number   = $f_serial,
                    color           = $f_color,
                    purchase_date   = $f_pdate,
                    purchase_price  = $f_price,
                    supplier        = $f_supplier,
                    warranty_expiry = $f_warranty,
                    condition_state = $f_cond,
                    current_status  = $f_status,
                    notes           = $f_notes
                WHERE asset_id = $is_edit";
        $label = "updated";
    } else {
        $sql = "INSERT INTO assets
                    (asset_code, category_id, asset_name, make, model, serial_number,
                     color, purchase_date, purchase_price, supplier, warranty_expiry,
                     condition_state, current_status, notes, created_by)
                VALUES
                    ($f_code, $category_id, $f_name, $f_make, $f_model, $f_serial,
                     $f_color, $f_pdate, $f_price, $f_supplier, $f_warranty,
                     $f_cond, $f_status, $f_notes, $f_by)";
        $label = "registered";
    }

    if (mysqli_query($conn, $sql)) {
        $new_id = $is_edit ? $is_edit : mysqli_insert_id($conn);
        $_SESSION['success_msg'] = "Asset <strong>" . htmlspecialchars($asset_name) . "</strong> $label successfully.";
        // Add another or go to dashboard
        if (isset($_POST['save_and_add'])) {
            header('Location: add_asset.php');
        } else {
            header('Location: assets_dashboard.php');
        }
    } else {
        $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        header('Location: add_asset.php' . ($is_edit ? "?asset_id=$is_edit" : ''));
    }
    exit();
}

$msg   = $_SESSION['success_msg'] ?? '';
$error = $_SESSION['error_msg']   ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// helper: old value (edit) or empty
$v = function($field, $default = '') use ($edit_data) {
    return htmlspecialchars($edit_data[$field] ?? $default);
};
$sel = function($field, $value) use ($edit_data) {
    return (isset($edit_data[$field]) && $edit_data[$field] === $value) ? 'selected' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $edit_id ? 'Edit Asset' : 'Register New Asset' ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f0f3fb; padding:20px; }

/* -- Header -- */
.page-header {
    background:linear-gradient(135deg,#0D1A63 0%,#1a3a8f 100%);
    color:#fff; padding:22px 30px; border-radius:14px; margin-bottom:22px;
    display:flex; justify-content:space-between; align-items:center;
    box-shadow:0 8px 24px rgba(13,26,99,.25);
}
.page-header h1 { font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; }
.hdr-links a {
    color:#fff; text-decoration:none; background:rgba(255,255,255,.15);
    padding:7px 14px; border-radius:8px; font-size:13px; margin-left:8px;
    transition:background .2s;
}
.hdr-links a:hover { background:rgba(255,255,255,.28); }

/* -- Alerts -- */
.alert { padding:13px 18px; border-radius:9px; margin-bottom:18px; font-size:14px;
    display:flex; align-items:flex-start; gap:10px; }
.alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.alert i { margin-top:1px; flex-shrink:0; }

/* -- Cards -- */
.card {
    background:#fff; border-radius:14px;
    box-shadow:0 2px 16px rgba(0,0,0,.07); margin-bottom:22px; overflow:hidden;
}
.card-head {
    background:linear-gradient(90deg,#0D1A63,#1a3a8f);
    color:#fff; padding:13px 22px;
    font-size:14.5px; font-weight:700;
    display:flex; align-items:center; gap:9px;
}
.card-head .badge-edit {
    margin-left:auto; background:rgba(255,255,255,.2);
    border-radius:6px; padding:2px 10px; font-size:11px; font-weight:500;
}
.card-body { padding:22px 24px; }

/* -- Form grid -- */
.form-grid { display:grid; gap:18px; }
.fg-2 { grid-template-columns:1fr 1fr; }
.fg-3 { grid-template-columns:1fr 1fr 1fr; }
.fg-4 { grid-template-columns:1fr 1fr 1fr 1fr; }
.full { grid-column:1/-1; }

.form-group label {
    display:block; font-size:12.5px; font-weight:700; color:#555;
    text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px;
}
.form-group label .req { color:#dc3545; font-style:normal; margin-left:2px; }
.form-group label .hint {
    font-size:10.5px; color:#aaa; font-weight:400;
    text-transform:none; letter-spacing:0; margin-left:4px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width:100%; padding:10px 13px; border:2px solid #e0e8f0; border-radius:9px;
    font-size:13.5px; font-family:inherit; color:#222;
    transition:border-color .2s, box-shadow .2s; background:#fafcff;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline:none; border-color:#0D1A63; background:#fff;
    box-shadow:0 0 0 3px rgba(13,26,99,.1);
}
.form-group input.readonly-code {
    background:#f0f3fb; color:#0D1A63; font-weight:700;
    font-family:'Courier New', monospace; letter-spacing:.5px;
}
.form-group textarea { resize:vertical; min-height:80px; }

/* -- Category grid selector -- */
.cat-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px;
    margin-top:2px;
}
.cat-option { display:none; }
.cat-label {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:6px; padding:12px 8px; border:2px solid #e0e8f0; border-radius:10px;
    cursor:pointer; text-align:center; font-size:12px; color:#555;
    background:#fafcff; transition:all .2s; user-select:none;
}
.cat-label i { font-size:20px; color:#aaa; transition:color .2s; }
.cat-label:hover { border-color:#0D1A63; background:#f0f3fb; }
.cat-label:hover i { color:#0D1A63; }
.cat-option:checked + .cat-label {
    border-color:#0D1A63; background:#0D1A63; color:#fff; font-weight:700;
}
.cat-option:checked + .cat-label i { color:#fff; }

/* -- Status & condition pill selectors -- */
.pill-group { display:flex; flex-wrap:wrap; gap:8px; margin-top:2px; }
.pill-radio { display:none; }
.pill-label {
    padding:7px 16px; border-radius:20px; border:2px solid #e0e8f0;
    font-size:12.5px; font-weight:600; cursor:pointer;
    background:#fafcff; color:#666; transition:all .2s;
}
.pill-label:hover { border-color:#0D1A63; color:#0D1A63; }

/* Condition colours */
.pill-radio[data-type="condition"][value="New"]:checked     + .pill-label { background:#0D1A63; border-color:#0D1A63; color:#fff; }
.pill-radio[data-type="condition"][value="Good"]:checked    + .pill-label { background:#28a745; border-color:#28a745; color:#fff; }
.pill-radio[data-type="condition"][value="Fair"]:checked    + .pill-label { background:#ffc107; border-color:#ffc107; color:#212529; }
.pill-radio[data-type="condition"][value="Poor"]:checked    + .pill-label { background:#fd7e14; border-color:#fd7e14; color:#fff; }
.pill-radio[data-type="condition"][value="Condemned"]:checked + .pill-label { background:#dc3545; border-color:#dc3545; color:#fff; }

/* Status colours */
.pill-radio[data-type="status"][value="Available"]:checked    + .pill-label { background:#28a745; border-color:#28a745; color:#fff; }
.pill-radio[data-type="status"][value="Allocated"]:checked    + .pill-label { background:#ffc107; border-color:#ffc107; color:#212529; }
.pill-radio[data-type="status"][value="Under Repair"]:checked + .pill-label { background:#17a2b8; border-color:#17a2b8; color:#fff; }
.pill-radio[data-type="status"][value="Condemned"]:checked    + .pill-label { background:#dc3545; border-color:#dc3545; color:#fff; }
.pill-radio[data-type="status"][value="Lost"]:checked         + .pill-label { background:#6c757d; border-color:#6c757d; color:#fff; }

/* -- Divider -- */
hr.sec { border:none; border-top:1px dashed #dce8f5; margin:6px 0 2px 0; }

/* -- Action buttons -- */
.action-bar {
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:12px; padding:18px 24px;
    background:#f8faff; border-top:1px solid #eef0f8;
}
.action-bar .left  { display:flex; gap:10px; }
.action-bar .right { display:flex; gap:10px; }

.btn {
    padding:10px 22px; border:none; border-radius:9px; font-size:13.5px;
    font-weight:600; cursor:pointer; display:inline-flex; align-items:center;
    gap:8px; transition:all .22s; text-decoration:none; font-family:inherit;
}
.btn-primary   { background:#0D1A63; color:#fff; }
.btn-primary:hover { background:#1a2a7a; transform:translateY(-1px);
    box-shadow:0 5px 16px rgba(13,26,99,.3); }
.btn-success   { background:#28a745; color:#fff; }
.btn-success:hover { background:#218838; transform:translateY(-1px); }
.btn-secondary { background:#6c757d; color:#fff; }
.btn-secondary:hover { background:#5a6268; }
.btn-outline   { background:#fff; color:#0D1A63; border:2px solid #0D1A63; }
.btn-outline:hover { background:#f0f3fb; }

/* -- Price prefix -- */
.input-prefix-wrap { position:relative; }
.input-prefix-wrap .prefix {
    position:absolute; left:12px; top:50%; transform:translateY(-50%);
    color:#aaa; font-size:14px; font-weight:600; pointer-events:none;
}
.input-prefix-wrap input { padding-left:28px; }

/* -- Responsive -- */
@media(max-width:900px) { .fg-4 { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) {
    .fg-2, .fg-3, .fg-4 { grid-template-columns:1fr; }
    .cat-grid { grid-template-columns:repeat(3,1fr); }
}
</style>
</head>
<body>

<!-- Header -->
<div class="page-header">
    <h1>
        <i class="fas <?= $edit_id ? 'fa-edit' : 'fa-plus-circle' ?>"></i>
        <?= $edit_id ? 'Edit Asset' : 'Register New Asset' ?>
    </h1>
    <div class="hdr-links">
        <a href="assets_dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a>
        <a href="allocate_asset.php"><i class="fas fa-user-tag"></i> Allocate</a>
        <a href="staffslist.php"><i class="fas fa-users"></i> Staff</a>
    </div>
</div>

<!-- Alerts -->
<?php if ($msg):  ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><div><?= $msg ?></div></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><div><?= $error ?></div></div><?php endif; ?>

<form method="POST" id="assetForm" novalidate>
<input type="hidden" name="save_asset" value="1">
<input type="hidden" name="edit_id"   value="<?= $edit_id ?>">

<!-- -- SECTION 1: Category --------------------------------------------------- -->
<div class="card">
    <div class="card-head">
        <i class="fas fa-th-large"></i> Asset Category
        <?php if ($edit_id): ?><span class="badge-edit">Editing ID #<?= $edit_id ?></span><?php endif; ?>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label>Select Category <span class="req">*</span></label>
            <div class="cat-grid">
                <?php
                mysqli_data_seek($categories, 0);
                while ($cat = mysqli_fetch_assoc($categories)):
                    $checked = (isset($edit_data['category_id']) && $edit_data['category_id'] == $cat['category_id'])
                               ? 'checked' : '';
                ?>
                <input type="radio" name="category_id" id="cat_<?= $cat['category_id'] ?>"
                       class="cat-option" value="<?= $cat['category_id'] ?>" <?= $checked ?> required>
                <label class="cat-label" for="cat_<?= $cat['category_id'] ?>">
                    <i class="fas <?= htmlspecialchars($cat['category_icon']) ?>"></i>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </label>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- -- SECTION 2: Core Details ----------------------------------------------- -->
<div class="card">
    <div class="card-head"><i class="fas fa-info-circle"></i> Asset Details</div>
    <div class="card-body">
        <div class="form-grid fg-2">

            <div class="form-group">
                <label>Asset Code <span class="req">*</span>
                    <span class="hint">— auto-generated, editable</span></label>
                <input type="text" name="asset_code" id="asset_code"
                       class="readonly-code"
                       value="<?= $v('asset_code', $suggested_code) ?>"
                       placeholder="e.g. AST-2024-00001" required
                       oninput="this.value=this.value.toUpperCase()">
            </div>

            <div class="form-group">
                <label>Asset Name <span class="req">*</span>
                    <span class="hint">— e.g. Dell Latitude 5520</span></label>
                <input type="text" name="asset_name"
                       value="<?= $v('asset_name') ?>"
                       placeholder="Full descriptive name" required>
            </div>

            <div class="form-group">
                <label>Make / Brand
                    <span class="hint">— e.g. Dell, Toyota, HP</span></label>
                <input type="text" name="make"
                       value="<?= $v('make') ?>" placeholder="Manufacturer / Brand">
            </div>

            <div class="form-group">
                <label>Model
                    <span class="hint">— e.g. Latitude 5520, Hilux</span></label>
                <input type="text" name="model"
                       value="<?= $v('model') ?>" placeholder="Model number or name">
            </div>

            <div class="form-group">
                <label>Serial Number</label>
                <input type="text" name="serial_number"
                       value="<?= $v('serial_number') ?>"
                       placeholder="Manufacturer serial number">
            </div>

            <div class="form-group">
                <label>Color</label>
                <input type="text" name="color"
                       value="<?= $v('color') ?>" placeholder="e.g. Black, Silver, White">
            </div>

        </div>
    </div>
</div>

<!-- -- SECTION 3: Purchase & Warranty --------------------------------------- -->
<div class="card">
    <div class="card-head"><i class="fas fa-receipt"></i> Purchase &amp; Warranty Information</div>
    <div class="card-body">
        <div class="form-grid fg-2">

            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" name="purchase_date"
                       value="<?= $v('purchase_date') ?>">
            </div>

            <div class="form-group">
                <label>Purchase Price <span class="hint">— KES</span></label>
                <div class="input-prefix-wrap">
                    <span class="prefix">KES</span>
                    <input type="number" name="purchase_price" min="0" step="0.01"
                           value="<?= $v('purchase_price') ?>"
                           placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label>Supplier / Vendor</label>
                <input type="text" name="supplier"
                       value="<?= $v('supplier') ?>"
                       placeholder="Company or person supplied from">
            </div>

            <div class="form-group">
                <label>Warranty Expiry Date</label>
                <input type="date" name="warranty_expiry"
                       value="<?= $v('warranty_expiry') ?>">
            </div>

        </div>
    </div>
</div>

<!-- -- SECTION 4: Condition & Status ---------------------------------------- -->
<div class="card">
    <div class="card-head"><i class="fas fa-heartbeat"></i> Condition &amp; Status</div>
    <div class="card-body">
        <div class="form-grid fg-2">

            <div class="form-group">
                <label>Physical Condition <span class="req">*</span></label>
                <div class="pill-group">
                    <?php
                    $conditions = ['New','Good','Fair','Poor','Condemned'];
                    $def_cond   = $edit_data['condition_state'] ?? 'Good';
                    foreach ($conditions as $c):
                        $chk = ($def_cond === $c) ? 'checked' : '';
                    ?>
                    <input type="radio" name="condition_state" id="cond_<?= $c ?>"
                           class="pill-radio" data-type="condition"
                           value="<?= $c ?>" <?= $chk ?> required>
                    <label class="pill-label" for="cond_<?= $c ?>"><?= $c ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Current Status <span class="req">*</span></label>
                <div class="pill-group">
                    <?php
                    $statuses = ['Available','Allocated','Under Repair','Condemned','Lost'];
                    $def_stat = $edit_data['current_status'] ?? 'Available';
                    foreach ($statuses as $s):
                        $chk = ($def_stat === $s) ? 'checked' : '';
                    ?>
                    <input type="radio" name="current_status" id="stat_<?= str_replace(' ','_',$s) ?>"
                           class="pill-radio" data-type="status"
                           value="<?= $s ?>" <?= $chk ?> required>
                    <label class="pill-label" for="stat_<?= str_replace(' ','_',$s) ?>"><?= $s ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <hr class="sec">

        <div class="form-grid fg-2" style="margin-top:16px;">
            <div class="form-group full">
                <label>Notes / Description
                    <span class="hint">— accessories included, special instructions, etc.</span></label>
                <textarea name="notes" placeholder="Any additional details about this asset..."><?= $v('notes') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- -- Action Bar ------------------------------------------------------------ -->
<div class="card" style="margin-bottom:0;">
    <div class="action-bar">
        <div class="left">
            <a href="assets_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <?php if (!$edit_id): ?>
            <button type="submit" name="save_and_add" class="btn btn-outline">
                <i class="fas fa-plus"></i> Save &amp; Add Another
            </button>
            <?php endif; ?>
        </div>
        <div class="right">
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-<?= $edit_id ? 'save' : 'check-circle' ?>"></i>
                <?= $edit_id ? 'Update Asset' : 'Register Asset' ?>
            </button>
        </div>
    </div>
</div>

</form>

<script>
// -- Require category selection before submit ----------------------------------
document.getElementById('assetForm').addEventListener('submit', function(e) {
    const cat = document.querySelector('input[name="category_id"]:checked');
    if (!cat) {
        e.preventDefault();
        alert('Please select an asset category before saving.');
        document.querySelector('.cat-grid').scrollIntoView({behavior:'smooth', block:'center'});
        return;
    }
    const name = document.querySelector('input[name="asset_name"]').value.trim();
    if (!name) {
        e.preventDefault();
        alert('Asset name is required.');
        document.querySelector('input[name="asset_name"]').focus();
        return;
    }
    const code = document.querySelector('input[name="asset_code"]').value.trim();
    if (!code) {
        e.preventDefault();
        alert('Asset code is required.');
        document.querySelector('input[name="asset_code"]').focus();
        return;
    }
    // Determine which button was clicked
    if (e.submitter && e.submitter.name === 'save_and_add') {
        document.querySelector('input[name="save_and_add"]') ||
            (function() {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = 'save_and_add'; i.value = '1';
                document.getElementById('assetForm').appendChild(i);
            })();
    }
});

// -- Auto-uppercase asset code -------------------------------------------------
document.getElementById('asset_code').addEventListener('blur', function() {
    this.value = this.value.trim().toUpperCase();
});

// -- Warn if warranty date < purchase date ------------------------------------
function checkDates() {
    const pd = document.querySelector('input[name="purchase_date"]').value;
    const wd = document.querySelector('input[name="warranty_expiry"]').value;
    if (pd && wd && wd < pd) {
        alert('Warning: Warranty expiry date is before the purchase date.');
    }
}
document.querySelector('input[name="warranty_expiry"]').addEventListener('change', checkDates);
document.querySelector('input[name="purchase_date"]').addEventListener('change', checkDates);
</script>
</body>
</html>