<?php
session_start();
require_once 'db/connection.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'passenger';
    if ($role === 'driver') { header('Location: driver_dashboard.php'); exit; }
    if ($role === 'admin')  { header('Location: admin.php');           exit; }
    header('Location: index.php'); exit;
}

$role     = ($_GET['role'] ?? '') === 'driver' ? 'driver' : 'passenger';
$redirect = $_GET['redirect'] ?? '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $role     = ($_POST['role'] ?? '') === 'driver' ? 'driver' : 'passenger';
    $redirect = $_POST['redirect']      ?? '';

    if (!$name || !$email || !$password || !$phone) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'That email address is already registered.';
        } else {
            $hash          = password_hash($password, PASSWORD_DEFAULT);
            $driver_status = ($role === 'driver') ? 'unverified' : null;
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, phone, password, role, driver_status, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ');
            $stmt->execute([$name, $email, $phone, $hash, $role, $driver_status]);
            $_SESSION['user_id']   = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            if ($redirect) { header('Location: ' . $redirect); exit; }
            header('Location: dashboard.php'); exit;
        }
    }
}

$is_driver = ($role === 'driver');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_driver ? 'Become a Driver' : 'Create Account' ?> — RideShare LK</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
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
  --amber:       #F59E0B;
  --red:         #EF4444;
  --gradient:    linear-gradient(135deg,#7050FF 0%,#5B8EFF 100%);
  --glow:        0 0 40px rgba(112,80,255,0.20);
  --shadow-lg:   0 16px 48px rgba(0,0,0,0.50);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--ink);color:var(--text);
  min-height:100vh;overflow-x:hidden;font-size:15px;line-height:1.6;
  display:flex;flex-direction:column;
}
body::before{
  content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(112,80,255,0.22) 1.5px,transparent 1.5px);
  background-size:28px 28px;opacity:0.45;
}
body::after{
  content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:
    radial-gradient(ellipse 70% 50% at 80% -10%,rgba(112,80,255,0.14) 0%,transparent 60%),
    radial-gradient(ellipse 50% 40% at 10% 80%, rgba(91,142,255,0.09)  0%,transparent 60%);
}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--ink-soft)}
::-webkit-scrollbar-thumb{background:var(--accent);border-radius:99px}

/* NAVBAR */
.navbar{
  position:sticky;top:0;z-index:500;height:64px;
  background:rgba(10,10,15,0.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 56px;animation:slideDown .5s ease both;
}
@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;font-size:18px;color:var(--text);letter-spacing:-0.02em}
.nav-logo{width:34px;height:34px;background:var(--gradient);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 4px 14px rgba(112,80,255,0.38)}
.nav-brand-name{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.nav-links{display:flex;align-items:center;gap:4px}
.nav-link{padding:7px 14px;font-size:13.5px;font-weight:500;color:var(--text-2);text-decoration:none;border-radius:99px;transition:all .2s}
.nav-link:hover{color:var(--text);background:var(--surface-2)}
.nav-link-driver{color:#A78BFF!important;background:var(--accent-glow)!important;border:1px solid rgba(112,80,255,0.20)}
.nav-link-driver:hover{background:rgba(112,80,255,0.25)!important}
.nav-actions{display:flex;align-items:center;gap:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 20px;border-radius:99px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:600;cursor:pointer;transition:all .22s;border:none;text-decoration:none}
.btn-primary{background:var(--gradient);color:white;box-shadow:0 4px 18px rgba(112,80,255,0.40)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.55)}
.btn-ghost{background:var(--surface-2);color:var(--text-2);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text);background:var(--surface-3)}
.btn-sm{padding:7px 15px;font-size:12.5px}

/* LAYOUT */
.layout{display:grid;grid-template-columns:1fr 520px;flex:1;position:relative;z-index:1}

