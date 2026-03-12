<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Require dompdf via Composer autoload (adjust path as needed)
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Parameters ────────────────────────────────────────────────────────────────
$training_id = isset($_GET['id'])     ? (int)$_GET['id']         : 0;
$source      = isset($_GET['source']) ? $_GET['source']           : 'self'; // 'self' | 'session'

if ($training_id === 0) {
    $_SESSION['error_msg'] = "Invalid training ID.";
    header('Location: view_staff_trainings.php');
    exit();
}

// ── Fetch training + staff details ────────────────────────────────────────────
$training = null;

if ($source === 'session') {
    // From staff_trainings (session-based, always verified)
    $query = "
        SELECT st.*,
               cs.first_name, cs.last_name, cs.id_number, cs.sex,
               cs.facility_name, cs.department_name, cs.cadre_name,
               cs.county_name, cs.subcounty_name
        FROM staff_trainings st
        JOIN county_staff cs ON st.id_number = cs.id_number
        WHERE st.self_training_id = ?
    ";
} else {
    // From staff_self_trainings (individual submissions)
    $query = "
        SELECT sst.*,
               cs.first_name, cs.last_name, cs.id_number, cs.sex,
               cs.facility_name, cs.department_name, cs.cadre_name,
               cs.county_name, cs.subcounty_name
        FROM staff_self_trainings sst
        JOIN county_staff cs ON sst.id_number = cs.id_number
        WHERE sst.self_training_id = ?
    ";
}

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $training_id);
$stmt->execute();
$result = $stmt->get_result();
$training = $result->fetch_assoc();
$stmt->close();

if (!$training) {
    $_SESSION['error_msg'] = "Training record not found.";
    header('Location: view_staff_trainings.php');
    exit();
}

// For self-trainings enforce verified status; sessions are always verified
if ($source === 'self' && $training['status'] !== 'verified') {
    $_SESSION['error_msg'] = "Certificate is only available for verified trainings.";
    header('Location: view_staff_trainings.php');
    exit();
}

// ── Fetch facilitators ────────────────────────────────────────────────────────
$facilitators = [];
$fac_stmt = $conn->prepare("SELECT * FROM training_facilitators WHERE self_training_id = ?");
$fac_stmt->bind_param('i', $training_id);
$fac_stmt->execute();
$fac_result = $fac_stmt->get_result();
while ($fac = $fac_result->fetch_assoc()) {
    $facilitators[] = $fac;
}
$fac_stmt->close();

// If no facilitators in table, fall back to facilitator_details JSON column
if (empty($facilitators) && !empty($training['facilitator_details'])) {
    $json_facs = json_decode($training['facilitator_details'], true);
    if (is_array($json_facs)) {
        foreach ($json_facs as $jf) {
            if (!empty($jf['name'])) {
                $facilitators[] = [
                    'facilitator_name'  => $jf['name'],
                    'facilitator_cadre' => $jf['cadre'] ?? ($jf['level'] ?? ''),
                ];
            }
        }
    }
}

// ── Derived display values ────────────────────────────────────────────────────
function calcDuration($start, $end) {
    $days = floor((strtotime($end) - strtotime($start)) / 86400) + 1;
    return $days === 1 ? '1 day' : "$days days";
}

$staff_name        = trim($training['first_name'] . ' ' . $training['last_name']);
$course_name       = $training['course_name'] ?? '';
$training_type     = $training['training_type'] ?? 'Training';
$start_date        = date('F j, Y', strtotime($training['start_date']));
$end_date          = date('F j, Y', strtotime($training['end_date']));
$duration          = calcDuration($training['start_date'], $training['end_date']);
$cert_num          = !empty($training['certificate_number'])
                        ? $training['certificate_number']
                        : 'CERT-' . str_pad($training_id, 6, '0', STR_PAD_LEFT);
$issue_date        = date('F j, Y');
$facility          = $training['facility_name']   ?? '';
$department        = $training['department_name'] ?? '';
$cadre             = $training['cadre_name']      ?? '';
$id_number         = $training['id_number']       ?? '';
$county            = $training['county_name']     ?? '';
$training_provider = $training['training_provider'] ?? '';
$venue             = $training['venue']           ?? '';

// ── Signature images (base64 embed so dompdf resolves them) ──────────────────
function embedImage(string $path): string {
    if (!file_exists($path)) return '';
    $mime = mime_content_type($path);
    $data = base64_encode(file_get_contents($path));
    return "data:$mime;base64,$data";
}

$sig_dir        = __DIR__ . '/../assets/signatures/';
$sig_coordinator = embedImage($sig_dir . 'coordinator.png');
$sig_director    = embedImage($sig_dir . 'director.png');
$sig_hr          = embedImage($sig_dir . 'hr.png');

