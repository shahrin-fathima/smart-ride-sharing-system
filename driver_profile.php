<?php
session_start();
require_once 'db/connection.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['user_role'] !== 'driver') { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$driver = $stmt->fetch();
if (!$driver) { session_destroy(); header('Location: login.php'); exit; }

$status = $driver['driver_status'] ?? 'unverified';
$success_msg = '';
$errors = [];

// ── Handle profile update ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $v_model  = trim($_POST['vehicle_model']  ?? '');
    $v_number = trim($_POST['vehicle_number'] ?? '');
    $v_color  = trim($_POST['vehicle_color']  ?? '');

    if (!$name)  $errors[] = 'Full name is required.';
    if (!$phone) $errors[] = 'Phone number is required.';

    if (empty($errors)) {
        // Add vehicle columns if they don't exist yet (safe to run multiple times)
        try {
            $pdo->exec("ALTER TABLE users
                ADD COLUMN IF NOT EXISTS vehicle_model  VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS vehicle_number VARCHAR(30)  DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS vehicle_color  VARCHAR(50)  DEFAULT NULL
            ");
        } catch (PDOException $e) { /* columns already exist */ }

        $pdo->prepare('UPDATE users SET name=?, phone=?, vehicle_model=?, vehicle_number=?, vehicle_color=? WHERE id=?')
            ->execute([$name, $phone, $v_model, $v_number, $v_color, $user_id]);

        $_SESSION['user_name'] = $name;
        $success_msg = 'Profile updated successfully!';
        $stmt->execute([$user_id]);
        $driver = $stmt->fetch();
    }
}

// ── Handle document upload ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_docs') {
    $upload_dir = 'uploads/driver_docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $allowed_img = ['image/jpeg', 'image/png', 'image/webp'];
    $allowed_doc = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $max = 5 * 1024 * 1024;

    $new_licence = $driver['licence_path'] ?? '';
    $new_vehicle = $driver['vehicle_photo_path'] ?? '';

    // Licence
    if (!empty($_FILES['licence']['name']) && $_FILES['licence']['error'] === 0) {
        if (!in_array($_FILES['licence']['type'], $allowed_doc)) {
            $errors[] = 'Licence must be JPG, PNG, WebP or PDF.';
        } elseif ($_FILES['licence']['size'] > $max) {
            $errors[] = 'Licence file must be under 5 MB.';
        } else {
            $ext = pathinfo($_FILES['licence']['name'], PATHINFO_EXTENSION);
            $fname = 'licence_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['licence']['tmp_name'], $upload_dir . $fname);
            $new_licence = $upload_dir . $fname;
        }
    }

    // Vehicle photo
    if (!empty($_FILES['vehicle_photo']['name']) && $_FILES['vehicle_photo']['error'] === 0) {
        if (!in_array($_FILES['vehicle_photo']['type'], $allowed_img)) {
            $errors[] = 'Vehicle photo must be JPG, PNG or WebP.';
        } elseif ($_FILES['vehicle_photo']['size'] > $max) {
            $errors[] = 'Vehicle photo must be under 5 MB.';
        } else {
            $ext = pathinfo($_FILES['vehicle_photo']['name'], PATHINFO_EXTENSION);
            $fname = 'vehicle_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['vehicle_photo']['tmp_name'], $upload_dir . $fname);
            $new_vehicle = $upload_dir . $fname;
        }
    }

    if (empty($errors)) {
        // If both docs present, change status to pending
        $new_status = ($new_licence && $new_vehicle && in_array($status, ['unverified', 'rejected']))
            ? 'pending' : $status;

        $pdo->prepare('UPDATE users SET licence_path=?, vehicle_photo_path=?, driver_status=? WHERE id=?')
            ->execute([$new_licence, $new_vehicle, $new_status, $user_id]);

        $status = $new_status;
        $success_msg = $new_status === 'pending'
            ? 'Documents submitted! Admin will review within 24 hours.'
            : 'Documents updated.';
        $stmt->execute([$user_id]);
        $driver = $stmt->fetch();
    }
}

