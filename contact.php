<?php
session_start();

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // In production: mail('support@rideshare.lk', $subject, $message);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — RideShare LK</title>
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
  --green-dim:   rgba(34,197,94,0.12);
  --amber:       #F59E0B;
  --red:         #EF4444;
  --gradient:    linear-gradient(135deg,#7050FF 0%,#5B8EFF 100%);
  --glow:        0 0 40px rgba(112,80,255,0.20);
  --shadow:      0 4px 24px rgba(0,0,0,0.35);
  --shadow-lg:   0 16px 48px rgba(0,0,0,0.50);
  --r:           18px;
  --r-sm:        12px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--ink);color:var(--text);
  min-height:100vh;overflow-x:hidden;font-size:15px;line-height:1.6;
  display:flex;flex-direction:column;
}
/* Dot grid */
body::before{
  content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(112,80,255,0.22) 1.5px,transparent 1.5px);
  background-size:28px 28px;opacity:0.45;
}
body::after{
  content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:
    radial-gradient(ellipse 70% 50% at 70% -10%,rgba(112,80,255,0.13) 0%,transparent 60%),
    radial-gradient(ellipse 50% 40% at 10% 80%, rgba(91,142,255,0.09)  0%,transparent 60%);
}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--ink-soft)}
::-webkit-scrollbar-thumb{background:var(--accent);border-radius:99px}

/* ── NAVBAR ── */
.navbar{
  position:sticky;top:0;z-index:500;height:64px;
  background:rgba(10,10,15,0.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 56px;
  animation:slideDown .5s ease both;
}
@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;font-size:18px;color:var(--text);letter-spacing:-0.02em}
.nav-logo{width:34px;height:34px;background:var(--gradient);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 4px 14px rgba(112,80,255,0.38);transition:transform .3s}
.nav-logo:hover{transform:rotate(-8deg) scale(1.1)}
.nav-brand-name{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.nav-links{display:flex;align-items:center;gap:4px}
.nav-link{padding:7px 14px;font-size:13.5px;font-weight:500;color:var(--text-2);text-decoration:none;border-radius:99px;transition:all .2s}
.nav-link:hover{color:var(--text);background:var(--surface-2)}
.nav-link.active{color:var(--text);background:var(--surface-2)}
.nav-link-driver{color:#A78BFF!important;background:var(--accent-glow)!important;border:1px solid rgba(112,80,255,0.20)}
.nav-link-driver:hover{background:rgba(112,80,255,0.25)!important}
.nav-actions{display:flex;align-items:center;gap:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 20px;border-radius:99px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:600;cursor:pointer;transition:all .22s;border:none;text-decoration:none}
.btn-primary{background:var(--gradient);color:white;box-shadow:0 4px 18px rgba(112,80,255,0.40)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.55)}
.btn-ghost{background:var(--surface-2);color:var(--text-2);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text);background:var(--surface-3)}
.btn-outline{background:transparent;color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:7px 15px;font-size:12.5px}
.nav-avatar{width:34px;height:34px;border-radius:50%;background:var(--gradient);border:2px solid rgba(112,80,255,0.28);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;text-decoration:none;flex-shrink:0;transition:transform .2s}
.nav-avatar:hover{transform:scale(1.08)}

/* ── PAGE HEADER ── */
.page-header{
  text-align:center;padding:64px 24px 44px;
  position:relative;z-index:1;
  animation:fadeUp .6s ease both;
}
.header-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);
  border-radius:99px;padding:6px 16px;
  font-size:11.5px;font-weight:700;color:#A78BFF;
  letter-spacing:.6px;text-transform:uppercase;margin-bottom:16px;
}
.header-title{font-size:clamp(32px,5vw,50px);font-weight:800;line-height:1.1;letter-spacing:-0.03em;margin-bottom:12px}
.header-title .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.header-sub{font-size:16px;color:var(--text-2);max-width:500px;margin:0 auto;line-height:1.7}