// ── Build facilitators HTML ───────────────────────────────────────────────────
$facilitators_html = '';
if (!empty($facilitators)) {
    $items = '';
    foreach ($facilitators as $f) {
        $name   = htmlspecialchars($f['facilitator_name']);
        $fcadre = !empty($f['facilitator_cadre']) ? ' (' . htmlspecialchars($f['facilitator_cadre']) . ')' : '';
        $items .= "<span class=\"fac-item\">$name$fcadre</span>";
    }
    $facilitators_html = "
    <div class='fac-wrap'>
        <div class='fac-lbl'>Facilitated by:</div>
        <div class='fac-list'>$items</div>
    </div>";
}

// ── Build signature blocks ────────────────────────────────────────────────────
function sigBlock(string $imgSrc, string $name, string $title): string {
    $img = $imgSrc
        ? "<img src=\"$imgSrc\" class=\"sig-img\" alt=\"signature\">"
        : "<span class=\"sig-blank\"></span>";
    $nameHtml = $name ? "<div class=\"sig-name\">" . htmlspecialchars($name) . "</div>" : '';
    return "
    <td>
        $img
        <div class=\"sig-line\"></div>
        $nameHtml
        <div class=\"sig-role\">" . htmlspecialchars($title) . "</div>
    </td>";
}

$verifier_name = $training['verified_by'] ?? '';
$sigs_html = "
<div class='sig-section'>
  <table class='sig-tbl'>
    <tr>"
    . sigBlock($sig_coordinator, $verifier_name, 'County Training Coordinator')
    . sigBlock($sig_director,    '',              'County Director of Health')
    . sigBlock($sig_hr,          '',              'Human Resources Officer')
. "</tr>
  </table>
</div>";

// ── Embed seal image ─────────────────────────────────────────────────────────
$seal_src = embedImage(__DIR__ . '/../assets/images/seal.png');
$seal_html = $seal_src
    ? "<img src=\"{$seal_src}\" style=\"width:60px;height:60px;\" alt=\"seal\">"
    : "<div style=\"width:60px;height:60px;border-radius:50%;background:#0D1A63;display:inline-block;\"></div>";

// ── Extra details row (venue / provider) ─────────────────────────────────────
$extra_row = '';
if ($venue || $training_provider) {
    $v = $venue            ? "<td><span class=\"lbl\">Venue</span><span class=\"val\">".htmlspecialchars($venue)."</span></td>"            : "<td></td>";
    $p = $training_provider ? "<td><span class=\"lbl\">Provider / Level</span><span class=\"val\">".htmlspecialchars($training_provider)."</span></td>" : "<td></td>";
    $extra_row = "<tr>$v$p<td></td><td></td></tr>";
}

// ── HTML for dompdf ───────────────────────────────────────────────────────────
// A4 landscape = 297 × 210 mm.  Margins: 8mm all round.
// Key dompdf rules:
//   • Never use % heights — use fixed px/pt values
//   • Use <table> for multi-column layouts, not flex/grid
//   • Use position:fixed for true page-bottom footer
//   • Embed all images as base64
$html = "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<style>
@page {
    size: A4 landscape;
    margin: 8mm 8mm 8mm 8mm;
}
* { margin:3; padding:3; box-sizing:border-box; }

body {
    font-family: 'DejaVu Serif', serif;
    font-size: 9pt;
    color: #1a1a2e;
    background: #fff;
}

/* ── Fixed footer — dompdf renders position:fixed relative to page ── */
.page-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 6.5pt;
    color: #999;
    border-top: 1px solid #ddd;
    padding-top: 2px;
    background: #fff;
}
.page-footer .cert-num {
    font-family: 'DejaVu Sans Mono', monospace;
    color: #777;
}

/* ── Outer border frame ── */
.frame-outer {
    border: 7px solid #0D1A63;
    padding: 5px;
    /* fixed height = 210mm - 2×8mm margins = 194mm ≈ 550pt */
    /* we let the inner table fill it naturally */
}
.frame-inner {
    border: 2px solid #c9940a;
    padding: 8px 12px 6px 12px;
}

/* ── Header row (logo | title | seal) ── */
.hdr-table { width:100%; border-collapse:collapse; }
.hdr-table td { vertical-align:middle; }
.hdr-left  { width:70px; text-align:center; }
.hdr-mid   { text-align:center; }
.hdr-right { width:70px; text-align:center; }

.org-line {
    font-size: 7pt;
    color: #666;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.cert-title {
    font-size: 24pt;
    font-weight: 900;
    color: #0D1A63;
    letter-spacing: 2px;
    text-transform: uppercase;
    line-height: 1;
}
.cert-sub {
    font-size: 8.5pt;
    color: #777;
    font-style: italic;
    margin-top: 1px;
}
.hdr-divider {
    border: none;
    border-top: 2px double #0D1A63;
    margin: 5px 0 4px 0;
}

/* ── Recipient ── */
.presented-to {
    text-align: center;
    font-size: 8pt;
    color: #555;
    margin: 2px 0 1px 0;
    font-style: italic;
}
.recipient-wrap { text-align: center; margin-bottom: 3px; }
.recipient-name {
    font-size: 19pt;
    font-weight: 900;
    color: #0D1A63;
    text-transform: uppercase;
    letter-spacing: 2px;
    border-bottom: 2px solid #c9940a;
    padding: 0 24px 2px 24px;
}
.completed-text {
    text-align: center;
    font-size: 8pt;
    color: #555;
    font-style: italic;
    margin: 2px 0;
}
.course-name {
    text-align: center;
    font-size: 13pt;
    font-weight: 900;
    color: #0D1A63;
    font-style: italic;
    margin: 3px 0 5px 0;
    line-height: 1.2;
}

