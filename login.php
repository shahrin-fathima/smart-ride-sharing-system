<?php
session_start();
require_once 'db/connection.php';

if(isset($_SESSION['user_id'])){
    $role = $_SESSION['user_role'] ?? 'passenger';
    if($role === 'driver') { header('Location: driver_dashboard.php'); exit; }
    if($role === 'admin')  { header('Location: admin/index.php');      exit; }
    header('Location: index.php'); exit;
}

$redirect = $_GET['redirect'] ?? '';
$error    = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $redirect = $_POST['redirect']      ?? '';

    if(!$email || !$password){
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email=?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if(!$user || !password_verify($password, $user['password'])){
            $error = 'Incorrect email or password.';
        } elseif($user['is_suspended']){
            $error = 'Your account has been suspended. Contact support.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            if($user['role'] === 'driver') { header('Location: driver_dashboard.php'); exit; }
            if($user['role'] === 'admin')  { header('Location: admin/index.php');      exit; }
            if($redirect)                  { header('Location: '.$redirect);           exit; }
            header('Location: index.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — RideShare LK</title>
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
  background:var(--ink);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
  font-size:15px;
  line-height:1.6;
}
/* Dot grid background */
body::before{
  content:'';
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(112,80,255,0.22) 1.5px,transparent 1.5px);
  background-size:28px 28px;
  opacity:0.45;
}
body::after{
  content:'';
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:
    radial-gradient(ellipse 70% 50% at 80% -10%,rgba(112,80,255,0.14) 0%,transparent 60%),
    radial-gradient(ellipse 50% 40% at 10% 80%, rgba(91,142,255,0.09)  0%,transparent 60%);
}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--ink-soft)}
::-webkit-scrollbar-thumb{background:var(--accent);border-radius:99px}

/* NAVBAR */
.navbar{
  position:sticky;top:0;z-index:500;
  height:64px;
  background:rgba(10,10,15,0.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 56px;
  animation:slideDown .5s ease both;
}
@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
.nav-brand{
  display:flex;align-items:center;gap:10px;
  text-decoration:none;font-weight:800;font-size:18px;color:var(--text);
  letter-spacing:-0.02em;
}
.nav-logo{
  width:34px;height:34px;background:var(--gradient);border-radius:9px;
  display:flex;align-items:center;justify-content:center;font-size:17px;
  box-shadow:0 4px 14px rgba(112,80,255,0.38);transition:transform .3s;
}
.nav-logo:hover{transform:rotate(-8deg) scale(1.1)}
.nav-brand-name{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.nav-links{display:flex;align-items:center;gap:4px}
.nav-link{
  padding:7px 14px;font-size:13.5px;font-weight:500;color:var(--text-2);
  text-decoration:none;border-radius:99px;transition:all .2s;
}
.nav-link:hover{color:var(--text);background:var(--surface-2)}
.nav-link-driver{
  color:#A78BFF!important;background:var(--accent-glow)!important;
  border:1px solid rgba(112,80,255,0.20);
}
.nav-link-driver:hover{background:rgba(112,80,255,0.25)!important}
.nav-actions{display:flex;align-items:center;gap:8px}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:7px;
  padding:9px 20px;border-radius:99px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:600;
  cursor:pointer;transition:all .22s;border:none;text-decoration:none;
}
.btn-primary{background:var(--gradient);color:white;box-shadow:0 4px 18px rgba(112,80,255,0.40)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.55)}
.btn-ghost{background:var(--surface-2);color:var(--text-2);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text);background:var(--surface-3)}
.btn-outline{background:transparent;color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:7px 15px;font-size:12.5px}
.avatar{
  width:34px;height:34px;border-radius:50%;background:var(--gradient);
  border:2px solid rgba(112,80,255,0.28);
  display:flex;align-items:center;justify-content:center;
  color:white;font-weight:700;font-size:13px;
  text-decoration:none;flex-shrink:0;transition:transform .2s;
}
.avatar:hover{transform:scale(1.08)}

/* LAYOUT */
.layout{
  display:grid;grid-template-columns:1fr 480px;
  min-height:calc(100vh - 64px);
  position:relative;z-index:1;
}

/* LEFT PANEL */
.panel-left{
  padding:60px 64px;
  display:flex;flex-direction:column;justify-content:space-between;
  position:relative;overflow:hidden;
}
.orb-1{
  position:absolute;top:-100px;left:-100px;
  width:400px;height:400px;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,rgba(112,80,255,0.12),transparent 70%);
  animation:orbFloat 8s ease-in-out infinite alternate;
}
.orb-2{
  position:absolute;bottom:-80px;right:-80px;
  width:300px;height:300px;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,rgba(91,142,255,0.09),transparent 70%);
  animation:orbFloat 11s ease-in-out infinite alternate-reverse;
}
@keyframes orbFloat{from{transform:translate(0,0) scale(1)}to{transform:translate(18px,-18px) scale(1.05)}}

