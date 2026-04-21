<?php
// county_integration_workplan.php
session_start();

// Fix include paths
$base_path = dirname(__DIR__);
$config_path = $base_path . '/includes/config.php';
$session_check_path = $base_path . '/includes/session_check.php';

if (!file_exists($config_path)) {
    die('Configuration file not found. Please check the path: ' . $config_path);
}

include($config_path);
include($session_check_path);

// Verify database connection
if (!isset($conn) || !$conn) {
    die('Database connection failed. Please check your config.php file.');
}

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

// Get main assessment
$query = "SELECT * FROM county_integration_assessments WHERE assessment_id = $id";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
    header('Location: county_integration_assessment_list.php');
    exit();
}
$assessment = mysqli_fetch_assoc($result);

// Generate the workplan data
$workplan = generateCountyWorkplan($assessment, $conn);

// Handle exports
if ($export_format === 'pdf') {
    if (!$dompdf_available) {
        die('dompdf not found. Please install dompdf using: composer require dompdf/dompdf');
    }
    exportToPDF($workplan, $conn);
    exit();
} elseif ($export_format === 'word') {
    exportToWord($workplan, $conn);
    exit();
}

// Output the workplan as HTML
renderWorkplan($workplan, $assessment, $conn);
exit();

// ==================== FUNCTIONS ====================

function generateCountyWorkplan($assessment, $conn) {
    $county_name = $assessment['county_name'];
    $agency_name = $assessment['agency_name'];
    $ip_name = $assessment['ip_name'];

    // Calculate readiness score
    $readiness_score = calculateCountyReadinessScore($assessment);

    // Determine integration model
    $integration_model = determineCountyIntegrationModel($assessment);

    // Generate recommendations
    $recommendations = generateCountyRecommendations($assessment);

    // Identify gaps
    $gaps = identifyCountyGaps($assessment);

    // Create phased transition timeline
    $timeline = createCountyPhasedTimeline($readiness_score);

    // Calculate key metrics
    $total_pepfar = $assessment['hcw_total_pepfar'] ?? 0;
    $total_transitioned = $assessment['hcw_transitioned_total'] ?? 0;
    $transition_percentage = $total_pepfar > 0 ? round(($total_transitioned / $total_pepfar) * 100) : 0;

    // Count active TWGs
    $twg_count = 0;
    $twg_names = [];
    if (($assessment['has_hiv_tb_twg'] ?? '') === 'Yes') { $twg_count++; $twg_names[] = 'HIV/TB'; }
    if (($assessment['has_pmtct_twg'] ?? '') === 'Yes') { $twg_count++; $twg_names[] = 'PMTCT'; }
    if (($assessment['has_mnch_twg'] ?? '') === 'Yes') { $twg_count++; $twg_names[] = 'MNCH'; }
    if (($assessment['has_hiv_prevention_twg'] ?? '') === 'Yes') { $twg_count++; $twg_names[] = 'HIV Prevention'; }
    if (($assessment['has_lab_twg'] ?? '') === 'Yes') { $twg_count++; $twg_names[] = 'Lab'; }

    // EMR status
    $emr_selected = $assessment['selected_emr_type'] ?? 'Not Selected';
    $has_his_guide = ($assessment['has_his_integration_guide'] ?? '') === 'Yes';
    $has_his_staff = ($assessment['has_dedicated_his_staff'] ?? '') === 'Yes';

    $workplan = [
        'assessment_id' => $assessment['assessment_id'],
        'county_name' => $county_name,
        'agency_name' => $agency_name,
        'ip_name' => $ip_name,
        'assessment_period' => $assessment['assessment_period'],
        'collected_by' => $assessment['collected_by'],
        'collection_date' => $assessment['collection_date'],
        'readiness_score' => $readiness_score['score'],
        'readiness_level' => $readiness_score['level'],
        'readiness_color' => $readiness_score['color'],
        'integration_model' => $integration_model,
        'recommendations' => $recommendations,
        'gaps' => $gaps,
        'timeline' => $timeline,
        'key_metrics' => [
            'hcw_pepfar' => $total_pepfar,
            'hcw_transitioned' => $total_transitioned,
            'transition_percentage' => $transition_percentage,
            'facilities_visited_ta' => $assessment['facilities_visited_ta'] ?? 0,
            'stakeholder_meetings' => $assessment['stakeholder_meetings_count'] ?? 0,
            'tb_twg_activities' => $assessment['tb_twg_activities_count'] ?? 0,
            'ahd_hubs_available' => $assessment['ahd_hubs_available'] ?? 0,
            'ahd_hubs_activated' => $assessment['ahd_hubs_activated'] ?? 0
        ],
        'twg_status' => [
            'count' => $twg_count,
            'names' => $twg_names,
            'meetings' => [
                'hiv_tb' => $assessment['hiv_tb_twg_meetings'] ?? 0,
                'pmtct' => $assessment['pmtct_twg_meetings'] ?? 0,
                'mnch' => $assessment['mnch_twg_meetings'] ?? 0,
                'hiv_prevention' => $assessment['hiv_prevention_twg_meetings'] ?? 0
            ]
        ],
        'emr_status' => [
            'selected_type' => $emr_selected,
            'has_his_integration_guide' => $has_his_guide,
            'has_dedicated_his_staff' => $has_his_staff,
            'deployment_meetings' => ($assessment['emr_deployment_meetings'] ?? '') === 'Yes'
        ],
        'financial_status' => [
            'has_fif_plan' => ($assessment['has_fif_collection_plan'] ?? '') === 'Yes',
            'receives_sha_capitation' => ($assessment['receives_sha_capitation'] ?? '') === 'Yes',
            'has_stakeholder_plan' => ($assessment['has_stakeholder_engagement_plan'] ?? '') === 'Yes'
        ],
        'lab_status' => [
            'has_isrs_plan' => ($assessment['has_isrs_operational_plan'] ?? '') === 'Yes',
            'has_lab_strategic_plan' => ($assessment['has_lab_strategic_plan'] ?? '') === 'Yes',
            'has_lab_twg' => ($assessment['has_lab_twg'] ?? '') === 'Yes',
            'has_lmis_guide' => ($assessment['has_lmis_integration_guide'] ?? '') === 'Yes'
        ],
        'hrh_status' => [
            'has_transition_plan' => ($assessment['has_hrh_transition_plan'] ?? '') === 'Yes',
            'leadership_commitment' => $assessment['leadership_commitment'] ?? 'Not Assessed',
            'hiv_in_awp' => $assessment['hiv_in_awp'] ?? 'Not Assessed',
            'hrh_gap' => $assessment['hrh_gap'] ?? 'Not Assessed',
            'staff_multiskilled' => $assessment['staff_multiskilled'] ?? 'Not Assessed',
            'infrastructure_capacity' => $assessment['infrastructure_capacity'] ?? 'Not Assessed'
        ],
        'barriers' => $assessment['integration_barriers'] ?? '',
        'detailed_recommendations' => generateCountyDetailedRecommendations($assessment)
    ];

    return $workplan;
}

