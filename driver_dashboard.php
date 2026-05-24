<?php
/**
 * driver_dashboard.php — RideShare LK
 * ──────────────────────────────────────────────────────────────────────────
 
 * ──────────────────────────────────────────────────────────────────────────
 */
session_start();
require_once 'db/connection.php';
require_once 'notifications.php';   // ← NEW: notification helpers

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['user_role'] !== 'driver') { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$stmt    = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$driver  = $stmt->fetch();
if (!$driver) { session_destroy(); header('Location: login.php'); exit; }

$status = $driver['driver_status'] ?? 'unverified';

// ── Document upload (unchanged) ───────────────────────────────────────────
$upload_error = $upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_docs') {
    $upload_dir = 'uploads/driver_docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max     = 5 * 1024 * 1024;
    if (empty($_FILES['licence']['name'])) {
        $upload_error = 'Please upload your driving licence.';
    } elseif (!in_array($_FILES['licence']['type'], $allowed)) {
        $upload_error = 'Licence must be JPG, PNG, WebP or PDF.';
    } elseif ($_FILES['licence']['size'] > $max) {
        $upload_error = 'Licence file must be under 5 MB.';
    } elseif (empty($_FILES['vehicle_photo']['name'])) {
        $upload_error = 'Please upload a vehicle photo.';
    } elseif (!in_array($_FILES['vehicle_photo']['type'], $allowed)) {
        $upload_error = 'Vehicle photo must be JPG, PNG or WebP.';
    } elseif ($_FILES['vehicle_photo']['size'] > $max) {
        $upload_error = 'Vehicle photo must be under 5 MB.';
    } else {
        $ext = pathinfo($_FILES['licence']['name'], PATHINFO_EXTENSION);
        $lp  = 'uploads/driver_docs/licence_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['licence']['tmp_name'], $lp);
        $ext = pathinfo($_FILES['vehicle_photo']['name'], PATHINFO_EXTENSION);
        $vp  = 'uploads/driver_docs/vehicle_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['vehicle_photo']['tmp_name'], $vp);
        $pdo->prepare('UPDATE users SET driver_status="pending",licence_path=?,vehicle_photo_path=? WHERE id=?')
            ->execute([$lp, $vp, $user_id]);
        $status = 'pending';
        $upload_success = 'Documents submitted!';
        $stmt->execute([$user_id]);
        $driver = $stmt->fetch();
    }
}

// ── Post ride (UPDATED: stores total_seats & sets booked_seats = 0) ───────
$ride_error = $ride_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'post_ride'
    && $status === 'approved'
) {
    $from          = trim($_POST['from']          ?? '');
    $to            = trim($_POST['to']            ?? '');
    $date          = $_POST['date']               ?? '';
    $time          = $_POST['time']               ?? '';
    $seats         = (int)($_POST['seats']        ?? 0);
    $price         = (float)($_POST['price']      ?? 0);
    $vehicle       = $_POST['vehicle_type']       ?? 'car';
    $vehicle_model = trim($_POST['vehicle_model'] ?? '');
    $notes         = trim($_POST['notes']         ?? '');

    if (!$from || !$to || !$date || !$time || !$seats || !$price || !$vehicle_model) {
        $ride_error = 'Please fill in all required fields.';
    } elseif ($date < date('Y-m-d')) {
        $ride_error = 'Date cannot be in the past.';
    } else {
        // Save vehicle model to driver profile
        $pdo->prepare("UPDATE users SET vehicle_model = ? WHERE id = ?")
            ->execute([$vehicle_model, $user_id]);

        // Insert ride — total_seats and booked_seats are now explicit
        $pdo->prepare("
            INSERT INTO rides
                (driver_id, start_location, destination, ride_date, ride_time,
                 seats_available, total_seats, booked_seats,
                 price, vehicle_type, vehicle_model, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 'active')
        ")->execute([
            $user_id, $from, $to, $date, $time,
            $seats, $seats,   // seats_available = total_seats at creation
            $price, $vehicle, $vehicle_model, $notes,
        ]);

        $ride_success = 'Ride posted successfully!';
    }
}

// ── Accept / Reject booking request (UPDATED: syncs seats + status) ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['accept', 'reject'])
) {
    $action = $_POST['action'];
    $rid    = (int)($_POST['request_id'] ?? 0);

    // Fetch request + ride in one query — ensures driver owns the ride
    $chk = $pdo->prepare("
        SELECT rq.*, r.total_seats, r.booked_seats, r.id AS rid,
               r.start_location, r.destination, r.driver_id,
               u.name AS pname
        FROM   ride_requests rq
        JOIN   rides r ON rq.ride_id = r.id
        JOIN   users u ON rq.passenger_id = u.id
        WHERE  rq.id = ? AND r.driver_id = ?
    ");
    $chk->execute([$rid, $user_id]);
    $req = $chk->fetch();

    if ($req) {
        if ($action === 'accept' && $req['booked_seats'] < $req['total_seats']) {
            $pdo->prepare("UPDATE ride_requests SET status = 'accepted' WHERE id = ?")
                ->execute([$rid]);
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE ride_requests SET status = 'rejected' WHERE id = ?")
                ->execute([$rid]);
        }

        // Sync ride seats & status; may trigger a "fully booked" notification
        sync_ride_status($pdo, (int)$req['rid'], $user_id);
    }

    header('Location: driver_dashboard.php?page=bookings');
    exit;
}

// ── Mark ride as completed (manual) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'complete_ride'
) {
    $ride_id = (int)($_POST['ride_id'] ?? 0);

    // Confirm driver owns the ride
    $own = $pdo->prepare("SELECT id, start_location, destination FROM rides WHERE id = ? AND driver_id = ?");
    $own->execute([$ride_id, $user_id]);
    $ownRide = $own->fetch();

    if ($ownRide) {
        $pdo->prepare("UPDATE rides SET status = 'completed' WHERE id = ?")
            ->execute([$ride_id]);

        // Notify driver of completion
        $route = htmlspecialchars($ownRide['start_location']) . ' → ' . htmlspecialchars($ownRide['destination']);
        notify_driver(
            $pdo, $user_id, $ride_id,
            'ride_completed',
            '✅ Ride Completed',
            "Your ride to {$route} has been marked as completed."
        );
    }

    header('Location: driver_dashboard.php?page=rides');
    exit;
}

// ── Cancel ride ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel_ride'
) {
    $ride_id = (int)($_POST['ride_id'] ?? 0);
    $own = $pdo->prepare("SELECT id FROM rides WHERE id = ? AND driver_id = ? AND status = 'active'");
    $own->execute([$ride_id, $user_id]);
    if ($own->fetch()) {
        $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?")
            ->execute([$ride_id]);
    }
    header('Location: driver_dashboard.php?page=rides');
    exit;
}

// ── Mark notification(s) as read ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'mark_read'
) {
    $nid = (int)($_POST['notif_id'] ?? 0);
    if ($nid) {
        mark_notification_read($pdo, $nid, $user_id);
    } else {
        mark_all_notifications_read($pdo, $user_id);
    }
    header('Location: driver_dashboard.php?page=notifications');
    exit;
}

// ── Passive check: warn driver if a ride departs soon with no passengers ──
if ($status === 'approved') {
    check_no_passenger_rides($pdo, $user_id);
}

// ── Fetch dashboard data ──────────────────────────────────────────────────
$upcoming_rides = [];
$past_rides     = [];
$all_requests   = [];
$stats          = ['total_rides' => 0, 'total_earned' => 0, 'pending_requests' => 0];
$notifications  = [];
$unread_count   = 0;

