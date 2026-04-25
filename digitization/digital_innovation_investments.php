<?php
// digital/digital_innovation_investments.php
session_start();

$base_path = dirname(__DIR__);
$config_path   = $base_path . '/includes/config.php';
$session_check = $base_path . '/includes/session_check.php';

if (!file_exists($config_path)) {
    die('Configuration file not found. Expected: ' . $config_path);
}
include($config_path);
include($session_check);

if (!isset($conn) || !$conn) {
    die('Database connection failed. Please check config.php.');
}
if (!mysqli_ping($conn)) {
    die('Database connection lost. Please check your database server.');
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$created_by = $_SESSION['full_name'] ?? '';
$uid        = (int)$_SESSION['user_id'];
$this_file  = basename(__FILE__);

// ────────────────────────────────────────────────────────────────────
//  HELPERS
// ────────────────────────────────────────────────────────────────────
$e  = fn($v) => mysqli_real_escape_string($conn, trim((string)($v ?? '')));
$i  = fn($v) => is_numeric($v) ? (int)$v   : 'NULL';
$f  = fn($v) => is_numeric($v) ? (float)$v : 'NULL';

// ────────────────────────────────────────────────────────────────────
//  AUTO-RECALCULATE current_value for all active records (monthly-safe)
//  Uses reducing-balance:  CV = PV × (1 − dep_rate)^(months/12)
// ────────────────────────────────────────────────────────────────────
mysqli_query($conn, "
    UPDATE digital_innovation_investments
    SET  current_value = GREATEST(0,
            purchase_value * POW(
                1 - (depreciation_percentage / 100),
                TIMESTAMPDIFF(MONTH, issue_date, NOW()) / 12
            )
         ),
         updated_at = NOW()
    WHERE invest_status = 'Active'
      AND (no_end_date = 1 OR end_date IS NULL OR end_date >= CURDATE())
");

// ────────────────────────────────────────────────────────────────────
//  AJAX — facility search
// ────────────────────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_facility') {
    $q    = $e($_GET['q'] ?? '');
    $rows = [];
    if (strlen($q) >= 2) {
        $res = mysqli_query($conn,
            "SELECT facility_id, facility_name, mflcode, county_name, subcounty_name,
                    owner, sdp, agency, emr, emrstatus, infrastructuretype,
                    latitude, longitude, level_of_care_name
             FROM facilities
             WHERE (facility_name LIKE '%$q%' OR mflcode LIKE '%$q%' OR county_name LIKE '%$q%')
             ORDER BY facility_name LIMIT 20");
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

// ────────────────────────────────────────────────────────────────────
//  AJAX — get asset details (depreciation_percentage)
// ────────────────────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_asset') {
    $dig_id = $i($_GET['dig_id'] ?? 0);
    $row    = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT dig_id, dit_asset_name, depreciation_percentage
         FROM digital_investments_assets WHERE dig_id = $dig_id LIMIT 1"));
    header('Content-Type: application/json');
    echo json_encode($row ?: []);
    exit();
}

// ────────────────────────────────────────────────────────────────────
//  AJAX — save / update investment record
// ────────────────────────────────────────────────────────────────────
if (isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');

    $invest_id           = $i($_POST['invest_id']             ?? 0);
    $facility_id         = $i($_POST['facility_id']           ?? 0);
    $facility_name       = $e($_POST['facility_name']         ?? '');
    $mflcode             = $e($_POST['mflcode']               ?? '');
    $county_name         = $e($_POST['county_name']           ?? '');
    $subcounty_name      = $e($_POST['subcounty_name']        ?? '');
    $dit_asset_name      = $e($_POST['dit_asset_name']        ?? '');
    $asset_name          = $e($_POST['asset_name']            ?? '');
    $dep_pct             = $f($_POST['depreciation_percentage'] ?? 0);
    $purchase_value      = $f($_POST['purchase_value']        ?? 0);
    $issue_date          = $e($_POST['issue_date']            ?? '');
    $no_end_date         = (int)!empty($_POST['no_end_date']);
    $end_date_raw        = $e($_POST['end_date']              ?? '');
    $end_date_val        = ($no_end_date || $end_date_raw === '') ? 'NULL' : "'$end_date_raw'";
    $dig_funder_name     = $e($_POST['dig_funder_name']       ?? '');
    $sdp_name            = $e($_POST['sdp_name']              ?? '');
    $emr_type_name       = $e($_POST['emr_type_name']         ?? '');
    $service_level       = $e($_POST['service_level']         ?? '');
    $lot_number          = $e($_POST['lot_number']            ?? '');

    if (!$facility_id || !$dit_asset_name || !$purchase_value || !$issue_date || !$service_level) {
        echo json_encode(['success' => false, 'error' => 'Please fill all required fields.']);
        exit();
    }

    // Calc initial current_value
    $months_elapsed = 0;
    if ($issue_date) {
        $diff = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT TIMESTAMPDIFF(MONTH, '$issue_date', NOW()) AS m"));
        $months_elapsed = (int)($diff['m'] ?? 0);
    }
    $current_value = 0;
    if ($dep_pct !== 'NULL' && $purchase_value !== 'NULL' && $months_elapsed >= 0) {
        $current_value = round($purchase_value * pow(1 - ($dep_pct / 100), $months_elapsed / 12), 2);
        if ($current_value < 0) $current_value = 0;
    }

    $invest_status = 'Active';
    if (!$no_end_date && $end_date_raw && strtotime($end_date_raw) < time()) {
        $invest_status = 'Expired';
    }
    $invest_status_s = $e($invest_status);

    if ($invest_id === 'NULL' || $invest_id == 0) {
        // INSERT
        $sql = "INSERT INTO digital_innovation_investments
                    (facility_id, facility_name, mflcode, county_name, subcounty_name,
                     dit_asset_name, asset_name, depreciation_percentage, purchase_value,
                     issue_date, end_date, no_end_date, current_value,
                     dig_funder_name, sdp_name, emr_type_name, service_level, lot_number,
                     invest_status, created_by, created_at, updated_at)
                VALUES
                    ($facility_id,'$facility_name','$mflcode','$county_name','$subcounty_name',
                     '$dit_asset_name','$asset_name',$dep_pct,$purchase_value,
                     '$issue_date',$end_date_val,$no_end_date,$current_value,
                     '$dig_funder_name','$sdp_name','$emr_type_name','$service_level','$lot_number',
                     '$invest_status_s','".mysqli_real_escape_string($conn,$created_by)."', NOW(), NOW())";
        if (mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode(['success' => true, 'invest_id' => $new_id, 'current_value' => $current_value, 'action' => 'insert']);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        // UPDATE
        $sql = "UPDATE digital_innovation_investments SET
                    facility_id=$facility_id, facility_name='$facility_name',
                    mflcode='$mflcode', county_name='$county_name', subcounty_name='$subcounty_name',
                    dit_asset_name='$dit_asset_name', asset_name='$asset_name',
                    depreciation_percentage=$dep_pct, purchase_value=$purchase_value,
                    issue_date='$issue_date', end_date=$end_date_val, no_end_date=$no_end_date,
                    current_value=$current_value, dig_funder_name='$dig_funder_name',
                    sdp_name='$sdp_name', emr_type_name='$emr_type_name',
                    service_level='$service_level', lot_number='$lot_number',
                    invest_status='$invest_status_s', updated_at=NOW()
                WHERE invest_id=$invest_id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'invest_id' => $invest_id, 'current_value' => $current_value, 'action' => 'update']);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    }
    exit();
}

// ────────────────────────────────────────────────────────────────────
//  AJAX — delete investment
// ────────────────────────────────────────────────────────────────────
if (isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $invest_id = $i($_POST['invest_id'] ?? 0);
    if ($invest_id !== 'NULL' && $invest_id > 0) {
        if (mysqli_query($conn, "DELETE FROM digital_innovation_investments WHERE invest_id=$invest_id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit();
}

// ────────────────────────────────────────────────────────────────────
//  AJAX — CSV import
// ────────────────────────────────────────────────────────────────────
if (isset($_POST['ajax_csv_import'])) {
    header('Content-Type: application/json');

    if (empty($_FILES['csv_file']['tmp_name'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
        exit();
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'error' => 'Cannot read uploaded file.']);
        exit();
    }

    // Expected CSV headers (case-insensitive):
    // facility_name, mflcode, dit_asset_name, purchase_value, issue_date, service_level — required
    // county_name, subcounty_name, end_date, no_end_date,
    // dig_funder_name, sdp_name, emr_type_name, lot_number — optional

    $headers = array_map('strtolower', array_map('trim', fgetcsv($handle)));
    $required = ['facility_name','dit_asset_name','purchase_value','issue_date','service_level'];
    $missing  = array_diff($required, $headers);
    if (!empty($missing)) {
        fclose($handle);
        echo json_encode(['success' => false, 'error' => 'Missing required columns: ' . implode(', ', $missing)]);
        exit();
    }

    $imported = 0; $skipped = 0; $errors = [];
    $cb = mysqli_real_escape_string($conn, $created_by);

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < count($headers)) { $skipped++; continue; }
        $data = array_combine($headers, $row);

        $fname         = $e($data['facility_name']   ?? '');
        $asset_name_csv = $e($data['dit_asset_name'] ?? '');
        $pv            = $f($data['purchase_value']  ?? 0);
        $idate         = $e($data['issue_date']      ?? '');
        $slevel        = $e($data['service_level']   ?? '');

        if (!$fname || !$asset_name_csv || $pv === 'NULL' || !$idate || !$slevel) {
            $skipped++; continue;
        }

        // Lookup facility by name or mflcode
        $mfl_csv  = $e($data['mflcode'] ?? '');
        $fac_row  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT facility_id, facility_name, mflcode, county_name, subcounty_name
             FROM facilities
             WHERE " . ($mfl_csv ? "mflcode='$mfl_csv'" : "facility_name='$fname'") . " LIMIT 1"));
        if (!$fac_row) { $skipped++; $errors[] = "Row ".($imported+$skipped).": facility '$fname' not found"; continue; }

        $fid     = (int)$fac_row['facility_id'];
        $fname   = $e($fac_row['facility_name']);
        $mfl     = $e($fac_row['mflcode']        ?? ($data['mflcode']        ?? ''));
        $county  = $e($fac_row['county_name']    ?? ($data['county_name']    ?? ''));
        $subcnty = $e($fac_row['subcounty_name'] ?? ($data['subcounty_name'] ?? ''));

        // Fetch asset details by name
        $asset_row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT dit_asset_name, depreciation_percentage FROM digital_investments_assets WHERE dit_asset_name='$asset_name_csv' LIMIT 1"));
        if (!$asset_row) { $skipped++; $errors[] = "Row ".($imported+$skipped).": asset '$asset_name_csv' not found"; continue; }

        $asset_name_s = $e($asset_row['dit_asset_name']);
        $dep_pct      = (float)$asset_row['depreciation_percentage'];

        $no_end   = (int)(!empty($data['no_end_date']) && strtolower($data['no_end_date']) !== '0');
        $edate_r  = $e($data['end_date'] ?? '');
        $edate_v  = ($no_end || $edate_r === '') ? 'NULL' : "'$edate_r'";

        $funder_name   = $e($data['dig_funder_name'] ?? '');
        $sdp_name_csv  = $e($data['sdp_name']        ?? '');
        $emr_type_csv  = $e($data['emr_type_name']   ?? '');
        $lot           = $e($data['lot_number']       ?? '');

        // Current value calc
        $diff_row       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT TIMESTAMPDIFF(MONTH,'$idate',NOW()) AS m"));
        $months_elapsed = (int)($diff_row['m'] ?? 0);
        $cv             = round($pv * pow(1 - ($dep_pct / 100), $months_elapsed / 12), 2);
        if ($cv < 0) $cv = 0;

        $invest_status = 'Active';
        if (!$no_end && $edate_r && strtotime($edate_r) < time()) $invest_status = 'Expired';
        $is_s = $e($invest_status);

        $ins = "INSERT INTO digital_innovation_investments
                    (facility_id,facility_name,mflcode,county_name,subcounty_name,
                     dit_asset_name,asset_name,depreciation_percentage,purchase_value,
                     issue_date,end_date,no_end_date,current_value,
                     dig_funder_name,sdp_name,emr_type_name,service_level,lot_number,
                     invest_status,created_by,created_at,updated_at)
                VALUES ($fid,'$fname','$mfl','$county','$subcnty',
                        '$asset_name_s','$asset_name_s',$dep_pct,$pv,
                        '$idate',$edate_v,$no_end,$cv,
                        '$funder_name','$sdp_name_csv','$emr_type_csv','$slevel','$lot',
                        '$is_s','$cb',NOW(),NOW())";
        if (mysqli_query($conn, $ins)) { $imported++; } else {
            $errors[] = 'Row '.($imported+$skipped+1).': '.mysqli_error($conn); $skipped++;
        }
    }
    fclose($handle);
    echo json_encode(['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    exit();
}

// ────────────────────────────────────────────────────────────────────
//  LOAD DROPDOWNS
// ────────────────────────────────────────────────────────────────────
$assets_res   = mysqli_query($conn, "SELECT dig_id, dit_asset_name, depreciation_percentage FROM digital_investments_assets ORDER BY dit_asset_name");
$funders_res  = mysqli_query($conn, "SELECT dig_funder_id, dig_funder_name FROM digital_funders ORDER BY dig_funder_name");
$sdps_res     = mysqli_query($conn, "SELECT sdp_id, sdp_name FROM service_delivery_points ORDER BY sdp_name");
$emr_res      = mysqli_query($conn, "SELECT emr_type_id, emr_type_name FROM emr_types ORDER BY emr_type_name");

$assets  = []; while ($r = mysqli_fetch_assoc($assets_res))  $assets[]  = $r;
$funders = []; while ($r = mysqli_fetch_assoc($funders_res)) $funders[] = $r;
$sdps    = []; while ($r = mysqli_fetch_assoc($sdps_res))    $sdps[]    = $r;
$emr_types_arr = []; while ($r = mysqli_fetch_assoc($emr_res)) $emr_types_arr[] = $r;

// ────────────────────────────────────────────────────────────────────
//  LOAD EXISTING RECORDS (for the table/list view)
// ────────────────────────────────────────────────────────────────────
$list_res = mysqli_query($conn, "
    SELECT *
    FROM   digital_innovation_investments
    ORDER BY created_at DESC
    LIMIT 200
");
$investments = [];
while ($r = mysqli_fetch_assoc($list_res)) $investments[] = $r;

// Edit pre-fill
$edit_row = null;
if (isset($_GET['edit'])) {
    $eid     = $i($_GET['edit']);
    $edit_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM digital_innovation_investments WHERE invest_id=$eid LIMIT 1"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Digital Innovations Investments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
/* ── Root tokens ─────────────────────────────────────────────────────── */
:root{
  --primary:#2D008A;
  --primary2:#4B00C8;
  --lilac:#AC80EE;
  --pink:#FFDCF9;
  --green:#04B04B;
  --amber:#FFC12E;
  --red:#E41E39;
  --bg:#f4f2fb;
  --card:#fff;
  --border:#e2d9f3;
  --muted:#6B7280;
  --shadow:0 2px 18px rgba(45,0,138,.10);
  --radius:14px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:var(--bg);color:#1a1e2e;line-height:1.6;}
.wrap{max-width:85%;margin:0 auto;padding:20px 16px;}

/* ── Header ─────────────────────────────────────────────────────────── */
.page-header{
  background:linear-gradient(135deg,var(--primary),var(--primary2));
  color:#fff;padding:20px 28px;border-radius:var(--radius);
  margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;
  box-shadow:0 6px 28px rgba(45,0,138,.28);}
.page-header h1{font-size:1.35rem;font-weight:700;display:flex;align-items:center;gap:10px;}
.hdr-links a{
  color:#fff;text-decoration:none;background:rgba(255,255,255,.15);
  padding:7px 15px;border-radius:8px;font-size:13px;margin-left:8px;transition:.2s;}
.hdr-links a:hover{background:rgba(255,255,255,.3);}

/* ── Tabs ────────────────────────────────────────────────────────────── */
.tabs{display:flex;gap:0;margin-bottom:22px;background:var(--card);
  border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.tab-btn{flex:1;padding:13px 10px;border:none;background:transparent;cursor:pointer;
  font-size:13.5px;font-weight:600;color:var(--muted);transition:.2s;
  border-bottom:3px solid transparent;display:flex;align-items:center;justify-content:center;gap:8px;}
.tab-btn:hover{background:#f4f2fb;color:var(--primary);}
.tab-btn.active{color:var(--primary);border-bottom-color:var(--primary);background:var(--pink);}

/* ── Card ────────────────────────────────────────────────────────────── */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);
  overflow:hidden;margin-bottom:22px;}
.card-head{
  background:linear-gradient(90deg,var(--primary),var(--primary2));
  color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center;}
.card-head h3{font-size:14px;font-weight:700;display:flex;align-items:center;gap:9px;}
.card-body{padding:24px;}

/* ── Form grid ───────────────────────────────────────────────────────── */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;}
.form-grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
.form-group{margin-bottom:0;}
.form-group.full{grid-column:1/-1;}
.form-group label{display:block;margin-bottom:5px;font-weight:600;color:#374151;font-size:13px;}
.form-group label span.req{color:var(--red);margin-left:2px;}

.form-control,
.form-select{
  width:100%;padding:10px 14px;border:1.5px solid var(--border);
  border-radius:9px;font-size:13.5px;font-family:inherit;
  background:#fff;transition:.2s;color:#1a1e2e;}
.form-control:focus,.form-select:focus{
  outline:none;border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(45,0,138,.10);}
.form-control[readonly]{background:#f8f7fe;color:#555;}

/* current-value display */
.current-value-display{
  background:linear-gradient(135deg,var(--primary),var(--primary2));
  color:#fff;border-radius:9px;padding:10px 16px;
  font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px;
  letter-spacing:.3px;}
.cv-label{font-size:11px;font-weight:400;opacity:.8;display:block;margin-bottom:2px;}

/* ── Dep badge ───────────────────────────────────────────────────────── */
.dep-badge{display:inline-flex;align-items:center;gap:6px;
  background:var(--pink);color:var(--primary);
  border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;
  margin-top:6px;border:1px solid var(--lilac);}

/* ── Checkbox row ────────────────────────────────────────────────────── */
.cb-row{display:flex;align-items:center;gap:9px;margin-top:8px;}
.cb-row input[type=checkbox]{width:17px;height:17px;accent-color:var(--primary);}
.cb-row label{font-weight:500;font-size:13px;color:#374151;cursor:pointer;}

/* ── Service level radio group ───────────────────────────────────────── */
.sl-group{display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;}
.sl-opt{display:flex;align-items:center;gap:8px;padding:9px 18px;
  border:2px solid var(--border);border-radius:9px;cursor:pointer;
  font-size:13px;font-weight:600;transition:.2s;background:#fff;}
.sl-opt:hover{border-color:var(--lilac);background:var(--pink);}
.sl-opt input[type=radio]{accent-color:var(--primary);width:16px;height:16px;}
.sl-opt.selected{border-color:var(--primary);background:var(--pink);color:var(--primary);}

/* ── Buttons ─────────────────────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;
  border:none;border-radius:9px;font-size:13px;font-weight:700;
  cursor:pointer;transition:.2s;text-decoration:none;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary2);}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{filter:brightness(.9);}
.btn-amber{background:var(--amber);color:#1a1e2e;}
.btn-amber:hover{filter:brightness(.9);}
.btn-red{background:var(--red);color:#fff;}
.btn-red:hover{filter:brightness(.9);}
.btn-outline{background:transparent;border:2px solid var(--primary);color:var(--primary);}
.btn-outline:hover{background:var(--pink);}
.btn-group{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}

/* ── Facility search ─────────────────────────────────────────────────── */
.search-wrap{position:relative;}
.search-wrap input{
  width:100%;padding:11px 44px 11px 14px;border:1.5px solid var(--border);
  border-radius:9px;font-size:13.5px;background:#fff;transition:.2s;font-family:inherit;}
.search-wrap input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(45,0,138,.10);}
.s-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#aaa;pointer-events:none;}
.s-spinner{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--primary);display:none;}
.results-dropdown{
  position:absolute;z-index:999;width:100%;background:#fff;
  border:1.5px solid var(--border);border-radius:10px;margin-top:4px;
  box-shadow:0 8px 28px rgba(45,0,138,.15);max-height:260px;overflow-y:auto;display:none;}
.result-item{padding:10px 14px;cursor:pointer;border-bottom:1px solid #f0f0f0;transition:.15s;}
.result-item:last-child{border-bottom:none;}
.result-item:hover{background:var(--pink);}
.ri-name{font-weight:700;color:var(--primary);font-size:13px;}
.ri-meta{font-size:11px;color:#777;margin-top:2px;}
.ri-badge{font-size:10px;background:var(--pink);color:var(--primary);
  border-radius:4px;padding:1px 6px;margin-left:4px;font-weight:600;}
.no-results{padding:14px;color:#999;font-size:13px;text-align:center;}

/* ── Facility card ───────────────────────────────────────────────────── */
.facility-card{
  border:2px solid var(--primary);border-radius:10px;padding:14px 18px;
  background:linear-gradient(135deg,var(--pink),#fff);margin-top:8px;display:none;}
.fac-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px;margin-top:8px;}
.fg label{font-size:9.5px;text-transform:uppercase;letter-spacing:.5px;
  color:#999;font-weight:700;display:block;margin-bottom:1px;}
.fg span{font-size:12.5px;color:#222;font-weight:500;}

/* ── Table ───────────────────────────────────────────────────────────── */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:12.5px;}
thead tr{background:linear-gradient(90deg,var(--primary),var(--primary2));color:#fff;}
thead th{padding:10px 12px;text-align:left;font-weight:700;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f0eeff;transition:.15s;}
tbody tr:hover{background:var(--pink);}
tbody td{padding:9px 12px;vertical-align:middle;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;
  border-radius:20px;font-size:11px;font-weight:700;}
.badge-active{background:#d4f8e5;color:var(--green);}
.badge-expired{background:#fde8eb;color:var(--red);}
.badge-fw{background:var(--pink);color:var(--primary);}
.badge-sdp{background:#fff3cc;color:#7a5800;}

/* ── Toast ───────────────────────────────────────────────────────────── */
.toast{position:fixed;bottom:24px;right:24px;background:#1a1e2e;color:#fff;
  padding:12px 22px;border-radius:10px;font-size:13.5px;font-weight:600;
  display:flex;align-items:center;gap:9px;z-index:9999;
  transform:translateY(80px);opacity:0;transition:.35s;pointer-events:none;}
.toast.show{transform:translateY(0);opacity:1;}
.toast.success .toast-icon{color:var(--green);}
.toast.error   .toast-icon{color:var(--red);}

/* ── CSV import area ─────────────────────────────────────────────────── */
.csv-drop{
  border:2px dashed var(--lilac);border-radius:12px;padding:32px 20px;
  text-align:center;cursor:pointer;transition:.2s;background:#faf8ff;}
.csv-drop:hover,.csv-drop.drag-over{background:var(--pink);border-color:var(--primary);}
.csv-drop i{font-size:2.5rem;color:var(--lilac);margin-bottom:10px;}
.csv-drop p{font-size:13.5px;color:var(--muted);}
.csv-drop strong{color:var(--primary);}

/* ── Alert ───────────────────────────────────────────────────────────── */
.alert{padding:12px 18px;border-radius:9px;margin-bottom:18px;
  font-size:13.5px;display:flex;align-items:flex-start;gap:10px;}
.alert-success{background:#d4f8e5;color:#0a5c2e;border:1px solid #a8e6c1;}
.alert-error{background:#fde8eb;color:#7a0011;border:1px solid #f5b8c0;}
.alert-info{background:var(--pink);color:var(--primary);border:1px solid var(--lilac);}

/* ── Search / filter bar ─────────────────────────────────────────────── */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;}
.filter-bar input,.filter-bar select{
  padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;
  font-size:13px;font-family:inherit;flex:1;min-width:160px;max-width:240px;}
.filter-bar input:focus,.filter-bar select:focus{
  outline:none;border-color:var(--primary);}

/* ── Section divider label ───────────────────────────────────────────── */
.divider-label{
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;
  color:var(--primary);display:flex;align-items:center;gap:8px;margin:20px 0 12px;}
.divider-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media(max-width:680px){
  .page-header{flex-direction:column;gap:12px;align-items:flex-start;}
  .tabs{flex-direction:column;}
  .tab-btn{border-bottom:none;border-right:3px solid transparent;}
  .tab-btn.active{border-right-color:var(--primary);}
}

/* ── Modal ───────────────────────────────────────────────────────────── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);
  z-index:8000;display:none;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal-box{background:#fff;border-radius:var(--radius);width:min(540px,95vw);
  overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);}
.modal-head{background:linear-gradient(90deg,var(--primary),var(--primary2));
  color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center;}
.modal-head h4{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;}
.modal-body{padding:22px;}
.modal-foot{padding:14px 22px;border-top:1px solid var(--border);
  display:flex;gap:10px;justify-content:flex-end;}
</style>
</head>
<body>
<div class="wrap">

<!-- ── PAGE HEADER ──────────────────────────────────────────────────── -->
<div class="page-header">
    <h1><i class="fas fa-laptop-medical"></i> Digital Innovations Investments</h1>
    <div class="hdr-links">
        <a href="javascript:void(0)" onclick="showTab('form')"><i class="fas fa-plus"></i> New Record</a>
        <a href="javascript:void(0)" onclick="showTab('list')"><i class="fas fa-list"></i> All Records</a>
        <a href="javascript:void(0)" onclick="showTab('csv')"><i class="fas fa-file-csv"></i> Import CSV</a>
        <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    </div>
</div>

<!-- ── TABS ─────────────────────────────────────────────────────────── -->
<div class="tabs" id="mainTabs">
    <button class="tab-btn active" id="tab_form" onclick="showTab('form')">
        <i class="fas fa-plus-circle"></i> Add / Edit Record
    </button>
    <button class="tab-btn" id="tab_list" onclick="showTab('list')">
        <i class="fas fa-table"></i> All Investments
    </button>
    <button class="tab-btn" id="tab_csv" onclick="showTab('csv')">
        <i class="fas fa-file-csv"></i> Import CSV
    </button>
</div>

<div id="globalAlert"></div>

<!-- ══════════════════════════════════════════════════════════════════
     TAB 1 — FORM
═══════════════════════════════════════════════════════════════════ -->
<div id="pane_form">

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-hospital-alt"></i> Facility Selection</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label>Facility <span class="req">*</span></label>
            <small style="color:var(--muted);display:block;margin-bottom:6px">
                Type the facility name or MFL code — selecting fills all location fields.
                <strong style="color:var(--red)">MFL Code is most precise.</strong>
            </small>
            <div class="search-wrap" id="facSearchWrap">
                <input type="text" id="facilitySearch" placeholder="Type facility name or MFL code…" autocomplete="off">
                <i class="fas fa-hospital s-icon" id="facIcon"></i>
                <i class="fas fa-spinner fa-spin s-spinner" id="facSpinner"></i>
                <div class="results-dropdown" id="facResults"></div>
            </div>
        </div>

        <div class="facility-card" id="facilityCard">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="color:var(--primary);font-size:14px" id="fc_name"></strong>
                <button type="button" onclick="clearFacility()"
                    style="background:none;border:none;color:var(--red);cursor:pointer;font-size:13px">
                    <i class="fas fa-times-circle"></i> Change
                </button>
            </div>
            <div class="fac-grid">
                <div class="fg"><label>MFL Code</label><span id="fc_mfl">—</span></div>
                <div class="fg"><label>County</label><span id="fc_county">—</span></div>
                <div class="fg"><label>Sub-County</label><span id="fc_subcounty">—</span></div>
                <div class="fg"><label>Level of Care</label><span id="fc_level">—</span></div>
                <div class="fg"><label>Owner</label><span id="fc_owner">—</span></div>
                <div class="fg"><label>SDP</label><span id="fc_sdp_fac">—</span></div>
                <div class="fg"><label>Agency</label><span id="fc_agency">—</span></div>
                <div class="fg"><label>EMR</label><span id="fc_emr">—</span></div>
            </div>
        </div>

        <!-- Hidden facility fields -->
        <input type="hidden" id="h_facility_id"   value="<?= $edit_row ? htmlspecialchars($edit_row['facility_id']) : '' ?>">
        <input type="hidden" id="h_facility_name" value="<?= $edit_row ? htmlspecialchars($edit_row['facility_name']) : '' ?>">
        <input type="hidden" id="h_mflcode"       value="<?= $edit_row ? htmlspecialchars($edit_row['mflcode']) : '' ?>">
        <input type="hidden" id="h_county"        value="<?= $edit_row ? htmlspecialchars($edit_row['county_name']) : '' ?>">
        <input type="hidden" id="h_subcounty"     value="<?= $edit_row ? htmlspecialchars($edit_row['subcounty_name']) : '' ?>">
        <input type="hidden" id="h_invest_id"     value="<?= $edit_row ? htmlspecialchars($edit_row['invest_id']) : '' ?>">
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-laptop"></i> Digital Asset Details</h3>
    </div>
    <div class="card-body">

        <div class="divider-label"><i class="fas fa-microchip"></i> Asset Information</div>
        <div class="form-grid">

            <!-- Digital Asset -->
            <div class="form-group">
                <label>Digital Asset <span class="req">*</span></label>
                <select id="dig_id" class="form-select" onchange="onAssetChange(this.value)">
                    <option value="">-- Select Asset --</option>
                    <?php foreach ($assets as $a): ?>
                    <option value="<?= $a['dig_id'] ?>"
                            data-dep="<?= $a['depreciation_percentage'] ?>"
                            data-name="<?= htmlspecialchars($a['dit_asset_name']) ?>"
                        <?= ($edit_row && $edit_row['dit_asset_name'] == $a['dit_asset_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['dit_asset_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="depBadge" style="display:none" class="dep-badge">
                    <i class="fas fa-chart-line"></i>
                    Depreciation: <strong id="depPct">0</strong>% per annum
                </div>
            </div>

            <!-- Funder -->
            <div class="form-group">
                <label>Funder <span class="req">*</span></label>
                <select id="dig_funder_id" class="form-select">
                    <option value="">-- Select Funder --</option>
                    <?php foreach ($funders as $fn): ?>
                    <option value="<?= htmlspecialchars($fn['dig_funder_name']) ?>"
                        <?= ($edit_row && $edit_row['dig_funder_name'] == $fn['dig_funder_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fn['dig_funder_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- EMR Type -->
            <div class="form-group">
                <label>Type of EMR <span class="req">*</span></label>
                <select id="emr_type_id" class="form-select">
                    <option value="">-- Select EMR Type --</option>
                    <?php foreach ($emr_types_arr as $em): ?>
                    <option value="<?= htmlspecialchars($em['emr_type_name']) ?>"
                        <?= ($edit_row && $edit_row['emr_type_name'] == $em['emr_type_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($em['emr_type_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Service Level -->
            <div class="form-group">
                <label>Service Level <span class="req">*</span></label>
                <div class="sl-group" id="serviceLevelGroup">
                    <label class="sl-opt <?= (!$edit_row || $edit_row['service_level']==='Facility-wide') ? 'selected' : '' ?>">
                        <input type="radio" name="service_level" value="Facility-wide"
                               <?= (!$edit_row || $edit_row['service_level']==='Facility-wide') ? 'checked' : '' ?>
                               onchange="onServiceLevelChange(this.value)">
                        <i class="fas fa-hospital"></i> Facility-wide
                    </label>
                    <label class="sl-opt <?= ($edit_row && $edit_row['service_level']==='Service Delivery Point') ? 'selected' : '' ?>">
                        <input type="radio" name="service_level" value="Service Delivery Point"
                               <?= ($edit_row && $edit_row['service_level']==='Service Delivery Point') ? 'checked' : '' ?>
                               onchange="onServiceLevelChange(this.value)">
                        <i class="fas fa-map-pin"></i> Service Delivery Point
                    </label>
                </div>
            </div>

            <!-- SDP (conditional on service level) -->
            <div class="form-group" id="sdpGroup"
                 style="display:<?= ($edit_row && $edit_row['service_level']==='Service Delivery Point') ? 'block' : 'none' ?>">
                <label>Service Delivery Point <span class="req">*</span></label>
                <select id="sdp_id" class="form-select">
                    <option value="">-- Select SDP --</option>
                    <?php foreach ($sdps as $sp): ?>
                    <option value="<?= htmlspecialchars($sp['sdp_name']) ?>"
                        <?= ($edit_row && $edit_row['sdp_name'] == $sp['sdp_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sp['sdp_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Lot Number -->
            <div class="form-group">
                <label>Lot Number <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                <input type="text" id="lot_number" class="form-control"
                       placeholder="e.g. LOT-2024-001"
                       value="<?= $edit_row ? htmlspecialchars($edit_row['lot_number'] ?? '') : '' ?>">
            </div>

        </div>

        <div class="divider-label"><i class="fas fa-calendar-alt"></i> Dates &amp; Valuation</div>
        <div class="form-grid">

            <!-- Purchase / Initial Value -->
            <div class="form-group">
                <label>Purchase Value (KES) <span class="req">*</span></label>
                <input type="number" id="purchase_value" class="form-control" min="0" step="0.01"
                       placeholder="0.00" oninput="calcCurrentValue()"
                       value="<?= $edit_row ? htmlspecialchars($edit_row['purchase_value'] ?? '') : '' ?>">
            </div>

            <!-- Issue Date -->
            <div class="form-group">
                <label>Issue Date <span class="req">*</span></label>
                <input type="date" id="issue_date" class="form-control"
                       oninput="calcCurrentValue()"
                       value="<?= $edit_row ? htmlspecialchars($edit_row['issue_date'] ?? '') : '' ?>">
            </div>

            <!-- End Date -->
            <div class="form-group">
                <label>End Date</label>
                <input type="date" id="end_date" class="form-control"
                       value="<?= $edit_row ? htmlspecialchars($edit_row['end_date'] ?? '') : '' ?>"
                       <?= ($edit_row && $edit_row['no_end_date']) ? 'disabled' : '' ?>>
                <div class="cb-row">
                    <input type="checkbox" id="no_end_date"
                           <?= ($edit_row && $edit_row['no_end_date']) ? 'checked' : '' ?>
                           onchange="toggleEndDate(this)">
                    <label for="no_end_date">This investment does not have an end date</label>
                </div>
            </div>

            <!-- Current Value (calculated, read-only) -->
            <div class="form-group">
                <label>Current Value (Auto-calculated)</label>
                <div class="current-value-display" id="cvDisplay">
                    <div>
                        <span class="cv-label">Estimated Current Value</span>
                        <span id="cvAmount">KES 0.00</span>
                    </div>
                    <i class="fas fa-calculator" style="margin-left:auto;opacity:.6;font-size:1.3rem"></i>
                </div>
                <input type="hidden" id="current_value_hidden" value="0">
                <small style="color:var(--muted);font-size:11px;margin-top:4px;display:block">
                    <i class="fas fa-info-circle"></i>
                    Reducing balance formula: PV × (1 − dep%)^(months/12). Updated automatically every month.
                </small>
            </div>

        </div>

        <div class="btn-group">
            <button class="btn btn-primary" onclick="saveRecord()">
                <i class="fas fa-save"></i> Save Investment
            </button>
            <button class="btn btn-outline" onclick="resetForm()">
                <i class="fas fa-undo"></i> Reset Form
            </button>
        </div>
    </div>
</div>

</div><!-- /pane_form -->

<!-- ══════════════════════════════════════════════════════════════════
     TAB 2 — ALL RECORDS
═══════════════════════════════════════════════════════════════════ -->
<div id="pane_list" style="display:none">

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-table"></i> All Digital Innovation Investments</h3>
        <button class="btn btn-green" style="padding:7px 14px;font-size:12px" onclick="showTab('form')">
            <i class="fas fa-plus"></i> Add New
        </button>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍  Search facility, asset…"
                   oninput="filterTable()">
            <select id="filterStatus" onchange="filterTable()">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Expired">Expired</option>
            </select>
            <select id="filterLevel" onchange="filterTable()">
                <option value="">All Service Levels</option>
                <option value="Facility-wide">Facility-wide</option>
                <option value="Service Delivery Point">Service Delivery Point</option>
            </select>
        </div>

        <div class="tbl-wrap">
        <table id="investTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Facility</th>
                    <th>MFL</th>
                    <th>Asset</th>
                    <th>Funder</th>
                    <th>Purchase Value</th>
                    <th>Current Value</th>
                    <th>Dep %</th>
                    <th>Issue Date</th>
                    <th>End Date</th>
                    <th>EMR Type</th>
                    <th>Service Level</th>
                    <th>SDP</th>
                    <th>Lot #</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="investTbody">
            <?php foreach ($investments as $idx => $inv): ?>
            <tr data-status="<?= htmlspecialchars($inv['invest_status']) ?>"
                data-level="<?= htmlspecialchars($inv['service_level']) ?>">
                <td><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($inv['facility_name']) ?></strong>
                    <br><small style="color:var(--muted)"><?= htmlspecialchars($inv['county_name'] ?? '') ?></small></td>
                <td><?= htmlspecialchars($inv['mflcode'] ?? '—') ?></td>
                <td><?= htmlspecialchars($inv['asset_name']) ?></td>
                <td><?= htmlspecialchars($inv['dig_funder_name'] ?? '—') ?></td>
                <td>KES <?= number_format((float)$inv['purchase_value'], 2) ?></td>
                <td><strong style="color:var(--primary)">KES <?= number_format((float)$inv['current_value'], 2) ?></strong></td>
                <td><?= htmlspecialchars($inv['depreciation_percentage']) ?>%</td>
                <td><?= htmlspecialchars($inv['issue_date'] ?? '—') ?></td>
                <td><?= $inv['no_end_date'] ? '<em style="color:var(--green)">No End</em>' : htmlspecialchars($inv['end_date'] ?? '—') ?></td>
                <td><?= htmlspecialchars($inv['emr_type_name'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $inv['service_level'] === 'Facility-wide' ? 'badge-fw' : 'badge-sdp' ?>">
                        <?= htmlspecialchars($inv['service_level']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($inv['sdp_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($inv['lot_number'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $inv['invest_status'] === 'Active' ? 'badge-active' : 'badge-expired' ?>">
                        <i class="fas <?= $inv['invest_status'] === 'Active' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= htmlspecialchars($inv['invest_status']) ?>
                    </span>
                </td>
                <td style="white-space:nowrap">
                    <a href="?edit=<?= $inv['invest_id'] ?>" class="btn btn-amber"
                       style="padding:5px 10px;font-size:11px">
                       <i class="fas fa-edit"></i> Edit
                    </a>
                    <button class="btn btn-red" style="padding:5px 10px;font-size:11px"
                            onclick="deleteRecord(<?= $inv['invest_id'] ?>, '<?= htmlspecialchars($inv['asset_name'], ENT_QUOTES) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($investments)): ?>
            <tr><td colspan="16" style="text-align:center;color:var(--muted);padding:30px">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px"></i>
                No records found. Click <strong>Add New</strong> to start.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

</div><!-- /pane_list -->

<!-- ══════════════════════════════════════════════════════════════════
     TAB 3 — CSV IMPORT
═══════════════════════════════════════════════════════════════════ -->
<div id="pane_csv" style="display:none">

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-file-csv"></i> Import Records via CSV</h3>
    </div>
    <div class="card-body">

        <div class="alert alert-info">
            <i class="fas fa-info-circle" style="font-size:1.2rem;flex-shrink:0"></i>
            <div>
                <strong>CSV Format Requirements</strong><br>
                Your CSV file must include these column headers (first row):<br>
                <code style="background:#e8deff;padding:2px 6px;border-radius:4px;font-size:12px">
                facility_name, dit_asset_name, purchase_value, issue_date, service_level
                </code>
                &nbsp;— required<br>
                <code style="background:#f0f8ff;padding:2px 6px;border-radius:4px;font-size:12px">
                mflcode, county_name, subcounty_name, end_date, no_end_date, dig_funder_name, sdp_name, emr_type_name, lot_number
                </code>
                &nbsp;— optional<br>
                <strong>Dates:</strong> YYYY-MM-DD &nbsp;|&nbsp;
                <strong>no_end_date:</strong> 1 = no end, 0 = has end &nbsp;|&nbsp;
                <strong>service_level:</strong> <em>Facility-wide</em> or <em>Service Delivery Point</em>
            </div>
        </div>

        <div class="csv-drop" id="csvDrop" onclick="document.getElementById('csvFile').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <p><strong>Click to browse</strong> or drag &amp; drop your CSV file here</p>
            <p id="csvFileName" style="margin-top:8px;color:var(--primary);font-weight:600"></p>
        </div>
        <input type="file" id="csvFile" accept=".csv,text/csv" style="display:none" onchange="onCsvFileChange(this)">

        <div class="btn-group">
            <button class="btn btn-primary" id="btnImport" disabled onclick="importCsv()">
                <i class="fas fa-file-import"></i> Import Records
            </button>
            <a href="#" id="csvTemplateLink" class="btn btn-outline" onclick="downloadTemplate(event)">
                <i class="fas fa-download"></i> Download Template
            </a>
        </div>

        <div id="importResult" style="margin-top:18px"></div>
    </div>
</div>

</div><!-- /pane_csv -->

</div><!-- /wrap -->

<!-- ── DELETE CONFIRM MODAL ─────────────────────────────────────────── -->
<div class="modal-overlay" id="delModal">
    <div class="modal-box">
        <div class="modal-head">
            <h4><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h4>
            <button onclick="closeDelModal()"
                style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.2rem">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the investment record for
                <strong id="delAssetName"></strong>?<br>
                This action cannot be undone.</p>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeDelModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn btn-red" id="btnConfirmDel" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- ── TOAST ─────────────────────────────────────────────────────────── -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle toast-icon"></i>
    <span id="toastMsg">Saved successfully</span>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════════════ -->
<script>
// ── State ─────────────────────────────────────────────────────────────
const THIS_FILE    = '<?= $this_file ?>';
let   facilityData = {};
let   currentDepRate  = 0;
let   deleteTargetId  = 0;

// Pre-fill edit data if editing
<?php if ($edit_row): ?>
window.addEventListener('DOMContentLoaded', () => {
    // Pre-fill facility card
    document.getElementById('facilitySearch').value = <?= json_encode($edit_row['facility_name']) ?>;
    document.getElementById('h_facility_id').value  = <?= json_encode($edit_row['facility_id'])   ?>;
    document.getElementById('h_facility_name').value = <?= json_encode($edit_row['facility_name']) ?>;
    document.getElementById('h_mflcode').value       = <?= json_encode($edit_row['mflcode'] ?? '') ?>;
    document.getElementById('h_county').value        = <?= json_encode($edit_row['county_name'] ?? '') ?>;
    document.getElementById('h_subcounty').value     = <?= json_encode($edit_row['subcounty_name'] ?? '') ?>;

    document.getElementById('fc_name').textContent     = <?= json_encode($edit_row['facility_name']) ?>;
    document.getElementById('fc_mfl').textContent      = <?= json_encode($edit_row['mflcode'] ?? '—') ?>;
    document.getElementById('fc_county').textContent   = <?= json_encode($edit_row['county_name'] ?? '—') ?>;
    document.getElementById('fc_subcounty').textContent = <?= json_encode($edit_row['subcounty_name'] ?? '—') ?>;
    document.getElementById('facilityCard').style.display = 'block';

    // Depreciation badge
    const sel = document.getElementById('dig_id');
    if (sel.value) {
        const opt = sel.options[sel.selectedIndex];
        currentDepRate = parseFloat(opt.dataset.dep) || 0;
        document.getElementById('depPct').textContent = currentDepRate;
        document.getElementById('depBadge').style.display = 'inline-flex';
    }
    calcCurrentValue();
});
<?php endif; ?>

// ── Toast ──────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = 'toast ' + type + ' show';
    const icon = t.querySelector('.toast-icon');
    icon.className = 'fas ' + (type==='success' ? 'fa-check-circle' : 'fa-exclamation-triangle') + ' toast-icon';
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Global alert ────────────────────────────────────────────────────────
function showAlert(msg, type='info') {
    const el = document.getElementById('globalAlert');
    el.innerHTML = `<div class="alert alert-${type}">
        <i class="fas fa-${type==='success'?'check-circle':type==='error'?'times-circle':'info-circle'}"></i>
        <span>${msg}</span></div>`;
    setTimeout(() => el.innerHTML = '', 5000);
}

// ── Tab switching ───────────────────────────────────────────────────────
function showTab(name) {
    ['form','list','csv'].forEach(t => {
        document.getElementById('pane_'+t).style.display = t===name ? 'block' : 'none';
        document.getElementById('tab_'+t).classList.toggle('active', t===name);
    });
}

// ── Facility search ─────────────────────────────────────────────────────
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

const facInput   = document.getElementById('facilitySearch');
const facResults = document.getElementById('facResults');
const facSpinner = document.getElementById('facSpinner');
const facIcon    = document.getElementById('facIcon');

if (facInput) {
    facInput.addEventListener('input', debounce(async function() {
        const q = facInput.value.trim();
        if (q.length < 2) { facResults.style.display='none'; return; }
        facSpinner.style.display='block'; facIcon.style.display='none';
        try {
            const rows = await fetch(`${THIS_FILE}?ajax=search_facility&q=${encodeURIComponent(q)}`).then(r=>r.json());
            facSpinner.style.display='none'; facIcon.style.display='block';
            if (!rows.length) {
                facResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i> No facilities found</div>';
            } else {
                facResults.innerHTML = rows.map(r =>
                    `<div class="result-item" onclick='pickFacility(${JSON.stringify(r).replace(/'/g,"&#39;")})'>
                        <div class="ri-name">${r.facility_name}
                            <span class="ri-badge">${r.mflcode||''}</span></div>
                        <div class="ri-meta">
                            <i class="fas fa-map-marker-alt" style="color:var(--primary)"></i>
                            ${r.county_name||''} | ${r.subcounty_name||''} | ${r.level_of_care_name||''}
                        </div>
                    </div>`
                ).join('');
            }
            facResults.style.display = 'block';
        } catch(e) { facSpinner.style.display='none'; facIcon.style.display='block'; }
    }, 350));

    document.addEventListener('click', e => {
        if (!document.getElementById('facSearchWrap').contains(e.target))
            facResults.style.display = 'none';
    });
}

function pickFacility(r) {
    facResults.style.display = 'none';
    facInput.value = r.facility_name;
    facilityData   = r;

    document.getElementById('h_facility_id').value   = r.facility_id;
    document.getElementById('h_facility_name').value = r.facility_name;
    document.getElementById('h_mflcode').value        = r.mflcode||'';
    document.getElementById('h_county').value         = r.county_name||'';
    document.getElementById('h_subcounty').value      = r.subcounty_name||'';

    document.getElementById('fc_name').textContent      = r.facility_name;
    document.getElementById('fc_mfl').textContent       = r.mflcode||'—';
    document.getElementById('fc_county').textContent    = r.county_name||'—';
    document.getElementById('fc_subcounty').textContent = r.subcounty_name||'—';
    document.getElementById('fc_level').textContent     = r.level_of_care_name||'—';
    document.getElementById('fc_owner').textContent     = r.owner||'—';
    document.getElementById('fc_sdp_fac').textContent   = r.sdp||'—';
    document.getElementById('fc_agency').textContent    = r.agency||'—';
    document.getElementById('fc_emr').textContent       = r.emr||'—';
    document.getElementById('facilityCard').style.display = 'block';
}

function clearFacility() {
    facilityData = {};
    facInput.value = '';
    document.getElementById('h_facility_id').value   = '';
    document.getElementById('h_facility_name').value = '';
    document.getElementById('h_mflcode').value        = '';
    document.getElementById('h_county').value         = '';
    document.getElementById('h_subcounty').value      = '';
    document.getElementById('facilityCard').style.display = 'none';
}

// ── Asset change — show depreciation ───────────────────────────────────
function onAssetChange(dig_id) {
    const sel = document.getElementById('dig_id');
    const opt = sel.options[sel.selectedIndex];
    if (dig_id && opt) {
        currentDepRate = parseFloat(opt.dataset.dep) || 0;
        document.getElementById('depPct').textContent = currentDepRate;
        document.getElementById('depBadge').style.display = 'inline-flex';
    } else {
        currentDepRate = 0;
        document.getElementById('depBadge').style.display = 'none';
    }
    calcCurrentValue();
}

// ── Toggle end-date field ───────────────────────────────────────────────
function toggleEndDate(cb) {
    const edField = document.getElementById('end_date');
    edField.disabled = cb.checked;
    if (cb.checked) edField.value = '';
}

// ── Service level toggle SDP ────────────────────────────────────────────
function onServiceLevelChange(val) {
    document.getElementById('sdpGroup').style.display = val==='Service Delivery Point' ? 'block' : 'none';
    // Update selected class on labels
    document.querySelectorAll('.sl-opt').forEach(lbl => {
        lbl.classList.toggle('selected', lbl.querySelector('input').value === val);
    });
}

// ── Current value calculation ───────────────────────────────────────────
function calcCurrentValue() {
    const pv        = parseFloat(document.getElementById('purchase_value').value) || 0;
    const issueDateStr = document.getElementById('issue_date').value;
    if (!pv || !issueDateStr || !currentDepRate) {
        document.getElementById('cvAmount').textContent = 'KES ' + (pv ? pv.toFixed(2) : '0.00');
        document.getElementById('current_value_hidden').value = pv || 0;
        return;
    }
    const issueDate = new Date(issueDateStr);
    const now       = new Date();
    const monthsElapsed = Math.max(0,
        (now.getFullYear() - issueDate.getFullYear()) * 12 +
        (now.getMonth() - issueDate.getMonth())
    );
    // Reducing balance: CV = PV × (1 − dep/100)^(months/12)
    let cv = pv * Math.pow(1 - currentDepRate / 100, monthsElapsed / 12);
    if (cv < 0) cv = 0;
    cv = Math.round(cv * 100) / 100;

    document.getElementById('cvAmount').textContent = 'KES ' + cv.toLocaleString('en-KE', {minimumFractionDigits:2});
    document.getElementById('current_value_hidden').value = cv;
}

// ── Save record ─────────────────────────────────────────────────────────
async function saveRecord() {
    const fid   = document.getElementById('h_facility_id').value;
    const digId = document.getElementById('dig_id').value;
    const pv    = document.getElementById('purchase_value').value;
    const idate = document.getElementById('issue_date').value;
    const slevel = document.querySelector('input[name="service_level"]:checked')?.value || '';

    if (!fid)    { showToast('Please select a facility first.', 'error'); return; }
    if (!digId)  { showToast('Please select a digital asset.', 'error'); return; }
    if (!pv || parseFloat(pv) <= 0) { showToast('Please enter a valid purchase value.', 'error'); return; }
    if (!idate)  { showToast('Please enter the issue date.', 'error'); return; }
    if (!slevel) { showToast('Please select a service level.', 'error'); return; }

    const sel    = document.getElementById('dig_id');
    const opt    = sel.options[sel.selectedIndex];
    const assetName = opt ? opt.dataset.name : '';
    const depPct = currentDepRate;

    const noEnd   = document.getElementById('no_end_date').checked ? 1 : 0;
    const endDate = noEnd ? '' : document.getElementById('end_date').value;
    const investId = document.getElementById('h_invest_id').value || '';

    const fd = new FormData();
    fd.append('ajax_save',              '1');
    fd.append('invest_id',              investId);
    fd.append('facility_id',            fid);
    fd.append('facility_name',          document.getElementById('h_facility_name').value);
    fd.append('mflcode',                document.getElementById('h_mflcode').value);
    fd.append('county_name',            document.getElementById('h_county').value);
    fd.append('subcounty_name',         document.getElementById('h_subcounty').value);
    fd.append('dit_asset_name',         assetName);
    fd.append('asset_name',             assetName);
    fd.append('depreciation_percentage', depPct);
    fd.append('purchase_value',         pv);
    fd.append('issue_date',             idate);
    fd.append('no_end_date',            noEnd);
    fd.append('end_date',               endDate);
    fd.append('current_value',          document.getElementById('current_value_hidden').value);
    fd.append('dig_funder_name',        document.getElementById('dig_funder_id').value);
    fd.append('sdp_name',               document.getElementById('sdp_id').value || '');
    fd.append('emr_type_name',          document.getElementById('emr_type_id').value);
    fd.append('service_level',          slevel);
    fd.append('lot_number',             document.getElementById('lot_number').value);

    try {
        const data = await fetch(THIS_FILE, {method:'POST', body:fd}).then(r=>r.json());
        if (data.success) {
            showToast(data.action==='insert' ? 'Investment saved successfully!' : 'Investment updated!', 'success');
            document.getElementById('h_invest_id').value = data.invest_id;
            // Update the CV display in case server recalculated
            document.getElementById('cvAmount').textContent =
                'KES ' + parseFloat(data.current_value).toLocaleString('en-KE', {minimumFractionDigits:2});
            setTimeout(() => { showTab('list'); window.location.reload(); }, 1400);
        } else {
            showToast(data.error || 'Save failed — please try again.', 'error');
        }
    } catch(err) {
        console.error(err);
        showToast('Network error — please check your connection.', 'error');
    }
}

// ── Reset form ──────────────────────────────────────────────────────────
function resetForm() {
    clearFacility();
    document.getElementById('dig_id').value          = '';
    document.getElementById('dig_funder_id').value   = '';
    document.getElementById('emr_type_id').value     = '';
    document.getElementById('sdp_id').value          = '';
    document.getElementById('purchase_value').value  = '';
    document.getElementById('issue_date').value      = '';
    document.getElementById('end_date').value        = '';
    document.getElementById('lot_number').value      = '';
    document.getElementById('no_end_date').checked   = false;
    document.getElementById('end_date').disabled     = false;
    document.getElementById('h_invest_id').value     = '';
    document.getElementById('depBadge').style.display = 'none';
    document.getElementById('sdpGroup').style.display = 'none';
    const radios = document.querySelectorAll('input[name="service_level"]');
    radios.forEach(r => r.checked = r.value === 'Facility-wide');
    document.querySelectorAll('.sl-opt').forEach(l =>
        l.classList.toggle('selected', l.querySelector('input').value === 'Facility-wide'));
    currentDepRate = 0;
    calcCurrentValue();
    showToast('Form cleared.', 'success');
    window.scrollTo({top:0, behavior:'smooth'});
}

// ── Delete record ───────────────────────────────────────────────────────
function deleteRecord(id, name) {
    deleteTargetId = id;
    document.getElementById('delAssetName').textContent = name;
    document.getElementById('delModal').classList.add('show');
}
function closeDelModal() {
    document.getElementById('delModal').classList.remove('show');
    deleteTargetId = 0;
}
async function confirmDelete() {
    if (!deleteTargetId) return;
    const fd = new FormData();
    fd.append('ajax_delete', '1');
    fd.append('invest_id',   deleteTargetId);
    try {
        const data = await fetch(THIS_FILE, {method:'POST', body:fd}).then(r=>r.json());
        if (data.success) {
            showToast('Record deleted.', 'success');
            closeDelModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.error || 'Delete failed.', 'error');
        }
    } catch(e) {
        showToast('Network error.', 'error');
    }
}

// ── Table filter ────────────────────────────────────────────────────────
function filterTable() {
    const q      = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value.toLowerCase();
    const level  = document.getElementById('filterLevel').value.toLowerCase();
    const rows   = document.querySelectorAll('#investTbody tr');
    rows.forEach(row => {
        const txt    = row.textContent.toLowerCase();
        const rStat  = (row.dataset.status||'').toLowerCase();
        const rLevel = (row.dataset.level||'').toLowerCase();
        const matchQ = !q || txt.includes(q);
        const matchS = !status || rStat === status;
        const matchL = !level  || rLevel === level;
        row.style.display = (matchQ && matchS && matchL) ? '' : 'none';
    });
}

// ── CSV import ──────────────────────────────────────────────────────────
let csvFile = null;

const csvDrop = document.getElementById('csvDrop');
if (csvDrop) {
    csvDrop.addEventListener('dragover', e => { e.preventDefault(); csvDrop.classList.add('drag-over'); });
    csvDrop.addEventListener('dragleave', () => csvDrop.classList.remove('drag-over'));
    csvDrop.addEventListener('drop', e => {
        e.preventDefault();
        csvDrop.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file && (file.type === 'text/csv' || file.name.endsWith('.csv'))) {
            csvFile = file;
            document.getElementById('csvFileName').textContent = '📎 ' + file.name;
            document.getElementById('btnImport').disabled = false;
        } else {
            showToast('Please upload a valid CSV file.', 'error');
        }
    });
}

function onCsvFileChange(input) {
    if (input.files.length) {
        csvFile = input.files[0];
        document.getElementById('csvFileName').textContent = '📎 ' + csvFile.name;
        document.getElementById('btnImport').disabled = false;
    }
}

async function importCsv() {
    if (!csvFile) { showToast('Please select a CSV file first.', 'error'); return; }
    const btn = document.getElementById('btnImport');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing…';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('ajax_csv_import', '1');
    fd.append('csv_file', csvFile);

    try {
        const data = await fetch(THIS_FILE, {method:'POST', body:fd}).then(r=>r.json());
        const res  = document.getElementById('importResult');
        if (data.success) {
            let html = `<div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size:1.3rem;flex-shrink:0"></i>
                <div><strong>Import Complete!</strong><br>
                ✅ ${data.imported} records imported &nbsp;|&nbsp;
                ⚠️ ${data.skipped} rows skipped</div></div>`;
            if (data.errors.length) {
                html += `<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i>
                    <div><strong>Errors:</strong><br>${data.errors.map(e=>`• ${e}`).join('<br>')}</div></div>`;
            }
            res.innerHTML = html;
            if (data.imported > 0) setTimeout(() => window.location.reload(), 2000);
        } else {
            res.innerHTML = `<div class="alert alert-error">
                <i class="fas fa-times-circle"></i> ${data.error}</div>`;
        }
    } catch(e) {
        document.getElementById('importResult').innerHTML =
            `<div class="alert alert-error"><i class="fas fa-times-circle"></i> Network error — please try again.</div>`;
    }

    btn.innerHTML = origHtml;
    btn.disabled  = false;
}

// ── Download CSV template ────────────────────────────────────────────────
function downloadTemplate(e) {
    e.preventDefault();
    const header = [
        'facility_name','mflcode','county_name','subcounty_name',
        'dit_asset_name','purchase_value','issue_date','end_date','no_end_date',
        'dig_funder_name','sdp_name','emr_type_name','service_level','lot_number'
    ].join(',');
    const sample = [
        'Nairobi Central Hospital','10001','Nairobi','Starehe',
        'Desktop Computer','250000','2024-01-15','','1',
        'Ministry of Health','Outpatient','OpenMRS','Facility-wide','LOT-001'
    ].join(',');
    const blob = new Blob([header + '\n' + sample], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'digital_investments_template.csv';
    a.click(); URL.revokeObjectURL(url);
}

// ── Init ─────────────────────────────────────────────────────────────────
<?php if ($edit_row): ?>
showTab('form');
<?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'list'): ?>
showTab('list');
<?php endif; ?>

// Recalculate CV on page load if editing
document.addEventListener('DOMContentLoaded', () => {
    calcCurrentValue();

    // Sync service level radio visual state on load
    const checked = document.querySelector('input[name="service_level"]:checked');
    if (checked) onServiceLevelChange(checked.value);

    // Load dep badge if asset pre-selected
    const digSel = document.getElementById('dig_id');
    if (digSel && digSel.value) onAssetChange(digSel.value);
});
</script>
</body>
</html>