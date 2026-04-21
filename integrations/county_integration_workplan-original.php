<?php
// county_integration_workplan.php
session_start();

$base_path = dirname(__DIR__);
$config_path = $base_path . '/includes/config.php';
$session_check_path = $base_path . '/includes/session_check.php';

if (!file_exists($config_path)) {
    die('Configuration file not found.');
}

include($config_path);
include($session_check_path);

// Check if dompdf is available for PDF export
$dompdf_available = false;
$dompdf_autoload_paths = [
    $base_path . '/vendor/autoload.php',
    $base_path . '/vendor/dompdf/dompdf/autoload.inc.php',
    $base_path . '/dompdf/autoload.inc.php'
];

foreach ($dompdf_autoload_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $dompdf_available = true;
        break;
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$export_format = isset($_GET['export']) ? $_GET['export'] : '';

if (!$id) {
    header('Location: county_integration_assessment_list.php');
    exit();
}

// Get assessment data
$query = "
    SELECT cia.*, c.county_name, c.county_code, c.region
    FROM county_integration_assessments cia
    JOIN counties c ON cia.county_id = c.county_id
    WHERE cia.assessment_id = $id
";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
    header('Location: county_integration_assessment_list.php');
    exit();
}
$assessment = mysqli_fetch_assoc($result);

// Get all section data
$sections_query = "
    SELECT * FROM county_integration_sections
    WHERE assessment_id = $id
";
$sections_result = mysqli_query($conn, $sections_query);
$sections_data = [];
while ($row = mysqli_fetch_assoc($sections_result)) {
    $sections_data[$row['section_key']] = json_decode($row['data'], true);
}

// Flatten all data
$data = [];
foreach ($sections_data as $section_data) {
    if ($section_data) {
        foreach ($section_data as $key => $value) {
            $data[$key] = $value;
        }
    }
}

// Generate workplan data
$workplan = generateCountyWorkplan($data, $assessment);
$workplan['detailed_recommendations'] = generateDetailedCountyRecommendations($data);

// Handle exports
if ($export_format === 'pdf') {
    if (!$dompdf_available) {
        die('dompdf not found. Please install dompdf using: composer require dompdf/dompdf');
    }
    exportCountyToPDF($workplan, $conn);
    exit();
} elseif ($export_format === 'word') {
    exportCountyToWord($workplan, $conn);
    exit();
}

renderCountyWorkplan($workplan, $assessment, $conn);
exit();

// ==================== FUNCTIONS ====================

function generateCountyWorkplan($data, $assessment) {
    $county_name = $assessment['county_name'];

    // Calculate readiness score
    $readiness = calculateCountyReadinessScore($data);

    // Determine integration model
    $integration_model = determineCountyIntegrationModel($data);

    // Generate recommendations
    $recommendations = generateCountyRecommendations($data, $readiness);

    // Identify gaps
    $gaps = identifyCountyGaps($data);

    // Create phased timeline
    $timeline = createCountyPhasedTimeline($readiness['score']);

    return [
        'assessment_id' => $assessment['assessment_id'],
        'county_name' => $county_name,
        'county_code' => $assessment['county_code'],
        'region' => $assessment['region'],
        'assessment_period' => $assessment['assessment_period'],
        'assessment_date' => $assessment['assessment_date'],
        'completed_by' => $assessment['completed_by'],
        'readiness_score' => $readiness['score'],
        'readiness_level' => $readiness['level'],
        'readiness_color' => $readiness['color'],
        'integration_model' => $integration_model,
        'recommendations' => $recommendations,
        'gaps' => $gaps,
        'timeline' => $timeline,
        'key_metrics' => [
            'tx_curr' => $data['tx_curr'] ?? 0,
            'plhiv_integrated' => $data['plhiv_integrated_care'] ?? 0,
            'plhiv_sha' => $data['plhiv_enrolled_sha'] ?? 0,
            'hcw_pepfar' => $data['hcw_total_pepfar'] ?? 0,
            'hcw_transitioned' => ($data['hcw_transitioned_clinical'] ?? 0) + ($data['hcw_transitioned_nonclinical'] ?? 0) + ($data['hcw_transitioned_data'] ?? 0) + ($data['hcw_transitioned_community'] ?? 0) + ($data['hcw_transitioned_other'] ?? 0),
            'ta_visits_total' => $data['ta_visits_total'] ?? 0,
            'ta_visits_moh' => $data['ta_visits_moh_only'] ?? 0,
            'deaths_hiv' => $data['deaths_hiv_related'] ?? 0,
            'deaths_tb' => $data['deaths_tb'] ?? 0
        ],
        'barriers' => $data['integration_barriers'] ?? ''
    ];
}

