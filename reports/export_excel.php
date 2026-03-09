<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) exit();

require '../vendor/autoload.php'; // Use PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers
$sheet->setCellValue('A1', 'Drug Name');
$sheet->setCellValue('B1', 'Batch No');
$sheet->setCellValue('C1', 'Expiry Date');
$sheet->setCellValue('D1', 'Quantity');
$sheet->setCellValue('E1', 'Facility');
$sheet->setCellValue('F1', 'Entry Month');

// Data
$row = 2;
$result = $conn->query("SELECT drug_name, batch_no, expiry_date, quantity, facilityname, entry_month FROM stock_entries ORDER BY entry_month DESC");
while ($data = $result->fetch_assoc()) {
    $sheet->setCellValue("A$row", $data['drug_name']);
    $sheet->setCellValue("B$row", $data['batch_no']);
    $sheet->setCellValue("C$row", $data['expiry_date']);
    $sheet->setCellValue("D$row", $data['quantity']);
    $sheet->setCellValue("E$row", $data['facilityname']);
    $sheet->setCellValue("F$row", $data['entry_month']);
    $row++;
}

// Style headers
$sheet->getStyle('A1:F1')->getFont()->setBold(true);
$sheet->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF4B0082');
$sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');

// Auto-size columns
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Download
$writer = new Xlsx($spreadsheet);
$filename = "Stock_Report_" . date('Ymd') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit();