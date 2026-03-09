<?php
include '../includes/config.php';
// Check if user is logged in - but don't redirect if not, as this is a public welcome page
// The layout.php will handle authentication
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SessionTracker | Professional Development & Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #011f88;
            --accent-color: #00d2ff;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
            padding: 80px 0;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 40px;
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(1, 31, 136, 0.1);
        }
        .icon-box {
            width: 70px;
            height: 70px;
            line-height: 70px;
            background: rgba(1, 31, 136, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            font-size: 28px;
            margin-bottom: 20px;
            display: inline-block;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #003d9e;
        }
        .btn-outline-light {
            padding: 12px 30px;
            border-radius: 8px;
        }
        .stat-box {
            padding: 30px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .color-primary {
            color: var(--primary-color);
        }
        .welcome-message {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="hero-section text-center">
        <div class="container">
            <h1 class="display-3 fw-bold mb-3">SessionTracker</h1>
            <p class="lead mb-5">Seamlessly track meetings, professional trainings, and mentorship sessions in one secure place.</p>
            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                <a href="../trainings/view_training.php" target="_parent" class="btn btn-primary btn-lg px-4 gap-3">
                    <i class="fas fa-chalkboard-teacher me-2"></i>View Trainings
                </a>
                <a href="../meetings/view_meetings.php" target="_parent" class="btn btn-outline-primary btn-lg px-4">
                    <i class="fas fa-users me-2"></i>View Meetings
                </a>
            </div>
        </div>
    </div>

    <!-- Welcome Message -->
    <div class="container">
        <div class="welcome-message text-center">
            <h2 class="fw-bold color-primary mb-3">Welcome to SessionTracker</h2>
            <p class="lead">Your centralized platform for tracking professional development activities.</p>
            <?php if(isset($_SESSION['full_name'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section id="features" class="py-5">
        <div class="container py-3">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Everything you need to stay organized</h2>
                <p class="text-muted">Designed for Healthcare. Optimized for Excellence.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card p-4 feature-card text-center">
                        <div class="icon-box"><i class="fas fa-users"></i></div>
                        <h4>Meetings</h4>
                        <p class="text-muted">Log minutes, attendance, and action items for facility-wide or departmental meetings.</p>
                        <a href="../meetings/view_meetings.php" target="_parent" class="btn btn-outline-primary mt-3">View Meetings</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 feature-card text-center">
                        <div class="icon-box"><i class="fas fa-graduation-cap"></i></div>
                        <h4>Trainings</h4>
                        <p class="text-muted">Track CPD points, certifications, and staff skill development across your organization.</p>
                        <a href="../trainings/view_training.php" target="_parent" class="btn btn-outline-primary mt-3">View Trainings</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 feature-card text-center">
                        <div class="icon-box"><i class="fas fa-hands-helping"></i></div>
                        <h4>Mentorship</h4>
                        <p class="text-muted">Document one-on-one sessions and clinical mentorship progress for healthcare providers.</p>
                        <a href="../meetings/mentorship.php" target="_parent" class="btn btn-outline-primary mt-3">View Mentorship</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="stat-box text-center">
                        <h2 class="fw-bold color-primary">100%</h2>
                        <p class="text-muted mb-0">Paperless Tracking</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box text-center">
                        <h2 class="fw-bold color-primary">Real-time</h2>
                        <p class="text-muted mb-0">Reporting & Analytics</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box text-center">
                        <h2 class="fw-bold color-primary">Secure</h2>
                        <p class="text-muted mb-0">Data Encryption</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Quick Actions</h2>
            <div class="row g-3">
                <div class="col-sm-4">
                    <a href="../trainings/add_training.php" target="_parent" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-plus-circle me-2"></i>Add Training
                    </a>
                </div>
                <div class="col-sm-4">
                    <a href="../meetings/add_meeting.php" target="_parent" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-plus-circle me-2"></i>Schedule Meeting
                    </a>
                </div>
                <div class="col-sm-4">
                    <a href="../reports/financial_report.php" target="_parent" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-file-alt me-2"></i>Generate Report
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>