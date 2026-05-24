<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — RideShare LK</title>
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
    radial-gradient(ellipse 40% 40% at 5% 80%, rgba(91,142,255,0.08)  0%,transparent 60%);
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
  padding:0 56px;animation:slideDown .5s ease both;
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

/* ── HERO ── */
.hero{
  text-align:center;padding:80px 32px 60px;
  position:relative;z-index:1;overflow:hidden;
}
.orb-1{position:absolute;top:-120px;right:-120px;width:440px;height:440px;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(112,80,255,0.12),transparent 70%);animation:orbFloat 8s ease-in-out infinite alternate}
.orb-2{position:absolute;bottom:-80px;left:-80px;width:320px;height:320px;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(91,142,255,0.09),transparent 70%);animation:orbFloat 11s ease-in-out infinite alternate-reverse}
@keyframes orbFloat{from{transform:translate(0,0) scale(1)}to{transform:translate(18px,-18px) scale(1.05)}}

.hero-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);
  border-radius:99px;padding:6px 18px;
  font-size:11.5px;font-weight:700;color:#A78BFF;
  letter-spacing:.6px;text-transform:uppercase;margin-bottom:18px;
  animation:fadeUp .6s ease both;
}
.hero-title{
  font-size:clamp(34px,5vw,54px);font-weight:800;line-height:1.1;
  letter-spacing:-0.03em;margin-bottom:18px;
  animation:fadeUp .7s ease both;
}
.hero-title .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.hero-sub{
  font-size:16px;color:var(--text-2);max-width:560px;
  margin:0 auto;line-height:1.75;
  animation:fadeUp .8s ease both;
}

/* ── PILLARS ── */
.section{padding:0 72px 72px;position:relative;z-index:1}
.section-alt{background:rgba(17,17,24,0.6)}
.section-inner{max-width:1100px;margin:0 auto}
.section-heading{font-size:clamp(22px,3vw,34px);font-weight:800;letter-spacing:-0.025em;text-align:center;margin-bottom:36px}
.section-heading .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}

.pillars-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.pillar-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:30px 26px;
  box-shadow:var(--shadow);position:relative;overflow:hidden;
  transition:all .28s cubic-bezier(.34,1.56,.64,1);
}
.pillar-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gradient);opacity:0;transition:opacity .25s}
.pillar-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg),var(--glow);border-color:rgba(112,80,255,0.24)}
.pillar-card:hover::before{opacity:1}
.pillar-icon{
  width:52px;height:52px;border-radius:15px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:24px;margin-bottom:20px;
  transition:transform .3s;
}
.pillar-card:hover .pillar-icon{transform:scale(1.12) rotate(-5deg)}
.pillar-name{font-size:17px;font-weight:700;margin-bottom:10px;color:var(--text)}
.pillar-desc{font-size:13.5px;color:var(--text-2);line-height:1.7}

/* ── STORY SECTION ── */
.story-wrap{
  background:rgba(17,17,24,0.6);
  border-top:1px solid var(--border);border-bottom:1px solid var(--border);
  padding:72px;position:relative;z-index:1;
}
.story-inner{
  max-width:1100px;margin:0 auto;
  display:grid;grid-template-columns:1.2fr 1fr;gap:64px;align-items:center;
}
.story-heading{font-size:clamp(24px,3.5vw,38px);font-weight:800;line-height:1.2;letter-spacing:-0.025em;margin-bottom:18px;color:var(--text)}
.story-heading .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.story-text{font-size:15px;color:var(--text-2);line-height:1.80}
.points-list{display:flex;flex-direction:column;gap:22px}
.point-item{display:flex;align-items:flex-start;gap:16px}
.point-check{
  width:26px;height:26px;border-radius:8px;flex-shrink:0;margin-top:2px;
  background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);
  display:flex;align-items:center;justify-content:center;
  font-size:12px;color:#A78BFF;font-weight:700;
}
.point-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px}
.point-desc{font-size:13.5px;color:var(--text-2);line-height:1.65}