/* ── MAIN GRID ── */
.page-wrap{
  flex:1;max-width:1060px;width:100%;
  margin:0 auto;padding:0 32px 72px;
  position:relative;z-index:1;
}
.contact-grid{
  display:grid;grid-template-columns:1fr 1.5fr;
  gap:24px;align-items:start;
}

/* ── INFO CARDS ── */
.info-stack{display:flex;flex-direction:column;gap:14px}
.info-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:22px;
  transition:all .28s cubic-bezier(.34,1.56,.64,1);
  box-shadow:var(--shadow);position:relative;overflow:hidden;
}
.info-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:var(--gradient);opacity:0;transition:opacity .25s;
}
.info-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg),var(--glow);border-color:rgba(112,80,255,0.22)}
.info-card:hover::before{opacity:1}
.info-icon{
  width:44px;height:44px;border-radius:13px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:20px;margin-bottom:14px;
  transition:transform .3s;
}
.info-card:hover .info-icon{transform:scale(1.1) rotate(-5deg)}
.info-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:7px}
.info-text{font-size:13px;color:var(--text-2);line-height:1.65}
.info-text a{color:#A78BFF;font-weight:600;text-decoration:none;transition:color .2s}
.info-text a:hover{color:#C4B5FD}

/* ── FORM CARD ── */
.form-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:34px;
  box-shadow:var(--shadow-lg),var(--glow);
  position:relative;overflow:hidden;
  animation:fadeUp .7s .1s ease both;
}
.form-card::before{
  content:'';position:absolute;inset:-1px;border-radius:calc(var(--r) + 1px);
  background:linear-gradient(135deg,rgba(112,80,255,0.26),transparent 52%);
  z-index:-1;
}
.fc-title{font-size:22px;font-weight:800;letter-spacing:-0.02em;margin-bottom:5px}
.fc-sub{font-size:13.5px;color:var(--text-2);margin-bottom:24px;line-height:1.6}

/* NOTICES */
.alert{
  background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.22);
  border-radius:11px;padding:12px 16px;font-size:13px;color:var(--red);
  margin-bottom:18px;animation:shake .4s ease;
}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}

/* SUCCESS */
.success-box{text-align:center;padding:32px 16px}
.success-icon{
  width:72px;height:72px;border-radius:50%;margin:0 auto 20px;
  background:var(--green-dim);border:2px solid rgba(34,197,94,0.3);
  display:flex;align-items:center;justify-content:center;font-size:32px;
  animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes popIn{from{opacity:0;transform:scale(.6)}to{opacity:1;transform:scale(1)}}
.success-title{font-size:24px;font-weight:800;letter-spacing:-0.02em;margin-bottom:10px}
.success-sub{font-size:14px;color:var(--text-2);line-height:1.7;margin-bottom:28px;max-width:380px;margin-left:auto;margin-right:auto}
.success-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:13px 28px;background:var(--gradient);color:white;
  border-radius:99px;text-decoration:none;font-weight:700;font-size:14px;
  box-shadow:0 4px 18px rgba(112,80,255,0.38);transition:all .22s;
}
.success-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.52)}

/* FORM FIELDS */
.field{margin-bottom:16px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field-label{display:block;font-size:10.5px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px}
.field-input,
.field-select,
.field-textarea{
  width:100%;padding:11px 14px;
  border:1.5px solid var(--border);border-radius:var(--r-sm);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:500;
  color:var(--text);background:var(--surface-2);
  transition:border .2s,box-shadow .2s;outline:none;-webkit-appearance:none;
}
.field-input:focus,
.field-select:focus,
.field-textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(112,80,255,0.12)}
.field-input::placeholder,
.field-textarea::placeholder{color:var(--text-3);font-weight:400}
.field-select{cursor:pointer;color-scheme:dark}
.field-select option{background:var(--surface-2);color:var(--text)}
.field-textarea{min-height:130px;resize:vertical;line-height:1.6}
.btn-submit{
  width:100%;padding:14px;margin-top:6px;
  background:var(--gradient);color:white;border:none;border-radius:14px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;
  cursor:pointer;transition:all .22s;
  box-shadow:0 4px 18px rgba(112,80,255,0.38);position:relative;overflow:hidden;
}
.btn-submit::before{content:'';position:absolute;top:0;left:-120%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.22),transparent);transition:.7s}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(112,80,255,0.52)}
.btn-submit:hover::before{left:120%}