function calculateCountyReadinessScore($data) {
    $scores = [];

    // Leadership & Governance (25%)
    $leadership_score = 0;
    $leadership_max = 12;
    if (($data['leadership_commitment'] ?? '') == 'High') $leadership_score += 4;
    elseif (($data['leadership_commitment'] ?? '') == 'Moderate') $leadership_score += 2;
    if (($data['transition_plan'] ?? '') == 'Yes - Implemented') $leadership_score += 4;
    elseif (($data['transition_plan'] ?? '') == 'Yes - Not Implemented') $leadership_score += 2;
    if (($data['hiv_in_awp'] ?? '') == 'Fully') $leadership_score += 4;
    elseif (($data['hiv_in_awp'] ?? '') == 'Partially') $leadership_score += 2;
    $scores['leadership'] = ($leadership_score / $leadership_max) * 25;

    // HRH Capacity (20%)
    $hrh_score = 0;
    $hrh_max = 12;
    $hrh_gap = $data['hrh_gap'] ?? '';
    if ($hrh_gap == '0-10%') $hrh_score += 4;
    elseif ($hrh_gap == '10-30%') $hrh_score += 2;
    if (($data['staff_multiskilled'] ?? '') == 'Yes') $hrh_score += 4;
    elseif (($data['staff_multiskilled'] ?? '') == 'Partial') $hrh_score += 2;
    if (($data['roving_staff'] ?? '') == 'Yes - Regular') $hrh_score += 4;
    elseif (($data['roving_staff'] ?? '') == 'Yes - Irregular') $hrh_score += 2;
    $scores['hrh'] = ($hrh_score / $hrh_max) * 20;

    // Infrastructure (15%)
    $infra_score = 0;
    $infra_max = 8;
    if (($data['infrastructure_capacity'] ?? '') == 'Adequate') $infra_score += 4;
    elseif (($data['infrastructure_capacity'] ?? '') == 'Minor changes needed') $infra_score += 2;
    if (($data['space_adequacy'] ?? '') == 'Adequate') $infra_score += 4;
    elseif (($data['space_adequacy'] ?? '') == 'Congested') $infra_score += 2;
    $scores['infra'] = ($infra_score / $infra_max) * 15;

    // Service Delivery (20%)
    $service_score = 0;
    $service_max = 8;
    if (($data['service_delivery_without_ccc'] ?? '') == 'Yes') $service_score += 4;
    elseif (($data['service_delivery_without_ccc'] ?? '') == 'Partially') $service_score += 2;
    if (($data['avg_wait_time'] ?? '') == '<1 hour') $service_score += 4;
    elseif (($data['avg_wait_time'] ?? '') == '1-3 hours') $service_score += 2;
    $scores['service'] = ($service_score / $service_max) * 20;

    // Data Integration (10%)
    $data_score = 0;
    if (($data['data_integration_level'] ?? '') == 'Fully Integrated') $data_score = 10;
    elseif (($data['data_integration_level'] ?? '') == 'Partial') $data_score = 5;
    $scores['data'] = $data_score;

    // Financial Sustainability (10%)
    $finance_score = 0;
    $finance_max = 8;
    if (($data['fif_collection_in_place'] ?? '') == 'Yes') $finance_score += 4;
    if (($data['sha_capitation_hiv_tb'] ?? '') == 'Yes') $finance_score += 4;
    $scores['finance'] = ($finance_score / $finance_max) * 10;

    $total_score = $scores['leadership'] + $scores['hrh'] + $scores['infra'] + $scores['service'] + $scores['data'] + $scores['finance'];

    if ($total_score >= 80) {
        $level = 'Fully Ready for Transition';
        $color = 'success';
    } elseif ($total_score >= 60) {
        $level = 'Moderately Ready';
        $color = 'warning';
    } elseif ($total_score >= 40) {
        $level = 'Low Readiness';
        $color = 'orange';
    } else {
        $level = 'Not Ready';
        $color = 'danger';
    }

    return ['score' => round($total_score, 1), 'level' => $level, 'color' => $color];
}

function determineCountyIntegrationModel($data) {
    $models = [];

    $leadership = $data['leadership_commitment'] ?? '';
    $hrh_gap = $data['hrh_gap'] ?? '';
    $infra = $data['infrastructure_capacity'] ?? '';
    $data_integration = $data['data_integration_level'] ?? '';

    if ($leadership == 'High' && $hrh_gap == '0-10%' && $infra == 'Adequate') {
        $models[] = ['name' => 'Full Integration Model', 'description' => 'Complete integration of all HIV/TB services into routine health services across all departments.', 'suitability' => 'High'];
    } elseif ($leadership == 'High' && $hrh_gap == '10-30%') {
        $models[] = ['name' => 'Phased Integration Model', 'description' => 'Gradual integration starting with HTS/PrEP, then ART/PMTCT, then full integration over 12-18 months.', 'suitability' => 'High'];
    } elseif ($data_integration == 'Partial') {
        $models[] = ['name' => 'Hybrid Model', 'description' => 'Combination of facility-based integration and community outreach for hard-to-reach populations.', 'suitability' => 'Medium'];
    } else {
        $models[] = ['name' => 'Supported Transition Model', 'description' => 'Maintain vertical support with gradual capacity building for county systems before full transition.', 'suitability' => 'Recommended'];
    }

    return $models;
}

