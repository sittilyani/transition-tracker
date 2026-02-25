<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['full_name'];

// Handle delete draft
if (isset($_GET['delete'])) {
    $draft_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM training_drafts WHERE draft_id = $draft_id AND user_id = '$user'");
    header("Location: training_drafts.php");
    exit();
}

// Fetch user's drafts
$drafts = mysqli_query($conn,
    "SELECT * FROM training_drafts
     WHERE user_id = '$user'
     ORDER BY last_updated DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drafts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Similar styling to training_list.php */
        body {
            background: #f4f7fc;
            padding: 20px;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            width: 90%;
            margin: 0 auto;
        }
        .header {
            background: #0D1A63;
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .draft-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .draft-info h3 {
            margin-bottom: 5px;
        }
        .draft-meta {
            color: #666;
            font-size: 13px;
        }
        .draft-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-save"></i> My Saved Drafts</h1>
            <a href="training_registration.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Registration
            </a>
        </div>

        <?php if (mysqli_num_rows($drafts) > 0): ?>
            <?php while ($draft = mysqli_fetch_assoc($drafts)):
                $data = json_decode($draft['draft_data'], true);
                $course_id = $data['course_id'] ?? 'Not selected';
            ?>
            <div class="draft-card">
                <div class="draft-info">
                    <h3><i class="fas fa-file"></i> Draft #<?php echo $draft['draft_id']; ?></h3>
                    <div class="draft-meta">
                        <i class="fas fa-clock"></i> Last updated: <?php echo date('F j, Y g:i a', strtotime($draft['last_updated'])); ?>
                    </div>
                    <div class="draft-meta">
                        <i class="fas fa-users"></i> Staff selected: <?php echo isset($data['selected_staff']) ? count($data['selected_staff']) : 0; ?>
                    </div>
                </div>
                <div class="draft-actions">
                    <a href="training_registration.php?draft=<?php echo $draft['draft_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Continue
                    </a>
                    <a href="?delete=<?php echo $draft['draft_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this draft?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: white; border-radius: 10px;">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                <h3>No Drafts Found</h3>
                <p>You don't have any saved drafts.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>