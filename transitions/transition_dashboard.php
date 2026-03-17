<?php
// transitions/transition_dashboard.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$county_id = isset($_GET['county_id']) ? (int)$_GET['county_id'] : 0;
$period = isset($_GET['period']) ? mysqli_real_escape_string($conn, $_GET['period']) : '';

// Get latest assessment for each county
$latest_assessments = [];
$query = "
    SELECT ta.*, c.county_name,
           ts.section_code, ts.section_name,
           AVG(tsc.cdoh_percentage) as avg_cdoh,
           AVG(tsc.ip_percentage) as avg_ip,
           AVG(tsc.gap_score) as avg_gap,
           AVG(tsc.overlap_score) as avg_overlap,
           COUNT(DISTINCT tsc.indicator_id) as indicators_count
    FROM transition_assessments ta
    JOIN counties c ON ta.county_id = c.county_id
    JOIN transition_scores tsc ON ta.assessment_id = tsc.assessment_id
    JOIN transition_indicators ti ON tsc.indicator_id = ti.indicator_id
    JOIN transition_sections ts ON ti.section_id = ts.section_id
    WHERE ta.assessment_status = 'submitted'
    GROUP BY ta.assessment_id, ts.section_id
    ORDER BY ta.assessment_date DESC
";

$result = mysqli_query($conn, $query);
$dashboard_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dashboard_data[$row['county_name']]['sections'][$row['section_code']] = $row;
}

