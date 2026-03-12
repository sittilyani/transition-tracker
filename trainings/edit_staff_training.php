<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Get training ID
$training_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($training_id === 0) {
    $_SESSION['error_msg'] = "Invalid training ID.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Fetch training details
$query = "SELECT sst.*,
          cs.first_name, cs.last_name, cs.facility_name, cs.department_name,
          cs.cadre_name, cs.county_name, cs.subcounty_name
          FROM staff_self_trainings sst
          JOIN county_staff cs ON sst.staff_id = cs.staff_id
          WHERE sst.self_training_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $training_id);
$stmt->execute();
$result = $stmt->get_result();
$training = $result->fetch_assoc();
$stmt->close();

if (!$training) {
    $_SESSION['error_msg'] = "Training record not found.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Check if user has permission to edit (admin or the staff who created it)
$can_edit = ($_SESSION['userrole'] === 'Admin' || $_SESSION['staff_id'] == $training['staff_id']);

if (!$can_edit) {
    $_SESSION['error_msg'] = "You don't have permission to edit this training.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Fetch facilitators for this training
$facilitators = [];
$fac_query = "SELECT * FROM training_facilitators WHERE self_training_id = ? ORDER BY facilitator_id";
$fac_stmt = $conn->prepare($fac_query);
$fac_stmt->bind_param('i', $training_id);
$fac_stmt->execute();
$fac_result = $fac_stmt->get_result();
while ($fac = $fac_result->fetch_assoc()) {
    $facilitators[] = $fac;
}
$fac_stmt->close();

// Fetch dropdown data
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");
$training_types = $conn->query("SELECT trainingtype_id, trainingtype_name FROM trainingtypes ORDER BY trainingtype_name");
$facilitator_levels = $conn->query("SELECT fac_level_id, facilitator_level_name FROM facilitator_levels ORDER BY facilitator_level_name");
$training_locations = $conn->query("SELECT location_id, location_name FROM training_locations ORDER BY location_name");
$cadres = $conn->query("SELECT cadre_id, cadre_name FROM cadres ORDER BY cadre_name");

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Training</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: #0D1A63;
            color: white;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .header h1 i {
            margin-right: 10px;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .training-form {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .training-form h2 {
            color: #0D1A63;
            font-size: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 2px rgba(13,26,99,0.1);
        }

        select.form-control {
            height: 40px;
            background-color: white;
        }

        .facilitator-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e0e0e0;
        }

        .facilitator-section h3 {
            color: #0D1A63;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .facilitator-entry {
            display: grid;
            grid-template-columns: 1fr 1fr 30px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .facilitator-entry input,
        .facilitator-entry select {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .add-facilitator {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }

        .remove-facilitator {
            color: #dc3545;
            cursor: pointer;
            font-size: 16px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-update {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-update:hover {
            background: #218838;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-draft { background: #ffc107; color: #212529; }
        .status-submitted { background: #17a2b8; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> Edit Training Record</h1>
            <div>
                <span class="status-badge status-<?php echo $training['status']; ?>">
                    <?php echo ucfirst($training['status']); ?>
                </span>
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

        <?php if ($training['status'] == 'submitted' || $training['status'] == 'verified'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> This training has been <?php echo $training['status']; ?> and cannot be edited.
                <a href="view_staff_training.php?id=<?php echo $training_id; ?>" class="btn btn-primary" style="margin-left: 10px;">View Details</a>
            </div>
        <?php endif; ?>

        <?php if ($training['status'] == 'draft'): ?>
        <div class="training-form">
            <form method="POST" action="update_staff_training.php" id="editForm">
                <input type="hidden" name="training_id" value="<?php echo $training_id; ?>">
                <input type="hidden" name="staff_id" value="<?php echo $training['staff_id']; ?>">
                <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($training['id_number']); ?>">

                <h2><i class="fas fa-info-circle"></i> Training Details</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Course</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">Select Course</option>
                            <?php
                            mysqli_data_seek($courses, 0);
                            while ($course = $courses->fetch_assoc()):
                            ?>
                                <option value="<?php echo $course['course_id']; ?>"
                                    <?php echo ($training['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="required">Training Type</label>
                        <select name="training_type_id" class="form-control" required>
                            <option value="">Select Type</option>
                            <?php
                            mysqli_data_seek($training_types, 0);
                            while ($type = $training_types->fetch_assoc()):
                            ?>
                                <option value="<?php echo $type['trainingtype_id']; ?>"
                                    <?php echo ($training['training_type_id'] == $type['trainingtype_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['trainingtype_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="required">Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?php echo $training['start_date']; ?>"
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="required">End Date</label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?php echo $training['end_date']; ?>"
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Training Provider</label>
                        <select name="provider_id" class="form-control">
                            <option value="">Select Provider</option>
                            <?php
                            mysqli_data_seek($facilitator_levels, 0);
                            while ($provider = $facilitator_levels->fetch_assoc()):
                            ?>
                                <option value="<?php echo $provider['fac_level_id']; ?>"
                                    <?php echo ($training['provider_id'] == $provider['fac_level_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($provider['facilitator_level_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Venue/Location</label>
                        <select name="location_id" class="form-control">
                            <option value="">Select Venue</option>
                            <?php
                            mysqli_data_seek($training_locations, 0);
                            while ($location = $training_locations->fetch_assoc()):
                            ?>
                                <option value="<?php echo $location['location_id']; ?>"
                                    <?php echo ($training['location_id'] == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['location_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Certificate Number</label>
                        <input type="text" name="certificate_number" class="form-control"
                               value="<?php echo htmlspecialchars($training['certificate_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Facilitators Section -->
                <div class="facilitator-section">
                    <h3><i class="fas fa-users"></i> Facilitators</h3>
                    <div id="facilitators-container">
                        <?php if (empty($facilitators)): ?>
                        <div class="facilitator-entry" id="facilitator-0">
                            <input type="text" name="facilitators[0][name]" placeholder="Facilitator Name" class="form-control">
                            <select name="facilitators[0][cadre_id]" class="form-control">
                                <option value="">Select Cadre</option>
                                <?php
                                mysqli_data_seek($cadres, 0);
                                while ($cadre = $cadres->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $cadre['cadre_id']; ?>">
                                        <?php echo htmlspecialchars($cadre['cadre_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span class="remove-facilitator" onclick="removeFacilitator(0)" style="display: none;">
                                <i class="fas fa-times"></i>
                            </span>
                        </div>
                        <?php else: ?>
                            <?php foreach ($facilitators as $index => $facilitator): ?>
                            <div class="facilitator-entry" id="facilitator-<?php echo $index; ?>">
                                <input type="text" name="facilitators[<?php echo $index; ?>][name]"
                                       value="<?php echo htmlspecialchars($facilitator['facilitator_name']); ?>"
                                       placeholder="Facilitator Name" class="form-control" required>
                                <select name="facilitators[<?php echo $index; ?>][cadre_id]" class="form-control" required>
                                    <option value="">Select Cadre</option>
                                    <?php
                                    mysqli_data_seek($cadres, 0);
                                    while ($cadre = $cadres->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $cadre['cadre_id']; ?>"
                                            <?php echo ($facilitator['facilitator_cadre_id'] == $cadre['cadre_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cadre['cadre_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <span class="remove-facilitator" onclick="removeFacilitator(<?php echo $index; ?>)">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="add-facilitator" onclick="addFacilitator()">
                        <i class="fas fa-plus"></i> Add Another Facilitator
                    </button>
                </div>

                <div class="form-actions">
                    <a href="view_staff_trainings.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" name="action" value="update" class="btn-update">
                        <i class="fas fa-save"></i> Update Training
                    </button>
                    <?php if ($training['status'] == 'draft'): ?>
                    <button type="submit" name="action" value="submit" class="btn-update" style="background: #17a2b8;">
                        <i class="fas fa-check-circle"></i> Submit for Verification
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let facilitatorCount = <?php echo max(count($facilitators), 1); ?>;

        function addFacilitator() {
            const container = document.getElementById('facilitators-container');
            const entry = document.createElement('div');
            entry.className = 'facilitator-entry';
            entry.id = `facilitator-${facilitatorCount}`;

            entry.innerHTML = `
                <input type="text" name="facilitators[${facilitatorCount}][name]"
                       placeholder="Facilitator Name" class="form-control" required>
                <select name="facilitators[${facilitatorCount}][cadre_id]" class="form-control" required>
                    <option value="">Select Cadre</option>
                    <?php
                    mysqli_data_seek($cadres, 0);
                    while ($cadre = $cadres->fetch_assoc()):
                    ?>
                        <option value="<?php echo $cadre['cadre_id']; ?>">
                            <?php echo htmlspecialchars($cadre['cadre_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span class="remove-facilitator" onclick="removeFacilitator(${facilitatorCount})">
                    <i class="fas fa-times"></i>
                </span>
            `;

            container.appendChild(entry);
            facilitatorCount++;
        }

        function removeFacilitator(index) {
            const element = document.getElementById(`facilitator-${index}`);
            if (element) {
                element.remove();
            }
        }

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('[name="start_date"]').value);
            const endDate = new Date(document.querySelector('[name="end_date"]').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (startDate > today) {
                e.preventDefault();
                alert('Start date cannot be in the future');
                return false;
            }

            if (endDate > today) {
                e.preventDefault();
                alert('End date cannot be in the future');
                return false;
            }

            if (endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be before start date');
                return false;
            }

            // Validate at least one facilitator
            const facilitators = document.querySelectorAll('[name$="[name]"]');
            let hasValidFacilitator = false;
            facilitators.forEach(f => {
                if (f.value.trim() !== '') hasValidFacilitator = true;
            });

            if (!hasValidFacilitator) {
                e.preventDefault();
                alert('Please add at least one facilitator');
                return false;
            }

            return true;
        });
    </script>
</body>
</html>