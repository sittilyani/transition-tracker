<?php
// transitions/transition_dashboard.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$county_id = isset($_GET['county']) ? (int)$_GET['county'] : 0;
$period    = isset($_GET['period']) ? mysqli_real_escape_string($conn, $_GET['period']) : '';

// ── Filter options ────────────────────────────────────────────────────────────
$counties_list = [];
$cr = mysqli_query($conn, "SELECT county_id, county_name FROM counties ORDER BY county_name");
if ($cr) while ($r = mysqli_fetch_assoc($cr)) $counties_list[] = $r;

$periods_list = [];
$pr = mysqli_query($conn,
    "SELECT DISTINCT assessment_period FROM transition_assessments ORDER BY assessment_period DESC");
if ($pr) while ($r = mysqli_fetch_assoc($pr)) $periods_list[] = $r['assessment_period'];

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where = "WHERE ta.assessment_status IN ('submitted','draft')";
if ($county_id) $where .= " AND ta.county_id=$county_id";
if ($period)    $where .= " AND ta.assessment_period='$period'";

// ── Fetch assessments with section-level averages ────────────────────────────
// Per indicator_code (T1, T4A, T4B…) per assessment
$raw_query = "
    SELECT
        ta.assessment_id,
        ta.county_id,
        c.county_name,
        ta.assessment_period,
        ta.assessment_date,
        ta.readiness_level,
        ta.overall_cdoh_score,
        ta.overall_ip_score,
        ta.assessed_by,
        rs.section_key,
        rs.indicator_code,
        COUNT(rs.raw_score_id)                         AS sub_count,
        ROUND(AVG(rs.cdoh_score),2)                    AS avg_cdoh_raw,
        ROUND(AVG(rs.ip_score),2)                      AS avg_ip_raw,
        ROUND(AVG(rs.cdoh_score)/4*100,1)              AS cdoh_pct,
        ROUND(AVG(rs.ip_score)/4*100,1)                AS ip_pct,
        SUM(CASE WHEN rs.cdoh_score=4 THEN 1 ELSE 0 END) AS s4,
        SUM(CASE WHEN rs.cdoh_score=3 THEN 1 ELSE 0 END) AS s3,
        SUM(CASE WHEN rs.cdoh_score=2 THEN 1 ELSE 0 END) AS s2,
        SUM(CASE WHEN rs.cdoh_score=1 THEN 1 ELSE 0 END) AS s1,
        SUM(CASE WHEN rs.cdoh_score=0 THEN 1 ELSE 0 END) AS s0
    FROM transition_assessments ta
    JOIN counties c ON c.county_id = ta.county_id
    JOIN transition_raw_scores rs ON rs.assessment_id = ta.assessment_id
    $where
    GROUP BY ta.assessment_id, rs.section_key, rs.indicator_code
    ORDER BY ta.assessment_date DESC, rs.section_key, rs.indicator_code
";

$result = mysqli_query($conn, $raw_query);

// Build nested structure: [county_name][assessment_id][section_key][indicator_code]
$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cn  = $row['county_name'];
        $aid = $row['assessment_id'];
        $sk  = $row['section_key'];
        $ic  = $row['indicator_code'];

        if (!isset($data[$cn])) {
            $data[$cn] = [
                'county_id'       => $row['county_id'],
                'assessments'     => [],
            ];
        }
        if (!isset($data[$cn]['assessments'][$aid])) {
            $data[$cn]['assessments'][$aid] = [
                'assessment_id'    => $aid,
                'period'           => $row['assessment_period'],
                'date'             => $row['assessment_date'],
                'readiness'        => $row['readiness_level'],
                'overall_cdoh'     => $row['overall_cdoh_score'],
                'overall_ip'       => $row['overall_ip_score'],
                'assessed_by'      => $row['assessed_by'],
                'sections'         => [],
            ];
        }
        if (!isset($data[$cn]['assessments'][$aid]['sections'][$sk])) {
            $data[$cn]['assessments'][$aid]['sections'][$sk] = [];
        }
        $data[$cn]['assessments'][$aid]['sections'][$sk][$ic] = $row;
    }
}

// ── Summary KPIs ──────────────────────────────────────────────────────────────
$total_assessments = 0;
$transition_count = 0; $support_count = 0; $not_ready_count = 0;
$all_cdoh_scores = [];

