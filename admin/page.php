<?php
// Use centralized session manager
include "../includes/session_manager.php";
requireLogin();

// Include configuration
include "../includes/config.php";

// Get user info from session manager functions
$userrole = getUserRole();
$user_id = getUserId();
$full_name = getUserFullName();
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TM - Monitoring</title>
<link rel="apple-touch-icon" sizes="180x180" href="../assets/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/favicons/favicon-16x16.png">
<link rel="manifest" href="../assets/favicons/site.webmanifest">
<link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
<link rel="stylesheet" href="../assets/css/page.css" type="text/css">
<link rel="stylesheet" href="../assets/fontawesome/css/all.min.css" type="text/css">
<link rel="stylesheet" href="../assets/fontawesome/css/svg.css" type="text/css">
<style>

</style>
</head>
<body>

<!-- Timeout Warning Modal -->
<div id="timeout-warning" class="timeout-warning">
    <h4><i class="fa fa-exclamation-triangle"></i> Session Timeout Warning</h4>
    <p>Your session will expire in <span id="countdown">60</span> seconds due to inactivity.</p>
    <button onclick="continueSession()">Continue Session</button>
</div>

<div class="sidenav">
    <center>
        <img src="../assets/images/logo-globe.png" width="100" height="100" alt="">
    </center> <br>
    <h2>
        Administrator Settings
    </h2>
                <a href="../dashboard/dashboard.php" class="home-link">
                    <img src="../assets/fontawesome/svgs-full/solid/house.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Home</a>

                <a href="../reports/dashboard.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/house.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Reports Dashboard</a>

                <a href="../public/userslist.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/shield-virus.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Password Management</a>

                <a href="../trainings/training_drafts.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/wifi-3.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    View drafts trainings</a>

                <a href="../trainings/training_list.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/vault.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Add trainings</a>

                <a href="../pharmacy/dispensing_pump.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/users-cog.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Add meetings</a>

                <a href="../staff/view_staff.php" target="contentFrame" class="nav-link" style="color: #FFF; margin-top: 10px;">
                    <img src="../assets/fontawesome/svgs-full/solid/venus-double.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Add staff</a>

                <a href="../backup/view_backups-manual.php" target="contentFrame" class="nav-link">
                    <img src="../assets/fontawesome/svgs-full/solid/database.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                    Back Up Database</a>


        </div>