// Calculate overall readiness levels
foreach ($dashboard_data as $county => &$data) {
    $total_cdoh = 0;
    $total_max = 0;
    $sections_count = 0;

    foreach ($data['sections'] as $section) {
        $total_cdoh += $section['avg_cdoh'];
        $sections_count++;
    }

    $avg_cdoh_percentage = $sections_count > 0 ? $total_cdoh / $sections_count : 0;

    // Determine readiness level
    if ($avg_cdoh_percentage >= 70) {
        $data['readiness'] = 'Transition';
        $data['readiness_color'] = '#28a745'; // Green
    } elseif ($avg_cdoh_percentage >= 50) {
        $data['readiness'] = 'Support and Monitor';
        $data['readiness_color'] = '#ffc107'; // Yellow/Orange
    } else {
        $data['readiness'] = 'Not Ready';
        $data['readiness_color'] = '#dc3545'; // Red
    }

    $data['avg_cdoh'] = round($avg_cdoh_percentage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transition Benchmarking Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f7;
            color: #333;
            line-height: 1.6;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 20px; }

        .page-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a3a9e 100%);
            color: #fff;
            padding: 22px 30px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 24px rgba(13,26,99,.25);
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header .hdr-links a {
            color: #fff;
            text-decoration: none;
            background: rgba(255,255,255,.15);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-left: 8px;
            transition: background .2s;
        }
        .page-header .hdr-links a:hover {
            background: rgba(255,255,255,.28);
        }

        /* Filter Bar */
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
            box-shadow: 0 2px 14px rgba(0,0,0,.07);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e4f0;
            border-radius: 8px;
            font-size: 13px;
        }
        .btn-filter {
            background: #0D1A63;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.05);
            border-left: 4px solid var(--color);
        }
        .summary-title {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .summary-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--color);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Sidebar - Section List */
        .section-sidebar {
            background: #fff;
            border-radius: 14px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,.05);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .section-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e8ecf5;
            cursor: pointer;
            transition: all .2s;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-item:hover {
            background: #f0f3fb;
        }
        .section-item.active {
            background: #0D1A63;
            color: #fff;
            border-radius: 8px;
        }
        .section-item .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Main Chart Area */
        .chart-container {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-title {
            font-size: 16px;
            font-weight: 700;
            color: #0D1A63;
        }
        .legend {
            display: flex;
            gap: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        .legend-color.cdoh { background: #0D1A63; }
        .legend-color.ip { background: #FFC107; }
        .legend-color.gap { background: #DC3545; }
        .legend-color.overlap { background: #FD7E14; }

        /* Bar Chart Customization */
        .bar-container {
            margin-top: 30px;
        }
        .bar-item {
            margin-bottom: 15px;
        }
        .bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .bar-track {
            height: 30px;
            background: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
        }
        .bar-segment {
            height: 100%;
            transition: width 0.3s;
        }
        .bar-segment.cdoh { background: #0D1A63; }
        .bar-segment.ip { background: #FFC107; }
        .bar-segment.overlap { background: #FD7E14; }
        .bar-segment.gap { background: #DC3545; }

        /* Readiness Badges */
        .readiness-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-transition { background: #d4edda; color: #155724; }
        .badge-support { background: #fff3cd; color: #856404; }
        .badge-not-ready { background: #f8d7da; color: #721c24; }

        /* County Selector */
        .county-selector {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            Transition Benchmarking Dashboard
        </h1>
        <div class="hdr-links">
            <a href="transition_index.php"><i class="fas fa-plus"></i> New Assessment</a>
            <a href="transition_reports.php"><i class="fas fa-download"></i> Reports</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <div class="filter-group">
            <label>County</label>
            <select id="countySelect">
                <option value="">All Counties</option>
                <?php foreach (array_keys($dashboard_data) as $county): ?>
                <option value="<?= htmlspecialchars($county) ?>"><?= htmlspecialchars($county) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Assessment Period</label>
            <select id="periodSelect">
                <option value="">Latest</option>
                <option value="Q1 2025">Q1 2025</option>
                <option value="Q2 2025">Q2 2025</option>
                <option value="Q3 2025">Q3 2025</option>
                <option value="Q4 2025">Q4 2025</option>
            </select>
        </div>
        <button class="btn-filter" onclick="applyFilters()">Apply Filters</button>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card" style="--color: #28a745;">
            <div class="summary-title">Counties in Transition</div>
            <div class="summary-value" id="transitionCount">0</div>
        </div>
        <div class="summary-card" style="--color: #ffc107;">
            <div class="summary-title">Counties Needing Support</div>
            <div class="summary-value" id="supportCount">0</div>
        </div>
        <div class="summary-card" style="--color: #dc3545;">
            <div class="summary-title">Counties Not Ready</div>
            <div class="summary-value" id="notReadyCount">0</div>
        </div>
        <div class="summary-card" style="--color: #0D1A63;">
            <div class="summary-title">Average CDOH Score</div>
            <div class="summary-value" id="avgScore">0%</div>
        </div>
    </div>

    <!-- Main Dashboard -->
    <div class="dashboard-grid">
        <!-- Sidebar with sections -->
        <div class="section-sidebar">
            <h3 style="padding: 15px; font-size: 14px; color: #0D1A63;">
                <i class="fas fa-layer-group"></i> Assessment Areas
            </h3>
            <div id="sectionList">
                <?php
                $sections = [
                    'County Health Leadership and Governance',
                    'County Executive (CHMT)',
                    'County Health Planning',
                    'HIV/TB Routine Supervision',
                    'HIV/TB Special Initiatives',
                    'HIV/TB Quality Improvement',
                    'HIV/TB Patient Identification',
                    'HIV/TB Patient Retention',
                    'HIV/TB Prevention & KP',
                    'HIV/TB Financial Management',
                    'HIV/TB Sub-Grants',
                    'HIV/TB Commodities',
                    'HIV/TB Equipment',
                    'HIV/TB Laboratory Services',
                    'HIV/TB Inventory Management',
                    'HIV/TB In-service Training',
                    'HIV/TB Human Resources',
                    'HIV/TB Data Management',
                    'HIV/TB Patient Monitoring',
                    'Operationalization of HIV Plan',
                    'Coordination of Services',
                    'Congruence of Expectations'
                ];
                foreach ($sections as $index => $section):
                ?>
                <div class="section-item" onclick="showSection(<?= $index ?>)">
                    <span class="indicator" style="background: #0D1A63;"></span>
                    <?= $section ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Chart Area -->
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title" id="selectedSection">County Health Leadership and Governance</div>
                <div class="legend">
                    <div class="legend-item">
                        <span class="legend-color cdoh"></span>
                        <span>CDOH (County)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color ip"></span>
                        <span>IP Involvement</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color overlap"></span>
                        <span>Overlap</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color gap"></span>
                        <span>Gap</span>
                    </div>
                </div>
            </div>

            <!-- Canvas for Chart.js -->
            <canvas id="mainChart" style="height: 400px;"></canvas>

            <!-- Bar Chart Representation -->
            <div class="bar-container" id="barChart">
                <!-- Dynamic bars will be inserted here -->
            </div>

            <!-- Readiness Indicator -->
            <div style="margin-top: 30px; padding: 20px; background: #f8f9fc; border-radius: 10px;">
                <h4 style="margin-bottom: 15px; color: #0D1A63;">County Readiness Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div>
                        <div style="font-size: 12px; color: #666;">CDOH Score</div>
                        <div style="font-size: 24px; font-weight: 800; color: #0D1A63;" id="cdohScore">0%</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #666;">IP Involvement</div>
                        <div style="font-size: 24px; font-weight: 800; color: #FFC107;" id="ipScore">0%</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #666;">Readiness Level</div>
                        <div style="font-size: 18px; font-weight: 700;">
                            <span class="readiness-badge" id="readinessBadge">Not Ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sample data - Replace with actual data from PHP
const sectionData = [
    {
        name: 'County Health Leadership and Governance',
        cdoh: 65,
        ip: 85,
        gap: 20,
        overlap: 65,
        indicators: [
            { name: 'T1.1 - Legal mechanism', cdoh: 3, ip: 4 },
            { name: 'T1.2 - Vision statement', cdoh: 2, ip: 4 },
            { name: 'T1.3 - Defined roles', cdoh: 3, ip: 3 },
            { name: 'T1.4 - Regular meetings', cdoh: 4, ip: 2 },
            { name: 'T1.5 - Committee composition', cdoh: 2, ip: 4 }
        ]
    },
    // Add more sections...
];

let currentSection = 0;
let chart;

function showSection(index) {
    currentSection = index;

    // Update active class in sidebar
    document.querySelectorAll('.section-item').forEach((item, i) => {
        if (i === index) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Update title
    document.getElementById('selectedSection').textContent = sectionData[index].name;

    // Update scores
    const data = sectionData[index];
    document.getElementById('cdohScore').textContent = data.cdoh + '%';
    document.getElementById('ipScore').textContent = data.ip + '%';

    // Update readiness badge
    const readinessBadge = document.getElementById('readinessBadge');
    if (data.cdoh >= 70) {
        readinessBadge.textContent = 'Transition';
        readinessBadge.className = 'readiness-badge badge-transition';
    } else if (data.cdoh >= 50) {
        readinessBadge.textContent = 'Support and Monitor';
        readinessBadge.className = 'readiness-badge badge-support';
    } else {
        readinessBadge.textContent = 'Not Ready';
        readinessBadge.className = 'readiness-badge badge-not-ready';
    }

    // Update bar chart
    updateBarChart(data);

    // Update main chart
    updateMainChart(data);
}

function updateBarChart(data) {
    const container = document.getElementById('barChart');
    let html = '<h4 style="margin-bottom: 15px;">Detailed Indicator Breakdown</h4>';

    data.indicators.forEach(indicator => {
        const cdohWidth = (indicator.cdoh / 4) * 100;
        const ipWidth = (indicator.ip / 4) * 100;
        const overlapWidth = Math.min(cdohWidth, ipWidth);
        const gapWidth = Math.max(0, ipWidth - cdohWidth);

        html += `
            <div class="bar-item">
                <div class="bar-label">
                    <span>${indicator.name}</span>
                    <span>CDOH: ${indicator.cdoh}/4 | IP: ${indicator.ip}/4</span>
                </div>
                <div class="bar-track">
                    <div class="bar-segment cdoh" style="width: ${cdohWidth}%;"></div>
                    <div class="bar-segment gap" style="width: ${gapWidth}%;"></div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function updateMainChart(data) {
    const ctx = document.getElementById('mainChart').getContext('2d');

    if (chart) {
        chart.destroy();
    }

    chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['CDOH (County)', 'IP Involvement', 'Overlap', 'Gap'],
            datasets: [{
                data: [data.cdoh, data.ip, data.overlap, data.gap],
                backgroundColor: ['#0D1A63', '#FFC107', '#FD7E14', '#DC3545'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => ` ${c.raw}%` } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: '#f0f0f0' },
                    ticks: { callback: v => v + '%' }
                }
            }
        }
    });
}

function applyFilters() {
    // Implement filter logic
    alert('Filters applied - would refresh data based on selections');
}

// Initialize with first section
document.addEventListener('DOMContentLoaded', () => {
    showSection(0);

    // Calculate summary stats
    document.getElementById('transitionCount').textContent = '5';
    document.getElementById('supportCount').textContent = '8';
    document.getElementById('notReadyCount').textContent = '3';
    document.getElementById('avgScore').textContent = '62%';
});
</script>
</body>
</html>