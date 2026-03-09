<?php
require_once '../includes/config.php';
require_once '../includes/session_check.php';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Training Registration</title>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #011f88;
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #3498db;
        }

        .section-title {
            color: #011f88;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #011f88;
            font-size: 15px;
        }

        .form-control, .form-select, textarea.form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .form-control:focus, .form-select:focus, textarea.form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: white;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 17px;
            font-weight: 600;
            transition: all 0.3s;
            display: block;
            width: 100%;
            max-width: 350px;
            margin: 40px auto 20px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1c5a7e);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 5px solid #28a745;
            font-weight: 500;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 5px solid #dc3545;
            font-weight: 500;
        }

        .info-box {
            background: #e8f4fc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid #3498db;
        }

        .info-item {
            display: flex;
            margin-bottom: 10px;
        }

        .info-label {
            min-width: 150px;
            font-weight: 600;
            color: #011f88;
        }

        .info-value {
            color: #34495e;
            font-weight: 500;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-style: italic;
        }

        .loading.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 1.7rem;
            }

            .form-section {
                padding: 20px;
            }

            .section-title {
                font-size: 1.3rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .info-item {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Staff Training Registration</h1>
            <p>Enter staff training details for record keeping</p>
            <a href="bulk_import_training.php">Import CSV file</a>
        </div>

        <?php
        // Display success message
        if (isset($_SESSION['success'])) {
            echo '<div class="success-message">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }

        // Display error message
        if (isset($_SESSION['error'])) {
            echo '<div class="error-message">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <form method="POST" action="submit_training.php" id="trainingForm">

            <!-- Facility Information Section -->
            <div class="form-section">
                <h2 class="section-title">Facility Information</h2>

                <div class="form-group">
                    <label class="required">Facility Name</label>
                    <select class="form-select" id="facility_name" name="facility_name" required>
                        <option value="">-- Select Facility --</option>
                        <?php
                        $facilityQuery = "SELECT facility_id, facility_name, mflcode FROM facilities ORDER BY facility_name";
                        $facilityResult = $conn->query($facilityQuery);

                        if($facilityResult && $facilityResult->num_rows > 0) {
                            while ($facility = $facilityResult->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($facility['facility_name']) . "'
                                        data-id='" . $facility['facility_id'] . "'
                                        data-mfl='" . htmlspecialchars($facility['mflcode']) . "'>"
                                        . htmlspecialchars($facility['facility_name']) . "</option>";
                            }
                        } else {
                            echo "<option value=''>No facilities found</option>";
                        }
                        ?>
                    </select>
                    <input type="hidden" id="facility_id" name="facility_id">
                    <input type="hidden" id="mflcode" name="mflcode">
                    <input type="hidden" id="county" name="county">
                    <input type="hidden" id="subcounty" name="subcounty">
                </div>

                <div id="facilityInfo" class="info-box" style="display:none;">
                    <div class="info-item">
                        <span class="info-label">Facility Name:</span>
                        <span class="info-value" id="displayfacility_name">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">MFL Code:</span>
                        <span class="info-value" id="displayMFLCode">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">County:</span>
                        <span class="info-value" id="displayCounty">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Subcounty:</span>
                        <span class="info-value" id="displaySubcounty">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Level of Care:</span>
                        <span class="info-value" id="displayLevelofcare">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ownership:</span>
                        <span class="info-value" id="displayOwnership">-</span>
                    </div>
                </div>
            </div>

            <!-- Staff Information Section -->
            <div class="form-section">
                <h2 class="section-title">Staff Information</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Staff Name</label>
                        <input type="text" class="form-control" id="staff_name" name="staff_name"
                               placeholder="Enter staff full name" required>
                    </div>

                    <div class="form-group">
                        <label>Staff P/No</label>
                        <input type="text" class="form-control" id="staff_p_no" name="staff_p_no"
                               placeholder="Enter staff P/No (Optional)">
                    </div>

                    <div class="form-group">
                        <label class="required">Phone Number</label>
                        <input type="tel" class="form-control" id="staff_phone" name="staff_phone"
                               placeholder="07XXXXXXXX or 01XXXXXXXX" pattern="0[0-9]{9}" required>
                    </div>

                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="staff@example.com" required>
                    </div>

                    <div class="form-group">
                        <label class="required">Department</label>
                        <select class="form-select" id="department_name" name="department_name" required>
                            <option value="">-- Select department --</option>
                            <?php
                            $departmentQuery = "SELECT department_id, department_name FROM departments ORDER BY department_name";
                            $departmentResult = $conn->query($departmentQuery);

                            if($departmentResult && $departmentResult->num_rows > 0) {
                                while ($department = $departmentResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($department['department_name']) . "'
                                            data-id='" . $department['department_id'] . "'>"
                                            . htmlspecialchars($department['department_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No departments found</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="required">Sex</label>
                        <select class="form-select" id="sex_name" name="sex_name" required>
                            <option value="">-- Select Sex --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Training Information Section -->
            <div class="form-section">
                <h2 class="section-title">Training Information</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Training Type</label>
                        <select class="form-select" id="trainingtype_name" name="trainingtype_name" required>
                            <option value="">-- Select training type --</option>
                            <?php
                            $trainingtypeQuery = "SELECT trainingtype_id, trainingtype_name FROM trainingtypes ORDER BY trainingtype_name";
                            $trainingtypeResult = $conn->query($trainingtypeQuery);

                            if($trainingtypeResult && $trainingtypeResult->num_rows > 0) {
                                while ($trainingtype = $trainingtypeResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($trainingtype['trainingtype_name']) . "'
                                            data-id='" . $trainingtype['trainingtype_id'] . "'>"
                                            . htmlspecialchars($trainingtype['trainingtype_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No training types found</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="required">Course/Training Name</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php
                            $courseQuery = "SELECT course_id, course_name FROM courses ORDER BY course_name";
                            $courseResult = $conn->query($courseQuery);

                            if($courseResult && $courseResult->num_rows > 0) {
                                while ($course = $courseResult->fetch_assoc()) {
                                    echo "<option value='" . $course['course_id'] . "'
                                            data-name='" . htmlspecialchars($course['course_name']) . "'>"
                                            . htmlspecialchars($course['course_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No courses found</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="course_name" name="course_name">
                    </div>

                    <div class="form-group">
                        <label class="required">Duration</label>
                        <select class="form-select" id="duration_id" name="duration_id" required>
                            <option value="">-- Select Duration --</option>
                            <?php
                            $durationQuery = "SELECT duration_id, duration_name FROM course_durations ORDER BY duration_id";
                            $durationResult = $conn->query($durationQuery);

                            if($durationResult && $durationResult->num_rows > 0) {
                                while ($duration = $durationResult->fetch_assoc()) {
                                    echo "<option value='" . $duration['duration_id'] . "'
                                            data-name='" . htmlspecialchars($duration['duration_name']) . "'>"
                                            . htmlspecialchars($duration['duration_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No durations found</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="duration_name" name="duration_name">
                    </div>

                    <div class="form-group">
                        <label class="required">Training Location</label>
                        <select class="form-select" id="location_id" name="location_id" required>
                            <option value="">-- Select Location --</option>
                            <?php
                            $locationQuery = "SELECT location_id, location_name FROM training_locations ORDER BY location_name";
                            $locationResult = $conn->query($locationQuery);

                            if($locationResult && $locationResult->num_rows > 0) {
                                while ($location = $locationResult->fetch_assoc()) {
                                    echo "<option value='" . $location['location_id'] . "'
                                            data-name='" . htmlspecialchars($location['location_name']) . "'>"
                                            . htmlspecialchars($location['location_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No locations found</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="location_name" name="location_name">
                    </div>
                </div>
            </div>

            <!-- Facilitator Information Section -->
            <div class="form-section">
                <h2 class="section-title">Facilitator Information</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Facilitator Name</label>
                        <input type="text" class="form-control" id="facilitator_name" name="facilitator_name"
                               placeholder="Enter facilitator full name" required>
                    </div>

                    <div class="form-group">
                        <label class="required">Facilitator Cadre</label>
                        <select class="form-select" id="cadre_id" name="facilitator    _name" required>
                            <option value="">-- Select Cadre --</option>
                            <?php
                            $cadreQuery = "SELECT cadre_id, cadrename FROM cadres ORDER BY cadrename";
                            $cadreResult = $conn->query($cadreQuery);

                            if($cadreResult && $cadreResult->num_rows > 0) {
                                while ($cadre = $cadreResult->fetch_assoc()) {
                                    echo "<option value='" . $cadre['cadre_id'] . "'
                                            data-name='" . htmlspecialchars($cadre['cadrename']) . "'>"
                                            . htmlspecialchars($cadre['cadrename']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No cadres found</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="cadre_name" name="cadre_name">
                    </div>

                    <div class="form-group">
                        <label class="required">Facilitator Level</label>
                        <select class="form-select" id="fac_level_id" name="fac_level_id" required>
                            <option value="">-- Select Level --</option>
                            <?php
                            $levelQuery = "SELECT fac_level_id, facilitator_level_name FROM facilitator_levels ORDER BY facilitator_level_name";
                            $levelResult = $conn->query($levelQuery);

                            if($levelResult && $levelResult->num_rows > 0) {
                                while ($level = $levelResult->fetch_assoc()) {
                                    echo "<option value='" . $level['fac_level_id'] . "'
                                            data-name='" . htmlspecialchars($level['facilitator_level_name']) . "'>"
                                            . htmlspecialchars($level['facilitator_level_name']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No levels found</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="facilitator_level" name="facilitator_level">
                    </div>
                </div>

                <div class="form-group">
                    <label>Remarks / Additional Notes</label>
                    <textarea class="form-control" id="remarks" name="remarks"
                              placeholder="Any additional information about the training..."></textarea>
                </div>
                <div class="form-group">
                    <label class="required">Training Date</label>
                    <input type="date" class="form-control" id="training_date" name="training_date" required
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <button type="submit" class="btn">Register Training</button>
        </form>
    </div>

    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize Select2 for all dropdowns to enable search by typing
        $('select').select2({
            placeholder: function(){
                $(this).data('placeholder');
            },
            allowClear: true,
            width: '100%'
        });

        // Handle facility selection
        $('#facility_name').on('change', function() {
            const facility_name = $(this).val();
            const selectedOption = $(this).find('option:selected');
            const facilityId = selectedOption.data('id');
            const mflCode = selectedOption.data('mfl');

            if (!facility_name) {
                $('#facilityInfo').hide();
                $('#facility_id').val('');
                $('#mflcode').val('');
                $('#county').val('');
                $('#subcounty').val('');
                $('#levelofcare').val('');
                $('#ownership').val('');
                return;
            }

            // Set facility ID and MFL code from data attributes
            $('#facility_id').val(facilityId || '');
            $('#mflcode').val(mflCode || '');

            // Show loading
            $('#displayfacility_name').text('Loading...');
            $('#displayMFLCode').text('Loading...');
            $('#displayCounty').text('Loading...');
            $('#displaySubcounty').text('Loading...');
            $('#displayLevelofcare').text('Loading...');
            $('#displayOwnership').text('Loading...');
            $('#facilityInfo').show();

            // Fetch facility details
            $.ajax({
                url: 'fetch_facility.php',
                method: 'POST',
                data: { facility_name: facility_name },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.facility) {
                        const facility = response.facility;

                        // Update display
                        $('#displayfacility_name').text(facility.facility_name || 'N/A');
                        $('#displayMFLCode').text(facility.mflcode || 'N/A');
                        $('#displayCounty').text(facility.countyname || 'N/A');
                        $('#displaySubcounty').text(facility.subcountyname || 'N/A');
                        $('#displayLevelofcare').text(facility.level_of_care || 'N/A');
                        $('#displayOwnership').text(facility.owner || 'N/A');

                        // Update hidden fields
                        $('#county').val(facility.countyname || '');
                        $('#subcounty').val(facility.subcountyname || '');

                        // Update facility_id if not already set
                        if (!facilityId && facility.facility_id) {
                            $('#facility_id').val(facility.facility_id);
                        }

                        // Update mflcode if not already set
                        if (!mflCode && facility.mflcode) {
                            $('#mflcode').val(facility.mflcode);
                        }
                    } else {
                        alert('Error loading facility details: ' + (response.message || 'Unknown error'));
                        $('#facility_name').val('').trigger('change');
                        $('#facilityInfo').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error loading facility details. Please try again.');
                    $('#facility_name').val('').trigger('change');
                    $('#facilityInfo').hide();
                }
            });
        });

        // Handle course selection - update hidden course_name
        $('#course_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const courseName = selectedOption.data('name') || selectedOption.text();
            $('#course_name').val(courseName);
        });

        // Handle duration selection - update hidden duration_name
        $('#duration_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const durationName = selectedOption.data('name') || selectedOption.text();
            $('#duration_name').val(durationName);
        });

        // Handle location selection - update hidden location_name
        $('#location_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const locationName = selectedOption.data('name') || selectedOption.text();
            $('#location_name').val(locationName);
        });

        // Handle cadre selection - update hidden cadre_name
        $('#cadre_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const cadreName = selectedOption.data('name') || selectedOption.text();
            $('#cadre_name').val(cadreName);
        });

        // Handle facilitator level selection - update hidden facilitator_level
        $('#fac_level_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const levelName = selectedOption.data('name') || selectedOption.text();
            $('#facilitator_level').val(levelName);
        });

        // Form validation
        $('#trainingForm').on('submit', function(e) {
            // Validate facility selection
            if (!$('#facility_id').val()) {
                e.preventDefault();
                alert('Please select a facility first');
                $('#facility_name').select2('open');
                return false;
            }

            // Validate phone number format
            const phone = $('#staff_phone').val();
            const phoneRegex = /^0[0-9]{9}$/;
            if (phone && !phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number starting with 0');
                $('#staff_phone').focus();
                return false;
            }

            // Validate training date is not in the future
            const trainingDate = new Date($('#training_date').val());
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            trainingDate.setHours(0, 0, 0, 0);
            if (trainingDate > today) {
                e.preventDefault();
                alert('Training date cannot be in the future');
                $('#training_date').focus();
                return false;
            }

            return true;
        });

        // Set max date for training date to today
        $('#training_date').attr('max', new Date().toISOString().split('T')[0]);

        // Phone number input formatting
        $('#staff_phone').on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            if (value.length > 0 && value[0] !== '0') {
                value = '0' + value;
            }
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            $(this).val(value);
        });
    });
    </script>
</body>
</html>