<?php
ob_start();
session_start();

include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user = [];

// Check if a user ID is provided in the URL
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Prepare and execute the SQL query to fetch user data
    $sql = "SELECT * FROM tblusers WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if a user was found
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "User not found!";
        header("Location: ?page=users");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "No user ID specified!";
    header("Location: ?page=users");
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User Details</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
    <style>
        :root {
            --primary-color: #4B0082;
            --primary-light: #6a11cb;
            --primary-dark: #3a0066;
            --secondary-color: #667eea;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
        }

        .user-view-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            padding: 0;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .user-header {
            background: #0D1A63;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .user-header h1 {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 2.2rem;
        }

        .user-header .user-id {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        .user-content {
            padding: 30px;
        }

        .info-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            color: #495057;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }

        .role-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .role-county {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .role-facility {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .role-default {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .user-photo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .user-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-color);
            box-shadow: 0 5px 20px rgba(75, 0, 130, 0.3);
        }

        .photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border: 5px solid var(--primary-color);
            margin: 0 auto;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(75, 0, 130, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #e67e22);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .empty-value {
            color: #6c757d;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .user-view-container {
                margin: 10px;
            }

            .user-content {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Status indicators */
        .status-active {
            color: var(--success-color);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--danger-color);
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="user-view-container">
    <!-- User Header -->
    <div class="user-header">
        <h1><i class="fas fa-user-circle"></i> User Details</h1>
        <div class="user-id">User ID: <?php echo htmlspecialchars($user['user_id']); ?></div>
    </div>

    <div class="user-content">
        <?php if (!empty($user)): ?>
            <!-- User Photo -->
            <div class="user-photo-container">
                <?php if (!empty($user['photo']) && file_exists("../photos/users/{$user['photo']}")): ?>
                    <img src="../photos/users/<?php echo htmlspecialchars($user['photo']); ?>"
                         alt="User Photo" class="user-photo">
                <?php else: ?>
                    <div class="photo-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Personal Information -->
            <div class="info-section">
                <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">First Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['first_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['full_name'] ?: 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email'] ?: '<span class="empty-value">Not provided</span>'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['sex'] ?: '<span class="empty-value">Not specified</span>'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Mobile</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['mobile'] ?: '<span class="empty-value">Not provided</span>'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Professional Information -->
            <div class="info-section">
                <h3 class="section-title"><i class="fas fa-briefcase"></i> Access Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">User Role</span>
                        <span class="info-value">
                            <?php
                            $role_class = 'role-default';
                            if (stripos($user['userrole'], 'admin') !== false) $role_class = 'role-admin';
                            elseif (stripos($user['userrole'], 'county') !== false) $role_class = 'role-county';
                            ?>
                            <span class="role-badge <?php echo $role_class; ?>">
                                <?php echo htmlspecialchars($user['userrole']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date Created</span>
                        <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['date_created'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Login</span>
                        <span class="info-value">
                            <?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : '<span class="empty-value">Never logged in</span>'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Status</span>
                        <span class="info-value">
                            <span class="<?php echo ($user['status'] == 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ($user['status'] == 'Active') ? 'Active' : 'Inactive'; ?>
                            </span>
                        </span>
                    </div>

                </div>
            </div>



            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="?page=users" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
                <a href="../public/update_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit User
                </a>
                <button class="btn btn-warning" onclick="if(confirm('Are you sure you want to reset the password for this user to default (123456)?')) location.href='../public/reset_user_password.php?id=<?php echo $user['user_id']; ?>'">
                    <i class="fas fa-sync-alt"></i> Reset Password
                </button>
            </div>

        <?php else: ?>
            <div class="alert alert-danger text-center" role="alert" style="margin: 20px;">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h4>User not found</h4>
                <p>The requested user could not be found in the system.</p>
                <a href="../public/userslist.php" class="btn btn-primary mt-2">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>