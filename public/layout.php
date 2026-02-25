<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/config.php';

// Set timezone to Africa/Nairobi
date_default_timezone_set('Africa/Nairobi');

// Check if user is logged in
if (!isset($_SESSION['full_name'])) {
    header("Location: ../login/login.php");
    exit();
}

// Get user role and info
$userName = $_SESSION['full_name'];
$userRole = $_SESSION['role'] ?? 'User';
$userId = $_SESSION['user_id'] ?? 0;

// Define which roles can access dashboard
$canAccessDashboard = in_array($userRole, ['Admin', 'Supervisor', 'Manager']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butchery Management System</title>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/ico" sizes="32x32" href="../assets/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f4f7fc;overflow:hidden}
        .app-wrapper{display:flex;height:100vh;width:100%;overflow:hidden}

        /* Sidebar Styles */
        .sidebar{width:280px;background:#CC0000;color:#fff;height:100vh;position:fixed;left:0;top:0;overflow-y:auto;transition:transform 0.3s ease;z-index:1000;box-shadow:4px 0 10px rgba(0,0,0,.1)}
        .sidebar::-webkit-scrollbar{width:6px}
        .sidebar::-webkit-scrollbar-track{background:#334155}
        .sidebar::-webkit-scrollbar-thumb{background:#475569;border-radius:3px}

        /* Sidebar states */
        .sidebar.show{transform:translateX(0)}

        /* Main content adjustment */
        .main-content{flex:1;margin-left:280px;height:100vh;display:flex;flex-direction:column;background:#f4f7fc;transition:margin-left 0.3s ease}

        .sidebar-header{padding:25px 20px;border-bottom:1px solid #334155;margin-bottom:20px}
        .logo-area{display:flex;align-items:center;gap:12px}
        .logo-icon{width:45px;height:45px;background:#4361ee;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff}
        .logo-text h3{font-size:18px;font-weight:600;margin:0;color:#fff}
        .logo-text p{font-size:12px;color:#94a3b8;margin:3px 0 0}
        .user-info{padding:15px 20px;background:#334155;margin:0 15px 20px;border-radius:12px;display:flex;align-items:center;gap:12px}
        .user-avatar{width:45px;height:45px;background:#4361ee;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff}
        .user-details h4{font-size:14px;font-weight:600;margin:0;color:#fff}
        .user-details span{font-size:11px;color:#94a3b8}
        .nav-menu{padding:0 15px}
        .nav-section{margin-bottom:25px}
        .nav-section-title{font-size:18px; font-weight: 600;text-transform:uppercase;letter-spacing:.5px;color: #80FF80;padding:0 10px;margin-bottom:10px}
        .nav-item{list-style:none;margin-bottom:5px}
        .nav-link{display:flex;align-items:center;gap:12px;padding:12px 15px;color:#cbd5e1;text-decoration:none;border-radius:10px;transition:.3s;font-size:14px;cursor:pointer}
        .nav-link:hover{background:#334155;color:#fff}
        .nav-link.active{background:#4361ee;color:#fff}

        /* Top Navbar */
        .top-navbar{height:70px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 25px;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.03)}
        .menu-toggle{display:none;background:none;border:none;font-size:24px;color:#475569;cursor:pointer;transition:color 0.3s;padding:8px;border-radius:8px}
        .menu-toggle:hover{background:#f1f5f9;color:#CC0000}
        .menu-toggle i{font-size:24px}
        .page-title{font-size:20px;font-weight:700;color:#CC0000;margin:0}
        .top-nav-actions{display:flex;align-items:center;gap:20px}

        /* DateTime Display Styles */
        .datetime-display{display:flex;align-items:center;gap:10px;background:#f1f5f9;padding:8px 15px;border-radius:10px;color:#1e293b}
        .datetime-display i,.datetime-display .time{color:#c00}
        .datetime-display .time{font-weight:600;font-size:1.1em;font-family:monospace}
        .datetime-display i{font-size:1.1em}
        .datetime-display .date{font-size:.9em;color:#64748b}

        .notification-btn{background:#f1f5f9;border:none;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#475569;position:relative;cursor:pointer}
        .notification-badge{position:absolute;top:-5px;right:-5px;background:#f72585;color:#fff;font-size:10px;padding:3px 6px;border-radius:30px;min-width:18px;text-align:center}
        .user-dropdown{display:flex;align-items:center;gap:10px;background:#f1f5f9;padding:8px 15px;border-radius:10px;cursor:pointer}
        .user-dropdown .avatar{width:35px;height:35px;background:#4361ee;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600}

        /* Iframe Container */
        .iframe-container{flex:1;overflow-y:auto;padding:20px 25px;background:#f4f7fc}
        .iframe-container iframe{width:100%;height:100%;border:none;background:#fff;border-radius:15px;box-shadow:0 4px 12px rgba(0,0,0,.03)}

        /* Dropdown Menu */
        .dropdown-menu-custom{position:absolute;right:0;top:100%;margin-top:10px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.1);min-width:200px;display:none;z-index:1000}
        .dropdown-menu-custom.show{display:block}
        .dropdown-item{padding:12px 20px;display:flex;align-items:center;gap:10px;color:#1e293b;text-decoration:none;transition:background 0.3s}
        .dropdown-item:hover{background:#f1f5f9}
        .dropdown-divider{height:1px;background:#e2e8f0;margin:5px 0}

        /* Loading Spinner */
        .iframe-loading{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;display:none}
        .spinner{width:40px;height:40px;border:3px solid #e2e8f0;border-top-color:#4361ee;border-radius:50%;animation:spin 1s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Responsive Design */
        @media(max-width:992px){
            .sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }
            .sidebar.show {
                transform: translateX(0);
                box-shadow: 4px 0 15px rgba(0,0,0,0.2);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }

        @media(max-width:768px){
            .top-navbar{padding:0 15px}
            .page-title{font-size:18px}
            .user-dropdown .info{display:none}
            .iframe-container{padding:15px}
            .datetime-display .date {display: none;}
            .datetime-display {padding: 6px 12px;}
        }

        @media(max-width:576px){
            .top-nav-actions{gap:10px}
            .notification-btn{width:35px;height:35px}
            .user-dropdown{padding:5px 10px}
            .datetime-display {padding: 5px 10px;}
            .datetime-display .time {font-size: 0.9em;}
            .iframe-container {padding: 10px;}
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Overlay (for mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon"><i class="bi bi-shop"></i></div>
                    <div class="logo-text">
                        <h3>Butchery Pro</h3>
                        <p>Katakala Butchery & Restaurant</p>
                    </div>
                </div>
            </div>

            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($userName); ?></h4>
                    <span><?php echo htmlspecialchars($userRole); ?></span>
                </div>
            </div>

            <div class="nav-menu">
                <!-- Main Navigation -->
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <?php if ($canAccessDashboard): ?>
                    <div class="nav-item">
                        <a href="../dashboard/admin_dashboard.php" target="contentFrame" class="nav-link" style="background: #FFFF00; color: #000000;" onclick="handleNavClick()">
                            <i class="fa fa-chart-pie"></i> Dashboard
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="nav-item">
                        <a href="../sales/orders.php" target="contentFrame" class="nav-link" style="background: #80FF80; color: #000000;" onclick="handleNavClick()">
                            <i class="fas fa-shopping-basket"></i> POS
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../sales/direct_orders.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <i class="fa fa-shopping-cart"></i> Quick Sales
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../backup/view_backups.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <i class="fa fa-database"></i> Backup
                        </a>
                    </div>
                </div>

                <!-- Inventory Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Inventory</div>
                    <div class="nav-item">
                        <a href="../stocks/viewstocks_sum.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <i class="fa fa-boxes"></i> Stock Management
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../stocks/view_categories.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/cow.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Categories
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../views/view_product.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/boxes.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Products
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../views/view_suppliers.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/truck-loading.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Suppliers
                        </a>
                    </div>
                </div>

                <!-- Sales Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Sales</div>
                    <div class="nav-item">
                        <a href="../sales/view_order.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/cash-register.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Cashier
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../views/view_credit_sales.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/credit-card.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Credit Sales
                        </a>
                    </div>
                </div>

                <!-- Reports Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Reports</div>
                    <div class="nav-item">
                        <a href="../reports/financial_report.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/file-invoice-dollar.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Financial Report
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../reports/staff_performance.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/chart-bar.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Staff Performance
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../receipts/view_receipts.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/print.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Receipts
                        </a>
                    </div>
                </div>

                <!-- System Section -->
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <div class="nav-item">
                        <a href="../login/userslist.php" target="contentFrame" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/cog.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            User Settings
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../index.php" class="nav-link" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/sign-out.svg" alt="" width="16" height="16" style="filter:invert(1)">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="menu-toggle" onclick="toggleSidebar()" id="menuToggleBtn">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="page-title" id="pageTitle">Quick Sales</h1>
                </div>

                <div class="top-nav-actions">
                    <!-- DateTime Display - Africa/Nairobi -->
                    <div class="datetime-display" id="datetimeDisplay">
                        <i class="bi bi-clock"></i>
                        <span class="time" id="timeDisplay"><?php echo date('H:i:s'); ?></span>
                        <span class="date" id="dateDisplay"><?php echo date('D, M d, Y'); ?></span>
                    </div>

                    <button class="notification-btn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>

                    <div class="user-dropdown" onclick="toggleUserMenu()">
                        <div class="avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="role"><?php echo htmlspecialchars($userRole); ?></div>
                        </div>
                        <i class="bi bi-chevron-down"></i>
                    </div>

                    <!-- User Dropdown Menu -->
                    <div class="dropdown-menu-custom" id="userMenu">
                        <a href="../login/profile.php" target="contentFrame" class="dropdown-item" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/person-chalkboard.svg" alt="" width="16" height="16">
                            Profile
                        </a>
                        <a href="../login/reset_password.php" target="contentFrame" class="dropdown-item" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/shield.svg" alt="" width="16" height="16">
                            Change password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../index.php" class="dropdown-item" onclick="handleNavClick()">
                            <img src="../assets/fontawesome/svgs-full/solid/sign-out.svg" alt="" width="16" height="16">
                            Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Iframe Content Area -->
            <div class="iframe-container" id="iframeContainer">
                <div class="iframe-loading" id="iframeLoading">
                    <div class="spinner"></div>
                    <p style="margin-top: 10px; color: #64748b;">Loading...</p>
                </div>
                <iframe name="contentFrame" src="../sales/orders.php" id="contentFrame" frameborder="0" onload="hideLoading()"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');

            // Change menu icon based on sidebar state
            const menuIcon = document.querySelector('#menuToggleBtn i');
            if (sidebar.classList.contains('show')) {
                menuIcon.classList.remove('bi-list');
                menuIcon.classList.add('bi-x-lg');
            } else {
                menuIcon.classList.remove('bi-x-lg');
                menuIcon.classList.add('bi-list');
            }
        }

        // Close sidebar function
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');

            // Reset menu icon
            const menuIcon = document.querySelector('#menuToggleBtn i');
            menuIcon.classList.remove('bi-x-lg');
            menuIcon.classList.add('bi-list');
        }

        // Handle navigation click - closes sidebar on mobile after clicking a link
        function handleNavClick() {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        }

        // Toggle user menu
        function toggleUserMenu() {
            document.getElementById('userMenu').classList.toggle('show');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            if (!userDropdown.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.remove('show');
            }
        });

        // Hide loading spinner
        function hideLoading() {
            document.getElementById('iframeLoading').style.display = 'none';
        }

        // Show loading when iframe starts loading
        document.getElementById('contentFrame').addEventListener('load', function() {
            hideLoading();
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeSidebar();
            }
        });

        // Update page title from iframe
        document.getElementById('contentFrame').addEventListener('load', function() {
            try {
                const iframeTitle = this.contentDocument.title;
                if (iframeTitle) {
                    document.getElementById('pageTitle').textContent = iframeTitle;
                }
            } catch(e) {
                // Cross-origin restrictions may prevent accessing title
            }
        });

        // Africa/Nairobi DateTime Display
        function updateDateTime() {
            const now = new Date();

            // Format time (HH:MM:SS)
            const timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
                timeZone: 'Africa/Nairobi'
            });

            // Format date (Day, Month DD, YYYY)
            const dateStr = now.toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                timeZone: 'Africa/Nairobi'
            });

            const timeElement = document.getElementById('timeDisplay');
            const dateElement = document.getElementById('dateDisplay');

            if (timeElement) timeElement.textContent = timeStr;
            if (dateElement) dateElement.textContent = dateStr;
        }

        // Initialize datetime
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Set initial page title
            const currentPage = window.location.href.split('/').pop();
            if (currentPage.includes('orders')) {
                document.getElementById('pageTitle').textContent = 'POS';
            }
        });

        // Add click event to all nav links for mobile
        document.querySelectorAll('.nav-link[target="contentFrame"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 992) {
                    // Small delay to ensure the iframe loads before closing sidebar
                    setTimeout(closeSidebar, 100);
                }
            });
        });

        // Session messaging for iframe
        window.addEventListener('message', function(event) {
            if (event.data === "getSession") {
                const iframe = document.getElementById('contentFrame');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage({
                        type: 'sessionData',
                        userId: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
                        fullName: '<?php echo $_SESSION['full_name'] ?? ''; ?>',
                        role: '<?php echo $_SESSION['role'] ?? ''; ?>'
                    }, '*');
                }
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>