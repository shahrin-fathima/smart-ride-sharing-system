<?php
session_start();
require_once 'db/connection.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['user_role'] !== 'driver') { header('Location: dashboard.php'); exit; }

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$driver = $stmt->fetch();

if (!$driver) { session_destroy(); header('Location: login.php'); exit; }

$status = $driver['driver_status'] ?? 'unverified';

$upload_error   = '';
$upload_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_docs') {
    $upload_dir = 'uploads/driver_docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max     = 5 * 1024 * 1024;

    $licence_ok   = false;
    $vehicle_ok   = false;
    $licence_path = '';
    $vehicle_path = '';

    if (!empty($_FILES['licence']['name'])) {
        $f = $_FILES['licence'];
        if (!in_array($f['type'], $allowed)) { $upload_error = 'Licence file must be JPG, PNG, WebP or PDF.'; }
        elseif ($f['size'] > $max)           { $upload_error = 'Licence file must be under 5 MB.'; }
        else {
            $ext   = pathinfo($f['name'], PATHINFO_EXTENSION);
            $fname = 'licence_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $upload_dir . $fname);
            $licence_path = $upload_dir . $fname;
            $licence_ok   = true;
        }
    } else { $upload_error = 'Please upload your driving licence.'; }

    if (!$upload_error) {
        if (!empty($_FILES['vehicle_photo']['name'])) {
            $f = $_FILES['vehicle_photo'];
            if (!in_array($f['type'], $allowed)) { $upload_error = 'Vehicle photo must be JPG, PNG, or WebP.'; }
            elseif ($f['size'] > $max)            { $upload_error = 'Vehicle photo must be under 5 MB.'; }
            else {
                $ext   = pathinfo($f['name'], PATHINFO_EXTENSION);
                $fname = 'vehicle_' . $user_id . '_' . time() . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $upload_dir . $fname);
                $vehicle_path = $upload_dir . $fname;
                $vehicle_ok   = true;
            }
        } else { $upload_error = 'Please upload a photo of your vehicle.'; }
    }

    if (!$upload_error && $licence_ok && $vehicle_ok) {
        $upd = $pdo->prepare('UPDATE users SET driver_status = "pending", licence_path = ?, vehicle_photo_path = ? WHERE id = ?');
        $upd->execute([$licence_path, $vehicle_path, $user_id]);
        $status         = 'pending';
        $upload_success = 'Documents submitted! Admin will review within 24 hours.';
        $stmt->execute([$user_id]);
        $driver = $stmt->fetch();
    }
}