foreach ($data as $county => $cd) {
    foreach ($cd['assessments'] as $aid => $asmnt) {
        $total_assessments++;
        if ($asmnt['overall_cdoh'] >= 70) $transition_count++;
        elseif ($asmnt['overall_cdoh'] >= 50) $support_count++;
        else $not_ready_count++;
        if ($asmnt['overall_cdoh']) $all_cdoh_scores[] = $asmnt['overall_cdoh'];
    }
}
$avg_overall = count($all_cdoh_scores) > 0 ? round(array_sum($all_cdoh_scores) / count($all_cdoh_scores)) : 0;

// ── Section labels map ─────────────────────────────────────────────────────────
$section_labels = [
    'leadership'             => 'Leadership & Governance',
    'supervision'            => 'Supervision & Mentorship',
    'special_initiatives'    => 'Special Initiatives',
    'quality_improvement'    => 'Quality Improvement',
    'identification_linkage' => 'Patient Identification',
    'retention_suppression'  => 'Patient Retention',
    'prevention_kp'          => 'Prevention & KP',
    'finance'                => 'Finance Management',
    'sub_grants'             => 'Sub-Grants',
    'commodities'            => 'Commodities Management',
    'equipment'              => 'Equipment Procurement',
    'laboratory'             => 'Laboratory Services',
    'inventory'              => 'Inventory Management',
    'training'               => 'In-Service Training',
    'hr_management'          => 'HR Management',
    'data_management'        => 'Data Management',
    'patient_monitoring'     => 'Patient Monitoring',
    'institution_ownership'  => 'Institutional Ownership',
];

$section_colors = [
    '#0D1A63','#1a3a9e','#2a4ab0','#3a5ac8','#4a6ae0','#5a7af8',
    '#6a8aff','#7a9aff','#8a5cf6','#9b6cf6','#ac7cf6','#bd8cf6',
    '#ce9cf6','#dfacf6','#f0bcf6','#0ABFBC','#27AE60','#F5A623',
];

// ── Build chart datasets for stacked bar (score distribution 0-4) ─────────────
// For each county's latest assessment, build a stacked bar per section
$chart_data_all = [];
foreach ($data as $county => $cd) {
    $latest_aid = array_key_first($cd['assessments']);
    if (!$latest_aid) continue;
    $asmnt = $cd['assessments'][$latest_aid];

    $section_stacks = []; // [section_key => [s0,s1,s2,s3,s4,total,cdoh_pct,ip_pct]]
    foreach ($asmnt['sections'] as $sk => $indicators) {
        $s = [0=>0,1=>0,2=>0,3=>0,4=>0,'total'=>0,'cdoh_pct'=>0,'ip_pct'=>0];
        foreach ($indicators as $ic => $row) {
            $s[0] += (int)$row['s0'];
            $s[1] += (int)$row['s1'];
            $s[2] += (int)$row['s2'];
            $s[3] += (int)$row['s3'];
            $s[4] += (int)$row['s4'];
            $s['total']    += (int)$row['sub_count'];
            $s['cdoh_pct'] += (float)$row['cdoh_pct'];
            $s['ip_pct']   += (float)$row['ip_pct'];
        }
        $ind_count = count($indicators);
        $s['cdoh_pct'] = $ind_count > 0 ? round($s['cdoh_pct'] / $ind_count, 1) : 0;
        $s['ip_pct']   = $ind_count > 0 ? round($s['ip_pct']   / $ind_count, 1) : 0;
        $section_stacks[$sk] = $s;
    }
    $chart_data_all[$county] = [
        'assessment' => $asmnt,
        'sections'   => $section_stacks,
    ];
}

// ── JSON for charts ───────────────────────────────────────────────────────────
// 1. Stacked bar chart: score level distribution per section (for selected county/first county)
$first_county = array_key_first($chart_data_all);
$chart_sections_labels = [];
$chart_stack_s0 = []; $chart_stack_s1 = []; $chart_stack_s2 = [];
$chart_stack_s3 = []; $chart_stack_s4 = [];
$chart_cdoh_line = []; $chart_ip_line = [];

if ($first_county && !empty($chart_data_all[$first_county]['sections'])) {
    foreach ($chart_data_all[$first_county]['sections'] as $sk => $s) {
        $lbl = $section_labels[$sk] ?? $sk;
        $chart_sections_labels[] = $lbl;
        $total = $s['total'] ?: 1;
        $chart_stack_s0[] = round($s[0] / $total * 100, 1);
        $chart_stack_s1[] = round($s[1] / $total * 100, 1);
        $chart_stack_s2[] = round($s[2] / $total * 100, 1);
        $chart_stack_s3[] = round($s[3] / $total * 100, 1);
        $chart_stack_s4[] = round($s[4] / $total * 100, 1);
        $chart_cdoh_line[] = $s['cdoh_pct'];
        $chart_ip_line[]   = $s['ip_pct'];
    }
}

