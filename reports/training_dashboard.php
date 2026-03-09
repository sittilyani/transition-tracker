<?php
require_once '../includes/config.php';
require_once '../includes/session_check.php';
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

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

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
            max-width: 1400px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .chart-title {
            color: #011f88;
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .dataTables_wrapper {
            margin-top: 20px;
        }

        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            margin-bottom: 15px;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #6c757d;
            font-size: 1.1rem;
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

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .export-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .export-btn:hover {
            background: #218838;
        }

        .export-btn.pdf {
            background: #dc3545;
        }

        .export-btn.pdf:hover {
            background: #c82333;
        }

        /* Responsive Design */
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

            .filter-section,
            .results-section,
            .chart-container,
            .table-container {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.6rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .export-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Training Analysis Dashboard</h1>
            <p>Analyze staff training data with interactive filters and visualizations</p>
        </div>

        <div class="dashboard-container">
            <!-- Filter Section -->
            <div class="filter-section">
                <h2 class="filter-title">Filter Data</h2>

                <form id="filterForm">
                    <div class="form-group">
                        <label>County</label>
                        <select class="form-select" id="county" name="county">
                            <option value="">All Counties</option>
                            <?php
                            $countyQuery = "SELECT DISTINCT county FROM staff_trainings WHERE county IS NOT NULL AND county != '' ORDER BY county";
                            $countyResult = $conn->query($countyQuery);

                            if($countyResult && $countyResult->num_rows > 0) {
                                while ($county = $countyResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($county['county']) . "'>" . htmlspecialchars($county['county']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sub-County</label>
                        <select class="form-select" id="subcounty" name="subcounty">
                            <option value="">All Sub-Counties</option>
                            <!-- Populated dynamically based on county selection -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Facility</label>
                        <select class="form-select" id="facility" name="facility">
                            <option value="">All Facilities</option>
                            <?php
                            $facilityQuery = "SELECT DISTINCT facility_id, facility_name FROM staff_trainings WHERE facility_name IS NOT NULL AND facility_name != '' ORDER BY facility_name";
                            $facilityResult = $conn->query($facilityQuery);

                            if($facilityResult && $facilityResult->num_rows > 0) {
                                while ($facility = $facilityResult->fetch_assoc()) {
                                    echo "<option value='" . $facility['facility_id'] . "'>" . htmlspecialchars($facility['facility_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Course Type</label>
                        <select class="form-select" id="course" name="course">
                            <option value="">All Courses</option>
                            <?php
                            $courseQuery = "SELECT DISTINCT course_id, course_name FROM staff_trainings WHERE course_name IS NOT NULL AND course_name != '' ORDER BY course_name";
                            $courseResult = $conn->query($courseQuery);

                            if($courseResult && $courseResult->num_rows > 0) {
                                while ($course = $courseResult->fetch_assoc()) {
                                    echo "<option value='" . $course['course_id'] . "'>" . htmlspecialchars($course['course_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Duration</label>
                        <select class="form-select" id="duration" name="duration">
                            <option value="">All Durations</option>
                            <?php
                            $durationQuery = "SELECT DISTINCT duration_id, duration_name FROM staff_trainings WHERE duration_name IS NOT NULL AND duration_name != '' ORDER BY duration_name";
                            $durationResult = $conn->query($durationQuery);

                            if($durationResult && $durationResult->num_rows > 0) {
                                while ($duration = $durationResult->fetch_assoc()) {
                                    echo "<option value='" . $duration['duration_id'] . "'>" . htmlspecialchars($duration['duration_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Training Location</label>
                        <select class="form-select" id="location" name="location">
                            <option value="">All Locations</option>
                            <?php
                            $locationQuery = "SELECT DISTINCT location_id, location_name FROM staff_trainings WHERE location_name IS NOT NULL AND location_name != '' ORDER BY location_name";
                            $locationResult = $conn->query($locationQuery);

                            if($locationResult && $locationResult->num_rows > 0) {
                                while ($location = $locationResult->fetch_assoc()) {
                                    echo "<option value='" . $location['location_id'] . "'>" . htmlspecialchars($location['location_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cadre</label>
                        <select class="form-select" id="cadre" name="cadre">
                            <option value="">All Cadres</option>
                            <?php
                            $cadreQuery = "SELECT DISTINCT cadre_id, cadrename FROM staff_trainings WHERE cadrename IS NOT NULL AND cadrename != '' ORDER BY cadrename";
                            $cadreResult = $conn->query($cadreQuery);

                            if($cadreResult && $cadreResult->num_rows > 0) {
                                while ($cadre = $cadreResult->fetch_assoc()) {
                                    echo "<option value='" . $cadre['cadre_id'] . "'>" . htmlspecialchars($cadre['cadrename']) . "</option>";
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
                            $yearQuery = "SELECT DISTINCT YEAR(training_date) as year FROM staff_trainings WHERE training_date IS NOT NULL ORDER BY year DESC";
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

                    <button type="submit" class="btn">Apply Filters</button>
                    <button type="button" id="resetBtn" class="btn btn-reset">Reset Filters</button>
                </form>
            </div>

            <!-- Results Section -->
            <div class="results-section">
                <div class="results-title">
                    <span>Training Analytics</span>
                    <div class="export-buttons">
                        <button class="export-btn" onclick="exportToExcel()">Export to Excel</button>
                        <button class="export-btn pdf" onclick="exportToPDF()">Export to PDF</button>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div class="loading" id="loading">
                    <div style="font-size: 18px; margin-bottom: 10px;">? Loading data...</div>
                    <div style="color: #6c757d;">Please wait while we fetch your data</div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid" id="statsCards">
                    <!-- Stats will be loaded here -->
                </div>

                <!-- Charts -->
                <div class="chart-container">
                    <h3 class="chart-title">Training Distribution by Course</h3>
                    <div class="chart-wrapper">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3 class="chart-title">Monthly Training Trend</h3>
                    <div class="chart-wrapper">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3 class="chart-title">Training by Cadre</h3>
                    <div class="chart-wrapper">
                        <canvas id="cadreChart"></canvas>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-container">
                    <h3 class="chart-title">Training Records</h3>
                    <div style="overflow-x: auto;">
                        <table id="trainingTable" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Facility</th>
                                    <th>Staff Name</th>
                                    <th>Course</th>
                                    <th>Duration</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Cadre</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- SheetJS for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize Select2 for all dropdowns
        $('.form-select').select2({
            placeholder: 'Select an option...',
            allowClear: true,
            width: '100%'
        });

        // Initialize DataTable
        let dataTable = $('#trainingTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthChange: true,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            responsive: true
        });

        // Chart instances
        let courseChart = null;
        let monthlyChart = null;
        let cadreChart = null;

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

        // County change - load subcounties
        $('#county').on('change', function() {
            const county = $(this).val();
            const subcountySelect = $('#subcounty');

            subcountySelect.html('<option value="">All Sub-Counties</option>');

            if (county) {
                $.ajax({
                    url: 'fetch_subcounties.php',
                    method: 'POST',
                    data: { county: county },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.subcounties) {
                            response.subcounties.forEach(function(subcounty) {
                                subcountySelect.append('<option value="' + subcounty + '">' + subcounty + '</option>');
                            });
                        }
                    },
                    error: function() {
                        console.error('Error loading subcounties');
                    }
                });
            }
        });

        // Main function to load dashboard data
        function loadDashboardData() {
            // Show loading
            $('#loading').addClass('active');
            $('#statsCards').html('');
            dataTable.clear().draw();

            // Destroy existing charts
            if (courseChart) courseChart.destroy();
            if (monthlyChart) monthlyChart.destroy();
            if (cadreChart) cadreChart.destroy();

            // Get filter values
            const filters = {
                county: $('#county').val(),
                subcounty: $('#subcounty').val(),
                facility: $('#facility').val(),
                course: $('#course').val(),
                duration: $('#duration').val(),
                location: $('#location').val(),
                cadre: $('#cadre').val(),
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

                        // Update data table
                        updateDataTable(response.trainings);

                        // Hide loading
                        $('#loading').removeClass('active');
                    } else {
                        alert('Error loading data: ' + response.message);
                        $('#loading').removeClass('active');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error loading dashboard data. Please try again.');
                    $('#loading').removeClass('active');
                }
            });
        }

        function updateStatsCards(stats) {
            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-value">${stats.totalTrainings}</div>
                    <div class="stat-label">Total Trainings</div>
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
            `;

            $('#statsCards').html(statsHtml);
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
                        label: 'Trainings per Month',
                        data: chartData.monthlyData,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 2,
                        fill: true,
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
                            '#9b59b6', '#1abc9c', '#d35400', '#34495e'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }

        function updateDataTable(trainings) {
            dataTable.clear();

            trainings.forEach(function(training, index) {
                dataTable.row.add([
                    index + 1,
                    training.facility_name,
                    training.staff_name,
                    training.course_name,
                    training.duration_name,
                    new Date(training.training_date).toLocaleDateString(),
                    training.location_name,
                    training.cadrename || training.staff_cadre,
                    training.staff_phone
                ]);
            });

            dataTable.draw();
        }

        // Export functions
        window.exportToExcel = function() {
            const data = [];
            const headers = ['#', 'Facility', 'Staff Name', 'Course', 'Duration', 'Date', 'Location', 'Cadre', 'Phone'];
            data.push(headers);

            $('#trainingTable tbody tr').each(function(index) {
                const row = [];
                row.push(index + 1);
                $(this).find('td').each(function(i) {
                    if (i !== 0) { // Skip the first column since we're adding index manually
                        row.push($(this).text());
                    }
                });
                data.push(row);
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Trainings');
            XLSX.writeFile(wb, 'training_data.xlsx');
        };

        window.exportToPDF = function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Title
            doc.setFontSize(18);
            doc.text('Training Report', 14, 22);

            // Filters info
            doc.setFontSize(11);
            let yPos = 35;
            const filters = {
                'County': $('#county').val() || 'All',
                'Sub-County': $('#subcounty').val() || 'All',
                'Facility': $('#facility option:selected').text() || 'All',
                'Course': $('#course option:selected').text() || 'All',
                'Year': $('#year').val() || 'All'
            };

            Object.entries(filters).forEach(([key, value]) => {
                doc.text(`${key}: ${value}`, 14, yPos);
                yPos += 7;
            });

            yPos += 10;

            // Table headers
            const headers = [['#', 'Facility', 'Staff Name', 'Course', 'Date', 'Cadre']];
            const rows = [];

            $('#trainingTable tbody tr').each(function(index) {
                const cols = $(this).find('td');
                rows.push([
                    index + 1,
                    $(cols[1]).text(),
                    $(cols[2]).text(),
                    $(cols[3]).text(),
                    $(cols[5]).text(),
                    $(cols[7]).text()
                ]);
            });

            doc.autoTable({
                head: headers,
                body: rows,
                startY: yPos,
                theme: 'grid',
                headStyles: { fillColor: [1, 31, 136] }
            });

            doc.save('training_report.pdf');
        };
    });
    </script>
</body>
</html>