function calculateCountyReadinessScore($assessment) {
    $scores = [];

    // Section 1: Governance & Leadership (25%)
    $gov_score = 0;
    $gov_max = 15;
    if (($assessment['has_integration_oversight_team'] ?? '') === 'Yes') $gov_score += 3;
    if (($assessment['has_hiv_integration_oversight'] ?? '') === 'Yes') $gov_score += 3;
    if (($assessment['has_hrh_transition_plan'] ?? '') === 'Yes') $gov_score += 3;
    if (($assessment['has_plhiv_sha_plan'] ?? '') === 'Yes') $gov_score += 3;
    if (($assessment['has_ta_mentorship_plan'] ?? '') === 'Yes') $gov_score += 3;
    $scores['governance'] = ($gov_score / $gov_max) * 25;

    // Section 2: HRH Capacity (20%)
    $hrh_score = 0;
    $hrh_max = 12;
    $leadership = $assessment['leadership_commitment'] ?? '';
    if ($leadership === 'High') $hrh_score += 4;
    elseif ($leadership === 'Moderate') $hrh_score += 2;
    $multiskilled = $assessment['staff_multiskilled'] ?? '';
    if ($multiskilled === 'Yes') $hrh_score += 4;
    elseif ($multiskilled === 'Partial') $hrh_score += 2;
    $roving = $assessment['roving_staff'] ?? '';
    if ($roving === 'Yes - Regular') $hrh_score += 4;
    elseif ($roving === 'Yes - Irregular') $hrh_score += 2;
    $scores['hrh'] = ($hrh_score / $hrh_max) * 20;

    // Section 3: Technical Working Groups (15%)
    $twg_score = 0;
    $twg_max = 10;
    if (($assessment['has_hiv_tb_twg'] ?? '') === 'Yes') $twg_score += 2;
    if (($assessment['has_pmtct_twg'] ?? '') === 'Yes') $twg_score += 2;
    if (($assessment['has_mnch_twg'] ?? '') === 'Yes') $twg_score += 2;
    if (($assessment['has_hiv_prevention_twg'] ?? '') === 'Yes') $twg_score += 2;
    if (($assessment['has_lab_twg'] ?? '') === 'Yes') $twg_score += 2;
    $scores['twg'] = ($twg_score / $twg_max) * 15;

    // Section 4: Service Integration (20%)
    $service_score = 0;
    $service_max = 8;
    if (($assessment['hiv_tb_integration_plan'] ?? '') === 'Yes') $service_score += 4;
    if (($assessment['hiv_tb_integration_meeting'] ?? '') === 'Yes') $service_score += 4;
    $scores['service'] = ($service_score / $service_max) * 20;

    // Section 5: EMR & Digital Health (10%)
    $digital_score = 0;
    $digital_max = 8;
    if (($assessment['selected_emr_type'] ?? '') !== '') $digital_score += 2;
    if (($assessment['emr_deployment_meetings'] ?? '') === 'Yes') $digital_score += 2;
    if (($assessment['has_his_integration_guide'] ?? '') === 'Yes') $digital_score += 2;
    if (($assessment['has_dedicated_his_staff'] ?? '') === 'Yes') $digital_score += 2;
    $scores['digital'] = ($digital_score / $digital_max) * 10;

    // Section 6: Financial Sustainability (10%)
    $finance_score = 0;
    $finance_max = 6;
    if (($assessment['has_fif_collection_plan'] ?? '') === 'Yes') $finance_score += 3;
    if (($assessment['receives_sha_capitation'] ?? '') === 'Yes') $finance_score += 3;
    $scores['finance'] = ($finance_score / $finance_max) * 10;

    $total_score = $scores['governance'] + $scores['hrh'] + $scores['twg'] + $scores['service'] + $scores['digital'] + $scores['finance'];

    if ($total_score >= 80) {
        $level = 'Fully Ready';
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

function determineCountyIntegrationModel($assessment) {
    $models = [];

    $has_oversight = ($assessment['has_integration_oversight_team'] ?? '') === 'Yes';
    $has_hrh_plan = ($assessment['has_hrh_transition_plan'] ?? '') === 'Yes';
    $has_emr_selected = ($assessment['selected_emr_type'] ?? '') !== '';
    $hiv_in_awp = $assessment['hiv_in_awp'] ?? '';

    if ($has_oversight && $has_hrh_plan && $hiv_in_awp === 'Fully') {
        $models[] = [
            'name' => 'Full County-Wide Integration',
            'description' => 'Accelerated integration across all county health facilities with full ownership and leadership from county government.',
            'suitability' => 'High'
        ];
    }

    if ($has_emr_selected) {
        $models[] = [
            'name' => 'Digital Health-Enabled Integration',
            'description' => 'Leverage standardized EMR across all facilities for seamless data sharing, patient tracking, and integrated reporting.',
            'suitability' => 'Recommended'
        ];
    }

    $models[] = [
        'name' => 'Phased Hub-and-Spoke Model',
        'description' => 'High-volume facilities serve as integration hubs providing mentorship and support to surrounding health centers and dispensaries.',
        'suitability' => 'Alternative'
    ];

    $models[] = [
        'name' => 'Differentiated Service Delivery (DSD) Model',
        'description' => 'Risk-stratified approach where stable patients receive multi-month dispensing at lower-level facilities, reducing burden on high-volume sites.',
        'suitability' => 'Complementary'
    ];

    return $models;
}

function generateCountyRecommendations($assessment) {
    $recommendations = [];

    // Governance & Leadership
    if (($assessment['has_integration_oversight_team'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'category' => 'Governance',
            'priority' => 'Critical',
            'title' => 'Establish County Integration Oversight Team',
            'description' => 'A functional oversight team is essential for driving integration across all county facilities.',
            'actions' => [
                'Form a multi-stakeholder integration oversight team',
                'Develop clear terms of reference and reporting lines',
                'Schedule quarterly review meetings',
                'Include representation from all health programs and partners'
            ],
            'timeline' => '1-2 months',
            'responsible' => 'County Executive for Health, CASCO'
        ];
    }

    // HRH Transition
    $total_pepfar = $assessment['hcw_total_pepfar'] ?? 0;
    $total_transitioned = $assessment['hcw_transitioned_total'] ?? 0;
    $transition_percentage = $total_pepfar > 0 ? round(($total_transitioned / $total_pepfar) * 100) : 0;

    if ($transition_percentage < 50 && $total_pepfar > 0) {
        $recommendations[] = [
            'category' => 'HRH Transition',
            'priority' => 'High',
            'title' => 'Accelerate Absorption of PEPFAR-Supported HCWs',
            'description' => "Only {$transition_percentage}% of PEPFAR-supported HCWs have been transitioned to county payroll. This poses a risk to service continuity.",
            'actions' => [
                'Develop a prioritized HRH absorption plan',
                'Engage County Public Service Board for recruitment',
                'Prioritize clinical and data staff for transition',
                'Establish a transition timeline with clear milestones'
            ],
            'timeline' => '3-6 months',
            'responsible' => 'County HR Department, CASCO'
        ];
    }

    // EMR Standardization
    $emr_selected = $assessment['selected_emr_type'] ?? '';
    if (empty($emr_selected)) {
        $recommendations[] = [
            'category' => 'Digital Health',
            'priority' => 'High',
            'title' => 'Select and Deploy Standardized County-Wide EMR',
            'description' => 'A unified EMR system is critical for data integration and seamless patient tracking across facilities.',
            'actions' => [
                'Conduct EMR needs assessment across all facilities',
                'Select a standardized EMR platform (KenyaEMR recommended)',
                'Develop phased deployment plan starting with high-volume facilities',
                'Train HIS staff and facility EMR champions'
            ],
            'timeline' => '6-12 months',
            'responsible' => 'Health Records Officer, ICT Department'
        ];
    }

    // TWG Strengthening
    $twg_count = 0;
    if (($assessment['has_hiv_tb_twg'] ?? '') === 'Yes') $twg_count++;
    if (($assessment['has_pmtct_twg'] ?? '') === 'Yes') $twg_count++;
    if (($assessment['has_mnch_twg'] ?? '') === 'Yes') $twg_count++;
    if (($assessment['has_hiv_prevention_twg'] ?? '') === 'Yes') $twg_count++;

    if ($twg_count < 3) {
        $recommendations[] = [
            'category' => 'Technical Working Groups',
            'priority' => 'Medium',
            'title' => 'Strengthen Program-Specific TWGs',
            'description' => "Only {$twg_count} of 5 key TWGs are functional. These groups are essential for technical coordination.",
            'actions' => [
                'Reconstitute non-functional TWGs',
                'Establish regular meeting schedules (quarterly minimum)',
                'Ensure TWGs have clear workplans and budgets',
                'Link TWG recommendations to county planning processes'
            ],
            'timeline' => '2-4 months',
            'responsible' => 'County Health Management Team'
        ];
    }

    // Financial Sustainability
    $has_fif = ($assessment['has_fif_collection_plan'] ?? '') === 'Yes';
    $has_sha = ($assessment['receives_sha_capitation'] ?? '') === 'Yes';

    if (!$has_fif || !$has_sha) {
        $recommendations[] = [
            'category' => 'Financial Sustainability',
            'priority' => 'High',
            'title' => 'Strengthen Local Revenue Generation Mechanisms',
            'description' => 'County needs to diversify funding sources for integration sustainability.',
            'actions' => [
                'Establish or strengthen FIF collection incorporating HIV/TB services',
                'Ensure all PLHIV are enrolled in SHA',
                'Build facility capacity for timely SHA claims submission',
                'Advocate for inclusion of HIV/TB in SHA capitation package'
            ],
            'timeline' => '3-6 months',
            'responsible' => 'Finance Department, Facility In-Charges'
        ];
    }

    // Stakeholder Engagement
    if (($assessment['has_stakeholder_engagement_plan'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'category' => 'Stakeholder Engagement',
            'priority' => 'Medium',
            'title' => 'Develop Multi-Stakeholder Engagement Plan',
            'description' => 'Broad stakeholder engagement is critical for successful integration and transition.',
            'actions' => [
                'Develop stakeholder engagement plan with clear communication channels',
                'Include PLHIV networks, community representatives, and private sector',
                'Schedule quarterly stakeholder review meetings',
                'Establish feedback mechanisms for continuous improvement'
            ],
            'timeline' => '2-3 months',
            'responsible' => 'County Health Promotion Officer'
        ];
    }

    // Lab Systems
    if (($assessment['has_lab_strategic_plan'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'category' => 'Laboratory Services',
            'priority' => 'Medium',
            'title' => 'Develop and Implement County Laboratory Strategic Plan',
            'description' => 'A strategic plan is needed to guide lab services integration and quality improvement.',
            'actions' => [
                'Develop comprehensive lab strategic plan',
                'Allocate budget for QMS activities and equipment maintenance',
                'Strengthen specimen referral systems',
                'Build capacity for LCQI implementation'
            ],
            'timeline' => '4-8 months',
            'responsible' => 'County Lab Coordinator'
        ];
    }

    // Sort by priority
    $priority_order = ['Critical' => 1, 'High' => 2, 'Medium' => 3, 'Low' => 4];
    usort($recommendations, function($a, $b) use ($priority_order) {
        return $priority_order[$a['priority']] <=> $priority_order[$b['priority']];
    });

    return $recommendations;
}

function identifyCountyGaps($assessment) {
    $gaps = [];

    $gap_indicators = [
        ['Governance', 'Integration Oversight Team', $assessment['has_integration_oversight_team'] ?? 'No', 'Yes'],
        ['Governance', 'HIV Integration Oversight', $assessment['has_hiv_integration_oversight'] ?? 'No', 'Yes'],
        ['Governance', 'HRH Transition Plan', $assessment['has_hrh_transition_plan'] ?? 'No', 'Yes'],
        ['Governance', 'PLHIV SHA Plan', $assessment['has_plhiv_sha_plan'] ?? 'No', 'Yes'],
        ['Governance', 'TA/Mentorship Plan', $assessment['has_ta_mentorship_plan'] ?? 'No', 'Yes'],
        ['Service Integration', 'HIV/TB Integration Plan', $assessment['hiv_tb_integration_plan'] ?? 'No', 'Yes'],
        ['Digital Health', 'Selected EMR Type', $assessment['selected_emr_type'] ?? '', 'Not Empty'],
        ['Digital Health', 'HIS Integration Guide', $assessment['has_his_integration_guide'] ?? 'No', 'Yes'],
        ['Digital Health', 'Dedicated HIS Staff', $assessment['has_dedicated_his_staff'] ?? 'No', 'Yes'],
        ['Financial', 'FIF Collection Plan', $assessment['has_fif_collection_plan'] ?? 'No', 'Yes'],
        ['Financial', 'SHA Capitation', $assessment['receives_sha_capitation'] ?? 'No', 'Yes'],
        ['Financial', 'Stakeholder Engagement Plan', $assessment['has_stakeholder_engagement_plan'] ?? 'No', 'Yes'],
        ['Lab', 'ISRS Operational Plan', $assessment['has_isrs_operational_plan'] ?? 'No', 'Yes'],
        ['Lab', 'Lab Strategic Plan', $assessment['has_lab_strategic_plan'] ?? 'No', 'Yes'],
        ['Lab', 'LMIS Integration Guide', $assessment['has_lmis_integration_guide'] ?? 'No', 'Yes']
    ];

    foreach ($gap_indicators as $indicator) {
        list($category, $indicator_name, $current, $target) = $indicator;
        if ($current != $target) {
            $gaps[] = [
                'category' => $category,
                'indicator' => $indicator_name,
                'current' => $current ?: 'Missing',
                'target' => $target,
                'severity' => ($current === 'No' || $current === '') ? 'High' : 'Medium'
            ];
        }
    }

    return $gaps;
}

function createCountyPhasedTimeline($readiness_score) {
    $score = $readiness_score['score'];
    $level = $readiness_score['level'];

    if ($score >= 80) {
        $months = 6;
        $phases = [
            ['Phase 1: Final Preparation', 1, 2, 'Complete remaining integration activities, finalize EMR deployment, and establish monitoring systems.'],
            ['Phase 2: Full Integration Launch', 3, 4, 'Launch fully integrated services across all county facilities, transition all IP-supported activities.'],
            ['Phase 3: Monitoring & Optimization', 5, 6, 'Monitor integrated service performance, address gaps, and optimize processes.']
        ];
    } elseif ($score >= 60) {
        $months = 9;
        $phases = [
            ['Phase 1: Foundation Building', 1, 3, 'Establish integration steering committee, develop transition plan, conduct facility baseline assessments.'],
            ['Phase 2: Service Integration', 4, 6, 'Pilot integration in high-volume facilities, expand EMR deployment, strengthen TWGs.'],
            ['Phase 3: Full Transition', 7, 9, 'Complete integration across all facilities, transition HRH, establish sustainability mechanisms.']
        ];
    } elseif ($score >= 40) {
        $months = 12;
        $phases = [
            ['Phase 1: Readiness Assessment', 1, 3, 'Conduct comprehensive gap analysis, develop detailed workplan, address critical infrastructure gaps.'],
            ['Phase 2: Capacity Building', 4, 7, 'Train county and facility staff, implement EMR system, strengthen leadership commitment.'],
            ['Phase 3: Phased Integration', 8, 10, 'Integrate services starting with high-volume facilities, expand to all levels.'],
            ['Phase 4: Transition & Handover', 11, 12, 'Transition IP-supported activities, establish county financing, implement post-handover support.']
        ];
    } else {
        $months = 18;
        $phases = [
            ['Phase 1: Infrastructure & Governance', 1, 4, 'Address critical infrastructure gaps, establish governance structures, secure county government commitment.'],
            ['Phase 2: Systems Strengthening', 5, 9, 'Implement EMR system, train staff, develop policies, strengthen HRH capacity.'],
            ['Phase 3: Service Integration', 10, 14, 'Phased integration starting with high-volume facilities, then all levels.'],
            ['Phase 4: Sustainability & Handover', 15, 18, 'Ensure financial sustainability, transition to county-led services, establish monitoring mechanisms.']
        ];
    }

    return [
        'total_months' => $months,
        'start_date' => date('F Y'),
        'end_date' => date('F Y', strtotime("+$months months")),
        'phases' => $phases
    ];
}

function generateCountyDetailedRecommendations($assessment) {
    $recommendations = [];

    // HRH Transition detailed
    $total_pepfar = $assessment['hcw_total_pepfar'] ?? 0;
    $total_transitioned = $assessment['hcw_transitioned_total'] ?? 0;

    if ($total_pepfar > 0 && $total_transitioned < $total_pepfar) {
        $remaining = $total_pepfar - $total_transitioned;
        $recommendations[] = [
            'question' => 'Section 3',
            'category' => 'HRH Transition',
            'response' => "{$total_transitioned} of {$total_pepfar} HCWs transitioned",
            'recommendation' => "?? {$remaining} HCWs remain on PEPFAR support. Urgently develop absorption plan prioritizing clinical and data staff.",
            'action_items' => [
                'Develop prioritized absorption schedule',
                'Engage County Public Service Board',
                'Budget for additional payroll positions',
                'Document lessons from successful transitions'
            ],
            'timeline' => '3-6 months',
            'priority' => 'High'
        ];
    }

    // TWG functionality
    $twgs = [
        'has_hiv_tb_twg' => 'HIV/TB TWG',
        'has_pmtct_twg' => 'PMTCT TWG',
        'has_mnch_twg' => 'MNCH TWG',
        'has_hiv_prevention_twg' => 'HIV Prevention TWG'
    ];

    foreach ($twgs as $field => $name) {
        if (($assessment[$field] ?? '') !== 'Yes') {
            $recommendations[] = [
                'question' => 'Section 8',
                'category' => 'Technical Working Groups',
                'response' => 'Not functional',
                'recommendation' => "?? {$name} not functional. Reconstitute with clear terms of reference and quarterly meeting schedule.",
                'action_items' => [
                    'Identify chairperson and secretariat',
                    'Develop workplan and budget',
                    'Schedule quarterly meetings',
                    'Link TWG outputs to county planning'
                ],
                'timeline' => '1-2 months',
                'priority' => 'Medium'
            ];
            break;
        }
    }

    // EMR deployment
    if (($assessment['emr_deployment_meetings'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'question' => 'Section 2b',
            'category' => 'EMR Integration',
            'response' => 'No deployment meetings',
            'recommendation' => "?? No EMR deployment meetings held. Critical to establish governance structure for digital health rollout.",
            'action_items' => [
                'Form EMR steering committee',
                'Develop phased deployment plan',
                'Conduct facility readiness assessments',
                'Schedule monthly deployment review meetings'
            ],
            'timeline' => '1-2 months',
            'priority' => 'High'
        ];
    }

    // FIF Collection
    if (($assessment['has_fif_collection_plan'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'question' => 'Section 9',
            'category' => 'Financial Sustainability',
            'response' => 'No FIF plan',
            'recommendation' => "?? County lacks FIF collection plan incorporating HIV/TB services. Critical for local revenue generation.",
            'action_items' => [
                'Develop FIF collection policy',
                'Train facility staff on FIF documentation',
                'Ensure HIV/TB services are included',
                'Quarterly FIF utilization review'
            ],
            'timeline' => '2-4 months',
            'priority' => 'High'
        ];
    }

    // PLHIV SHA Plan
    if (($assessment['has_plhiv_sha_plan'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'question' => 'Section 4',
            'category' => 'SHA Enrollment',
            'response' => 'No plan',
            'recommendation' => "?? No county plan for PLHIV SHA enrollment. This represents lost revenue and missed opportunity for client-centered care.",
            'action_items' => [
                'Develop county PLHIV SHA enrollment strategy',
                'Assign SHA enrollment champions at facility level',
                'Track enrollment and premium payment',
                'Support claims submission for HIV/TB services'
            ],
            'timeline' => '2-3 months',
            'priority' => 'High'
        ];
    }

    // Lab Strategic Plan
    if (($assessment['has_lab_strategic_plan'] ?? '') !== 'Yes') {
        $recommendations[] = [
            'question' => 'Section 6',
            'category' => 'Lab Support',
            'response' => 'No strategic plan',
            'recommendation' => "?? County lacks laboratory strategic plan. Essential for guiding lab services integration and quality improvement.",
            'action_items' => [
                'Develop lab strategic plan with budget',
                'Include QMS activities and equipment maintenance',
                'Strengthen specimen referral systems',
                'Plan for ISO 15189 accreditation'
            ],
            'timeline' => '4-6 months',
            'priority' => 'Medium'
        ];
    }

    // Sort by priority
    $priority_order = ['Critical' => 1, 'High' => 2, 'Medium' => 3, 'Low' => 4];
    usort($recommendations, function($a, $b) use ($priority_order) {
        return ($priority_order[$a['priority']] ?? 5) <=> ($priority_order[$b['priority']] ?? 5);
    });

    return $recommendations;
}

function getWorkplanHTML($workplan) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Integration Workplan - <?= htmlspecialchars($workplan['county_name']) ?> County</title>
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

            .action-list { margin-top: 8px; padding-left: 20px; }
            .action-list li { margin: 3px 0; font-size: 12px; }

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

            .sub-label {
                font-size: 13px;
                font-weight: 700;
                color: #0D1A63;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                margin: 20px 0 12px;
                padding-bottom: 6px;
                border-bottom: 1px solid #e8edf8;
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
                .page-break { page-break-before: always; }
            }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="page-header">
            <h1>County Integration Transition Workplan</h1>
            <div class="subtitle">HIV/TB Service Integration into County Health Systems</div>
            <div style="margin-top: 8px;"><?= htmlspecialchars($workplan['county_name']) ?> County | Agency: <?= htmlspecialchars($workplan['agency_name']) ?> | IP: <?= htmlspecialchars($workplan['ip_name']) ?></div>
        </div>

        <!-- Workplan Meta Information -->
        <div class="workplan-meta">
            <div class="meta-item"><div class="label">Assessment Period</div><div class="value"><?= htmlspecialchars($workplan['assessment_period']) ?></div></div>
            <div class="meta-item"><div class="label">Integration Readiness</div><div class="value"><span class="readiness-badge badge-<?= $workplan['readiness_color'] ?>"><?= $workplan['readiness_level'] ?> (<?= $workplan['readiness_score'] ?>%)</span></div></div>
            <div class="meta-item"><div class="label">Assessment Date</div><div class="value"><?= date('d M Y', strtotime($workplan['collection_date'])) ?></div></div>
            <div class="meta-item"><div class="label">Assessed By</div><div class="value"><?= htmlspecialchars($workplan['collected_by']) ?></div></div>
        </div>

        <!-- Executive Summary -->
        <div class="card">
            <div class="card-header">Executive Summary</div>
            <div class="card-body">
                <p>This integration workplan outlines the transition from vertically supported HIV/TB services to county-led integrated service delivery for <strong><?= htmlspecialchars($workplan['county_name']) ?> County</strong>. Based on the integration assessment conducted in <strong><?= $workplan['assessment_period'] ?></strong>, the county has been classified as <strong><?= $workplan['readiness_level'] ?></strong> with an overall integration readiness score of <strong><?= $workplan['readiness_score'] ?>%</strong>.</p>
                <p style="margin-top: 10px;">The county currently has <strong><?= number_format($workplan['key_metrics']['hcw_pepfar']) ?></strong> HCWs supported by PEPFAR, with <strong><?= number_format($workplan['key_metrics']['hcw_transitioned']) ?></strong> (<?= $workplan['key_metrics']['transition_percentage'] ?>%) already transitioned to county payroll. The transition period will run from <strong><?= $workplan['timeline']['start_date'] ?></strong> to <strong><?= $workplan['timeline']['end_date'] ?></strong> (<?= $workplan['timeline']['total_months'] ?> months).</p>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="section-title">Key Performance Indicators</div>
        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['hcw_pepfar']) ?></div><div class="kpi-label">HCWs PEPFAR Supported</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['hcw_transitioned']) ?></div><div class="kpi-label">HCWs Transitioned to County</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= $workplan['key_metrics']['transition_percentage'] ?>%</div><div class="kpi-label">Transition Rate</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['facilities_visited_ta']) ?></div><div class="kpi-label">Facilities Visited (TA)</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= number_format($workplan['key_metrics']['stakeholder_meetings']) ?></div><div class="kpi-label">Stakeholder Meetings</div></div>
            <div class="kpi-card"><div class="kpi-value"><?= $workplan['twg_status']['count'] ?></div><div class="kpi-label">Active TWGs</div></div>
        </div>

        <!-- Recommended Integration Model -->
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

        <!-- Gaps Analysis -->
        <div class="section-title">Gaps Analysis</div>
        <div class="card">
            <div class="card-body">
                <table class="gap-table">
                    <thead>
                        <tr><th>Category</th><th>Indicator</th><th>Current Status</th><th>Target Status</th><th>Severity</th></thead>
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

        <!-- Strategic Recommendations -->
        <div class="section-title">Strategic Recommendations</div>
        <div class="card">
            <div class="card-body">
                <?php foreach ($workplan['recommendations'] as $rec):
                    $rec_class = $rec['priority'] == 'Critical' ? 'rec-critical' : ($rec['priority'] == 'High' ? 'rec-high' : ($rec['priority'] == 'Medium' ? 'rec-medium' : 'rec-low'));
                ?>
                <div class="recommendation-item <?= $rec_class ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                        <strong style="font-size: 13px;"><?= htmlspecialchars($rec['category']) ?>: <?= htmlspecialchars($rec['title']) ?></strong>
                        <div>
                            <span class="priority-badge <?= $rec['priority'] == 'Critical' ? 'priority-critical' : ($rec['priority'] == 'High' ? 'priority-high' : ($rec['priority'] == 'Medium' ? 'priority-medium' : 'priority-low')) ?>">
                                <?= $rec['priority'] ?> Priority
                            </span>
                            <span style="margin-left: 5px; font-size: 10px; color: #666;">Timeline: <?= $rec['timeline'] ?></span>
                        </div>
                    </div>
                    <p style="margin-bottom: 8px; font-size: 12px;"><?= htmlspecialchars($rec['description']) ?></p>
                    <div>
                        <strong>Key Action Items:</strong>
                        <ul class="action-list">
                            <?php foreach ($rec['actions'] as $action): ?>
                            <?php if (!empty($action)): ?>
                            <li><?= htmlspecialchars($action) ?></li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        <div style="margin-top: 8px; font-size: 11px; color: #666;">
                            <strong>Responsible:</strong> <?= htmlspecialchars($rec['responsible']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Phased Transition Timeline -->
        <div class="section-title">Phased Transition Timeline</div>
        <div class="card">
            <div class="card-body">
                <table class="timeline-table">
                    <thead>
                        <tr><th>Phase</th><th>Duration</th><th>Key Activities</th></thead>
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

        <!-- TWG Status -->
        <div class="section-title">Technical Working Groups Status</div>
        <div class="card">
            <div class="card-body">
                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['twg_status']['count'] ?></div><div class="kpi-label">Active TWGs</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['twg_status']['meetings']['hiv_tb'] ?></div><div class="kpi-label">HIV/TB TWG Meetings</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['twg_status']['meetings']['pmtct'] ?></div><div class="kpi-label">PMTCT TWG Meetings</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['twg_status']['meetings']['mnch'] ?></div><div class="kpi-label">MNCH TWG Meetings</div></div>
                </div>
                <?php if (!empty($workplan['twg_status']['names'])): ?>
                <div style="margin-top: 12px;">
                    <strong>Active TWGs:</strong> <?= implode(', ', $workplan['twg_status']['names']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EMR & Digital Health Status -->
        <div class="section-title">EMR & Digital Health Status</div>
        <div class="card">
            <div class="card-body">
                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                    <div class="kpi-card"><div class="kpi-value"><?= htmlspecialchars($workplan['emr_status']['selected_type']) ?></div><div class="kpi-label">Selected EMR Type</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['emr_status']['has_his_integration_guide'] ? 'Yes' : 'No' ?></div><div class="kpi-label">HIS Integration Guide</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['emr_status']['has_dedicated_his_staff'] ? 'Yes' : 'No' ?></div><div class="kpi-label">Dedicated HIS Staff</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['emr_status']['deployment_meetings'] ? 'Yes' : 'No' ?></div><div class="kpi-label">Deployment Meetings</div></div>
                </div>
            </div>
        </div>

        <!-- HRH & Financial Status -->
        <div class="section-title">HRH & Financial Sustainability</div>
        <div class="card">
            <div class="card-body">
                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); margin-bottom: 15px;">
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['hrh_status']['has_transition_plan'] ? 'Yes' : 'No' ?></div><div class="kpi-label">HRH Transition Plan</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['hrh_status']['leadership_commitment'] ?></div><div class="kpi-label">Leadership Commitment</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['hrh_status']['hiv_in_awp'] ?></div><div class="kpi-label">HIV in AWP</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['hrh_status']['hrh_gap'] ?></div><div class="kpi-label">HRH Gap</div></div>
                </div>
                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['financial_status']['has_fif_plan'] ? 'Yes' : 'No' ?></div><div class="kpi-label">FIF Collection Plan</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['financial_status']['receives_sha_capitation'] ? 'Yes' : 'No' ?></div><div class="kpi-label">SHA Capitation</div></div>
                    <div class="kpi-card"><div class="kpi-value"><?= $workplan['financial_status']['has_stakeholder_plan'] ? 'Yes' : 'No' ?></div><div class="kpi-label">Stakeholder Plan</div></div>
                </div>
                <?php if (!empty($workplan['barriers'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 6px;">
                    <strong>Key Barriers:</strong> <?= nl2br(htmlspecialchars($workplan['barriers'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detailed Question-by-Question Recommendations -->
        <div class="section-title">Detailed Actionable Recommendations by Section</div>
        <div class="card">
            <div class="card-body">
                <p style="margin-bottom: 15px; color: #666;">The following recommendations are based on specific responses to each assessment section:</p>

                <?php if (!empty($workplan['detailed_recommendations'])): ?>
                    <?php
                    $current_category = '';
                    foreach ($workplan['detailed_recommendations'] as $rec):
                        $priority_class = $rec['priority'] == 'Critical' ? 'priority-critical' :
                                         ($rec['priority'] == 'High' ? 'priority-high' :
                                         ($rec['priority'] == 'Medium' ? 'priority-medium' : 'priority-low'));
                        $border_class = $rec['priority'] == 'Critical' ? 'rec-critical' :
                                       ($rec['priority'] == 'High' ? 'rec-high' :
                                       ($rec['priority'] == 'Medium' ? 'rec-medium' : 'rec-low'));
                    ?>
                        <?php if ($current_category != $rec['category']): ?>
                            <?php if ($current_category != ''): ?></div><?php endif; ?>
                            <div class="sub-label" style="margin-top: 15px;">
                                <i class="fas fa-folder-open"></i> <?= htmlspecialchars($rec['category']) ?>
                            </div>
                            <div style="margin-top: 10px;">
                            <?php $current_category = $rec['category']; ?>
                        <?php endif; ?>

                        <div class="recommendation-item <?= $border_class ?>" style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                                <strong style="font-size: 12px;"><?= htmlspecialchars($rec['question']) ?></strong>
                                <div>
                                    <span class="priority-badge <?= $priority_class ?>"><?= $rec['priority'] ?> Priority</span>
                                    <span style="margin-left: 5px; font-size: 10px; color: #666;">Timeline: <?= $rec['timeline'] ?></span>
                                </div>
                            </div>
                            <p style="margin-bottom: 8px; font-size: 12px; background: #f8f9fa; padding: 6px 10px; border-radius: 4px;">
                                <strong>Response:</strong> <?= htmlspecialchars($rec['response']) ?>
                            </p>
                            <p style="margin-bottom: 8px; font-size: 12px;"><?= htmlspecialchars($rec['recommendation']) ?></p>
                            <div>
                                <strong>Action Items:</strong>
                                <ul class="action-list">
                                    <?php foreach ($rec['action_items'] as $action): ?>
                                        <li><?= htmlspecialchars($action) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No detailed recommendations available.</p>
                <?php endif; ?>
            </div>
        </div>

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

function exportToPDF($workplan, $conn) {
    $html = getWorkplanHTML($workplan);

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

function exportToWord($workplan, $conn) {
    $html = getWorkplanHTML($workplan);

    $html = str_replace('</head>',
        '<meta charset="UTF-8">
        <meta name="generator" content="Microsoft Word 15">
        <meta name="ProgId" content="Word">
        <style>
            @page { size: A4; margin: 2.54cm; }
            body { margin: 0; padding: 20px; }
        </style>
        </head>',
        $html);

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="County_Integration_Workplan_' . str_replace(' ', '_', $workplan['county_name']) . '_' . date('Ymd') . '.doc"');
    header('Cache-Control: max-age=0');

    echo $html;
    exit();
}

function renderWorkplan($workplan, $assessment, $conn) {
    $html = getWorkplanHTML($workplan);

    $html = str_replace('</head>',
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>',
        $html);

    $html = str_replace('</body>', '
    <div style="position: fixed; bottom: 20px; right: 20px; display: flex; gap: 10px; z-index: 1000;" class="no-print">
        <a href="?id=' . $assessment['assessment_id'] . '&export=pdf"
           style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 600;">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <a href="?id=' . $assessment['assessment_id'] . '&export=word"
           style="background: #0D1A63; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 600;">
            <i class="fas fa-file-word"></i> Export Word
        </a>
        <button onclick="window.print()"
                style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    </body>', $html);

    echo $html;
}
?>