$chart_sections_labels_json = json_encode($chart_sections_labels);
$chart_s0_json = json_encode($chart_stack_s0);
$chart_s1_json = json_encode($chart_stack_s1);
$chart_s2_json = json_encode($chart_stack_s2);
$chart_s3_json = json_encode($chart_stack_s3);
$chart_s4_json = json_encode($chart_stack_s4);
$chart_cdoh_json = json_encode($chart_cdoh_line);
$chart_ip_json   = json_encode($chart_ip_line);

// 2. County comparison bar chart (overall cdoh %)
$cmp_labels = [];
$cmp_cdoh   = [];
$cmp_ip     = [];
$cmp_colors = [];
$ci = 0;
foreach ($chart_data_all as $county => $d) {
    $cmp_labels[] = $county;
    $cmp_cdoh[]   = $d['assessment']['overall_cdoh'] ?? 0;
    $cmp_ip[]     = $d['assessment']['overall_ip']   ?? 0;
    $cmp_colors[] = $d['assessment']['overall_cdoh'] >= 70 ? '#28a745'
                  : ($d['assessment']['overall_cdoh'] >= 50 ? '#ffc107' : '#dc3545');
    $ci++;
}
$cmp_labels_json  = json_encode($cmp_labels);
$cmp_cdoh_json    = json_encode($cmp_cdoh);
$cmp_ip_json      = json_encode($cmp_ip);
$cmp_colors_json  = json_encode($cmp_colors);

