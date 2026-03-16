<?php
// submit_training_needs.php (INT version - CORRECTED)
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1); // Remove in production

include('../includes/config.php');
include('../includes/session_check.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

try {
    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    if (empty($_POST['id_number'])) {
        throw new Exception('No staff member selected');
    }

    // Get and sanitize ID number (cast to INT)
    $id_number = intval($_POST['id_number']);

    // Verify staff exists in county_staff
    $stmt = $conn->prepare("SELECT id_number, first_name, last_name, other_name, facility_name,
                                   county_name, subcounty_name, cadre_name, department_name,
                                   sex, employment_status, date_of_birth, date_of_joining
                            FROM county_staff
                            WHERE id_number = ? AND status = 'active'");
    $stmt->bind_param("i", $id_number);
    $stmt->execute();
    $staff_result = $stmt->get_result();

    if ($staff_result->num_rows === 0) {
        throw new Exception('Staff member not found or inactive');
    }

    $staff = $staff_result->fetch_assoc();

    // Begin transaction
    $conn->begin_transaction();

    // Build name from staff data
    $full_name = trim($staff['first_name'] . ' ' .
                     (!empty($staff['other_name']) ? $staff['other_name'] . ' ' : '') .
                     $staff['last_name']);

    // Calculate years of service
    $years_of_service = null;
    if (!empty($staff['date_of_joining'])) {
        $join_date = new DateTime($staff['date_of_joining']);
        $now = new DateTime();
        $interval = $join_date->diff($now);
        $years_of_service = $interval->y . ' years, ' . $interval->m . ' months';
    }

    // Calculate age range
    $age_range = null;
    if (!empty($staff['date_of_birth'])) {
        $dob = new DateTime($staff['date_of_birth']);
        $now = new DateTime();
        $age = $dob->diff($now)->y;

        if ($age < 25) $age_range = 'Below 25';
        elseif ($age < 35) $age_range = '25-34';
        elseif ($age < 45) $age_range = '35-44';
        elseif ($age < 55) $age_range = '45-54';
        else $age_range = '55 and above';
    }

    // Collect competences trained
    $competences_trained = [];
    $competence_fields = [
        'research_methods', 'training_needs_assessment', 'presentations',
        'proposal_report_writing', 'human_relations_skills', 'financial_management',
        'monitoring_evaluation', 'leadership_management', 'communication',
        'negotiation_networking', 'policy_formulation', 'report_writing',
        'minute_writing', 'speech_writing', 'time_management',
        'negotiation_skills', 'guidance_counseling', 'integrity',
        'performance_management'
    ];

    foreach ($competence_fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $competences_trained[] = $_POST[$field];
        }
    }
    $competences_trained_str = implode(', ', $competences_trained);

    // Prepare the main assessment insert
    $sql = "INSERT INTO tna_assessments (
        id_number, facility_name, countyname, subcountyname,
        name, cadre, department, position, designation,
        gender, years_of_service, years_current_job_group, age_range,
        duties_responsibilities, knowledge_skills_challenges,
        challenging_duties, other_challenges,
        possess_necessary_skills, skills_explanation, skills_acquisition,
        challenge_knowledge, challenge_equipment, challenge_workload,
        challenge_motivation, challenge_teamwork, challenge_management,
        challenge_environment, suggestions,
        set_targets, targets_explanation, set_own_targets, own_targets_areas,
        unrelated_duties, skills_unrelated_explanation,
        necessary_technical_skills1, necessary_technical_skills_explanation,
        performance_evaluation, least_score_aspects, score_reasons,
        improvement_suggestions,
        necessary_technical_skills, possess_technical_skills, technical_skills_list,
        competences_trained,
        attended_training, training_details,
        administered_by, submission_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Create variables for all parameters (bind_param requires variables, not literals)
    $facility_name = $staff['facility_name'];
    $county_name = $staff['county_name'];
    $subcounty_name = $staff['subcounty_name'];
    $cadre_name = $staff['cadre_name'];
    $department_name = $staff['department_name'];
    $position = $_POST['position'] ?? null;
    $designation = $_POST['designation'] ?? null;
    $gender = $staff['sex'];
    $years_current_job_group = $_POST['years_current_job_group'] ?? null;
    $duties_responsibilities = $_POST['duties_responsibilities'] ?? null;
    $knowledge_skills_challenges = $_POST['knowledge_skills_challenges'] ?? null;
    $challenging_duties = $_POST['challenging_duties'] ?? null;
    $other_challenges = $_POST['other_challenges'] ?? null;
    $possess_necessary_skills = $_POST['possess_necessary_skills'] ?? null;
    $skills_explanation = $_POST['skills_explanation'] ?? null;
    $skills_acquisition = $_POST['skills_acquisition'] ?? null;
    $challenge_knowledge = $_POST['challenge_knowledge'] ?? null;
    $challenge_equipment = $_POST['challenge_equipment'] ?? null;
    $challenge_workload = $_POST['challenge_workload'] ?? null;
    $challenge_motivation = $_POST['challenge_motivation'] ?? null;
    $challenge_teamwork = $_POST['challenge_teamwork'] ?? null;
    $challenge_management = $_POST['challenge_management'] ?? null;
    $challenge_environment = $_POST['challenge_environment'] ?? null;
    $suggestions = $_POST['suggestions'] ?? null;
    $set_targets = $_POST['set_targets'] ?? null;
    $targets_explanation = $_POST['targets_explanation'] ?? null;
    $set_own_targets = $_POST['set_own_targets'] ?? null;
    $own_targets_areas = $_POST['own_targets_areas'] ?? null;
    $unrelated_duties = $_POST['unrelated_duties'] ?? null;
    $skills_unrelated_explanation = $_POST['skills_unrelated_explanation'] ?? null;
    $necessary_technical_skills1 = $_POST['necessary_technical_skills1'] ?? null;
    $necessary_technical_skills_explanation = $_POST['necessary_technical_skills_explanation'] ?? null;
    $performance_evaluation = $_POST['performance_evaluation'] ?? null;
    $least_score_aspects = $_POST['least_score_aspects'] ?? null;
    $score_reasons = $_POST['score_reasons'] ?? null;
    $improvement_suggestions = $_POST['improvement_suggestions'] ?? null;
    $necessary_technical_skills = $_POST['necessary_technical_skills'] ?? null;
    $possess_technical_skills = $_POST['possess_technical_skills'] ?? null;
    $technical_skills_list = $_POST['technical_skills_list'] ?? null;
    $attended_training = $_POST['attended_training'] ?? null;
    $training_details = $_POST['training_details'] ?? null;
    $administered_by = $_POST['administered_by'] ?? $_SESSION['full_name'] ?? 'System';
    $submission_date = $_POST['submission_date'] ?? date('Y-m-d');

    // Now bind all variables
    $stmt->bind_param(
        "isssssssssssssssssssssssssssssssssssssssssssssss",
        $id_number,
        $facility_name,
        $county_name,
        $subcounty_name,
        $full_name,
        $cadre_name,
        $department_name,
        $position,
        $designation,
        $gender,
        $years_of_service,
        $years_current_job_group,
        $age_range,
        $duties_responsibilities,
        $knowledge_skills_challenges,
        $challenging_duties,
        $other_challenges,
        $possess_necessary_skills,
        $skills_explanation,
        $skills_acquisition,
        $challenge_knowledge,
        $challenge_equipment,
        $challenge_workload,
        $challenge_motivation,
        $challenge_teamwork,
        $challenge_management,
        $challenge_environment,
        $suggestions,
        $set_targets,
        $targets_explanation,
        $set_own_targets,
        $own_targets_areas,
        $unrelated_duties,
        $skills_unrelated_explanation,
        $necessary_technical_skills1,
        $necessary_technical_skills_explanation,
        $performance_evaluation,
        $least_score_aspects,
        $score_reasons,
        $improvement_suggestions,
        $necessary_technical_skills,
        $possess_technical_skills,
        $technical_skills_list,
        $competences_trained_str,
        $attended_training,
        $training_details,
        $administered_by,
        $submission_date
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save assessment: ' . $stmt->error);
    }

    $tna_id = $conn->insert_id;

    // --- INSERT PROPOSED TRAININGS ---
    $areas = $_POST['proposed_training_area'] ?? [];
    $institutions = $_POST['proposed_training_institution'] ?? [];
    $durations = $_POST['proposed_training_duration'] ?? [];
    $years = $_POST['proposed_training_year'] ?? [];

    $count = max(count($areas), count($institutions), count($durations), count($years));

    if ($count > 0) {
        $training_sql = "INSERT INTO tna_proposed_trainings
                        (tna_id, id_number, area_of_training, institution, duration, preferred_year, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $training_stmt = $conn->prepare($training_sql);

        for ($i = 0; $i < $count; $i++) {
            if (empty($areas[$i] ?? '') && empty($institutions[$i] ?? '') &&
                empty($durations[$i] ?? '') && empty($years[$i] ?? '')) {
                continue;
            }

            $area = !empty($areas[$i]) ? trim($areas[$i]) : null;
            $institution = !empty($institutions[$i]) ? trim($institutions[$i]) : null;
            $duration = !empty($durations[$i]) ? trim($durations[$i]) : null;
            $year = !empty($years[$i]) ? intval($years[$i]) : null;
            $sort_order = $i + 1;

            $training_stmt->bind_param(
                "iissssi",
                $tna_id,
                $id_number,
                $area,
                $institution,
                $duration,
                $year,
                $sort_order
            );

            if (!$training_stmt->execute()) {
                throw new Exception('Failed to save training entry: ' . $training_stmt->error);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'Training Needs Assessment submitted successfully';
    $response['redirect'] = 'training_needs_assessment_questionaire.php?success=1';

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_error === null) {
        $conn->rollback();
    }

    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('TNA Submission Error: ' . $e->getMessage());
}

// Handle response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    if ($response['success']) {
        header('Location: ' . $response['redirect']);
        exit();
    } else {
        $_SESSION['tna_error'] = $response['message'];
        header('Location: training_needs_assessment_questionaire.php?error=1');
        exit();
    }
}
?>