$rides = [];
if ($status === 'approved') {
    $rs = $pdo->prepare('
        SELECT r.*,
               (SELECT COUNT(*) FROM ride_requests rq WHERE rq.ride_id = r.id AND rq.status = "pending")  AS pending_requests,
               (SELECT COUNT(*) FROM ride_requests rq WHERE rq.ride_id = r.id AND rq.status = "accepted") AS accepted_requests
        FROM rides r WHERE r.driver_id = ?
        ORDER BY r.ride_date DESC, r.ride_time DESC LIMIT 10
    ');
    $rs->execute([$user_id]);
    $rides = $rs->fetchAll();
}

function initials(string $n): string {
    $p = explode(' ', $n);
    $i = strtoupper(substr($p[0], 0, 1));
    if (count($p) > 1) $i .= strtoupper(substr($p[1], 0, 1));
    return $i;
}

// Counts for stat cards
$totalRides    = count($rides);
$pendingCount  = array_sum(array_column($rides, 'pending_requests'));
$confirmedCount= array_sum(array_column($rides, 'accepted_requests'));
$totalSeats    = array_sum(array_column($rides, 'seats_available'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Dashboard — RideShare LK</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — matches index.php exactly
═══════════════════════════════════════ */
:root {
  --ink:         #0A0A0F;
  --ink-soft:    #111118;
  --surface:     #16161F;
  --surface-2:   #1E1E2A;
  --surface-3:   #252533;
  --border:      rgba(255,255,255,0.07);
  --text:        #F0EFF8;
  --text-2:      #A09EC0;
  --text-3:      #5E5C7A;
  --accent:      #7050FF;
  --accent-2:    #5B8EFF;
  --accent-glow: rgba(112,80,255,0.15);
  --green:       #22C55E;
  --green-dim:   rgba(34,197,94,0.12);
  --amber:       #F59E0B;
  --amber-dim:   rgba(245,158,11,0.12);
  --red:         #EF4444;
  --red-dim:     rgba(239,68,68,0.12);
  --gradient:    linear-gradient(135deg,#7050FF 0%,#5B8EFF 100%);
  --glow:        0 0 40px rgba(112,80,255,0.20);
  --shadow:      0 4px 24px rgba(0,0,0,0.35);
  --shadow-lg:   0 16px 48px rgba(0,0,0,0.50);
  --r:           18px;
  --r-sm:        12px;
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }

body {
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--ink); color:var(--text);
  min-height:100vh; overflow-x:hidden;
  font-size:15px; line-height:1.6;
  display:flex; flex-direction:column;
}

/* DOT GRID */
body::before {
  content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
  background-image:radial-gradient(circle,rgba(112,80,255,0.22) 1.5px,transparent 1.5px);
  background-size:28px 28px; opacity:0.45;
}
body::after {
  content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
  background-image:
    radial-gradient(ellipse 70% 50% at 80% -10%,rgba(112,80,255,0.14) 0%,transparent 60%),
    radial-gradient(ellipse 50% 40% at 10% 80%, rgba(91,142,255,0.09)  0%,transparent 60%);
}

::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:var(--ink-soft); }
::-webkit-scrollbar-thumb { background:var(--accent); border-radius:99px; }

/* ── NAVBAR ── */
.navbar {
  position:sticky; top:0; z-index:500; height:64px;
  background:rgba(10,10,15,0.88);
  backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  padding:0 56px;
  animation:slideDown .5s ease both;
}
@keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }

.nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; font-weight:800; font-size:18px; color:var(--text); letter-spacing:-0.02em; }
.nav-logo  { width:34px; height:34px; background:var(--gradient); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 4px 14px rgba(112,80,255,0.38); transition:transform .3s; }
.nav-logo:hover { transform:rotate(-8deg) scale(1.1); }
.nav-brand-name { background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }

.nav-links { display:flex; align-items:center; gap:4px; }
.nav-link  { padding:7px 14px; font-size:13.5px; font-weight:500; color:var(--text-2); text-decoration:none; border-radius:99px; transition:all .2s; }
.nav-link:hover  { color:var(--text); background:var(--surface-2); }
.nav-link.active { color:var(--text); background:var(--surface-2); }
.nav-link-driver { color:#A78BFF!important; background:var(--accent-glow)!important; border:1px solid rgba(112,80,255,0.20); }

.nav-actions { display:flex; align-items:center; gap:10px; }

/* Profile block in nav */
.nav-profile {
  display:flex; align-items:center; gap:10px;
  padding:5px 14px 5px 5px;
  background:var(--surface-2); border:1px solid var(--border);
  border-radius:99px;
}
.nav-avatar {
  width:32px; height:32px; border-radius:50%;
  background:var(--gradient); border:2px solid rgba(112,80,255,0.28);
  display:flex; align-items:center; justify-content:center;
  color:white; font-weight:700; font-size:12.5px; flex-shrink:0;
}
.nav-name { font-size:13px; font-weight:600; color:var(--text-2); }
.nav-status-dot {
  width:7px; height:7px; border-radius:50%;
  background:var(--green); flex-shrink:0;
  animation:pulse 2s infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }

/* BUTTONS */
.btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:9px 20px; border-radius:99px; font-family:'Plus Jakarta Sans',sans-serif; font-size:13.5px; font-weight:600; cursor:pointer; transition:all .22s; border:none; text-decoration:none; }
.btn-primary { background:var(--gradient); color:white; box-shadow:0 4px 18px rgba(112,80,255,0.40); }
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(112,80,255,0.55); }
.btn-ghost   { background:var(--surface-2); color:var(--text-2); border:1px solid var(--border); }
.btn-ghost:hover { color:var(--text); background:var(--surface-3); }
.btn-outline { background:transparent; color:var(--text); border:1.5px solid var(--border); }
.btn-outline:hover { border-color:var(--red); color:var(--red); }
.btn-sm      { padding:7px 15px; font-size:12.5px; }

/* ── PAGE SHELL ── */
.page {
  flex:1; max-width:960px; width:100%;
  margin:0 auto; padding:40px 32px 72px;
  position:relative; z-index:1;
}

