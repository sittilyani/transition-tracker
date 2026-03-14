<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

// Fetch all training sessions with counts
$sessions = mysqli_query($conn,
    "SELECT ts.*, c.course_name,
     COUNT(tp.participant_id) as participant_count,
     co.county_name,
     sc.sub_county_name
     FROM training_sessions ts
     LEFT JOIN courses c ON ts.course_id = c.course_id
     LEFT JOIN training_participants tp ON ts.session_id = tp.session_id
     LEFT JOIN counties co ON ts.county_id = co.county_id
     LEFT JOIN sub_counties sc ON ts.subcounty_id = sc.sub_county_id
     GROUP BY ts.session_id
     ORDER BY ts.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Sessions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            font-size: 28px;
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
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .session-grid {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .session-grid table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .session-grid thead tr {
            background: #0D1A63;
            color: white;
        }

        .session-grid thead th {
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .session-grid tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s ease;
        }

        .session-grid tbody tr:last-child { border-bottom: none; }
        .session-grid tbody tr:hover { background: #f8f9ff; }

        .session-grid td {
            padding: 11px 14px;
            vertical-align: middle;
        }

        .session-code {
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
            white-space: nowrap;
        }

        .session-title {
            font-weight: 600;
            color: #1a1e2e;
            font-size: 13px;
        }

        .status {
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status.draft      { background: #ffc107; color: #212529; }
        .status.submitted  { background: #28a745; color: white; }
        .status.completed  { background: #17a2b8; color: white; }
        .status.cancelled  { background: #dc3545; color: white; }

        .participant-count {
            background: #f0f0f0;
            padding: 3px 9px;
            border-radius: 8px;
            font-size: 12px;
            white-space: nowrap;
        }

        .session-actions {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            text-align: center;
            text-decoration: none;
            color: white;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .view-btn  { background: #17a2b8; }
        .edit-btn  { background: #ffc107; color: #212529; }
        .print-btn { background: #6c757d; }

        @media (max-width: 768px) {
            .session-grid { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions" style="display: flex; gap: 10px;">
            <a href="staff_training_form.php" class="btn btn-primary">
                <i class="fas fa-user-graduate"></i> My Trainings
            </a>
            <a href="view_staff_trainings.php" class="btn btn-primary">
                <i class="fas fa-search"></i> View All Staff Trainings
            </a>
            <a href="training_registration.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Training Session
            </a>
        </div>

        <?php
        // Get statistics
        $total = mysqli_num_rows($sessions);
        $drafts = mysqli_query($conn, "SELECT COUNT(*) as count FROM training_sessions WHERE status = 'draft'")->fetch_assoc()['count'];
        $submitted = mysqli_query($conn, "SELECT COUNT(*) as count FROM training_sessions WHERE status = 'submitted'")->fetch_assoc()['count'];
        $participants = mysqli_query($conn, "SELECT COUNT(*) as count FROM training_participants")->fetch_assoc()['count'];
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total; ?></div>
                <div class="label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $drafts; ?></div>
                <div class="label">Drafts</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $submitted; ?></div>
                <div class="label">Submitted</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $participants; ?></div>
                <div class="label">Total Participants</div>
            </div>
        </div>

        <div class="session-grid">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Course</th>
                        <th>Dates</th>
                        <th>Location</th>
                        <th>Created By</th>
                        <th>Participants</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($sessions) > 0):
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($sessions)): ?>
                    <tr>
                        <td style="color:#999;font-size:12px"><?php echo $i++; ?></td>
                        <td><span class="session-code"><?php echo htmlspecialchars($row['session_code']); ?></span></td>
                        <td><span class="session-title"><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></span></td>
                        <td style="color:#555;white-space:nowrap">
                            <i class="fas fa-calendar" style="color:#667eea;margin-right:4px"></i>
                            <?php echo date('d M Y', strtotime($row['start_date'])); ?> –
                            <?php echo date('d M Y', strtotime($row['end_date'])); ?>
                        </td>
                        <td style="color:#555">
                            <i class="fas fa-map-marker-alt" style="color:#667eea;margin-right:4px"></i>
                            <?php echo htmlspecialchars($row['county_name'] ?? 'N/A'); ?>
                            <?php if (!empty($row['sub_county_name'])): ?>,
                                <?php echo htmlspecialchars($row['sub_county_name']); ?>
                            <?php endif; ?>
                        </td>
                        <td style="color:#555">
                            <i class="fas fa-user" style="color:#667eea;margin-right:4px"></i>
                            <?php echo htmlspecialchars($row['created_by']); ?>
                        </td>
                        <td>
                            <span class="participant-count">
                                <i class="fas fa-users"></i>
                                <?php echo $row['participant_count']; ?>
                                <?php echo $row['participant_count'] != 1 ? 'participants' : 'participant'; ?>
                            </span>
                        </td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td>
                            <div class="session-actions">
                                <a href="view_training.php?id=<?php echo $row['session_id']; ?>" class="action-btn view-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($row['status'] == 'draft'): ?>
                                <a href="training_registration.php?edit=<?php echo $row['session_id']; ?>" class="action-btn edit-btn">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php endif; ?>
                                <a href="print_training.php?id=<?php echo $row['session_id']; ?>" class="action-btn print-btn" target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:50px;color:#999">
                            <i class="fas fa-folder-open" style="font-size:36px;display:block;margin-bottom:12px;opacity:.4"></i>
                            <strong>No Training Sessions Found</strong><br>
                            <span style="font-size:12px">Click "New Training Session" to create your first session.</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>