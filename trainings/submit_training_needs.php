<?php
require_once '../includes/config.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        //  and validate input data
        $facility_id = ($_POST['facility_id']);
        $facilityname = ($_POST['facilityname']);
        $mflcode = ($_POST['mflcode']);
        $countyname = ($_POST['countyname']);
        $subcountyname = ($_POST['subcountyname']);
        $owner = ($_POST['owner'] ?? '');
        $sdp = ($_POST['sdp'] ?? '');
        $agency = ($_POST['agency'] ?? '');
        $emr_status = ($_POST['emr_status'] ?? '');
        $infrastructure_type = ($_POST['infrastructure_type'] ?? '');

        // Personal data
        $name = ($_POST['name'] ?? '');
        $department = ($_POST['department'] ?? '');
        $designation = ($_POST['designation'] ?? '');
        $p_no = ($_POST['p_no'] ?? '');
        $gender = ($_POST['gender'] ?? '');
        $years_of_service = ($_POST['years_of_service'] ?? '');
        $years_current_job_group = ($_POST['years_current_job_group'] ?? '');
        $age_range = ($_POST['age_range'] ?? '');

        // Academic qualifications
        $highest_academic_qualification = ($_POST['highest_academic_qualification'] ?? '');
        $professional_qualifications = ($_POST['professional_qualifications'] ?? '');
        $areas_of_specialization = ($_POST['areas_of_specialization'] ?? '');
        $other_qualifications = ($_POST['other_qualifications'] ?? '');
        $short_courses = ($_POST['short_courses'] ?? '');

        // Job content
        $duties_responsibilities = ($_POST['duties_responsibilities'] ?? '');
        $knowledge_skills_challenges = ($_POST['knowledge_skills_challenges'] ?? '');
        $challenging_duties = ($_POST['challenging_duties'] ?? '');
        $other_challenges = ($_POST['other_challenges'] ?? '');
        $possess_necessary_skills = ($_POST['possess_necessary_skills'] ?? '');
        $skills_explanation = ($_POST['skills_explanation'] ?? '');
        $skills_acquisition = ($_POST['skills_acquisition'] ?? '');
        $challenge_level = intval($_POST['challenge_level'] ?? 0);
        $suggestions = ($_POST['suggestions'] ?? '');

        // Performance measures
        $set_targets = ($_POST['set_targets'] ?? '');
        $targets_explanation = ($_POST['targets_explanation'] ?? '');
        $set_own_targets = ($_POST['set_own_targets'] ?? '');
        $own_targets_areas = ($_POST['own_targets_areas'] ?? '');
        $unrelated_duties = ($_POST['unrelated_duties'] ?? '');
        $unrelated_duties_specify = ($_POST['unrelated_duties_specify'] ?? '');
        $skills_unrelated_duties = ($_POST['skills_unrelated_duties'] ?? '');
        $skills_unrelated_explanation = ($_POST['skills_unrelated_explanation'] ?? '');
        $performance_evaluation = ($_POST['performance_evaluation'] ?? '');
        $least_score_aspects = ($_POST['least_score_aspects'] ?? '');
        $score_reasons = ($_POST['score_reasons'] ?? '');
        $improvement_suggestions = ($_POST['improvement_suggestions'] ?? '');

        // Technical skills
        $necessary_technical_skills = ($_POST['necessary_technical_skills'] ?? '');
        $possess_technical_skills = ($_POST['possess_technical_skills'] ?? '');
        $technical_skills_list = ($_POST['technical_skills_list'] ?? '');

        // Competencies
        $competencies = [
            'research_methods' => isset($_POST['research_methods']) ? 1 : 0,
            'training_needs_assessment' => isset($_POST['training_needs_assessment']) ? 1 : 0,
            'presentations' => isset($_POST['presentations']) ? 1 : 0,
            'proposal_report_writing' => isset($_POST['proposal_report_writing']) ? 1 : 0,
            'human_relations_skills' => isset($_POST['human_relations_skills']) ? 1 : 0,
            'financial_management' => isset($_POST['financial_management']) ? 1 : 0,
            'monitoring_evaluation' => isset($_POST['monitoring_evaluation']) ? 1 : 0,
            'leadership_management' => isset($_POST['leadership_management']) ? 1 : 0,
            'communication' => isset($_POST['communication']) ? 1 : 0,
            'negotiation_networking' => isset($_POST['negotiation_networking']) ? 1 : 0,
            'policy_formulation_implementation' => isset($_POST['policy_formulation_implementation']) ? 1 : 0,
            'report_writing' => isset($_POST['report_writing']) ? 1 : 0,
            'minute_writing' => isset($_POST['minute_writing']) ? 1 : 0,
            'speech_writing' => isset($_POST['speech_writing']) ? 1 : 0,
            'time_management' => isset($_POST['time_management']) ? 1 : 0,
            'negotiation_skills' => isset($_POST['negotiation_skills']) ? 1 : 0,
            'guidance_counseling' => isset($_POST['guidance_counseling']) ? 1 : 0,
            'integrity' => isset($_POST['integrity']) ? 1 : 0,
            'performance_management' => isset($_POST['performance_management']) ? 1 : 0
        ];

        // Training
        $attended_training = ($_POST['attended_training'] ?? '');
        $training_details = ($_POST['training_details'] ?? '');
        $proposed_training = ($_POST['proposed_training'] ?? '');

        $signature = ($_POST['signature'] ?? '');

        // Insert into database
        $sql = "INSERT INTO tna_responses (
            facility_id, facilityname, mflcode, countyname, subcountyname, owner, sdp, agency, emr_status, infrastructure_type,
            name, department, designation, p_no, gender, years_of_service, years_current_job_group, age_range,
            highest_academic_qualification, professional_qualifications, areas_of_specialization, other_qualifications, short_courses,
            duties_responsibilities, knowledge_skills_challenges, challenging_duties, other_challenges,
            possess_necessary_skills, skills_explanation, skills_acquisition, challenge_level, suggestions,
            set_targets, targets_explanation, set_own_targets, own_targets_areas,
            unrelated_duties, unrelated_duties_specify, skills_unrelated_duties, skills_unrelated_explanation,
            performance_evaluation, least_score_aspects, score_reasons, improvement_suggestions,
            necessary_technical_skills, possess_technical_skills, technical_skills_list,
            research_methods, training_needs_assessment, presentations, proposal_report_writing,
            human_relations_skills, financial_management, monitoring_evaluation, leadership_management,
            communication, negotiation_networking, policy_formulation_implementation, report_writing,
            minute_writing, speech_writing, time_management, negotiation_skills, guidance_counseling,
            integrity, performance_management,
            attended_training, training_details, proposed_training, signature
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $facility_id, $facilityname, $mflcode, $countyname, $subcountyname, $owner, $sdp, $agency, $emr_status, $infrastructure_type,
            $name, $department, $designation, $p_no, $gender, $years_of_service, $years_current_job_group, $age_range,
            $highest_academic_qualification, $professional_qualifications, $areas_of_specialization, $other_qualifications, $short_courses,
            $duties_responsibilities, $knowledge_skills_challenges, $challenging_duties, $other_challenges,
            $possess_necessary_skills, $skills_explanation, $skills_acquisition, $challenge_level, $suggestions,
            $set_targets, $targets_explanation, $set_own_targets, $own_targets_areas,
            $unrelated_duties, $unrelated_duties_specify, $skills_unrelated_duties, $skills_unrelated_explanation,
            $performance_evaluation, $least_score_aspects, $score_reasons, $improvement_suggestions,
            $necessary_technical_skills, $possess_technical_skills, $technical_skills_list,
            $competencies['research_methods'], $competencies['training_needs_assessment'], $competencies['presentations'],
            $competencies['proposal_report_writing'], $competencies['human_relations_skills'], $competencies['financial_management'],
            $competencies['monitoring_evaluation'], $competencies['leadership_management'], $competencies['communication'],
            $competencies['negotiation_networking'], $competencies['policy_formulation_implementation'], $competencies['report_writing'],
            $competencies['minute_writing'], $competencies['speech_writing'], $competencies['time_management'],
            $competencies['negotiation_skills'], $competencies['guidance_counseling'], $competencies['integrity'],
            $competencies['performance_management'], $attended_training, $training_details, $proposed_training, $signature
        ]);

        // Redirect with success message
        header('Location: index.php?success=1');
        exit;

    } catch(PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>