/* ── PAGE HEADER ── */
.page-header {
  margin-bottom:32px;
  animation:fadeUp .6s ease both;
}
.page-header-top { display:flex; align-items:center; gap:14px; margin-bottom:8px; }
.page-header-badge {
  display:inline-flex; align-items:center; gap:7px;
  background:var(--accent-glow); border:1px solid rgba(112,80,255,0.25);
  border-radius:99px; padding:5px 14px;
  font-size:11px; font-weight:700; color:#A78BFF;
  text-transform:uppercase; letter-spacing:.6px;
}
.page-header h1 { font-size:clamp(26px,4vw,36px); font-weight:800; letter-spacing:-0.025em; color:var(--text); }
.page-header h1 .grad { background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.page-header p { font-size:15px; color:var(--text-2); margin-top:4px; }

/* ── ALERTS ── */
.alert {
  display:flex; align-items:center; gap:12px;
  border-radius:var(--r-sm); padding:14px 18px;
  font-size:13.5px; font-weight:500;
  margin-bottom:22px;
  animation:shake .4s ease;
}
.alert-error   { background:var(--red-dim);   border:1px solid rgba(239,68,68,0.22);  color:var(--red);   }
.alert-success { background:var(--green-dim);  border:1px solid rgba(34,197,94,0.22);  color:var(--green); }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

/* ── STAT CARDS ── */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:26px; animation:fadeUp .7s ease both; }
.stat-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--r-sm); padding:20px 18px;
  box-shadow:var(--shadow); transition:all .28s cubic-bezier(.34,1.56,.64,1);
  position:relative; overflow:hidden;
}
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--gradient); opacity:0; transition:opacity .25s; }
.stat-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg),var(--glow); border-color:rgba(112,80,255,0.24); }
.stat-card:hover::before { opacity:1; }
.stat-icon { font-size:22px; margin-bottom:10px; }
.stat-val  { font-size:26px; font-weight:800; letter-spacing:-0.03em; background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1; margin-bottom:4px; }
.stat-lbl  { font-size:11px; color:var(--text-3); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

/* ── STATUS BANNERS ── */
.status-banner {
  display:flex; align-items:flex-start; gap:18px;
  padding:22px 24px; border-radius:var(--r);
  margin-bottom:24px; border:1px solid var(--border);
  background:var(--surface);
  animation:fadeUp .6s .1s ease both;
  position:relative; overflow:hidden;
}
.status-banner::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; }
.sb-unverified::before { background:var(--text-3); }
.sb-pending::before    { background:var(--amber); }
.sb-approved::before   { background:var(--green); }
.sb-rejected::before   { background:var(--red); }

.sb-pending  { background:rgba(245,158,11,0.06); border-color:rgba(245,158,11,0.18); }
.sb-approved { background:rgba(34,197,94,0.06);  border-color:rgba(34,197,94,0.18);  }
.sb-rejected { background:rgba(239,68,68,0.06);  border-color:rgba(239,68,68,0.18);  }

.sb-icon {
  width:48px; height:48px; border-radius:13px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:22px;
  background:var(--surface-2); border:1px solid var(--border);
}
.sb-pending  .sb-icon { background:var(--amber-dim); border-color:rgba(245,158,11,0.2); }
.sb-approved .sb-icon { background:var(--green-dim); border-color:rgba(34,197,94,0.2); }
.sb-rejected .sb-icon { background:var(--red-dim);   border-color:rgba(239,68,68,0.2); }

.sb-title {
  font-size:16px; font-weight:700; margin-bottom:4px;
}
.sb-unverified .sb-title { color:var(--text); }
.sb-pending    .sb-title { color:var(--amber); }
.sb-approved   .sb-title { color:var(--green); }
.sb-rejected   .sb-title { color:var(--red); }
.sb-desc { font-size:13.5px; color:var(--text-2); line-height:1.65; }

/* ── UPLOAD CARD ── */
.upload-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--r); padding:32px;
  margin-bottom:24px; box-shadow:var(--shadow);
  animation:fadeUp .7s .15s ease both;
  position:relative; overflow:hidden;
}
.upload-card::before {
  content:''; position:absolute; inset:-1px; border-radius:calc(var(--r)+1px);
  background:linear-gradient(135deg,rgba(112,80,255,0.22),transparent 52%);
  z-index:-1;
}
.upload-card-title { font-size:20px; font-weight:800; letter-spacing:-0.02em; margin-bottom:6px; }
.upload-card-sub   { font-size:13.5px; color:var(--text-2); margin-bottom:26px; line-height:1.65; }

