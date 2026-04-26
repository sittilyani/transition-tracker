<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

if (!isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit();
}

$msg = $error = "";
$training_id = $_GET['id'] ?? 0;
$qr_token    = "";
$training_code = "";
$data = [];

// ── FETCH TRAINING FOR EDIT ──
if ($training_id) {
    $stmt = $conn->prepare("SELECT * FROM planned_trainings WHERE id=?");
    $stmt->bind_param("i", $training_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data) {
        $qr_token = $data['qr_token'];
        $training_code = $data['training_code'];
    }
}

// ── Fetch dropdown data ──
$courses            = mysqli_query($conn, "SELECT * FROM courses ORDER BY course_name");
$durations          = mysqli_query($conn, "SELECT * FROM course_durations ORDER BY duration_name");
$trainingtypes      = mysqli_query($conn, "SELECT * FROM trainingtypes ORDER BY trainingtype_name");
$locations          = mysqli_query($conn, "SELECT * FROM training_locations ORDER BY location_name");
$facilitator_levels = mysqli_query($conn, "SELECT * FROM facilitator_levels ORDER BY facilitator_level_name");
$counties           = mysqli_query($conn, "SELECT * FROM counties ORDER BY county_name");

// ── HANDLE POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $training_id       = $_POST['training_id'] ?? 0;
    $course_id         = (int)$_POST['course_id'];
    $duration_id       = (int)$_POST['duration_id'];
    $trainingtype_id   = (int)$_POST['trainingtype_id'];
    $location_id       = (int)$_POST['location_id'];
    $fac_level_id      = (int)$_POST['fac_level_id'];
    $county_id         = (int)$_POST['county_id'];
    $subcounty_id      = (int)$_POST['subcounty_id'];
    $start_date        = $_POST['start_date'];
    $end_date          = $_POST['end_date'];
    $training_objectives = $_POST['training_objectives'];
    $materials_provided  = $_POST['materials_provided'];
    $facilitator_name  = $_POST['facilitator_name'];
    $venue_details     = $_POST['venue_details'];
    $max_participants  = (int)$_POST['max_participants'];

    if (!$course_id || !$trainingtype_id || !$location_id || !$county_id || !$subcounty_id || !$start_date || !$end_date) {
        $error = "Please fill in all required fields.";
    } elseif ($end_date < $start_date) {
        $error = "End date must be after start date.";
    } else {

        if ($training_id) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE planned_trainings SET
                course_id=?, duration_id=?, trainingtype_id=?, location_id=?, fac_level_id=?,
                county_id=?, subcounty_id=?, venue_details=?, facilitator_name=?,
                start_date=?, end_date=?, training_objectives=?, materials_provided=?, max_participants=?
                WHERE id=?");

            $stmt->bind_param("iiiiiiissssssii",
                $course_id, $duration_id, $trainingtype_id, $location_id, $fac_level_id,
                $county_id, $subcounty_id, $venue_details, $facilitator_name,
                $start_date, $end_date, $training_objectives, $materials_provided,
                $max_participants, $training_id
            );

            $stmt->execute();
            $msg = "Training updated successfully.";

        } else {
            // CREATE
            $training_code = 'TRN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $qr_token      = bin2hex(random_bytes(16));
            $created_by    = $_SESSION['full_name'];

            $stmt = $conn->prepare("INSERT INTO planned_trainings (
                training_code, qr_token, course_id, duration_id, trainingtype_id,
                location_id, fac_level_id, county_id, subcounty_id,
                venue_details, facilitator_name, start_date, end_date,
                training_objectives, materials_provided, max_participants,
                status, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'planned', ?)");

            $stmt->bind_param("ssiiiiiiissssssis",
                $training_code, $qr_token, $course_id, $duration_id, $trainingtype_id,
                $location_id, $fac_level_id, $county_id, $subcounty_id,
                $venue_details, $facilitator_name, $start_date, $end_date,
                $training_objectives, $materials_provided, $max_participants,
                $created_by
            );

            $stmt->execute();
            $training_id = $conn->insert_id;
            $msg = "Training created successfully.";
        }
    }
}

