<?php
require_once '../includes/config.php';
require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Needs Assessment</title>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/radio_buttons.css" type="text/css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #011f88;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #011f88;
        }

        .section-title {
            color: #011f88;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.4rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #011f88;
            box-shadow: 0 0 0 2px rgba(0,102,204,0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .radio-group, .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input, .checkbox-option input {
            width: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .btn {
            background-color: #011f88;
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
            display: block;
            width: 100%;
            max-width: 300px;
            margin: 30px auto;
        }

        .btn:hover {
            background-color: #0052a3;
        }

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 15px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .form-section {
                padding: 20px;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .radio-group, .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Facility info display */
        .facility-info {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 600;
            color: #333;
        }

        .hidden {
            display: none;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .loading.active {
            display: block;
        }

        /* Challenge items styling */
        .challenge-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .challenge-label {
            flex: 1;
            font-weight: 500;
        }

        .rating-options {
            display: flex;
            gap: 15px;
        }

        .rating-option {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .radio-input {
            margin: 0;
            width: auto;
        }

        .radio-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .scale-explanation {
            background: #f0f7ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            color: #011f88;
        }

        .scale-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Training Needs Assessment Questionnaire</h1>
        </div>

        <?php if(isset($_GET['success'])): ?>
        <div class="success-message">
            Training Needs Assessment submitted successfully!
        </div>
        <?php endif; ?>

        <form id="tnaForm" action="submit_training_needs.php" method="POST">
            <!-- Section 1: Facility Information -->
            <div class="form-section">
            <h2 class="section-title">Facility Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Facility Name <span class="text-danger">*</span></label>
                        <select class="form-select" id="facilityname" name="facilityname" required>
                            <option value="">-- Select Facility --</option>
                            <?php
                            // Use mysqli instead of PDO
                            $sql = "SELECT facilityname, facility_id FROM facilities ORDER BY facilityname";
                            $result = $conn->query($sql);

                            if($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['facilityname']) . "' data-id='" . $row['facility_id'] . "'>" . htmlspecialchars($row['facilityname']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No facilities found</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>MFL Code</label>
                        <input type="text" class="form-control" id="mflcode" name="mflcode" readonly placeholder="Auto-filled">
                    </div>

                    <div class="form-group">
                        <label>County</label>
                        <input type="text" class="form-control" id="countyname" name="countyname" readonly placeholder="Auto-filled">
                    </div>

                    <div class="form-group">
                        <label>Sub-County</label>
                        <input type="text" class="form-control" id="subcountyname" name="subcountyname" readonly placeholder="Auto-filled">
                    </div>

                    <div class="form-group">
                        <label>Level of Care</label>
                        <input type="text" class="form-control" id="level_of_care" name="level_of_care" readonly placeholder="Auto-filled">
                    </div>

                    <div class="form-group">
                        <label>Owner</label>
                        <input type="text" class="form-control" id="owner" name="owner" readonly placeholder="Auto-filled">
                    </div>
                </div>

            <!-- Hidden facility ID field -->
                <input type="hidden" id="facility_id" name="facility_id">
            </div>

            <!-- Section 2: Personal Data -->
            <div class="form-section">
                <h2 class="section-title">Personal Data</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Cadre</label>
                        <select class="form-select" name="cadre">
                            <option value="">-- Select Cadre --</option>
                            <?php
                            $result = $conn->query("SELECT cadrename FROM cadres ORDER BY cadrename");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['cadrename']) . "'>" . htmlspecialchars($row['cadrename']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-select" name="department">
                            <option value="">-- Select Department --</option>
                            <?php
                            $result = $conn->query("SELECT departmentname FROM departments ORDER BY departmentname");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['departmentname']) . "'>" . htmlspecialchars($row['departmentname']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select class="form-select" name="position">
                            <option value="">-- Select Position --</option>
                            <?php
                            $result = $conn->query("SELECT positionname FROM positions ORDER BY positionname");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['positionname']) . "'>" . htmlspecialchars($row['positionname']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="p_no">P/No</label>
                        <input type="text" id="p_no" name="p_no" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="gender" value="Male"> Male
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="gender" value="Female"> Female
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Years of Service</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="years_of_service" value="5 yrs or below"> 5 yrs or below
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_of_service" value="6-10 yrs"> 6-10 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_of_service" value="11-15 yrs"> 11-15 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_of_service" value="16-20 yrs"> 16-20 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_of_service" value="over 21 yrs"> over 21 yrs
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Years in Current Job Group</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="years_current_job_group" value="Below 5 yrs"> Below 5 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_current_job_group" value="6-10 yrs"> 6-10 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_current_job_group" value="11-15 yrs"> 11-15 yrs
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="years_current_job_group" value="over 16 years"> over 16 years
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Age</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="age_range" value="18-25"> 18-25
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="age_range" value="26-35"> 26-35
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="age_range" value="36-45"> 36-45
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="age_range" value="46-55"> 46-55
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="age_range" value="56 and above"> 56 and above
                        </label>
                    </div>
                </div>
            </div>

            <!-- Section 3: Academic/Professional Qualifications -->
            <div class="form-section">
                <h2 class="section-title">Academic/Professional Qualifications</h2>

                <div class="form-group">
                    <label>i. Indicate your highest academic qualification</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="PhD"> PhD
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="Masters Degree"> Masters Degree
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="Diploma"> Diploma
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="A level"> A level
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="KCSE/KCE"> KCSE/KCE
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="highest_academic_qualification" value="Others"> Others
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="professional_qualifications">ii. List down all your professional qualifications</label>
                    <textarea id="professional_qualifications" name="professional_qualifications" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="areas_of_specialization">iii. What are your area(s) of specialization?</label>
                    <textarea id="areas_of_specialization" name="areas_of_specialization" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="other_qualifications">iv. What are your other qualifications?</label>
                    <textarea id="other_qualifications" name="other_qualifications" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="short_courses">V. List the short job-related courses you have attended</label>
                    <textarea id="short_courses" name="short_courses" class="form-control"></textarea>
                </div>
            </div>

            <!-- Section 4: Job Content -->
            <div class="form-section">
                <h2 class="section-title">Job Content</h2>

                <div class="form-group">
                    <label for="duties_responsibilities">i. What are your duties and responsibilities?</label>
                    <textarea id="duties_responsibilities" name="duties_responsibilities" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label>ii. Do you experience any knowledge/skills related challenges in carrying out the duties and responsibilities in 3(i) above?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="knowledge_skills_challenges" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="knowledge_skills_challenges" value="No"> No
                        </label>
                    </div>
                </div>

                <div class="form-group" id="challengingDutiesGroup" style="display: none;">
                    <label for="challenging_duties">iii. If YES please identify the duties and responsibilities that present the greatest knowledge/skills challenges</label>
                    <textarea id="challenging_duties" name="challenging_duties" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="other_challenges">iv. What other challenges affect the performance of your duties and responsibilities?</label>
                    <textarea id="other_challenges" name="other_challenges" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label>v. Do you possess all the necessary skills to perform your duties?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="possess_necessary_skills" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="possess_necessary_skills" value="No"> No
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="skills_explanation">Please explain your response?</label>
                    <textarea id="skills_explanation" name="skills_explanation" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label>vi. From the options listed, below, please tick one that best explains how you acquired the skills that enable you perform your duties and responsibilities in (3i)</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Experience"> Experience
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Attachment"> Attachment
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Training"> Training
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Mentorship"> Mentorship
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Induction"> Induction
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="skills_acquisition" value="Research"> Research
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label style="margin-top: 30px;"><b>In a scale of 1-5 (where 5 is the highest) please identify from the options below that best explains the level of challenges encountered in performing your duties and responsibilities.</b></label>

                    <div class="scale-explanation">
                        <div class="scale-header">
                            <span>1 - Least Challenging</span>
                            <span>5 - Most Challenging</span>
                        </div>
                    </div>

                    <!-- Challenge items -->
                    <div class="challenge-item">
                        <div class="challenge-label">a. Inadequate knowledge and skills</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="knowledge_<?php echo $i; ?>" name="challenge_knowledge" value="<?php echo $i; ?>" class="radio-input">
                                <label for="knowledge_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">b. Experience</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="experience_<?php echo $i; ?>" name="challenge_experience" value="<?php echo $i; ?>" class="radio-input">
                                <label for="experience_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">c. Exposure</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="exposure_<?php echo $i; ?>" name="challenge_exposure" value="<?php echo $i; ?>" class="radio-input">
                                <label for="exposure_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">d. Deployment</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="deployment_<?php echo $i; ?>" name="challenge_deployment" value="<?php echo $i; ?>" class="radio-input">
                                <label for="deployment_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">e. Tools and Equipment</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="tools_<?php echo $i; ?>" name="challenge_tools" value="<?php echo $i; ?>" class="radio-input">
                                <label for="tools_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">f. Management Support</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="management_<?php echo $i; ?>" name="challenge_management" value="<?php echo $i; ?>" class="radio-input">
                                <label for="management_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="challenge-item">
                        <div class="challenge-label">g. Conducive Environment</div>
                        <div class="rating-options">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" id="environment_<?php echo $i; ?>" name="challenge_environment" value="<?php echo $i; ?>" class="radio-input">
                                <label for="environment_<?php echo $i; ?>" class="radio-label"><?php echo $i; ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="suggestions">vii. Suggest ways of addressing the challenges in (vii) above</label>
                    <textarea id="suggestions" name="suggestions" class="form-control"></textarea>
                </div>
            </div>

        <div class="form-section">
                <h2 class="section-title">4. Performance Measures</h2>

                <label>a. Do you set target for your Units/Division/Sub division/department?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="set_targets" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="set_targets" value="No"> No
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="targets_explanation">b. If No, Explain</label>
                        <textarea id="targets_explanation" name="targets_explanation" class="form-control"></textarea>
                    </div>
                <label>c. Do you set own target?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="set_own_targets" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="set_own_targets" value="No"> No
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="own_targets_areas">d. If Yes, which area</label>
                        <textarea id="own_targets_areas" name="own_targets_areas" class="form-control"></textarea>
                    </div>
                <label>ii. Do you perform duties which you consider unrelated to your job?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="unrelated_duties" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="unrelated_duties" value="No"> No
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="skills_unrelated_explanation">iii. If Yes, please specify</label>
                        <textarea id="skills_unrelated_explanation" name="skills_unrelated_explanation" class="form-control"></textarea>
                    </div>
                <label>iv. Do you posses the skills necessary to perform the above duties?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="necessary_technical_skills1" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="necessary_technical_skills1" value="No"> No
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="necessary_technical_skills_explanation1">Explain</label>
                        <textarea id="necessary_technical_skills_explanation1" name="necessary_technical_skills_explanation" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="performance_evaluation">v. How is your performance evaluated?</label>
                        <textarea id="performance_evaluation" name="performance_evaluation" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="least_score_aspects">On what aspects of your targets did you score least during your last evaluation?</label>
                        <textarea id="least_score_aspects" name="least_score_aspects" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="score_reasons">vii.    Please list reasons for the scores in (v) above </label>
                        <textarea id="score_reasons" name="score_reasons" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="improvement_suggestions">viii.    Suggest three (3) ways of improving your performance</label>
                        <textarea id="improvement_suggestions" name="improvement_suggestions" class="form-control"></textarea>
                    </div>
</div>


<div class="form-section">
                <h2 class="section-title">5. Technical Skill Levels</h2>
                    <div class="form-group">
                        <label for="necessary_technical_skills">i. Identify the technical skills that you consider necessary for the performance of your job?</label>
                        <textarea id="necessary_technical_skills" name="necessary_technical_skills" class="form-control"></textarea>
                    </div>
                <label>ii. Do you possess the skills identified in 5(i) above?</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="possess_technical_skills" value="Yes"> Yes
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="possess_technical_skills" value="No"> No
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="technical_skills_list">iii. If Yes, please list any three (3) such skills</label>
                        <textarea id="technical_skills_list" name="technical_skills_list" class="form-control"></textarea>
                    </div>
                <label style="font-weight: bold; margin-bottom: 10px;">From the following core competences, please tick the ones you have been trained on?</label> <br>
                    <input type="checkbox" style="margin-right: 20px;" style="margin-right: 20px;" id="research_methods" name="research_methods" value="Research Methods">
                    <label for="research_methods"> Research Methods</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="training_needs_assessment" name="training_needs_assessment" value="TNA">
                    <label for="training_needs_assessment"> Training Needs Assessment</label><br>

                    <input type="checkbox" style="margin-right: 20px;" id="presentations" name="presentations" value="Presentations">
                    <label for="presentations"> Presentations</label><br>

                    <input type="checkbox" style="margin-right: 20px;" id="proposal_report_writing" name="proposal_report_writing" value="proposal_report_writing">
                    <label for="human_relations_skills"> Proposal & report writing</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="human_relations_skills" name="human_relations_skills" value="human_relations_skills">
                    <label for="human_relations_skills"> Human Relations Skills</label><br>

                    <input type="checkbox" style="margin-right: 20px;" id="financial_management" name="financial_management" value="financial_management">
                    <label for="financial_management"> Financial Management</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="monitoring_evaluation" name="monitoring_evaluation" value="monitoring_evaluation">
                    <label for="monitoring_evaluation"> Monitoring & Evaluation</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="leadership_management" name="leadership_management" value="leadership_management">
                    <label for="leadership_management"> Leadership & Management</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="communication" name="communication" value="communication">
                    <label for="communication"> Communication</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="negotiation_networking" name="negotiation_networking" value="negotiation_networking">
                    <label for="negotiation_networking"> Negotiation Networking</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="policy_formulation_implementation" name="policy_formulation_implementation" value="policy_formulation_implementation">
                    <label for="policy_formulation_implementation"> Policy Formulation & Implementation Skills</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="report_writing" name="report_writing" value="report_writing">
                    <label for="report_writing"> Report Writing</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="minute_writing" name="minute_writing" value="minute_writing">
                    <label for="minute_writing"> Minute Writing</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="speech_writing" name="speech_writing" value="speech_writing">
                    <label for="speech_writing"> Speech Writing</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="time_management" name="time_management" value="time_management">
                    <label for="time_management"> Time Management</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="negotiation_skills" name="negotiation_skills" value="negotiation_skills">
                    <label for="negotiation_skills"> Negotiation Skills</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="guidance_counseling" name="guidance_counseling" value="guidance_counseling">
                    <label for="guidance_counseling"> Guidance & Counseling</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="integrity" name="integrity" value="integrity">
                    <label for="integrity"> Integrity</label><br>
                    <input type="checkbox" style="margin-right: 20px;" id="performance_management" name="performance_management" value="performance_management">
                    <label for="performance_management"> Performance Management</label><br>
        </div>
        <div class="form-section">
                        <h2 class="section-title">6. Training</h2>

                        <label>i. (a) Have you attended any training sponsored by the County Government?</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="attended_training" value="Yes"> Yes
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="attended_training" value="No"> No
                                </label>
                            </div>
                            <div class="form-group">
                                <label for="training_details">(b). If yes, please specify the area of training, duration and the year of training.</label>
                                <textarea id="training_details" name="training_details" class="form-control"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="proposed_training">ii.    List the proposed areas of training that you are interested in for the next Three Years.  Kindly specify the Institution and duration</label>
                                <textarea id="proposed_training" name="proposed_training" class="form-control"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="signature">Signature</label>
                                <textarea id="signature" name="signature" class="form-control"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="submission_date">Submission Date</label>
                                <input type="date" name="submission_date">
                            </div>
        </div>

            <button type="submit" class="btn">Submit Assessment</button>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle facility selection change
            $('#facilityname').on('change', function() {
                const facilityName = $(this).val();
                const facilityId = $(this).find('option:selected').data('id');

                if (!facilityName) {
                    // Clear all fields if no facility selected
                    $('#mflcode').val('');
                    $('#countyname').val('');
                    $('#subcountyname').val('');
                    $('#level_of_care').val('');
                    $('#owner').val('');
                    $('#facility_id').val('');
                    return;
                }

                // Set facility_id from data attribute
                $('#facility_id').val(facilityId || '');

                // Show loading state
                $('#mflcode').val('Loading...');
                $('#countyname').val('Loading...');
                $('#subcountyname').val('Loading...');
                $('#level_of_care').val('Loading...');
                $('#owner').val('Loading...');

                // Fetch facility details
                $.ajax({
                    url: 'fetch_facility.php',
                    method: 'POST',
                    data: { facilityname: facilityName },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Response:', response); // Debug log

                        if (response.success && response.facility) {
                            const facility = response.facility;

                            // Update fields with facility data
                            $('#mflcode').val(facility.mflcode || '');
                            $('#countyname').val(facility.countyname || '');
                            $('#subcountyname').val(facility.subcountyname || '');
                            $('#level_of_care').val(facility.level_of_care || '');
                            $('#owner').val(facility.owner || '');

                            // Also update the hidden facility_id if not already set
                            if (!facilityId && facility.facility_id) {
                                $('#facility_id').val(facility.facility_id);
                            }
                        } else {
                            alert(response.message || 'Could not load facility details.');
                            $('#facilityname').val('');
                            $('#mflcode').val('');
                            $('#countyname').val('');
                            $('#subcountyname').val('');
                            $('#level_of_care').val('');
                            $('#owner').val('');
                            $('#facility_id').val('');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        alert('Error loading facility details. Please try again. Check console for details.');
                        $('#facilityname').val('');
                        $('#mflcode').val('');
                        $('#countyname').val('');
                        $('#subcountyname').val('');
                        $('#level_of_care').val('');
                        $('#owner').val('');
                        $('#facility_id').val('');
                    }
                });
            });

            // Toggle challenging duties textarea based on radio selection
            $('input[name="knowledge_skills_challenges"]').on('change', function() {
                if ($(this).val() === 'Yes') {
                    $('#challengingDutiesGroup').show();
                } else {
                    $('#challengingDutiesGroup').hide();
                    $('#challenging_duties').val('');
                }
            });

            // Form validation
            $('#tnaForm').on('submit', function(e) {
                const facilityName = $('#facilityname').val();
                if (!facilityName) {
                    e.preventDefault();
                    alert('Please select a facility first');
                    $('#facilityname').focus();
                    return false;
                }

                return true;
            });
        });
    </script>
</body>
</html>