if ($status === 'approved') {
    // Upcoming active & fully-booked rides (today onwards)
    $r = $pdo->prepare("
        SELECT r.*,
               (SELECT COUNT(*) FROM ride_requests rq
                WHERE rq.ride_id = r.id AND rq.status = 'pending')  AS pending_count,
               (SELECT COUNT(*) FROM ride_requests rq
                WHERE rq.ride_id = r.id AND rq.status = 'accepted') AS accepted_count
        FROM   rides r
        WHERE  r.driver_id = ?
          AND  r.ride_date >= CURDATE()
          AND  r.status IN ('active','fully_booked')
        ORDER BY r.ride_date ASC, r.ride_time ASC
    ");
    $r->execute([$user_id]);
    $upcoming_rides = $r->fetchAll();

    // Completed / past rides
    $p = $pdo->prepare("
        SELECT r.*,
               (SELECT COUNT(*) FROM ride_requests rq
                WHERE rq.ride_id = r.id AND rq.status = 'accepted') AS accepted_count
        FROM   rides r
        WHERE  r.driver_id = ?
          AND  (r.ride_date < CURDATE() OR r.status IN ('completed','cancelled'))
        ORDER BY r.ride_date DESC
        LIMIT  30
    ");
    $p->execute([$user_id]);
    $past_rides = $p->fetchAll();

    // All booking requests for this driver's rides
    $rq = $pdo->prepare("
        SELECT rq.*,
               r.start_location, r.destination, r.ride_date, r.ride_time,
               r.total_seats, r.booked_seats, r.seats_available,
               u.name  AS pname,
               u.phone AS pphone,
               u.email AS pemail
        FROM   ride_requests rq
        JOIN   rides r ON rq.ride_id = r.id
        JOIN   users u ON rq.passenger_id = u.id
        WHERE  r.driver_id = ?
        ORDER BY
               CASE rq.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END,
               rq.created_at DESC
    ");
    $rq->execute([$user_id]);
    $all_requests = $rq->fetchAll();

    // Stats
    $stats['total_rides']       = count(array_filter($past_rides, fn($r) => $r['status'] === 'completed'));
    $stats['total_earned']      = array_sum(array_map(fn($r) => $r['price'] * $r['accepted_count'], $past_rides));
    $stats['pending_requests']  = count(array_filter($all_requests, fn($r) => $r['status'] === 'pending'));

    // Notifications
    $notifications = get_all_notifications($pdo, $user_id);
    $unread_count  = count_unread_notifications($pdo, $user_id);
}

// ── Page routing ──────────────────────────────────────────────────────────
$page_raw   = $_GET['page'] ?? 'dashboard';
$page_alias = ['requests' => 'bookings', 'history' => 'dashboard', 'overview' => 'dashboard'];
$current_page = $page_alias[$page_raw] ?? $page_raw;
$valid_pages  = ['dashboard', 'rides', 'bookings', 'earnings', 'notifications', 'profile', 'support'];
if (!in_array($current_page, $valid_pages)) $current_page = 'dashboard';

// Initials helper
$initials = strtoupper(substr($driver['name'], 0, 1));
if (strpos($driver['name'], ' ') !== false)
    $initials .= strtoupper(substr(strrchr($driver['name'], ' '), 1, 1));

// Vehicle label helpers (unchanged)
function vLabel(string $t): string {
    return match($t) { 'car' => 'Car', 'van' => 'Van', 'three-wheeler' => 'Tuk-tuk', default => $t };
}
function vIcon(string $t): string {
    return match($t) { 'car' => 'ti-car', 'van' => 'ti-bus', 'three-wheeler' => 'ti-motorbike', default => 'ti-car' };
}

// Ride status badge helper
function rideBadge(string $status, int $pending = 0): array {
    return match($status) {
        'active'       => $pending > 0
                            ? ['class' => 'badge-pending-r', 'label' => $pending . ' Pending']
                            : ['class' => 'badge-active',    'label' => 'Active'],
        'fully_booked' => ['class' => 'badge-full',      'label' => 'Fully Booked'],
        'cancelled'    => ['class' => 'badge-cancelled',  'label' => 'Cancelled'],
        'completed'    => ['class' => 'badge-completed',  'label' => 'Completed'],
        default        => ['class' => 'badge-active',     'label' => $status],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Driver Dashboard — RideShare LK</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
  <style>
    /* ── BASE (unchanged) ─────────────────────────────────────────────── */
    *{margin:0;padding:0;box-sizing:border-box}
    :root{
      --bg:#0D1117;--surface:#161B22;--surface2:#1C2330;--surface3:#21293A;
      --border:#2A3444;--border2:#334155;
      --text:#E6EDF3;--text2:#8B949E;--text3:#6E7681;
      --green:#3FB950;--green-dim:#1A3626;
      --amber:#E3B341;--amber-dim:#2D2208;
      --red:#F85149;--red-dim:#2D1117;
      --blue:#58A6FF;--blue-dim:#0D1F3C;
      --purple:#BC8CFF;--purple-dim:#1A0D3C;
      --accent:#FF6B35;--accent2:#FF8C5A;
      --radius:10px;--radius-lg:14px;
    }
    html{-webkit-font-smoothing:antialiased}
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;min-height:100vh}
    a{text-decoration:none;color:inherit}

    /* SHELL */
    .shell{display:flex;min-height:100vh}

    /* SIDEBAR */
    .sidebar{width:224px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto}
    .logo{padding:20px 20px 16px;border-bottom:1px solid var(--border)}
    .logo-mark{display:flex;align-items:center;gap:10px}
    .logo-icon{width:34px;height:34px;background:var(--accent);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
    .logo-text{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;letter-spacing:.3px}
    .logo-sub{font-size:11px;color:var(--text3);margin-top:1px}
    .sidenav{padding:12px 10px;flex:1}
    .nav-section{font-size:10px;font-weight:500;color:var(--text3);letter-spacing:1.2px;text-transform:uppercase;padding:0 10px;margin:14px 0 5px}
    .nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;cursor:pointer;color:var(--text2);transition:all .15s;font-size:13px;border:1px solid transparent}
    .nav-item:hover{background:var(--surface2);color:var(--text)}
    .nav-item.active{background:linear-gradient(135deg,rgba(255,107,53,.15),rgba(255,107,53,.05));color:var(--accent);border-color:rgba(255,107,53,.2)}
    .nav-item i{font-size:16px;width:18px;text-align:center}
    .nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;min-width:20px;text-align:center}
    .nav-badge.green{background:var(--green)}
    .driver-card{margin:10px;padding:14px;background:var(--surface2);border-radius:var(--radius);border:1px solid var(--border)}
    .d-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:14px;color:#fff}
    .d-info{margin-top:10px}
    .d-name{font-weight:500;font-size:13px}
    .d-status{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--green);margin-top:3px}
    .d-status.pending{color:var(--amber)}.d-status.unverified{color:var(--text3)}
    .status-dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
    .d-logout{display:flex;align-items:center;gap:6px;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);font-size:12px;color:var(--text3);cursor:pointer;transition:color .15s}
    .d-logout:hover{color:var(--red)}

    /* MAIN */
    .main{flex:1;margin-left:224px;display:flex;flex-direction:column;min-height:100vh}
    .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
    .topbar-title{font-family:'Syne',sans-serif;font-weight:600;font-size:17px}
    .topbar-right{display:flex;align-items:center;gap:10px}
    .icon-btn{width:34px;height:34px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text2);transition:all .15s;position:relative}
    .icon-btn:hover{border-color:var(--border2);color:var(--text)}
    .notif-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;background:var(--accent);border-radius:50%;border:1.5px solid var(--surface)}
    .notif-count{position:absolute;top:-5px;right:-5px;background:var(--accent);color:#fff;font-size:9px;font-weight:700;padding:2px 4px;border-radius:8px;min-width:16px;text-align:center;line-height:1.2}

    /* BUTTONS */
    .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s;font-family:'DM Sans',sans-serif}
    .btn-primary{background:var(--accent);color:#fff}
    .btn-primary:hover{background:var(--accent2)}
    .btn-ghost{background:var(--surface2);color:var(--text2);border:1px solid var(--border)}
    .btn-ghost:hover{color:var(--text);border-color:var(--border2)}
    .btn-green{background:var(--green-dim);color:var(--green);border:1px solid rgba(63,185,80,.25)}
    .btn-green:hover{background:var(--green);color:#000}
    .btn-red{background:var(--red-dim);color:var(--red);border:1px solid rgba(248,81,73,.25)}
    .btn-red:hover{background:var(--red);color:#fff}
    .btn-amber{background:var(--amber-dim);color:var(--amber);border:1px solid rgba(227,179,65,.25)}
    .btn-amber:hover{background:var(--amber);color:#000}

    .content{flex:1;padding:26px 28px;overflow-y:auto}
    .page{display:none}.page.active{display:block}

    /* ALERTS */
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;border:1px solid;font-weight:500}
    .alert-g{background:var(--green-dim);color:var(--green);border-color:rgba(63,185,80,.3)}
    .alert-r{background:var(--red-dim);color:var(--red);border-color:rgba(248,81,73,.3)}
    .alert-a{background:var(--amber-dim);color:var(--amber);border-color:rgba(227,179,65,.3)}

    /* VERIFY BANNER */
    .verify-banner{border-radius:var(--radius-lg);padding:14px 18px;display:flex;align-items:center;gap:14px;margin-bottom:22px;border:1px solid}
    .vb-approved{background:linear-gradient(135deg,rgba(63,185,80,.08),rgba(63,185,80,.03));border-color:rgba(63,185,80,.2)}
    .vb-pending{background:linear-gradient(135deg,rgba(227,179,65,.08),rgba(227,179,65,.03));border-color:rgba(227,179,65,.2)}
    .vb-rejected{background:linear-gradient(135deg,rgba(248,81,73,.08),rgba(248,81,73,.03));border-color:rgba(248,81,73,.2)}
    .vb-unverified{background:linear-gradient(135deg,rgba(255,107,53,.08),rgba(255,107,53,.03));border-color:rgba(255,107,53,.2)}
    .vb-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
    .vb-approved .vb-icon{background:rgba(63,185,80,.12);color:var(--green)}
    .vb-pending .vb-icon{background:rgba(227,179,65,.12);color:var(--amber)}
    .vb-rejected .vb-icon{background:rgba(248,81,73,.12);color:var(--red)}
    .vb-unverified .vb-icon{background:rgba(255,107,53,.12);color:var(--accent)}
    .vb-t1{font-size:13px;font-weight:600}.vb-t2{font-size:12px;color:var(--text3);margin-top:2px}

    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;transition:border-color .15s}
    .stat-card:hover{border-color:var(--border2)}
    .stat-label{font-size:11px;color:var(--text3);font-weight:500;letter-spacing:.5px;text-transform:uppercase}
    .stat-value{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;margin:6px 0 4px}
    .stat-delta{font-size:11px;color:var(--text3);display:flex;align-items:center;gap:4px}
    .delta-up{color:var(--green)}

    /* SECTION */
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .section-title{font-family:'Syne',sans-serif;font-weight:600;font-size:15px}
    .section-sub{font-size:12px;color:var(--text3);margin-top:2px}

    /* RIDE CARDS */
    .rides-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .rides-col{display:flex;flex-direction:column;gap:12px}
    .ride-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;transition:border-color .15s}
    .ride-card:hover{border-color:var(--border2)}
    .ride-card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
    .ride-route{font-weight:600;font-size:14px;font-family:'Syne',sans-serif}
    .ride-route span{color:var(--text2);font-weight:400}
    .ride-time{font-size:12px;color:var(--text3);margin-top:4px;display:flex;align-items:center;gap:4px}

    /* ── NEW: ride status badges ── */
    .ride-badge{font-size:10px;font-weight:600;padding:3px 9px;border-radius:6px;letter-spacing:.3px;white-space:nowrap}
    .badge-active{background:var(--green-dim);color:var(--green)}
    .badge-pending-r{background:var(--amber-dim);color:var(--amber)}
    .badge-full{background:var(--blue-dim);color:var(--blue)}
    .badge-cancelled{background:var(--red-dim);color:var(--red)}
    .badge-completed{background:var(--surface3);color:var(--text3);border:1px solid var(--border2)}

    .ride-meta{display:flex;gap:12px;font-size:12px;color:var(--text2);flex-wrap:wrap;margin-bottom:8px}
    .ride-meta-item{display:flex;align-items:center;gap:4px}
    .ride-meta-item i{font-size:13px;color:var(--text3)}

    /* ── NEW: seat indicator row ── */
    .seat-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:8px 10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border)}
    .seat-label{font-size:11px;color:var(--text3);min-width:70px}
    .seat-value{font-size:13px;font-weight:600}
    .seats-bar{display:flex;gap:3px}
    .seat{height:5px;border-radius:3px;flex:1}
    .seat.taken{background:var(--accent)}.seat.free{background:var(--border)}.seat.all-taken{background:var(--green)}

    .ride-footer{border-top:1px solid var(--border);margin-top:12px;padding-top:10px;display:flex;align-items:center;justify-content:space-between}
    .ride-price{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;color:var(--accent)}
    .ride-price span{font-size:11px;font-weight:400;color:var(--text3);font-family:'DM Sans',sans-serif}
    .ride-actions{display:flex;gap:6px}
    .action-btn{width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text2);font-size:14px;transition:all .15s}
    .action-btn:hover{background:var(--surface2);border-color:var(--border2);color:var(--text)}

    /* REQUESTS TABLE */
    .requests-table{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
    .table-head{display:grid;grid-template-columns:2fr 2fr 1fr 1fr 120px;padding:10px 18px;background:var(--surface2);border-bottom:1px solid var(--border);font-size:11px;font-weight:500;color:var(--text3);letter-spacing:.5px;text-transform:uppercase;gap:8px}
    .table-row{display:grid;grid-template-columns:2fr 2fr 1fr 1fr 120px;padding:13px 18px;border-bottom:1px solid var(--border);align-items:center;transition:background .1s;gap:8px}
    .table-row:last-child{border-bottom:none}
    .table-row:hover{background:var(--surface2)}
    .p-cell{display:flex;align-items:center;gap:10px}
    .p-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;font-family:'Syne',sans-serif;flex-shrink:0}
    .p-av-pending{background:rgba(227,179,65,.2);color:var(--amber)}
    .p-av-accepted{background:rgba(63,185,80,.2);color:var(--green)}
    .p-av-rejected{background:rgba(110,118,129,.15);color:var(--text3)}
    .p-name{font-size:13px;font-weight:500}.p-contact{font-size:11px;color:var(--text3)}
    .route-cell{font-size:13px;color:var(--text2)}
    .route-cell strong{color:var(--text);font-weight:500}
    .status-pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:3px 9px;border-radius:20px}
    .pill-pending{background:var(--amber-dim);color:var(--amber)}
    .pill-accepted{background:var(--green-dim);color:var(--green)}
    .pill-rejected{background:var(--red-dim);color:var(--red)}
    .req-actions{display:flex;gap:5px}
    .req-btn{padding:4px 11px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s}
    .req-accept{background:var(--green-dim);color:var(--green);border:1px solid rgba(63,185,80,.25)}
    .req-accept:hover{background:var(--green);color:#000}
    .req-reject{background:var(--red-dim);color:var(--red);border:1px solid rgba(248,81,73,.25)}
    .req-reject:hover{background:var(--red);color:#fff}

    /* HISTORY TABLE */
    .history-table{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
    .h-head{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;padding:10px 18px;background:var(--surface2);border-bottom:1px solid var(--border);font-size:11px;font-weight:500;color:var(--text3);letter-spacing:.5px;text-transform:uppercase;gap:8px}
    .h-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;padding:13px 18px;border-bottom:1px solid var(--border);align-items:center;transition:background .1s;gap:8px;font-size:13px}
    .h-row:last-child{border-bottom:none}
    .h-row:hover{background:var(--surface2)}
    .h-earned{font-family:'Syne',sans-serif;font-weight:700;color:var(--green)}

    /* EARNINGS */
    .earnings-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .earnings-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px}
    .mini-chart{display:flex;align-items:flex-end;gap:4px;height:60px;margin-top:14px}
    .bar{flex:1;background:var(--surface3);border-radius:3px 3px 0 0;transition:background .15s;cursor:pointer}
    .bar:hover,.bar.highlight{background:linear-gradient(180deg,var(--accent),var(--accent2))}
    .chart-labels{display:flex;gap:4px;margin-top:6px}
    .chart-label{flex:1;text-align:center;font-size:10px;color:var(--text3)}

    /* ── NEW: NOTIFICATION PANEL ── */
    .notif-list{display:flex;flex-direction:column;gap:10px}
    .notif-item{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);transition:border-color .15s;position:relative}
    .notif-item.unread{border-left:3px solid var(--accent);background:linear-gradient(90deg,rgba(255,107,53,.05),var(--surface))}
    .notif-item:hover{border-color:var(--border2)}
    .notif-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
    .notif-body{flex:1;min-width:0}
    .notif-title{font-size:13px;font-weight:600;margin-bottom:3px}
    .notif-msg{font-size:12px;color:var(--text3);line-height:1.5}
    .notif-time{font-size:11px;color:var(--text3);margin-top:6px}
    .notif-unread-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:6px}
    .notif-actions{display:flex;align-items:center;gap:8px;margin-top:8px}

    /* UPLOAD */
    .upload-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
    .file-zone{border:2px dashed var(--border2);border-radius:var(--radius);padding:24px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:var(--surface2)}
    .file-zone:hover{border-color:var(--accent);background:rgba(255,107,53,.04)}
    .file-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
    .file-zone i{font-size:28px;color:var(--text3);display:block;margin-bottom:8px}
    .file-zone p{font-size:12px;color:var(--text3);line-height:1.5}
    .file-name{font-size:12px;color:var(--accent);font-weight:500;margin-top:6px;display:none}

    /* TAB BAR */
    .tab-bar{display:flex;gap:2px;background:var(--surface2);border-radius:9px;padding:3px;margin-bottom:20px;width:fit-content;border:1px solid var(--border)}
    .tab{padding:7px 18px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;color:var(--text2);transition:all .15s;user-select:none}
    .tab.active{background:var(--surface);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.3)}

    /* MODAL */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:200;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-lg);width:560px;max-height:90vh;overflow-y:auto}
    .modal-header{padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .modal-title{font-family:'Syne',sans-serif;font-weight:600;font-size:16px;display:flex;align-items:center;gap:8px}
    .modal-close{width:28px;height:28px;border-radius:6px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text2);font-size:16px;transition:all .15s}
    .modal-close:hover{border-color:var(--border2);color:var(--text)}
    .modal-body{padding:22px 24px}
    .modal-footer{padding:14px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
    .form-row.single{grid-template-columns:1fr}
    .form-group label{display:block;font-size:12px;font-weight:500;color:var(--text2);margin-bottom:6px;letter-spacing:.3px}
    .form-group input,.form-group select,.form-group textarea{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:border-color .15s}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent)}
    .form-group select option{background:var(--surface2)}
    .form-group textarea{resize:vertical;min-height:70px}
    .modal-alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;background:var(--red-dim);color:var(--red);border:1px solid rgba(248,81,73,.3)}
    .modal-success{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;background:var(--green-dim);color:var(--green);border:1px solid rgba(63,185,80,.3)}

    /* EMPTY STATE */
    .empty-state{text-align:center;padding:52px 24px;color:var(--text3)}
    .empty-state i{font-size:42px;margin-bottom:14px;display:block;color:var(--border2)}
    .empty-title{font-size:15px;color:var(--text2);font-weight:500;margin-bottom:6px}
    .empty-sub{font-size:13px}

    /* PROFILE */
    .profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .profile-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px}
    .profile-card h3{font-family:'Syne',sans-serif;font-size:13px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}
    .profile-row{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);margin-bottom:8px}
    .profile-row:last-child{margin-bottom:0}
    .pr-label{font-size:13px;color:var(--text3)}.pr-val{font-size:13px;font-weight:500}

    .scrollbar-thin{scrollbar-width:thin;scrollbar-color:var(--border) transparent}
    .scrollbar-thin::-webkit-scrollbar{width:5px}
    .scrollbar-thin::-webkit-scrollbar-track{background:transparent}
    .scrollbar-thin::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}

    @media(max-width:900px){
      .sidebar{display:none}.main{margin-left:0}
      .stats-grid{grid-template-columns:1fr 1fr}
      .rides-grid{grid-template-columns:1fr}
      .table-head,.table-row{grid-template-columns:1fr 1fr 1fr}
      .table-head>:nth-child(4),.table-row>:nth-child(4),.table-head>:last-child,.table-row>:last-child{display:none}
      .earnings-grid,.profile-grid{grid-template-columns:1fr}
      .upload-grid{grid-template-columns:1fr}
      .h-head,.h-row{grid-template-columns:2fr 1fr 1fr 1fr}
    }
  </style>
</head>
<body>
<div class="shell">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">
      <div class="logo-mark">
        <div class="logo-icon"><i class="ti ti-car"></i></div>
        <div>
          <div class="logo-text">RideShare LK</div>
          <div class="logo-sub">Driver Portal</div>
        </div>
      </div>
    </div>

    <nav class="sidenav">
      <?php if ($status === 'approved'): ?>
        <div class="nav-section">Main</div>
        <div class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>" onclick="goPage('dashboard')">
          <i class="ti ti-layout-dashboard"></i> Dashboard
        </div>
        <div class="nav-item <?= $current_page === 'rides' ? 'active' : '' ?>" onclick="goPage('rides')">
          <i class="ti ti-route"></i> My Rides
          <?php if (count($upcoming_rides) > 0): ?>
            <span class="nav-badge"><?= count($upcoming_rides) ?></span>
          <?php endif; ?>
        </div>
        <div class="nav-item <?= $current_page === 'bookings' ? 'active' : '' ?>" onclick="goPage('bookings')">
          <i class="ti ti-ticket"></i> Bookings
          <?php if ($stats['pending_requests'] > 0): ?>
            <span class="nav-badge"><?= $stats['pending_requests'] ?></span>
          <?php endif; ?>
        </div>

        <!-- NEW: Notifications nav item -->
        <div class="nav-item <?= $current_page === 'notifications' ? 'active' : '' ?>" onclick="goPage('notifications')">
          <i class="ti ti-bell"></i> Notifications
          <?php if ($unread_count > 0): ?>
            <span class="nav-badge"><?= $unread_count ?></span>
          <?php endif; ?>
        </div>

        <div class="nav-section">Finance</div>
        <div class="nav-item <?= $current_page === 'earnings' ? 'active' : '' ?>" onclick="goPage('earnings')">
          <i class="ti ti-wallet"></i> Earnings
        </div>
        <div class="nav-section">Account</div>
      <?php endif; ?>
      <a href="driver_profile.php" class="nav-item"><i class="ti ti-user-circle"></i> Profile</a>
      <div class="nav-item <?= $current_page === 'support' ? 'active' : '' ?>" onclick="goPage('support')">
        <i class="ti ti-headset"></i> Support
      </div>
      <a href="index.php" class="nav-item"><i class="ti ti-home"></i> Public site</a>
    </nav>

    <div class="driver-card">
      <div class="d-avatar"><?= $initials ?></div>
      <div class="d-info">
        <div class="d-name"><?= htmlspecialchars($driver['name']) ?></div>
        <div class="d-status <?= $status === 'approved' ? '' : ($status === 'pending' ? 'pending' : 'unverified') ?>">
          <span class="status-dot"></span>
          <?= match($status) { 'approved' => 'Verified Driver', 'pending' => 'Pending Review', 'rejected' => 'Action Required', default => 'Not Verified' } ?>
        </div>
      </div>
      <a href="logout.php" class="d-logout"><i class="ti ti-logout" style="font-size:14px"></i> Log out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div class="topbar-title" id="page-title">
        <?= match($current_page) { 'dashboard' => 'Dashboard', 'rides' => 'My Rides', 'bookings' => 'Bookings', 'earnings' => 'Earnings', 'notifications' => 'Notifications', 'support' => 'Support', default => 'Dashboard' } ?>
      </div>
      <div class="topbar-right">
        <?php if ($status === 'approved'): ?>
          <!-- Bell icon with unread count badge -->
          <div class="icon-btn" onclick="goPage('notifications')" title="Notifications">
            <i class="ti ti-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="notif-count"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
            <?php endif; ?>
          </div>
          <button class="btn btn-primary" onclick="openModal()"><i class="ti ti-plus"></i> Post Ride</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="content scrollbar-thin">

      <?php if ($upload_error): ?><div class="alert alert-r"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($upload_error) ?></div><?php endif; ?>
      <?php if ($upload_success): ?><div class="alert alert-g"><i class="ti ti-check"></i> <?= htmlspecialchars($upload_success) ?></div><?php endif; ?>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- DASHBOARD PAGE                                                    -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'dashboard' ? 'active' : '' ?>" id="page-dashboard">

        <!-- Verification banner -->
        <?php
        $vbClass = match($status) { 'approved' => 'vb-approved', 'pending' => 'vb-pending', 'rejected' => 'vb-rejected', default => 'vb-unverified' };
        $vbIcon  = match($status) { 'approved' => 'ti-shield-check', 'pending' => 'ti-clock', 'rejected' => 'ti-shield-x', default => 'ti-upload' };
        $vbT1    = match($status) { 'approved' => 'Account Verified', 'pending' => 'Verification in Progress', 'rejected' => 'Documents Rejected', 'unverified' => 'Upload Your Documents' };
        $vbT2    = match($status) { 'approved' => 'Driving licence and vehicle documents approved. You can post rides.', 'pending' => 'Documents under admin review — usually within 24 hours.', 'rejected' => 'Admin could not verify your documents. Please re-upload.', 'unverified' => 'Upload your driving licence and vehicle photo to start.' };
        ?>
        <div class="verify-banner <?= $vbClass ?>">
          <div class="vb-icon"><i class="ti <?= $vbIcon ?>"></i></div>
          <div><div class="vb-t1"><?= $vbT1 ?></div><div class="vb-t2"><?= $vbT2 ?></div></div>
          <?php if (!in_array($status, ['approved', 'pending'])): ?>
            <a href="driver_profile.php" class="btn btn-ghost" style="margin-left:auto;font-size:12px">Upload docs</a>
          <?php endif; ?>
        </div>

        <?php if ($status === 'approved'):
          $totalEarned = array_sum(array_map(fn($r) => $r['price'] * $r['accepted_count'], $past_rides));
          $totalPax    = array_sum(array_column($past_rides, 'accepted_count'));
          $avgPerRide  = count($past_rides) > 0 ? round($totalEarned / count($past_rides)) : 0;
        ?>

        <!-- Stats grid -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">Total Rides</div>
            <div class="stat-value" style="color:var(--blue)"><?= $stats['total_rides'] ?></div>
            <div class="stat-delta">Completed past rides</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Earned</div>
            <div class="stat-value" style="color:var(--green)">Rs. <?= number_format($stats['total_earned']) ?></div>
            <div class="stat-delta delta-up"><i class="ti ti-trending-up" style="font-size:12px"></i> Based on accepted passengers</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value" style="color:var(--amber)"><?= $stats['pending_requests'] ?></div>
            <div class="stat-delta">Awaiting your response</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Upcoming Rides</div>
            <div class="stat-value" style="color:var(--accent)"><?= count($upcoming_rides) ?></div>
            <div class="stat-delta">Scheduled ahead</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Passengers Carried</div>
            <div class="stat-value" style="color:var(--green)"><?= $totalPax ?></div>
            <div class="stat-delta">All time</div>
          </div>
          <!-- NEW: Unread notifications stat -->
          <div class="stat-card" style="cursor:pointer" onclick="goPage('notifications')">
            <div class="stat-label">Notifications</div>
            <div class="stat-value" style="color:var(--accent)"><?= $unread_count ?></div>
            <div class="stat-delta">Unread alerts</div>
          </div>
        </div>

        <!-- Earnings summary bar -->
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:160px">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(63,185,80,.12);display:flex;align-items:center;justify-content:center;color:var(--green);font-size:18px;flex-shrink:0"><i class="ti ti-wallet"></i></div>
            <div>
              <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;font-weight:500">Today's Earnings</div>
              <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--green)">Rs. 0</div>
            </div>
          </div>
          <div style="width:1px;height:36px;background:var(--border);flex-shrink:0"></div>
          <div style="flex:1;min-width:160px">
            <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;font-weight:500">This Week</div>
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--blue)">Rs. <?= number_format($stats['total_earned']) ?></div>
          </div>
          <div style="width:1px;height:36px;background:var(--border);flex-shrink:0"></div>
          <div style="flex:1;min-width:160px">
            <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;font-weight:500">All Time</div>
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--accent)">Rs. <?= number_format($stats['total_earned']) ?></div>
          </div>
          <button class="btn btn-ghost" onclick="goPage('earnings')" style="font-size:12px;flex-shrink:0"><i class="ti ti-wallet"></i> Full Earnings</button>
        </div>

        <!-- Upcoming rides + Pending bookings -->
        <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:14px;margin-bottom:28px">
          <div>
            <div class="section-header">
              <div><div class="section-title">Upcoming Rides</div><div class="section-sub">Active & fully booked rides</div></div>
              <button class="btn btn-ghost" onclick="goPage('rides')" style="font-size:12px;padding:6px 12px">View all</button>
            </div>
            <div class="rides-col">
              <?php if (empty($upcoming_rides)): ?>
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;text-align:center;color:var(--text3);font-size:13px">
                  <i class="ti ti-route" style="font-size:32px;display:block;margin-bottom:10px;color:var(--border2)"></i>
                  No upcoming rides. <span style="color:var(--accent);cursor:pointer" onclick="openModal()">Post your first ride →</span>
                </div>
              <?php else: ?>
                <?php foreach (array_slice($upcoming_rides, 0, 3) as $r):
                  $badge = rideBadge($r['status'], (int)$r['pending_count']);
                  $totalS = (int)$r['total_seats'];
                  $bookedS = (int)$r['booked_seats'];
                  $availS = max($totalS - $bookedS, 0);
                ?>
                <div class="ride-card">
                  <div class="ride-card-top">
                    <div>
                      <div class="ride-route"><?= htmlspecialchars($r['start_location']) ?> <i class="ti ti-arrow-right" style="font-size:12px;color:var(--text3);margin:0 4px"></i> <span><?= htmlspecialchars($r['destination']) ?></span></div>
                      <div class="ride-time"><i class="ti ti-calendar" style="font-size:12px"></i> <?= date('D, d M', strtotime($r['ride_date'])) ?> · <?= date('g:i A', strtotime($r['ride_time'])) ?></div>
                    </div>
                    <span class="ride-badge <?= $badge['class'] ?>"><?= htmlspecialchars($badge['label']) ?></span>
                  </div>

                  <!-- NEW: Seat summary row -->
                  <div class="seat-row">
                    <div style="display:flex;gap:18px;flex:1;flex-wrap:wrap">
                      <div><span class="seat-label">Total</span> <span class="seat-value" style="color:var(--text)"><?= $totalS ?></span></div>
                      <div><span class="seat-label">Booked</span> <span class="seat-value" style="color:var(--accent)"><?= $bookedS ?></span></div>
                      <div><span class="seat-label">Available</span> <span class="seat-value" style="color:var(--green)"><?= $availS ?></span></div>
                    </div>
                  </div>

                  <!-- Seat bar -->
                  <div class="seats-bar">
                    <?php for ($s = 0; $s < $totalS; $s++): ?>
                      <div class="seat <?= $s < $bookedS ? ($bookedS >= $totalS ? 'all-taken' : 'taken') : 'free' ?>"></div>
                    <?php endfor; ?>
                  </div>

                  <div class="ride-footer">
                    <div class="ride-price">Rs. <?= number_format($r['price']) ?> <span>/ seat</span></div>
                    <div class="ride-actions">
                      <?php if ((int)$r['pending_count'] > 0): ?>
                        <button class="action-btn" onclick="goPage('bookings')" title="View bookings"><i class="ti ti-bell"></i></button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Pending Bookings mini-list -->
          <div>
            <div class="section-header">
              <div><div class="section-title">Pending Bookings</div><div class="section-sub">Awaiting your response</div></div>
              <button class="btn btn-ghost" onclick="goPage('bookings')" style="font-size:12px;padding:6px 12px">View all</button>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
              <?php $pending_reqs = array_filter($all_requests, fn($r) => $r['status'] === 'pending'); ?>
              <?php if (empty($pending_reqs)): ?>
                <div style="padding:32px;text-align:center;color:var(--text3);font-size:13px"><i class="ti ti-inbox" style="font-size:28px;display:block;margin-bottom:8px;color:var(--border2)"></i>No pending requests</div>
              <?php else: ?>
                <?php foreach (array_slice($pending_reqs, 0, 4) as $req):
                  $pi = strtoupper(substr($req['pname'], 0, 1));
                  if (strpos($req['pname'], ' ') !== false) $pi .= strtoupper(substr(strrchr($req['pname'], ' '), 1, 1));
                ?>
                <div style="display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--border)">
                  <div class="p-av p-av-pending"><?= $pi ?></div>
                  <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($req['pname']) ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($req['start_location']) ?> → <?= htmlspecialchars($req['destination']) ?></div>
                  </div>
                  <div style="display:flex;gap:5px;flex-shrink:0">
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="accept"/><input type="hidden" name="request_id" value="<?= $req['id'] ?>"/><button type="submit" class="req-btn req-accept">✓</button></form>
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject"/><input type="hidden" name="request_id" value="<?= $req['id'] ?>"/><button type="submit" class="req-btn req-reject">✗</button></form>
                  </div>
                </div>
                <?php endforeach; ?>
                <?php if (count($pending_reqs) > 4): ?>
                  <div style="padding:10px 16px;text-align:center"><button class="btn btn-ghost" onclick="goPage('bookings')" style="font-size:12px;width:100%">View all <?= count($pending_reqs) ?> bookings</button></div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Activity history -->
        <div class="section-header" style="margin-top:8px">
          <div><div class="section-title">Activity History</div><div class="section-sub">Your completed rides at a glance</div></div>
        </div>
        <?php if (empty($past_rides)): ?>
          <div class="empty-state"><i class="ti ti-chart-bar"></i><div class="empty-title">No activity yet</div><div class="empty-sub">Completed rides will appear here</div></div>
        <?php else: ?>
          <div class="history-table">
            <div class="h-head"><div>Route</div><div>Date &amp; Time</div><div>Seats</div><div>Passengers</div><div>Status</div><div>Earned</div></div>
            <?php foreach ($past_rides as $r): $earned = $r['price'] * $r['accepted_count']; ?>
            <div class="h-row">
              <div style="font-weight:500"><?= htmlspecialchars($r['start_location']) ?> → <?= htmlspecialchars($r['destination']) ?></div>
              <div style="color:var(--text2)"><?= date('d M Y', strtotime($r['ride_date'])) ?><br><span style="color:var(--text3);font-size:11px"><?= date('g:i A', strtotime($r['ride_time'])) ?></span></div>
              <div style="color:var(--text2);font-size:12px"><?= $r['booked_seats'] ?>/<?= $r['total_seats'] ?></div>
              <div style="color:var(--text2)"><?= $r['accepted_count'] ?> pax</div>
              <div><?php $b = rideBadge($r['status']); ?><span class="ride-badge <?= $b['class'] ?>"><?= $b['label'] ?></span></div>
              <div class="h-earned"><?= $earned > 0 ? 'Rs. ' . number_format($earned) : '—' ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- MY RIDES PAGE (UPDATED with seat info + complete/cancel actions) -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'rides' ? 'active' : '' ?>" id="page-rides">
        <div class="tab-bar">
          <div class="tab active" onclick="switchTab(this,'upcoming-rides')">Upcoming (<?= count($upcoming_rides) ?>)</div>
          <div class="tab" onclick="switchTab(this,'past-rides')">Completed / Past (<?= count($past_rides) ?>)</div>
        </div>

        <!-- Upcoming rides -->
        <div id="upcoming-rides">
          <?php if (empty($upcoming_rides)): ?>
            <div class="empty-state"><i class="ti ti-route"></i><div class="empty-title">No upcoming rides</div><div class="empty-sub">Post a ride to start accepting passengers</div><br><button class="btn btn-primary" onclick="openModal()" style="margin-top:12px"><i class="ti ti-plus"></i> Post a ride</button></div>
          <?php else: ?>
            <div class="rides-grid">
              <?php foreach ($upcoming_rides as $r):
                $badge  = rideBadge($r['status'], (int)$r['pending_count']);
                $totalS = (int)$r['total_seats'];
                $bookedS = (int)$r['booked_seats'];
                $availS  = max($totalS - $bookedS, 0);
              ?>
              <div class="ride-card">
                <div class="ride-card-top">
                  <div>
                    <div class="ride-route"><?= htmlspecialchars($r['start_location']) ?> <i class="ti ti-arrow-right" style="font-size:12px;color:var(--text3);margin:0 4px"></i> <span><?= htmlspecialchars($r['destination']) ?></span></div>
                    <div class="ride-time"><i class="ti ti-calendar" style="font-size:12px"></i> <?= date('D, d M Y', strtotime($r['ride_date'])) ?> · <?= date('g:i A', strtotime($r['ride_time'])) ?></div>
                  </div>
                  <span class="ride-badge <?= $badge['class'] ?>"><?= htmlspecialchars($badge['label']) ?></span>
                </div>

                <!-- NEW: Detailed seat management display -->
                <div class="seat-row">
                  <div style="display:flex;gap:18px;flex:1;flex-wrap:wrap">
                    <div style="text-align:center">
                      <div class="seat-label" style="display:block;margin-bottom:2px">Total Seats</div>
                      <div class="seat-value" style="color:var(--text);font-size:16px"><?= $totalS ?></div>
                    </div>
                    <div style="text-align:center">
                      <div class="seat-label" style="display:block;margin-bottom:2px">Booked</div>
                      <div class="seat-value" style="color:var(--accent);font-size:16px"><?= $bookedS ?></div>
                    </div>
                    <div style="text-align:center">
                      <div class="seat-label" style="display:block;margin-bottom:2px">Available</div>
                      <div class="seat-value" style="color:var(--green);font-size:16px"><?= $availS ?></div>
                    </div>
                  </div>
                </div>

                <!-- Visual seat bar -->
                <div class="seats-bar" style="margin-bottom:10px">
                  <?php for ($s = 0; $s < max($totalS, 1); $s++): ?>
                    <div class="seat <?= $s < $bookedS ? ($bookedS >= $totalS ? 'all-taken' : 'taken') : 'free' ?>"></div>
                  <?php endfor; ?>
                </div>

                <div class="ride-meta">
                  <div class="ride-meta-item"><i class="ti <?= vIcon($r['vehicle_type']) ?>"></i> <?= vLabel($r['vehicle_type']) ?></div>
                  <?php if (!empty($r['vehicle_model'])): ?><div class="ride-meta-item"><i class="ti ti-car"></i> <?= htmlspecialchars($r['vehicle_model']) ?></div><?php endif; ?>
                </div>

                <?php if (!empty($r['notes'])): ?>
                  <div style="margin-top:10px;font-size:12px;color:var(--text3);padding:8px 10px;background:var(--surface2);border-radius:7px;border:1px solid var(--border)"><?= htmlspecialchars($r['notes']) ?></div>
                <?php endif; ?>

                <div class="ride-footer">
                  <div class="ride-price">Rs. <?= number_format($r['price']) ?> <span>/ seat</span></div>
                  <div class="ride-actions">
                    <!-- Mark as completed -->
                    <form method="POST" style="display:inline" onsubmit="return confirm('Mark this ride as completed?')">
                      <input type="hidden" name="action" value="complete_ride"/>
                      <input type="hidden" name="ride_id" value="<?= $r['id'] ?>"/>
                      <button type="submit" class="btn btn-green" style="padding:5px 10px;font-size:11px"><i class="ti ti-flag"></i> Complete</button>
                    </form>
                    <!-- Cancel ride (only if active) -->
                    <?php if ($r['status'] === 'active'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this ride? This cannot be undone.')">
                      <input type="hidden" name="action" value="cancel_ride"/>
                      <input type="hidden" name="ride_id" value="<?= $r['id'] ?>"/>
                      <button type="submit" class="btn btn-red" style="padding:5px 10px;font-size:11px"><i class="ti ti-x"></i></button>
                    </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Past / completed rides -->
        <div id="past-rides" style="display:none">
          <?php if (empty($past_rides)): ?>
            <div class="empty-state"><i class="ti ti-history"></i><div class="empty-title">No completed rides yet</div></div>
          <?php else: ?>
            <div class="history-table">
              <div class="h-head"><div>Route</div><div>Date</div><div>Seats (B/T)</div><div>Passengers</div><div>Status</div><div>Earned</div></div>
              <?php foreach ($past_rides as $r): $earned = $r['price'] * $r['accepted_count']; ?>
              <div class="h-row">
                <div style="font-weight:500"><?= htmlspecialchars($r['start_location']) ?> → <?= htmlspecialchars($r['destination']) ?></div>
                <div style="color:var(--text2)"><?= date('d M Y', strtotime($r['ride_date'])) ?></div>
                <div style="color:var(--text2)"><?= $r['booked_seats'] ?>/<?= $r['total_seats'] ?></div>
                <div style="color:var(--text2)"><?= $r['accepted_count'] ?> pax</div>
                <div><?php $b = rideBadge($r['status']); ?><span class="ride-badge <?= $b['class'] ?>"><?= $b['label'] ?></span></div>
                <div class="h-earned"><?= $earned > 0 ? 'Rs. ' . number_format($earned) : '—' ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- BOOKINGS PAGE (unchanged logic, minor seat info added)           -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'bookings' ? 'active' : '' ?>" id="page-bookings">
        <div class="tab-bar">
          <?php
          $pendingR  = array_filter($all_requests, fn($r) => $r['status'] === 'pending');
          $acceptedR = array_filter($all_requests, fn($r) => $r['status'] === 'accepted');
          $rejectedR = array_filter($all_requests, fn($r) => $r['status'] === 'rejected');
          ?>
          <div class="tab active" onclick="switchTab(this,'req-pending')">Pending (<?= count($pendingR) ?>)</div>
          <div class="tab" onclick="switchTab(this,'req-accepted')">Accepted (<?= count($acceptedR) ?>)</div>
          <div class="tab" onclick="switchTab(this,'req-rejected')">Rejected (<?= count($rejectedR) ?>)</div>
        </div>

        <?php foreach (['pending' => [$pendingR, true], 'accepted' => [$acceptedR, false], 'rejected' => [$rejectedR, false]] as $tab => [$reqs, $showActions]): ?>
        <div id="req-<?= $tab ?>" style="<?= $tab !== 'pending' ? 'display:none' : '' ?>">
          <?php if (empty($reqs)): ?>
            <div class="empty-state"><i class="ti ti-inbox"></i><div class="empty-title">No <?= $tab ?> bookings</div></div>
          <?php else: ?>
            <div class="requests-table">
              <div class="table-head"><div>Passenger</div><div>Route</div><div>Date</div><div>Status</div><div>Action</div></div>
              <?php foreach ($reqs as $req):
                $pi = strtoupper(substr($req['pname'], 0, 1));
                if (strpos($req['pname'], ' ') !== false) $pi .= strtoupper(substr(strrchr($req['pname'], ' '), 1, 1));
                $avClass = 'p-av-' . $req['status'];
              ?>
              <div class="table-row">
                <div class="p-cell">
                  <div class="p-av <?= $avClass ?>"><?= $pi ?></div>
                  <div>
                    <div class="p-name"><?= htmlspecialchars($req['pname']) ?></div>
                    <div class="p-contact"><?= htmlspecialchars($req['pphone'] ?? $req['pemail']) ?></div>
                    <!-- NEW: seat availability context for pending requests -->
                    <?php if ($tab === 'pending'): ?>
                      <div style="font-size:10px;color:var(--text3);margin-top:2px"><?= $req['booked_seats'] ?>/<?= $req['total_seats'] ?> seats booked</div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="route-cell"><strong><?= htmlspecialchars($req['start_location']) ?></strong> → <?= htmlspecialchars($req['destination']) ?></div>
                <div style="color:var(--text2);font-size:12px"><?= date('d M', strtotime($req['ride_date'])) ?> · <?= date('g:i A', strtotime($req['ride_time'])) ?></div>
                <div><span class="status-pill pill-<?= $req['status'] ?>"><?= match($req['status']) { 'pending' => '⏳ Pending', 'accepted' => '✓ Accepted', 'rejected' => '✗ Rejected', default => $req['status'] } ?></span></div>
                <div class="req-actions">
                  <?php if ($showActions): ?>
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="accept"/><input type="hidden" name="request_id" value="<?= $req['id'] ?>"/><button type="submit" class="req-btn req-accept">✓ Accept</button></form>
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject"/><input type="hidden" name="request_id" value="<?= $req['id'] ?>"/><button type="submit" class="req-btn req-reject" onclick="return confirm('Reject this request?')">✗ Reject</button></form>
                  <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">—</span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- NOTIFICATIONS PAGE (NEW)                                         -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'notifications' ? 'active' : '' ?>" id="page-notifications">

        <div class="section-header" style="margin-bottom:18px">
          <div>
            <div class="section-title">Notifications</div>
            <div class="section-sub"><?= $unread_count ?> unread alert<?= $unread_count !== 1 ? 's' : '' ?></div>
          </div>
          <?php if ($unread_count > 0): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="mark_read"/>
              <input type="hidden" name="notif_id" value="0"/>
              <button type="submit" class="btn btn-ghost" style="font-size:12px"><i class="ti ti-checks"></i> Mark all read</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
          <div class="empty-state">
            <i class="ti ti-bell-off"></i>
            <div class="empty-title">No notifications yet</div>
            <div class="empty-sub">You'll be notified about bookings, seat updates, and ride alerts here</div>
          </div>
        <?php else: ?>
          <div class="notif-list">
            <?php foreach ($notifications as $n):
              $meta = notif_meta($n['type']);
              $routeCtx = (!empty($n['start_location']) && !empty($n['destination']))
                ? htmlspecialchars($n['start_location']) . ' → ' . htmlspecialchars($n['destination'])
                : '';
              $timeAgo = (time() - strtotime($n['created_at']));
              $timeLabel = $timeAgo < 60 ? 'Just now'
                : ($timeAgo < 3600 ? floor($timeAgo/60) . ' min ago'
                : ($timeAgo < 86400 ? floor($timeAgo/3600) . ' hr ago'
                : date('d M Y', strtotime($n['created_at']))));
            ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
              <div class="notif-icon" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>">
                <i class="ti <?= $meta['icon'] ?>"></i>
              </div>
              <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <?php if ($routeCtx): ?>
                  <div style="font-size:11px;color:var(--blue);margin-top:4px"><i class="ti ti-route" style="font-size:11px"></i> <?= $routeCtx ?></div>
                <?php endif; ?>
                <div class="notif-time"><i class="ti ti-clock" style="font-size:11px"></i> <?= $timeLabel ?></div>
                <?php if (!$n['is_read']): ?>
                  <div class="notif-actions">
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="mark_read"/>
                      <input type="hidden" name="notif_id" value="<?= $n['id'] ?>"/>
                      <button type="submit" class="btn btn-ghost" style="font-size:11px;padding:4px 10px"><i class="ti ti-check"></i> Mark read</button>
                    </form>
                    <?php if ($n['ride_id']): ?>
                      <button class="btn btn-ghost" onclick="goPage('rides')" style="font-size:11px;padding:4px 10px"><i class="ti ti-eye"></i> View ride</button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!$n['is_read']): ?><div class="notif-unread-dot"></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- EARNINGS PAGE (unchanged)                                         -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'earnings' ? 'active' : '' ?>" id="page-earnings">
        <?php
        $totalEarnedAll = array_sum(array_map(fn($r) => $r['price'] * $r['accepted_count'], $past_rides));
        $totalPaxAll    = array_sum(array_column($past_rides, 'accepted_count'));
        $avgPerRide     = count($past_rides) > 0 ? round($totalEarnedAll / count($past_rides)) : 0;
        $weekDays       = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        $weekAmounts    = [0,0,0,0,0,0,0];
        foreach ($past_rides as $r) {
            $dow = (int)date('N', strtotime($r['ride_date'])) - 1;
            if ($dow >= 0 && $dow < 7) $weekAmounts[$dow] += $r['price'] * $r['accepted_count'];
        }
        $maxWeek = max($weekAmounts) ?: 1;
        ?>
        <div class="stats-grid" style="margin-bottom:22px">
          <div class="stat-card" style="border-color:rgba(63,185,80,.3)"><div class="stat-label">Today's Earnings</div><div class="stat-value" style="color:var(--green)">Rs. 0</div><div class="stat-delta">No rides today yet</div></div>
          <div class="stat-card" style="border-color:rgba(88,166,255,.3)"><div class="stat-label">This Week</div><div class="stat-value" style="color:var(--blue)">Rs. <?= number_format($totalEarnedAll) ?></div><div class="stat-delta">Based on all completed rides</div></div>
          <div class="stat-card" style="border-color:rgba(255,107,53,.3)"><div class="stat-label">Total Earned</div><div class="stat-value" style="color:var(--accent)">Rs. <?= number_format($totalEarnedAll) ?></div><div class="stat-delta delta-up"><i class="ti ti-trending-up" style="font-size:12px"></i> All time</div></div>
          <div class="stat-card" style="border-color:rgba(227,179,65,.3)"><div class="stat-label">Avg. per Ride</div><div class="stat-value" style="color:var(--amber)">Rs. <?= number_format($avgPerRide) ?></div><div class="stat-delta">Across <?= count($past_rides) ?> completed rides</div></div>
        </div>
        <div class="earnings-grid" style="margin-bottom:22px">
          <div class="earnings-card">
            <div class="section-header" style="margin-bottom:4px"><div class="section-title">Weekly Breakdown</div><div style="font-size:11px;color:var(--text3)">Rs. <?= number_format($totalEarnedAll) ?></div></div>
            <div class="mini-chart" style="height:80px">
              <?php foreach ($weekAmounts as $amt): ?>
                <div class="bar <?= $amt == max($weekAmounts) && $amt > 0 ? 'highlight' : '' ?>" style="height:<?= $maxWeek > 0 ? max(8, round(($amt / $maxWeek) * 80)) . 'px' : '8px' ?>" title="Rs. <?= number_format($amt) ?>"></div>
              <?php endforeach; ?>
            </div>
            <div class="chart-labels"><?php foreach ($weekDays as $d): ?><div class="chart-label"><?= $d ?></div><?php endforeach; ?></div>
          </div>
          <div class="earnings-card">
            <div class="section-header" style="margin-bottom:14px"><div class="section-title">By Vehicle Type</div></div>
            <?php
            $byType = ['car' => 0, 'van' => 0, 'three-wheeler' => 0];
            foreach ($past_rides as $r) { $t = $r['vehicle_type']; if (isset($byType[$t])) $byType[$t] += $r['price'] * $r['accepted_count']; }
            foreach ($byType as $t => $amt):
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--surface2);display:flex;align-items:center;justify-content:center;color:var(--text2);font-size:15px;flex-shrink:0"><i class="ti <?= vIcon($t) ?>"></i></div>
              <div style="flex:1">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:12px;color:var(--text2)"><?= vLabel($t) ?></span><span style="font-size:12px;font-weight:600;color:var(--text)">Rs. <?= number_format($amt) ?></span></div>
                <div style="height:4px;background:var(--surface3);border-radius:2px"><div style="height:4px;background:var(--accent);border-radius:2px;width:<?= $totalEarnedAll > 0 ? round(($amt / $totalEarnedAll) * 100) : 0 ?>%"></div></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="section-header"><div class="section-title">Earning History</div></div>
        <?php if (empty($past_rides)): ?>
          <div class="empty-state"><i class="ti ti-wallet"></i><div class="empty-title">No earnings yet</div><div class="empty-sub">Complete rides to see your earnings here</div></div>
        <?php else: ?>
          <div class="history-table">
            <div class="h-head"><div>Route</div><div>Date</div><div>Passengers</div><div>Price/Seat</div><div>Status</div><div>Earned</div></div>
            <?php foreach ($past_rides as $r): $earned = $r['price'] * $r['accepted_count']; ?>
            <div class="h-row">
              <div style="font-weight:500"><?= htmlspecialchars($r['start_location']) ?> → <?= htmlspecialchars($r['destination']) ?></div>
              <div style="color:var(--text2)"><?= date('d M Y', strtotime($r['ride_date'])) ?></div>
              <div style="color:var(--text2)"><?= $r['accepted_count'] ?> pax</div>
              <div style="color:var(--text2)">Rs. <?= number_format($r['price']) ?></div>
              <div><?php $b = rideBadge($r['status']); ?><span class="ride-badge <?= $b['class'] ?>"><?= $b['label'] ?></span></div>
              <div class="h-earned"><?= $earned > 0 ? 'Rs. ' . number_format($earned) : '—' ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ──────────────────────────────────────────────────────────────── -->
      <!-- SUPPORT PAGE (unchanged)                                          -->
      <!-- ──────────────────────────────────────────────────────────────── -->
      <div class="page <?= $current_page === 'support' ? 'active' : '' ?>" id="page-support">
        <div class="profile-grid">
          <div class="profile-card">
            <h3>Contact Support</h3>
            <div style="font-size:13px;color:var(--text3);margin-bottom:18px;line-height:1.6">If you have an issue with a ride, payment, or your account, our team is here to help.</div>
            <div class="profile-row"><span class="pr-label"><i class="ti ti-mail" style="margin-right:5px"></i>Email</span><span class="pr-val"><a href="mailto:support@ridesharelk.com" style="color:var(--accent)">support@ridesharelk.com</a></span></div>
            <div class="profile-row"><span class="pr-label"><i class="ti ti-phone" style="margin-right:5px"></i>Hotline</span><span class="pr-val">+94 11 985 2356</span></div>
            <div class="profile-row"><span class="pr-label"><i class="ti ti-clock" style="margin-right:5px"></i>Hours</span><span class="pr-val">Daily 7 AM – 10 PM</span></div>
          </div>
          <div class="profile-card">
            <h3>Quick Help</h3>
            <?php
            $faqs = [
              ['How do I cancel a ride?', 'Go to My Rides, find the ride, and click the red × cancel button before the departure time.'],
              ['When will I get paid?', 'Earnings are processed within 2–3 business days after the ride is completed.'],
              ['Passenger didn\'t show up?', 'Mark the ride as completed. No-show policy protects your rating.'],
              ['How is my rating calculated?', 'Based on passenger feedback after each completed ride.'],
            ];
            foreach ($faqs as [$q, $a]):
            ?>
            <div style="padding:10px 12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);margin-bottom:8px">
              <div style="font-size:13px;font-weight:500;margin-bottom:4px"><?= $q ?></div>
              <div style="font-size:12px;color:var(--text3);line-height:1.5"><?= $a ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </main>
</div>

<!-- POST RIDE MODAL (UPDATED: seats field now populates total_seats) -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="ti ti-route" style="color:var(--accent)"></i> Post a New Ride</div>
      <div class="modal-close" onclick="closeModal()"><i class="ti ti-x"></i></div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="post_ride"/>
      <div class="modal-body">
        <?php if ($ride_error): ?><div class="modal-alert"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($ride_error) ?></div><?php endif; ?>
        <?php if ($ride_success): ?><div class="modal-success"><i class="ti ti-check"></i> <?= htmlspecialchars($ride_success) ?></div><?php endif; ?>
        <div class="form-row">
          <div class="form-group"><label>Pickup Location</label><input type="text" name="from" placeholder="e.g. Colombo Fort" required value="<?= htmlspecialchars($_POST['from'] ?? '') ?>"/></div>
          <div class="form-group"><label>Destination</label><input type="text" name="to" placeholder="e.g. Kandy" required value="<?= htmlspecialchars($_POST['to'] ?? '') ?>"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Date</label><input type="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['date'] ?? '') ?>"/></div>
          <div class="form-group"><label>Departure Time</label><input type="time" name="time" required value="<?= htmlspecialchars($_POST['time'] ?? '') ?>"/></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <!-- "seats" = total seats offered; stored in both seats_available and total_seats -->
            <label>Total Seats Available</label>
            <select name="seats" required>
              <?php for ($i = 1; $i <= 8; $i++): ?>
                <option value="<?= $i ?>" <?= (($_POST['seats'] ?? 3) == $i) ? 'selected' : '' ?>><?= $i ?> seat<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Vehicle Type</label>
            <select name="vehicle_type" required>
              <option value="car"           <?= (($_POST['vehicle_type'] ?? 'car') === 'car')            ? 'selected' : '' ?>>🚗 Car</option>
              <option value="van"           <?= (($_POST['vehicle_type'] ?? '') === 'van')               ? 'selected' : '' ?>>🚐 Van</option>
              <option value="three-wheeler" <?= (($_POST['vehicle_type'] ?? '') === 'three-wheeler')     ? 'selected' : '' ?>>🛺 Three-Wheeler</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Vehicle Model</label><input type="text" name="vehicle_model" placeholder="e.g. Toyota Aqua" required value="<?= htmlspecialchars($_POST['vehicle_model'] ?? $driver['vehicle_model'] ?? '') ?>"/></div>
          <div class="form-group"><label>Price per Seat (Rs.)</label><input type="number" name="price" placeholder="e.g. 850" min="1" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"/></div>
        </div>
        <div class="form-row single">
          <div class="form-group"><label>Notes (Optional)</label><textarea name="notes" rows="2" placeholder="Pickup point details, luggage policy, AC availability..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Post Ride</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Page navigation ────────────────────────────────────────────────────────