// CORRECT PUBLIC URL (FIXED)
$form_url = "https://the-touch-haven-investments.store/transition/participant_form.php?token=" . $qr_token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Training Event — Vuqa</title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --navy:    #0D1A63;
            --navy2:   #162180;
            --accent:  #00C2FF;
            --success: #10b981;
            --danger:  #ef4444;
            --warn:    #f59e0b;
            --surface: #f4f7fc;
            --card:    #ffffff;
            --border:  #e2e8f0;
            --text:    #1e293b;
            --muted:   #64748b;
            --radius:  12px;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--surface); font-family:'Segoe UI',system-ui,sans-serif; color:var(--text); }

        /* ── HEADER ── */
        .page-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            color:#fff;
            padding:24px 32px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
        }
        .page-header h1 { font-size:1.5rem; font-weight:700; display:flex; align-items:center; gap:10px; }
        .header-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border:none; border-radius:8px;
            font-size:.875rem; font-weight:600; cursor:pointer;
            text-decoration:none; transition:all .2s;
        }
        .btn-light   { background:#fff; color:var(--navy); }
        .btn-light:hover { background:#f0f4ff; }
        .btn-primary { background:var(--accent); color:#fff; }
        .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
        .btn-success { background:var(--success); color:#fff; }
        .btn-success:hover { opacity:.9; transform:translateY(-1px); }
        .btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--text); }
        .btn-outline:hover { border-color:var(--navy); color:var(--navy); }

        /* ── LAYOUT ── */
        .page-body { max-width:960px; margin:0 auto; padding:32px 20px; }

        /* ── ALERTS ── */
        .alert {
            padding:14px 18px; border-radius:var(--radius); margin-bottom:24px;
            display:flex; align-items:center; gap:10px; font-weight:500;
        }
        .alert-success { background:#ecfdf5; color:#065f46; border-left:4px solid var(--success); }
        .alert-error   { background:#fef2f2; color:#991b1b; border-left:4px solid var(--danger); }

        /* ── CARDS ── */
        .card {
            background:var(--card); border-radius:var(--radius);
            box-shadow:0 2px 12px rgba(0,0,0,.07);
            margin-bottom:24px; overflow:hidden;
        }
        .card-header {
            background:linear-gradient(90deg,var(--navy),var(--navy2));
            color:#fff; padding:16px 24px;
            display:flex; align-items:center; gap:10px;
            font-weight:600; font-size:1rem;
        }
        .card-body { padding:28px 24px; }

        /* ── FORM GRID ── */
        .form-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
            gap:20px;
        }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group.full { grid-column:1/-1; }
        .form-group label {
            font-size:.8rem; font-weight:600; text-transform:uppercase;
            letter-spacing:.5px; color:var(--muted);
        }
        .form-group label .req { color:var(--danger); margin-left:2px; }
        .form-control {
            padding:10px 14px; border:1.5px solid var(--border);
            border-radius:9px; font-size:.9rem; color:var(--text);
            background:#fff; transition:border-color .2s, box-shadow .2s;
            outline:none; width:100%;
        }
        .form-control:focus { border-color:var(--navy); box-shadow:0 0 0 3px rgba(13,26,99,.1); }
        select.form-control { cursor:pointer; }
        textarea.form-control { resize:vertical; min-height:90px; }

        /* ── STEP INDICATOR ── */
        .steps {
            display:flex; gap:0; margin-bottom:32px; overflow:hidden;
            border-radius:var(--radius); border:1.5px solid var(--border);
        }
        .step {
            flex:1; padding:14px 12px; text-align:center; font-size:.8rem;
            font-weight:600; display:flex; align-items:center; justify-content:center;
            gap:8px; color:var(--muted); background:#fff; cursor:default;
            border-right:1.5px solid var(--border); transition:all .3s;
        }
        .step:last-child { border-right:none; }
        .step.active { background:var(--navy); color:#fff; }
        .step.done   { background:#ecfdf5; color:var(--success); }
        .step-num {
            width:22px; height:22px; border-radius:50%;
            background:rgba(255,255,255,.2); font-size:.75rem;
            display:inline-flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        .step.active .step-num { background:rgba(255,255,255,.3); }
        .step.done .step-num   { background:var(--success); color:#fff; }

        /* ── FORM ACTIONS ── */
        .form-actions {
            display:flex; gap:12px; justify-content:flex-end;
            padding-top:24px; border-top:1.5px solid var(--border); margin-top:8px;
            flex-wrap:wrap;
        }

        /* ── QR MODAL ── */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
            z-index:1000; align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.show { display:flex; }
        .modal-box {
            background:#fff; border-radius:20px; padding:36px;
            max-width:520px; width:100%;
            box-shadow:0 24px 80px rgba(0,0,0,.25);
            animation:slideUp .3s ease;
            text-align:center;
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        .modal-icon {
            width:64px; height:64px; border-radius:50%;
            background:linear-gradient(135deg,var(--navy),var(--accent));
            display:inline-flex; align-items:center; justify-content:center;
            font-size:1.6rem; color:#fff; margin-bottom:16px;
        }
        .modal-title { font-size:1.3rem; font-weight:700; color:var(--navy); margin-bottom:6px; }
        .modal-sub   { font-size:.875rem; color:var(--muted); margin-bottom:24px; }
        #qr-canvas   { margin:0 auto 20px; display:block; }
        .qr-url-box {
            background:var(--surface); border:1.5px solid var(--border);
            border-radius:9px; padding:10px 14px; font-size:.8rem; color:var(--muted);
            word-break:break-all; margin-bottom:20px; text-align:left;
            display:flex; align-items:center; gap:10px;
        }
        .qr-url-box span { flex:1; }
        .copy-btn {
            padding:6px 12px; background:var(--navy); color:#fff;
            border:none; border-radius:7px; font-size:.75rem; cursor:pointer;
            white-space:nowrap;
        }
        .modal-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
        .training-badge {
            display:inline-block; background:#f0f4ff; color:var(--navy);
            border-radius:6px; padding:4px 10px; font-size:.78rem; font-weight:700;
            letter-spacing:.5px; margin-bottom:16px;
        }

        @media(max-width:600px){
            .page-header { padding:18px 16px; }
            .page-body   { padding:20px 12px; }
            .card-body   { padding:20px 16px; }
            .form-grid   { grid-template-columns:1fr; }
            .steps       { font-size:.7rem; }
            .step        { padding:10px 6px; gap:4px; }
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1><i class="fas fa-calendar-plus"></i> Create Training Event</h1>
    <div class="header-actions">
        <a href="planned_trainings.php" class="btn btn-light">
            <i class="fas fa-list"></i> Planned Trainings
        </a>
        <a href="dashboard.php" class="btn btn-light">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</header>

<div class="page-body">

    <!-- Step indicator -->
    <div class="steps">
        <div class="step <?php echo !$training_id ? 'active' : 'done'; ?>">
            <span class="step-num"><?php echo $training_id ? '✓' : '1'; ?></span>
            <span class="d-sm-inline">Training Details</span>
        </div>
        <div class="step <?php echo $training_id ? 'active' : ''; ?>">
            <span class="step-num">2</span>
            <span>QR Code</span>
        </div>
        <div class="step">
            <span class="step-num">3</span>
            <span>Participants Register</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($msg && !$training_id): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="POST" id="trainingForm">
        <input type="hidden" name="training_id" value="<?php echo $training_id; ?>">

        <!-- ── Section 1: Training Info ── -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-book-open"></i> Training Information
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="form-group">
                        <label>Course / Training Topic <span class="req">*</span></label>
                        <select name="course_id" class="form-control" required>
                            <option value="<?php echo $r['course_id']; ?>"
                            <?php if(($data['course_id'] ?? '') == $r['course_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($r['course_name']); ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Training Type <span class="req">*</span></label>
                        <select name="trainingtype_id" class="form-control" required>
                            <option value="">Select Type</option>
                            <?php while ($r = mysqli_fetch_assoc($trainingtypes)): ?>
                                <option value="<?php echo $r['trainingtype_id']; ?>"><?php echo htmlspecialchars($r['trainingtype_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Duration</label>
                        <select name="duration_id" class="form-control">
                            <option value="">Select Duration</option>
                            <?php while ($r = mysqli_fetch_assoc($durations)): ?>
                                <option value="<?php echo $r['duration_id']; ?>"><?php echo htmlspecialchars($r['duration_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Facilitator Level</label>
                        <select name="fac_level_id" class="form-control">
                            <option value="">Select Level</option>
                            <?php while ($r = mysqli_fetch_assoc($facilitator_levels)): ?>
                                <option value="<?php echo $r['fac_level_id']; ?>"><?php echo htmlspecialchars($r['facilitator_level_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Facilitator Name</label>
                        <input type="text" name="facilitator_name"
                        value="<?php echo htmlspecialchars($data['facilitator_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Max Participants</label>
                        <input type="number" name="max_participants" class="form-control" value="50" min="1" max="500">
                    </div>

                    <div class="form-group">
                        <label>Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $data['start_date'] ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>End Date <span class="req">*</span></label>
                        <input type="date" name="end_date" id="end_date" class="form-control"  value="<?php echo $data['start_date'] ?? ''; ?>" required>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Section 2: Location ── -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-map-marker-alt"></i> Location
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="form-group">
                        <label>Training Venue / Location <span class="req">*</span></label>
                        <select name="location_id" class="form-control" required>
                            <option value="">Select Venue</option>
                            <?php while ($r = mysqli_fetch_assoc($locations)): ?>
                                <option value="<?php echo $r['location_id']; ?>"><?php echo htmlspecialchars($r['location_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Specific Room / Hall / Address</label>
                        <input type="text" name="venue_details" class="form-control" placeholder="e.g. Conference Room A, 2nd Floor">
                    </div>

                    <div class="form-group">
                        <label>County <span class="req">*</span></label>
                        <select name="county_id" id="county_sel" class="form-control" required>
                            <option value="">Select County</option>
                            <?php while ($r = mysqli_fetch_assoc($counties)): ?>
                                <option value="<?php echo $r['county_id']; ?>"><?php echo htmlspecialchars($r['county_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subcounty <span class="req">*</span></label>
                        <select name="subcounty_id" id="subcounty_sel" class="form-control" required>
                            <option value="">Select Subcounty</option>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Section 3: Objectives ── -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bullseye"></i> Training Objectives & Materials
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Training Objectives</label>
                        <textarea name="training_objectives" class="form-control" placeholder="Describe what participants will learn / achieve…"><?php echo $data['training_objectives'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Materials / Resources Provided</label>
                        <textarea name="materials_provided" class="form-control" placeholder="List handouts, manuals, kits that will be given…"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Actions ── -->
        <div class="form-actions">
            <a href="planned_trainings.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-qrcode"></i> Save & Generate QR Code
            </button>
        </div>

    </form>
</div><!-- /page-body -->

<!-- ── QR SUCCESS MODAL ── -->
<?php if ($training_id && $qr_token): ?>
<div class="modal-overlay show" id="qrModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-qrcode"></i></div>
        <div class="training-badge"><?php echo htmlspecialchars($training_code); ?></div>
        <div class="modal-title">Training Event Created!</div>
        <div class="modal-sub">Share the QR code below or copy the link. Participants scan it to self-register.</div>

        <div id="qr-canvas"></div>

        <div class="qr-url-box">
            <span id="qr-url-text"><?php echo htmlspecialchars($form_url); ?></span>
            <button class="copy-btn" onclick="copyUrl()"><i class="fas fa-copy"></i> Copy</button>
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="printQR()">
                <i class="fas fa-print"></i> Print QR
            </button>
            <button class="btn btn-light" onclick="downloadQR()">
                <i class="fas fa-download"></i> Download PNG
            </button>
            <a href="planned_trainings.php" class="btn btn-success">
                <i class="fas fa-list"></i> View All Trainings
            </a>
        </div>
    </div>
</div>

<script>
// Generate QR code into the modal
const formUrl  = <?php echo json_encode($form_url); ?>;
const qrCanvas = document.getElementById('qr-canvas');
const qr = new QRCode(qrCanvas, {
    text:          formUrl,
    width:         200,
    height:        200,
    colorDark:     "#0D1A63",
    colorLight:    "#ffffff",
    correctLevel:  QRCode.CorrectLevel.H
});

function copyUrl() {
    navigator.clipboard.writeText(formUrl).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 2000);
    });
}

function downloadQR() {
    const canvas = qrCanvas.querySelector('canvas');
    if (!canvas) { alert('QR not ready yet'); return; }
    const link   = document.createElement('a');
    link.download = <?php echo json_encode($training_code . '_QR.png'); ?>;
    link.href    = canvas.toDataURL('image/png');
    link.click();
}

function printQR() {
    const canvas = qrCanvas.querySelector('canvas');
    if (!canvas) { alert('QR not ready yet'); return; }
    const win = window.open('', '_blank');
    win.document.write(`
        <html><head><title>QR Code — <?php echo htmlspecialchars($training_code); ?></title>
        <style>
            body { font-family:sans-serif; text-align:center; padding:40px; }
            h2   { color:#0D1A63; font-size:1.4rem; margin-bottom:4px; }
            p    { color:#555; font-size:.85rem; margin-bottom:20px; }
            img  { display:block; margin:0 auto 16px; }
            .url { font-size:.7rem; color:#888; word-break:break-all; }
        </style></head><body>
        <h2><?php echo htmlspecialchars($training_code); ?></h2>
        <p>Scan to register for this training</p>
        <img src="${canvas.toDataURL('image/png')}" width="220" height="220">
        <p class="url"><?php echo htmlspecialchars($form_url); ?></p>
        </body></html>`);
    win.document.close();
    win.focus();
    win.print();
}
</script>
<?php endif; ?>

<script>
$(document).ready(function(){
    // Subcounty AJAX loader
    $('#county_sel').change(function(){
        const id = $(this).val();
        $('#subcounty_sel').html('<option value="">Loading…</option>');
        if (!id) { $('#subcounty_sel').html('<option value="">Select Subcounty</option>'); return; }
        $.post('get_subcounties.php', {county_id: id}, function(data){
            let opts = '<option value="">Select Subcounty</option>';
            $.each(data, function(i, r){ opts += `<option value="${r.sub_county_id}">${r.sub_county_name}</option>`; });
            $('#subcounty_sel').html(opts);
        }, 'json').fail(function(){
            $('#subcounty_sel').html('<option value="">Error loading</option>');
        });
    });

    // Date validation
    $('#end_date').change(function(){
        const s = $('#start_date').val(), e = $(this).val();
        if (s && e && e < s) {
            alert('End date cannot be before start date.');
            $(this).val('');
        }
    });
});
</script>
</body>
</html>
