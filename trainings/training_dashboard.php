<?php
// trainings/training_dashboard.php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1); // Remove in production

include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get filter values
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$cadre_filter = $_GET['cadre'] ?? '';
$facility_filter = $_GET['facility'] ?? '';

// Build filter conditions for later use
$filter_conditions = [];
$filter_params = [];
$filter_types = "";

if (!empty($cadre_filter)) {
    $filter_conditions[] = "t.cadre = ?";
    $filter_params[] = $cadre_filter;
    $filter_types .= "s";
}

if (!empty($facility_filter)) {
    $filter_conditions[] = "t.facility_name = ?";
    $filter_params[] = $facility_filter;
    $filter_types .= "s";
}

// Add date range to filter conditions
$date_condition = "t.submission_date BETWEEN ? AND ?";
$date_params = [$date_from, $date_to];
$date_types = "ss";

// Combine all filters for queries that need them
$all_conditions = [];
$all_params = [];
$all_types = "";

$all_conditions[] = $date_condition;
$all_params = array_merge($all_params, $date_params);
$all_types .= $date_types;

if (!empty($filter_conditions)) {
    $all_conditions = array_merge($all_conditions, $filter_conditions);
    $all_params = array_merge($all_params, $filter_params);
    $all_types .= $filter_types;
}

$where_clause = "WHERE " . implode(" AND ", $all_conditions);

// Get unique staff with latest assessments (one per id_number) - FIXED for ONLY_FULL_GROUP_BY
$latest_assessments_query = "
    SELECT t1.tna_id, t1.id_number, t1.created_at as latest_date
    FROM tna_assessments t1
    INNER JOIN (
        SELECT id_number, MAX(created_at) as max_date
        FROM tna_assessments
        WHERE submission_date BETWEEN ? AND ?
        GROUP BY id_number
    ) t2 ON t1.id_number = t2.id_number AND t1.created_at = t2.max_date
";

$stmt = $conn->prepare($latest_assessments_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$latest_result = $stmt->get_result();

$latest_ids = [];
$latest_id_numbers = [];
while ($row = $latest_result->fetch_assoc()) {
    $latest_ids[] = $row['tna_id'];
    $latest_id_numbers[] = $row['id_number'];
}

$latest_ids_str = !empty($latest_ids) ? implode(',', $latest_ids) : '0';
$latest_id_numbers_str = !empty($latest_id_numbers) ? "'" . implode("','", $latest_id_numbers) . "'" : "''";

// Get distinct cadres and facilities for filters
$cadres = $conn->query("SELECT DISTINCT cadre FROM tna_assessments WHERE cadre IS NOT NULL AND cadre != '' ORDER BY cadre");
$facilities = $conn->query("SELECT DISTINCT facility_name FROM tna_assessments WHERE facility_name IS NOT NULL AND facility_name != '' ORDER BY facility_name");

// --- STATISTICS CARDS ---
// Total Assessments (unique staff)
$total_assessments = 0;
$total_query = "SELECT COUNT(DISTINCT id_number) as total FROM tna_assessments t $where_clause";
$stmt = $conn->prepare($total_query);
if (!empty($all_params)) {
    $stmt->bind_param($all_types, ...$all_params);
}
$stmt->execute();
$total_assessments = $stmt->get_result()->fetch_assoc()['total'];

// Total Facilities covered
$facilities_count = 0;
$facilities_query = "SELECT COUNT(DISTINCT facility_name) as total FROM tna_assessments t $where_clause AND facility_name IS NOT NULL AND facility_name != ''";
$stmt = $conn->prepare($facilities_query);
if (!empty($all_params)) {
    $stmt->bind_param($all_types, ...$all_params);
}
$stmt->execute();
$facilities_count = $stmt->get_result()->fetch_assoc()['total'];

// Staff with training needs (those who proposed at least one training)
$staff_with_needs = 0;
$needs_query = "SELECT COUNT(DISTINCT t.id_number) as total
                FROM tna_assessments t
                INNER JOIN tna_proposed_trainings pt ON pt.tna_id = t.tna_id
                $where_clause";
$stmt = $conn->prepare($needs_query);
if (!empty($all_params)) {
    $stmt->bind_param($all_types, ...$all_params);
}
$stmt->execute();
$staff_with_needs = $stmt->get_result()->fetch_assoc()['total'];

// --- PROPOSED TRAININGS ANALYSIS ---
$proposed_trainings_query = "
    SELECT
        pt.area_of_training,
        COUNT(DISTINCT pt.id_number) as staff_count,
        GROUP_CONCAT(DISTINCT pt.duration) as durations,
        GROUP_CONCAT(DISTINCT pt.preferred_year) as preferred_years,
        GROUP_CONCAT(DISTINCT t.cadre) as cadres,
        GROUP_CONCAT(DISTINCT t.facility_name) as facilities
    FROM tna_proposed_trainings pt
    INNER JOIN tna_assessments t ON t.tna_id = pt.tna_id
    WHERE t.tna_id IN ($latest_ids_str)
    AND pt.area_of_training IS NOT NULL
    AND pt.area_of_training != ''
    GROUP BY pt.area_of_training
    ORDER BY staff_count DESC
    LIMIT 20
";

$proposed_trainings = $conn->query($proposed_trainings_query);

// --- COMPETENCES TRAINED ANALYSIS ---
$competences_data = [];
$competences_list = [
    'Research Methods', 'Training Needs Assessment', 'Presentations',
    'Proposal & Report Writing', 'Human Relations Skills', 'Financial Management',
    'Monitoring & Evaluation', 'Leadership & Management', 'Communication',
    'Negotiation Networking', 'Policy Formulation & Implementation', 'Report Writing',
    'Minute Writing', 'Speech Writing', 'Time Management',
    'Negotiation Skills', 'Guidance & Counseling', 'Integrity',
    'Performance Management'
];

foreach ($competences_list as $competence) {
    $query = "
        SELECT COUNT(DISTINCT id_number) as count
        FROM tna_assessments
        WHERE tna_id IN ($latest_ids_str)
        AND competences_trained LIKE ?
    ";
    $like = '%' . $conn->real_escape_string($competence) . '%';
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        $competences_data[$competence] = $count;
    }
}
arsort($competences_data);

