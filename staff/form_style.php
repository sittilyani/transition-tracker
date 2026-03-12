<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#f4f7fc; padding:20px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
.container { max-width:1100px; margin:0 auto; }

/* ── Back bar ── */
.back-bar {
    display:flex; align-items:center; gap:10px; margin-bottom:16px;
}
.back-bar a {
    display:inline-flex; align-items:center; gap:7px;
    background:#fff; color:#0D1A63; border:2px solid #e0e8f5;
    padding:7px 16px; border-radius:8px; font-size:13px; font-weight:600;
    text-decoration:none; transition:all .2s;
}
.back-bar a:hover { background:#0D1A63; color:#fff; border-color:#0D1A63; }

/* ── Page header ── */
.form-page-header {
    background:linear-gradient(135deg,#0D1A63 0%,#1a2a7a 100%);
    color:#fff; padding:22px 28px; border-radius:14px; margin-bottom:22px;
    display:flex; justify-content:space-between; align-items:center;
    box-shadow:0 8px 24px rgba(13,26,99,.25);
}
.fph-left { display:flex; align-items:center; gap:16px; }
.fph-left h1 { font-size:22px; font-weight:700; margin-bottom:4px; }
.fph-left p  { font-size:13px; opacity:.8; margin:0; }

/* ── Cards ── */
.form-card { background:#fff; border-radius:13px; box-shadow:0 2px 16px rgba(0,0,0,.07);
    margin-bottom:20px; overflow:hidden; }
.form-card-head {
    background:linear-gradient(90deg,#0D1A63,#1a3a8f);
    color:#fff; padding:12px 22px; font-size:14px; font-weight:700;
    display:flex; align-items:center; gap:9px;
}
.form-card-body { padding:22px 24px; }

/* ── Grid ── */
.fg { display:grid; gap:16px; }
.fg-2 { grid-template-columns:1fr 1fr; }
.fg-3 { grid-template-columns:1fr 1fr 1fr; }
.fg-4 { grid-template-columns:1fr 1fr 1fr 1fr; }
.full { grid-column:1/-1; }

/* ── Form controls ── */
.form-group label {
    display:block; font-size:11.5px; font-weight:700; color:#666;
    text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px;
}
.field-hint { font-size:10px; color:#aaa; font-weight:400; text-transform:none; letter-spacing:0; }
.form-group input,
.form-group select,
.form-group textarea {
    width:100%; padding:10px 13px; border:2px solid #e0e8f0; border-radius:8px;
    font-size:13.5px; font-family:inherit; color:#222; background:#fafcff;
    transition:border-color .2s, box-shadow .2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline:none; border-color:#0D1A63; background:#fff;
    box-shadow:0 0 0 3px rgba(13,26,99,.1);
}
.form-group textarea { resize:vertical; min-height:90px; }
.req { color:#dc3545; }

/* ── Section divider ── */
.section-sub {
    font-size:13px; font-weight:700; color:#0D1A63;
    border-bottom:2px solid #f0f4fb; padding-bottom:8px;
    margin:20px 0 14px; display:flex; align-items:center; gap:7px;
}

/* ── Alert ── */
.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13.5px;
    display:flex; align-items:center; gap:10px; }
.alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

/* ── Action bar ── */
.form-actions {
    background:#fff; border-radius:13px; padding:18px 24px;
    display:flex; justify-content:space-between; align-items:center;
    box-shadow:0 2px 16px rgba(0,0,0,.07); margin-bottom:30px;
}
.btn {
    padding:10px 22px; border:none; border-radius:9px; font-size:13.5px;
    font-weight:600; cursor:pointer; display:inline-flex; align-items:center;
    gap:8px; text-decoration:none; font-family:inherit; transition:all .22s;
}
.btn-primary   { background:#0D1A63; color:#fff; }
.btn-primary:hover   { background:#1a2a7a; transform:translateY(-1px); box-shadow:0 5px 16px rgba(13,26,99,.3); }
.btn-success   { background:#28a745; color:#fff; }
.btn-success:hover   { background:#218838; transform:translateY(-1px); }
.btn-secondary { background:#6c757d; color:#fff; }
.btn-secondary:hover { background:#5a6268; }
.btn-warning   { background:#ffc107; color:#212529; }
.btn-warning:hover   { background:#e0a800; }
.btn-danger    { background:#dc3545; color:#fff; }
.btn-danger:hover    { background:#c82333; }
.btn-outline   { background:#fff; color:#0D1A63; border:2px solid #0D1A63; }
.btn-outline:hover   { background:#f0f4fb; }

/* ── Responsive ── */
@media(max-width:900px) { .fg-3,.fg-4 { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) { .fg-2,.fg-3,.fg-4 { grid-template-columns:1fr; } }
</style>
