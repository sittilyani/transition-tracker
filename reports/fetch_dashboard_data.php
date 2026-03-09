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
    $whereClauses[] = "YEAR(created_at) = ?";
    $params[] = $year;
    $types .= 'i';
}

if (!empty($month)) {
    $whereClauses[] = "MONTH(created_at) = ?";
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

    // Total trainings (distinct course_id + created_at date combinations)
    $sql = "SELECT COUNT(DISTINCT CONCAT(course_id, '_', DATE(created_at))) as total FROM staff_trainings $whereSQL";
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

    // Total participants (sum of all participants)
    $sql = "SELECT COUNT(*) as total FROM staff_trainings $whereSQL";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalParticipants'] = $row['total'];
    $stmt->close();

    // Get chart data
    $chartData = [];

    // Courses distribution (by number of participants)
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

    // Monthly data (by created_at date)
    $monthlyData = array_fill(0, 12, 0);
    $sql = "SELECT MONTH(created_at) as month, COUNT(DISTINCT CONCAT(course_id, '_', DATE(created_at))) as count 
            FROM staff_trainings $whereSQL 
            GROUP BY MONTH(created_at)";
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
    $sql = "SELECT cadrename as cadre_name, COUNT(*) as count 
            FROM staff_trainings 
            WHERE cadrename IS NOT NULL AND cadrename != '' $whereSQL 
            GROUP BY cadrename 
            ORDER BY count DESC 
            LIMIT 8";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        // Need to reconstruct WHERE for this query
        $whereSQL2 = str_replace("WHERE ", "AND ", $whereSQL);
        $sql = "SELECT cadrename as cadre_name, COUNT(*) as count 
                FROM staff_trainings 
                WHERE cadrename IS NOT NULL AND cadrename != '' $whereSQL2 
                GROUP BY cadrename 
                ORDER BY count DESC 
                LIMIT 8";
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

    // Get distinct training sessions for display
    $sql = "SELECT 
                st.*,
                DATE(st.created_at) as training_date_only
            FROM staff_trainings st 
            $whereSQL 
            ORDER BY st.created_at DESC 
            LIMIT 500";
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

    // Get unique training sessions count for additional info
    $unique_sessions_query = "SELECT COUNT(DISTINCT CONCAT(course_id, '_', DATE(created_at))) as unique_sessions FROM staff_trainings $whereSQL";
    $unique_result = $conn->query($unique_sessions_query);
    $unique_row = $unique_result->fetch_assoc();
    $stats['uniqueTrainingSessions'] = $unique_row['unique_sessions'];

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