/* ── FOOTER ── */
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

/* REVEAL */
.rs-reveal{opacity:0;transform:translateY(22px);transition:opacity .6s ease,transform .6s ease}
.rs-reveal.visible{opacity:1;transform:translateY(0)}
.rs-reveal-d1{transition-delay:.08s}
.rs-reveal-d2{transition-delay:.16s}
.rs-reveal-d3{transition-delay:.24s}
.rs-reveal-d4{transition-delay:.32s}

@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}

/* RESPONSIVE */
@media(max-width:900px){.contact-grid{grid-template-columns:1fr}.footer-grid{grid-template-columns:1fr 1fr;gap:28px}}
@media(max-width:640px){.navbar{padding:0 18px}.nav-links{display:none}.page-header{padding:48px 18px 32px}.page-wrap{padding:0 18px 56px}.footer{padding:40px 18px 22px}.footer-grid{grid-template-columns:1fr}.field-row{grid-template-columns:1fr}}
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
    <a href="about.php"   class="nav-link">About</a>
    <a href="contact.php" class="nav-link active">Contact</a>
    <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
      <a href="register.php?role=driver" class="nav-link nav-link-driver">🚗 Become Driver</a>
    <?php endif; ?>
  </div>
  <div class="nav-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
      <span style="font-size:13px;color:var(--text-3);font-weight:500">Hi, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
      <?php if ($_SESSION['user_role'] === 'driver'): ?>
        <a href="driver_dashboard.php" class="btn btn-ghost btn-sm">Dashboard</a>
      <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
        <a href="admin/index.php" class="btn btn-ghost btn-sm">Admin panel</a>
      <?php else: ?>
        <a href="my_bookings.php" class="btn btn-ghost btn-sm">My bookings</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-outline btn-sm">Log out</a>
      <div class="nav-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
    <?php else: ?>
      <a href="login.php"    class="btn btn-ghost btn-sm">Log in</a>
      <a href="register.php" class="btn btn-primary btn-sm">Sign up free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ═══ PAGE HEADER ═══ -->
<div class="page-header">
  <div class="header-eyebrow">💬 Support Hub</div>
  <h1 class="header-title">Get in <span class="grad">touch</span></h1>
  <p class="header-sub">Have an inquiry, booking anomaly, or system improvement feedback? We're here to help anytime.</p>
</div>