.upload-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:22px; }
.upload-field label { display:block; font-size:10.5px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.8px; margin-bottom:9px; }

.file-drop {
  position:relative; border:2px dashed rgba(112,80,255,0.22);
  border-radius:var(--r-sm); padding:30px 20px;
  text-align:center; cursor:pointer;
  transition:all .25s; background:var(--surface-2);
}
.file-drop:hover { border-color:var(--accent); background:rgba(112,80,255,0.08); }
.file-drop input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; }
.file-drop-icon { font-size:28px; margin-bottom:9px; transition:transform .2s; display:block; }
.file-drop:hover .file-drop-icon { transform:scale(1.12) translateY(-2px); }
.file-drop-text { font-size:13px; color:var(--text-2); font-weight:500; }
.file-drop-text span { font-size:11px; color:var(--text-3); display:block; margin-top:4px; }
.file-drop-name {
  margin-top:10px; font-size:12px; color:#A78BFF; font-weight:600;
  display:none; word-break:break-all;
  background:var(--accent-glow); border:1px solid rgba(112,80,255,0.2);
  padding:4px 10px; border-radius:6px;
}

.btn-upload {
  width:100%; padding:14px; background:var(--gradient);
  color:white; border:none; border-radius:14px;
  font-family:'Plus Jakarta Sans',sans-serif; font-size:15px; font-weight:700;
  cursor:pointer; transition:all .22s;
  box-shadow:0 4px 18px rgba(112,80,255,0.38);
  position:relative; overflow:hidden;
}
.btn-upload::before { content:''; position:absolute; top:0; left:-120%; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.22),transparent); transition:.7s; }
.btn-upload:hover   { transform:translateY(-2px); box-shadow:0 8px 28px rgba(112,80,255,0.52); }
.btn-upload:hover::before { left:120%; }

/* ── POST RIDE BUTTON ── */
.btn-post {
  display:inline-flex; align-items:center; gap:10px;
  padding:14px 28px; background:var(--gradient);
  color:white; border-radius:14px;
  font-size:14.5px; font-weight:700;
  text-decoration:none; transition:all .22s;
  margin-bottom:22px;
  box-shadow:0 4px 18px rgba(112,80,255,0.38);
  position:relative; overflow:hidden;
  animation:fadeUp .6s ease both;
}
.btn-post::before { content:''; position:absolute; top:0; left:-120%; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.22),transparent); transition:.7s; }
.btn-post:hover   { transform:translateY(-2px); box-shadow:0 8px 28px rgba(112,80,255,0.52); }
.btn-post:hover::before { left:120%; }

/* ── RIDES CARD ── */
.rides-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--r); overflow:hidden;
  box-shadow:var(--shadow);
  animation:fadeUp .7s .1s ease both;
}
.rides-head {
  display:flex; align-items:center; justify-content:space-between;
  padding:20px 24px; border-bottom:1px solid var(--border);
  background:rgba(112,80,255,0.04);
}
.rides-head h3 { font-size:16px; font-weight:800; }
.rides-count {
  font-size:12px; font-weight:700; color:#A78BFF;
  background:var(--accent-glow); border:1px solid rgba(112,80,255,0.2);
  padding:4px 13px; border-radius:99px;
}

/* RIDE ROW */
.ride-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:20px 24px; border-bottom:1px solid var(--border);
  transition:all .22s;
  animation:rowIn .4s ease both;
}
.ride-row:last-child { border-bottom:none; }
.ride-row:hover { background:var(--surface-2); padding-left:28px; }
@keyframes rowIn { from{opacity:0;transform:translateX(-10px)} to{opacity:1;transform:translateX(0)} }

