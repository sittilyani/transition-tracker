<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

if (!isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit();
}

$training_id = $_GET['id'] ?? 0;

if (!$training_id) {
    die("Invalid training ID");
}

// FETCH TRAINING DETAILS
$stmt = $conn->prepare("
    SELECT pt.*, c.course_name, cnt.county_name
    FROM planned_trainings pt
    LEFT JOIN courses c ON pt.course_id = c.course_id
    LEFT JOIN counties cnt ON pt.county_id = cnt.county_id
    WHERE pt.id = ?
");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$training = $stmt->get_result()->fetch_assoc();

if (!$training) {
    die("Training not found");
}

// FETCH PARTICIPANTS
$stmt = $conn->prepare("
    SELECT * FROM participants
    WHERE training_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$participants = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Participants</title>
<style>
body { font-family: Arial; background:#f5f7fb; padding:20px; }
.card { background:#fff; padding:20px; border-radius:10px; margin-bottom:20px; }
table { width:100%; border-collapse: collapse; }
th, td { padding:10px; border:1px solid #ddd; }
th { background:#0D1A63; color:#fff; }
.badge { background:#e0f2fe; padding:5px 10px; border-radius:6px; }
</style>
</head>
<body>

<div class="card">
    <h2><?php echo htmlspecialchars($training['course_name']); ?></h2>
    <p><b>County:</b> <?php echo htmlspecialchars($training['county_name']); ?></p>
    <p><b>Dates:</b> <?php echo $training['start_date']; ?> ? <?php echo $training['end_date']; ?></p>
</div>

<div class="card">
    <h3>Participants (<?php echo $participants->num_rows; ?>)</h3>

    <table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Date Registered</th>
        </tr>

        <?php $i=1; while($row = $participants->fetch_assoc()): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['phone']); ?></td>
            <td><?php echo $row['created_at'] ?? '-'; ?></td>
        </tr>
        <?php endwhile; ?>

    </table>
</div>

</body>
</html>