<!-- ═══ CONTENT ═══ -->
<div class="page-wrap">
  <div class="contact-grid">

    <!-- LEFT: INFO CARDS -->
    <div class="info-stack">
      <div class="info-card rs-reveal">
        <div class="info-icon">📧</div>
        <div class="info-title">Email Correspondence</div>
        <div class="info-text">
          <a href="mailto:support@rideshare.lk">support@rideshare.lk</a><br>
          Our team answers all items within 24 hours.
        </div>
      </div>

      <div class="info-card rs-reveal rs-reveal-d1">
        <div class="info-icon">📞</div>
        <div class="info-title">Direct Helpline &amp; WhatsApp</div>
        <div class="info-text">
          <a href="tel:+94767940176">+94 76 794 0176</a><br>
          Operational Mon–Sat, 8 AM – 8 PM
        </div>
      </div>

      <div class="info-card rs-reveal rs-reveal-d2">
        <div class="info-icon">🚗</div>
        <div class="info-title">Driver Partner Operations</div>
        <div class="info-text">
          Need assistance with verification workflows or ride listings?<br>
          <a href="register.php?role=driver">Become a verified driver →</a>
        </div>
      </div>

      <div class="info-card rs-reveal rs-reveal-d3">
        <div class="info-icon">📍</div>
        <div class="info-title">Sri Lankan Transit Network</div>
        <div class="info-text">
          Providing localized matching cross-country — Colombo, Kandy, Galle, Ampara, and beyond.
        </div>
      </div>
    </div>

    <!-- RIGHT: FORM CARD -->
    <div class="form-card">
      <?php if ($success): ?>
        <div class="success-box">
          <div class="success-icon">✅</div>
          <div class="success-title">Message transmitted!</div>
          <div class="success-sub">
            Thank you, we received your request and will follow up at
            <strong style="color:var(--text)"><?= htmlspecialchars($_POST['email']) ?></strong> within 24 hours.
          </div>
          <a href="index.php" class="success-btn">← Return to homepage</a>
        </div>
      <?php else: ?>
        <div class="fc-title">Send us a message</div>
        <div class="fc-sub">Complete the form below and an agent will reach out shortly.</div>

        <?php if ($error): ?>
          <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="field-row">
            <div class="field">
              <label class="field-label" for="name">Your name</label>
              <input class="field-input" type="text" id="name" name="name"
                     placeholder="Saman Perera" required
                     value="<?= htmlspecialchars($_POST['name'] ?? ($_SESSION['user_name'] ?? '')) ?>">
            </div>
            <div class="field">
              <label class="field-label" for="email">Email address</label>
              <input class="field-input" type="email" id="email" name="email"
                     placeholder="you@email.com" required
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="subject">Subject topic</label>
            <select class="field-select" id="subject" name="subject" required>
              <option value="">Select a topic category...</option>
              <option value="Booking issue"       <?= ($_POST['subject']??'')==='Booking issue'?'selected':'' ?>>Booking issue</option>
              <option value="Driver verification" <?= ($_POST['subject']??'')==='Driver verification'?'selected':'' ?>>Driver verification</option>
              <option value="Payment question"    <?= ($_POST['subject']??'')==='Payment question'?'selected':'' ?>>Payment question</option>
              <option value="Safety concern"      <?= ($_POST['subject']??'')==='Safety concern'?'selected':'' ?>>Safety concern</option>
              <option value="Account problem"     <?= ($_POST['subject']??'')==='Account problem'?'selected':'' ?>>Account problem</option>
              <option value="Suggestion"          <?= ($_POST['subject']??'')==='Suggestion'?'selected':'' ?>>Suggestion</option>
              <option value="Other"               <?= ($_POST['subject']??'')==='Other'?'selected':'' ?>>Other</option>
            </select>
          </div>

          <div class="field">
            <label class="field-label" for="message">Detailed message</label>
            <textarea class="field-textarea" id="message" name="message"
                      placeholder="Describe your inquiry in full detail..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn-submit">Send message →</button>
        </form>
      <?php endif; ?>
    </div>

  </div>
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
      <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
        <a class="footer-link footer-link-accent" href="register.php?role=driver">Become a Driver</a>
      <?php endif; ?>
      <a class="footer-link" href="about.php">About Us</a>
    </div>
    <div>
      <div class="footer-heading">Account</div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($_SESSION['user_role'] === 'driver'): ?>
          <a class="footer-link" href="driver_dashboard.php">My Dashboard</a>
        <?php else: ?>
          <a class="footer-link" href="my_bookings.php">My Bookings</a>
        <?php endif; ?>
        <a class="footer-link" href="logout.php">Log Out</a>
      <?php else: ?>
        <a class="footer-link" href="login.php">Sign In</a>
        <a class="footer-link" href="register.php">Create Account</a>
      <?php endif; ?>
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
const rsObs = new IntersectionObserver(entries => {
  entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('visible'); rsObs.unobserve(e.target); } });
}, {threshold:0.10});
document.querySelectorAll('.rs-reveal').forEach(el => rsObs.observe(el));
</script>
</body>
</html>