const currentPage = '<?= $current_page ?>';
function goPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  const titles = { dashboard:'Dashboard', rides:'My Rides', bookings:'Bookings',
                   earnings:'Earnings', notifications:'Notifications', support:'Support' };
  document.getElementById('page-title').textContent = titles[id] || 'Dashboard';
  history.replaceState(null, '', '?page=' + id);
}

// ── Tab bar ────────────────────────────────────────────────────────────────
function switchTab(el, id) {
  const parent = el.closest('.page');
  parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  parent.querySelectorAll('[id^="upcoming-"],[id^="past-"],[id^="req-"]')
        .forEach(e => e.style.display = 'none');
  document.getElementById(id).style.display = 'block';
}

// ── Post ride modal ────────────────────────────────────────────────────────
function openModal()  { document.getElementById('modal-overlay').classList.add('open'); }
function closeModal() { document.getElementById('modal-overlay').classList.remove('open'); }
document.getElementById('modal-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
<?php if ($ride_error || $ride_success): ?>openModal();<?php endif; ?>

// ── File upload label helpers ──────────────────────────────────────────────
function bindFile(a, b) {
  const inp = document.getElementById(a), lbl = document.getElementById(b);
  if (!inp) return;
  inp.addEventListener('change', () => {
    if (inp.files.length) { lbl.textContent = inp.files[0].name; lbl.style.display = 'block'; }
  });
}
bindFile('inp-licence', 'fn-licence');
bindFile('inp-vehicle', 'fn-vehicle');
</script>
</body>
</html>