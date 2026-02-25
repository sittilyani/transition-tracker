<?php
session_start();
include '../includes/config.php';

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
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .session-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .session-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .session-code {
            font-size: 14px;
            color: #667eea;
            font-weight: 600;
        }

        .status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status.draft {
            background: #ffc107;
            color: #212529;
        }

        .status.submitted {
            background: #28a745;
            color: white;
        }

        .status.completed {
            background: #17a2b8;
            color: white;
        }

        .status.cancelled {
            background: #dc3545;
            color: white;
        }

        .session-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .session-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #666;
            font-size: 13px;
            margin: 10px 0;
        }

        .session-meta i {
            width: 16px;
            color: #667eea;
            margin-right: 5px;
        }

        .participant-count {
            background: #f0f0f0;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-block;
            font-size: 13px;
            margin: 10px 0;
        }

        .session-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-align: center;
            text-decoration: none;
            color: white;
        }

        .view-btn { background: #17a2b8; }
        .edit-btn { background: #ffc107; color: #212529; }
        .print-btn { background: #6c757d; }

        @media (max-width: 768px) {
            .session-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> Training Sessions</h1>
            <a href="training_registration.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Training
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
            <?php if (mysqli_num_rows($sessions) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($sessions)): ?>
                    <div class="session-card">
                        <div class="session-header">
                            <span class="session-code"><?php echo htmlspecialchars($row['session_code']); ?></span>
                            <span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                        </div>
                        <div class="session-title"><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></div>
                        <div class="session-meta">
                            <div><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['start_date'])); ?> - <?php echo date('M d, Y', strtotime($row['end_date'])); ?></div>
                            <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['county_name'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($row['sub_county_name'] ?? 'N/A'); ?></div>
                            <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['created_by']); ?></div>
                        </div>
                        <div class="participant-count">
                            <i class="fas fa-users"></i> <?php echo $row['participant_count']; ?> Participant<?php echo $row['participant_count'] != 1 ? 's' : ''; ?>
                        </div>
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
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 10px;">
                    <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <h3>No Training Sessions Found</h3>
                    <p style="color: #666;">Click the "New Training" button to create your first training session.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>