function generateCountyAIReport($data, $readiness) {
    $report = [];

    $leadership = $data['leadership_commitment'] ?? '';
    $transition_plan = $data['transition_plan'] ?? '';
    $hrh_gap = $data['hrh_gap'] ?? '';
    $infra = $data['infrastructure_capacity'] ?? '';
    $data_integration = $data['data_integration_level'] ?? '';
    $disruption_risk = $data['disruption_risk'] ?? '';

    if ($readiness['score'] >= 80) {
        $report[] = "The county demonstrates strong readiness for integration with a score of {$readiness['score']}%. Full transition can proceed with minimal external support.";
    } elseif ($readiness['score'] >= 60) {
        $report[] = "The county has moderate readiness ({$readiness['score']}%). A phased integration approach over 9-12 months is recommended with targeted technical assistance.";
    } elseif ($readiness['score'] >= 40) {
        $report[] = "The county has low readiness ({$readiness['score']}%). Significant capacity building is required before initiating transition. Priority areas: leadership commitment, HRH, and infrastructure.";
    } else {
        $report[] = "The county is not ready for integration ({$readiness['score']}%). Continue vertical support while strengthening county systems over 18-24 months.";
    }

    if ($leadership == 'Low') {
        $report[] = "Low leadership commitment identified. Recommend conducting leadership sensitization workshops and establishing an integration steering committee with clear terms of reference.";
    }

    if ($transition_plan != 'Yes - Implemented') {
        $report[] = "No formal transition plan in place. Develop a comprehensive transition plan with clear milestones, budgets, and responsible persons within the next 3 months.";
    }

    if ($hrh_gap == '>30%') {
        $report[] = "Significant HRH gap (>30%) identified. Consider hub-and-spoke model where high-volume facilities support lower-volume ones, and implement task-shifting strategies.";
    } elseif ($hrh_gap == '10-30%') {
        $report[] = "Moderate HRH gap (10-30%). Prioritize multi-skilling of existing staff and targeted recruitment for critical positions.";
    }

    if ($infra == 'Major redesign needed') {
        $report[] = "Infrastructure requires major redesign. Advocate for county government funding and explore temporary solutions like mobile clinics during transition.";
    }

    if ($data_integration == 'Fragmented') {
        $report[] = "Fragmented data systems pose high risk for patient tracking. Prioritize EMR integration and data harmonization before full transition.";
    }

    if ($disruption_risk == 'High') {
        $report[] = "High risk of service disruption detected. Implement a prolonged phased transition (>12 months) with close monitoring and contingency planning.";
    }

    return $report;
}

