<?php
// Note: You need to install dompdf via composer: composer require dompdf/dompdf
require_once '../vendor/autoload.php';

session_start();
include '../includes/config.php';

if (!isset($_GET['id'])) {
    die('Invalid request');
}

$session_id = (int)$_GET['id'];

// Fetch session details with joins
$query = "SELECT ts.*,
          c.course_name, c.course_section,
          cd.duration_name,
          tt.trainingtype_name,
          tl.location_name,
          fl.facilitator_level_name,
          co.county_name,
          s.sub_county_name
          FROM training_sessions ts
          LEFT JOIN courses c ON ts.course_id = c.course_id
          LEFT JOIN course_durations cd ON ts.duration_id = cd.duration_id
          LEFT JOIN training_types tt ON ts.training_type_id = tt.trainingtype_id
          LEFT JOIN training_locations tl ON ts.location_id = tl.location_id
          LEFT JOIN facilitator_levels fl ON ts.fac_level_id = fl.fac_level_id
          LEFT JOIN counties co ON ts.county_id = co.county_id
          LEFT JOIN sub_counties s ON ts.subcounty_id = s.sub_county_id
          WHERE ts.session_id = $session_id";

$result = mysqli_query($conn, $query);
$session = mysqli_fetch_assoc($result);

if (!$session) {
    die('Session not found');
}

// Fetch participants
$participants = mysqli_query($conn,
    "SELECT cs.*, tp.attendance_status
     FROM training_participants tp
     JOIN county_staff cs ON tp.staff_id = cs.staff_id
     WHERE tp.session_id = $session_id
     ORDER BY cs.first_name, cs.last_name"
);

// Generate HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Training Session Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .report-title {
            font-size: 18px;
            margin-top: 10px;
            color: #666;
        }
        .session-code {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .info-item {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
        }
        .info-item .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-size: 14px;
            font-weight: bold;
        }
        .dates {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        th {
            background: #667eea;
            color: white;
            padding: 10px;
            font-size: 12px;
            text-align: left;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        .watermark {
            position: fixed;
            bottom: 10px;
            right: 10px;
            font-size: 10px;
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- Replace with your company logo -->
        <div class="logo">🏥</div>
        <div class="company-name">Health Training Institute</div>
        <div class="report-title">Training Session Report</div>
        <div class="session-code">Session Code: ' . htmlspecialchars($session['session_code']) . '</div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <div class="label">Course</div>
            <div class="value">' . htmlspecialchars($session['course_name']) .
            (!empty($session['course_section']) ? ' (' . htmlspecialchars($session['course_section']) . ')' : '') . '</div>
        </div>
        <div class="info-item">
            <div class="label">Training Type</div>
            <div class="value">' . htmlspecialchars($session['trainingtype_name'] ?? 'N/A') . '</div>
        </div>
        <div class="info-item">
            <div class="label">Duration</div>
            <div class="value">' . htmlspecialchars($session['duration_name'] ?? 'N/A') . '</div>
        </div>
        <div class="info-item">
            <div class="label">Location</div>
            <div class="value">' . htmlspecialchars($session['location_name'] ?? 'N/A') . '</div>
        </div>
        <div class="info-item">
            <div class="label">Facilitator Level</div>
            <div class="value">' . htmlspecialchars($session['facilitator_level_name'] ?? 'N/A') . '</div>
        </div>
        <div class="info-item">
            <div class="label">County/Subcounty</div>
            <div class="value">' . htmlspecialchars($session['county_name'] ?? 'N/A') . ' / ' . htmlspecialchars($session['sub_county_name'] ?? 'N/A') . '</div>
        </div>
    </div>

    <div class="dates">
        <div><strong>Start Date:</strong> ' . date('F j, Y', strtotime($session['start_date'])) . '</div>
        <div><strong>End Date:</strong> ' . date('F j, Y', strtotime($session['end_date'])) . '</div>
    </div>';

if (!empty($session['training_objectives'])) {
    $html .= '<div style="margin: 15px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #667eea;">
        <strong>Training Objectives:</strong><br>' . nl2br(htmlspecialchars($session['training_objectives'])) . '
    </div>';
}

if (!empty($session['materials_provided'])) {
    $html .= '<div style="margin: 15px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #667eea;">
        <strong>Materials Provided:</strong><br>' . nl2br(htmlspecialchars($session['materials_provided'])) . '
    </div>';
}

$html .= '
    <h3 style="margin-top: 25px;">Participants List (' . mysqli_num_rows($participants) . ')</h3>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Sex</th>
                <th>Phone</th>
                <th>ID Number</th>
                <th>Facility</th>
                <th>Department</th>
                <th>Cadre</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

$count = 1;
while ($p = mysqli_fetch_assoc($participants)) {
    $full_name = trim($p['first_name'] . ' ' . $p['last_name'] . (!empty($p['other_name']) ? ' ' . $p['other_name'] : ''));
    $status_color = $p['attendance_status'] == 'registered' ? '#ffc107' :
                   ($p['attendance_status'] == 'attended' ? '#28a745' :
                   ($p['attendance_status'] == 'certified' ? '#17a2b8' : '#dc3545'));
    $status_text_color = $p['attendance_status'] == 'registered' ? '#212529' : '#fff';

    $html .= '
        <tr>
            <td>' . $count++ . '</td>
            <td><strong>' . htmlspecialchars($full_name) . '</strong></td>
            <td>' . htmlspecialchars($p['sex'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($p['staff_phone'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($p['id_number'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($p['facility_name']) . '</td>
            <td>' . htmlspecialchars($p['department_name']) . '</td>
            <td>' . htmlspecialchars($p['cadre_name']) . '</td>
            <td>
                <span style="background: ' . $status_color . '; color: ' . $status_text_color . ';
                         padding: 2px 6px; border-radius: 3px; font-size: 9px;">
                    ' . ucfirst($p['attendance_status']) . '
                </span>
            </td>
        </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <div>Report Generated: ' . date('F j, Y g:i a') . '</div>
        <div>Generated by: ' . htmlspecialchars($_SESSION['full_name'] ?? 'System') . '</div>
    </div>

    <div class="footer">
        <div>Submitted by: ' . htmlspecialchars($session['submitted_by'] ?? 'Not submitted') . '</div>
        <div>Submission Date: ' . (!empty($session['submitted_at']) ? date('F j, Y g:i a', strtotime($session['submitted_at'])) : 'N/A') . '</div>
    </div>

    <div class="watermark">
        Generated on ' . date('Y-m-d H:i:s') . '
    </div>
</body>
</html>';

// Generate PDF
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Output PDF
$filename = "training_session_" . $session['session_code'] . "_" . date('Ymd') . ".pdf";
$dompdf->stream($filename, array("Attachment" => false));
?>