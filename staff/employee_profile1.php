<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_number = isset($_GET['id_number']) ? $_GET['id_number'] : '';

if (empty($id_number)) {
    $_SESSION['error_message'] = "No ID Number provided";
    header("Location: county_staff_list.php");
    exit;
}

// Fetch staff details
$staff_stmt = $conn->prepare("SELECT * FROM county_staff WHERE id_number = ?");
$staff_stmt->bind_param('s', $id_number);
$staff_stmt->execute();
$staff_result = $staff_stmt->get_result();
$staff = $staff_result->fetch_assoc();

if (!$staff) {
    $_SESSION['error_message'] = "Staff not found";
    header("Location: county_staff_list.php");
    exit;
}
$staff_stmt->close();

// Fetch statutory details
$statutory_stmt = $conn->prepare("SELECT * FROM employee_statutory WHERE id_number = ?");
$statutory_stmt->bind_param('s', $id_number);
$statutory_stmt->execute();
$statutory_result = $statutory_stmt->get_result();
$statutory = $statutory_result->fetch_assoc();
$statutory_stmt->close();

// Fetch academics
$academics_stmt = $conn->prepare("SELECT * FROM employee_academics WHERE id_number = ? ORDER BY end_date DESC");
$academics_stmt->bind_param('s', $id_number);
$academics_stmt->execute();
$academics = $academics_stmt->get_result();
$academics_stmt->close();