function generateCountyRecommendations($data, $readiness) {
    $recommendations = [];

    // Leadership Recommendations
    $leadership = $data['leadership_commitment'] ?? '';
    if ($leadership != 'High') {
        $recommendations[] = [
            'category' => 'Leadership & Governance',
            'priority' => 'Critical',
            'title' => 'Strengthen County Leadership Commitment to Integration',
            'description' => "Leadership commitment is currently rated as {$leadership}. Strong leadership is essential for successful transition.",
            'actions' => [
                'Conduct leadership sensitization workshop on integration benefits',
                'Establish County Integration Steering Committee',
                'Set measurable integration targets in county work plans',
                'Regularly review integration progress in CHMT meetings'
            ]
        ];
    }

    // Transition Plan
    $transition_plan = $data['transition_plan'] ?? '';
    if ($transition_plan != 'Yes - Implemented') {
        $recommendations[] = [
            'category' => 'Transition Planning',
            'priority' => 'High',
            'title' => 'Develop and Implement Comprehensive Transition Plan',
            'description' => 'A formal transition plan is needed to guide the integration process with clear milestones.',
            'actions' => [
                'Develop transition plan with specific timelines and budgets',
                'Include risk mitigation strategies',
                'Define roles and responsibilities for all stakeholders',
                'Establish monitoring and evaluation framework'
            ]
        ];
    }

    // HRH Recommendations
    $hrh_gap = $data['hrh_gap'] ?? '';
    if ($hrh_gap == '>30%') {
        $recommendations[] = [
            'category' => 'HRH Capacity Building',
            'priority' => 'Critical',
            'title' => 'Address Critical HRH Gaps',
            'description' => "The county has a significant HRH gap of >30%. This requires urgent attention before full integration.",
            'actions' => [
                'Advocate for recruitment of additional healthcare workers',
                'Implement multi-skilling training program for existing staff',
                'Establish hub-and-spoke model for resource sharing',
                'Develop task-shifting guidelines'
            ]
        ];
    } elseif ($hrh_gap == '10-30%') {
        $recommendations[] = [
            'category' => 'HRH Optimization',
            'priority' => 'Medium',
            'title' => 'Optimize Existing HRH Capacity',
            'description' => 'Moderate HRH gap identified. Focus on optimizing current workforce.',
            'actions' => [
                'Implement cross-training program for clinical staff',
                'Establish mentorship program for junior staff',
                'Introduce performance-based incentives',
                'Review and update job descriptions'
            ]
        ];
    }

    // Infrastructure Recommendations
    $infra = $data['infrastructure_capacity'] ?? '';
    $space = $data['space_adequacy'] ?? '';
    if ($infra == 'Major redesign needed' || $space == 'Severely Inadequate') {
        $recommendations[] = [
            'category' => 'Infrastructure Development',
            'priority' => 'High',
            'title' => 'Upgrade Infrastructure for Integrated Services',
            'description' => 'Current infrastructure and space are inadequate for integrated service delivery.',
            'actions' => [
                'Conduct comprehensive facility space assessment',
                'Develop infrastructure upgrade plan with costing',
                'Advocate for county government funding',
                'Reorganize existing space to optimize patient flow',
                'Explore temporary solutions during transition'
            ]
        ];
    }

    // Data Integration
    $data_integration = $data['data_integration_level'] ?? '';
    if ($data_integration != 'Fully Integrated') {
        $recommendations[] = [
            'category' => 'Data Management',
            'priority' => 'High',
            'title' => 'Improve Data Integration Across Systems',
            'description' => "Data systems are currently {$data_integration}, limiting ability to track integrated services.",
            'actions' => [
                'Standardize data collection tools across departments',
                'Train staff on integrated reporting requirements',
                'Establish monthly data review meetings',
                'Implement data quality assurance protocols',
                'Link HIV/TB data with routine health information systems'
            ]
        ];
    }

    // Financial Sustainability
    $fif = $data['fif_collection_in_place'] ?? '';
    if ($fif != 'Yes') {
        $recommendations[] = [
            'category' => 'Financial Sustainability',
            'priority' => 'High',
            'title' => 'Establish FIF Collection Mechanism',
            'description' => 'The county lacks a FIF collection mechanism, limiting local revenue generation.',
            'actions' => [
                'Establish or strengthen FIF collection system county-wide',
                'Ensure FIF includes HIV/TB/PMTCT services',
                'Train finance staff on proper FIF documentation',
                'Regularly audit FIF utilization'
            ]
        ];
    }

    // Sort by priority
    $priority_order = ['Critical' => 1, 'High' => 2, 'Medium' => 3];
    usort($recommendations, function($a, $b) use ($priority_order) {
        return $priority_order[$a['priority']] <=> $priority_order[$b['priority']];
    });

    return $recommendations;
}

function identifyCountyGaps($data) {
    $gaps = [];

    $gap_indicators = [
        ['Leadership', 'Leadership Commitment', $data['leadership_commitment'] ?? '', 'High'],
        ['Leadership', 'Transition Plan', $data['transition_plan'] ?? '', 'Yes - Implemented'],
        ['Leadership', 'HIV in AWP', $data['hiv_in_awp'] ?? '', 'Fully'],
        ['HRH', 'HRH Gap', $data['hrh_gap'] ?? '', '0-10%'],
        ['HRH', 'Staff Multi-skilled', $data['staff_multiskilled'] ?? '', 'Yes'],
        ['Infrastructure', 'Infrastructure Capacity', $data['infrastructure_capacity'] ?? '', 'Adequate'],
        ['Infrastructure', 'Space Adequacy', $data['space_adequacy'] ?? '', 'Adequate'],
        ['Data', 'Data Integration Level', $data['data_integration_level'] ?? '', 'Fully Integrated'],
        ['Financial', 'FIF Collection', $data['fif_collection_in_place'] ?? '', 'Yes'],
        ['Financial', 'SHA Capitation', $data['sha_capitation_hiv_tb'] ?? '', 'Yes']
    ];

    foreach ($gap_indicators as $indicator) {
        list($category, $indicator_name, $current, $target) = $indicator;
        if ($current != $target) {
            $gaps[] = [
                'category' => $category,
                'indicator' => $indicator_name,
                'current' => $current,
                'target' => $target,
                'severity' => ($current == 'Low' || $current == 'No' || $current == '>30%' || $current == 'Not Assessed') ? 'High' : 'Medium'
            ];
        }
    }

    return $gaps;
}