/* ── STATS BAND ── */
.stats-band{
  padding:56px 72px;position:relative;z-index:1;
  background:var(--gradient);overflow:hidden;
}
.stats-band::before{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:rgba(255,255,255,0.07);border-radius:50%;animation:orbFloat 8s ease-in-out infinite alternate}
.stats-band-inner{max-width:900px;margin:0 auto;display:flex;justify-content:space-around;flex-wrap:wrap;gap:28px;position:relative;z-index:2}
.stat-item{text-align:center}
.stat-num{font-size:clamp(28px,4vw,44px);font-weight:800;color:white;line-height:1;letter-spacing:-0.03em;margin-bottom:8px}
.stat-lbl{font-size:13px;color:rgba(255,255,255,.72);font-weight:600;text-transform:uppercase;letter-spacing:.6px}

/* ── CTA STRIP ── */
.cta-strip{
  padding:72px;position:relative;z-index:1;text-align:center;
}
.cta-strip-inner{max-width:680px;margin:0 auto}
.cta-eyebrow{display:inline-flex;align-items:center;gap:8px;background:var(--accent-glow);border:1px solid rgba(112,80,255,0.25);border-radius:99px;padding:6px 18px;font-size:11.5px;font-weight:700;color:#A78BFF;letter-spacing:.6px;text-transform:uppercase;margin-bottom:16px}
.cta-title{font-size:clamp(26px,3.5vw,40px);font-weight:800;line-height:1.15;letter-spacing:-0.025em;margin-bottom:14px}
.cta-title .grad{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.cta-sub{font-size:15px;color:var(--text-2);line-height:1.75;margin-bottom:30px;max-width:520px;margin-left:auto;margin-right:auto}
.cta-actions{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
.btn-lg{padding:14px 32px;font-size:15px}
.btn-white{background:white;color:var(--accent);font-weight:700;box-shadow:0 4px 16px rgba(0,0,0,0.15)}
.btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.22)}

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
.rs-reveal{opacity:0;transform:translateY(24px);transition:opacity .6s ease,transform .6s ease}
.rs-reveal.visible{opacity:1;transform:translateY(0)}
.rs-reveal-d1{transition-delay:.08s}
.rs-reveal-d2{transition-delay:.16s}
.rs-reveal-d3{transition-delay:.24s}

@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:1100px){.section{padding:0 40px 60px}.story-wrap{padding:56px 40px}.stats-band{padding:48px 40px}.cta-strip{padding:56px 40px}.footer{padding:48px 40px 24px}}
@media(max-width:900px){.pillars-grid{grid-template-columns:1fr}.story-inner{grid-template-columns:1fr;gap:36px}.footer-grid{grid-template-columns:1fr 1fr;gap:28px}}
@media(max-width:640px){.navbar{padding:0 18px}.nav-links{display:none}.hero{padding:56px 18px 40px}.section{padding:0 18px 48px}.story-wrap{padding:48px 18px}.stats-band{padding:40px 18px}.cta-strip{padding:48px 18px}.footer{padding:40px 18px 22px}.footer-grid{grid-template-columns:1fr}.stats-band-inner{gap:20px}}
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
    <a href="about.php"   class="nav-link active">About</a>
    <a href="contact.php" class="nav-link">Contact</a>
    <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
      <a href="register.php?role=driver" class="nav-link nav-link-driver">🚗 Become Driver</a>
    <?php endif; ?>
  </div>
  <div class="nav-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($_SESSION['user_role'] === 'driver'): ?>
        <a href="driver_dashboard.php" class="btn btn-ghost btn-sm">Dashboard</a>
      <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
        <a href="admin/index.php" class="btn btn-ghost btn-sm">Admin</a>
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

<!-- ═══ HERO ═══ -->
<section class="hero">
  <div class="orb-1"></div>
  <div class="orb-2"></div>
  <div class="hero-eyebrow">✨ Who We Are</div>
  <h1 class="hero-title">Our Mission &amp; <br><span class="grad">The Shared Journey</span></h1>
  <p class="hero-sub">We are building Sri Lanka's premium decentralized community network — a smarter, optimized way to traverse across cities safely and efficiently.</p>
</section>

