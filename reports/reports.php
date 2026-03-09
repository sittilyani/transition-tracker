<?php
session_start();
include '../includes/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Reports</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/fontawesome/css/font-awesome.min.css" type="text/css">
</head>
<body class="bg-light">
<?php include '../includes/header.php'; ?>

<div class="container mt-5">

<!--Export to Excel-->

    <div class="text-end mb-3">
        <a href="export_excel.php" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-chart-bar"></i> Stock Reports</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Near Expiry (Next 3 Months)</h6>
                    <table class="table table-sm">
                        <?php
                        $sql = "SELECT drug_name, batch_no, expiry_date, quantity FROM stock_entries
                                WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                                AND expiry_date >= CURDATE()";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['drug_name']) ?></td>
                            <td><?= $row['batch_no'] ?></td>
                            <td><span class="badge bg-warning"><?= $row['expiry_date'] ?></span></td>
                            <td><?= $row['quantity'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Low Stock</h6>
                    <table class="table table-sm">
                        <?php
                        $sql = "SELECT drug_name, SUM(quantity) as total FROM stock_entries GROUP BY drug_name HAVING total < 50";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['drug_name']) ?></td>
                            <td><span class="badge bg-danger"><?= $row['total'] ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>