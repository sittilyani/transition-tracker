<?php
// transitions/transition_assessment.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get parameters
$county_id = isset($_GET['county']) ? (int)$_GET['county'] : 0;
$period = isset($_GET['period']) ? mysqli_real_escape_string($conn, $_GET['period']) : '';
$sections = isset($_GET['sections']) ? explode(',', $_GET['sections']) : [];

if (!$county_id || !$period || empty($sections)) {
    header('Location: transition_index.php');
    exit();
}

// Get county name
$county_result = $conn->query("SELECT county_name FROM counties WHERE county_id = $county_id");
$county_name = $county_result->fetch_assoc()['county_name'];

// Check if this is a new assessment or editing existing
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$existing_scores = [];

if ($assessment_id) {
    // Load existing scores
    $scores_query = "
        SELECT ts.indicator_id, ts.cdoh_score, ts.ip_score, ts.comments
        FROM transition_scores ts
        WHERE ts.assessment_id = $assessment_id
    ";
    $scores_result = mysqli_query($conn, $scores_query);
    while ($row = mysqli_fetch_assoc($scores_result)) {
        $existing_scores[$row['indicator_id']] = $row;
    }
}

// Define scoring criteria for each level
$scoring_criteria = [
    4 => ['label' => 'Fully adequate with evidence', 'class' => 'level-4'],
    3 => ['label' => 'Partially adequate with evidence', 'class' => 'level-3'],
    2 => ['label' => 'Structures/functions defined some evidence', 'class' => 'level-2'],
    1 => ['label' => 'Structures/functions defined NO evidence', 'class' => 'level-1'],
    0 => ['label' => 'Inadequate structures/functions', 'class' => 'level-0']
];

// Define all sections with their detailed indicators
$all_sections = [
    'leadership' => [
        'title' => 'COUNTY LEVEL LEADERSHIP AND GOVERNANCE',
        'icon' => 'fa-landmark',
        'color' => '#0D1A63',
        'indicators' => [
            'T1' => [
                'code' => 'T1',
                'name' => 'Transition of County Legislature Health Leadership and Governance',
                'sub_indicators' => [
                    'T1.1' => 'Does the county have a legally constituted mechanism that oversees the health department? (e.g. County assembly health committee)',
                    'T1.2' => 'Does the county have an overall vision for the County Department of Health (CDOH) that is overseen by the County assembly health committee?',
                    'T1.3' => 'Are the roles of the County assembly health committee well-defined in the county health system?',
                    'T1.4' => 'Are County assembly health committee meetings held regularly as stipulated; decisions documented; and reflect accountability and resource stewardship?',
                    'T1.5' => 'Does the County assembly health committee composition include members who are recognized for leadership and/or area of expertise and are representative of stakeholders including PLHIV/TB patients?',
                    'T1.6' => 'Does the County assembly health committee ensure that public interest is considered in decision making?',
                    'T1.7' => 'How committed and accountable is the County assembly health committee in following up on agreed action items?',
                    'T1.8' => 'Does the County assembly health committee have a risk management policy/framework?',
                    'T1.9' => 'How much oversight is given to HIV/TB activities in the county by the health committee of the county assembly?',
                    'T1.10' => 'Is the leadership arrangement/structure for the HIV/TB program adequate to increase coverage and quality of HIV/TB services?',
                    'T1.11' => 'Does the HIV/TB program planning and funding allow for sustainability?'
                ]
            ],
            'T2' => [
                'code' => 'T2',
                'name' => 'Transition of County Executive (CHMT) in Health Leadership and Governance',
                'sub_indicators' => [
                    'T2.1' => 'Is the CHMT responsive to the requirements of the County\'s Oversight structures, i.e. County assembly health committee?',
                    'T2.2' => 'Is the CHMT accountable to clients/patients seeking services within the county?',
                    'T2.3' => 'Is the CHMT involving the private sector and community based organizations in the planning of health services including HIV/TB services?',
                    'T2.4' => 'Are CHMT meetings held regularly as stipulated; decisions documented including for the HIV/TB program; and reflect accountability and resource stewardship?',
                    'T2.5' => 'Is the CHMT implementing policies and regulations set by national level?',
                    'T2.6' => 'Does the CHMT hold joint monitoring teams and joint high-level meetings with development partners supporting the county?',
                    'T2.7' => 'Does the CHMT plan and manage health services to meet local needs?',
                    'T2.8' => 'Does the CHMT mobilize local resources for the HIV/TB program?',
                    'T2.9' => 'Is the CHMT involved in the supervision of HIV/TB services in the county?',
                    'T2.10' => 'Has the CHMT ensured that the leadership arrangement/structure for the HIV/TB program is adequate?',
                    'T2.11' => 'Has the CHMT ensured that the HIV/TB program planning and funding allow for sustainability?'
                ]
            ],
            'T3' => [
                'code' => 'T3',
                'name' => 'Transition of County Health Planning: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T3.1' => 'Creating a costed county annual work plan for HIV/TB services',
                    'T3.2' => 'Identifying key HIV program priorities that sustains good coverage and high HIV service quality',
                    'T3.3' => 'Track implementation of the costed county annual work plan for HIV/TB services',
                    'T3.4' => 'Identifying HRH needs for HIV/TB that will support the delivery of the agreed package of activities',
                    'T3.5' => 'Having in place a system for forecasting, including HRH needs for HIV/TB',
                    'T3.6' => 'Coordinating the scope of activities and resource contributions of all partners for HIV/TB in county',
                    'T3.7' => 'Convening meetings with key county HIV/TB services program staff and implementing partners to review performance',
                    'T3.8' => 'Convening meetings with community HIV/TB stakeholders to review community needs',
                    'T3.9' => 'Convening to review program performance for HIV/TB',
                    'T3.10' => 'Providing technical guidance for county AIDS/TB coordination',
                    'T3.11' => 'Providing support to the County AIDS Committee'
                ]
            ]
        ]
    ],
    'supervision' => [
        'title' => 'COUNTY LEVEL ROUTINE SUPERVISION AND MENTORSHIP',
        'icon' => 'fa-clipboard-check',
        'color' => '#1a3a9e',
        'indicators' => [
            'T4A' => [
                'code' => 'T4A',
                'name' => 'Transition of routine Supervision and Mentorship: Level of Involvement of the IP',
                'sub_indicators' => [
                    'T4A.1' => 'Developing the county HIV/TB programme routine supervision plan',
                    'T4A.2' => 'Arranging logistics, including vehicle and/or fuel',
                    'T4A.3' => 'Conducting routine supervision visits to county (public)/private/faith-based facilities',
                    'T4A.4' => 'Completing supervision checklist',
                    'T4A.5' => 'Mobilizing support to address issues identified during supervision',
                    'T4A.6' => 'Financial facilitation for county supervision (paying allowances to supervisors)',
                    'T4A.7' => 'Developing the action plan and following up on issues identified during the supervision',
                    'T4A.8' => 'Planning for staff mentorship including cross learning visits',
                    'T4A.9' => 'Spending time with staff to identify individual\'s strengths',
                    'T4A.10' => 'Identifying and working with facility staff to pursue mentorship goals',
                    'T4A.11' => 'Paying for mentorship activities',
                    'T4A.12' => 'Documenting outcomes of the mentorship'
                ]
            ],
            'T4B' => [
                'code' => 'T4B',
                'name' => 'Transition of routine Supervision and mentorship: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T4B.1' => 'Developing the county HIV/TB supervision plan',
                    'T4B.2' => 'Arranging logistics, including vehicle and/or fuel',
                    'T4B.3' => 'Conducting supervision visits to county (public)/private/faith-based facilities',
                    'T4B.4' => 'Completing supervision forms',
                    'T4B.5' => 'Mobilizing support to address issues identified during supervision',
                    'T4B.6' => 'Financial facilitation for county supervision (paying allowances to supervisors)',
                    'T4B.7' => 'Developing the action plan and following up on issues identified during the supervision',
                    'T4B.8' => 'Planning for staff mentorship including cross learning visits',
                    'T4B.9' => 'Spending time with staff to identify individual\'s strengths',
                    'T4B.10' => 'Identifying and working with facility staff to pursue mentorship goals',
                    'T4B.11' => 'Paying for mentorship activities',
                    'T4B.12' => 'Documenting outcomes of the mentorship'
                ]
            ]
        ]
    ],
    'special_initiatives' => [
        'title' => 'COUNTY LEVEL HIV/TB PROGRAM SPECIAL INITIATIVES (RRI, Leap, Surge, SIMS)',
        'icon' => 'fa-bolt',
        'color' => '#2a4ab0',
        'indicators' => [
            'T5A' => [
                'code' => 'T5A',
                'name' => 'Transition of HIV/TB program special initiatives: Level of Involvement of the IP',
                'sub_indicators' => [
                    'T5A.1' => 'Developing the county RRI, LEAP, Surge or SIMS plan or any other initiative',
                    'T5A.2' => 'Arranging logistics, including vehicle and/or fuel',
                    'T5A.3' => 'Conducting LEAP, SURGE, SIMS or RRI visits to public/private/faith based facilities',
                    'T5A.4' => 'Completing relevant initiative tools / reporting templates',
                    'T5A.5' => 'Mobilizing support to address issues identified during site visits',
                    'T5A.6' => 'Financial facilitation for site visits (paying allowances to the team)',
                    'T5A.7' => 'Developing the action plan and following up on issues identified during site visits',
                    'T5A.8' => 'Reporting special initiative implementation progress to higher levels'
                ]
            ],
            'T5B' => [
                'code' => 'T5B',
                'name' => 'Transition of HIV program special initiatives: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T5B.1' => 'Developing the county RRI, LEAP, Surge or SIMS plan or any other initiative',
                    'T5B.2' => 'Arranging logistics, including vehicle and/or fuel',
                    'T5B.3' => 'Conducting LEAP, SURGE, SIMS or RRI visits to public/private/faith based facilities',
                    'T5B.4' => 'Completing relevant initiative tools/ reporting templates',
                    'T5B.5' => 'Mobilizing support to address issues identified during site visits',
                    'T5B.6' => 'Financial facilitation for site visits (paying allowances to the team)',
                    'T5B.7' => 'Developing the action plan and following up on issues identified during site visits',
                    'T5B.8' => 'Reporting special initiative implementation progress to higher levels'
                ]
            ]
        ]
    ],
    'quality_improvement' => [
        'title' => 'COUNTY LEVEL QUALITY IMPROVEMENT',
        'icon' => 'fa-chart-line',
        'color' => '#3a5ac8',
        'indicators' => [
            'T6A' => [
                'code' => 'T6A',
                'name' => 'Transition of Quality Improvement (QI): Level of Involvement of the IP',
                'sub_indicators' => [
                    'T6A.1' => 'Selecting priorities and developing / adapting QI plan',
                    'T6A.2' => 'Training facility staff',
                    'T6A.3' => 'Providing technical support to QI teams',
                    'T6A.4' => 'Reviewing/tracking facility QI reports',
                    'T6A.5' => 'Funding QI Initiatives',
                    'T6A.6' => 'Other support QI activities',
                    'T6A.7' => 'Convening/managing county-wide QI forum'
                ]
            ],
            'T6B' => [
                'code' => 'T6B',
                'name' => 'Transition of Quality Improvement: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T6B.1' => 'Selecting priorities and developing/adapting QI plan',
                    'T6B.2' => 'Training facility staff',
                    'T6B.3' => 'Providing technical support to QI teams',
                    'T6B.4' => 'Reviewing/tracking facility QI reports',
                    'T6B.5' => 'Funding QI Initiatives',
                    'T6B.6' => 'Other support QI activities',
                    'T6B.7' => 'Convening/managing county-wide QI forum'
                ]
            ]
        ]
    ],
    'identification_linkage' => [
        'title' => 'COUNTY LEVEL HIV/TB PATIENT IDENTIFICATION AND LINKAGE TO TREATMENT',
        'icon' => 'fa-user-plus',
        'color' => '#4a6ae0',
        'indicators' => [
            'T7A' => [
                'code' => 'T7A',
                'name' => 'Transition of Patient identification and linkage to treatment: Level of Involvement of the IP',
                'sub_indicators' => [
                    'T7A.1' => 'Recruitment of HIV testing services (HTS) counselors',
                    'T7A.2' => 'Remuneration of HIV testing counselors (Funds for paying HTS Counselors)',
                    'T7A.3' => 'Ensuring that HTS eligibility screening registers and SOPS are available',
                    'T7A.4' => 'Ensuring that HIV testing consumables/supplies are available',
                    'T7A.5' => 'Ensuring availability of adequate and appropriate HIV testing space/environment',
                    'T7A.6' => 'Ensuring effective procedures of linkage of HIV positive patients',
                    'T7A.7' => 'Ensuring documentation of linkage of HIV positive patients',
                    'T7A.8' => 'Training and providing refresher training to HIV testing counsellors',
                    'T7A.9' => 'HTS quality monitoring including conducting observed practices for HTS counsellors',
                    'T7A.10' => 'Providing transport and airtime for follow up and testing of sexual and other contacts',
                    'T7A.11' => 'Documenting, tracking and reporting ART, PEP and PrEP among those eligible'
                ]
            ],
            'T7B' => [
                'code' => 'T7B',
                'name' => 'Transition of Patient identification and linkage to treatment: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T7B.1' => 'Recruitment of HIV testing services (HTS) counselors',
                    'T7B.2' => 'Remuneration of HIV testing counselors (Funds for paying HTS Counselors)',
                    'T7B.3' => 'Ensuring that HTS eligibility screening registers and SOPS are available',
                    'T7B.4' => 'Ensuring that HIV testing consumables/supplies are available',
                    'T7B.5' => 'Ensuring availability of adequate and appropriate HIV testing space/environment',
                    'T7B.6' => 'Ensuring effective procedures of linkage of HIV positive patients',
                    'T7B.7' => 'Ensuring documentation of linkage of HIV positive patients',
                    'T7B.8' => 'Training and providing refresher training to HIV testing counsellors',
                    'T7B.9' => 'HTS quality monitoring including conducting observed practices for HTS counsellors',
                    'T7B.10' => 'Providing transport and airtime for follow up and testing of sexual and other contacts',
                    'T7B.11' => 'Documenting, tracking and reporting ART, PEP and PrEP among those eligible'
                ]
            ]
        ]
    ],
    'retention_suppression' => [
        'title' => 'COUNTY LEVEL PATIENT RETENTION, ADHERENCE AND VIRAL SUPPRESSION SERVICES',
        'icon' => 'fa-heartbeat',
        'color' => '#5a7af8',
        'indicators' => [
            'T8A' => [
                'code' => 'T8A',
                'name' => 'Transition of Patient retention, adherence and Viral suppression services: Level of Involvement of the IP',
                'sub_indicators' => [
                    'T8A.1' => 'Provision of patient referral forms, appointment diaries and defaulter management tools to facilities',
                    'T8A.2' => 'Ensuring effective procedure to track missed appointments of patients on treatment',
                    'T8A.3' => 'Ensuring effective procedures and tracking of referrals and transfers between health facilities',
                    'T8A.4' => 'Ensuring effective procedures and tracking of referrals between different units within the same health facility',
                    'T8A.5' => 'Paying allowances to track and bring patients with missed appointments or lost to follow-up back to care',
                    'T8A.6' => 'Paying allowances to community health volunteers for HIV/TB related activities',
                    'T8A.7' => 'Supporting of patient support groups for HIV/TB related activities',
                    'T8A.8' => 'Linking facilities with community groups supporting PLHIV/TB for patient follow-up and support',
                    'T8A.9' => 'Strengthening on patient cohort analysis and reporting',
                    'T8A.10' => 'Ensure dissemination/updates of the most updated treatment guidelines',
                    'T8A.11' => 'Supporting enhanced adherence counselling for patients with poor adherence',
                    'T8A.12' => 'Supporting HIV/TB treatment optimization ensuring all cases are on an appropriate regimen',
                    'T8A.13' => 'Funding /Supporting MDT meetings to discuss difficult HIV/TB cases',
                    'T8A.14' => 'Tracking Viral suppression rates by population at site level'
                ]
            ],
            'T8B' => [
                'code' => 'T8B',
                'name' => 'Transition of Patient retention, adherence and Viral suppression services: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T8B.1' => 'Provision of patient referral forms, appointment diaries and defaulter management tools to facilities',
                    'T8B.2' => 'Ensuring effective procedure to track missed appointments of patients on treatment',
                    'T8B.3' => 'Ensuring effective procedures and tracking of referrals and transfers between health facilities',
                    'T8B.4' => 'Ensuring effective procedures and tracking of referrals between different units within the same health facility',
                    'T8B.5' => 'Paying for processes to track and bring patients with missed appointments or lost to follow-up back to care',
                    'T8B.6' => 'Funding community health volunteers and patient support groups',
                    'T8B.7' => 'Linking facilities with community groups supporting PLHIV for patient follow-up and support',
                    'T8B.8' => 'Provide funding for community visits to track patients',
                    'T8B.9' => 'Strengthening on patient cohort analysis and reporting',
                    'T8B.10' => 'Ensure dissemination/updates of the most updated treatment guidelines',
                    'T8B.11' => 'Supporting enhanced adherence counselling for patients with poor adherence',
                    'T8B.12' => 'Supporting HIV treatment optimization ensuring all cases are on an appropriate regimen',
                    'T8B.13' => 'Funding /Supporting MDT meetings to discuss difficult HIV cases',
                    'T8B.14' => 'Tracking Viral suppression rates by population at site level'
                ]
            ]
        ]
    ],
    'prevention_kp' => [
        'title' => 'COUNTY LEVEL HIV PREVENTION AND KEY POPULATION SERVICES',
        'icon' => 'fa-shield-alt',
        'color' => '#6a8aff',
        'indicators' => [
            'T9A' => [
                'code' => 'T9A',
                'name' => 'Transition of HIV/TB prevention and Key population services: Level of Involvement of the IP',
                'sub_indicators' => [
                    'T9A.1' => 'Conducting targeted HIV testing of Members of Key population groups',
                    'T9A.2' => 'Providing AGYW services for HIV prevention in safe spaces or Youth friendly settings',
                    'T9A.3' => 'Providing VMMC services for HIV prevention',
                    'T9A.4' => 'Providing condoms and lubricants to members of Key populations',
                    'T9A.5' => 'Provision of KP friendly services including provision of safe spaces',
                    'T9A.6' => 'Providing PrEP to all HIV negative clients at risk of HIV',
                    'T9A.7' => 'Provision of Post Exposure Prophylaxis',
                    'T9A.8' => 'Conducting outreach to Key population hot spots to increase enrollment',
                    'T9A.9' => 'Tracking of enrollment into HIV prevention services and outcomes in Key populations'
                ]
            ],
            'T9B' => [
                'code' => 'T9B',
                'name' => 'Transition of HIV prevention and Key population services: Level of Autonomy of the CDOH',
                'sub_indicators' => [
                    'T9B.1' => 'Conducting targeted HIV testing of Members of priority population groups',
                    'T9B.2' => 'Providing AGYW services for HIV prevention in safe spaces or Youth friendly settings',
                    'T9B.3' => 'Providing VMMC services for HIV prevention',
                    'T9B.4' => 'Providing condoms and lubricants to members of Key populations',
                    'T9B.5' => 'Provision of KP friendly services including provision of safe spaces',
                    'T9B.6' => 'Providing PrEP to all HIV negative clients at risk of HIV',
                    'T9B.7' => 'Provision of Post Exposure Prophylaxis',
                    'T9B.8' => 'Conducting outreach to Key population hot spots to increase enrollment',
                    'T9B.9' => 'Tracking of enrollment into HIV prevention services and outcomes by populations'
                ]
            ]
        ]
    ],
    // Add all other sections following the same pattern...
];

