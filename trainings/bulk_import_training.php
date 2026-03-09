<?php
// bulk_import_training.php - processing part
require_once '../includes/config.php';
require '../vendor/autoload.php';  // if using Composer
require '../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST['import']) && !empty($_FILES['import_file']['name'])) {

    $file = $_FILES['import_file']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

    try {
        $spreadsheet = IOFactory::load($file);
        $worksheet   = $spreadsheet->getActiveSheet();
        $rows        = $worksheet->toArray();

        // Skip header row (row 1)
        array_shift($rows);

        $inserted = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            // Map columns carefully (adjust indices to your Excel/CSV layout!)
            // Example assuming this column order:
            // 0=staff_name, 1=staff_p_no, 2=staff_phone, 3=email, 4=department,
            // 5=sex_name, 6=facilityname, 7=course_name, 8=trainingtype_name,
            // 9=training_date, 10=facilitator_name, ...

            $staff_name         = trim($row[0] ?? '');
            $staff_p_no         = trim($row[1] ?? '');
            $staff_phone        = trim($row[2] ?? '');
            // ... map all other fields you want to support

            // Minimal validation
            if (empty($staff_name) || empty($staff_p_no) || empty($staff_phone)) {
                $errors[] = "Row " . ($index+2) . ": missing required fields";
                continue;
            }

            // You can add more validation (phone format, date format, etc.)

            // Try to lookup IDs the same way your form does
            // (facility_id from facilityname, course_id from course_name, etc.)

            // Prepare and execute INSERT (same as your submit_training.php)
            // Use prepared statement!

            $sql = "INSERT INTO staff_trainings (...) VALUES (?,?,?,?,... )";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param( ... );   // match your original types
            if ($stmt->execute()) {
                $inserted++;
            } else {
                $errors[] = "Row " . ($index+2) . ": " . $stmt->error;
            }
        }

        // Show result
        if ($inserted > 0) {
            $_SESSION['success'] = "$inserted training records imported successfully!";
        }
        if (!empty($errors)) {
            $_SESSION['error'] = "Some rows failed:<br>" . implode("<br>", $errors);
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "File error: " . $e->getMessage();
    }

    header("Location: bulk_import_training.php");
    exit();
}
?>



<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document</title>
</head>
<body>
        <form method="post" enctype="multipart/form-data">
            <label>Upload Excel (.xlsx) or CSV:</label>
            <input type="file" name="import_file" accept=".csv,.xlsx" required>
            <button type="submit" name="import">Import Trainings</button>
        </form>
</body>
</html>