// Initials
$initials = strtoupper(substr($driver['name'], 0, 1));
if (strpos($driver['name'], ' ') !== false)
    $initials .= strtoupper(substr(strrchr($driver['name'], ' '), 1, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Profile — RideShare LK Driver Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --bg:#0D1117;--s1:#161B22;--s2:#1C2330;--s3:#21293A;
  --b1:#2A3444;--b2:#334155;
  --t1:#E6EDF3;--t2:#8B949E;--t3:#6E7681;
  --green:#3FB950;--gdim:#162312;
  --amber:#E3B341;--adim:#1F1700;
  --red:#F85149;--rdim:#200D0D;
  --blue:#58A6FF;--bdim:#0D1F3C;
  --acc:#FF6B35;--acc2:#FF8C5A;
  --r:10px;--rl:14px;
}
html{-webkit-font-smoothing:antialiased}
body{background:var(--bg);color:var(--t1);font-family:'DM Sans',sans-serif;font-size:14px;min-height:100vh}
a{text-decoration:none;color:inherit}

/* LAYOUT */
.shell{display:flex;min-height:100vh}
.sidebar{width:224px;background:var(--s1);border-right:1px solid var(--b1);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto}
.main{flex:1;margin-left:224px;display:flex;flex-direction:column;min-height:100vh}

/* SIDEBAR */
.logo{padding:20px 20px 16px;border-bottom:1px solid var(--b1);display:flex;align-items:center;gap:10px}
.logo-icon{width:34px;height:34px;background:var(--acc);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
.logo-text{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;letter-spacing:.3px}
.logo-sub{font-size:11px;color:var(--t3);margin-top:1px}
.sidenav{padding:10px;flex:1}
.nav-group{font-size:10px;font-weight:600;color:var(--t3);letter-spacing:1.2px;text-transform:uppercase;padding:14px 10px 5px}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;cursor:pointer;color:var(--t2);transition:all .15s;font-size:13px;border:1px solid transparent;margin-bottom:2px}
.nav-item:hover{background:var(--s2);color:var(--t1)}
.nav-item.active{background:linear-gradient(135deg,rgba(255,107,53,.18),rgba(255,107,53,.06));color:var(--acc);border-color:rgba(255,107,53,.25)}
.nav-item i{font-size:16px;width:18px;text-align:center;flex-shrink:0}
.driver-info{margin:10px;padding:14px;background:var(--s2);border-radius:var(--r);border:1px solid var(--b1)}
.drv-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.drv-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--acc),var(--acc2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;color:#fff;flex-shrink:0}
.drv-name{font-size:13px;font-weight:500}
.drv-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;margin-top:3px}
.drv-badge.approved{background:rgba(63,185,80,.15);color:var(--green);border:1px solid rgba(63,185,80,.3)}
.drv-badge.pending{background:rgba(227,179,65,.15);color:var(--amber);border:1px solid rgba(227,179,65,.3)}
.drv-badge.unverified,.drv-badge.rejected{background:rgba(248,81,73,.12);color:var(--red);border:1px solid rgba(248,81,73,.25)}
.drv-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
.drv-logout{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t3);cursor:pointer;transition:color .15s;padding-top:10px;border-top:1px solid var(--b1)}
.drv-logout:hover{color:var(--red)}

