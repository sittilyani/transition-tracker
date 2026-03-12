<?php
require_once '../includes/config.php';
require_once '../includes/session_check.php';

// Get counts for stats
$total_self_trainings = 0;
$total_session_trainings = 0;
$total_staff_trained = 0;
$total_facilities = 0;
$total_courses = 0;

// Get count from staff_self_trainings (only verified)
$self_count = $conn->query("SELECT COUNT(*) as count FROM staff_self_trainings WHERE status = 'verified'");
if ($self_count) {
    $total_self_trainings = $self_count->fetch_assoc()['count'];
}

// Get count from staff_trainings (all are verified by default)
$session_count = $conn->query("SELECT COUNT(*) as count FROM staff_trainings");
if ($session_count) {
    $total_session_trainings = $session_count->fetch_assoc()['count'];
}

// Get unique staff count (union of both tables)
$staff_count = $conn->query("
    SELECT COUNT(DISTINCT id_number) as count FROM (
        SELECT id_number FROM staff_self_trainings WHERE status = 'verified'
        UNION
        SELECT id_number FROM staff_trainings
    ) as combined_staff
");
if ($staff_count) {
    $total_staff_trained = $staff_count->fetch_assoc()['count'];
}

// Get unique facilities count from county_staff (joined with trainings)
$facility_count = $conn->query("
    SELECT COUNT(DISTINCT cs.facility_name) as count FROM (
        SELECT sst.staff_id FROM staff_self_trainings sst WHERE sst.status = 'verified'
        UNION ALL
        SELECT st.staff_id FROM staff_trainings st
    ) as trainings
    JOIN county_staff cs ON trainings.staff_id = cs.staff_id
");
if ($facility_count) {
    $total_facilities = $facility_count->fetch_assoc()['count'];
}

// Get unique courses count
$course_count = $conn->query("
    SELECT COUNT(DISTINCT course_name) as count FROM (
        SELECT course_name FROM staff_self_trainings WHERE status = 'verified'
        UNION
        SELECT course_name FROM staff_trainings
    ) as combined_courses
");
if ($course_count) {
    $total_courses = $course_count->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Dashboard</title>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .container {
            width: 95%;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #011f88, #3498db);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(1, 31, 136, 0.2);
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .header .badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            margin-top: 10px;
            font-size: 14px;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #011f88;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .filter-title {
            color: #011f88;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .results-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .results-title {
            color: #011f88;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
            font-size: 14px;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: white;
        }

        .btn {
            background: linear-gradient(135deg, #011f88, #3498db);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: block;
            width: 100%;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(1, 31, 136, 0.2);
        }

        .btn:hover {
            background: linear-gradient(135deg, #011166, #2980b9);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(1, 31, 136, 0.3);
        }

        .btn-reset {
            background: linear-gradient(135deg, #6c757d, #495057);
            margin-top: 15px;
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-top: 4px solid #011f88;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #011f88;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
        }

        .stat-sub {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .chart-title {
            color: #011f88;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title .badge {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .loading.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .filter-section {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Training Analysis Dashboard</h1>
            <p>Comprehensive analytics of all training activities</p>
            <span class="badge"><i class="fas fa-database"></i> Data Sources: Individual & Session-based Trainings</span>
        </div>

        <div class="dashboard-container">
            <!-- Filter Section -->
            <div class="filter-section">
                <h2 class="filter-title"><i class="fas fa-filter"></i> Filter Data</h2>

                <form id="filterForm">
                    <div class="form-group">
                        <label>Data Source</label>
                        <select class="form-select" id="data_source" name="data_source">
                            <option value="all">All Sources</option>
                            <option value="self">Individual Trainings Only</option>
                            <option value="session">Session-based Trainings Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>County</label>
                        <select class="form-select" id="county" name="county">
                            <option value="">All Counties</option>
                            <?php
                            $countyQuery = "SELECT DISTINCT county_name FROM county_staff WHERE county_name IS NOT NULL AND county_name != '' ORDER BY county_name";
                            $countyResult = $conn->query($countyQuery);

                            if($countyResult && $countyResult->num_rows > 0) {
                                while ($county = $countyResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($county['county_name']) . "'>" . htmlspecialchars($county['county_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sub-County</label>
                        <select class="form-select" id="subcounty" name="subcounty">
                            <option value="">All Sub-Counties</option>
                            <?php
                            $subcountyQuery = "SELECT DISTINCT subcounty_name FROM county_staff WHERE subcounty_name IS NOT NULL AND subcounty_name != '' ORDER BY subcounty_name";
                            $subcountyResult = $conn->query($subcountyQuery);

                            if($subcountyResult && $subcountyResult->num_rows > 0) {
                                while ($subcounty = $subcountyResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($subcounty['subcounty_name']) . "'>" . htmlspecialchars($subcounty['subcounty_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Facility</label>
                        <select class="form-select" id="facility" name="facility">
                            <option value="">All Facilities</option>
                            <?php
                            $facilityQuery = "SELECT DISTINCT facility_name FROM county_staff WHERE facility_name IS NOT NULL AND facility_name != '' ORDER BY facility_name";
                            $facilityResult = $conn->query($facilityQuery);

                            if($facilityResult && $facilityResult->num_rows > 0) {
                                while ($facility = $facilityResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($facility['facility_name']) . "'>" . htmlspecialchars($facility['facility_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Course</label>
                        <select class="form-select" id="course" name="course">
                            <option value="">All Courses</option>
                            <?php
                            $courseQuery = "SELECT DISTINCT course_name FROM (
                                SELECT course_name FROM staff_trainings WHERE course_name IS NOT NULL AND course_name != ''
                                UNION
                                SELECT course_name FROM staff_self_trainings WHERE status = 'verified'
                            ) as courses ORDER BY course_name";
                            $courseResult = $conn->query($courseQuery);

                            if($courseResult && $courseResult->num_rows > 0) {
                                while ($course = $courseResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($course['course_name']) . "'>" . htmlspecialchars($course['course_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Year</label>
                        <select class="form-select" id="year" name="year">
                            <option value="">All Years</option>
                            <?php
                            $yearQuery = "SELECT DISTINCT YEAR(start_date) as year FROM (
                                SELECT start_date FROM staff_trainings WHERE start_date IS NOT NULL
                                UNION
                                SELECT start_date FROM staff_self_trainings WHERE status = 'verified'
                            ) as years ORDER BY year DESC";
                            $yearResult = $conn->query($yearQuery);

                            if($yearResult && $yearResult->num_rows > 0) {
                                while ($year = $yearResult->fetch_assoc()) {
                                    if ($year['year']) {
                                        echo "<option value='" . $year['year'] . "'>" . $year['year'] . "</option>";
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Month</label>
                        <select class="form-select" id="month" name="month">
                            <option value="">All Months</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>

                    <button type="submit" class="btn"><i class="fas fa-search"></i> Apply Filters</button>
                    <button type="button" id="resetBtn" class="btn btn-reset"><i class="fas fa-undo"></i> Reset Filters</button>
                </form>
            </div>

            <!-- Results Section (No Staff List) -->
            <div class="results-section">
                <div class="results-title">
                    <span><i class="fas fa-chart-bar"></i> Training Analytics</span>
                </div>

                <!-- Loading Indicator -->
                <div class="loading" id="loading">
                    <div class="spinner" style="font-size: 18px; margin-bottom: 10px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                    <div style="color: #6c757d;">Loading dashboard data...</div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid" id="statsCards">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($total_self_trainings + $total_session_trainings); ?></div>
                        <div class="stat-label">Total Trainings</div>
                        <div class="stat-sub">Self: <?php echo number_format($total_self_trainings); ?> | Session: <?php echo number_format($total_session_trainings); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($total_staff_trained); ?></div>
                        <div class="stat-label">Staff Trained</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($total_facilities); ?></div>
                        <div class="stat-label">Facilities</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($total_courses); ?></div>
                        <div class="stat-label">Courses</div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i> Training by Course
                            <span class="badge">Top 10</span>
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="courseChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i> Training by Cadre
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="cadreChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i> Monthly Training Trend
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i> Training by Department
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 3 -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i> Training by Facility Type
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="facilityChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-bar"></i> Training by County
                        </h3>
                        <div class="chart-wrapper">
                            <canvas id="countyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize Select2 for all dropdowns
        $('.form-select').select2({
            placeholder: 'Select an option...',
            allowClear: true,
            width: '100%'
        });

        // Chart instances
        let courseChart = null;
        let monthlyChart = null;
        let cadreChart = null;
        let departmentChart = null;
        let facilityChart = null;
        let countyChart = null;

        // Load initial data
        loadDashboardData();

        // Handle filter form submission
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            loadDashboardData();
        });

        // Handle reset button
        $('#resetBtn').on('click', function() {
            $('#filterForm')[0].reset();
            $('.form-select').val(null).trigger('change');
            loadDashboardData();
        });

        // Main function to load dashboard data
        function loadDashboardData() {
            // Show loading
            $('#loading').addClass('active');

            // Destroy existing charts
            if (courseChart) courseChart.destroy();
            if (monthlyChart) monthlyChart.destroy();
            if (cadreChart) cadreChart.destroy();
            if (departmentChart) departmentChart.destroy();
            if (facilityChart) facilityChart.destroy();
            if (countyChart) countyChart.destroy();

            // Get filter values
            const filters = {
                data_source: $('#data_source').val(),
                county: $('#county').val(),
                subcounty: $('#subcounty').val(),
                facility: $('#facility').val(),
                course: $('#course').val(),
                year: $('#year').val(),
                month: $('#month').val()
            };

            // AJAX call to fetch data
            $.ajax({
                url: 'fetch_dashboard_data.php',
                method: 'POST',
                data: filters,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update stats cards
                        updateStatsCards(response.stats);

                        // Update charts
                        createCharts(response.chartData);

                        // Hide loading
                        $('#loading').removeClass('active');
                    } else {
                        alert('Error loading data: ' + response.message);
                        $('#loading').removeClass('active');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);
                    alert('Error loading dashboard data. Please try again.');
                    $('#loading').removeClass('active');
                }
            });
        }

        function updateStatsCards(stats) {
            $('#statsCards').html(`
                <div class="stat-card">
                    <div class="stat-value">${stats.totalTrainings}</div>
                    <div class="stat-label">Total Trainings</div>
                    <div class="stat-sub">Self: ${stats.selfTrainings} | Session: ${stats.sessionTrainings}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.totalStaff}</div>
                    <div class="stat-label">Staff Trained</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.totalFacilities}</div>
                    <div class="stat-label">Facilities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.totalCourses}</div>
                    <div class="stat-label">Courses</div>
                </div>
            `);
        }

        function createCharts(chartData) {
            // Course Distribution Chart
            const courseCtx = document.getElementById('courseChart').getContext('2d');
            courseChart = new Chart(courseCtx, {
                type: 'bar',
                data: {
                    labels: chartData.courses.map(c => c.course_name),
                    datasets: [{
                        label: 'Number of Trainings',
                        data: chartData.courses.map(c => c.count),
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Monthly Trend Chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Self Trainings',
                        data: chartData.monthlyData.self,
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        borderColor: '#17a2b8',
                        borderWidth: 2,
                        tension: 0.4
                    },
                    {
                        label: 'Session Trainings',
                        data: chartData.monthlyData.session,
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderColor: '#28a745',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Cadre Distribution Chart
            const cadreCtx = document.getElementById('cadreChart').getContext('2d');
            cadreChart = new Chart(cadreCtx, {
                type: 'pie',
                data: {
                    labels: chartData.cadres.map(c => c.cadre_name),
                    datasets: [{
                        data: chartData.cadres.map(c => c.count),
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#e74c3c', '#f39c12',
                            '#9b59b6', '#1abc9c', '#d35400', '#34495e',
                            '#e67e22', '#27ae60', '#2980b9', '#8e44ad'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // Department Distribution Chart
            const deptCtx = document.getElementById('departmentChart').getContext('2d');
            departmentChart = new Chart(deptCtx, {
                type: 'pie',
                data: {
                    labels: chartData.departments.map(d => d.department_name),
                    datasets: [{
                        data: chartData.departments.map(d => d.count),
                        backgroundColor: [
                            '#e74c3c', '#f39c12', '#1abc9c', '#9b59b6',
                            '#34495e', '#e67e22', '#27ae60', '#2980b9'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // Facility Type Chart
            const facilityCtx = document.getElementById('facilityChart').getContext('2d');
            facilityChart = new Chart(facilityCtx, {
                type: 'bar',
                data: {
                    labels: chartData.facilities.map(f => f.facility_name),
                    datasets: [{
                        label: 'Trainings by Facility',
                        data: chartData.facilities.map(f => f.count),
                        backgroundColor: 'rgba(46, 204, 113, 0.7)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // County Chart
            const countyCtx = document.getElementById('countyChart').getContext('2d');
            countyChart = new Chart(countyCtx, {
                type: 'bar',
                data: {
                    labels: chartData.counties.map(c => c.county_name),
                    datasets: [{
                        label: 'Trainings by County',
                        data: chartData.counties.map(c => c.count),
                        backgroundColor: 'rgba(155, 89, 182, 0.7)',
                        borderColor: 'rgba(155, 89, 182, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>