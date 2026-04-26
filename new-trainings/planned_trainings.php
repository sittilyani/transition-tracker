<?php
session_start();
include '../includes/config.php';
include '../includes/session_check.php';

if (!isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit();
}

// ── Handle status change ──
if (isset($_POST['change_status'])) {
    $tid    = (int)$_POST['training_id'];
    $status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $allowed = ['planned','active','completed','cancelled'];
    if (in_array($status, $allowed)) {
        mysqli_query($conn, "UPDATE planned_trainings SET status='$status' WHERE training_id=$tid");
    }
    header("Location: planned_trainings.php?msg=Status+updated");
    exit();
}

// ── Handle delete ──
if (isset($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM planned_trainings WHERE training_id=$tid");
    header("Location: planned_trainings.php?msg=Training+deleted");
    exit();
}

// ── Filters ──
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search        = isset($_GET['q'])      ? mysqli_real_escape_string($conn, $_GET['q'])      : '';

$where = "WHERE 1=1";
if ($status_filter) $where .= " AND pt.status = '$status_filter'";
if ($search)        $where .= " AND (c.course_name LIKE '%$search%' OR pt.training_code LIKE '%$search%' OR pt.facilitator_name LIKE '%$search%')";

// ── Fetch trainings ──
$sql = "
    SELECT pt.*,
           c.course_name,
           tt.trainingtype_name,
           tl.location_name,
           co.county_name,
           sc.sub_county_name,
           COUNT(tr.registration_id) AS registered_count
    FROM planned_trainings pt
    LEFT JOIN courses            c  ON pt.course_id      = c.course_id
    LEFT JOIN trainingtypes      tt ON pt.trainingtype_id = tt.trainingtype_id
    LEFT JOIN training_locations tl ON pt.location_id    = tl.location_id
    LEFT JOIN counties           co ON pt.county_id      = co.county_id
    LEFT JOIN sub_counties       sc ON pt.subcounty_id   = sc.sub_county_id
    LEFT JOIN training_registrations tr ON pt.training_id = tr.training_id
    $where
    GROUP BY pt.training_id
    ORDER BY pt.start_date DESC
";
$trainings = mysqli_query($conn, $sql);

// ── Summary counts ──
$counts = [];
foreach (['planned','active','completed','cancelled'] as $s) {
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM planned_trainings WHERE status='$s'"));
    $counts[$s] = $r['n'] ?? 0;
}

// Build base URL for participant form
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');

$flash = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planned Trainings — Vuqa</title>
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
            --info:    #3b82f6;
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
            background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);
            color:#fff; padding:22px 32px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
        }
        .page-header h1 { font-size:1.4rem; font-weight:700; display:flex; align-items:center; gap:10px; }
        .btn {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border:none; border-radius:8px;
            font-size:.875rem; font-weight:600; cursor:pointer;
            text-decoration:none; transition:all .2s;
        }
        .btn-light   { background:#fff; color:var(--navy); }
        .btn-success { background:var(--success); color:#fff; }
        .btn-success:hover { opacity:.9; transform:translateY(-1px); }
        .btn-primary { background:var(--info); color:#fff; }
        .btn-danger  { background:var(--danger); color:#fff; }
        .btn-sm      { padding:5px 12px; font-size:.78rem; }

        /* ── BODY ── */
        .page-body { max-width:1300px; margin:0 auto; padding:28px 20px; }

        /* ── FLASH ── */
        .flash {
            padding:12px 18px; border-radius:var(--radius);
            background:#ecfdf5; color:#065f46;
            border-left:4px solid var(--success);
            margin-bottom:20px; font-weight:500;
            display:flex; align-items:center; gap:10px;
        }

        /* ── STAT CARDS ── */
        .stats-row {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:16px; margin-bottom:28px;
        }
        .stat-card {
            background:var(--card); border-radius:var(--radius);
            padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.06);
            display:flex; align-items:center; gap:14px;
            border-left:4px solid transparent; text-decoration:none; color:inherit;
            transition:transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
        .stat-card.planned   { border-color: var(--info); }
        .stat-card.active    { border-color: var(--success); }
        .stat-card.completed { border-color: var(--navy); }
        .stat-card.cancelled { border-color: var(--danger); }
        .stat-icon {
            width:44px; height:44px; border-radius:10px;
            display:flex; align-items:center; justify-content:center; font-size:1.1rem;
        }
        .stat-card.planned   .stat-icon { background:#eff6ff; color:var(--info); }
        .stat-card.active    .stat-icon { background:#ecfdf5; color:var(--success); }
        .stat-card.completed .stat-icon { background:#f0f4ff; color:var(--navy); }
        .stat-card.cancelled .stat-icon { background:#fef2f2; color:var(--danger); }
        .stat-val  { font-size:1.8rem; font-weight:800; line-height:1; }
        .stat-label{ font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }

        /* ── FILTERS ── */
        .filters-bar {
            background:var(--card); border-radius:var(--radius);
            padding:16px 20px; margin-bottom:20px;
            display:flex; gap:12px; align-items:center; flex-wrap:wrap;
            box-shadow:0 2px 8px rgba(0,0,0,.05);
        }
        .filter-input {
            padding:9px 14px; border:1.5px solid var(--border);
            border-radius:9px; font-size:.875rem; outline:none; color:var(--text);
        }
        .filter-input:focus { border-color:var(--navy); }
        .search-wrap { position:relative; flex:1; min-width:200px; }
        .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); }
        .search-wrap input { padding-left:36px; width:100%; }

        /* ── TABLE ── */
        .table-card {
            background:var(--card); border-radius:var(--radius);
            box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden;
        }
        .table-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        th {
            background:var(--navy); color:#fff;
            padding:13px 16px; text-align:left;
            font-size:.75rem; text-transform:uppercase; letter-spacing:.5px;
            white-space:nowrap;
        }
        td { padding:13px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#f8faff; }

        .badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700;
        }
        .badge-planned   { background:#eff6ff; color:var(--info); }
        .badge-active    { background:#ecfdf5; color:var(--success); }
        .badge-completed { background:#f0f4ff; color:var(--navy); }
        .badge-cancelled { background:#fef2f2; color:var(--danger); }

        .action-btns { display:flex; gap:6px; flex-wrap:wrap; }

        .empty-state {
            padding:60px 20px; text-align:center; color:var(--muted);
        }
        .empty-state i { font-size:3rem; margin-bottom:16px; opacity:.3; }
        .empty-state p { font-size:.95rem; }

        /* ── QR MODAL ── */
        .modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.55); z-index:1000;
            align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.show { display:flex; }
        .modal-box {
            background:#fff; border-radius:20px; padding:36px;
            max-width:460px; width:100%;
            box-shadow:0 24px 80px rgba(0,0,0,.25);
            animation:slideUp .3s ease; text-align:center; position:relative;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
        .modal-close {
            position:absolute; top:16px; right:18px;
            background:none; border:none; font-size:1.3rem;
            color:var(--muted); cursor:pointer;
        }
        .modal-close:hover { color:var(--danger); }
        .qr-badge { display:inline-block; background:#f0f4ff; color:var(--navy); border-radius:6px; padding:4px 10px; font-size:.78rem; font-weight:700; margin-bottom:12px; }
        .qr-title { font-size:1.15rem; font-weight:700; color:var(--navy); margin-bottom:4px; }
        .qr-sub   { font-size:.8rem; color:var(--muted); margin-bottom:20px; }
        #modal-qr-canvas { margin:0 auto 16px; }
        .qr-url-box {
            background:var(--surface); border:1.5px solid var(--border);
            border-radius:9px; padding:9px 12px; font-size:.75rem; color:var(--muted);
            word-break:break-all; margin-bottom:16px; text-align:left;
            display:flex; align-items:center; gap:8px;
        }
        .qr-url-box span { flex:1; }
        .copy-btn { padding:5px 10px; background:var(--navy); color:#fff; border:none; border-radius:6px; font-size:.7rem; cursor:pointer; white-space:nowrap; }
        .modal-actions { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }

        /* ── STATUS FORM ── */
        .status-form { display:inline; }
        .status-form select {
            padding:4px 8px; border:1.5px solid var(--border);
            border-radius:6px; font-size:.75rem; cursor:pointer;
        }

        /* ── PARTICIPANTS COUNT ── */
        .reg-count {
            display:inline-flex; align-items:center; gap:4px;
            font-weight:700; color:var(--navy);
        }

        @media(max-width:600px){
            .page-header { padding:16px; }
            .page-body   { padding:16px 10px; }
            table { font-size:.78rem; }
            th, td { padding:10px 10px; }
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1><i class="fas fa-calendar-alt"></i> Planned Trainings</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="create_training.php" class="btn btn-success">
            <i class="fas fa-plus"></i> New Training
        </a>
        <a href="dashboard.php" class="btn btn-light">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</header>

<div class="page-body">

    <?php if ($flash): ?>
    <div class="flash"><i class="fas fa-check-circle"></i> <?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- ── STAT CARDS ── -->
    <div class="stats-row">
        <a href="?status=planned" class="stat-card planned">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div><div class="stat-val"><?php echo $counts['planned']; ?></div><div class="stat-label">Planned</div></div>
        </a>
        <a href="?status=active" class="stat-card active">
            <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
            <div><div class="stat-val"><?php echo $counts['active']; ?></div><div class="stat-label">Active</div></div>
        </a>
        <a href="?status=completed" class="stat-card completed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-val"><?php echo $counts['completed']; ?></div><div class="stat-label">Completed</div></div>
        </a>
        <a href="?status=cancelled" class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-ban"></i></div>
            <div><div class="stat-val"><?php echo $counts['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
        </a>
        <a href="planned_trainings.php" class="stat-card" style="border-color:var(--muted);">
            <div class="stat-icon" style="background:#f1f5f9;color:var(--muted);"><i class="fas fa-list"></i></div>
            <div><div class="stat-val"><?php echo array_sum($counts); ?></div><div class="stat-label">All</div></div>
        </a>
    </div>

    <!-- ── FILTERS ── -->
    <form method="GET" class="filters-bar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="q" class="filter-input" placeholder="Search code, course, facilitator…" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <select name="status" class="filter-input" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach (['planned','active','completed','cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status_filter===$s ? 'selected' : ''; ?>>
                    <?php echo ucfirst($s); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="planned_trainings.php" class="btn btn-sm" style="background:var(--surface);border:1.5px solid var(--border);color:var(--muted);">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>

    <!-- ── TABLE ── -->
    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Course</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>County</th>
                        <th>Dates</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                if (mysqli_num_rows($trainings) === 0):
                ?>
                    <tr><td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No trainings found. <a href="create_training.php">Create one now.</a></p>
                        </div>
                    </td></tr>
                <?php else: while ($t = mysqli_fetch_assoc($trainings)): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        
                        <td><strong><?php echo htmlspecialchars($t['training_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($t['course_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['trainingtype_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['location_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['county_name'] ?? '—'); ?></td>
                        <td>
                            <small>
                                <?php echo date('d M Y', strtotime($t['start_date'])); ?> –<br>
                                <?php echo date('d M Y', strtotime($t['end_date'])); ?>
                            </small>
                        </td>
                        <td>
                            <span class="reg-count">
                                <i class="fas fa-users" style="font-size:.7rem;opacity:.6;"></i>
                                <?php echo (int)$t['registered_count']; ?>
                                <span style="font-weight:400;color:var(--muted);font-size:.75rem;">/ <?php echo (int)$t['max_participants']; ?></span>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="training_id" value="<?php echo $t['training_id']; ?>">
                                <input type="hidden" name="change_status" value="1">
                                <select name="new_status" onchange="this.form.submit()">
                                    <?php foreach (['planned','active','completed','cancelled'] as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $t['status']===$s ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($s); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-primary btn-sm"
                                    onclick="showQR(
                                        '<?php echo htmlspecialchars($t['training_code'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($t['course_name'] ?? '', ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($base_url . '/new-trainings/participant_form.php?token=' . $t['qr_token'], ENT_QUOTES); ?>'
                                    )">
                                    <i class="fas fa-qrcode"></i> QR
                                </button>
                                <a href="view_registrations.php?training_id=<?php echo $t['training_id']; ?>" class="btn btn-sm" style="background:#f0f4ff;color:var(--navy);">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="create_training.php?id=<?php echo $t['training_id']; ?>" class="btn btn-sm" style="background:#fff7ed;color:#b45309;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_registrations.php?training_id=<?php echo $t['training_id']; ?>" class="btn btn-sm" style="background:#f0f4ff;color:var(--navy);">
                                    <i class="fas fa-users"></i> View
                                </a>
                                <a href="?delete=<?php echo $t['training_id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this training and all registrations? This cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── QR MODAL ── -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        <div class="qr-badge" id="modal-code"></div>
        <div class="qr-title" id="modal-course"></div>
        <div class="qr-sub">Participants scan this QR to self-register</div>
        <div id="modal-qr-canvas"></div>
        <div class="qr-url-box">
            <span id="modal-url-text"></span>
            <button class="copy-btn" onclick="copyModalUrl()"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div class="modal-actions">
            <button class="btn btn-sm btn-light" onclick="printModalQR()"><i class="fas fa-print"></i> Print</button>
            <button class="btn btn-sm btn-primary" onclick="downloadModalQR()"><i class="fas fa-download"></i> Download</button>
            <button class="btn btn-sm" style="background:var(--surface);border:1.5px solid var(--border);" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
let currentQR    = null;
let currentUrl   = '';
let currentCode  = '';

function showQR(code, course, url) {
    currentUrl  = url;
    currentCode = code;

    document.getElementById('modal-code').textContent   = code;
    document.getElementById('modal-course').textContent = course || 'Training Event';
    document.getElementById('modal-url-text').textContent = url;

    // Clear previous
    const canvas = document.getElementById('modal-qr-canvas');
    canvas.innerHTML = '';
    currentQR = null;

    currentQR = new QRCode(canvas, {
        text:         url,
        width:        200,
        height:       200,
        colorDark:    "#0D1A63",
        colorLight:   "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    document.getElementById('qrModal').classList.add('show');
}

function closeModal() {
    document.getElementById('qrModal').classList.remove('show');
}

// Close on overlay click
document.getElementById('qrModal').addEventListener('click', function(e){
    if (e.target === this) closeModal();
});

function copyModalUrl() {
    navigator.clipboard.writeText(currentUrl).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 2000);
    });
}

function getCanvas() {
    return document.getElementById('modal-qr-canvas').querySelector('canvas');
}

function downloadModalQR() {
    const c = getCanvas();
    if (!c) { alert('QR not ready'); return; }
    const a  = document.createElement('a');
    a.download = currentCode + '_QR.png';
    a.href     = c.toDataURL('image/png');
    a.click();
}

function printModalQR() {
    const c = getCanvas();
    if (!c) { alert('QR not ready'); return; }
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>QR — ${currentCode}</title>
    <style>body{font-family:sans-serif;text-align:center;padding:40px;}
    h2{color:#0D1A63;margin-bottom:4px;}p{color:#555;font-size:.85rem;margin-bottom:18px;}
    .url{font-size:.65rem;color:#888;word-break:break-all;margin-top:12px;}</style></head>
    <body><h2>${currentCode}</h2><p>Scan to register for this training</p>
    <img src="${c.toDataURL()}" width="200" height="200">
    <p class="url">${currentUrl}</p></body></html>`);
    w.document.close(); w.focus(); w.print();
}
</script>
</body>
</html>