.rr-route { font-size:15px; font-weight:700; color:var(--text); margin-bottom:7px; }
.rr-meta  { font-size:13px; color:var(--text-2); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.rr-meta-dot { width:3px; height:3px; border-radius:50%; background:var(--text-3); flex-shrink:0; }

.rr-right { text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
.rr-price { font-size:18px; font-weight:800; background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }

/* BADGES */
.badge-stack { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
.badge       { font-size:11px; padding:3px 10px; border-radius:99px; font-weight:700; }
.badge-pending   { background:var(--amber-dim); color:var(--amber); border:1px solid rgba(245,158,11,0.22); }
.badge-confirmed { background:var(--green-dim); color:var(--green); border:1px solid rgba(34,197,94,0.22); }

/* EMPTY STATE */
.empty-rides {
  text-align:center; padding:56px 24px;
}
.empty-icon  { font-size:44px; margin-bottom:14px; }
.empty-title { font-size:18px; font-weight:700; margin-bottom:8px; }
.empty-sub   { font-size:14px; color:var(--text-2); margin-bottom:22px; }

/* ── FOOTER ── */
.footer { background:var(--ink-soft); border-top:1px solid var(--border); padding:48px 72px 28px; position:relative; z-index:1; }
.footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:48px; margin-bottom:44px; }
.footer-brand   { font-size:19px; font-weight:800; color:var(--text); margin-bottom:11px; }
.footer-desc    { font-size:13px; color:var(--text-3); line-height:1.7; margin-bottom:20px; }
.footer-heading { font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.9px; margin-bottom:14px; }
.footer-link    { display:block; font-size:13px; color:var(--text-3); margin-bottom:9px; text-decoration:none; transition:color .2s; }
.footer-link:hover { color:var(--text); }
.footer-link-accent { color:#A78BFF!important; font-weight:600; }
.footer-socials { display:flex; gap:8px; }
.social-btn { width:32px; height:32px; border-radius:8px; background:var(--surface); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:13px; cursor:pointer; color:var(--text-2); transition:all .2s; }
.social-btn:hover { background:var(--accent-glow); border-color:rgba(112,80,255,0.3); color:var(--text); }
.footer-bottom { border-top:1px solid var(--border); padding-top:20px; display:flex; justify-content:space-between; align-items:center; font-size:12px; color:var(--text-3); }

/* REVEAL */
.rs-reveal { opacity:0; transform:translateY(22px); transition:opacity .6s ease,transform .6s ease; }
.rs-reveal.visible { opacity:1; transform:translateY(0); }
.rs-reveal-d1 { transition-delay:.08s; }
.rs-reveal-d2 { transition-delay:.16s; }
.rs-reveal-d3 { transition-delay:.24s; }

@keyframes fadeUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }

/* RESPONSIVE */
@media(max-width:960px) { .navbar{padding:0 28px} .page{padding:32px 24px 60px} .stats-row{grid-template-columns:repeat(2,1fr)} .footer{padding:48px 40px 24px} }
@media(max-width:640px) { .navbar{padding:0 16px} .nav-links{display:none} .page{padding:28px 16px 48px} .stats-row{grid-template-columns:1fr 1fr} .upload-grid{grid-template-columns:1fr} .ride-row{flex-direction:column;align-items:flex-start;gap:14px} .rr-right{text-align:left;align-items:flex-start;width:100%;border-top:1px solid var(--border);padding-top:12px} .footer{padding:40px 18px 22px} .footer-grid{grid-template-columns:1fr} }
</style>
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="navbar">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">🚗</div>
    <span class="nav-brand-name">RideShare LK</span>
  </a>

  <div class="nav-links">
    <a href="index.php"   class="nav-link">Home</a>
    <a href="results.php" class="nav-link">Find Rides</a>
    <a href="dashboard.php" class="nav-link active">Dashboard</a>
    <a href="about.php"   class="nav-link">About</a>
    <a href="contact.php" class="nav-link">Contact</a>
  </div>

  <div class="nav-actions">
    <div class="nav-profile">
      <div class="nav-avatar"><?= initials($driver['name']) ?></div>
      <?php if ($status === 'approved'): ?>
        <div class="nav-status-dot"></div>
      <?php endif; ?>
      <span class="nav-name"><?= htmlspecialchars($driver['name']) ?></span>
    </div>
    <a href="logout.php" class="btn btn-outline btn-sm">Log out</a>
  </div>
</nav>

<!-- ═══ PAGE ═══ -->
<div class="page">

  <!-- ALERTS -->
  <?php if ($upload_error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($upload_error) ?></div><?php endif; ?>
  <?php if ($upload_success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($upload_success) ?></div><?php endif; ?>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-header-top">
      <div class="page-header-badge">🚗 Driver Panel</div>
    </div>
    <h1>Driver <span class="grad">Dashboard</span></h1>
    <p>Manage your transit route offers, credentials processing and active passenger matches.</p>
  </div>

  <!-- STAT CARDS (approved only) -->
  <?php if ($status === 'approved'): ?>
  <div class="stats-row">
    <div class="stat-card rs-reveal">
      <div class="stat-icon">🛣️</div>
      <div class="stat-val"><?= $totalRides ?></div>
      <div class="stat-lbl">Total rides posted</div>
    </div>
    <div class="stat-card rs-reveal rs-reveal-d1">
      <div class="stat-icon">✅</div>
      <div class="stat-val"><?= $confirmedCount ?></div>
      <div class="stat-lbl">Confirmed bookings</div>
    </div>
    <div class="stat-card rs-reveal rs-reveal-d2">
      <div class="stat-icon">⏳</div>
      <div class="stat-val"><?= $pendingCount ?></div>
      <div class="stat-lbl">Pending requests</div>
    </div>
    <div class="stat-card rs-reveal rs-reveal-d3">
      <div class="stat-icon">🪑</div>
      <div class="stat-val"><?= $totalSeats ?></div>
      <div class="stat-lbl">Seats available now</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- STATUS BANNERS -->
  <?php if ($status === 'unverified'): ?>
    <div class="status-banner sb-unverified">
      <div class="sb-icon">📋</div>
      <div>
        <div class="sb-title">Upload your documents to start</div>
        <div class="sb-desc">Please supply a scanned copy of your driving licence along with vehicle snapshots. Our safety audit team will authorize your profile within 24 hours.</div>
      </div>
    </div>

  <?php elseif ($status === 'pending'): ?>
    <div class="status-banner sb-pending">
      <div class="sb-icon">⏳</div>
      <div>
        <div class="sb-title">Verification in progress</div>
        <div class="sb-desc">Your uploaded registration documents are currently being reviewed by administrators. You will be cleared to list route seats very shortly.</div>
      </div>
    </div>

  <?php elseif ($status === 'approved'): ?>
    <div class="status-banner sb-approved">
      <div class="sb-icon">✨</div>
      <div>
        <div class="sb-title">Account Verified &amp; Active</div>
        <div class="sb-desc">Your operational parameters are approved! You are ready to open shared seating pools across Sri Lanka's road networks.</div>
      </div>
    </div>

  <?php elseif ($status === 'rejected'): ?>
    <div class="status-banner sb-rejected">
      <div class="sb-icon">❌</div>
      <div>
        <div class="sb-title">Document Authorization Failed</div>
        <div class="sb-desc">The submitted identification assets could not be verified by our team. Please check image clarity and upload valid copies again below.</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- UPLOAD CARD -->
  <?php if (in_array($status, ['unverified','rejected'])): ?>
  <div class="upload-card">
    <div class="upload-card-title">Upload your documents</div>
    <div class="upload-card-sub">Submit a clear photo of your driving licence and a photo showing your vehicle with the number plate visible. Accepted formats: JPG, PNG, WebP or PDF (max 5 MB each).</div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_docs">

      <div class="upload-grid">
        <div class="upload-field">
          <label>Driving licence scan</label>
          <div class="file-drop" id="drop-licence">
            <input type="file" name="licence" accept=".jpg,.jpeg,.png,.webp,.pdf" id="inp-licence">
            <span class="file-drop-icon">🪪</span>
            <div class="file-drop-text">Click to browse file<span>JPG, PNG, PDF — max 5 MB</span></div>
            <div class="file-drop-name" id="fn-licence"></div>
          </div>
        </div>
        <div class="upload-field">
          <label>Vehicle photo identity</label>
          <div class="file-drop" id="drop-vehicle">
            <input type="file" name="vehicle_photo" accept=".jpg,.jpeg,.png,.webp" id="inp-vehicle">
            <span class="file-drop-icon">🚗</span>
            <div class="file-drop-text">Click to browse file<span>JPG, PNG, WebP — max 5 MB</span></div>
            <div class="file-drop-name" id="fn-vehicle"></div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-upload">Submit for verification →</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- APPROVED: POST RIDE + RIDES TABLE -->
  <?php if ($status === 'approved'): ?>

    <a href="post_ride.php" class="btn-post">＋ Post a new ride</a>

    <div class="rides-card">
      <div class="rides-head">
        <h3>Your recent rides</h3>
        <span class="rides-count">
          <?= count($rides) ?> Ride<?= count($rides) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <?php if (empty($rides)): ?>
        <div class="empty-rides">
          <div class="empty-icon">🛣️</div>
          <div class="empty-title">No rides posted yet</div>
          <div class="empty-sub">Click "Post a new ride" above to open your first route pool.</div>
          <a href="post_ride.php" class="btn btn-primary">＋ Post a ride</a>
        </div>
      <?php else: ?>
        <?php foreach ($rides as $i => $r): ?>
        <div class="ride-row" style="animation-delay:<?= $i * 0.05 ?>s">
          <div>
            <div class="rr-route">
              <?= htmlspecialchars($r['start_location']) ?> → <?= htmlspecialchars($r['destination']) ?>
            </div>
            <div class="rr-meta">
              <span>📅 <?= date('D, d M Y', strtotime($r['ride_date'])) ?></span>
              <div class="rr-meta-dot"></div>
              <span>⏰ <?= date('g:i A', strtotime($r['ride_time'])) ?></span>
              <div class="rr-meta-dot"></div>
              <span>🪑 <?= $r['seats_available'] ?> seat<?= $r['seats_available'] != 1 ? 's' : '' ?> left</span>
            </div>
          </div>
          <div class="rr-right">
            <div class="rr-price">Rs. <?= number_format($r['price']) ?></div>
            <div class="badge-stack">
              <?php if ($r['pending_requests'] > 0): ?>
                <span class="badge badge-pending">⏳ <?= $r['pending_requests'] ?> pending</span>
              <?php endif; ?>
              <?php if ($r['accepted_requests'] > 0): ?>
                <span class="badge badge-confirmed">✅ <?= $r['accepted_requests'] ?> confirmed</span>
              <?php endif; ?>
              <?php if ($r['pending_requests'] == 0 && $r['accepted_requests'] == 0): ?>
                <span class="badge" style="background:var(--surface-3);color:var(--text-3);border:1px solid var(--border)">No requests</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</div>

<!-- ═══ FOOTER ═══ -->
<footer class="footer">
  <div class="footer-grid">
    <div>
      <div class="footer-brand">🚗 RideShare LK</div>
      <div class="footer-desc">Sri Lanka's trusted ride-sharing platform. Safe, affordable and sustainable travel connecting passengers and drivers island-wide.</div>
      <div class="footer-socials">
        <div class="social-btn">f</div>
        <div class="social-btn">𝕏</div>
        <div class="social-btn">in</div>
        <div class="social-btn">📷</div>
      </div>
    </div>
    <div>
      <div class="footer-heading">Explore</div>
      <a class="footer-link" href="results.php">Find Rides</a>
      <a class="footer-link footer-link-accent" href="post_ride.php">Post a Ride</a>
      <a class="footer-link" href="about.php">About Us</a>
    </div>
    <div>
      <div class="footer-heading">Account</div>
      <a class="footer-link" href="dashboard.php">My Dashboard</a>
      <a class="footer-link" href="logout.php">Log Out</a>
    </div>
    <div>
      <div class="footer-heading">Support</div>
      <a class="footer-link" href="contact.php">Contact Us</a>
      <a class="footer-link" href="about.php">About</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> RideShare LK · Made with ❤️ in Sri Lanka</span>
    <span>🌿 Eco-friendly travel</span>
  </div>
</footer>

<script>
/* File picker display */
function bindFilePicker(inputId, nameId) {
  const inp  = document.getElementById(inputId);
  const name = document.getElementById(nameId);
  if (!inp) return;
  inp.addEventListener('change', () => {
    if (inp.files.length) {
      name.textContent    = '📄 ' + inp.files[0].name;
      name.style.display  = 'inline-block';
      inp.parentElement.style.borderColor = 'var(--accent)';
      inp.parentElement.style.background  = 'rgba(112,80,255,0.08)';
    }
  });
}
bindFilePicker('inp-licence', 'fn-licence');
bindFilePicker('inp-vehicle', 'fn-vehicle');

/* Drag-over visual */
document.querySelectorAll('.file-drop').forEach(drop => {
  drop.addEventListener('dragover',  e => { e.preventDefault(); drop.style.borderColor='var(--accent)'; drop.style.background='rgba(112,80,255,0.08)'; });
  drop.addEventListener('dragleave', ()  => { drop.style.borderColor=''; drop.style.background=''; });
  drop.addEventListener('drop',      e  => { e.preventDefault(); drop.style.borderColor=''; drop.style.background=''; });
});

/* Scroll reveal */
const rsObs = new IntersectionObserver(entries => {
  entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('visible'); rsObs.unobserve(e.target); } });
}, { threshold:0.10 });
document.querySelectorAll('.rs-reveal').forEach(el => rsObs.observe(el));
</script>

</body>
</html>