function createCountyPhasedTimeline($score) {
    if ($score >= 80) {
        $months = 6;
        $phases = [
            ['Phase 1: Final Preparation', 1, 2, 'Complete remaining integration activities, final staff training, and establish monitoring systems.'],
            ['Phase 2: Full Integration Launch', 3, 4, 'Launch fully integrated services across all facilities, transition all IP-supported activities.'],
            ['Phase 3: Monitoring & Optimization', 5, 6, 'Monitor integrated service performance, address gaps, and optimize processes.']
        ];
    } elseif ($score >= 60) {
        $months = 9;
        $phases = [
            ['Phase 1: Foundation Building', 1, 3, 'Establish integration steering committee, develop transition plan, conduct baseline assessment.'],
            ['Phase 2: Service Integration', 4, 6, 'Pilot integration in selected facilities, expand to all departments, implement data integration.'],
            ['Phase 3: Full Transition', 7, 9, 'Complete integration across all facilities, transition IP-supported activities, establish sustainability.']
        ];
    } elseif ($score >= 40) {
        $months = 12;
        $phases = [
            ['Phase 1: Readiness Assessment', 1, 3, 'Conduct comprehensive gap analysis, address critical infrastructure gaps, engage county leadership.'],
            ['Phase 2: Capacity Building', 4, 7, 'Train all staff on integrated service delivery, implement EMR, strengthen leadership.'],
            ['Phase 3: Phased Integration', 8, 10, 'Integrate HTS/PrEP services first, then ART/PMTCT, then full integration.'],
            ['Phase 4: Transition & Handover', 11, 12, 'Transition IP-supported activities, establish county financing, implement post-handover support.']
        ];
    } else {
        $months = 18;
        $phases = [
            ['Phase 1: Infrastructure & Governance', 1, 4, 'Address critical infrastructure gaps, establish governance structures, secure county commitment.'],
            ['Phase 2: Systems Strengthening', 5, 9, 'Implement EMR system, train staff, develop policies, strengthen HRH capacity.'],
            ['Phase 3: Service Integration', 10, 14, 'Phased integration starting with HTS/PrEP, then ART/PMTCT, then full integration.'],
            ['Phase 4: Sustainability & Handover', 15, 18, 'Ensure financial sustainability, transition to county-led services, establish monitoring.']
        ];
    }

    return [
        'total_months' => $months,
        'start_date' => date('F Y'),
        'end_date' => date('F Y', strtotime("+$months months")),
        'phases' => $phases
    ];
}

function generateDetailedCountyRecommendations($data) {
    $recommendations = [];

    // Leadership recommendations
    if (($data['leadership_commitment'] ?? '') == 'Low') {
        $recommendations[] = [
            'question' => 'Leadership Commitment',
            'category' => 'Leadership',
            'response' => 'Low',
            'recommendation' => 'Low leadership commitment poses a critical risk to successful integration. Immediate action required.',
            'action_items' => [
                'Schedule leadership retreat focused on integration benefits',
                'Present evidence from successful transition counties',
                'Establish integration as a priority in county health agenda',
                'Engage county assembly health committee for oversight'
            ],
            'timeline' => '1-2 months',
            'priority' => 'Critical'
        ];
    }

    // HRH gap recommendations
    $hrh_gap = $data['hrh_gap'] ?? '';
    if ($hrh_gap == '>30%') {
        $recommendations[] = [
            'question' => 'HRH Gap',
            'category' => 'HRH',
            'response' => '>30%',
            'recommendation' => 'Critical HRH shortage requires immediate multi-pronged intervention.',
            'action_items' => [
                'Fast-track recruitment of critical staff positions',
                'Implement task-shifting from higher to lower cadres',
                'Establish hub-and-spoke model for resource sharing',
                'Utilize community health workers for basic services'
            ],
            'timeline' => '3-6 months',
            'priority' => 'Critical'
        ];
    } elseif ($hrh_gap == '10-30%') {
        $recommendations[] = [
            'question' => 'HRH Gap',
            'category' => 'HRH',
            'response' => '10-30%',
            'recommendation' => 'Moderate HRH gap manageable through optimization strategies.',
            'action_items' => [
                'Implement multi-skilling training program',
                'Introduce flexible staffing arrangements',
                'Enhance retention incentives',
                'Strengthen supervision and mentorship'
            ],
            'timeline' => '6 months',
            'priority' => 'High'
        ];
    }

    // Infrastructure recommendations
    $infra = $data['infrastructure_capacity'] ?? '';
    if ($infra == 'Major redesign needed') {
        $recommendations[] = [
            'question' => 'Infrastructure Capacity',
            'category' => 'Infrastructure',
            'response' => 'Major redesign needed',
            'recommendation' => 'Infrastructure limitations require significant investment before integration.',
            'action_items' => [
                'Develop infrastructure upgrade master plan with costing',
                'Prioritize high-impact renovations',
                'Explore public-private partnerships for funding',
                'Implement temporary solutions during construction'
            ],
            'timeline' => '6-12 months',
            'priority' => 'High'
        ];
    }

    // Data integration recommendations
    $data_integration = $data['data_integration_level'] ?? '';
    if ($data_integration == 'Fragmented') {
        $recommendations[] = [
            'question' => 'Data Integration Level',
            'category' => 'Data Management',
            'response' => 'Fragmented',
            'recommendation' => 'Fragmented data systems pose high risk for patient tracking and continuity of care.',
            'action_items' => [
                'Conduct data systems assessment and gap analysis',
                'Implement interoperable EMR across all departments',
                'Train all staff on integrated data entry',
                'Establish data quality assurance committee'
            ],
            'timeline' => '4-8 months',
            'priority' => 'High'
        ];
    }

    // FIF collection
    if (($data['fif_collection_in_place'] ?? '') != 'Yes') {
        $recommendations[] = [
            'question' => 'FIF Collection',
            'category' => 'Finance',
            'response' => 'No',
            'recommendation' => 'Lack of FIF collection limits financial sustainability after transition.',
            'action_items' => [
                'Establish FIF collection mechanisms at all facilities',
                'Train finance staff on FIF management',
                'Develop FIF utilization guidelines',
                'Conduct regular FIF audits'
            ],
            'timeline' => '2-4 months',
            'priority' => 'High'
        ];
    }

    return $recommendations;
}

