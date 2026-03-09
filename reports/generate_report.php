<?php
require_once '../includes/config.php';

// Add TCPDF library for PDF generation (you need to install it)
// For now, let's create a simple HTML report

if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="training_report_' . date('Y-m-d') . '.pdf"');

    // Get filter parameters
    $filters = $_GET;

    // Build query
    $whereClauses = [];
    if (!empty($filters['county'])) {
        $whereClauses[] = "county = '" . $conn->real_escape_string($filters['county']) . "'";
    }
    // Add more filters as needed...

    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Get data
    $sql = "SELECT * FROM staff_trainings $whereSQL ORDER BY training_date DESC";
    $result = $conn->query($sql);

    // Generate HTML for PDF (simplified version)
    $html = '<h1>Training Report</h1>';
    $html .= '<table border="1" cellpadding="5">';
    $html .= '<tr><th>#</th><th>Facility</th><th>Staff Name</th><th>Course</th><th>Date</th><th>Location</th></tr>';

    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>' . $count++ . '</td>';
        $html .= '<td>' . htmlspecialchars($row['facility_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['staff_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['course_name']) . '</td>';
        $html .= '<td>' . $row['training_date'] . '</td>';
        $html .= '<td>' . htmlspecialchars($row['location_name']) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';

    // In a real implementation, you would use TCPDF or similar to generate PDF
    // For now, just output HTML
    echo $html;

    exit();
}

$conn->close();
?>