/* ── Details box ── */
.details-box {
    background: #f4f6fb;
    border-left: 4px solid #0D1A63;
    padding: 4px 10px;
    margin: 4px 0;
}
.det { width:100%; border-collapse:collapse; }
.det td {
    width: 25%;
    padding: 2px 6px 2px 0;
    font-size: 7.5pt;
    vertical-align: top;
}
.lbl {
    display: block;
    color: #0D1A63;
    font-weight: bold;
    font-size: 6.5pt;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.val { color: #222; }

/* ── Facilitators ── */
.fac-wrap {
    text-align: center;
    margin: 3px 0 4px 0;
    font-size: 7pt;
}
.fac-lbl {
    font-weight: bold;
    color: #0D1A63;
    text-transform: uppercase;
    font-size: 6.5pt;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
}
.fac-item {
    display: inline-block;
    background: #fff8e1;
    border: 1px solid #c9940a;
    border-radius: 8px;
    padding: 1px 6px;
    margin: 1px 2px;
    color: #444;
}

/* ── Signatures (table-based, dompdf-safe) ── */
.sig-section {
    border-top: 1px dashed #bbb;
    padding-top: 4px;
    margin-top: 4px;
}
.sig-tbl { width:100%; border-collapse:collapse; }
.sig-tbl td {
    width: 33.33%;
    text-align: center;
    padding: 0 6px;
    vertical-align: bottom;
}
.sig-img {
    display: block;
    margin: 0 auto 1px auto;
    max-height: 34px;
    max-width: 110px;
}
.sig-blank { height: 30px; display: block; }
.sig-line  {
    width: 75%;
    margin: 0 auto 2px auto;
    border-top: 1px solid #333;
}
.sig-name  { font-size: 7pt; font-weight: bold; color: #0D1A63; }
.sig-role  { font-size: 6.5pt; color: #666; font-style: italic; }
</style>
</head>
<body>

<!-- Fixed footer (renders at page bottom in dompdf) -->
<div class='page-footer'>
    <span class='cert-num'>Certificate No: {$cert_num}</span>
    &nbsp;&bull;&nbsp; Issue Date: {$issue_date}
    &nbsp;&bull;&nbsp; This certificate is electronically generated and valid without a physical stamp.
</div>

<!-- Main certificate frame -->
<div class='frame-outer'>
<div class='frame-inner'>

  <!-- Header: seal left | title centre | seal right -->
  <table class='hdr-table'>
    <tr>
      <td class='hdr-left'>{$seal_html}</td>
      <td class='hdr-mid'>
        <div class='org-line'>County Government &bull; Department of Health &bull; Human Resource for Health</div>
        <div class='cert-title'>Certificate of Completion</div>
        <div class='cert-sub'>County Health Training Programme</div>
      </td>
      <td class='hdr-right'>{$seal_html}</td>
    </tr>
  </table>
  <hr class='hdr-divider'>

  <!-- Recipient -->
  <div class='presented-to'>This is to certify that</div>
  <div class='recipient-wrap'><span class='recipient-name'>{$staff_name}</span></div>
  <div class='completed-text'>has successfully completed the following training programme:</div>

  <!-- Course -->
  <div class='course-name'>{$course_name}</div>

  <!-- Details -->
  <div class='details-box'>
    <table class='det'>
      <tr>
        <td><span class='lbl'>Training Type</span><span class='val'>{$training_type}</span></td>
        <td><span class='lbl'>Duration</span><span class='val'>{$duration}</span></td>
        <td><span class='lbl'>Start Date</span><span class='val'>{$start_date}</span></td>
        <td><span class='lbl'>End Date</span><span class='val'>{$end_date}</span></td>
      </tr>
      <tr>
        <td><span class='lbl'>Facility</span><span class='val'>{$facility}</span></td>
        <td><span class='lbl'>Department</span><span class='val'>{$department}</span></td>
        <td><span class='lbl'>Cadre</span><span class='val'>{$cadre}</span></td>
        <td><span class='lbl'>ID Number</span><span class='val'>{$id_number}</span></td>
      </tr>
      {$extra_row}
    </table>
  </div>

  <!-- Facilitators -->
  {$facilitators_html}

  <!-- Signatures -->
  {$sigs_html}

</div><!-- /frame-inner -->
</div><!-- /frame-outer -->

</body>
</html>";


// ── Render with dompdf ────────────────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', true);   // allow base64 images
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Serif');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// A4 landscape: 297 × 210 mm
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$safe_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $staff_name);
$filename  = "Certificate_{$safe_name}_{$cert_num}.pdf";

$dompdf->stream($filename, ['Attachment' => 0]); // 0 = inline (view in browser), 1 = download
exit();