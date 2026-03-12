<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Get logged-in staff details
$staff_id = $_SESSION['staff_id'] ?? 0;
$staff_data = null;

if ($staff_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM county_staff WHERE staff_id = ?");
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff_data = $result->fetch_assoc();
        $stmt->close();
}

// If no staff_id in session, try to get from id_number
if (!$staff_data && isset($_SESSION['id_number'])) {
        $stmt = $conn->prepare("SELECT * FROM county_staff WHERE id_number = ?");
        $stmt->bind_param('s', $_SESSION['id_number']);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff_data = $result->fetch_assoc();
        if ($staff_data) {
                $_SESSION['staff_id'] = $staff_data['staff_id'];
                $staff_id = $staff_data['staff_id'];
        }
        $stmt->close();
}

// Fetch staff's submitted trainings
$trainings = [];
if ($staff_id > 0) {
        $query = "SELECT sst.*,
                            (SELECT COUNT(*) FROM training_facilitators WHERE self_training_id = sst.self_training_id) as facilitator_count
                            FROM staff_self_trainings sst
                            WHERE sst.staff_id = ?
                            ORDER BY sst.start_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
                $trainings[] = $row;
        }
        $stmt->close();
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Staff Training Management</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
                * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }

                body {
                        background: #f4f7fc;
                        padding: 20px;
                }

                .container {
                        max-width: 1400px;
                        margin: 0 auto;
                }

                .header {
                        background: #0D1A63;
                        color: white;
                        padding: 25px 30px;
                        border-radius: 15px;
                        margin-bottom: 25px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                }

                .header h1 {
                        font-size: 28px;
                        font-weight: 600;
                }

                .header h1 i {
                        margin-right: 10px;
                }

                .header-actions {
                        display: flex;
                        gap: 15px;
                }

                .btn {
                        padding: 10px 20px;
                        border: none;
                        border-radius: 8px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        text-decoration: none;
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                }

                .btn-primary {
                        background: white;
                        color: #0D1A63;
                }

                .btn-primary:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(255,255,255,0.2);
                }

                .btn-success {
                        background: #28a745;
                        color: white;
                }

                .btn-success:hover {
                        background: #218838;
                        transform: translateY(-2px);
                }

                .btn-info {
                        background: #17a2b8;
                        color: white;
                }

                .btn-warning {
                        background: #ffc107;
                        color: #212529;
                }

                .staff-profile {
                        background: white;
                        border-radius: 15px;
                        padding: 20px;
                        margin-bottom: 25px;
                        display: flex;
                        align-items: center;
                        gap: 30px;
                        flex-wrap: wrap;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                }

                .staff-avatar {
                        width: 80px;
                        height: 80px;
                        background: #0D1A63;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 32px;
                        color: white;
                }

                .staff-info {
                        flex: 1;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 15px;
                }

                .info-item {
                        font-size: 14px;
                }

                .info-item label {
                        color: #666;
                        display: block;
                        font-size: 12px;
                        text-transform: uppercase;
                }

                .info-item span {
                        font-weight: 600;
                        color: #333;
                }

                .training-form {
                        background: white;
                        border-radius: 15px;
                        padding: 25px;
                        margin-bottom: 30px;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                }

                .training-form h2 {
                        color: #0D1A63;
                        margin-bottom: 20px;
                        font-size: 20px;
                        border-bottom: 2px solid #f0f0f0;
                        padding-bottom: 10px;
                }

                .training-form h2 i {
                        margin-right: 10px;
                }

                .course-entry {
                        background: #f8f9fa;
                        border-radius: 10px;
                        padding: 20px;
                        margin-bottom: 20px;
                        border-left: 4px solid #0D1A63;
                }

                .course-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 15px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid #e0e0e0;
                }

                .course-title {
                        font-weight: 600;
                        color: #0D1A63;
                }

                .remove-course {
                        color: #dc3545;
                        cursor: pointer;
                        font-size: 18px;
                }

                .form-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                        gap: 15px;
                        margin-bottom: 15px;
                }

                .form-group {
                        margin-bottom: 15px;
                }

                label {
                        display: block;
                        margin-bottom: 5px;
                        color: #555;
                        font-weight: 500;
                        font-size: 13px;
                }

                label.required::after {
                        content: " *";
                        color: #dc3545;
                }

                input, select, textarea {
                        width: 100%;
                        padding: 10px 12px;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 14px;
                        transition: all 0.3s ease;
                }

                input:focus, select:focus, textarea:focus {
                        outline: none;
                        border-color: #0D1A63;
                        box-shadow: 0 0 0 3px rgba(13,26,99,0.1);
                }

                .facilitator-section {
                        background: white;
                        border-radius: 8px;
                        padding: 15px;
                        margin: 15px 0;
                        border: 1px solid #e0e0e0;
                }

                .facilitator-entry {
                        display: grid;
                        grid-template-columns: 1fr 1fr 30px;
                        gap: 10px;
                        margin-bottom: 10px;
                        align-items: center;
                }

                .add-facilitator {
                        background: #28a745;
                        color: white;
                        border: none;
                        padding: 8px 15px;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 13px;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                }

                .remove-facilitator {
                        color: #dc3545;
                        cursor: pointer;
                        font-size: 16px;
                }

                .btn-add-course {
                        background: #17a2b8;
                        color: white;
                        border: none;
                        padding: 12px 25px;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 15px;
                        margin: 20px 0;
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                }

                .form-actions {
                        display: flex;
                        gap: 15px;
                        justify-content: flex-end;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 2px solid #f0f0f0;
                }

                .btn-submit {
                        background: #28a745;
                        color: white;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                }

                .btn-submit:hover {
                        background: #218838;
                        transform: translateY(-2px);
                }

                .btn-draft {
                        background: #ffc107;
                        color: #212529;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                }

                .btn-draft:hover {
                        background: #e0a800;
                }

                /* Trainings Table */
                .trainings-table {
                        background: white;
                        border-radius: 15px;
                        padding: 25px;
                        margin-top: 30px;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                }

                .trainings-table h3 {
                        color: #0D1A63;
                        margin-bottom: 20px;
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

                .status-badge {
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 11px;
                        font-weight: 600;
                        display: inline-block;
                }

                .status-draft { background: #ffc107; color: #212529; }
                .status-submitted { background: #17a2b8; color: white; }
                .status-verified { background: #28a745; color: white; }

                .action-cell {
                        display: flex;
                        gap: 5px;
                }

                .action-btn {
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 11px;
                        text-decoration: none;
                        color: white;
                        display: inline-flex;
                        align-items: center;
                        gap: 3px;
                }

                .btn-view { background: #17a2b8; }
                .btn-print { background: #6c757d; }
                .btn-edit { background: #ffc107; color: #212529; }

                .alert {
                        padding: 15px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                }

                .alert-success {
                        background: #d4edda;
                        color: #155724;
                        border: 1px solid #c3e6cb;
                }

                .alert-danger {
                        background: #f8d7da;
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                }

                @media (max-width: 768px) {
                        .staff-profile {
                                flex-direction: column;
                                text-align: center;
                        }

                        .facilitator-entry {
                                grid-template-columns: 1fr;
                        }

                        .form-actions {
                                flex-direction: column;
                        }

                        .btn {
                                width: 100%;
                                justify-content: center;
                        }
                }
        </style>
</head>
<body>
        <div class="container">
                <div class="header">
                        <h1><i class="fas fa-chalkboard-teacher"></i> Staff Training Management</h1>
                        <div class="header-actions">
                                <a href="training_list.php" class="btn btn-primary">
                                        <i class="fas fa-list"></i> View All Trainings
                                </a>
                        </div>
                </div>

                <?php if ($success_msg): ?>
                        <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                        </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                        <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                <?php endif; ?>

                <?php if ($staff_data): ?>
                        <!-- Staff Profile -->
                        <div class="staff-profile">
                                <div class="staff-avatar">
                                        <?php echo strtoupper(substr($staff_data['first_name'], 0, 1) . substr($staff_data['last_name'], 0, 1)); ?>
                                </div>
                                <div class="staff-info">
                                        <div class="info-item">
                                                <label>Full Name</label>
                                                <span><?php echo htmlspecialchars($staff_data['first_name'] . ' ' . $staff_data['last_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                                <label>ID Number</label>
                                                <span><?php echo htmlspecialchars($staff_data['id_number']); ?></span>
                                        </div>
                                        <div class="info-item">
                                                <label>Department</label>
                                                <span><?php echo htmlspecialchars($staff_data['department_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                                <label>Cadre</label>
                                                <span><?php echo htmlspecialchars($staff_data['cadre_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                                <label>Facility</label>
                                                <span><?php echo htmlspecialchars($staff_data['facility_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                                <label>County</label>
                                                <span><?php echo htmlspecialchars($staff_data['county_name'] ?? 'N/A'); ?></span>
                                        </div>
                                </div>
                        </div>

                        <!-- Training Form -->
                        <div class="training-form">
                                <h2><i class="fas fa-plus-circle"></i> Add New Training(s)</h2>
                                <form method="POST" action="submit_staff_trainings.php" id="trainingsForm">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff_data['staff_id']; ?>">
                                        <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($staff_data['id_number']); ?>">

                                        <div id="courses-container">
                                                <!-- Course entries will be added here dynamically -->
                                        </div>

                                        <button type="button" class="btn-add-course" onclick="addCourseEntry()">
                                                <i class="fas fa-plus"></i> Add Another Course
                                        </button>

                                        <div class="form-actions">
                                                <button type="button" class="btn-draft" onclick="saveAsDraft()">
                                                        <i class="fas fa-save"></i> Save as Draft
                                                </button>
                                                <button type="button" class="btn-submit" onclick="submitAll()">
                                                        <i class="fas fa-check-circle"></i> Submit All Courses
                                                </button>
                                        </div>
                                </form>
                        </div>

                        <!-- My Trainings List -->
                        <div class="trainings-table">
                                <h3><i class="fas fa-history"></i> My Training History</h3>
                                <table>
                                        <thead>
                                                <tr>
                                                        <th>#</th>
                                                        <th>Course Name</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Provider</th>
                                                        <th>Facilitators</th>
                                                        <th>Status</th>
                                                        <th>Submitted On</th>
                                                        <th>Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <?php if (empty($trainings)): ?>
                                                        <tr>
                                                                <td colspan="9" style="text-align: center; padding: 30px;">
                                                                        <i class="fas fa-folder-open" style="font-size: 40px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                                                        No trainings recorded yet
                                                                </td>
                                                        </tr>
                                                <?php else: ?>
                                                        <?php foreach ($trainings as $index => $training): ?>
                                                                <tr>
                                                                        <td><?php echo $index + 1; ?></td>
                                                                        <td><?php echo htmlspecialchars($training['course_name']); ?></td>
                                                                        <td><?php echo date('d/m/Y', strtotime($training['start_date'])); ?></td>
                                                                        <td><?php echo date('d/m/Y', strtotime($training['end_date'])); ?></td>
                                                                        <td><?php echo htmlspecialchars($training['training_provider'] ?? 'N/A'); ?></td>
                                                                        <td><?php echo $training['facilitator_count']; ?> facilitator(s)</td>
                                                                        <td>
                                                                                <span class="status-badge status-<?php echo $training['status']; ?>">
                                                                                        <?php echo ucfirst($training['status']); ?>
                                                                                </span>
                                                                        </td>
                                                                        <td><?php echo $training['submission_date'] ? date('d/m/Y', strtotime($training['submission_date'])) : 'Not submitted'; ?></td>
                                                                        <td class="action-cell">
                                                                                <a href="view_staff_training.php?id=<?php echo $training['self_training_id']; ?>" class="action-btn btn-view">
                                                                                        <i class="fas fa-eye"></i>
                                                                                </a>
                                                                                <?php if ($training['status'] == 'verified'): ?>
                                                                                        <a href="print_certificate.php?id=<?php echo $training['self_training_id']; ?>" class="action-btn btn-print" target="_blank">
                                                                                                <i class="fas fa-print"></i>
                                                                                        </a>
                                                                                <?php endif; ?>
                                                                                <?php if ($training['status'] == 'draft'): ?>
                                                                                        <a href="edit_staff_training.php?id=<?php echo $training['self_training_id']; ?>" class="action-btn btn-edit">
                                                                                                <i class="fas fa-edit"></i>
                                                                                        </a>
                                                                                <?php endif; ?>
                                                                        </td>
                                                                </tr>
                                                        <?php endforeach; ?>
                                                <?php endif; ?>
                                        </tbody>
                                </table>
                        </div>

                <?php else: ?>
                        <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Staff record not found. Please contact administrator.
                        </div>
                <?php endif; ?>
        </div>

        <script>
                let courseCounter = 0;

                function addCourseEntry() {
                        courseCounter++;
                        const container = document.getElementById('courses-container');
                        const today = new Date().toISOString().split('T')[0];

                        const courseHtml = `
                                <div class="course-entry" id="course-${courseCounter}">
                                        <div class="course-header">
                                                <span class="course-title">Course #${courseCounter}</span>
                                                <span class="remove-course" onclick="removeCourse(${courseCounter})">
                                                        <i class="fas fa-times-circle"></i>
                                                </span>
                                        </div>

                                        <div class="form-grid">
                                                <div class="form-group">
                                                        <label class="required">Course Name</label>
                                                        <input type="text" name="courses[${courseCounter}][course_name]" required>
                                                </div>

                                                <div class="form-group">
                                                        <label class="required">Training Type</label>
                                                        <select name="courses[${courseCounter}][training_type]" required>
                                                                <option value="">Select Type</option>
                                                                <option value="In-service">In-service</option>
                                                                <option value="Workshop">Workshop</option>
                                                                <option value="Seminar">Seminar</option>
                                                                <option value="Conference">Conference</option>
                                                                <option value="Online Course">Online Course</option>
                                                                <option value="Certificate Program">Certificate Program</option>
                                                                <option value="Diploma Program">Diploma Program</option>
                                                                <option value="Other">Other</option>
                                                        </select>
                                                </div>

                                                <div class="form-group">
                                                        <label class="required">Start Date</label>
                                                        <input type="date" name="courses[${courseCounter}][start_date]" max="${today}" required>
                                                </div>

                                                <div class="form-group">
                                                        <label class="required">End Date</label>
                                                        <input type="date" name="courses[${courseCounter}][end_date]" max="${today}" required>
                                                </div>

                                                <div class="form-group">
                                                        <label>Training Provider</label>
                                                        <input type="text" name="courses[${courseCounter}][training_provider]" placeholder="Institution/Organization">
                                                </div>

                                                <div class="form-group">
                                                        <label>Venue/Location</label>
                                                        <input type="text" name="courses[${courseCounter}][venue]">
                                                </div>

                                                <div class="form-group">
                                                        <label>Certificate Number</label>
                                                        <input type="text" name="courses[${courseCounter}][certificate_number]">
                                                </div>

                                                <div class="form-group">
                                                        <label>Funding Source</label>
                                                        <select name="courses[${courseCounter}][funding_source]">
                                                                <option value="">Select Funding</option>
                                                                <option value="Self">Self</option>
                                                                <option value="County">County Government</option>
                                                                <option value="National">National Government</option>
                                                                <option value="Donor">Donor Funded</option>
                                                                <option value="Facility">Facility</option>
                                                                <option value="Other">Other</option>
                                                        </select>
                                                </div>
                                        </div>

                                        <div class="facilitator-section">
                                                <label>Facilitators</label>
                                                <div id="facilitators-${courseCounter}">
                                                        <div class="facilitator-entry" id="facilitator-${courseCounter}-0">
                                                                <input type="text" name="courses[${courseCounter}][facilitators][0][name]" placeholder="Facilitator Name">
                                                                <input type="text" name="courses[${courseCounter}][facilitators][0][cadre]" placeholder="Facilitator Cadre">
                                                                <span class="remove-facilitator" onclick="removeFacilitator(${courseCounter}, 0)" style="display: none;">
                                                                        <i class="fas fa-times"></i>
                                                                </span>
                                                        </div>
                                                </div>
                                                <button type="button" class="add-facilitator" onclick="addFacilitator(${courseCounter})">
                                                        <i class="fas fa-plus"></i> Add Another Facilitator
                                                </button>
                                        </div>

                                        <div class="form-group">
                                                <label>Skills Acquired / Key Takeaways</label>
                                                <textarea name="courses[${courseCounter}][skills_acquired]" rows="2" placeholder="List key skills learned..."></textarea>
                                        </div>
                                </div>
                        `;

                        container.insertAdjacentHTML('beforeend', courseHtml);
                }

                function removeCourse(courseId) {
                        if (confirm('Are you sure you want to remove this course?')) {
                                document.getElementById(`course-${courseId}`).remove();
                        }
                }

                function addFacilitator(courseId) {
                        const container = document.getElementById(`facilitators-${courseId}`);
                        const facilitatorCount = container.children.length;

                        const facilitatorHtml = `
                                <div class="facilitator-entry" id="facilitator-${courseId}-${facilitatorCount}">
                                        <input type="text" name="courses[${courseId}][facilitators][${facilitatorCount}][name]" placeholder="Facilitator Name">
                                        <input type="text" name="courses[${courseId}][facilitators][${facilitatorCount}][cadre]" placeholder="Facilitator Cadre">
                                        <span class="remove-facilitator" onclick="removeFacilitator(${courseId}, ${facilitatorCount})">
                                                <i class="fas fa-times"></i>
                                        </span>
                                </div>
                        `;

                        container.insertAdjacentHTML('beforeend', facilitatorHtml);
                }

                function removeFacilitator(courseId, facilitatorId) {
                        document.getElementById(`facilitator-${courseId}-${facilitatorId}`).remove();
                }

                function validateDates() {
                        const startDates = document.querySelectorAll('input[name$="[start_date]"]');
                        const endDates = document.querySelectorAll('input[name$="[end_date]"]');
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);

                        for (let i = 0; i < startDates.length; i++) {
                                const startDate = new Date(startDates[i].value);
                                const endDate = new Date(endDates[i].value);

                                if (startDate > today) {
                                        alert('Start date cannot be in the future');
                                        startDates[i].focus();
                                        return false;
                                }

                                if (endDate > today) {
                                        alert('End date cannot be in the future');
                                        endDates[i].focus();
                                        return false;
                                }

                                if (endDate < startDate) {
                                        alert('End date cannot be before start date');
                                        endDates[i].focus();
                                        return false;
                                }
                        }
                        return true;
                }

                function validateForm() {
                        const courseEntries = document.querySelectorAll('.course-entry');
                        if (courseEntries.length === 0) {
                                alert('Please add at least one course');
                                return false;
                        }

                        // Check required fields
                        const requiredFields = document.querySelectorAll('[required]');
                        for (let field of requiredFields) {
                                if (!field.value) {
                                        alert('Please fill in all required fields');
                                        field.focus();
                                        return false;
                                }
                        }

                        return validateDates();
                }

                function saveAsDraft() {
                        if (confirm('Save current progress as draft?')) {
                                const form = document.getElementById('trainingsForm');
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'action';
                                input.value = 'draft';
                                form.appendChild(input);
                                form.submit();
                        }
                }

                function submitAll() {
                        if (validateForm()) {
                                if (confirm('Are you sure you want to submit all courses? This action cannot be undone.')) {
                                        const form = document.getElementById('trainingsForm');
                                        const input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'action';
                                        input.value = 'submit';
                                        form.appendChild(input);
                                        form.submit();
                                }
                        }
                }

                // Add initial course on page load
                window.onload = function() {
                        addCourseEntry();
                };
        </script>
</body>
</html>