.left-logo{
  display:flex;align-items:center;gap:10px;
  text-decoration:none;font-weight:800;font-size:20px;color:var(--text);
  animation:fadeUp .6s ease both;
}
.left-logo-icon{
  width:40px;height:40px;background:var(--gradient);border-radius:11px;
  display:flex;align-items:center;justify-content:center;font-size:18px;
  box-shadow:0 8px 24px rgba(112,80,255,0.36);
}
.left-logo-name{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}

.left-body{max-width:480px}
.left-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);
  border-radius:99px;padding:6px 16px;
  font-size:12px;font-weight:700;color:#A78BFF;
  margin-bottom:24px;animation:fadeUp .7s ease both;
}
.badge-dot{
  width:7px;height:7px;border-radius:50%;background:var(--accent);
  animation:pulse 2s infinite;
}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}

.left-title{
  font-size:clamp(32px,4vw,50px);font-weight:800;line-height:1.08;
  letter-spacing:-0.03em;margin-bottom:18px;
  animation:fadeUp .8s ease both;
}
.left-title .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.left-sub{
  font-size:15px;color:var(--text-2);line-height:1.72;
  margin-bottom:36px;max-width:440px;
  animation:fadeUp .9s ease both;
}
.features{display:flex;flex-direction:column;gap:12px;animation:fadeUp 1s ease both}
.feat{
  display:flex;align-items:center;gap:14px;
  padding:14px 16px;border-radius:16px;
  background:rgba(255,255,255,0.04);
  border:1px solid var(--border);
  font-size:13.5px;color:var(--text-2);
  transition:all .25s;
}
.feat:hover{background:var(--surface-2);border-color:rgba(112,80,255,0.22);color:var(--text);transform:translateX(4px)}
.feat-icon{
  width:40px;height:40px;border-radius:12px;background:var(--gradient);
  display:flex;align-items:center;justify-content:center;font-size:17px;
  box-shadow:0 6px 18px rgba(112,80,255,0.30);flex-shrink:0;
}
.left-foot{font-size:12px;color:var(--text-3);margin-top:48px;animation:fadeUp 1.1s ease both}

/* RIGHT PANEL */
.panel-right{
  display:flex;align-items:center;justify-content:center;
  padding:40px 48px;background:rgba(17,17,24,0.4);
  border-left:1px solid var(--border);
}
.form-card{
  width:100%;background:var(--surface);
  border:1px solid var(--border);border-radius:24px;
  padding:40px;
  box-shadow:var(--shadow-lg),var(--glow);
  position:relative;overflow:hidden;
  animation:cardEnter .8s cubic-bezier(.2,.8,.2,1) both;
}
.form-card::before{
  content:'';position:absolute;inset:-1px;border-radius:25px;
  background:linear-gradient(135deg,rgba(112,80,255,0.28),transparent 55%);
  z-index:-1;
}
@keyframes cardEnter{from{opacity:0;transform:translateY(30px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

.fc-label{
  font-size:10px;font-weight:700;color:#A78BFF;
  text-transform:uppercase;letter-spacing:.9px;margin-bottom:6px;
}
.fc-title{font-size:26px;font-weight:800;letter-spacing:-0.02em;margin-bottom:6px}
.fc-sub{font-size:13.5px;color:var(--text-2);margin-bottom:24px}

/* NOTICES */
.notice{
  background:rgba(91,142,255,0.10);border:1px solid rgba(91,142,255,0.22);
  border-radius:12px;padding:12px 16px;
  font-size:13px;color:var(--accent-2);margin-bottom:18px;
}
.alert{
  background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.22);
  border-radius:12px;padding:12px 16px;
  font-size:13px;color:var(--red);margin-bottom:18px;
  animation:shake .4s ease;
}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}

/* FORM */
.field{margin-bottom:16px}
.field-label{
  display:block;font-size:10.5px;font-weight:700;color:var(--text-3);
  text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px;
}
.field-input{
  width:100%;padding:12px 16px;
  border:1.5px solid var(--border);border-radius:12px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:500;
  color:var(--text);background:var(--surface-2);
  transition:border .2s,box-shadow .2s;outline:none;-webkit-appearance:none;
}
.field-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(112,80,255,0.12)}
.field-input::placeholder{color:var(--text-3);font-weight:400}
.btn-submit{
  width:100%;padding:14px;margin-top:6px;
  background:var(--gradient);color:white;border:none;border-radius:14px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;
  cursor:pointer;transition:all .22s;
  box-shadow:0 4px 18px rgba(112,80,255,0.40);position:relative;overflow:hidden;
}
.btn-submit::before{
  content:'';position:absolute;top:0;left:-120%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.22),transparent);
  transition:.7s;
}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.55)}
.btn-submit:hover::before{left:120%}

