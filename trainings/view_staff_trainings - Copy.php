<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Build filter query for combined data
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Data source filter
$data_source = $_GET['data_source'] ?? 'all';

// Course filter
if (!empty($_GET['course'])) {
    $where_clauses[] = "course_name LIKE ?";
    $params[] = "%" . $_GET['course'] . "%";
    $types .= "s";
}

// County filter (using staff details)
if (!empty($_GET['county'])) {
    $where_clauses[] = "cs.county_name = ?";
    $params[] = $_GET['county'];
    $types .= "s";
}

// Subcounty filter
if (!empty($_GET['subcounty'])) {
    $where_clauses[] = "cs.subcounty_name = ?";
    $params[] = $_GET['subcounty'];
    $types .= "s";
}

// Facility filter
if (!empty($_GET['facility'])) {
    $where_clauses[] = "cs.facility_name LIKE ?";
    $params[] = "%" . $_GET['facility'] . "%";
    $types .= "s";
}

// Date range filter
if (!empty($_GET['date_from'])) {
    $where_clauses[] = "t.start_date >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}
if (!empty($_GET['date_to'])) {
    $where_clauses[] = "t.end_date <= ?";
    $params[] = $_GET['date_to'];
    $types .= "s";
}

// Status filter
if (!empty($_GET['status'])) {
    $where_clauses[] = "status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build combined query from both tables
$union_queries = [];

// Add staff_self_trainings to union
if ($data_source == 'all' || $data_source == 'self') {
    $union_queries[] = "
        SELECT
            'self' as source,
            sst.self_training_id as id,
            sst.staff_id,
            sst.id_number,
            sst.course_name,
            sst.training_type,
            sst.start_date,
            sst.end_date,
            sst.training_provider,
            sst.venue,
            sst.certificate_number,
            sst.facilitator_details,
            sst.skills_acquired,
            sst.funding_source,
            sst.status,
            sst.submission_date,
            sst.verified_by,
            sst.verification_date,
            sst.created_at,
            sst.created_by,
            cs.first_name,
            cs.last_name,
            cs.facility_name,
            cs.department_name,
            cs.cadre_name,
            cs.county_name,
            cs.subcounty_name,
            cs.level_of_care_name,
            cs.staff_phone,
            cs.email,
            (SELECT COUNT(*) FROM training_facilitators WHERE self_training_id = sst.self_training_id) as facilitator_count
        FROM staff_self_trainings sst
        JOIN county_staff cs ON sst.id_number = cs.id_number
        WHERE $where_sql
    ";
}

// Add staff_trainings to union
if ($data_source == 'all' || $data_source == 'session') {
    $union_queries[] = "
        SELECT
            'session' as source,
            st.self_training_id as id,
            st.staff_id,
            st.id_number,
            st.course_name,
            st.training_type,
            st.start_date,
            st.end_date,
            st.training_provider,
            st.venue,
            st.certificate_number,
            st.facilitator_details,
            st.skills_acquired,
            st.funding_source,
            st.status,
            st.submission_date,
            st.verified_by,
            st.verification_date,
            st.created_at,
            st.created_by,
            cs.first_name,
            cs.last_name,
            cs.facility_name,
            cs.department_name,
            cs.cadre_name,
            cs.county_name,
            cs.subcounty_name,
            cs.level_of_care_name,
            cs.staff_phone,
            cs.email,
            0 as facilitator_count
        FROM staff_trainings st
        JOIN county_staff cs ON st.id_number = cs.id_number
        WHERE $where_sql
    ";
}

// Combine with UNION
$combined_query = implode(" UNION ALL ", $union_queries) . " ORDER BY start_date DESC";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($combined_query) as combined";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch paginated results
$paginated_query = $combined_query . " LIMIT ? OFFSET ?";
$stmt = $conn->prepare($paginated_query);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$trainings = $stmt->get_result();

// Get filter dropdown data
$counties = $conn->query("SELECT DISTINCT county_name FROM county_staff ORDER BY county_name");
$statuses = ['draft', 'submitted', 'verified', 'rejected'];

// Get statistics
$stats_query = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN source = 'self' AND status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN source = 'self' AND status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN source = 'self' AND status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN source = 'self' AND status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN source = 'session' THEN 1 ELSE 0 END) as session_trainings
    FROM (
        SELECT 'self' as source, sst.status FROM staff_self_trainings sst
        JOIN county_staff cs ON sst.id_number = cs.id_number
        WHERE $where_sql
        UNION ALL
        SELECT 'session' as source, 'verified' as status FROM staff_trainings st
        JOIN county_staff cs ON st.id_number = cs.id_number
        WHERE $where_sql
    ) as combined_stats
";
$stats_stmt = $conn->prepare($stats_query);
if (!empty($params)) {
    $stats_stmt->bind_param($types, ...$params);
}
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Trainings - View All</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            width: 95%;
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
            margin: 0;
        }

        .header h1 i {
            margin-right: 10px;
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
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: white;
            color: #0D1A63;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,255,255,0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .filter-group input, .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #0D1A63;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #0D1A63;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #0D1A63;
        }

        .stat-card .label {
            font-size: 12px;
            color: #666;
        }

        .stat-card .sub {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .source-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-right: 5px;
        }

        .source-self {
            background: #17a2b8;
            color: white;
        }

        .source-session {
            background: #28a745;
            color: white;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-draft { background: #ffc107; color: #212529; }
        .status-submitted { background: #17a2b8; color: white; }
        .status-verified { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }

        .action-btns {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-view { background: #17a2b8; }
        .btn-print { background: #6c757d; }
        .btn-cert { background: #28a745; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-verify { background: #28a745; }
        .btn-reject { background: #dc3545; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #0D1A63;
            color: white;
            border-color: #0D1A63;
        }

        .pagination .active {
            background: #0D1A63;
            color: white;
            border-color: #0D1A63;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search"></i> Staff Trainings - View All</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="staff_training_form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Training
                </a>
                <a href="training_list.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i> Training Sessions
                </a>
                <a href="training_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Data Source</label>
                        <select name="data_source" class="form-select">
                            <option value="all" <?php echo ($_GET['data_source'] ?? 'all') == 'all' ? 'selected' : ''; ?>>All Sources</option>
                            <option value="self" <?php echo ($_GET['data_source'] ?? '') == 'self' ? 'selected' : ''; ?>>Individual Trainings</option>
                            <option value="session" <?php echo ($_GET['data_source'] ?? '') == 'session' ? 'selected' : ''; ?>>Session Trainings</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Course Name</label>
                        <input type="text" name="course" value="<?php echo htmlspecialchars($_GET['course'] ?? ''); ?>" placeholder="Search course...">
                    </div>

                    <div class="filter-group">
                        <label>County</label>
                        <select name="county" class="form-select">
                            <option value="">All Counties</option>
                            <?php
                            mysqli_data_seek($counties, 0);
                            while ($county = $counties->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($county['county_name']); ?>"
                                    <?php echo ($_GET['county'] ?? '') == $county['county_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($county['county_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Subcounty</label>
                        <input type="text" name="subcounty" value="<?php echo htmlspecialchars($_GET['subcounty'] ?? ''); ?>" placeholder="Subcounty">
                    </div>

                    <div class="filter-group">
                        <label>Facility</label>
                        <input type="text" name="facility" value="<?php echo htmlspecialchars($_GET['facility'] ?? ''); ?>" placeholder="Facility name">
                    </div>

                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo ($_GET['status'] ?? '') == $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary" style="background: #0D1A63; color: white;">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="view_staff_trainings.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <a href="export_trainings.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo ($stats['total'] ?? 0) + ($stats['session_trainings'] ?? 0); ?></div>
                <div class="label">Total Records</div>
                <div class="sub">Self: <?php echo $stats['total'] ?? 0; ?> | Session: <?php echo $stats['session_trainings'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['submitted'] ?? 0; ?></div>
                <div class="label">Submitted</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['verified'] ?? 0; ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['draft'] ?? 0; ?></div>
                <div class="label">Drafts</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <!-- Trainings Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Source</th>
                        <th>Staff Name</th>
                        <th>ID Number</th>
                        <th>Facility</th>
                        <th>County</th>
                        <th>Department</th>
                        <th>Cadre</th>
                        <th>Course</th>
                        <th>Duration</th>
                        <th>Facilitators</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($trainings && $trainings->num_rows > 0): ?>
                        <?php $count = $offset + 1; ?>
                        <?php while ($row = $trainings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td>
                                    <?php if ($row['source'] == 'self'): ?>
                                        <span class="source-badge source-self">Individual</span>
                                    <?php else: ?>
                                        <span class="source-badge source-session">Session</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['facility_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['county_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['cadre_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['course_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($row['training_type'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $start = date('d/m/Y', strtotime($row['start_date']));
                                    $end = date('d/m/Y', strtotime($row['end_date']));
                                    echo $start . ' - ' . $end;
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($row['source'] == 'self') {
                                        echo $row['facilitator_count'] . ' facilitator(s)';
                                    } else {
                                        echo 'Session-based';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['source'] == 'self'): ?>
                                        <span class="status-badge status-<?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-verified">Verified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $row['submission_date'] ? date('d/m/Y', strtotime($row['submission_date'])) : 'N/A'; ?>
                                </td>
                                <td class="action-btns">
                                    <?php if ($row['source'] == 'self'): ?>
                                        <?php if ($row['status'] == 'draft'): ?>
                                            <a href="edit_staff_training.php?id=<?php echo $row['id']; ?>" class="action-btn btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($row['status'] == 'submitted' && in_array($_SESSION['userrole'], ['Admin', 'Training Coordinator', 'HR Manager'])): ?>
                                            <a href="verify_training.php?id=<?php echo $row['id']; ?>&action=verify" class="action-btn btn-verify">
                                                <i class="fas fa-check"></i> Verify
                                            </a>
                                            <a href="verify_training.php?id=<?php echo $row['id']; ?>&action=reject" class="action-btn btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($row['status'] == 'verified'): ?>
                                            <a href="print_certificate.php?id=<?php echo $row['id']; ?>" class="action-btn btn-cert" target="_blank">
                                                <i class="fas fa-certificate"></i> Cert
                                            </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Session trainings are always verified -->
                                            <a href="print_certificate.php?id=<?php echo $row['id']; ?>&source=session" class="action-btn btn-cert" target="_blank">
                                                <i class="fas fa-certificate"></i> Cert
                                            </a>
                                        <?php endif; ?>

                                    <!--<a href="print_training_details.php?id=<?php echo $row['id']; ?>&source=<?php echo $row['source']; ?>" class="action-btn btn-print" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>-->
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 40px;">
                                <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                No training records found
                                <?php if (!empty($_GET)): ?>
                                    <br><a href="view_staff_trainings.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    // Display limited page numbers
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1) echo '<span>...</span>'; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize Select2 for dropdowns
        $('.form-select').select2({
            width: '100%'
        });
    });
    </script>
</body>
</html>