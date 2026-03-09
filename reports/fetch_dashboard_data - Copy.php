<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Get filter parameters
$county = isset($_POST['county']) ? $conn->real_escape_string(trim($_POST['county'])) : '';
$subcounty = isset($_POST['subcounty']) ? $conn->real_escape_string(trim($_POST['subcounty'])) : '';
$facility = isset($_POST['facility']) ? intval($_POST['facility']) : 0;
$course = isset($_POST['course']) ? intval($_POST['course']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
$location = isset($_POST['location']) ? intval($_POST['location']) : 0;
$cadre = isset($_POST['cadre']) ? intval($_POST['cadre']) : 0;
$year = isset($_POST['year']) ? intval($_POST['year']) : 0;
$month = isset($_POST['month']) ? $conn->real_escape_string($_POST['month']) : '';

// Build WHERE clause
$whereClauses = [];
$params = [];
$types = '';

if (!empty($county)) {
    $whereClauses[] = "county = ?";
    $params[] = $county;
    $types .= 's';
}

if (!empty($subcounty)) {
    $whereClauses[] = "subcounty = ?";
    $params[] = $subcounty;
    $types .= 's';
}

if ($facility > 0) {
    $whereClauses[] = "facility_id = ?";
    $params[] = $facility;
    $types .= 'i';
}

if ($course > 0) {
    $whereClauses[] = "course_id = ?";
    $params[] = $course;
    $types .= 'i';
}

if ($duration > 0) {
    $whereClauses[] = "duration_id = ?";
    $params[] = $duration;
    $types .= 'i';
}

if ($location > 0) {
    $whereClauses[] = "location_id = ?";
    $params[] = $location;
    $types .= 'i';
}

if ($cadre > 0) {
    $whereClauses[] = "cadre_id = ?";
    $params[] = $cadre;
    $types .= 'i';
}

if ($year > 0) {
    $whereClauses[] = "YEAR(training_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

if (!empty($month)) {
    $whereClauses[] = "MONTH(training_date) = ?";
    $params[] = $month;
    $types .= 's';
}

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

try {
    // Get total statistics
    $stats = [];

    // Total trainings
    $sql = "SELECT COUNT(*) as total FROM staff_trainings $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalTrainings'] = $row['total'];
    $stmt->close();

    // Total unique staff
    $sql = "SELECT COUNT(DISTINCT staff_name) as total FROM staff_trainings $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalStaff'] = $row['total'];
    $stmt->close();

    // Total unique facilities
    $sql = "SELECT COUNT(DISTINCT facility_id) as total FROM staff_trainings $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalFacilities'] = $row['total'];
    $stmt->close();

    // Total unique courses
    $sql = "SELECT COUNT(DISTINCT course_id) as total FROM staff_trainings $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalCourses'] = $row['total'];
    $stmt->close();

    // Get chart data
    $chartData = [];

    // Courses distribution
    $sql = "SELECT course_name, COUNT(*) as count FROM staff_trainings $whereSQL GROUP BY course_id, course_name ORDER BY count DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $chartData['courses'] = [];
    while ($row = $result->fetch_assoc()) {
        $chartData['courses'][] = $row;
    }
    $stmt->close();

    // Monthly data
    $monthlyData = array_fill(0, 12, 0);
    $sql = "SELECT MONTH(training_date) as month, COUNT(*) as count FROM staff_trainings $whereSQL GROUP BY MONTH(training_date)";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['month'] >= 1 && $row['month'] <= 12) {
            $monthlyData[$row['month'] - 1] = $row['count'];
        }
    }
    $chartData['monthlyData'] = $monthlyData;
    $stmt->close();

    // Cadre distribution
    $sql = "SELECT staff_cadre as cadre_name, COUNT(*) as count FROM staff_trainings WHERE cadrename IS NOT NULL AND staff_cadre != '' $whereSQL GROUP BY staff_cadre ORDER BY count DESC LIMIT 8";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        // Need to reconstruct WHERE for this query
        $whereSQL2 = str_replace("WHERE ", "AND ", $whereSQL);
        $sql = "SELECT staff_cadre as cadre_name, COUNT(*) as count FROM staff_trainings WHERE staff_cadre IS NOT NULL AND staff_cadre != '' $whereSQL2 GROUP BY staff_cadre ORDER BY count DESC LIMIT 8";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $chartData['cadres'] = [];
    while ($row = $result->fetch_assoc()) {
        $chartData['cadres'][] = $row;
    }
    $stmt->close();

    // Get training records
    $sql = "SELECT * FROM staff_trainings $whereSQL ORDER BY training_date DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $trainings = [];
    while ($row = $result->fetch_assoc()) {
        $trainings[] = $row;
    }
    $stmt->close();

    // Return JSON response
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'chartData' => $chartData,
        'trainings' => $trainings
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>