/* TOPBAR */
.topbar{background:var(--s1);border-bottom:1px solid var(--b1);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Syne',sans-serif;font-weight:600;font-size:16px}

/* CONTENT */
.content{flex:1;padding:28px 32px;max-width:1100px;width:100%}

/* ALERTS */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:500;border:1px solid}
.alert-g{background:var(--gdim);color:var(--green);border-color:rgba(63,185,80,.3)}
.alert-r{background:var(--rdim);color:var(--red);border-color:rgba(248,81,73,.3)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s;font-family:'DM Sans',sans-serif}
.btn-acc{background:var(--acc);color:#fff}
.btn-acc:hover{background:var(--acc2);transform:translateY(-1px)}
.btn-ghost{background:var(--s2);color:var(--t2);border:1px solid var(--b1)}
.btn-ghost:hover{color:var(--t1);border-color:var(--b2)}
.btn-blue{background:var(--bdim);color:var(--blue);border:1px solid rgba(88,166,255,.25)}
.btn-blue:hover{background:var(--blue);color:#000}

/* PAGE HEADER */
.page-header{margin-bottom:26px}
.page-header h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;margin-bottom:4px}
.page-header p{font-size:13px;color:var(--t3)}

/* SECTION */
.section{margin-bottom:28px}
.section-head{margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--b1);display:flex;align-items:center;justify-content:space-between}
.section-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--t1);display:flex;align-items:center;gap:8px}
.section-title i{font-size:16px;color:var(--acc)}
.section-sub{font-size:12px;color:var(--t3);margin-top:2px}

/* PROFILE HEADER CARD */
.profile-hero{background:var(--s1);border:1px solid var(--b1);border-radius:var(--rl);padding:24px;display:flex;align-items:center;gap:20px;margin-bottom:24px;position:relative;overflow:hidden}
.profile-hero::before{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(255,107,53,.1),transparent 70%);pointer-events:none}
.hero-av{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--acc),var(--acc2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 0 0 4px rgba(255,107,53,.2)}
.hero-name{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:4px}
.hero-email{font-size:13px;color:var(--t3);margin-bottom:8px}
.hero-badges{display:flex;gap:8px;flex-wrap:wrap}
.hbadge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px}
.hb-status.approved{background:rgba(63,185,80,.15);color:var(--green);border:1px solid rgba(63,185,80,.3)}
.hb-status.pending{background:rgba(227,179,65,.15);color:var(--amber);border:1px solid rgba(227,179,65,.3)}
.hb-status.unverified,.hb-status.rejected{background:rgba(248,81,73,.12);color:var(--red);border:1px solid rgba(248,81,73,.25)}
.hb-member{background:var(--s2);color:var(--t2);border:1px solid var(--b1)}

/* TWO COL GRID */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:var(--s1);border:1px solid var(--b1);border-radius:var(--rl);padding:22px}

/* FORM */
.frow{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.frow.single{grid-template-columns:1fr}
.fg{display:flex;flex-direction:column;gap:6px}
.fg label{font-size:12px;font-weight:500;color:var(--t2);display:flex;align-items:center;gap:5px}
.fg label .req{color:var(--acc);font-size:11px}
.fg input,.fg select{width:100%;background:var(--s2);border:1.5px solid var(--b1);border-radius:8px;padding:10px 14px;color:var(--t1);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:all .15s}
.fg input:focus,.fg select:focus{border-color:var(--acc);background:var(--s3);box-shadow:0 0 0 3px rgba(255,107,53,.1)}
.fg input::placeholder{color:var(--t3)}
.fg .hint{font-size:11px;color:var(--t3);margin-top:1px}
.fg input.error-field{border-color:var(--red)}

/* INPUT WITH ICON */
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--t3);pointer-events:none}
.input-wrap input{padding-left:36px}

/* FILE UPLOAD ZONE */
.doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.doc-zone{border:2px dashed var(--b2);border-radius:var(--rl);padding:20px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:var(--s2)}
.doc-zone:hover{border-color:var(--acc);background:rgba(255,107,53,.04)}
.doc-zone.has-file{border-color:var(--green);border-style:solid;background:rgba(63,185,80,.04)}
.doc-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.doc-zone-icon{width:44px;height:44px;border-radius:10px;background:var(--s3);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:20px;color:var(--t2);transition:all .2s}
.doc-zone:hover .doc-zone-icon,.doc-zone.has-file .doc-zone-icon{background:rgba(255,107,53,.12);color:var(--acc)}
.doc-zone.has-file .doc-zone-icon{background:rgba(63,185,80,.12);color:var(--green)}
.doc-zone-title{font-size:13px;font-weight:600;color:var(--t1);margin-bottom:3px}
.doc-zone-sub{font-size:11px;color:var(--t3);line-height:1.5}
.doc-zone-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin-top:8px}
.dz-uploaded{background:rgba(63,185,80,.15);color:var(--green)}
.dz-empty{background:var(--s3);color:var(--t3)}
.dz-selected{background:rgba(255,107,53,.15);color:var(--acc)}
.doc-zone-filename{font-size:11px;color:var(--acc);font-weight:500;margin-top:6px;display:none;word-break:break-all}
.doc-view-link{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--blue);margin-top:6px}
.doc-view-link:hover{text-decoration:underline}

