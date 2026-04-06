<?php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

$error = '';
$success = '';

// Get logged-in user for added_by field
$added_by = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';

// Fetch existing course sections for dropdown
$sections = [];
$section_query = "SELECT DISTINCT course_section FROM courses WHERE course_section IS NOT NULL AND course_section != '' ORDER BY course_section";
$section_result = $conn->query($section_query);
if ($section_result && $section_result->num_rows > 0) {
    while ($row = $section_result->fetch_assoc()) {
        $sections[] = $row['course_section'];
    }
}

// Predefined section suggestions
$section_suggestions = [
    'Pharmacy', 'PMTCT', 'Clinical Services', 'Laboratory Services',
    'Nursing', 'Administration', 'Finance', 'Human Resources',
    'HIV/TB Services', 'MCH Services', 'Emergency Services', 'Radiology',
    'Dental Services', 'Nutrition', 'Pharmaceutical Technology', 'Health Records'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_name = mysqli_real_escape_string($conn, trim($_POST['course_name']));
    $course_section = mysqli_real_escape_string($conn, trim($_POST['course_section']));

    // Validate data
    if (empty($course_name)) {
        $error = "Please enter the course name.";
    } elseif (empty($course_section)) {
        $error = "Please enter the course section.";
    } else {
        // Check if course already exists in the same section
        $check_sql = "SELECT * FROM courses WHERE course_name = '$course_name' AND course_section = '$course_section'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error = "This course already exists in the selected section.";
        } else {
            // Insert data into training_courses table
            $sql = "INSERT INTO courses (course_name, course_section, added_by, created_at)
                    VALUES ('$course_name', '$course_section', '$added_by', NOW())";

            if ($conn->query($sql) === TRUE) {
                $_SESSION['success_msg'] = 'Training course added successfully!';
                header('Location: add_training.php?success=1');
                exit();
            } else {
                $error = "Error: " . $sql . "<br>" . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Training Course - Transition Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f7;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #0D1A63 0%, #1a3a9e 100%);
            color: #fff;
            padding: 22px 30px;
            border-radius: 14px;
            margin-bottom: 30px;
            box-shadow: 0 6px 24px rgba(13,26,99,.25);
        }

        .page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 5px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #0D1A63;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }

        .back-link:hover {
            background: #e8edf8;
            transform: translateX(-2px);
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Form Card */
        .form-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(90deg, #f8fafc, #fff);
            padding: 20px 25px;
            border-bottom: 1px solid #e8ecf5;
        }

        .form-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0D1A63;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-body {
            padding: 25px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 6px;
            color: #0D1A63;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e4f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all .2s;
            background: #fff;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #0D1A63;
            box-shadow: 0 0 0 3px rgba(13,26,99,.1);
        }

        /* Autocomplete / Suggestions */
        .suggestions-box {
            position: relative;
        }

        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e0e4f0;
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: background .2s;
            font-size: 13px;
        }

        .suggestion-item:hover {
            background: #f0f3fb;
        }

        /* Button */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #0D1A63;
            color: #fff;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #1a2a7a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13,26,99,.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #666;
            flex: 1;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Required Field */
        .required {
            color: #dc3545;
            margin-left: 4px;
        }

        small {
            color: #999;
            font-size: 11px;
            display: block;
            margin-top: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: #999;
            font-size: 12px;
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 3px solid #0D1A63;
        }

        .info-box p {
            font-size: 12px;
            color: #666;
            margin-bottom: 0;
        }

        .info-box i {
            color: #0D1A63;
            margin-right: 8px;
        }

        /* Courses List Preview */
        .preview-list {
            margin-top: 20px;
            border-top: 1px solid #e8ecf5;
            padding-top: 20px;
        }

        .preview-list h4 {
            font-size: 13px;
            color: #0D1A63;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 250px;
            overflow-y: auto;
        }

        .preview-item {
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid #0D1A63;
        }

        .preview-course-name {
            font-weight: 500;
            color: #333;
        }

        .preview-section {
            background: #e8edf8;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            color: #0D1A63;
            font-weight: 600;
        }

        /* Created By Info */
        .creator-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e8ecf5;
        }

        .creator-icon {
            width: 40px;
            height: 40px;
            background: #0D1A63;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
        }

        .creator-details {
            flex: 1;
        }

        .creator-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }

        .creator-name {
            font-size: 14px;
            font-weight: 600;
            color: #0D1A63;
        }

        /* Section Badge */
        .section-badge {
            display: inline-block;
            background: #e8edf8;
            color: #0D1A63;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .section-tag {
            display: inline-block;
            background: #e8edf8;
            color: #0D1A63;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="page-header">
        <h1>
            <i class="fas fa-book-open"></i>
            Add Training Course
        </h1>
        <p>Add a new training course for staff development programs</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Training course added successfully! Redirecting...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "add_training.php";
        }, 2000);
    </script>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <h2>
                <i class="fas fa-plus-circle"></i>
                Training Course Information
            </h2>
        </div>
        <div class="form-body">
            <!-- Created By Info -->
            <div class="creator-info">
                <div class="creator-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="creator-details">
                    <div class="creator-label">Record will be created by</div>
                    <div class="creator-name"><?= htmlspecialchars($added_by) ?></div>
                </div>
            </div>

            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Course Name <span class="required">*</span></label>
                    <input type="text" name="course_name" class="form-control"
                           placeholder="e.g., Pharmacovigilance, Advanced HIV Management, Laboratory Quality Control" required>
                    <small>Full name of the training course</small>
                </div>

                <div class="form-group suggestions-box">
                    <label><i class="fas fa-layer-group"></i> Course Section <span class="required">*</span></label>
                    <input type="text" name="course_section" id="course_section" class="form-control"
                           placeholder="e.g., Pharmacy, PMTCT, Clinical Services, Laboratory Services"
                           list="section-suggestions" autocomplete="off" required>
                    <datalist id="section-suggestions">
                        <?php foreach ($section_suggestions as $suggestion): ?>
                        <option value="<?= htmlspecialchars($suggestion) ?>">
                        <?php endforeach; ?>
                        <?php foreach ($sections as $existing): ?>
                        <option value="<?= htmlspecialchars($existing) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small><i class="fas fa-folder"></i> Department or category under which this course falls</small>
                </div>

                <div class="info-box" style="margin-top: 10px; margin-bottom: 10px;">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <strong>Course Section Examples:</strong> Pharmacy, PMTCT, Clinical Services, Laboratory Services,
                        Nursing, Administration, Finance, Human Resources, HIV/TB Services, MCH Services, etc.
                    </p>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Course
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="preview-list">
                <h4>
                    <i class="fas fa-list"></i>
                    Existing Training Courses
                </h4>
                <div class="preview-items">
                    <?php
                    // Fetch existing courses for preview
                    $preview_sql = "SELECT course_name, course_section FROM courses ORDER BY course_section, course_name LIMIT 20";
                    $preview_result = $conn->query($preview_sql);
                    if ($preview_result && $preview_result->num_rows > 0) {
                        while ($row = $preview_result->fetch_assoc()) {
                            echo '<div class="preview-item">
                                    <span class="preview-course-name">' . htmlspecialchars($row['course_name']) . '</span>
                                    <span class="preview-section">' . htmlspecialchars($row['course_section']) . '</span>
                                  </div>';
                        }
                    } else {
                        echo '<p style="color: #999; font-size: 12px; text-align: center; padding: 20px;">No training courses added yet.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-database"></i> Transition Benchmarking System | Add Training Course
    </div>
</div>

<script>
    // Auto-suggest for course section
    const sectionInput = document.getElementById('course_section');
    const suggestionsList = document.getElementById('suggestions-list');

    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!sectionInput.contains(e.target)) {
            if (suggestionsList) {
                suggestionsList.style.display = 'none';
            }
        }
    });
</script>
</body>
</html>