// Fetch work experience
$experience_stmt = $conn->prepare("SELECT * FROM employee_work_experience WHERE id_number = ? ORDER BY
                                    CASE WHEN is_current = 'Yes' THEN 0 ELSE 1 END, end_date DESC");
$experience_stmt->bind_param('s', $id_number);
$experience_stmt->execute();
$experiences = $experience_stmt->get_result();
$experience_stmt->close();

// Fetch professional registrations
$registration_stmt = $conn->prepare("SELECT * FROM employee_professional_registrations WHERE id_number = ? ORDER BY expiry_date DESC");
$registration_stmt->bind_param('s', $id_number);
$registration_stmt->execute();
$registrations = $registration_stmt->get_result();
$registration_stmt->close();

// Fetch trainings
$training_stmt = $conn->prepare("SELECT * FROM employee_trainings WHERE id_number = ? ORDER BY end_date DESC");
$training_stmt->bind_param('s', $id_number);
$training_stmt->execute();
$trainings = $training_stmt->get_result();
$training_stmt->close();

// Fetch languages
$language_stmt = $conn->prepare("SELECT * FROM employee_languages WHERE id_number = ? ORDER BY language_name");
$language_stmt->bind_param('s', $id_number);
$language_stmt->execute();
$languages = $language_stmt->get_result();
$language_stmt->close();

// Fetch referees
$referee_stmt = $conn->prepare("SELECT * FROM employee_referees WHERE id_number = ?");
$referee_stmt->bind_param('s', $id_number);
$referee_stmt->execute();
$referees = $referee_stmt->get_result();
$referee_stmt->close();

// Fetch next of kin
$kin_stmt = $conn->prepare("SELECT * FROM employee_next_of_kin WHERE id_number = ? ORDER BY priority_order");
$kin_stmt->bind_param('s', $id_number);
$kin_stmt->execute();
$kin_list = $kin_stmt->get_result();
$kin_stmt->close();

// Fetch disciplinary records
$disc_stmt = $conn->prepare("SELECT * FROM employee_disciplinary WHERE id_number = ? ORDER BY incident_date DESC");
$disc_stmt->bind_param('s', $id_number);
$disc_stmt->execute();
$disciplinary = $disc_stmt->get_result();
$disc_stmt->close();

// Fetch appraisals
$appr_stmt = $conn->prepare("SELECT * FROM employee_appraisals WHERE id_number = ? ORDER BY appraisal_year DESC, appraisal_date DESC");
$appr_stmt->bind_param('s', $id_number);
$appr_stmt->execute();
$appraisals = $appr_stmt->get_result();
$appr_stmt->close();

// Fetch leave records
$leave_stmt = $conn->prepare("SELECT * FROM employee_leave WHERE id_number = ? ORDER BY start_date DESC");
$leave_stmt->bind_param('s', $id_number);
$leave_stmt->execute();
$leave_records = $leave_stmt->get_result();
$leave_stmt->close();

$full_name = trim($staff['first_name'] . ' ' . $staff['last_name'] . (!empty($staff['other_name']) ? ' ' . $staff['other_name'] : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo htmlspecialchars($full_name); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a2a7a 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .profile-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-title img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .profile-title h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .profile-title .id-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .nav-tabs {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-bottom: none;
        }

        .nav-tabs .nav-link {
            color: #666;
            border: none;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 8px;
        }

        .nav-tabs .nav-link:hover {
            background: #f0f0f0;
        }

        .nav-tabs .nav-link.active {
            background: #0D1A63;
            color: white;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h3 {
            color: #0D1A63;
            font-size: 20px;
            margin: 0;
        }

        .section-header h3 i {
            margin-right: 10px;
        }

        .btn-add {
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
            padding: 5px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            margin-left: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .info-item label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-verified { background: #28a745; color: white; }
        .status-pending { background: #ffc107; color: #212529; }
        .status-rejected { background: #dc3545; color: white; }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-title {
                flex-direction: column;
            }

            .nav-tabs .nav-link {
                display: block;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-title">
                <?php if (!empty($staff['photo'])): ?>
                    <img src="display_staff_photo.php?id_number=<?php echo urlencode($staff['id_number']); ?>"
                         alt="Profile Photo">
                <?php else: ?>
                    <img src="https://via.placeholder.com/80?text=No+Photo" alt="No Photo">
                <?php endif; ?>
                <div>
                    <h1><?php echo htmlspecialchars($full_name); ?></h1>
                    <div class="id-badge">
                        <i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($staff['id_number']); ?>
                    </div>
                </div>
            </div>
            <div>
                <span class="status-badge" style="background: <?php echo $staff['status'] == 'active' ? '#28a745' : '#dc3545'; ?>; color: white;">
                    <?php echo ucfirst($staff['status']); ?>
                </span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal" role="tab">
                    <i class="fas fa-user"></i> Personal
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="statutory-tab" data-toggle="tab" href="#statutory" role="tab">
                    <i class="fas fa-file-contract"></i> Statutory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="academics-tab" data-toggle="tab" href="#academics" role="tab">
                    <i class="fas fa-graduation-cap"></i> Academics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="experience-tab" data-toggle="tab" href="#experience" role="tab">
                    <i class="fas fa-briefcase"></i> Experience
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professional-tab" data-toggle="tab" href="#professional" role="tab">
                    <i class="fas fa-certificate"></i> Professional
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="trainings-tab" data-toggle="tab" href="#trainings" role="tab">
                    <i class="fas fa-chalkboard-teacher"></i> Trainings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="languages-tab" data-toggle="tab" href="#languages" role="tab">
                    <i class="fas fa-language"></i> Languages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="referees-tab" data-toggle="tab" href="#referees" role="tab">
                    <i class="fas fa-address-book"></i> Referees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="kin-tab" data-toggle="tab" href="#kin" role="tab">
                    <i class="fas fa-users"></i> Next of Kin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="disciplinary-tab" data-toggle="tab" href="#disciplinary" role="tab">
                    <i class="fas fa-gavel"></i> Disciplinary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="appraisals-tab" data-toggle="tab" href="#appraisals" role="tab">
                    <i class="fas fa-chart-line"></i> Appraisals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="leave-tab" data-toggle="tab" href="#leave" role="tab">
                    <i class="fas fa-calendar-minus"></i> Leave
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Personal Information -->
            <div class="tab-pane active" id="personal" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <a href="edit_staff.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>First Name</label>
                            <div class="value"><?php echo htmlspecialchars($staff['first_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Last Name</label>
                            <div class="value"><?php echo htmlspecialchars($staff['last_name']); ?></div>
                        </div>
                        <?php if (!empty($staff['other_name'])): ?>
                        <div class="info-item">
                            <label>Other Name</label>
                            <div class="value"><?php echo htmlspecialchars($staff['other_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>ID Number</label>
                            <div class="value"><?php echo htmlspecialchars($staff['id_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Sex</label>
                            <div class="value"><?php echo htmlspecialchars($staff['sex']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <div class="value"><?php echo htmlspecialchars($staff['staff_phone'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <div class="value"><?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Facility</label>
                            <div class="value"><?php echo htmlspecialchars($staff['facility_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Department</label>
                            <div class="value"><?php echo htmlspecialchars($staff['department_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Cadre</label>
                            <div class="value"><?php echo htmlspecialchars($staff['cadre_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>County</label>
                            <div class="value"><?php echo htmlspecialchars($staff['county_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Subcounty</label>
                            <div class="value"><?php echo htmlspecialchars($staff['subcounty_name']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statutory Information -->
            <div class="tab-pane" id="statutory" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-file-contract"></i> Statutory Details</h3>
                        <a href="edit_statutory.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-edit"></i> <?php echo $statutory ? 'Update' : 'Add'; ?>
                        </a>
                    </div>
                    <?php if ($statutory): ?>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>KRA PIN</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['kra_pin'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>NHIF Number</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nhif_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>NSSF Number</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nssf_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Huduma Number</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['huduma_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Passport Number</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['passport_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Birth Certificate</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['birth_cert_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Disability</label>
                            <div class="value"><?php echo $statutory['disability'] ?? 'No'; ?></div>
                        </div>
                        <?php if (($statutory['disability'] ?? 'No') == 'Yes'): ?>
                        <div class="info-item">
                            <label>Disability Description</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['disability_description'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Disability Cert No.</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['disability_cert_number'] ?? 'N/A'); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Next of Kin Section within Statutory -->
                    <h4 style="margin: 30px 0 20px; color: #0D1A63;">Next of Kin / Emergency Contact</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Next of Kin Name</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Relationship</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_relationship'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_phone'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Alternate Phone</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_alternate_phone'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_email'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Postal Address</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['nok_postal_address'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Emergency Contact</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['emergency_contact_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <label>Emergency Phone</label>
                            <div class="value"><?php echo htmlspecialchars($statutory['emergency_contact_phone'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No statutory details added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Academics -->
            <div class="tab-pane" id="academics" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Qualifications</h3>
                        <a href="add_academic.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Academic
                        </a>
                    </div>
                    <?php if ($academics->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Qualification</th>
                                    <th>Institution</th>
                                    <th>Course</th>
                                    <th>Grade</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $academics->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['qualification_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade']); ?></td>
                                    <td><?php echo $row['award_year']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['verification_status']); ?>">
                                            <?php echo $row['verification_status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="add_academic.php?id=<?php echo $row['academic_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_document.php?type=academic&id=<?php echo $row['academic_id']; ?>" class="btn-sm btn-info" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="delete_academic.php?id=<?php echo $row['academic_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No academic records added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Work Experience -->
            <div class="tab-pane" id="experience" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
                        <a href="add_experience.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Experience
                        </a>
                    </div>
                    <?php if ($experiences->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employer</th>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $experiences->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['employer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <?php
                                        echo date('M Y', strtotime($row['start_date'])) . ' - ';
                                        if ($row['is_current'] == 'Yes') {
                                            echo 'Present';
                                        } else {
                                            echo date('M Y', strtotime($row['end_date']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['verification_status']); ?>">
                                            <?php echo $row['verification_status']; ?>
                                        </span>
                                        <?php if ($row['is_current'] == 'Yes'): ?>
                                            <span class="badge bg-success text-white">Current</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-btns">
                                        <a href="add_experience.php?id=<?php echo $row['experience_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_document.php?type=experience&id=<?php echo $row['experience_id']; ?>" class="btn-sm btn-info" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="delete_experience.php?id=<?php echo $row['experience_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No work experience added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Professional Registrations -->
            <div class="tab-pane" id="professional" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-certificate"></i> Professional Registrations</h3>
                        <a href="add_registration.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Registration
                        </a>
                    </div>
                    <?php if ($registrations->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Regulatory Body</th>
                                    <th>Registration No.</th>
                                    <th>License No.</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $registrations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['regulatory_body']); ?></td>
                                    <td><?php echo htmlspecialchars($row['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($row['expiry_date'])); ?>
                                        <?php if (strtotime($row['expiry_date']) < time()): ?>
                                            <span class="badge bg-danger text-white">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['verification_status']); ?>">
                                            <?php echo $row['verification_status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="add_registration.php?id=<?php echo $row['registration_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_document.php?type=registration&id=<?php echo $row['registration_id']; ?>" class="btn-sm btn-info" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="delete_registration.php?id=<?php echo $row['registration_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No professional registrations added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trainings -->
            <div class="tab-pane" id="trainings" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Trainings & Certifications</h3>
                        <a href="add_training.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Training
                        </a>
                    </div>
                    <?php if ($trainings->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Training Name</th>
                                    <th>Provider</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Certificate No.</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $trainings->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['training_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['training_provider']); ?></td>
                                    <td><?php echo $row['training_type']; ?></td>
                                    <td>
                                        <?php
                                        echo date('M Y', strtotime($row['start_date'])) . ' - ' . date('M Y', strtotime($row['end_date']));
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['certificate_number']); ?></td>
                                    <td class="action-btns">
                                        <a href="add_training.php?id=<?php echo $row['training_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_document.php?type=training&id=<?php echo $row['training_id']; ?>" class="btn-sm btn-info" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="delete_training.php?id=<?php echo $row['training_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No trainings added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Languages -->
            <div class="tab-pane" id="languages" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-language"></i> Languages</h3>
                        <a href="add_language.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Language
                        </a>
                    </div>
                    <?php if ($languages->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Language</th>
                                    <th>Proficiency</th>
                                    <th>Speaking</th>
                                    <th>Writing</th>
                                    <th>Reading</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $languages->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['language_name']); ?></td>
                                    <td><?php echo $row['proficiency']; ?></td>
                                    <td><?php echo $row['speaking']; ?></td>
                                    <td><?php echo $row['writing']; ?></td>
                                    <td><?php echo $row['reading']; ?></td>
                                    <td class="action-btns">
                                        <a href="add_language.php?id=<?php echo $row['language_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_language.php?id=<?php echo $row['language_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No languages added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Referees -->
            <div class="tab-pane" id="referees" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-address-book"></i> Referees</h3>
                        <a href="add_referee.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Referee
                        </a>
                    </div>
                    <?php if ($referees->num_rows > 0): ?>
                    <div class="info-grid">
                        <?php while ($row = $referees->fetch_assoc()): ?>
                        <div class="info-item">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong><?php echo htmlspecialchars($row['referee_name']); ?></strong>
                                <div>
                                    <a href="add_referee.php?id=<?php echo $row['referee_id']; ?>" class="btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_referee.php?id=<?php echo $row['referee_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <p><strong>Position:</strong> <?php echo htmlspecialchars($row['referee_position']); ?></p>
                            <p><strong>Organization:</strong> <?php echo htmlspecialchars($row['referee_organization']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['referee_phone']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['referee_email']); ?></p>
                            <p><strong>Relationship:</strong> <?php echo htmlspecialchars($row['referee_relationship']); ?></p>
                            <p><strong>Years Known:</strong> <?php echo $row['years_known']; ?></p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No referees added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Next of Kin (Multiple) -->
            <div class="tab-pane" id="kin" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-users"></i> Next of Kin</h3>
                        <a href="add_kin.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Next of Kin
                        </a>
                    </div>
                    <?php if ($kin_list->num_rows > 0): ?>
                    <div class="info-grid">
                        <?php while ($row = $kin_list->fetch_assoc()): ?>
                        <div class="info-item">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong><?php echo htmlspecialchars($row['kin_name']); ?></strong>
                                <?php if ($row['is_emergency_contact'] == 'Yes'): ?>
                                    <span class="badge bg-danger text-white">Emergency</span>
                                <?php endif; ?>
                            </div>
                            <p><strong>Relationship:</strong> <?php echo htmlspecialchars($row['kin_relationship']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['kin_phone']); ?></p>
                            <?php if (!empty($row['kin_alternate_phone'])): ?>
                                <p><strong>Alt Phone:</strong> <?php echo htmlspecialchars($row['kin_alternate_phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['kin_email'] ?? 'N/A'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($row['kin_address'] ?? 'N/A'); ?></p>
                            <p><strong>County:</strong> <?php echo htmlspecialchars($row['kin_county'] ?? 'N/A'); ?></p>
                            <div style="margin-top: 10px;">
                                <a href="add_kin.php?id=<?php echo $row['kin_id']; ?>" class="btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete_kin.php?id=<?php echo $row['kin_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No next of kin added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Disciplinary -->
            <div class="tab-pane" id="disciplinary" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-gavel"></i> Disciplinary Records</h3>
                        <a href="add_disciplinary.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Record Case
                        </a>
                    </div>
                    <?php if ($disciplinary->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Case No.</th>
                                    <th>Case Type</th>
                                    <th>Incident Date</th>
                                    <th>Penalty</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $disciplinary->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['case_number'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['case_type']); ?></td>
                                    <td><?php echo $row['incident_date'] ? date('d/m/Y', strtotime($row['incident_date'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($row['penalty'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                        $disc_colors = ['Open'=>'#dc3545','Closed'=>'#28a745','Under Investigation'=>'#ffc107','Appealed'=>'#17a2b8'];
                                        $disc_color = $disc_colors[$row['status']] ?? '#6c757d';
                                        ?>
                                        <span class="status-badge" style="background:<?php echo $disc_color; ?>;color:<?php echo $row['status']==='Under Investigation'?'#212529':'white'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="add_disciplinary.php?id=<?php echo $row['disciplinary_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_disciplinary.php?id=<?php echo $row['disciplinary_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this disciplinary record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted"><i class="fas fa-check-circle" style="color:#28a745;"></i> No disciplinary records on file.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Appraisals -->
            <div class="tab-pane" id="appraisals" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-chart-line"></i> Performance Appraisals</h3>
                        <a href="add_appraisal.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Appraisal
                        </a>
                    </div>
                    <?php if ($appraisals->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Year</th>
                                    <th>Date</th>
                                    <th>Supervisor</th>
                                    <th>Rating</th>
                                    <th>Next Appraisal</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $appraisals->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['appraisal_period']); ?></td>
                                    <td><?php echo $row['appraisal_year']; ?></td>
                                    <td><?php echo $row['appraisal_date'] ? date('d/m/Y', strtotime($row['appraisal_date'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($row['supervisor_name'] ?? '—'); ?></td>
                                    <td>
                                        <?php if ($row['overall_rating']): ?>
                                        <?php
                                        $rating = (float)$row['overall_rating'];
                                        $pct = round($rating / 5 * 100);
                                        $rcolor = $rating >= 4 ? '#28a745' : ($rating >= 3 ? '#17a2b8' : ($rating >= 2 ? '#ffc107' : '#dc3545'));
                                        ?>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="width:60px;height:7px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $rcolor; ?>;border-radius:4px;"></div>
                                            </div>
                                            <strong style="color:<?php echo $rcolor; ?>"><?php echo number_format($rating,2); ?></strong>
                                        </div>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td><?php echo $row['next_appraisal_date'] ? date('d/m/Y', strtotime($row['next_appraisal_date'])) : '—'; ?></td>
                                    <td class="action-btns">
                                        <a href="add_appraisal.php?id=<?php echo $row['appraisal_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_appraisal.php?id=<?php echo $row['appraisal_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this appraisal record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No appraisal records added yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leave Records -->
            <div class="tab-pane" id="leave" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-minus"></i> Leave Records</h3>
                        <a href="add_leave.php?id_number=<?php echo urlencode($staff['id_number']); ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Record Leave
                        </a>
                    </div>
                    <?php if ($leave_records->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days Req.</th>
                                    <th>Days Appr.</th>
                                    <th>Approver</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $leave_records->fetch_assoc()):
                                    $lv_colors = ['Approved'=>'status-verified','Pending'=>'status-pending','Rejected'=>'status-rejected','Cancelled'=>''];
                                    $lv_cls = $lv_colors[$row['status']] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                    <td><?php echo $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : '—'; ?></td>
                                    <td><?php echo $row['end_date']   ? date('d/m/Y', strtotime($row['end_date']))   : '—'; ?></td>
                                    <td><?php echo $row['days_requested'] ?? '—'; ?></td>
                                    <td><?php echo $row['days_approved']  ?? '—'; ?></td>
                                    <td><?php echo htmlspecialchars($row['approver_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $lv_cls; ?>" <?php if(!$lv_cls) echo 'style="background:#6c757d;color:white;"'; ?>>
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="add_leave.php?id=<?php echo $row['leave_id']; ?>" class="btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_leave.php?id=<?php echo $row['leave_id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this leave record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No leave records added yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>