<!-- ═══ PILLARS ═══ -->
<section class="section section-alt" style="padding-top:60px">
  <div class="section-inner">
    <h2 class="section-heading rs-reveal">What Drives Our <span class="grad">Application</span></h2>
    <div class="pillars-grid">
      <div class="pillar-card rs-reveal rs-reveal-d1">
        <div class="pillar-icon">🤝</div>
        <div class="pillar-name">Trusted Community</div>
        <div class="pillar-desc">We convert anonymous highways into shared networking channels. Traveling together connects individuals across districts safely and reliably.</div>
      </div>
      <div class="pillar-card rs-reveal rs-reveal-d2">
        <div class="pillar-icon">🛡️</div>
        <div class="pillar-name">Identity Protection</div>
        <div class="pillar-desc">Structured account constraints and profile verification parameters keep administrative layers and user safety elements highly secure.</div>
      </div>
      <div class="pillar-card rs-reveal rs-reveal-d3">
        <div class="pillar-icon">🌿</div>
        <div class="pillar-name">Fuel Optimization</div>
        <div class="pillar-desc">By filling up vacant seats, we directly offset operational expenses for drivers while maintaining low-cost transit options for commuters.</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ STORY ═══ -->
<div class="story-wrap">
  <div class="story-inner">
    <div class="rs-reveal">
      <h3 class="story-heading">Smarter coordination for <span class="grad">island commuters.</span></h3>
      <p class="story-text">
        Every day, private transit resources run below structural capacities while alternative routes remain highly packed and costly.
        <br><br>
        RideShare acts as the automated optimization layer. We coordinate empty seats with active search requests, allowing split costs without unexpected delays or logistical overhead.
      </p>
    </div>
    <div class="points-list rs-reveal rs-reveal-d1">
      <div class="point-item">
        <div class="point-check">✓</div>
        <div>
          <div class="point-title">All 25 Districts Covered</div>
          <div class="point-desc">Intercity and long-haul connections spanning accurately from Colombo up to northern and southern provinces.</div>
        </div>
      </div>
      <div class="point-item">
        <div class="point-check">✓</div>
        <div>
          <div class="point-title">Emissions Reduction Tracking</div>
          <div class="point-desc">Consolidating multiple routes into coordinated runs consistently removes carbon output per passenger index.</div>
        </div>
      </div>
      <div class="point-item">
        <div class="point-check">✓</div>
        <div>
          <div class="point-title">Verified Drivers Only</div>
          <div class="point-desc">Every driver passes identity and vehicle checks before being approved to post rides on the platform.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ STATS BAND ═══ -->
<div class="stats-band">
  <div class="stats-band-inner">
    <div class="stat-item rs-reveal">
      <div class="stat-num">2,400+</div>
      <div class="stat-lbl">Rides Completed</div>
    </div>
    <div class="stat-item rs-reveal rs-reveal-d1">
      <div class="stat-num">850+</div>
      <div class="stat-lbl">Active Drivers</div>
    </div>
    <div class="stat-item rs-reveal rs-reveal-d2">
      <div class="stat-num">25+</div>
      <div class="stat-lbl">Districts Covered</div>
    </div>
    <div class="stat-item rs-reveal rs-reveal-d3">
      <div class="stat-num">4.8★</div>
      <div class="stat-lbl">Average Rating</div>
    </div>
  </div>
</div>

<!-- ═══ CTA STRIP ═══ -->
<?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
<div class="cta-strip">
  <div class="cta-strip-inner rs-reveal">
    <div class="cta-eyebrow">🚗 Join the Network</div>
    <h2 class="cta-title">Ready to <span class="grad">travel smarter?</span></h2>
    <p class="cta-sub">Whether you're looking for a safe affordable ride or want to earn by sharing your daily journey — RideShare LK has you covered.</p>
    <div class="cta-actions">
      <a href="results.php" class="btn btn-primary btn-lg">🔍 Find a Ride</a>
      <a href="register.php?role=driver" class="btn btn-ghost btn-lg">🚗 Become a Driver</a>
    </div>
  </div>
</div>
<?php endif; ?>

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