// All counties' section-level data for dynamic chart update
$all_county_chart_data = [];
foreach ($chart_data_all as $county => $d) {
    $entry = ['assessment' => $d['assessment'], 'sections' => []];
    foreach ($d['sections'] as $sk => $s) {
        $total = $s['total'] ?: 1;
        $entry['sections'][] = [
            'label'    => $section_labels[$sk] ?? $sk,
            'key'      => $sk,
            's0_pct'   => round($s[0] / $total * 100, 1),
            's1_pct'   => round($s[1] / $total * 100, 1),
            's2_pct'   => round($s[2] / $total * 100, 1),
            's3_pct'   => round($s[3] / $total * 100, 1),
            's4_pct'   => round($s[4] / $total * 100, 1),
            'cdoh_pct' => $s['cdoh_pct'],
            'ip_pct'   => $s['ip_pct'],
            'total'    => $s['total'],
        ];
    }
    $all_county_chart_data[$county] = $entry;
}
$all_county_json = json_encode($all_county_chart_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transition Assessment Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f2f7;color:#333;line-height:1.6;}
.container{max-width:1600px;margin:0 auto;padding:20px;}
.page-header{background:linear-gradient(135deg,#0D1A63 0%,#1a3a9e 100%);color:#fff;padding:22px 30px;border-radius:14px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 6px 24px rgba(13,26,99,.25);}
.page-header h1{font-size:1.8rem;font-weight:700;display:flex;align-items:center;gap:10px;}
.page-header .hdr-links a{color:#fff;text-decoration:none;background:rgba(255,255,255,.15);padding:8px 16px;border-radius:8px;font-size:13px;margin-left:8px;transition:background .2s;}
.page-header .hdr-links a:hover{background:rgba(255,255,255,.28);}

.filter-bar{background:#fff;border-radius:12px;padding:18px 22px;margin-bottom:24px;box-shadow:0 2px 14px rgba(0,0,0,.07);display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end;}
.filter-group{flex:1;min-width:200px;}
.filter-group label{display:block;font-size:11px;font-weight:700;color:#666;margin-bottom:5px;text-transform:uppercase;}
.filter-group select{width:100%;padding:10px 12px;border:2px solid #e0e4f0;border-radius:8px;font-size:13px;}
.btn-filter{background:#0D1A63;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-weight:600;cursor:pointer;}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;margin-bottom:28px;}
.kpi-card{background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.05);border-top:4px solid var(--kc);}
.kpi-val{font-size:38px;font-weight:900;color:var(--kc);line-height:1;}
.kpi-lbl{font-size:13px;color:#666;margin-top:6px;font-weight:500;}

.section-title{font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#888;margin:24px 0 14px;display:flex;align-items:center;gap:10px;}
.section-title::after{content:'';flex:1;height:1px;background:#e0e4f0;}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.card{background:#fff;border-radius:14px;padding:0;box-shadow:0 4px 20px rgba(0,0,0,.05);overflow:hidden;}
.card-head{padding:16px 20px 14px;border-bottom:1px solid #e8ecf5;display:flex;align-items:center;justify-content:space-between;}
.card-head h3{font-size:15px;font-weight:700;color:#0D1A63;display:flex;align-items:center;gap:8px;}
.card-body{padding:20px;}

.county-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
.county-tab{padding:7px 16px;background:#fff;border:2px solid #e0e4f0;border-radius:30px;font-size:13px;font-weight:600;color:#666;cursor:pointer;transition:all .2s;}
.county-tab.active{background:#0D1A63;color:#fff;border-color:#0D1A63;}

.readiness-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;}
.badge-transition{background:#d4edda;color:#155724;}
.badge-support{background:#fff3cd;color:#856404;}
.badge-not-ready{background:#f8d7da;color:#721c24;}

.legend{display:flex;gap:16px;flex-wrap:wrap;}
.legend-item{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;}
.legend-dot{width:12px;height:12px;border-radius:3px;}

.detail-table{width:100%;border-collapse:collapse;font-size:12px;}
.detail-table th{background:#f8fafc;padding:9px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#888;border-bottom:1px solid #e8ecf5;}
.detail-table td{padding:9px 12px;border-bottom:1px solid #e8ecf5;vertical-align:middle;}
.detail-table tr:last-child td{border-bottom:none;}
.detail-table tr:hover td{background:#f8fafc;}
.bar-inline{display:flex;align-items:center;gap:6px;}
.bar-inline-track{flex:1;height:8px;background:#e8ecf5;border-radius:99px;overflow:hidden;}
.bar-inline-fill{height:100%;border-radius:99px;}

.no-data{text-align:center;padding:60px 20px;color:#aaa;}
.no-data i{font-size:48px;margin-bottom:16px;}
</style>
</head>
<body>
<div class="container">

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Transition Assessment Dashboard</h1>
    <div class="hdr-links">
        <a href="transition_index.php"><i class="fas fa-plus"></i> New Assessment</a>
        <a href="transition_index.php"><i class="fas fa-home"></i> Home</a>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <div class="filter-group">
        <label>County</label>
        <select name="county">
            <option value="">All Counties</option>
            <?php foreach ($counties_list as $c): ?>
            <option value="<?= $c['county_id'] ?>" <?= $county_id == $c['county_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['county_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Assessment Period</label>
        <select name="period">
            <option value="">All Periods</option>
            <?php foreach ($periods_list as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= $period === $p ? 'selected' : '' ?>>
                <?= htmlspecialchars($p) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
    <a href="transition_dashboard.php" style="background:#e0e4f0;color:#333;border:none;padding:10px 18px;border-radius:8px;font-weight:600;text-decoration:none;font-size:13px;">
        <i class="fas fa-times"></i> Clear</a>
</form>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card" style="--kc:#0D1A63">
        <div class="kpi-val"><?= $total_assessments ?></div>
        <div class="kpi-lbl"><i class="fas fa-clipboard-check"></i> Total Assessments</div>
    </div>
    <div class="kpi-card" style="--kc:#28a745">
        <div class="kpi-val"><?= $transition_count ?></div>
        <div class="kpi-lbl"><i class="fas fa-check-circle"></i> Ready to Transition</div>
    </div>
    <div class="kpi-card" style="--kc:#ffc107">
        <div class="kpi-val"><?= $support_count ?></div>
        <div class="kpi-lbl"><i class="fas fa-tools"></i> Support &amp; Monitor</div>
    </div>
    <div class="kpi-card" style="--kc:#dc3545">
        <div class="kpi-val"><?= $not_ready_count ?></div>
        <div class="kpi-lbl"><i class="fas fa-exclamation-triangle"></i> Not Ready</div>
    </div>
    <div class="kpi-card" style="--kc:#0ABFBC">
        <div class="kpi-val"><?= $avg_overall ?>%</div>
        <div class="kpi-lbl"><i class="fas fa-percentage"></i> Avg CDOH Score</div>
    </div>
</div>

<?php if (empty($data)): ?>
<div class="no-data">
    <i class="fas fa-chart-bar"></i>
    <p>No assessment data found. <a href="transition_index.php">Start an assessment</a>.</p>
</div>
<?php else: ?>

<!-- ── County tabs ─────────────────────────────────────────────────────── -->
<div class="section-title"><i class="fas fa-map-marker-alt"></i> Select County</div>
<div class="county-tabs" id="countyTabs">
    <?php $ci = 0; foreach ($data as $county => $cd): ?>
    <div class="county-tab <?= $ci===0?'active':'' ?>"
         onclick="switchCounty('<?= htmlspecialchars(addslashes($county)) ?>', this)">
        <?= htmlspecialchars($county) ?>
        <?php $aid = array_key_first($cd['assessments']);
              $rc  = $cd['assessments'][$aid]['overall_cdoh'] ?? 0; ?>
        <span class="readiness-badge <?= $rc>=70?'badge-transition':($rc>=50?'badge-support':'badge-not-ready') ?>" style="margin-left:8px">
            <?= $rc ?>%
        </span>
    </div>
    <?php $ci++; endforeach; ?>
</div>

<div id="countyName" style="font-size:22px;font-weight:800;color:#0D1A63;margin-bottom:18px">
    <?= htmlspecialchars($first_county ?? '') ?>
</div>

<!-- ── Main stacked bar chart ─────────────────────────────────────────── -->
<div class="section-title"><i class="fas fa-chart-bar"></i> CDOH Score Distribution per Section (Stacked %)</div>
<div class="card" style="margin-bottom:22px">
    <div class="card-head">
        <h3><i class="fas fa-layer-group"></i> Score Level Distribution by Section</h3>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#dc3545"></div>Score 0 — Inadequate</div>
            <div class="legend-item"><div class="legend-dot" style="background:#fd7e14"></div>Score 1 — Minimal</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ffc107"></div>Score 2 — Some evidence</div>
            <div class="legend-item"><div class="legend-dot" style="background:#17a2b8"></div>Score 3 — Partial</div>
            <div class="legend-item"><div class="legend-dot" style="background:#28a745"></div>Score 4 — Fully adequate</div>
        </div>
    </div>
    <div class="card-body">
        <div style="height:380px"><canvas id="stackedChart"></canvas></div>
    </div>
</div>

<!-- ── CDOH vs IP line overlay chart ──────────────────────────────────── -->
<div class="section-title"><i class="fas fa-chart-line"></i> CDOH % vs IP % per Section</div>
<div class="card" style="margin-bottom:22px">
    <div class="card-head">
        <h3><i class="fas fa-exchange-alt"></i> County (CDOH) vs Implementing Partner — by Section</h3>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#0D1A63"></div>CDOH (County)</div>
            <div class="legend-item"><div class="legend-dot" style="background:#FFC107"></div>Implementing Partner</div>
        </div>
    </div>
    <div class="card-body">
        <div style="height:320px"><canvas id="lineChart"></canvas></div>
    </div>
</div>

<!-- ── County comparison & section detail ────────────────────────────── -->
<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-map"></i> County Overall CDOH Comparison</h3>
        </div>
        <div class="card-body">
            <div style="height:300px"><canvas id="countyChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-table"></i> Section Detail — <span id="sectionDetailTitle">Select a county</span></h3>
        </div>
        <div class="card-body" style="overflow-x:auto;max-height:340px;overflow-y:auto">
            <table class="detail-table" id="sectionTable">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>CDOH %</th>
                        <th>IP %</th>
                        <th>Score distribution</th>
                    </tr>
                </thead>
                <tbody id="sectionTableBody">
                    <tr><td colspan="4" class="no-data">Select a county above to see details</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Per-assessment detail cards ───────────────────────────────────── -->
<?php foreach ($data as $county => $cd): ?>
<div class="county-detail" id="detail_<?= preg_replace('/[^a-z0-9]/i','_',$county) ?>"
     style="display:<?= $county === $first_county ? 'block' : 'none' ?>">

    <div class="section-title" style="margin-top:30px">
        <i class="fas fa-clipboard"></i> Assessment History — <?= htmlspecialchars($county) ?>
    </div>

    <?php foreach ($cd['assessments'] as $aid => $asmnt): ?>
    <div class="card" style="margin-bottom:20px">
        <div class="card-head">
            <h3><i class="fas fa-calendar"></i>
                <?= htmlspecialchars($asmnt['period']) ?> &mdash;
                <?= date('d M Y', strtotime($asmnt['date'])) ?>
            </h3>
            <div style="display:flex;gap:10px;align-items:center">
                <span>CDOH: <strong style="color:#0D1A63"><?= $asmnt['overall_cdoh'] ?>%</strong></span>
                <span>IP: <strong style="color:#FFC107"><?= $asmnt['overall_ip'] ?>%</strong></span>
                <span class="readiness-badge <?= $asmnt['overall_cdoh']>=70?'badge-transition':($asmnt['overall_cdoh']>=50?'badge-support':'badge-not-ready') ?>">
                    <?= $asmnt['readiness'] ?>
                </span>
                <a href="view_transition.php?id=<?= $aid ?>"
                   style="background:#0D1A63;color:#fff;padding:5px 14px;border-radius:8px;font-size:12px;text-decoration:none">
                    <i class="fas fa-eye"></i> View Detail
                </a>
            </div>
        </div>
        <div class="card-body">
            <div style="height:260px;margin-bottom:16px"><canvas id="section_chart_<?= $aid ?>"></canvas></div>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Indicators scored</th>
                        <th>CDOH %</th>
                        <th>IP %</th>
                        <th style="width:200px">Score breakdown (CDOH)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($asmnt['sections'] as $sk => $indicators):
                    $cdoh_pcts = array_column($indicators, 'cdoh_pct');
                    $ip_pcts   = array_column($indicators, 'ip_pct');
                    $avg_c = count($cdoh_pcts) > 0 ? round(array_sum($cdoh_pcts) / count($cdoh_pcts), 1) : 0;
                    $avg_i = count($ip_pcts)   > 0 ? round(array_sum($ip_pcts)   / count($ip_pcts),   1) : 0;
                    $sub_cnt = array_sum(array_column($indicators, 'sub_count'));
                    $s4 = array_sum(array_column($indicators,'s4'));
                    $s3 = array_sum(array_column($indicators,'s3'));
                    $s2 = array_sum(array_column($indicators,'s2'));
                    $s1 = array_sum(array_column($indicators,'s1'));
                    $s0 = array_sum(array_column($indicators,'s0'));
                    $tot = max(1, $sub_cnt);
                    $col = $avg_c >= 70 ? '#28a745' : ($avg_c >= 50 ? '#ffc107' : '#dc3545');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($section_labels[$sk] ?? $sk) ?></strong></td>
                    <td><?= $sub_cnt ?></td>
                    <td><span style="font-weight:700;color:<?= $col ?>"><?= $avg_c ?>%</span></td>
                    <td><span style="font-weight:700;color:#FFC107"><?= $avg_i ?>%</span></td>
                    <td>
                        <div style="display:flex;height:14px;border-radius:7px;overflow:hidden;width:100%">
                            <div style="width:<?= round($s4/$tot*100) ?>%;background:#28a745" title="Score 4: <?= $s4 ?>"></div>
                            <div style="width:<?= round($s3/$tot*100) ?>%;background:#17a2b8" title="Score 3: <?= $s3 ?>"></div>
                            <div style="width:<?= round($s2/$tot*100) ?>%;background:#ffc107" title="Score 2: <?= $s2 ?>"></div>
                            <div style="width:<?= round($s1/$tot*100) ?>%;background:#fd7e14" title="Score 1: <?= $s1 ?>"></div>
                            <div style="width:<?= round($s0/$tot*100) ?>%;background:#dc3545" title="Score 0: <?= $s0 ?>"></div>
                        </div>
                        <div style="font-size:10px;color:#999;margin-top:2px">
                            4:<?= $s4 ?> 3:<?= $s3 ?> 2:<?= $s2 ?> 1:<?= $s1 ?> 0:<?= $s0 ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function() {
        const sectionLabels = <?= json_encode(array_map(fn($sk) => $section_labels[$sk] ?? $sk, array_keys($asmnt['sections']))) ?>;
        const cdohData = <?= json_encode(array_map(function($indicators) {
            $pcts = array_column($indicators, 'cdoh_pct');
            return count($pcts) ? round(array_sum($pcts)/count($pcts),1) : 0;
        }, $asmnt['sections'])) ?>;
        const ipData = <?= json_encode(array_map(function($indicators) {
            $pcts = array_column($indicators, 'ip_pct');
            return count($pcts) ? round(array_sum($pcts)/count($pcts),1) : 0;
        }, $asmnt['sections'])) ?>;
        const s4Data = <?= json_encode(array_map(fn($indicators) => array_sum(array_column($indicators,'s4')), $asmnt['sections'])) ?>;
        const s3Data = <?= json_encode(array_map(fn($indicators) => array_sum(array_column($indicators,'s3')), $asmnt['sections'])) ?>;
        const s2Data = <?= json_encode(array_map(fn($indicators) => array_sum(array_column($indicators,'s2')), $asmnt['sections'])) ?>;
        const s1Data = <?= json_encode(array_map(fn($indicators) => array_sum(array_column($indicators,'s1')), $asmnt['sections'])) ?>;
        const s0Data = <?= json_encode(array_map(fn($indicators) => array_sum(array_column($indicators,'s0')), $asmnt['sections'])) ?>;
        const totals = s4Data.map((_,i) => Math.max(1, s4Data[i]+s3Data[i]+s2Data[i]+s1Data[i]+s0Data[i]));

        new Chart(document.getElementById('section_chart_<?= $aid ?>'), {
            type: 'bar',
            data: {
                labels: sectionLabels,
                datasets: [
                    { label:'Score 4 (Fully)', data: s4Data.map((v,i)=>Math.round(v/totals[i]*100)), backgroundColor:'#28a745', stack:'s', borderRadius:{topLeft:0,topRight:0,bottomLeft:0,bottomRight:0} },
                    { label:'Score 3 (Partial)', data: s3Data.map((v,i)=>Math.round(v/totals[i]*100)), backgroundColor:'#17a2b8', stack:'s' },
                    { label:'Score 2 (Some)', data: s2Data.map((v,i)=>Math.round(v/totals[i]*100)), backgroundColor:'#ffc107', stack:'s' },
                    { label:'Score 1 (Minimal)', data: s1Data.map((v,i)=>Math.round(v/totals[i]*100)), backgroundColor:'#fd7e14', stack:'s' },
                    { label:'Score 0 (None)', data: s0Data.map((v,i)=>Math.round(v/totals[i]*100)), backgroundColor:'#dc3545', stack:'s' },
                    { label:'CDOH %', data: cdohData, type:'line', yAxisID:'pct', borderColor:'#0D1A63', backgroundColor:'transparent', borderWidth:2.5, pointRadius:4, tension:.3 },
                    { label:'IP %', data: ipData, type:'line', yAxisID:'pct', borderColor:'#FFC107', backgroundColor:'transparent', borderWidth:2.5, borderDash:[5,4], pointRadius:4, tension:.3 },
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11}}},
                          tooltip:{ mode:'index', intersect:false,
                            callbacks:{ label: c => c.dataset.stack ? ` ${c.dataset.label}: ${c.raw}%` : ` ${c.dataset.label}: ${c.raw}%` } } },
                scales:{
                    x:{ stacked:true, grid:{display:false}, ticks:{font:{size:10},maxRotation:40} },
                    y:{ stacked:true, max:100, grid:{color:'#f0f0f0'}, ticks:{callback:v=>v+'%'}, title:{display:true,text:'% of sub-indicators',font:{size:11}} },
                    pct:{ position:'right', min:0, max:100, grid:{display:false}, ticks:{callback:v=>v+'%',font:{size:10}}, title:{display:true,text:'Score %',font:{size:11}} }
                }
            }
        });
    })();
    </script>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div><!-- /container -->

<script>
const allCountyData = <?= $all_county_json ?>;
let stackedChart, lineChart, countyChart;

// ── Main stacked bar (top) ────────────────────────────────────────────────────
function buildStackedChart(county) {
    const d = allCountyData[county];
    if (!d || !d.sections.length) return;

    const labels = d.sections.map(s => s.label);
    const s4 = d.sections.map(s => s.s4_pct);
    const s3 = d.sections.map(s => s.s3_pct);
    const s2 = d.sections.map(s => s.s2_pct);
    const s1 = d.sections.map(s => s.s1_pct);
    const s0 = d.sections.map(s => s.s0_pct);

    if (stackedChart) stackedChart.destroy();
    stackedChart = new Chart(document.getElementById('stackedChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label:'Score 4 — Fully adequate', data:s4, backgroundColor:'#28a745', stack:'s' },
                { label:'Score 3 — Partial',         data:s3, backgroundColor:'#17a2b8', stack:'s' },
                { label:'Score 2 — Some evidence',   data:s2, backgroundColor:'#ffc107', stack:'s' },
                { label:'Score 1 — Minimal',          data:s1, backgroundColor:'#fd7e14', stack:'s' },
                { label:'Score 0 — Inadequate',       data:s0, backgroundColor:'#dc3545', stack:'s' },
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins: {
                legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11}}},
                tooltip:{ mode:'index',intersect:false,
                    callbacks:{ label: c => ` ${c.dataset.label}: ${c.raw}%` } }
            },
            scales:{
                x:{ stacked:true,grid:{display:false},ticks:{font:{size:10},maxRotation:40} },
                y:{ stacked:true,max:100,grid:{color:'#f0f0f0'},ticks:{callback:v=>v+'%'},
                    title:{display:true,text:'% of sub-indicators at each score level'} }
            }
        }
    });
}

// ── CDOH vs IP line chart ─────────────────────────────────────────────────────
function buildLineChart(county) {
    const d = allCountyData[county];
    if (!d || !d.sections.length) return;

    const labels = d.sections.map(s => s.label);
    const cdoh   = d.sections.map(s => s.cdoh_pct);
    const ip     = d.sections.map(s => s.ip_pct);

    if (lineChart) lineChart.destroy();
    lineChart = new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label:'CDOH (County)', data:cdoh, borderColor:'#0D1A63', backgroundColor:'rgba(13,26,99,.07)', fill:true, tension:.35, borderWidth:2.5, pointRadius:5, pointBackgroundColor:'#0D1A63' },
                { label:'Implementing Partner', data:ip, borderColor:'#FFC107', backgroundColor:'rgba(255,193,7,.07)', fill:true, tension:.35, borderWidth:2.5, borderDash:[6,4], pointRadius:5, pointBackgroundColor:'#FFC107' },
            ]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{ legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11}}},
                      tooltip:{mode:'index',intersect:false,callbacks:{label:c=>` ${c.dataset.label}: ${c.raw}%`}} },
            scales:{
                x:{grid:{display:false},ticks:{font:{size:10},maxRotation:40}},
                y:{min:0,max:100,grid:{color:'#f0f0f0'},ticks:{callback:v=>v+'%'},
                   title:{display:true,text:'Score %'}}
            }
        }
    });
}

// ── County overall comparison bar ────────────────────────────────────────────
function buildCountyChart() {
    const labels  = <?= $cmp_labels_json ?>;
    const cdoh    = <?= $cmp_cdoh_json ?>;
    const ip      = <?= $cmp_ip_json ?>;
    const colors  = <?= $cmp_colors_json ?>;

    if (countyChart) countyChart.destroy();
    countyChart = new Chart(document.getElementById('countyChart'), {
        type:'bar',
        data:{
            labels,
            datasets:[
                { label:'CDOH %', data:cdoh, backgroundColor:colors, borderRadius:6 },
                { label:'IP %',   data:ip,   backgroundColor:'rgba(255,193,7,.4)', borderRadius:6 },
            ]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{ legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11}}},
                      tooltip:{mode:'index',intersect:false,callbacks:{label:c=>` ${c.dataset.label}: ${c.raw}%`}} },
            scales:{
                x:{grid:{display:false},ticks:{font:{size:10},maxRotation:40}},
                y:{min:0,max:100,grid:{color:'#f0f0f0'},ticks:{callback:v=>v+'%'}}
            }
        }
    });
}

// ── Section detail table ──────────────────────────────────────────────────────
function updateSectionTable(county) {
    const d = allCountyData[county];
    document.getElementById('sectionDetailTitle').textContent = county;
    if (!d || !d.sections.length) return;

    const tbody = document.getElementById('sectionTableBody');
    tbody.innerHTML = d.sections.map(s => {
        const col = s.cdoh_pct>=70?'#28a745':s.cdoh_pct>=50?'#ffc107':'#dc3545';
        const stackHtml = `
            <div style="display:flex;height:12px;border-radius:6px;overflow:hidden">
                <div style="width:${s.s4_pct}%;background:#28a745" title="${s.s4_pct}%"></div>
                <div style="width:${s.s3_pct}%;background:#17a2b8" title="${s.s3_pct}%"></div>
                <div style="width:${s.s2_pct}%;background:#ffc107" title="${s.s2_pct}%"></div>
                <div style="width:${s.s1_pct}%;background:#fd7e14" title="${s.s1_pct}%"></div>
                <div style="width:${s.s0_pct}%;background:#dc3545" title="${s.s0_pct}%"></div>
            </div>`;
        return `<tr>
            <td><strong>${s.label}</strong></td>
            <td style="font-weight:700;color:${col}">${s.cdoh_pct}%</td>
            <td style="font-weight:700;color:#b8860b">${s.ip_pct}%</td>
            <td>${stackHtml}</td>
        </tr>`;
    }).join('');
}

// ── Switch county ─────────────────────────────────────────────────────────────
function switchCounty(county, tabEl) {
    document.querySelectorAll('.county-tab').forEach(t => t.classList.remove('active'));
    if (tabEl) tabEl.classList.add('active');

    document.querySelectorAll('.county-detail').forEach(d => d.style.display = 'none');
    const key = county.replace(/[^a-z0-9]/gi,'_');
    const det = document.getElementById('detail_' + key);
    if (det) det.style.display = 'block';

    document.getElementById('countyName').textContent = county;
    buildStackedChart(county);
    buildLineChart(county);
    updateSectionTable(county);
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    buildCountyChart();
    const firstCounty = <?= json_encode($first_county) ?>;
    if (firstCounty) switchCounty(firstCounty, document.querySelector('.county-tab.active'));
});
</script>
</body>
</html>