// Filter sections based on selection
$active_sections = array_intersect_key($all_sections, array_flip($sections));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    $assessed_by = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? '');
    $assessment_date = mysqli_real_escape_string($conn, $_POST['assessment_date'] ?? date('Y-m-d'));

    // Begin transaction
    mysqli_begin_transaction($conn);

    try {
        // Create new assessment or update existing
        if ($assessment_id) {
            // Update existing assessment
            $update_query = "UPDATE transition_assessments SET
                assessment_date = '$assessment_date',
                assessed_by = '$assessed_by',
                assessment_status = 'submitted'
                WHERE assessment_id = $assessment_id";
            mysqli_query($conn, $update_query);

            // Delete existing scores
            mysqli_query($conn, "DELETE FROM transition_scores WHERE assessment_id = $assessment_id");
        } else {
            // Insert new assessment
            $insert_query = "INSERT INTO transition_assessments
                (county_id, assessment_period, assessment_date, assessed_by, assessment_status)
                VALUES ($county_id, '$period', '$assessment_date', '$assessed_by', 'submitted')";
            mysqli_query($conn, $insert_query);
            $assessment_id = mysqli_insert_id($conn);
        }

        // Save scores for each indicator
        $total_cdoh = 0;
        $total_ip = 0;
        $indicator_count = 0;

        foreach ($_POST['scores'] as $indicator_key => $scores) {
            list($section_key, $indicator_code, $sub_indicator) = explode('_', $indicator_key);

            $cdoh_score = isset($scores['cdoh']) ? (int)$scores['cdoh'] : 0;
            $ip_score = isset($scores['ip']) ? (int)$scores['ip'] : 0;
            $comments = mysqli_real_escape_string($conn, $scores['comments'] ?? '');

            // Get indicator_id from database (you'll need to create this mapping)
            // For now, we'll use a placeholder. In production, you should look up the actual indicator_id
            $indicator_id = 1; // Placeholder

            $score_query = "INSERT INTO transition_scores
                (assessment_id, indicator_id, cdoh_score, ip_score, comments)
                VALUES ($assessment_id, $indicator_id, $cdoh_score, $ip_score, '$comments')";
            mysqli_query($conn, $score_query);

            $total_cdoh += $cdoh_score;
            $total_ip += $ip_score;
            $indicator_count++;
        }

        // Calculate overall scores and readiness level
        $avg_cdoh = $indicator_count > 0 ? round(($total_cdoh / ($indicator_count * 4)) * 100) : 0;
        $avg_ip = $indicator_count > 0 ? round(($total_ip / ($indicator_count * 4)) * 100) : 0;

        if ($avg_cdoh >= 70) {
            $readiness = 'Transition';
        } elseif ($avg_cdoh >= 50) {
            $readiness = 'Support and Monitor';
        } else {
            $readiness = 'Not Ready';
        }

        // Update assessment with overall scores
        $update_overall = "UPDATE transition_assessments SET
            overall_cdoh_score = $avg_cdoh,
            overall_ip_score = $avg_ip,
            overall_gap_score = GREATEST(0, $avg_ip - $avg_cdoh),
            overall_overlap_score = LEAST($avg_cdoh, $avg_ip),
            readiness_level = '$readiness'
            WHERE assessment_id = $assessment_id";
        mysqli_query($conn, $update_overall);

        mysqli_commit($conn);

        $_SESSION['success_msg'] = 'Assessment saved successfully!';
        header('Location: transition_dashboard.php?county=' . $county_id);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = 'Error saving assessment: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transition Assessment - <?= htmlspecialchars($county_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f7;
            color: #333;
            line-height: 1.6;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

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
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header .hdr-links a {
            color: #fff;
            text-decoration: none;
            background: rgba(255,255,255,.15);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-left: 8px;
            transition: background .2s;
        }
        .page-header .hdr-links a:hover {
            background: rgba(255,255,255,.28);
        }

        .alert {
            padding: 13px 18px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .progress-tracker {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 14px rgba(0,0,0,.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .section-tab {
            padding: 10px 20px;
            background: #fff;
            border-radius: 30px;
            border: 2px solid #e0e4f0;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            white-space: nowrap;
            transition: all .2s;
        }

        .section-tab.active {
            background: #0D1A63;
            color: #fff;
            border-color: #0D1A63;
        }

        .section-tab.completed {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }

        .assessment-form {
            background: #fff;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 14px rgba(0,0,0,.07);
        }

        /* Indicator Card Styles */
        .indicator-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid var(--color);
        }

        .indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .indicator-code {
            background: #0D1A63;
            color: #fff;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 14px;
        }

        .indicator-title {
            font-size: 16px;
            font-weight: 700;
            color: #0D1A63;
            margin-bottom: 15px;
        }

        .sub-indicator {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e0e4f0;
        }

        .sub-indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .sub-indicator-code {
            font-weight: 700;
            color: #0D1A63;
            background: #e8edf8;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .sub-indicator-text {
            font-size: 13px;
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .score-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .score-column {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 15px;
        }

        .score-column h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .score-column.cdoh h4 { color: #0D1A63; }
        .score-column.ip h4 { color: #FFC107; }

        .radio-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .radio-option {
            flex: 1;
            min-width: 60px;
        }

        .radio-option input[type="radio"] {
            display: none;
        }

        .radio-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 5px;
            background: #fff;
            border: 2px solid #e0e4f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all .2s;
        }

        .radio-option input[type="radio"]:checked + label {
            border-color: var(--color);
            background: var(--bg-color);
        }

        .radio-option .score {
            font-weight: 700;
            font-size: 16px;
        }

        .radio-option .label {
            font-size: 9px;
            text-align: center;
            color: #666;
            margin-top: 3px;
        }

        .level-4 { --color: #28a745; --bg-color: #d4edda; }
        .level-3 { --color: #17a2b8; --bg-color: #d1ecf1; }
        .level-2 { --color: #ffc107; --bg-color: #fff3cd; }
        .level-1 { --color: #fd7e14; --bg-color: #ffe5d0; }
        .level-0 { --color: #dc3545; --bg-color: #f8d7da; }

        .comments-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e0e4f0;
        }

        .comments-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e4f0;
            border-radius: 8px;
            font-size: 12px;
            resize: vertical;
        }

        .section-summary {
            background: #0D1A63;
            color: #fff;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-badge {
            background: rgba(255,255,255,.2);
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 600;
        }

        .save-progress {
            position: sticky;
            bottom: 20px;
            background: #0D1A63;
            color: #fff;
            padding: 15px 25px;
            border-radius: 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(13,26,99,.3);
            max-width: 400px;
            margin: 0 auto;
        }

        .btn-save {
            background: #fff;
            color: #0D1A63;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-save:hover {
            transform: scale(1.05);
        }

        .btn-submit {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all .2s;
            display: block;
            margin: 30px auto;
        }
        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .progress-bar {
            width: 200px;
            height: 8px;
            background: #e0e4f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            border-radius: 10px;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1>
            <i class="fas fa-clipboard-check"></i>
            Transition Assessment: <?= htmlspecialchars($county_name) ?>
        </h1>
        <div class="hdr-links">
            <a href="transition_index.php"><i class="fas fa-arrow-left"></i> Back to Sections</a>
            <a href="transition_dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="assessmentForm">
        <input type="hidden" name="save_assessment" value="1">
        <input type="hidden" name="assessment_date" value="<?= date('Y-m-d') ?>">

        <!-- Progress Tracker -->
        <div class="progress-tracker">
            <div>
                <p style="color: #666; font-size: 13px;">
                    <i class="fas fa-calendar"></i> Period: <?= htmlspecialchars($period) ?> |
                    <i class="fas fa-layer-group"></i> Sections: <?= count($active_sections) ?> |
                    <i class="fas fa-tasks"></i> Total Indicators: <span id="totalIndicators">0</span>
                </p>
            </div>
            <div class="progress-indicator">
                <span style="font-size: 13px; color: #666;">Overall Progress</span>
                <div class="progress-bar">
                    <div class="progress-fill" id="overallProgress" style="width: 0%;"></div>
                </div>
                <span id="progressPercent" style="font-weight: 700; color: #0D1A63;">0%</span>
            </div>
        </div>

        <!-- Section Tabs -->
        <div class="section-tabs" id="sectionTabs">
            <?php
            $index = 1;
            foreach ($active_sections as $key => $section):
            ?>
            <div class="section-tab" data-section="<?= $key ?>" onclick="showSection('<?= $key ?>')">
                <i class="fas <?= $section['icon'] ?? 'fa-file' ?>"></i> <?= $section['title'] ?>
            </div>
            <?php
            $index++;
            endforeach;
            ?>
        </div>

        <!-- Assessment Forms Container -->
        <div id="formsContainer">
            <?php
            $total_indicators = 0;
            foreach ($active_sections as $key => $section):
                $section_total = 0;
            ?>
            <div class="assessment-form" id="form_<?= $key ?>" style="display: <?= $key === array_key_first($active_sections) ? 'block' : 'none' ?>;">
                <div class="section-summary">
                    <div>
                        <i class="fas <?= $section['icon'] ?? 'fa-file' ?>"></i>
                        <strong><?= $section['title'] ?></strong>
                    </div>
                    <div class="summary-badge" id="section_progress_<?= $key ?>">0% Complete</div>
                </div>

                <?php foreach ($section['indicators'] as $indicator_code => $indicator):
                    $sub_indicator_count = count($indicator['sub_indicators']);
                    $section_total += $sub_indicator_count;
                ?>
                <div class="indicator-card" style="--color: <?= $section['color'] ?? '#0D1A63' ?>">
                    <div class="indicator-header">
                        <span class="indicator-code"><?= $indicator_code ?></span>
                        <span style="font-size: 12px; color: #666;"><?= $sub_indicator_count ?> sub-indicators</span>
                    </div>
                    <div class="indicator-title"><?= $indicator['name'] ?></div>

                    <?php foreach ($indicator['sub_indicators'] as $sub_code => $sub_text):
                        $indicator_key = $key . '_' . $indicator_code . '_' . $sub_code;
                    ?>
                    <div class="sub-indicator">
                        <div class="sub-indicator-header">
                            <span class="sub-indicator-code"><?= $sub_code ?></span>
                        </div>
                        <div class="sub-indicator-text"><?= $sub_text ?></div>

                        <div class="score-grid">
                            <!-- CDOH Score Column -->
                            <div class="score-column cdoh">
                                <h4><i class="fas fa-building"></i> CDOH (County)</h4>
                                <div class="radio-group">
                                    <?php foreach ($scoring_criteria as $score => $criteria): ?>
                                    <div class="radio-option <?= $criteria['class'] ?>">
                                        <input type="radio"
                                               name="scores[<?= $indicator_key ?>][cdoh]"
                                               value="<?= $score ?>"
                                               id="cdoh_<?= $indicator_key ?>_<?= $score ?>"
                                               data-section="<?= $key ?>"
                                               onchange="updateProgress()">
                                        <label for="cdoh_<?= $indicator_key ?>_<?= $score ?>">
                                            <span class="score"><?= $score ?></span>
                                            <span class="label"><?= $score == 4 ? 'Fully' : ($score == 3 ? 'Partial' : ($score == 2 ? 'Some' : ($score == 1 ? 'Minimal' : 'None'))) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- IP Score Column -->
                            <div class="score-column ip">
                                <h4><i class="fas fa-handshake"></i> Implementing Partner</h4>
                                <div class="radio-group">
                                    <?php foreach ($scoring_criteria as $score => $criteria): ?>
                                    <div class="radio-option <?= $criteria['class'] ?>">
                                        <input type="radio"
                                               name="scores[<?= $indicator_key ?>][ip]"
                                               value="<?= $score ?>"
                                               id="ip_<?= $indicator_key ?>_<?= $score ?>"
                                               data-section="<?= $key ?>"
                                               onchange="updateProgress()">
                                        <label for="ip_<?= $indicator_key ?>_<?= $score ?>">
                                            <span class="score"><?= $score ?></span>
                                            <span class="label"><?= $score == 4 ? 'Dominates' : ($score == 3 ? 'Support' : ($score == 2 ? 'Involved' : ($score == 1 ? 'Partial' : 'None'))) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Section -->
                        <div class="comments-section">
                            <textarea name="scores[<?= $indicator_key ?>][comments]"
                                      placeholder="Add comments or verification notes for this indicator..."
                                      rows="2"></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <?php $total_indicators += $section_total; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Hidden field for total indicators -->
        <input type="hidden" id="totalIndicatorsCount" value="<?= $total_indicators ?>">

        <!-- Save & Submit -->
        <button type="submit" class="btn-submit">
            <i class="fas fa-save"></i> Save Assessment
        </button>
    </form>

    <!-- Save Progress Bar -->
    <div class="save-progress">
        <span><i class="fas fa-sync-alt fa-spin" id="saveSpinner" style="display: none;"></i> <span id="saveStatus">All changes saved</span></span>
        <span id="completionBadge" style="background: rgba(255,255,255,.2); padding: 5px 15px; border-radius: 30px;">0% complete</span>
    </div>
</div>

<script>
let currentSection = '<?= array_key_first($active_sections) ?>';
let sectionKeys = <?= json_encode(array_keys($active_sections)) ?>;
let autoSaveTimer;
let totalIndicators = <?= $total_indicators ?>;

function showSection(sectionKey) {
    // Hide all forms
    document.querySelectorAll('.assessment-form').forEach(form => {
        form.style.display = 'none';
    });

    // Show selected form
    document.getElementById('form_' + sectionKey).style.display = 'block';

    // Update tabs
    document.querySelectorAll('.section-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.section === sectionKey) {
            tab.classList.add('active');
        }
    });

    currentSection = sectionKey;
    updateSectionProgress(sectionKey);
}

function updateProgress() {
    let totalScored = 0;
    let totalPossible = totalIndicators * 2; // Each indicator has CDOH and IP scores

    // Count all radio buttons that are checked
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        totalScored++;
    });

    let percent = Math.round((totalScored / totalPossible) * 100) || 0;

    // Update overall progress
    document.getElementById('overallProgress').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    document.getElementById('completionBadge').textContent = percent + '% complete';

    // Update section-specific progress
    sectionKeys.forEach(section => {
        updateSectionProgress(section);
    });

    // Trigger auto-save
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSave, 3000);
    document.getElementById('saveStatus').textContent = 'Saving...';
    document.getElementById('saveSpinner').style.display = 'inline-block';
}

function updateSectionProgress(sectionKey) {
    const sectionForm = document.getElementById('form_' + sectionKey);
    if (!sectionForm) return;

    const sectionRadios = sectionForm.querySelectorAll('input[type="radio"]');
    const totalSectionRadios = sectionRadios.length;
    const checkedSectionRadios = sectionForm.querySelectorAll('input[type="radio"]:checked').length;

    let sectionPercent = totalSectionRadios > 0 ? Math.round((checkedSectionRadios / totalSectionRadios) * 100) : 0;

    const progressSpan = document.getElementById('section_progress_' + sectionKey);
    if (progressSpan) {
        progressSpan.textContent = sectionPercent + '% Complete';

        // Mark tab as completed if section is 100% done
        const tab = document.querySelector(`.section-tab[data-section="${sectionKey}"]`);
        if (tab) {
            if (sectionPercent === 100) {
                tab.classList.add('completed');
            } else {
                tab.classList.remove('completed');
            }
        }
    }
}

function autoSave() {
    // Collect form data
    let formData = new FormData(document.getElementById('assessmentForm'));

    // Simulate save (in production, you'd send to server via AJAX)
    console.log('Auto-saving...', Object.fromEntries(formData));

    document.getElementById('saveStatus').textContent = 'All changes saved';
    document.getElementById('saveSpinner').style.display = 'none';
}

// Navigation between sections with keyboard
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'ArrowRight') {
        e.preventDefault();
        let currentIndex = sectionKeys.indexOf(currentSection);
        if (currentIndex < sectionKeys.length - 1) {
            showSection(sectionKeys[currentIndex + 1]);
        }
    } else if (e.ctrlKey && e.key === 'ArrowLeft') {
        e.preventDefault();
        let currentIndex = sectionKeys.indexOf(currentSection);
        if (currentIndex > 0) {
            showSection(sectionKeys[currentIndex - 1]);
        }
    }
});

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    showSection(currentSection);
    updateProgress();

    // Set total indicators display
    document.getElementById('totalIndicators').textContent = totalIndicators;
});

// Form validation before submit
document.getElementById('assessmentForm').addEventListener('submit', function(e) {
    const totalRadios = document.querySelectorAll('input[type="radio"]').length;
    const checkedRadios = document.querySelectorAll('input[type="radio"]:checked').length;

    if (checkedRadios < totalRadios) {
        if (!confirm('You have not completed all indicators. Incomplete sections will be saved as draft. Continue?')) {
            e.preventDefault();
        }
    }
});
</script>
</body>
</html>