function getCountyWorkplanHTML($workplan) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>County Integration Workplan - <?= htmlspecialchars($workplan['county_name']) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: white;
                color: #333;
                line-height: 1.6;
                padding: 30px;
            }
            .container { max-width: 1200px; margin: 0 auto; }

            .page-header {
                background: linear-gradient(135deg, #0D1A63 0%, #1a3a9e 100%);
                color: #fff;
                padding: 20px 25px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
            }
            .page-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
            .page-header .subtitle { font-size: 0.9rem; opacity: 0.9; }

            .workplan-meta {
                background: #f8fafc;
                border: 1px solid #e0e4f0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
            }
            .meta-item { text-align: center; padding: 6px; }
            .meta-item .label { font-size: 10px; font-weight: 700; color: #666; text-transform: uppercase; }
            .meta-item .value { font-size: 14px; font-weight: 800; color: #0D1A63; margin-top: 3px; }

            .readiness-badge {
                display: inline-block;
                padding: 5px 12px;
                border-radius: 20px;
                font-weight: 700;
                font-size: 12px;
            }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-orange { background: #ffe5d0; color: #fd7e14; }
            .badge-danger { background: #f8d7da; color: #721c24; }

            .section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: #0D1A63;
                margin: 20px 0 12px;
                padding-bottom: 6px;
                border-bottom: 2px solid #0D1A63;
            }

            .card {
                background: #fff;
                border: 1px solid #e0e4f0;
                border-radius: 8px;
                margin-bottom: 18px;
                overflow: hidden;
            }
            .card-header {
                background: #f8fafc;
                padding: 10px 15px;
                border-bottom: 1px solid #e0e4f0;
                font-weight: 700;
                color: #0D1A63;
                font-size: 14px;
            }
            .card-body { padding: 15px; }

            .recommendation-item {
                background: #f8fafc;
                border-left: 4px solid;
                padding: 12px;
                margin-bottom: 12px;
                border-radius: 6px;
            }
            .rec-critical { border-left-color: #dc3545; }
            .rec-high { border-left-color: #fd7e14; }
            .rec-medium { border-left-color: #ffc107; }
            .rec-low { border-left-color: #28a745; }

            .priority-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 9px;
                font-weight: 700;
            }
            .priority-critical { background: #f8d7da; color: #721c24; }
            .priority-high { background: #ffe5d0; color: #fd7e14; }
            .priority-medium { background: #fff3cd; color: #856404; }
            .priority-low { background: #d4edda; color: #155724; }

            .timeline-table, .gap-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }
            .timeline-table th, .gap-table th {
                background: #0D1A63;
                color: #fff;
                padding: 8px;
                text-align: left;
            }
            .timeline-table td, .gap-table td {
                padding: 8px;
                border-bottom: 1px solid #e0e4f0;
            }

            .phase-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
            }
            .phase-1 { background: #cfe2ff; color: #004085; }
            .phase-2 { background: #fff3cd; color: #856404; }
            .phase-3 { background: #d4edda; color: #155724; }
            .phase-4 { background: #e2e3e5; color: #383d41; }

            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 12px;
                margin-bottom: 15px;
            }
            .kpi-card {
                background: #f8fafc;
                border-radius: 8px;
                padding: 12px;
                text-align: center;
                border: 1px solid #e0e4f0;
            }
            .kpi-value {
                font-size: 22px;
                font-weight: 800;
                color: #0D1A63;
            }
            .kpi-label {
                font-size: 10px;
                color: #666;
                text-transform: uppercase;
                margin-top: 4px;
            }

            .footer-note {
                margin-top: 30px;
                padding: 15px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #e0e4f0;
            }

            @media print {
                body { padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="page-header">
            <h1>County Integration Transition Workplan</h1>
            <div class="subtitle">HIV/TB Service Integration Roadmap</div>
            <div style="margin-top: 8px;"><?= htmlspecialchars($workplan['county_name']) ?> County (Code: <?= $workplan['county_code'] ?>) | <?= $workplan['region'] ?> Region</div>
        </div>

        <div class="workplan-meta">
            <div class="meta-item"><div class="label">Assessment Period</div><div class="value"><?= htmlspecialchars($workplan['assessment_period']) ?></div></div>
            <div class="meta-item"><div class="label">Integration Readiness</div><div class="value"><span class="readiness-badge badge-<?= $workplan['readiness_color'] ?>"><?= $workplan['readiness_level'] ?> (<?= $workplan['readiness_score'] ?>%)</span></div></div>
            <div class="meta-item"><div class="label">Assessment Date</div><div class="value"><?= date('d M Y', strtotime($workplan['assessment_date'])) ?></div></div>
            <div class="meta-item"><div class="label">Completed By</div><div class="value"><?= htmlspecialchars($workplan['completed_by']) ?></div></div>
        </div>

        <div class="card">
            <div class="card-header">Executive Summary</div>
            <div class="card-body">
                <p>This county integration workplan outlines the transition from vertical HIV/TB services to fully integrated service delivery across <strong><?= htmlspecialchars($workplan['county_name']) ?> County</strong>. Based on the integration assessment conducted in <strong><?= $workplan['assessment_period'] ?></strong>, the county has been classified as <strong><?= $workplan['readiness_level'] ?></strong> with an overall integration readiness score of <strong><?= $workplan['readiness_score'] ?>%</strong>.</p>
                <p style="margin-top: 10px;">The county currently serves <strong><?= number_format($workplan['key_metrics']['tx_curr']) ?></strong> PLHIV on ART, with <strong><?= number_format($workplan['key_metrics']['plhiv_integrated']) ?></strong> receiving integrated care. The transition period will run from <strong><?= $workplan['timeline']['start_date'] ?></strong> to <strong><?= $workplan['timeline']['end_date'] ?></strong> (<?= $workplan['timeline']['total_months'] ?> months).</p>
            </div>
        </div>

        <div class="section-title">Key Performance Indicators</div>
        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['tx_curr']) ?></div><div class="kpi-label">TX_CURR (PLHIV on ART)</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['plhiv_integrated']) ?></div><div class="kpi-label">PLHIV in Integrated Care</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['plhiv_sha']) ?></div><div class="kpi-label">PLHIV Enrolled SHA</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['hcw_pepfar']) ?></div><div class="kpi-label">HCWs PEPFAR Supported</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['hcw_transitioned']) ?></div><div class="kpi-label">HCWs Transitioned to County</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['ta_visits_total']) ?></div><div class="kpi-label">TA/Mentorship Visits</div></div>
        </div>

        <div class="section-title">Recommended Integration Model</div>
        <div class="card">
            <div class="card-body">
                <?php foreach ($workplan['integration_model'] as $model): ?>
                <div style="margin-bottom: 15px; padding: 12px; background: #f8fafc; border-radius: 6px;">
                    <strong style="color: #0D1A63; font-size: 14px;"><?= htmlspecialchars($model['name']) ?></strong>
                    <span style="display: inline-block; margin-left: 10px; padding: 2px 8px; background: <?= $model['suitability'] == 'High' ? '#d4edda' : ($model['suitability'] == 'Recommended' ? '#fff3cd' : '#e2e3e5') ?>; border-radius: 12px; font-size: 10px; font-weight: 600;"><?= $model['suitability'] ?> Suitability</span>
                    <p style="margin-top: 8px; font-size: 13px; color: #555;"><?= htmlspecialchars($model['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-title">Gaps Analysis</div>
        <div class="card">
            <div class="card-body">
                <table class="gap-table">
                    <thead><tr><th>Category</th><th>Indicator</th><th>Current Status</th><th>Target Status</th><th>Severity</th></tr></thead>
                    <tbody>
                        <?php foreach ($workplan['gaps'] as $gap): ?>
                        <tr>
                            <td><?= $gap['category'] ?></td>
                            <td><?= $gap['indicator'] ?></td>
                            <td><span class="priority-badge <?= $gap['severity'] == 'High' ? 'priority-critical' : 'priority-medium' ?>"><?= htmlspecialchars($gap['current']) ?></span></td>
                            <td><span class="priority-badge priority-low"><?= $gap['target'] ?></span></td>
                            <td><span class="priority-badge <?= $gap['severity'] == 'High' ? 'priority-critical' : 'priority-medium' ?>"><?= $gap['severity'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section-title">Strategic Recommendations</div>
        <div class="card">
            <div class="card-body">
                <?php foreach ($workplan['recommendations'] as $rec):
                    $rec_class = $rec['priority'] == 'Critical' ? 'rec-critical' : ($rec['priority'] == 'High' ? 'rec-high' : ($rec['priority'] == 'Medium' ? 'rec-medium' : 'rec-low'));
                ?>
                <div class="recommendation-item <?= $rec_class ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                        <strong style="font-size: 13px;"><?= htmlspecialchars($rec['category']) ?>: <?= htmlspecialchars($rec['title']) ?></strong>
                        <span class="priority-badge <?= $rec['priority'] == 'Critical' ? 'priority-critical' : ($rec['priority'] == 'High' ? 'priority-high' : ($rec['priority'] == 'Medium' ? 'priority-medium' : 'priority-low')) ?>"><?= $rec['priority'] ?> Priority</span>
                    </div>
                    <p style="margin-bottom: 8px; font-size: 12px;"><?= htmlspecialchars($rec['description']) ?></p>
                    <div>
                        <strong>Key Action Items:</strong>
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <?php foreach ($rec['actions'] as $action): ?>
                                <li style="font-size: 12px;"><?= htmlspecialchars($action) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-title">Phased Transition Timeline</div>
        <div class="card">
            <div class="card-body">
                <table class="timeline-table">
                    <thead><tr><th>Phase</th><th>Duration</th><th>Key Activities</th></tr></thead>
                    <tbody>
                        <?php foreach ($workplan['timeline']['phases'] as $phase): ?>
                        <tr>
                            <td><span class="phase-badge phase-<?= $phase[0] == 'Phase 1' ? '1' : ($phase[0] == 'Phase 2' ? '2' : ($phase[0] == 'Phase 3' ? '3' : '4')) ?>"><?= $phase[0] ?></span></td>
                            <td>Months <?= $phase[1] ?>-<?= $phase[2] ?></td>
                            <td><?= htmlspecialchars($phase[3]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 12px; font-size: 12px; color: #666;"><strong>Overall Timeline:</strong> <?= $workplan['timeline']['start_date'] ?> to <?= $workplan['timeline']['end_date'] ?> (<?= $workplan['timeline']['total_months'] ?> months)</p>
            </div>
        </div>

        <?php if (!empty($workplan['barriers'])): ?>
        <div class="section-title">Key Barriers to Integration</div>
        <div class="card">
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($workplan['barriers'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-note">
            This county integration workplan was generated based on assessment data from the County Integration Assessment Tool.<br>
            Generated on: <?= date('d F Y H:i:s') ?>
        </div>
    </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function exportCountyToPDF($workplan, $conn) {
    $html = getCountyWorkplanHTML($workplan);
    try {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = "County_Integration_Workplan_" . str_replace(' ', '_', $workplan['county_name']) . "_" . date('Ymd') . ".pdf";
        $dompdf->stream($filename, array('Attachment' => true));
    } catch (Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
    exit();
}

function exportCountyToWord($workplan, $conn) {
    $html = getCountyWorkplanHTML($workplan);
    $html = str_replace('</head>',
        '<meta charset="UTF-8">
        <meta name="generator" content="Microsoft Word 15">
        <style>@page { size: A4; margin: 2.54cm; } body { margin: 0; padding: 20px; }</style>
        </head>',
        $html);
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="County_Integration_Workplan_' . str_replace(' ', '_', $workplan['county_name']) . '_' . date('Ymd') . '.doc"');
    echo $html;
    exit();
}

function renderCountyWorkplan($workplan, $assessment, $conn) {
    $html = getCountyWorkplanHTML($workplan);
    $html = str_replace('</head>',
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></head>',
        $html);
    $html = str_replace('</body>', '
    <div style="position: fixed; bottom: 20px; right: 20px; display: flex; gap: 10px; z-index: 1000;" class="no-print">
        <a href="?id=' . $assessment['assessment_id'] . '&export=pdf" style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="?id=' . $assessment['assessment_id'] . '&export=word" style="background: #0D1A63; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fas fa-file-word"></i> Export Word</a>
        <button onclick="window.print()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;"><i class="fas fa-print"></i> Print</button>
    </div>
    </body>', $html);
    echo $html;
}
?>