/* LEFT PANEL */
.panel-left{
  padding:60px 64px;display:flex;flex-direction:column;
  justify-content:space-between;position:relative;overflow:hidden;
}
.orb-1{position:absolute;top:-100px;left:-100px;width:380px;height:380px;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(112,80,255,0.12),transparent 70%);animation:orbFloat 8s ease-in-out infinite alternate}
.orb-2{position:absolute;bottom:-80px;right:-80px;width:280px;height:280px;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(91,142,255,0.09),transparent 70%);animation:orbFloat 11s ease-in-out infinite alternate-reverse}
@keyframes orbFloat{from{transform:translate(0,0) scale(1)}to{transform:translate(18px,-18px) scale(1.05)}}

.left-logo{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;font-size:20px;color:var(--text);animation:fadeUp .6s ease both}
.left-logo-icon{width:40px;height:40px;background:var(--gradient);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 8px 24px rgba(112,80,255,0.36)}
.left-logo-name{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.left-body{max-width:480px;display:flex;flex-direction:column;justify-content:center;flex:1;padding:48px 0}
.left-badge{display:inline-flex;align-items:center;gap:8px;background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);border-radius:99px;padding:6px 16px;font-size:12px;font-weight:700;color:#A78BFF;margin-bottom:24px;animation:fadeUp .7s ease both}
.badge-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}
.left-title{font-size:clamp(28px,3.8vw,46px);font-weight:800;line-height:1.08;letter-spacing:-0.03em;margin-bottom:18px;animation:fadeUp .8s ease both}
.left-title .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.left-sub{font-size:15px;color:var(--text-2);line-height:1.72;margin-bottom:0;max-width:440px;animation:fadeUp .9s ease both}
.left-foot{font-size:12px;color:var(--text-3);animation:fadeUp 1s ease both}

/* DRIVER PERKS */
.driver-perks{list-style:none;display:flex;flex-direction:column;gap:11px;margin-top:28px;animation:fadeUp 1s ease both}
.driver-perk{display:flex;align-items:center;gap:12px;font-size:14px;color:var(--text-2)}
.perk-check{width:22px;height:22px;border-radius:6px;background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);display:flex;align-items:center;justify-content:center;font-size:11px;color:#A78BFF;flex-shrink:0}

