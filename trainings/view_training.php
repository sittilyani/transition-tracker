<?php
session_start();
include '../includes/config.php';

if (!isset($_GET['id'])) {
    header("Location: training_list.php");
    exit();
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
    header("Location: training_list.php");
    exit();
}

// Fetch participants
$participants = mysqli_query($conn,
    "SELECT cs.*, tp.attendance_status, tp.certificate_issued, tp.certificate_number
     FROM training_participants tp
     JOIN county_staff cs ON tp.staff_id = cs.staff_id
     WHERE tp.session_id = $session_id
     ORDER BY cs.first_name, cs.last_name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Training Session</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f4f7fc;
            padding: 20px;
        }

        .container {
            max-width: 90%;
            margin: 0 auto;
        }

        .header {
            background: #0D1A63;
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .header h1 i {
            margin-right: 10px;
        }

        .badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .session-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .session-code {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        .status {
            background: <?php echo $session['status'] == 'submitted' ? '#28a745' : ($session['status'] == 'draft' ? '#ffc107' : ($session['status'] == 'completed' ? '#17a2b8' : '#dc3545')); ?>;
            color: <?php echo $session['status'] == 'draft' ? '#212529' : 'white'; ?>;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        .dates {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .date-box {
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            flex: 1;
        }

        .date-box .label {
            font-size: 12px;
            color: #666;
        }

        .date-box .date {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
        }

        .participants-table {
            width: 100%;
            border-collapse: collapse;
        }

        .participants-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #555;
        }

        .participants-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .participants-table tr:hover {
            background: #f5f5f5;
        }

        .footer-info {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 14px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            font-size: 24px;
            transition: transform 0.3s ease;
        }

        .print-btn:hover {
            transform: scale(1.1);
        }

        @media print {
            .print-btn, .header .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-eye"></i> Training Session Details</h1>
            <div>
                <span class="badge"><i class="fas fa-code"></i> <?php echo htmlspecialchars($session['session_code']); ?></span>
                <a href="print_training.php?id=<?php echo $session_id; ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Print
                </a>
                <a href="training_list.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="session-card">
            <div class="session-header">
                <div>
                    <div class="session-code"><?php echo htmlspecialchars($session['course_name']); ?></div>
                    <?php if (!empty($session['course_section'])): ?>
                        <div style="color: #666;">Section: <?php echo htmlspecialchars($session['course_section']); ?></div>
                    <?php endif; ?>
                </div>
                <span class="status"><?php echo ucfirst($session['status']); ?></span>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label>Training Type</label>
                    <div class="value"><?php echo htmlspecialchars($session['trainingtype_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Duration</label>
                    <div class="value"><?php echo htmlspecialchars($session['duration_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Location</label>
                    <div class="value"><?php echo htmlspecialchars($session['location_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Facilitator Level</label>
                    <div class="value"><?php echo htmlspecialchars($session['facilitator_level_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>County</label>
                    <div class="value"><?php echo htmlspecialchars($session['county_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Subcounty</label>
                    <div class="value"><?php echo htmlspecialchars($session['sub_county_name'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <div class="dates">
                <div class="date-box">
                    <div class="label">Start Date</div>
                    <div class="date"><?php echo date('F j, Y', strtotime($session['start_date'])); ?></div>
                </div>
                <div class="date-box">
                    <div class="label">End Date</div>
                    <div class="date"><?php echo date('F j, Y', strtotime($session['end_date'])); ?></div>
                </div>
            </div>

            <?php if (!empty($session['training_objectives'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong>Training Objectives:</strong>
                <p style="margin-top: 5px; color: #555;"><?php echo nl2br(htmlspecialchars($session['training_objectives'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($session['materials_provided'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong>Materials Provided:</strong>
                <p style="margin-top: 5px; color: #555;"><?php echo nl2br(htmlspecialchars($session['materials_provided'])); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="session-card">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-users"></i> Participants (<?php echo mysqli_num_rows($participants); ?>)</h2>

            <div style="overflow-x: auto;">
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Sex</th>
                            <th>Phone</th>
                            <th>ID Number</th>
                            <th>Email</th>
                            <th>Facility</th>
                            <th>Department</th>
                            <th>Cadre</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        while ($p = mysqli_fetch_assoc($participants)):
                            $full_name = trim($p['first_name'] . ' ' . $p['last_name'] . (!empty($p['other_name']) ? ' ' . $p['other_name'] : ''));
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['sex'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['staff_phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['id_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['email'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['facility_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['cadre_name']); ?></td>
                            <td>
                                <span style="background: <?php
                                    echo $p['attendance_status'] == 'registered' ? '#ffc107' :
                                        ($p['attendance_status'] == 'attended' ? '#28a745' :
                                        ($p['attendance_status'] == 'certified' ? '#17a2b8' : '#dc3545'));
                                ?>;
                                               color: <?php echo $p['attendance_status'] == 'registered' ? '#212529' : '#fff'; ?>;
                                               padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo ucfirst($p['attendance_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-info">
            <div><i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($session['created_by']); ?></div>
            <div><i class="fas fa-calendar"></i> Created: <?php echo date('F j, Y g:i a', strtotime($session['created_at'])); ?></div>
            <?php if ($session['submitted_by']): ?>
            <div><i class="fas fa-check-circle"></i> Submitted by: <?php echo htmlspecialchars($session['submitted_by']); ?> on <?php echo date('F j, Y g:i a', strtotime($session['submitted_at'])); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i>
    </button>
</body>
</html>