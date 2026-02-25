<?php
// Use centralized session manager from includes directory
include '../includes/session_manager.php';

// Check if user is logged in
requireLogin();

// Include the header (which handles session and config)
include '../includes/header.php';


// Define role-based permissions for cards and sidebars
$rolePermissions = [
    'super admin' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'admin' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'admin assistant' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'chmt' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'schmt' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'county director' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'facility staff' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'finance' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'guest' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'human resource' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'program staff' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'other partner' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
    'in charge' => [
        'cards' => [
            'Systems Admin' => '../admin/page.php',
            'BackUp and Refresh' => '../backup/page.php',
            'Staff Management' => '../staffs/page.php',
            'Meetings Management' => '../meetings/page.php',
            'Trainings Management' => '../trainings/page.php',
            'Biometrics' => '../biometrics/page.php',
            'Profile' => '../profile/page.php',
            'Mentorship Management' => '../mentorship/page.php',
            'Reports Management' => '../reports/page.php',
        ],
        'sidebars' => ['Staffs Summary', 'Trainings Statistics'],
    ],
];

// Get the user's role from session, default to 'guest' if not set
$userRole = isset($_SESSION['userrole']) ? strtolower($_SESSION['userrole']) : 'guest';
$permissions = isset($rolePermissions[$userRole]) ? $rolePermissions[$userRole] : $rolePermissions['guest'];
$allowedCards = $permissions['cards'];
$allowedSidebars = $permissions['sidebars'];

// Define card details (title, icon, aria-label, more-info link, color)
$allCards = [
    'Systems Admin' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/wrench.svg',
        'aria-label' => 'Systems Admin',
        'link' => '../admin/page.php',
        'color' => 'purple',
    ],
    'Administrator' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/user-shield.svg',
        'aria-label' => 'Administrator Management',
        'link' => '../admin/page.php',
        'color' => 'purple',
    ],
    'BackUp and Refresh' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/database.svg',
        'aria-label' => 'BackUp and Refresh',
        'link' => '../backup/page.php',
        'color' => 'purple',
    ],
    'Staff Management' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/users.svg',
        'aria-label' => 'Staff Management',
        'link' => '../staffs/page.php',
        'color' => 'purple',
    ],
    'Meetings Management' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/handshake.svg',
        'aria-label' => 'Meetings Management',
        'link' => '../meetings/page.php',
        'color' => 'purple',
    ],
    'Trainings Management' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/graduation-cap.svg',
        'aria-label' => 'Trainings Management',
        'link' => '../trainings/page.php',
        'color' => 'purple',
    ],
    'Mentorship Management' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/user-graduate.svg',
        'aria-label' => 'Mentorship Management',
        'link' => '../mentorship/page.php',
        'color' => 'purple',
    ],
    'Reports Management' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/file-alt.svg',
        'aria-label' => 'Reports Management',
        'link' => '../reports/page.php',
        'color' => 'purple',
    ],
    'Biometrics' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/fingerprint.svg',
        'aria-label' => 'Biometrics',
        'link' => '../biometrics/page.php',
        'color' => 'purple',
    ],
    'Profile' => [
        'icon' => '../assets/fontawesome/svgs-full/solid/user-circle.svg',
        'aria-label' => 'Profile',
        'link' => '../profile/page.php',
        'color' => 'purple',
    ],
];
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TM Dashboard</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="../assets/css/dashboard.css" type="text/css">
    <style>
        .card-icon-svg {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: white;
            -webkit-mask: var(--svg-icon) no-repeat center;
            mask: var(--svg-icon) no-repeat center;
            -webkit-mask-size: contain;
            mask-size: contain;
        }
        .card:hover .card-icon-svg {
            transform: scale(1.1);
        }
    </style>
</head>
<body>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
    <span class="hamburger-icon"></span>
    <span class="hamburger-icon"></span>
    <span class="hamburger-icon"></span>
</button>