.divider{
  display:flex;align-items:center;gap:12px;
  margin:24px 0;font-size:11px;font-weight:700;
  color:var(--text-3);text-transform:uppercase;letter-spacing:1px;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}

.link-btns{display:flex;flex-direction:column;gap:10px}
.link-btn{
  display:flex;align-items:center;justify-content:center;
  text-decoration:none;padding:12px 16px;border-radius:12px;
  font-size:13px;font-weight:600;color:var(--text-2);
  background:var(--surface-2);border:1px solid var(--border);
  transition:all .25s;
}
.link-btn:hover{color:var(--text);background:var(--surface-3);border-color:rgba(112,80,255,0.22);transform:translateY(-2px)}
.link-btn-driver{
  background:var(--accent-glow);border-color:rgba(112,80,255,0.22);
  color:#A78BFF;
}
.link-btn-driver:hover{background:rgba(112,80,255,0.22);color:#C4B5FD}

/* FOOTER */
.footer{
  background:var(--ink-soft);border-top:1px solid var(--border);
  padding:48px 72px 28px;position:relative;z-index:1;
}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;margin-bottom:44px}
.footer-brand{font-size:19px;font-weight:800;color:var(--text);margin-bottom:11px}
.footer-desc{font-size:13px;color:var(--text-3);line-height:1.7;margin-bottom:20px}
.footer-heading{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.9px;margin-bottom:14px}
.footer-link{display:block;font-size:13px;color:var(--text-3);margin-bottom:9px;text-decoration:none;transition:color .2s}
.footer-link:hover{color:var(--text)}
.footer-link-accent{color:#A78BFF!important;font-weight:600}
.footer-socials{display:flex;gap:8px}
.social-btn{
  width:32px;height:32px;border-radius:8px;
  background:var(--surface);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:13px;cursor:pointer;color:var(--text-2);transition:all .2s;
}
.social-btn:hover{background:var(--accent-glow);border-color:rgba(112,80,255,0.3);color:var(--text)}
.footer-bottom{
  border-top:1px solid var(--border);padding-top:20px;
  display:flex;justify-content:space-between;align-items:center;
  font-size:12px;color:var(--text-3);
}

/* KEYFRAMES */
@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}

/* RESPONSIVE */
@media(max-width:960px){
  .layout{grid-template-columns:1fr}
  .panel-left{display:none}
  .panel-right{border-left:none;padding:32px 24px}
}
@media(max-width:640px){
  .navbar{padding:0 18px}
  .nav-links{display:none}
  .footer{padding:40px 18px 22px}
  .footer-grid{grid-template-columns:1fr}
}
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
    <a href="login.php"    class="btn btn-ghost btn-sm">Log in</a>
    <a href="register.php" class="btn btn-primary btn-sm">Sign up free</a>
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
        Live Ride-Sharing Ecosystem
      </div>
      <h1 class="left-title">
        Save costs and travel<br>
        <span class="grad">eco-friendly</span>
      </h1>
      <p class="left-sub">Log in to seamlessly book shared rides, view verified drivers across Sri Lanka, and access active commuter statistics in real time.</p>
      <div class="features">
        <div class="feat"><div class="feat-icon">🛡️</div>100% Fully Verified Drivers &amp; Safe Profiles</div>
        <div class="feat"><div class="feat-icon">💰</div>Split Intercity Fuel Expenses Seamlessly</div>
        <div class="feat"><div class="feat-icon">⚡</div>Real-time Interactive Booking Confirmation</div>
      </div>
    </div>

    <div class="left-foot">© <?= date('Y') ?> RideShare LK · Made with ❤️ in Sri Lanka</div>
  </div>

  <!-- RIGHT PANEL (FORM) -->
  <div class="panel-right">
    <div class="form-card">
      <div class="fc-label">Secure Access</div>
      <div class="fc-title">Welcome back</div>
      <div class="fc-sub">Enter your credentials to continue.</div>

      <?php if($redirect): ?>
        <div class="notice">🎟 Log in and you'll go straight back to your ride.</div>
      <?php endif; ?>

      <?php if($error): ?>
        <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

        <div class="field">
          <label class="field-label" for="email">Email address</label>
          <input class="field-input" type="email" id="email" name="email"
                 placeholder="you@email.com" required autofocus
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input class="field-input" type="password" id="password" name="password"
                 placeholder="Your password" required>
        </div>

        <button type="submit" class="btn-submit">Log in →</button>
      </form>

      <div class="divider">or</div>

      <div class="link-btns">
        <a class="link-btn" href="register.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">
          Create a passenger account
        </a>
        <a class="link-btn link-btn-driver" href="register.php?role=driver">
          🚘 Become a Driver
        </a>
        <a class="link-btn" href="index.php" style="border-color:transparent;color:var(--text-3)">
          ← Return to Homepage
        </a>
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