/* VERIFICATION STEPS */
.verify-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:20px}
.vstep{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--s2);border-radius:var(--r);border:1px solid var(--b1)}
.vstep-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.vstep-done .vstep-num{background:rgba(63,185,80,.15);color:var(--green);border:1px solid rgba(63,185,80,.3)}
.vstep-pending .vstep-num{background:rgba(227,179,65,.15);color:var(--amber);border:1px solid rgba(227,179,65,.3)}
.vstep-todo .vstep-num{background:var(--s3);color:var(--t3);border:1px solid var(--b2)}
.vstep-text{font-size:13px;font-weight:500}
.vstep-sub{font-size:11px;color:var(--t3);margin-top:1px}

/* STATUS PILL */
.pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px}
.pill-a{background:rgba(63,185,80,.15);color:var(--green)}
.pill-p{background:rgba(227,179,65,.15);color:var(--amber)}
.pill-r{background:rgba(248,81,73,.15);color:var(--red)}
.pill-u{background:var(--s2);color:var(--t3);border:1px solid var(--b2)}

/* INFO ROW */
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--s2);border-radius:8px;border:1px solid var(--b1);margin-bottom:8px}
.info-row:last-child{margin-bottom:0}
.ir-l{font-size:12px;color:var(--t3)}
.ir-r{font-size:13px;font-weight:500}

/* DIVIDER */
.divider{height:1px;background:var(--b1);margin:20px 0}

/* FORM FOOTER */
.form-footer{display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid var(--b1);margin-top:4px}
.form-footer-right{display:flex;gap:10px}

.scrollbar{scrollbar-width:thin;scrollbar-color:var(--b1) transparent}

