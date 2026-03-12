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

// Fetch dropdown data
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");
$training_types = $conn->query("SELECT trainingtype_id, trainingtype_name FROM trainingtypes ORDER BY trainingtype_name");
$facilitator_levels = $conn->query("SELECT fac_level_id, facilitator_level_name FROM facilitator_levels ORDER BY facilitator_level_name");
$training_locations = $conn->query("SELECT location_id, location_name FROM training_locations ORDER BY location_name");
$cadres = $conn->query("SELECT cadre_id, cadre_name FROM cadres ORDER BY cadre_name");

// Fetch staff's submitted trainings
$trainings = [];
if ($staff_id > 0) {
        $query = "SELECT sst.*,
                            (SELECT COUNT(*) FROM training_facilitators WHERE self_training_id = sst.self_training_id) as facilitator_count
                            FROM staff_self_trainings sst
                            WHERE sst.staff_id = ?
                            ORDER BY sst.start_date DESC
                            LIMIT 10";
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
                        padding: 15px;
                }

                .container {
                        max-width: 100%;
                        margin: 0 auto;
                }

                /* Header */
                .header {
                        background: #0D1A63;
                        color: white;
                        padding: 15px 20px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }

                .header h1 {
                        font-size: 22px;
                        font-weight: 600;
                }

                .header h1 i {
                        margin-right: 8px;
                }

                .header-actions {
                        display: flex;
                        gap: 10px;
                }

                .btn {
                        padding: 8px 15px;
                        border: none;
                        border-radius: 6px;
                        font-size: 13px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        text-decoration: none;
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                }

                .btn-primary {
                        background: white;
                        color: #0D1A63;
                }

                .btn-primary:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 3px 10px rgba(255,255,255,0.2);
                }

                .btn-success {
                        background: #28a745;
                        color: white;
                }

                .btn-success:hover {
                        background: #218838;
                }

                .btn-info {
                        background: #17a2b8;
                        color: white;
                }

                .btn-warning {
                        background: #ffc107;
                        color: #212529;
                }

                .btn-secondary {
                        background: #6c757d;
                        color: white;
                }

                .btn-sm {
                        padding: 5px 10px;
                        font-size: 12px;
                }

                /* Staff Profile - Compact */
                .staff-profile {
                        background: white;
                        border-radius: 10px;
                        padding: 15px;
                        margin-bottom: 20px;
                        display: flex;
                        align-items: center;
                        gap: 20px;
                        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                }

                .staff-avatar {
                        width: 50px;
                        height: 50px;
                        background: #0D1A63;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 20px;
                        color: white;
                        flex-shrink: 0;
                }

                .staff-info {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 20px;
                        flex: 1;
                }

                .info-item {
                        font-size: 13px;
                        min-width: 150px;
                }

                .info-item label {
                        color: #666;
                        font-size: 11px;
                        text-transform: uppercase;
                        display: block;
                }

                .info-item span {
                        font-weight: 600;
                        color: #333;
                }

                /* Training Form - Table Layout */
                .training-form {
                        background: white;
                        border-radius: 10px;
                        padding: 20px;
                        margin-bottom: 25px;
                        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                }

                .training-form h2 {
                        color: #0D1A63;
                        font-size: 18px;
                        margin-bottom: 15px;
                        border-bottom: 1px solid #e0e0e0;
                        padding-bottom: 10px;
                }

                .training-form h2 i {
                        margin-right: 8px;
                }

                /* Table-like Course Entry */
                .courses-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 15px;
                }

                .courses-table thead th {
                        background: #f8f9fa;
                        padding: 10px;
                        font-size: 12px;
                        font-weight: 600;
                        color: #0D1A63;
                        text-align: left;
                        border-bottom: 2px solid #e0e0e0;
                        white-space: nowrap;
                }

                .course-row {
                        background: #ffffff;
                        border-bottom: 1px solid #e0e0e0;
                }

                .course-row:hover {
                        background: #f5f5f5;
                }

                .course-row td {
                        padding: 10px;
                        vertical-align: top;
                }

                .course-row .form-control {
                        width: 100%;
                        padding: 8px 10px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        font-size: 13px;
                        transition: all 0.2s;
                }

                .course-row .form-control:focus {
                        outline: none;
                        border-color: #0D1A63;
                        box-shadow: 0 0 0 2px rgba(13,26,99,0.1);
                }

                .course-row select.form-control {
                        height: 35px;
                        background-color: white;
                }

                .facilitator-cell {
                        min-width: 200px;
                }

                .facilitator-entry {
                        display: flex;
                        gap: 5px;
                        margin-bottom: 5px;
                        align-items: center;
                }

                .facilitator-entry input {
                        flex: 1;
                        padding: 6px 8px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        font-size: 12px;
                }

                .add-facilitator {
                        background: #17a2b8;
                        color: white;
                        border: none;
                        padding: 5px 8px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 11px;
                        display: inline-flex;
                        align-items: center;
                        gap: 3px;
                        margin-top: 5px;
                }

                .remove-facilitator {
                        color: #dc3545;
                        cursor: pointer;
                        font-size: 14px;
                }

                .remove-course {
                        color: #dc3545;
                        cursor: pointer;
                        font-size: 16px;
                        text-align: center;
                }

                .remove-course:hover {
                        color: #bd2130;
                }

                .btn-add-course {
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
                        margin-bottom: 15px;
                }

                .btn-add-course:hover {
                        background: #218838;
                }

                .form-actions {
                        display: flex;
                        gap: 10px;
                        justify-content: flex-end;
                        margin-top: 20px;
                        padding-top: 15px;
                        border-top: 1px solid #e0e0e0;
                }

                .btn-submit, .btn-draft {
                        padding: 10px 25px;
                        border: none;
                        border-radius: 5px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s;
                }

                .btn-submit {
                        background: #28a745;
                        color: white;
                }

                .btn-submit:hover {
                        background: #218838;
                        transform: translateY(-2px);
                }

                .btn-draft {
                        background: #ffc107;
                        color: #212529;
                }

                .btn-draft:hover {
                        background: #e0a800;
                        transform: translateY(-2px);
                }

                /* Recent Trainings Table */
                .recent-trainings {
                        background: white;
                        border-radius: 10px;
                        padding: 20px;
                        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                }

                .recent-trainings h3 {
                        color: #0D1A63;
                        font-size: 18px;
                        margin-bottom: 15px;
                }

                .trainings-table {
                        width: 100%;
                        border-collapse: collapse;
                }

                .trainings-table th {
                        background: #f8f9fa;
                        padding: 10px;
                        font-size: 12px;
                        font-weight: 600;
                        color: #555;
                        text-align: left;
                        border-bottom: 2px solid #e0e0e0;
                }

                .trainings-table td {
                        padding: 10px;
                        border-bottom: 1px solid #e0e0e0;
                        font-size: 13px;
                }

                .status-badge {
                        padding: 3px 8px;
                        border-radius: 3px;
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
                        padding: 4px 8px;
                        border-radius: 3px;
                        font-size: 11px;
                        text-decoration: none;
                        color: white;
                }

                .btn-view { background: #17a2b8; }
                .btn-print { background: #6c757d; }
                .btn-edit { background: #ffc107; color: #212529; }

                .alert {
                        padding: 12px 15px;
                        border-radius: 6px;
                        margin-bottom: 20px;
                        font-size: 13px;
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

                @media (max-width: 1200px) {
                        .courses-table {
                                display: block;
                                overflow-x: auto;
                        }
                }
        </style>
</head>
<body>
        <div class="container">
                <div class="header">
                        <h1><i class="fas fa-chalkboard-teacher"></i> Staff Training Management</h1>
                        <div class="header-actions">
                                <a href="view_staff_trainings.php" class="btn btn-primary">
                                        <i class="fas fa-search"></i> View All
                                </a>
                                <a href="training_list.php" class="btn btn-primary">
                                        <i class="fas fa-calendar-alt"></i> Sessions
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
                        <!-- Staff Profile - Compact -->
                        <div class="staff-profile">
                                <div class="staff-avatar">
                                        <?php echo strtoupper(substr($staff_data['first_name'], 0, 1) . substr($staff_data['last_name'], 0, 1)); ?>
                                </div>
                                <div class="staff-info">
                                        <div class="info-item">
                                                <label>Name</label>
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
                                                <label>Facility</label>
                                                <span><?php echo htmlspecialchars($staff_data['facility_name'] ?? 'N/A'); ?></span>
                                        </div>
                                </div>
                        </div>

                        <!-- Training Form - Table Layout -->
                        <div class="training-form">
                                <h2><i class="fas fa-plus-circle"></i> Add Trainings</h2>
                                <form method="POST" action="submit_staff_trainings.php" id="trainingsForm">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff_data['staff_id']; ?>">
                                        <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($staff_data['id_number']); ?>">

                                        <table class="courses-table" id="coursesTable">
                                                <thead>
                                                        <tr>
                                                                <th style="width: 5%">#</th>
                                                                <th style="width: 12%">Course</th>
                                                                <th style="width: 10%">Type</th>
                                                                <th style="width: 10%">Start Date</th>
                                                                <th style="width: 10%">End Date</th>
                                                                <th style="width: 12%">Provider</th>
                                                                <th style="width: 12%">Venue</th>
                                                                <th style="width: 15%">Facilitators</th>
                                                                <th style="width: 8%">Certificate #</th>
                                                                <th style="width: 4%"></th>
                                                        </tr>
                                                </thead>
                                                <tbody id="coursesBody">
                                                        <!-- Courses will be added here dynamically -->
                                                </tbody>
                                        </table>

                                        <button type="button" class="btn-add-course" onclick="addCourse()">
                                                <i class="fas fa-plus"></i> Add Course
                                        </button>

                                        <div class="form-actions">
                                                <button type="button" class="btn-draft" onclick="saveAsDraft()">
                                                        <i class="fas fa-save"></i> Save Draft
                                                </button>
                                                <button type="button" class="btn-submit" onclick="submitAll()">
                                                        <i class="fas fa-check-circle"></i> Submit All
                                                </button>
                                        </div>
                                </form>
                        </div>

                        <!-- Recent Trainings -->
                        <div class="recent-trainings">
                                <h3><i class="fas fa-history"></i> Recent Trainings</h3>
                                <table class="trainings-table">
                                        <thead>
                                                <tr>
                                                        <th>Course</th>
                                                        <th>Dates</th>
                                                        <th>Provider</th>
                                                        <th>Facilitators</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <?php if (empty($trainings)): ?>
                                                        <tr>
                                                                <td colspan="6" style="text-align: center; padding: 20px;">
                                                                        <i class="fas fa-folder-open" style="color: #ccc; font-size: 24px; margin-bottom: 5px; display: block;"></i>
                                                                        No trainings recorded yet
                                                                </td>
                                                        </tr>
                                                <?php else: ?>
                                                        <?php foreach ($trainings as $training): ?>
                                                                <tr>
                                                                        <td><?php echo htmlspecialchars($training['course_name']); ?></td>
                                                                        <td><?php echo date('d/m/Y', strtotime($training['start_date'])) . ' - ' . date('d/m/Y', strtotime($training['end_date'])); ?></td>
                                                                        <td><?php echo htmlspecialchars($training['training_provider'] ?? 'N/A'); ?></td>
                                                                        <td><?php echo $training['facilitator_count']; ?> facilitator(s)</td>
                                                                        <td>
                                                                                <span class="status-badge status-<?php echo $training['status']; ?>">
                                                                                        <?php echo ucfirst($training['status']); ?>
                                                                                </span>
                                                                        </td>
                                                                        <td class="action-cell">
                                                                                <a href="view_staff_training.php?id=<?php echo $training['self_training_id']; ?>" class="action-btn btn-view">
                                                                                        <i class="fas fa-eye"></i>
                                                                                </a>
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

                // PHP data for dropdowns
                const courses = <?php echo json_encode($courses->fetch_all(MYSQLI_ASSOC)); ?>;
                const trainingTypes = <?php echo json_encode($training_types->fetch_all(MYSQLI_ASSOC)); ?>;
                const facilitatorLevels = <?php echo json_encode($facilitator_levels->fetch_all(MYSQLI_ASSOC)); ?>;
                const trainingLocations = <?php echo json_encode($training_locations->fetch_all(MYSQLI_ASSOC)); ?>;
                const cadres = <?php echo json_encode($cadres->fetch_all(MYSQLI_ASSOC)); ?>;

                function addCourse() {
                        courseCounter++;
                        const tbody = document.getElementById('coursesBody');
                        const today = new Date().toISOString().split('T')[0];

                        // Build course dropdown options
                        let courseOptions = '<option value="">Select Course</option>';
                        courses.forEach(course => {
                                courseOptions += `<option value="${course.course_id}">${escapeHtml(course.course_name)}</option>`;
                        });

                        // Build training type options
                        let typeOptions = '<option value="">Select Type</option>';
                        trainingTypes.forEach(type => {
                                typeOptions += `<option value="${type.trainingtype_id}">${escapeHtml(type.trainingtype_name)}</option>`;
                        });

                        // Build provider options (facilitator levels)
                        let providerOptions = '<option value="">Select Provider</option>';
                        facilitatorLevels.forEach(level => {
                                providerOptions += `<option value="${level.fac_level_id}">${escapeHtml(level.facilitator_level_name)}</option>`;
                        });

                        // Build venue options
                        let venueOptions = '<option value="">Select Venue</option>';
                        trainingLocations.forEach(location => {
                                venueOptions += `<option value="${location.location_id}">${escapeHtml(location.location_name)}</option>`;
                        });

                        const row = document.createElement('tr');
                        row.className = 'course-row';
                        row.id = `course-${courseCounter}`;
                        row.innerHTML = `
                                <td>${courseCounter}</td>
                                <td>
                                        <select name="courses[${courseCounter}][course_id]" class="form-control" required onchange="updateCourseName(this, ${courseCounter})">
                                                ${courseOptions}
                                        </select>
                                        <input type="hidden" name="courses[${courseCounter}][course_name]" id="course_name_${courseCounter}">
                                </td>
                                <td>
                                        <select name="courses[${courseCounter}][training_type_id]" class="form-control" required onchange="updateTrainingType(this, ${courseCounter})">
                                                ${typeOptions}
                                        </select>
                                        <input type="hidden" name="courses[${courseCounter}][training_type]" id="training_type_${courseCounter}">
                                </td>
                                <td>
                                        <input type="date" name="courses[${courseCounter}][start_date]" class="form-control" max="${today}" required>
                                </td>
                                <td>
                                        <input type="date" name="courses[${courseCounter}][end_date]" class="form-control" max="${today}" required>
                                </td>
                                <td>
                                        <select name="courses[${courseCounter}][provider_id]" class="form-control" onchange="updateProvider(this, ${courseCounter})">
                                                ${providerOptions}
                                        </select>
                                        <input type="hidden" name="courses[${courseCounter}][training_provider]" id="provider_${courseCounter}">
                                </td>
                                <td>
                                        <select name="courses[${courseCounter}][location_id]" class="form-control" onchange="updateVenue(this, ${courseCounter})">
                                                ${venueOptions}
                                        </select>
                                        <input type="hidden" name="courses[${courseCounter}][venue]" id="venue_${courseCounter}">
                                </td>
                                <td class="facilitator-cell">
                                        <div id="facilitators-${courseCounter}">
                                                <div class="facilitator-entry" id="facilitator-${courseCounter}-0">
                                                        <input type="text" name="courses[${courseCounter}][facilitators][0][name]" placeholder="Name" style="width: 45%;">
                                                        <select name="courses[${courseCounter}][facilitators][0][cadre_id]" style="width: 45%;" onchange="updateFacilitatorCadre(this, ${courseCounter}, 0)">
                                                                <option value="">Select Cadre</option>
                                                                ${cadres.map(c => `<option value="${c.cadre_id}">${escapeHtml(c.cadre_name)}</option>`).join('')}
                                                        </select>
                                                        <input type="hidden" name="courses[${courseCounter}][facilitators][0][cadre]" id="facilitator_cadre_${courseCounter}_0">
                                                        <span class="remove-facilitator" onclick="removeFacilitator(${courseCounter}, 0)" style="display: none;">
                                                                <i class="fas fa-times"></i>
                                                        </span>
                                                </div>
                                        </div>
                                        <button type="button" class="add-facilitator" onclick="addFacilitator(${courseCounter})">
                                                <i class="fas fa-plus"></i> Add
                                        </button>
                                </td>
                                <td>
                                        <input type="text" name="courses[${courseCounter}][certificate_number]" class="form-control" placeholder="Cert #">
                                </td>
                                <td class="remove-course" onclick="removeCourse(${courseCounter})">
                                        <i class="fas fa-times-circle"></i>
                                </td>
                        `;

                        tbody.appendChild(row);
                }

                function updateCourseName(select, courseId) {
                        const selectedOption = select.options[select.selectedIndex];
                        const courseName = selectedOption.text;
                        document.getElementById(`course_name_${courseId}`).value = courseName;
                }

                function updateTrainingType(select, courseId) {
                        const selectedOption = select.options[select.selectedIndex];
                        const typeName = selectedOption.text;
                        document.getElementById(`training_type_${courseId}`).value = typeName;
                }

                function updateProvider(select, courseId) {
                        const selectedOption = select.options[select.selectedIndex];
                        const providerName = selectedOption.text;
                        document.getElementById(`provider_${courseId}`).value = providerName;
                }

                function updateVenue(select, courseId) {
                        const selectedOption = select.options[select.selectedIndex];
                        const venueName = selectedOption.text;
                        document.getElementById(`venue_${courseId}`).value = venueName;
                }

                function updateFacilitatorCadre(select, courseId, facilitatorId) {
                        const selectedOption = select.options[select.selectedIndex];
                        const cadre_name = selectedOption.text;
                        document.getElementById(`facilitator_cadre_${courseId}_${facilitatorId}`).value = cadre_name;
                }

                function addFacilitator(courseId) {
                        const container = document.getElementById(`facilitators-${courseId}`);
                        const facilitatorCount = container.children.length;

                        const facilitatorDiv = document.createElement('div');
                        facilitatorDiv.className = 'facilitator-entry';
                        facilitatorDiv.id = `facilitator-${courseId}-${facilitatorCount}`;
                        facilitatorDiv.innerHTML = `
                                <input type="text" name="courses[${courseId}][facilitators][${facilitatorCount}][name]" placeholder="Facilitator Name" style="width: 45%;">
                                <select name="courses[${courseId}][facilitators][${facilitatorCount}][cadre_id]" style="width: 45%;" onchange="updateFacilitatorCadre(this, ${courseId}, ${facilitatorCount})">
                                        <option value="">Select Cadre</option>
                                        ${cadres.map(c => `<option value="${c.cadre_id}">${escapeHtml(c.cadre_name)}</option>`).join('')}
                                </select>
                                <input type="hidden" name="courses[${courseId}][facilitators][${facilitatorCount}][cadre]" id="facilitator_cadre_${courseId}_${facilitatorCount}">
                                <span class="remove-facilitator" onclick="removeFacilitator(${courseId}, ${facilitatorCount})">
                                        <i class="fas fa-times"></i>
                                </span>
                        `;

                        container.appendChild(facilitatorDiv);
                }

                function removeFacilitator(courseId, facilitatorId) {
                        document.getElementById(`facilitator-${courseId}-${facilitatorId}`).remove();
                }

                function removeCourse(courseId) {
                        if (confirm('Remove this course?')) {
                                document.getElementById(`course-${courseId}`).remove();
                                renumberCourses();
                        }
                }

                function renumberCourses() {
                        const rows = document.querySelectorAll('.course-row');
                        rows.forEach((row, index) => {
                                const newIndex = index + 1;
                                row.querySelector('td:first-child').textContent = newIndex;
                                row.id = `course-${newIndex}`;

                                // Update all name attributes
                                const inputs = row.querySelectorAll('input, select');
                                inputs.forEach(input => {
                                        if (input.name) {
                                                input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                                        }
                                });
                        });
                        courseCounter = rows.length;
                }

                function escapeHtml(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                }

                function validateDates() {
                        const rows = document.querySelectorAll('.course-row');
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);

                        for (let row of rows) {
                                const startInput = row.querySelector('input[name$="[start_date]"]');
                                const endInput = row.querySelector('input[name$="[end_date]"]');

                                if (!startInput || !endInput) continue;

                                const startDate = new Date(startInput.value);
                                const endDate = new Date(endInput.value);

                                if (startDate > today) {
                                        alert('Start date cannot be in the future');
                                        startInput.focus();
                                        return false;
                                }

                                if (endDate > today) {
                                        alert('End date cannot be in the future');
                                        endInput.focus();
                                        return false;
                                }

                                if (endDate < startDate) {
                                        alert('End date cannot be before start date');
                                        endInput.focus();
                                        return false;
                                }
                        }
                        return true;
                }

                function validateForm() {
                        const rows = document.querySelectorAll('.course-row');
                        if (rows.length === 0) {
                                alert('Please add at least one course');
                                return false;
                        }

                        // Check required fields
                        for (let row of rows) {
                                const requiredSelects = row.querySelectorAll('select[required]');
                                for (let select of requiredSelects) {
                                        if (!select.value) {
                                                alert('Please fill in all required fields');
                                                select.focus();
                                                return false;
                                        }
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
                                if (confirm('Submit all courses?')) {
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

                // Add first course on page load
                window.onload = function() {
                        addCourse();
                };
        </script>
</body>
</html>