/* RIGHT PANEL */
.panel-right{
  display:flex;align-items:center;justify-content:center;
  padding:40px 48px;background:rgba(17,17,24,0.4);border-left:1px solid var(--border);
}
.form-card{
  width:100%;background:var(--surface);border:1px solid var(--border);
  border-radius:24px;padding:36px;
  box-shadow:var(--shadow-lg),var(--glow);
  position:relative;overflow:hidden;
  animation:cardEnter .8s cubic-bezier(.2,.8,.2,1) both;
}
.form-card::before{content:'';position:absolute;inset:-1px;border-radius:25px;background:linear-gradient(135deg,rgba(112,80,255,0.28),transparent 55%);z-index:-1}
@keyframes cardEnter{from{opacity:0;transform:translateY(30px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

/* DRIVER STRIP */
.driver-strip{
  display:flex;align-items:center;gap:16px;
  background:var(--gradient);border-radius:16px;padding:18px 20px;
  margin-bottom:20px;position:relative;overflow:hidden;
}
.driver-strip::before{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;background:rgba(255,255,255,0.08);border-radius:50%}
.driver-strip-icon{font-size:32px;flex-shrink:0}
.driver-strip-title{font-size:16px;font-weight:800;color:white;margin-bottom:3px}
.driver-strip-sub{font-size:12px;color:rgba(255,255,255,0.80);line-height:1.5}

/* VERIFY BOX */
.verify-box{
  background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.22);
  border-radius:12px;padding:14px 16px;margin-bottom:20px;
}
.verify-head{font-size:13px;font-weight:700;color:var(--amber);margin-bottom:6px}
.verify-body{font-size:12.5px;color:var(--text-2);line-height:1.65}
.verify-body ul{margin:6px 0 0 16px;display:flex;flex-direction:column;gap:3px}
.verify-body strong{color:var(--text)}

.fc-label{font-size:10px;font-weight:700;color:#A78BFF;text-transform:uppercase;letter-spacing:.9px;margin-bottom:6px}
.fc-title{font-size:24px;font-weight:800;letter-spacing:-0.02em;margin-bottom:6px}
.fc-sub{font-size:13px;color:var(--text-2);margin-bottom:22px}

.alert{background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.22);border-radius:12px;padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:18px;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}

.field{margin-bottom:14px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field-label{display:block;font-size:10.5px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
.field-input{
  width:100%;padding:11px 14px;
  border:1.5px solid var(--border);border-radius:11px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:500;
  color:var(--text);background:var(--surface-2);
  transition:border .2s,box-shadow .2s;outline:none;-webkit-appearance:none;
}
.field-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(112,80,255,0.12)}
.field-input::placeholder{color:var(--text-3);font-weight:400}
.btn-submit{
  width:100%;padding:13px;margin-top:6px;
  background:var(--gradient);color:white;border:none;border-radius:14px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14.5px;font-weight:700;
  cursor:pointer;transition:all .22s;
  box-shadow:0 4px 18px rgba(112,80,255,0.40);position:relative;overflow:hidden;
}
.btn-submit::before{content:'';position:absolute;top:0;left:-120%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.22),transparent);transition:.7s}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.55)}
.btn-submit:hover::before{left:120%}
.bottom-link{text-align:center;margin-top:18px;font-size:13.5px;color:var(--text-2)}
.bottom-link a{color:#A78BFF;font-weight:600;text-decoration:none;transition:color .2s}
.bottom-link a:hover{color:#C4B5FD}

/* FOOTER */
.footer{background:var(--ink-soft);border-top:1px solid var(--border);padding:48px 72px 28px;position:relative;z-index:1}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;margin-bottom:44px}
.footer-brand{font-size:19px;font-weight:800;color:var(--text);margin-bottom:11px}
.footer-desc{font-size:13px;color:var(--text-3);line-height:1.7;margin-bottom:20px}
.footer-heading{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.9px;margin-bottom:14px}
.footer-link{display:block;font-size:13px;color:var(--text-3);margin-bottom:9px;text-decoration:none;transition:color .2s}
.footer-link:hover{color:var(--text)}
.footer-link-accent{color:#A78BFF!important;font-weight:600}
.footer-socials{display:flex;gap:8px}
.social-btn{width:32px;height:32px;border-radius:8px;background:var(--surface);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer;color:var(--text-2);transition:all .2s}
.social-btn:hover{background:var(--accent-glow);border-color:rgba(112,80,255,0.3);color:var(--text)}
.footer-bottom{border-top:1px solid var(--border);padding-top:20px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-3)}

@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:960px){.layout{grid-template-columns:1fr}.panel-left{display:none}.panel-right{border-left:none;padding:32px 24px}}
@media(max-width:640px){.navbar{padding:0 18px}.nav-links{display:none}.footer{padding:40px 18px 22px}.footer-grid{grid-template-columns:1fr}.field-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">🚗</div>
    <span class="nav-brand-name">RideShare LK</span>
  </a>
  <div class="nav-links">
    <a href="index.php"   class="nav-link">Home</a>
    <a href="results.php" class="nav-link">Find Rides</a>
    <a href="about.php"   class="nav-link">About</a>
    <a href="contact.php" class="nav-link">Contact</a>
    <a href="register.php?role=driver" class="nav-link nav-link-driver">🚗 Become Driver</a>
  </div>
  <div class="nav-actions">
    <?php if ($is_driver): ?>
      <a href="register.php"            class="btn btn-ghost btn-sm">Passenger?</a>
      <a href="register.php?role=driver" class="btn btn-primary btn-sm">Become a Driver</a>
    <?php else: ?>
      <a href="login.php"    class="btn btn-ghost btn-sm">Log in</a>
      <a href="register.php" class="btn btn-primary btn-sm">Sign up free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- LAYOUT -->
<div class="layout">
  <!-- LEFT PANEL -->
  <div class="panel-left">
    <div class="orb-1"></div>
    <div class="orb-2"></div>

    <a href="index.php" class="left-logo">
      <div class="left-logo-icon">🚗</div>
      <span class="left-logo-name">RideShare LK</span>
    </a>

    <div class="left-body">
      <div class="left-badge">
        <div class="badge-dot"></div>
        🛡️ Verified Eco Platform
      </div>
      <?php if ($is_driver): ?>
        <h1 class="left-title">Turn empty car seats into <span class="grad">extra income</span></h1>
        <p class="left-sub">Cover your transit fuel bills across Sri Lanka, build social networks, and offset regional carbon output emissions seamlessly on your schedule.</p>
        <ul class="driver-perks">
          <li class="driver-perk"><div class="perk-check">✓</div>Post a ride in under 2 minutes</li>
          <li class="driver-perk"><div class="perk-check">✓</div>Share seats on routes you already drive</li>
          <li class="driver-perk"><div class="perk-check">✓</div>Offset fuel and vehicle running costs</li>
        </ul>
      <?php else: ?>
        <h1 class="left-title">Join thousands of commuter <span class="grad">matches daily</span></h1>
        <p class="left-sub">Experience fully safe, interactive travel coordinates. Create your account parameters to unlock shared transport choices dynamically.</p>
      <?php endif; ?>
    </div>

    <div class="left-foot">© <?= date('Y') ?> RideShare LK · Made with ❤️ in Sri Lanka</div>
  </div>

  <!-- RIGHT PANEL (FORM) -->
  <div class="panel-right">
    <div class="form-card">

      <?php if ($is_driver): ?>
        <div class="driver-strip">
          <div class="driver-strip-icon">🚗</div>
          <div>
            <div class="driver-strip-title">Become a RideShare Driver</div>
            <div class="driver-strip-sub">Offer seats on your existing journeys and earn money along the way.</div>
          </div>
        </div>
        <div class="verify-box">
          <div class="verify-head">⚠️ Verification Required</div>
          <div class="verify-body">
            After sign-up, you'll need to upload:
            <ul>
              <li>Valid Driving Licence credentials</li>
              <li>Clear vehicle identity photos</li>
            </ul>
            Your profile will be authorized within <strong>24 hours</strong>.
          </div>
        </div>
      <?php else: ?>
        <div class="fc-label">Platform Registry</div>
        <div class="fc-title">Create your account</div>
        <div class="fc-sub">Join your local neighbourhood ride-sharing network.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="role"     value="<?= $role ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

        <div class="field">
          <label class="field-label" for="name">Full name</label>
          <input class="field-input" type="text" id="name" name="name"
                 placeholder="e.g. Saman Perera" required autofocus
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="email">Email address</label>
            <input class="field-input" type="email" id="email" name="email"
                   placeholder="you@email.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="field-label" for="phone">Phone number</label>
            <input class="field-input" type="tel" id="phone" name="phone"
                   placeholder="07X XXX XXXX" required
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="password">Password</label>
            <input class="field-input" type="password" id="password" name="password"
                   placeholder="Min. 6 characters" required>
          </div>
          <div class="field">
            <label class="field-label" for="confirm">Confirm password</label>
            <input class="field-input" type="password" id="confirm" name="confirm"
                   placeholder="Repeat password" required>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <?= $is_driver ? '🚗 Sign up as Driver →' : 'Create Account →' ?>
        </button>
      </form>

      <div class="bottom-link">
        Already have an account? <a href="login.php">Log in here</a>
      </div>

    </div>
  </div>
</div>

<!-- FOOTER -->
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
      <a class="footer-link footer-link-accent" href="register.php?role=driver">Become a Driver</a>
      <a class="footer-link" href="about.php">About Us</a>
    </div>
    <div>
      <div class="footer-heading">Account</div>
      <a class="footer-link" href="login.php">Sign In</a>
      <a class="footer-link" href="register.php">Create Account</a>
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

</body>
</html>