// --- CHALLENGE AREAS ANALYSIS ---
$challenge_avg_query = "
    SELECT
        AVG(challenge_knowledge) as avg_knowledge,
        AVG(challenge_equipment) as avg_equipment,
        AVG(challenge_workload) as avg_workload,
        AVG(challenge_motivation) as avg_motivation,
        AVG(challenge_teamwork) as avg_teamwork,
        AVG(challenge_management) as avg_management,
        AVG(challenge_environment) as avg_environment,
        COUNT(DISTINCT id_number) as respondent_count
    FROM tna_assessments
    WHERE tna_id IN ($latest_ids_str)
";

$challenge_avgs = $conn->query($challenge_avg_query)->fetch_assoc();

// --- SKILLS GAP ANALYSIS ---
$skills_gap_query = "
    SELECT
        SUM(CASE WHEN possess_necessary_skills = 'No' THEN 1 ELSE 0 END) as lacking_skills,
        SUM(CASE WHEN possess_technical_skills = 'No' THEN 1 ELSE 0 END) as lacking_technical,
        COUNT(DISTINCT id_number) as total_respondents
    FROM tna_assessments
    WHERE tna_id IN ($latest_ids_str)
";

$skills_gap = $conn->query($skills_gap_query)->fetch_assoc();

// --- TRAINING BY PREFERRED YEAR ---
$training_by_year_query = "
    SELECT
        preferred_year,
        COUNT(*) as training_count,
        COUNT(DISTINCT pt.id_number) as staff_count
    FROM tna_proposed_trainings pt
    INNER JOIN tna_assessments t ON t.tna_id = pt.tna_id
    WHERE t.tna_id IN ($latest_ids_str)
    AND preferred_year IS NOT NULL
    GROUP BY preferred_year
    ORDER BY preferred_year
";

$training_by_year = $conn->query($training_by_year_query);

// --- TOP FACILITIES WITH TRAINING NEEDS ---
$top_facilities_query = "
    SELECT
        t.facility_name,
        COUNT(DISTINCT t.id_number) as staff_count,
        COUNT(pt.proposed_id) as total_trainings
    FROM tna_assessments t
    LEFT JOIN tna_proposed_trainings pt ON pt.tna_id = t.tna_id
    WHERE t.tna_id IN ($latest_ids_str)
    AND t.facility_name IS NOT NULL
    AND t.facility_name != ''
    GROUP BY t.facility_name
    ORDER BY staff_count DESC
    LIMIT 10
";

$top_facilities = $conn->query($top_facilities_query);

