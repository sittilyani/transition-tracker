<?php
// trainings/export_training_needs.php
session_start();

include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$cadre_filter = $_GET['cadre'] ?? '';
$facility_filter = $_GET['facility'] ?? '';

// Build filter conditions
$params = [];
$types = "";
$where = "WHERE t.submission_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;
$types .= "ss";

if (!empty($cadre_filter)) {
    $where .= " AND t.cadre = ?";
    $params[] = $cadre_filter;
    $types .= "s";
}

if (!empty($facility_filter)) {
    $where .= " AND t.facility_name = ?";
    $params[] = $facility_filter;
    $types .= "s";
}

// Get latest assessments per staff
$query = "
    SELECT
        t.tna_id,
        t.id_number,
        t.name,
        t.cadre,
        t.department,
        t.facility_name,
        t.submission_date,
        t.possess_necessary_skills,
        t.possess_technical_skills,
        t.competences_trained,
        pt.area_of_training,
        pt.institution,
        pt.duration,
        pt.preferred_year
    FROM tna_assessments t
    INNER JOIN (
        SELECT id_number, MAX(created_at) as max_date
        FROM tna_assessments
        GROUP BY id_number
    ) latest ON latest.id_number = t.id_number AND latest.max_date = t.created_at
    LEFT JOIN tna_proposed_trainings pt ON pt.tna_id = t.tna_id
    $where
    ORDER BY t.facility_name, t.cadre, t.name
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="training_needs_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel format
echo '<html>';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';
echo '<table border="1">';
echo '<tr>';
echo '<th>ID Number</th>';
echo '<th>Staff Name</th>';
echo '<th>Cadre</th>';
echo '<th>Department</th>';
echo '<th>Facility</th>';
echo '<th>Submission Date</th>';
echo '<th>Proposed Training Area</th>';
echo '<th>Preferred Institution</th>';
echo '<th>Duration</th>';
echo '<th>Preferred Year</th>';
echo '<th>Skills Gap?</th>';
echo '<th>Competences Already Trained</th>';
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['id_number'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['cadre'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['department'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['facility_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['submission_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['area_of_training'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['institution'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['duration'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['preferred_year'] ?? '') . '</td>';
    echo '<td>' . ($row['possess_necessary_skills'] == 'No' ? 'Yes' : 'No') . '</td>';
    echo '<td>' . htmlspecialchars($row['competences_trained'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';
exit();
?>