<div class="main">
    <div class="content-header">
        <h2>Managing participant registration in meetings and trainings</h2>
        <div class="user-info">
            <span>Welcome, <strong><?php echo $_SESSION['full_name'] ?? 'User'; ?></strong> (<?php echo $userrole; ?>)</span>
            <span class="current-time">
                <img src="../assets/fontawesome/svgs-full/solid/clock.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;">
                 <span id="current-time"><?php echo date('H:i:s'); ?></span>
            </span>
            <a href="../public/login.php" class="logout-btn">
                <img src="../assets/fontawesome/svgs-full/solid/sign-out.svg" width="18" height="18" style="margin-right:8px;vertical-align:middle;filter:invert(1)">
                Logout </a>
        </div>
    </div>

    <div class="content-area">
        <!-- Loading Spinner -->
        <div id="loadingSpinner" style="display: none; text-align: center; padding: 50px;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p style="margin-top: 10px;">Loading content...</p>
        </div>

        <iframe name="contentFrame" src="about:blank" style="width: 100%; height: 90vh; border: none; display: none;" id="contentFrame"></iframe>

        <div class="welcome-message" id="welcomeMessage">
            <img src="../assets/fontawesome/svgs/brands/themeco.svg" width="172" height="116" alt="">
            <img src="../assets/fontawesome/svgs-full/solid/wrench.svg" width="172" height="116" alt="">
            <h3>Remember these steps and Repeat <span style="color: red;">PRIME/REVERSE PRIME AND CALIBRATION</span> every after serving <span style="color: red;">20</span> Clients </h3>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.nav-link[target="contentFrame"]');
        const contentFrame = document.getElementById('contentFrame');
        const welcomeMessage = document.getElementById('welcomeMessage');

        // Function to update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            document.getElementById('current-time').textContent = timeString;
        }

        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);

        // Function to load content into iframe
        function loadContent(url) {
            if (url) {
                // Show loading spinner
                const loadingSpinner = document.getElementById('loadingSpinner');
                loadingSpinner.style.display = 'block';

                // Hide welcome message and frame
                contentFrame.style.display = 'none';
                welcomeMessage.style.display = 'none';

                // Load the content
                contentFrame.src = url;
            }
        }

        // Add click event listeners to navigation links (excluding Home)
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');

                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));

                // Add active class to clicked link
                this.classList.add('active');

                // Load content
                loadContent(url);

                // Reset timeout timer on activity
                resetTimer();
            });
        });

        // Handle iframe load event
        contentFrame.addEventListener('load', function() {
            // Hide loading spinner and show iframe
            const loadingSpinner = document.getElementById('loadingSpinner');
            loadingSpinner.style.display = 'none';

            // Only show iframe if it has actual content (not about:blank)
            if (this.src !== 'about:blank' && this.src !== '') {
                this.style.display = 'block';
            }

            try {
                // Adjust iframe height to content
                const iframeDoc = this.contentDocument || this.contentWindow.document;
                const iframeBody = iframeDoc.body;
                const iframeHtml = iframeDoc.documentElement;

                const height = Math.max(
                    iframeBody.scrollHeight,
                    iframeBody.offsetHeight,
                    iframeHtml.clientHeight,
                    iframeHtml.scrollHeight,
                    iframeHtml.offsetHeight
                );

                this.style.height = height + 'px';
            } catch (e) {
                // Cross-origin frame, can't access contents
                console.log('Cannot adjust iframe height due to cross-origin restrictions');
            }
        });

        // Show welcome message initially
        contentFrame.style.display = 'none';
        welcomeMessage.style.display = 'block';
    });

    // Auto logout after inactivity
    let timeout;
    const warningTime = 60; // Show warning 60 seconds before logout
    const logoutTime = 600; // Logout after 600 seconds (5 minutes)

    function resetTimer() {
        clearTimeout(timeout);

        // Hide warning if visible
        const warningElement = document.getElementById('timeout-warning');
        if (warningElement) {
            warningElement.style.display = 'none';
        }

        // Set new timeout
        timeout = setTimeout(showWarning, (logoutTime - warningTime) * 1000);
    }

    function showWarning() {
        // Show warning
        const warningElement = document.getElementById('timeout-warning');
        if (warningElement) {
            warningElement.style.display = 'block';

            // Start countdown
            let seconds = warningTime;
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                countdownElement.textContent = seconds;

                const countdownInterval = setInterval(() => {
                    seconds--;
                    countdownElement.textContent = seconds;

                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = '../public/login.php?timeout=1';
                    }
                }, 1000);
            }

            // Set final logout timeout
            timeout = setTimeout(() => {
                window.location.href = '../public/login.php?timeout=1';
            }, warningTime * 1000);
        }
    }

    function continueSession() {
        // Hide warning
        const warningElement = document.getElementById('timeout-warning');
        if (warningElement) {
            warningElement.style.display = 'none';
        }

        // Reset timer
        resetTimer();

        // Send a request to the server to keep the session alive
        fetch('../includes/keepalive.php')
            .then(response => response.text())
            .then(data => {
                console.log('Session extended');
            })
            .catch(error => {
                console.error('Error extending session:', error);
            });
    }

    // Reset timer on any user activity
    document.addEventListener('mousemove', resetTimer);
    document.addEventListener('keypress', resetTimer);
    document.addEventListener('click', resetTimer);
    document.addEventListener('scroll', resetTimer);

    // Initialize timer
    resetTimer();
</script>
<script>
            // Keep session alive across page navigation
            let sessionKeepAlive = null;

            function startSessionKeepAlive() {
                // Send keep-alive request every 5 minutes
                sessionKeepAlive = setInterval(() => {
                    fetch('../includes/keepalive.php')
                        .then(response => response.text())
                        .then(data => {
                            console.log('Session kept alive');
                        })
                        .catch(error => {
                            console.error('Error keeping session alive:', error);
                        });
                }, 300000); // 5 minutes
            }

            // Stop keep-alive when leaving page
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
                    // Send activity ping
                    fetch('../includes/keepalive.php?activity=1')
                        .then(response => response.text())
                        .then(data => {
                            console.log('Activity recorded');
                        });
                });
            });
    </script>

</body>
</html>