// --- TRAINING DURATION ANALYSIS ---
$duration_query = "
    SELECT
        CASE
            WHEN duration LIKE '%day%' OR duration LIKE '%Day%' OR duration LIKE '%days%' OR duration LIKE '%Days%' THEN 'Days'
            WHEN duration LIKE '%week%' OR duration LIKE '%Week%' OR duration LIKE '%weeks%' OR duration LIKE '%Weeks%' THEN 'Weeks'
            WHEN duration LIKE '%month%' OR duration LIKE '%Month%' OR duration LIKE '%months%' OR duration LIKE '%Months%' THEN 'Months'
            WHEN duration LIKE '%year%' OR duration LIKE '%Year%' OR duration LIKE '%years%' OR duration LIKE '%Years%' THEN 'Years'
            ELSE 'Not Specified'
        END as duration_category,
        COUNT(*) as count
    FROM tna_proposed_trainings pt
    INNER JOIN tna_assessments t ON t.tna_id = pt.tna_id
    WHERE t.tna_id IN ($latest_ids_str)
    AND duration IS NOT NULL
    AND duration != ''
    GROUP BY duration_category
    ORDER BY count DESC
";

$duration_analysis = $conn->query($duration_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Needs Assessment Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f3fb;
            padding: 20px;
        }
        .container { max-width: 1600px; margin: 0 auto; }

        .page-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a3a8f 100%);
            color: #fff; padding: 22px 30px; border-radius: 14px;
            margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 8px 24px rgba(13,26,99,.25);
        }
        .page-header h1 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .page-header .hdr-links a {
            color: #fff; text-decoration: none; background: rgba(255,255,255,.15);
            padding: 7px 14px; border-radius: 8px; font-size: 13px; margin-left: 8px;
            transition: background .2s;
        }
        .page-header .hdr-links a:hover { background: rgba(255,255,255,.28); }
        .page-header .hdr-links a.active {
            background: #fff;
            color: #0D1A63;
            font-weight: 600;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #666;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e4f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all .2s;
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        .btn-filter {
            background: #0D1A63;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-filter:hover { background: #1a2a7a; }
        .btn-reset {
            background: #e8ecf5;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reset:hover { background: #d6dceb; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(13,26,99,.1);
        }
        .stat-icon {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #0D1A63, #1a3a8f);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        .stat-content h3 {
            font-size: 13px;
            color: #777;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0D1A63;
            line-height: 1;
        }
        .stat-label {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        /* Chart Grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .chart-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
        }
        .chart-card.full-width {
            grid-column: span 2;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .chart-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0D1A63;
        }
        .chart-header .badge {
            background: #e8edf8;
            color: #0D1A63;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        canvas {
            max-height: 300px;
            width: 100% !important;
        }

        /* Tables */
        .table-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            margin-bottom: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            text-align: left;
            padding: 12px 10px;
            background: #f0f3fb;
            color: #0D1A63;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e8ecf5;
        }
        tr:hover td {
            background: #f8faff;
        }
        .progress-bar {
            background: #e8ecf5;
            height: 8px;
            border-radius: 20px;
            overflow: hidden;
            width: 120px;
        }
        .progress-fill {
            background: #0D1A63;
            height: 100%;
            border-radius: 20px;
        }

        /* Insights Panel */
        .insights-panel {
            background: linear-gradient(135deg, #f6f9ff, #ffffff);
            border-left: 4px solid #0D1A63;
            padding: 18px 22px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .insight-item {
            flex: 1;
            min-width: 200px;
        }
        .insight-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            font-weight: 600;
        }
        .insight-value {
            font-size: 16px;
            font-weight: 700;
            color: #0D1A63;
            margin-top: 4px;
        }

        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .export-btn:hover { background: #218838; }

        .priority-high {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .priority-medium {
            background: #ffc107;
            color: #333;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .priority-low {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> Training Needs Assessment Dashboard</h1>
        <div class="hdr-links">
            <a href="training_needs_assessment_questionaire.php"><i class="fas fa-clipboard-list"></i> New Assessment</a>
            <a href="training_dashboard.php" class="active"><i class="fas fa-chart-bar"></i> Dashboard</a>
            <a href="export_training_needs.php?<?php echo http_build_query($_GET); ?>" class="export-btn" style="background: #28a745;"><i class="fas fa-download"></i> Export</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label><i class="far fa-calendar-alt"></i> From Date</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div class="filter-group">
            <label><i class="far fa-calendar-alt"></i> To Date</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-user-tag"></i> Cadre</label>
            <select name="cadre">
                <option value="">All Cadres</option>
                <?php
                if ($cadres && $cadres->num_rows > 0) {
                    $cadres->data_seek(0);
                    while ($cadre = $cadres->fetch_assoc()):
                ?>
                <option value="<?php echo htmlspecialchars($cadre['cadre']); ?>" <?php echo $cadre_filter == $cadre['cadre'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cadre['cadre']); ?>
                </option>
                <?php
                    endwhile;
                }
                ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-hospital"></i> Facility</label>
            <select name="facility">
                <option value="">All Facilities</option>
                <?php
                if ($facilities && $facilities->num_rows > 0) {
                    $facilities->data_seek(0);
                    while ($facility = $facilities->fetch_assoc()):
                ?>
                <option value="<?php echo htmlspecialchars($facility['facility_name']); ?>" <?php echo $facility_filter == $facility['facility_name'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($facility['facility_name']); ?>
                </option>
                <?php
                    endwhile;
                }
                ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="training_dashboard.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
        </div>
    </form>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <h3>Total Staff Assessed</h3>
                <div class="stat-number"><?php echo number_format($total_assessments); ?></div>
                <div class="stat-label">Unique individuals</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hospital"></i></div>
            <div class="stat-content">
                <h3>Facilities Covered</h3>
                <div class="stat-number"><?php echo number_format($facilities_count); ?></div>
                <div class="stat-label">Health facilities</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="stat-content">
                <h3>Staff with Training Needs</h3>
                <div class="stat-number"><?php echo number_format($staff_with_needs); ?></div>
                <div class="stat-label">Proposed at least 1 training</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <h3>Skills Gap</h3>
                <div class="stat-number">
                    <?php
                    $gap_percent = ($skills_gap['total_respondents'] ?? 0) > 0
                        ? round((($skills_gap['lacking_skills'] ?? 0) / $skills_gap['total_respondents']) * 100)
                        : 0;
                    echo $gap_percent . '%';
                    ?>
                </div>
                <div class="stat-label">Report lacking skills</div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="chart-grid">
        <!-- Top Proposed Trainings -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-bar"></i> Top 10 Proposed Training Areas</h3>
                <span class="badge">Staff Count</span>
            </div>
            <canvas id="trainingsChart"></canvas>
        </div>

        <!-- Core Competences Trained -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-cogs"></i> Core Competences Already Trained</h3>
                <span class="badge">Staff trained</span>
            </div>
            <canvas id="competencesChart"></canvas>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="chart-grid">
        <!-- Challenge Areas (Radar) -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Challenge Areas (Avg Rating 1-5)</h3>
                <span class="badge">Higher = More Challenging</span>
            </div>
            <canvas id="challengesChart"></canvas>
        </div>

        <!-- Training by Preferred Year -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-calendar-alt"></i> Training Demand by Year</h3>
                <span class="badge">Number of trainings</span>
            </div>
            <canvas id="yearChart"></canvas>
        </div>
    </div>

    <!-- Full Width: Training Duration Analysis -->
    <div class="chart-card full-width" style="margin-bottom: 20px;">
        <div class="chart-header">
            <h3><i class="fas fa-clock"></i> Training Duration Preferences</h3>
            <span class="badge">Number of requests</span>
        </div>
        <canvas id="durationChart" style="max-height: 200px;"></canvas>
    </div>

    <!-- Tables Section -->
    <div class="table-card">
        <div class="chart-header">
            <h3><i class="fas fa-list"></i> Detailed Training Needs by Staff</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Training Area</th>
                        <th>Staff Count</th>
                        <th>Cadres</th>
                        <th>Preferred Years</th>
                        <th>Typical Duration</th>
                        <th>Priority</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($proposed_trainings && $proposed_trainings->num_rows > 0) {
                        $proposed_trainings->data_seek(0);
                        $count = 0;
                        while ($training = $proposed_trainings->fetch_assoc()):
                            $count++;
                            if ($count > 15) break;
                            $priority = ($training['staff_count'] ?? 0) >= 10 ? 'High' : (($training['staff_count'] ?? 0) >= 5 ? 'Medium' : 'Low');
                            $priority_class = $priority == 'High' ? 'priority-high' : ($priority == 'Medium' ? 'priority-medium' : 'priority-low');

                            // Get unique cadres
                            $cadre_list = !empty($training['cadres']) ? array_unique(explode(',', $training['cadres'])) : [];
                            $cadre_display = implode(', ', array_slice($cadre_list, 0, 3));
                            if (count($cadre_list) > 3) $cadre_display .= ' ...';

                            // Get unique years
                            $year_list = !empty($training['preferred_years']) ? array_unique(explode(',', $training['preferred_years'])) : [];
                            $year_display = implode(', ', $year_list);

                            // Get sample durations
                            $duration_list = !empty($training['durations']) ? array_unique(explode(',', $training['durations'])) : [];
                            $duration_display = implode(', ', array_slice($duration_list, 0, 2));
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($training['area_of_training'] ?? ''); ?></strong></td>
                        <td><?php echo $training['staff_count'] ?? 0; ?> staff</td>
                        <td><?php echo htmlspecialchars($cadre_display); ?></td>
                        <td><?php echo htmlspecialchars($year_display); ?></td>
                        <td><?php echo htmlspecialchars($duration_display); ?></td>
                        <td><span class="<?php echo $priority_class; ?>"><?php echo $priority; ?> Priority</span></td>
                    </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 30px;">No training needs data available for the selected filters</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Facilities -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-building"></i> Top Facilities by Training Needs</h3>
                <span class="badge">Staff count</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Staff with Needs</th>
                            <th>Total Trainings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($top_facilities && $top_facilities->num_rows > 0) {
                            while ($fac = $top_facilities->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fac['facility_name'] ?? ''); ?></td>
                            <td><?php echo $fac['staff_count'] ?? 0; ?> staff</td>
                            <td><?php echo $fac['total_trainings'] ?? 0; ?> trainings</td>
                        </tr>
                        <?php
                            endwhile;
                        } else {
                            echo '<tr><td colspan="3" style="text-align: center;">No facility data available</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Insights Panel -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-lightbulb"></i> Key Insights for HR</h3>
            </div>
            <div class="insights-panel" style="flex-direction: column; gap: 15px;">
                <?php
                // Get top training
                $top_training = null;
                if ($proposed_trainings && $proposed_trainings->num_rows > 0) {
                    $proposed_trainings->data_seek(0);
                    $top_training = $proposed_trainings->fetch_assoc();
                }

                // Get top competence
                $top_competence = !empty($competences_data) ? array_key_first($competences_data) : null;

                // Find biggest challenge
                $challenges_map = [
                    'avg_knowledge' => 'Inadequate Knowledge',
                    'avg_equipment' => 'Inadequate Equipment',
                    'avg_workload' => 'Heavy Workload',
                    'avg_motivation' => 'Motivation',
                    'avg_teamwork' => 'Teamwork',
                    'avg_management' => 'Management Support',
                    'avg_environment' => 'Conducive Environment'
                ];
                $max_challenge = 0;
                $max_challenge_name = '';
                if ($challenge_avgs) {
                    foreach ($challenge_avgs as $key => $value) {
                        if (strpos($key, 'avg_') === 0 && $value > $max_challenge) {
                            $max_challenge = $value;
                            $max_challenge_name = $key;
                        }
                    }
                }
                ?>
                <div class="insight-item">
                    <div class="insight-label">TOP PRIORITY TRAINING</div>
                    <div class="insight-value">
                        <?php echo htmlspecialchars(substr($top_training['area_of_training'] ?? 'N/A', 0, 50)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        Requested by <?php echo $top_training['staff_count'] ?? 0; ?> staff members
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">MOST COMMON COMPETENCE</div>
                    <div class="insight-value">
                        <?php echo htmlspecialchars($top_competence ?? 'N/A'); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?php echo $competences_data[$top_competence] ?? 0; ?> staff already trained
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">BIGGEST CHALLENGE</div>
                    <div class="insight-value">
                        <?php echo $challenges_map[$max_challenge_name] ?? 'N/A'; ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        Rating: <?php echo number_format($max_challenge, 1); ?>/5.0
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">RECOMMENDATION</div>
                    <div class="insight-value">
                        <?php
                        if (($top_training['staff_count'] ?? 0) > 10) {
                            echo 'Schedule ' . htmlspecialchars(substr($top_training['area_of_training'] ?? 'priority', 0, 30)) . ' training';
                        } elseif ($gap_percent > 50) {
                            echo 'Conduct skills gap analysis workshop';
                        } else {
                            echo 'Develop annual training calendar';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data last updated -->
    <div style="text-align: right; margin-top: 20px; color: #999; font-size: 12px;">
        <i class="far fa-clock"></i> Data last updated: <?php echo date('d M Y H:i'); ?>
        | Showing latest assessment per staff member
    </div>
</div>

<script>
// Prepare data for charts
<?php
// Training areas data
$training_labels = [];
$training_counts = [];
if ($proposed_trainings && $proposed_trainings->num_rows > 0) {
    $proposed_trainings->data_seek(0);
    $counter = 0;
    while ($row = $proposed_trainings->fetch_assoc()) {
        if ($counter++ < 10) {
            $training_labels[] = substr($row['area_of_training'] ?? '', 0, 30) . (strlen($row['area_of_training'] ?? '') > 30 ? '...' : '');
            $training_counts[] = $row['staff_count'] ?? 0;
        }
    }
}

// Competences data
$comp_labels = array_keys(array_slice($competences_data, 0, 8));
$comp_counts = array_values(array_slice($competences_data, 0, 8));

// Challenge data
$challenge_labels = ['Knowledge', 'Equipment', 'Workload', 'Motivation', 'Teamwork', 'Management', 'Environment'];
$challenge_values = [
    round($challenge_avgs['avg_knowledge'] ?? 0, 1),
    round($challenge_avgs['avg_equipment'] ?? 0, 1),
    round($challenge_avgs['avg_workload'] ?? 0, 1),
    round($challenge_avgs['avg_motivation'] ?? 0, 1),
    round($challenge_avgs['avg_teamwork'] ?? 0, 1),
    round($challenge_avgs['avg_management'] ?? 0, 1),
    round($challenge_avgs['avg_environment'] ?? 0, 1)
];

// Year data
$year_labels = [];
$year_counts = [];
if ($training_by_year && $training_by_year->num_rows > 0) {
    $training_by_year->data_seek(0);
    while ($row = $training_by_year->fetch_assoc()) {
        $year_labels[] = $row['preferred_year'] ?? '';
        $year_counts[] = $row['training_count'] ?? 0;
    }
}

// Duration data
$duration_labels = [];
$duration_counts = [];
if ($duration_analysis && $duration_analysis->num_rows > 0) {
    $duration_analysis->data_seek(0);
    while ($row = $duration_analysis->fetch_assoc()) {
        $duration_labels[] = $row['duration_category'] ?? '';
        $duration_counts[] = $row['count'] ?? 0;
    }
}
?>

// Chart 1: Top Trainings
new Chart(document.getElementById('trainingsChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($training_labels); ?>,
        datasets: [{
            label: 'Number of Staff',
            data: <?php echo json_encode($training_counts); ?>,
            backgroundColor: '#0D1A63',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Chart 2: Competences
if (<?php echo count($comp_labels); ?> > 0) {
    new Chart(document.getElementById('competencesChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($comp_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($comp_counts); ?>,
                backgroundColor: [
                    '#0D1A63', '#1a3a8f', '#2a4ab0', '#3a5ac8',
                    '#4a6ae0', '#5a7af8', '#6a8aff', '#7a9aff'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
} else {
    document.getElementById('competencesChart').parentNode.innerHTML += '<p style="text-align: center; color: #999;">No competence data available</p>';
}

// Chart 3: Challenges (Radar)
new Chart(document.getElementById('challengesChart'), {
    type: 'radar',
    data: {
        labels: <?php echo json_encode($challenge_labels); ?>,
        datasets: [{
            label: 'Challenge Level (1-5)',
            data: <?php echo json_encode($challenge_values); ?>,
            backgroundColor: 'rgba(13, 26, 99, 0.2)',
            borderColor: '#0D1A63',
            pointBackgroundColor: '#0D1A63',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#0D1A63'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                max: 5,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Chart 4: Year Distribution
if (<?php echo count($year_labels); ?> > 0) {
    new Chart(document.getElementById('yearChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($year_labels); ?>,
            datasets: [{
                label: 'Number of Trainings',
                data: <?php echo json_encode($year_counts); ?>,
                borderColor: '#0D1A63',
                backgroundColor: 'rgba(13, 26, 99, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
} else {
    document.getElementById('yearChart').parentNode.innerHTML += '<p style="text-align: center; color: #999;">No year data available</p>';
}

// Chart 5: Duration Analysis
if (<?php echo count($duration_labels); ?> > 0) {
    new Chart(document.getElementById('durationChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($duration_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($duration_counts); ?>,
                backgroundColor: ['#0D1A63', '#1a3a8f', '#2a4ab0', '#3a5ac8', '#4a6ae0']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
} else {
    document.getElementById('durationChart').parentNode.innerHTML += '<p style="text-align: center; color: #999;">No duration data available</p>';
}
</script>
</body>
</html>