@media(max-width:900px){
  .sidebar{display:none}.main{margin-left:0}
  .two-col,.frow,.doc-grid{grid-template-columns:1fr}
  .content{padding:20px 16px}
}
</style>
</head>
<body>
<div class="shell">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <div class="logo-icon"><i class="ti ti-car"></i></div>
    <div><div class="logo-text">RideShare LK</div><div class="logo-sub">Driver Portal</div></div>
  </div>
  <nav class="sidenav">
    <div class="nav-group">Main</div>
    <a href="driver_dashboard.php" class="nav-item"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
    <a href="driver_dashboard.php?page=rides" class="nav-item"><i class="ti ti-route"></i> My Rides</a>
    <a href="driver_dashboard.php?page=bookings" class="nav-item"><i class="ti ti-ticket"></i> Bookings</a>
    <div class="nav-group">Finance</div>
    <a href="driver_dashboard.php?page=earnings" class="nav-item"><i class="ti ti-wallet"></i> Earnings</a>
    <div class="nav-group">Account</div>
    <div class="nav-item active"><i class="ti ti-user-circle"></i> Profile</div>
    <a href="driver_dashboard.php?page=support" class="nav-item"><i class="ti ti-headset"></i> Support</a>
    <a href="index.php" class="nav-item"><i class="ti ti-home"></i> Public site</a>
  </nav>
  <div class="driver-info">
    <div class="drv-top">
      <div class="drv-av"><?= $initials ?></div>
      <div>
        <div class="drv-name"><?= htmlspecialchars($driver['name']) ?></div>
        <div class="drv-badge <?= $status ?>">
          <span class="drv-dot"></span>
          <?= match($status) { 'approved' => 'Verified Driver', 'pending' => 'Pending Review', 'rejected' => 'Action Required', default => 'Not Verified' } ?>
        </div>
      </div>
    </div>
    <a href="logout.php" class="drv-logout"><i class="ti ti-logout" style="font-size:14px"></i> Sign out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="topbar-title">Profile &amp; Verification</div>
  </div>

  <div class="content scrollbar">

    <?php if (!empty($errors)): ?>
      <div class="alert alert-r"><i class="ti ti-alert-circle"></i>
        <?= implode(' &nbsp;·&nbsp; ', array_map('htmlspecialchars', $errors)) ?>
      </div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
      <div class="alert alert-g"><i class="ti ti-circle-check"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <!-- Hero card -->
    <div class="profile-hero">
      <div class="hero-av"><?= $initials ?></div>
      <div>
        <div class="hero-name"><?= htmlspecialchars($driver['name']) ?></div>
        <div class="hero-email"><?= htmlspecialchars($driver['email']) ?></div>
        <div class="hero-badges">
          <span class="hbadge hb-status <?= $status ?>">
            <?= match($status) { 'approved' => '✓ Verified Driver', 'pending' => '⏳ Pending Review', 'rejected' => '✗ Documents Rejected', default => '● Not Verified' } ?>
          </span>
          <span class="hbadge hb-member"><i class="ti ti-calendar" style="font-size:11px"></i> Joined <?= date('M Y', strtotime($driver['created_at'])) ?></span>
          <?php if (!empty($driver['phone'])): ?>
            <span class="hbadge hb-member"><i class="ti ti-phone" style="font-size:11px"></i> <?= htmlspecialchars($driver['phone']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── SECTION 1: Personal Info + Vehicle Details ── -->
    <form method="POST">
      <input type="hidden" name="action" value="update_profile"/>
      <div class="section">
        <div class="section-head">
          <div>
            <div class="section-title"><i class="ti ti-user"></i> Personal Information</div>
            <div class="section-sub">Your name and contact details visible to passengers after booking confirmation</div>
          </div>
        </div>

        <div class="two-col">
          <!-- Personal info -->
          <div class="card">
            <div class="frow">
              <div class="fg">
                <label>Full Name <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="ti ti-user"></i>
                  <input type="text" name="name" placeholder="e.g. Saman Perera" required
                         value="<?= htmlspecialchars($driver['name'] ?? '') ?>"/>
                </div>
              </div>
              <div class="fg">
                <label>Phone Number <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="ti ti-phone"></i>
                  <input type="tel" name="phone" placeholder="07X XXX XXXX" required
                         value="<?= htmlspecialchars($driver['phone'] ?? '') ?>"/>
                </div>
              </div>
            </div>
            <div class="frow single">
              <div class="fg">
                <label>Email Address</label>
                <div class="input-wrap">
                  <i class="ti ti-mail"></i>
                  <input type="email" value="<?= htmlspecialchars($driver['email']) ?>" disabled
                         style="opacity:.5;cursor:not-allowed"/>
                </div>
                <span class="hint">Email cannot be changed. Contact support if needed.</span>
              </div>
            </div>
          </div>

          <!-- Vehicle details -->
          <div class="card">
            <div style="font-family:'Syne',sans-serif;font-size:13px;font-weight:600;color:var(--t2);margin-bottom:14px;display:flex;align-items:center;gap:6px">
              <i class="ti ti-car" style="color:var(--acc)"></i> Vehicle Details
            </div>
            <div class="frow">
              <div class="fg">
                <label>Vehicle Model</label>
                <div class="input-wrap">
                  <i class="ti ti-car"></i>
                  <input type="text" name="vehicle_model" placeholder="e.g. Toyota Prius"
                         value="<?= htmlspecialchars($driver['vehicle_model'] ?? '') ?>"/>
                </div>
              </div>
              <div class="fg">
                <label>Registration Number</label>
                <div class="input-wrap">
                  <i class="ti ti-license"></i>
                  <input type="text" name="vehicle_number" placeholder="e.g. CAB-1234"
                         value="<?= htmlspecialchars($driver['vehicle_number'] ?? '') ?>"/>
                </div>
              </div>
            </div>
            <div class="frow single">
              <div class="fg">
                <label>Vehicle Color</label>
                <div class="input-wrap">
                  <i class="ti ti-palette"></i>
                  <input type="text" name="vehicle_color" placeholder="e.g. Silver"
                         value="<?= htmlspecialchars($driver['vehicle_color'] ?? '') ?>"/>
                </div>
                <span class="hint">Helps passengers identify your vehicle at the pickup point.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-footer">
          <span style="font-size:12px;color:var(--t3)"><i class="ti ti-asterisk" style="font-size:10px;color:var(--acc)"></i> Required fields</span>
          <div class="form-footer-right">
            <button type="reset" class="btn btn-ghost"><i class="ti ti-refresh"></i> Reset</button>
            <button type="submit" class="btn btn-acc"><i class="ti ti-device-floppy"></i> Save Changes</button>
          </div>
        </div>
      </div>
    </form>

    <div class="divider"></div>

    <!-- ── SECTION 2: Document Upload ── -->
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_docs"/>
      <div class="section">
        <div class="section-head">
          <div>
            <div class="section-title"><i class="ti ti-shield-check"></i> Verification Documents</div>
            <div class="section-sub">Upload your driving licence and vehicle photo for admin verification</div>
          </div>
          <?php
          $pillClass = match($status) { 'approved' => 'pill-a', 'pending' => 'pill-p', 'rejected' => 'pill-r', default => 'pill-u' };
          $pillText  = match($status) { 'approved' => '✓ Verified', 'pending' => '⏳ Under Review', 'rejected' => '✗ Rejected', default => '● Not Submitted' };
          ?>
          <span class="pill <?= $pillClass ?>"><?= $pillText ?></span>
        </div>

        <!-- Verification progress steps -->
        <div class="verify-steps">
          <?php
          $step1 = $driver['licence_path'] && $driver['vehicle_photo_path'];
          $step2 = in_array($status, ['pending', 'approved']);
          $step3 = $status === 'approved';
          ?>
          <div class="vstep <?= $step1 ? 'vstep-done' : 'vstep-todo' ?>">
            <div class="vstep-num"><?= $step1 ? '✓' : '1' ?></div>
            <div>
              <div class="vstep-text">Upload your documents</div>
              <div class="vstep-sub">Driving licence + vehicle photo</div>
            </div>
          </div>
          <div class="vstep <?= $step2 ? ($step3 ? 'vstep-done' : 'vstep-pending') : 'vstep-todo' ?>">
            <div class="vstep-num"><?= $step3 ? '✓' : ($step2 ? '⏳' : '2') ?></div>
            <div>
              <div class="vstep-text">Admin review</div>
              <div class="vstep-sub"><?= $step2 ? 'Your documents are being reviewed' : 'Waiting for document submission' ?></div>
            </div>
          </div>
          <div class="vstep <?= $step3 ? 'vstep-done' : 'vstep-todo' ?>">
            <div class="vstep-num"><?= $step3 ? '✓' : '3' ?></div>
            <div>
              <div class="vstep-text">Approved — start posting rides</div>
              <div class="vstep-sub"><?= $step3 ? 'Your account is fully verified!' : 'Unlocked after admin approval' ?></div>
            </div>
          </div>
        </div>

        <?php if ($status === 'rejected'): ?>
          <div class="alert alert-r" style="margin-bottom:18px">
            <i class="ti ti-alert-triangle"></i>
            Your documents were rejected by admin. Please upload clearer, higher-quality photos and resubmit.
          </div>
        <?php endif; ?>

        <div class="doc-grid">
          <!-- Driving Licence -->
          <div>
            <div style="font-size:12px;font-weight:500;color:var(--t2);margin-bottom:8px;display:flex;align-items:center;gap:5px">
              <i class="ti ti-id" style="font-size:14px;color:var(--acc)"></i> Driving Licence
              <span style="color:var(--acc);font-size:11px">*</span>
            </div>
            <div class="doc-zone <?= $driver['licence_path'] ? 'has-file' : '' ?>" id="zone-licence">
              <input type="file" name="licence" accept=".jpg,.jpeg,.png,.webp,.pdf"
                     id="inp-licence" onchange="fileSelected(this,'zone-licence','fn-licence','status-licence')"/>
              <div class="doc-zone-icon"><i class="ti ti-id"></i></div>
              <div class="doc-zone-title">Driving Licence</div>
              <div class="doc-zone-sub">
                <?php if ($driver['licence_path']): ?>
                  Click to replace the current file
                <?php else: ?>
                  Click to upload or drag &amp; drop<br>JPG, PNG, WebP or PDF — max 5 MB
                <?php endif; ?>
              </div>
              <?php if ($driver['licence_path']): ?>
                <div class="doc-zone-status dz-uploaded" id="status-licence">✓ Uploaded</div>
                <a href="<?= htmlspecialchars($driver['licence_path']) ?>" target="_blank"
                   class="doc-view-link" onclick="event.stopPropagation()">
                  <i class="ti ti-external-link" style="font-size:11px"></i> View current file
                </a>
              <?php else: ?>
                <div class="doc-zone-status dz-empty" id="status-licence">● No file uploaded</div>
              <?php endif; ?>
              <div class="doc-zone-filename" id="fn-licence"></div>
            </div>
          </div>

          <!-- Vehicle Photo -->
          <div>
            <div style="font-size:12px;font-weight:500;color:var(--t2);margin-bottom:8px;display:flex;align-items:center;gap:5px">
              <i class="ti ti-car" style="font-size:14px;color:var(--acc)"></i> Vehicle Photo
              <span style="color:var(--acc);font-size:11px">*</span>
            </div>
            <div class="doc-zone <?= $driver['vehicle_photo_path'] ? 'has-file' : '' ?>" id="zone-vehicle">
              <input type="file" name="vehicle_photo" accept=".jpg,.jpeg,.png,.webp"
                     id="inp-vehicle" onchange="fileSelected(this,'zone-vehicle','fn-vehicle','status-vehicle')"/>
              <div class="doc-zone-icon"><i class="ti ti-car"></i></div>
              <div class="doc-zone-title">Vehicle Photo</div>
              <div class="doc-zone-sub">
                <?php if ($driver['vehicle_photo_path']): ?>
                  Click to replace the current photo
                <?php else: ?>
                  Number plate must be clearly visible<br>JPG, PNG or WebP — max 5 MB
                <?php endif; ?>
              </div>
              <?php if ($driver['vehicle_photo_path']): ?>
                <div class="doc-zone-status dz-uploaded" id="status-vehicle">✓ Uploaded</div>
                <a href="<?= htmlspecialchars($driver['vehicle_photo_path']) ?>" target="_blank"
                   class="doc-view-link" onclick="event.stopPropagation()">
                  <i class="ti ti-external-link" style="font-size:11px"></i> View current photo
                </a>
              <?php else: ?>
                <div class="doc-zone-status dz-empty" id="status-vehicle">● No photo uploaded</div>
              <?php endif; ?>
              <div class="doc-zone-filename" id="fn-vehicle"></div>
            </div>
          </div>
        </div>

        <!-- Tips box -->
        <div style="background:var(--s2);border:1px solid var(--b1);border-radius:var(--r);padding:14px 16px;margin-bottom:18px;font-size:12px;color:var(--t3);line-height:1.7">
          <strong style="color:var(--t2);display:flex;align-items:center;gap:5px;margin-bottom:6px">
            <i class="ti ti-bulb" style="color:var(--amber);font-size:14px"></i> Tips for faster approval
          </strong>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px">
            <span>📸 Use good lighting, no shadows</span>
            <span>🔍 All text must be clearly readable</span>
            <span>🚗 Number plate fully visible in vehicle photo</span>
            <span>📄 PDF is accepted for the licence scan</span>
          </div>
        </div>

        <div class="form-footer">
          <span style="font-size:12px;color:var(--t3)">
            <?php if ($status === 'approved'): ?>
              <i class="ti ti-shield-check" style="color:var(--green)"></i> Your account is verified. Resubmitting will require re-review.
            <?php elseif ($status === 'pending'): ?>
              <i class="ti ti-clock" style="color:var(--amber)"></i> Documents submitted — waiting for admin review.
            <?php else: ?>
              <i class="ti ti-info-circle" style="color:var(--acc)"></i> Submit both documents to begin verification.
            <?php endif; ?>
          </span>
          <div class="form-footer-right">
            <?php if ($status !== 'pending'): ?>
              <button type="submit" class="btn btn-acc">
                <i class="ti ti-upload"></i>
                <?= $status === 'approved' ? 'Resubmit Documents' : 'Submit for Verification' ?>
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" disabled style="opacity:.5;cursor:not-allowed">
                <i class="ti ti-clock"></i> Under Review
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </form>

    <!-- ── SECTION 3: Read-only account summary ── -->
    <div class="divider"></div>
    <div class="section">
      <div class="section-head">
        <div class="section-title"><i class="ti ti-info-circle"></i> Account Summary</div>
      </div>
      <div class="two-col">
        <div class="card">
          <div style="font-size:12px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Identity</div>
          <div class="info-row"><span class="ir-l">Driver ID</span><span class="ir-r" style="color:var(--t3);font-family:monospace">#<?= str_pad($driver['id'], 5, '0', STR_PAD_LEFT) ?></span></div>
          <div class="info-row"><span class="ir-l">Account role</span><span class="ir-r">Driver</span></div>
          <div class="info-row"><span class="ir-l">Registered</span><span class="ir-r"><?= date('d M Y', strtotime($driver['created_at'])) ?></span></div>
          <div class="info-row"><span class="ir-l">Suspended</span><span class="ir-r" style="color:<?= $driver['is_suspended'] ? 'var(--red)' : 'var(--green)' ?>"><?= $driver['is_suspended'] ? 'Yes — contact support' : 'No' ?></span></div>
        </div>
        <div class="card">
          <div style="font-size:12px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Vehicle Info</div>
          <div class="info-row"><span class="ir-l">Model</span><span class="ir-r"><?= htmlspecialchars($driver['vehicle_model'] ?? '—') ?></span></div>
          <div class="info-row"><span class="ir-l">Reg. number</span><span class="ir-r"><?= htmlspecialchars($driver['vehicle_number'] ?? '—') ?></span></div>
          <div class="info-row"><span class="ir-l">Color</span><span class="ir-r"><?= htmlspecialchars($driver['vehicle_color'] ?? '—') ?></span></div>
          <div class="info-row">
            <span class="ir-l">Licence doc</span>
            <span class="ir-r">
              <?php if ($driver['licence_path']): ?>
                <a href="<?= htmlspecialchars($driver['licence_path']) ?>" target="_blank"
                   style="color:var(--blue);font-size:12px;display:inline-flex;align-items:center;gap:4px">
                  <i class="ti ti-file" style="font-size:12px"></i> View file
                </a>
              <?php else: ?>
                <span style="color:var(--t3)">Not uploaded</span>
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</main>
</div>

<script>
function fileSelected(input, zoneId, fnId, statusId) {
  const zone   = document.getElementById(zoneId);
  const fn     = document.getElementById(fnId);
  const status = document.getElementById(statusId);

  if (input.files && input.files.length > 0) {
    const file = input.files[0];
    const sizeMB = (file.size / 1024 / 1024).toFixed(1);

    // Update zone style
    zone.classList.add('has-file');

    // Show filename
    fn.textContent = `${file.name} (${sizeMB} MB)`;
    fn.style.display = 'block';

    // Update status badge
    status.className = 'doc-zone-status dz-selected';
    status.textContent = '● New file selected';
  }
}
</script>
</body>
</html>