<div class="dashboard-container">
    <div class="cards-grid">
        <?php foreach ($allCards as $title => $card): ?>
            <?php if (isset($allowedCards[$title])): ?>
                <?php
                // Determine if icon is SVG or Font Awesome class
                $isSvg = strpos($card['icon'], '.svg') !== false;
                ?>
                <div class="card <?php echo htmlspecialchars($card['color']); ?>"
                     onclick="window.location.href='<?php echo htmlspecialchars($card['link']); ?>'"
                     tabindex="0"
                     role="button"
                     aria-label="<?php echo htmlspecialchars($card['aria-label']); ?>">
                    <div class="card-content">
                        <h3><?php echo htmlspecialchars($title); ?></h3>
                        <?php if ($isSvg): ?>
                            <span class="card-icon card-icon-svg" style="--svg-icon:url('<?php echo htmlspecialchars($card['icon']); ?>')"></span>
                        <?php else: ?>
                            <i class="<?php echo htmlspecialchars($card['icon']); ?> card-icon"></i>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (in_array('Staffs Summary', $allowedSidebars)): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-card">
            <h4 style="color:#0033CC;"><i class="fa fa-users"></i> Staffs Summary</h4>
            <table class="balances-table">
                <thead>
                    <tr>
                        <th>Staff Status</th>
                        <th>Total Number</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="prescription-row">
                        <td style="color:red;">Pending Drafts</td>
                        <td>
                            <span class="count-retro">
                                <a href="../pharmacy/view_prescriptions.php" target="contentFrame" class="nav-link">
                                    <?php include ('../counts/drafts_count.php'); ?>
                                </a>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Total Staff Trained</td>
                        <td>
                            <span class="count-retro">
                                <a href="../staffs/cumulative_ever_Staffs.php" target="contentFrame" class="nav-link">
                                    <?php include ('../counts/trained_ever_count.php'); ?>
                                </a>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Total Meetings</td>
                        <td>
                            <span class="count-retro">
                                <a href="../staffs/ever_enrolled_Staffs.php" target="contentFrame" class="nav-link">
                                    <?php include ('../counts/ever_meetings_count.php'); ?>
                                </a>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Meetings this Month</td>
                        <td>
                            <span class="count-retro">
                                <a href="../staffs/view_active_Staffs.php" target="contentFrame" class="nav-link" style="background:#66ff66;color:#000000;">
                                    <?php include ('../counts/meetings_this_count.php'); ?>
                                </a>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Trainings this month</td>
                        <td>
                            <span class="count-retro">
                                <a href="../staffs/view_weaned_Staffs.php" target="contentFrame" class="nav-link" style="background:#66ff66;color:#000000;">
                                    <?php include ('../counts/trainings_this_count.php'); ?>
                                </a>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array('Trainings Statistics', $allowedSidebars)): ?>
    <div class="sidebar">
        <div class="sidebar-card">
            <h4 style="color:#0033CC;"><i class="fa fa-calculator"></i> Trainings Statistics</h4>
            <?php include '../functions/sql_functions.php';?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');

    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            sidebar.classList.toggle('active');

            // Prevent body scroll when menu is open
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                e.target !== mobileMenuToggle &&
                !mobileMenuToggle.contains(e.target)) {
                mobileMenuToggle.classList.remove('active');
                sidebar.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                mobileMenuToggle.classList.remove('active');
                sidebar.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Add keyboard navigation for cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Add ripple effect on card click
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('div');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 1;
            `;

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});

// Prescription count blinking
function checkPrescriptionCount() {
    const countElement = document.getElementById('prescription-count');
    const row = document.getElementById('prescription-row');
    if (countElement && row) {
        const count = parseInt(countElement.textContent.trim());
        if (count > 0) {
            row.classList.add('blink-animation');
        } else {
            row.classList.remove('blink-animation');
        }
    }
}

// Check on page load
checkPrescriptionCount();

// Session keep-alive
let sessionKeepAlive = null;

function startSessionKeepAlive() {
    sessionKeepAlive = setInterval(() => {
        fetch('../includes/keepalive.php')
            .then(response => response.text())
            .then(data => console.log('Session kept alive'))
            .catch(error => console.error('Error keeping session alive:', error));
    }, 300000); // 5 minutes
}

function stopSessionKeepAlive() {
    if (sessionKeepAlive) {
        clearInterval(sessionKeepAlive);
        sessionKeepAlive = null;
    }
}

// Start when page loads
document.addEventListener('DOMContentLoaded', function() {
    startSessionKeepAlive();
});

// Clean up before page unload
window.addEventListener('beforeunload', stopSessionKeepAlive);

// Reset timeout on user activity
['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, function() {
        fetch('../includes/keepalive.php?activity=1')
            .then(response => response.text())
            .then(data => console.log('Activity recorded'));
    }, {passive: true});
});
</script>
</body>
</html>