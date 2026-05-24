<?php
session_start();
require_once 'db/connection.php';

$today = date('Y-m-d');
$stmt  = $pdo->prepare("
    SELECT r.*, u.name AS driver_name, u.is_verified
    FROM rides r
    JOIN users u ON r.driver_id = u.id
    WHERE r.ride_date = :today
      AND r.seats_available > 0
      AND r.status = 'active'
    ORDER BY r.ride_time ASC
    LIMIT 3
");
$stmt->execute([':today' => $today]);
$rides = $stmt->fetchAll();

$avatar_colors = [
    'linear-gradient(135deg,#7C3AED,#3B82F6)',
    'linear-gradient(135deg,#10B981,#3B82F6)',
    'linear-gradient(135deg,#EC4899,#7C3AED)',
    'linear-gradient(135deg,#F59E0B,#EF4444)',
    'linear-gradient(135deg,#4F46E5,#0EA5E9)',
];

function driverInitials(string $n): string {
    $p = explode(' ', $n);
    $i = strtoupper(substr($p[0], 0, 1));
    if (count($p) > 1) $i .= strtoupper(substr($p[1], 0, 1));
    return $i;
}
function vehicleLabel(string $t): string {
    return match($t){ 'car'=>'🚗 Car','van'=>'🚐 Van','three-wheeler'=>'🛺 Three-wheeler', default=>$t };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RideShare Sri Lanka — Share Rides, Save Money</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — PickMe/Uber Dark Luxury
═══════════════════════════════════════ */
:root {
  --ink:          #0A0A0F;
  --ink-soft:     #111118;
  --surface:      #16161F;
  --surface-2:    #1E1E2A;
  --surface-3:    #252533;
  --border:       rgba(255,255,255,0.07);
  --border-glow:  rgba(112,80,255,0.3);
  --text:         #F0EFF8;
  --text-2:       #A09EC0;
  --text-3:       #5E5C7A;
  --accent:       #7050FF;
  --accent-2:     #5B8EFF;
  --accent-glow:  rgba(112,80,255,0.15);
  --green:        #22C55E;
  --green-dim:    rgba(34,197,94,0.12);
  --amber:        #F59E0B;
  --red:          #EF4444;
  --gradient:     linear-gradient(135deg, #7050FF 0%, #5B8EFF 100%);
  --gradient-r:   linear-gradient(135deg, #5B8EFF 0%, #7050FF 100%);
  --glow:         0 0 40px rgba(112,80,255,0.20);
  --shadow:       0 4px 24px rgba(0,0,0,0.35);
  --shadow-lg:    0 16px 48px rgba(0,0,0,0.50);
  --r:            18px;
  --r-sm:         12px;
  --r-xs:         8px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--ink);
  color: var(--text);
  overflow-x: hidden;
  font-size: 15px;
  line-height: 1.6;
}

::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--ink-soft); }
::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 99px; }

/* ═══════════════ GRID NOISE BACKGROUND ═══════════════ */
body::before {
  content: '';
  position: fixed; inset: 0; z-index: 0;
  background-image: 
    radial-gradient(ellipse 70% 50% at 80% -10%, rgba(112,80,255,0.12) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 10% 80%, rgba(91,142,255,0.08) 0%, transparent 60%);
  pointer-events: none;
}

/* ═══════════════ NAVBAR ═══════════════ */
.navbar {
  position: sticky; top: 0; z-index: 500;
  height: 64px;
  background: rgba(10,10,15,0.85);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 48px;
}

.nav-brand {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none;
  font-weight: 800; font-size: 18px;
  color: var(--text);
  letter-spacing: -0.02em;
}
.nav-logo {
  width: 36px; height: 36px;
  background: var(--gradient);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  box-shadow: 0 4px 16px rgba(112,80,255,0.4);
}
.nav-brand span { color: var(--accent); }

.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-link {
  padding: 7px 14px;
  font-size: 14px; font-weight: 500; color: var(--text-2);
  text-decoration: none; border-radius: 99px;
  transition: all .2s;
}
.nav-link:hover { color: var(--text); background: var(--surface-2); }
.nav-link.active { color: var(--text); background: var(--surface-2); }

.nav-actions { display: flex; align-items: center; gap: 8px; }

/* ═══════════════ BUTTONS ═══════════════ */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 10px 22px; border-radius: 99px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; font-weight: 600;
  cursor: pointer; transition: all .22s; border: none;
  text-decoration: none; white-space: nowrap;
}
.btn-primary {
  background: var(--gradient); color: white;
  box-shadow: 0 4px 20px rgba(112,80,255,0.40);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(112,80,255,0.55); }
.btn-ghost {
  background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-ghost:hover { color: var(--text); border-color: rgba(255,255,255,0.15); background: var(--surface-3); }
.btn-outline {
  background: transparent; color: var(--text);
  border: 1.5px solid var(--border);
}
.btn-outline:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: 7px 16px; font-size: 13px; }
.btn-lg { padding: 14px 32px; font-size: 15px; }
.btn-xl { padding: 16px 40px; font-size: 16px; border-radius: 14px; }
.w-full { width: 100%; }

/* ═══════════════ AVATAR ═══════════════ */
.avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--gradient);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 700; font-size: 13px;
  cursor: pointer; flex-shrink: 0; text-decoration: none;
  border: 2px solid rgba(112,80,255,0.3);
}

/* ═══════════════ BADGES ═══════════════ */
.badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 99px;
  font-size: 11px; font-weight: 700; letter-spacing: .2px;
}
.badge-green  { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
.badge-amber  { background: rgba(245,158,11,0.12); color: var(--amber); border: 1px solid rgba(245,158,11,0.2); }
.badge-accent { background: var(--accent-glow); color: #A78BFF; border: 1px solid rgba(112,80,255,0.2); }
.badge-red    { background: rgba(239,68,68,0.12); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }

/* ═══════════════ HERO ═══════════════ */
.hero {
  min-height: calc(100vh - 64px);
  display: grid; grid-template-columns: 1fr 480px;
  align-items: center; gap: 60px;
  padding: 64px 80px;
  position: relative; z-index: 1;
}

.hero-kicker {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--accent-glow);
  border: 1px solid rgba(112,80,255,0.25);
  border-radius: 99px; padding: 6px 16px;
  font-size: 12px; font-weight: 700; color: #A78BFF;
  letter-spacing: .5px; text-transform: uppercase;
  margin-bottom: 24px;
  animation: fadeUp .6s both;
}

.hero-title {
  font-size: clamp(40px, 5vw, 62px);
  font-weight: 800; line-height: 1.08;
  letter-spacing: -0.03em;
  margin-bottom: 24px;
  animation: fadeUp .6s .1s both;
}
.hero-title .grad {
  background: var(--gradient);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

.hero-sub {
  font-size: 17px; color: var(--text-2); line-height: 1.7;
  margin-bottom: 36px; max-width: 460px;
  animation: fadeUp .6s .2s both;
  font-weight: 400;
}

.hero-actions {
  display: flex; gap: 12px; flex-wrap: wrap;
  margin-bottom: 48px;
  animation: fadeUp .6s .3s both;
}

.hero-stats {
  display: flex; gap: 40px;
  animation: fadeUp .6s .4s both;
  padding-top: 32px;
  border-top: 1px solid var(--border);
}
.hero-stat-num {
  font-size: 28px; font-weight: 800;
  letter-spacing: -0.03em;
  background: var(--gradient);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.hero-stat-lbl { font-size: 12px; color: var(--text-3); margin-top: 2px; font-weight: 500; }

/* ─── SEARCH CARD ─── */
.search-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 32px;
  box-shadow: var(--shadow-lg), var(--glow);
  animation: fadeUp .6s .15s both;
  position: relative;
}
.search-card::before {
  content: '';
  position: absolute; inset: -1px; border-radius: 25px;
  background: linear-gradient(135deg, rgba(112,80,255,0.3), transparent 50%);
  z-index: -1;
}
.search-title {
  font-size: 18px; font-weight: 700;
  margin-bottom: 24px;
  display: flex; align-items: center; gap: 10px;
}
.search-title-icon {
  width: 32px; height: 32px;
  background: var(--gradient);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
}

.form-group { margin-bottom: 14px; }
.form-label {
  font-size: 11px; font-weight: 700; color: var(--text-3);
  text-transform: uppercase; letter-spacing: .8px;
  margin-bottom: 8px; display: block;
}
.form-input {
  width: 100%; padding: 12px 16px 12px 42px;
  border: 1.5px solid var(--border);
  border-radius: var(--r-sm);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; font-weight: 500; color: var(--text);
  background: var(--surface-2);
  transition: border .2s, box-shadow .2s;
  outline: none; -webkit-appearance: none;
}
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(112,80,255,0.12); }
.form-input::placeholder { color: var(--text-3); }
.form-input option { background: var(--surface-2); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  font-size: 15px; pointer-events: none;
}
.form-input-bare { padding-left: 16px; }

.search-divider {
  display: flex; align-items: center; gap: 12px;
  margin: 18px 0 14px;
  color: var(--text-3); font-size: 12px;
}
.search-divider::before, .search-divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ─── ROUTE CONNECTOR ─── */
.route-fields { position: relative; }
.route-swap {
  position: absolute; right: -18px; top: 50%; transform: translateY(-50%);
  width: 32px; height: 32px;
  background: var(--surface-3); border: 1px solid var(--border);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; cursor: pointer; z-index: 2;
  transition: all .2s; color: var(--text-2);
}
.route-swap:hover { background: var(--accent); color: white; border-color: var(--accent); }

.route-line {
  position: absolute; left: 20px; top: 46px; bottom: 30px;
  width: 2px;
  background: linear-gradient(to bottom, var(--accent) 0%, var(--accent-2) 100%);
  opacity: .4; border-radius: 2px;
}

/* ═══════════════ LIVE RIDES SECTION ═══════════════ */
.section {
  padding: 80px 80px;
  position: relative; z-index: 1;
}
.section-alt { background: var(--ink-soft); }

.section-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--accent-glow);
  border: 1px solid rgba(112,80,255,0.2);
  border-radius: 99px; padding: 5px 14px;
  font-size: 11px; font-weight: 700; color: #A78BFF;
  letter-spacing: .8px; text-transform: uppercase;
  margin-bottom: 14px;
}
.section-title {
  font-size: clamp(28px, 4vw, 42px);
  font-weight: 800; line-height: 1.15;
  letter-spacing: -0.025em;
  margin-bottom: 12px;
}
.section-title .grad {
  background: var(--gradient);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.section-sub { color: var(--text-2); font-size: 16px; max-width: 500px; }
.section-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 40px; flex-wrap: wrap; gap: 16px; }

/* ─── RIDE CARD MINI ─── */
.rides-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

.ride-mini {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 24px;
  text-decoration: none; color: inherit;
  display: flex; flex-direction: column;
  transition: all .25s;
  position: relative; overflow: hidden;
}
.ride-mini::before {
  content: ''; position: absolute;
  top: 0; left: 0; right: 0; height: 2px;
  background: var(--gradient);
  opacity: 0; transition: opacity .25s;
}
.ride-mini:hover {
  border-color: rgba(112,80,255,0.25);
  transform: translateY(-4px);
  box-shadow: var(--shadow), var(--glow);
}
.ride-mini:hover::before { opacity: 1; }

.rm-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.rm-route { flex: 1; position: relative; padding-left: 22px; }
.rm-route-line {
  position: absolute; left: 6px; top: 10px; bottom: 10px;
  width: 2px;
  background: linear-gradient(to bottom, var(--accent), var(--accent-2));
  opacity: 0.5;
}
.rm-stop { position: relative; margin-bottom: 14px; }
.rm-stop:last-child { margin-bottom: 0; }
.rm-dot {
  position: absolute; left: -22px; top: 5px;
  width: 8px; height: 8px; border-radius: 50%;
}
.rm-dot.start { background: var(--accent); box-shadow: 0 0 0 3px rgba(112,80,255,0.2); }
.rm-dot.end   { background: var(--accent-2); box-shadow: 0 0 0 3px rgba(91,142,255,0.2); }
.rm-label { font-size: 10px; color: var(--text-3); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
.rm-city { font-size: 15px; font-weight: 700; color: var(--text); }

.rm-price-pill {
  background: var(--accent-glow);
  border: 1px solid rgba(112,80,255,0.2);
  border-radius: 12px;
  padding: 8px 14px; text-align: center; flex-shrink: 0;
}
.rm-price-num { font-size: 18px; font-weight: 800; color: #A78BFF; line-height: 1; }
.rm-price-lbl { font-size: 10px; color: var(--text-3); font-weight: 600; margin-top: 2px; }

.rm-driver { display: flex; align-items: center; gap: 10px; margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); }
.rm-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: white; flex-shrink: 0;
}
.rm-driver-name { font-size: 13px; font-weight: 600; }
.rm-driver-tag { font-size: 11px; color: var(--green); font-weight: 600; }
.rm-book { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--accent); }

/* ═══════════════ TRUST BAR ═══════════════ */
.trust-bar {
  background: var(--surface);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  padding: 20px 80px;
  display: flex; align-items: center;
  justify-content: space-between;
  gap: 24px;
  overflow: hidden;
  position: relative; z-index: 1;
}
.trust-item {
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; font-weight: 600; color: var(--text-2);
  white-space: nowrap;
}
.trust-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

/* ═══════════════ HOW IT WORKS ═══════════════ */
.steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 48px; }
.step-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 32px 28px;
  position: relative; overflow: hidden;
  transition: all .25s;
}
.step-card:hover { border-color: rgba(112,80,255,0.25); transform: translateY(-4px); box-shadow: var(--shadow), var(--glow); }
.step-num {
  font-size: 64px; font-weight: 800;
  line-height: 1;
  background: var(--gradient);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  opacity: .15;
  position: absolute; top: 16px; right: 20px;
}
.step-icon {
  width: 52px; height: 52px;
  background: var(--accent-glow);
  border: 1px solid rgba(112,80,255,0.2);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; margin-bottom: 20px;
}
.step-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; }
.step-desc { font-size: 14px; color: var(--text-2); line-height: 1.65; }

/* ═══════════════ DRIVER CTA SECTION ═══════════════ */
.driver-cta {
  margin: 0 80px 80px;
  background: var(--gradient);
  border-radius: 24px;
  padding: 64px 72px;
  display: grid; grid-template-columns: 1.2fr .8fr;
  gap: 48px; align-items: center;
  position: relative; overflow: hidden; z-index: 1;
}
.driver-cta::before {
  content: ''; position: absolute;
  top: -80px; right: -80px;
  width: 320px; height: 320px;
  background: rgba(255,255,255,0.07); border-radius: 50%;
}
.driver-cta::after {
  content: ''; position: absolute;
  bottom: -50px; right: 160px;
  width: 200px; height: 200px;
  background: rgba(255,255,255,0.04); border-radius: 50%;
}

.dcta-tag {
  display: inline-flex; align-items: center; gap: 7px;
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
  border-radius: 99px; padding: 5px 14px;
  font-size: 12px; font-weight: 700; color: rgba(255,255,255,.9);
  letter-spacing: .5px; margin-bottom: 18px;
}
.dcta-title {
  font-size: clamp(26px, 3.5vw, 40px);
  font-weight: 800; line-height: 1.15;
  color: white; margin-bottom: 16px;
  letter-spacing: -0.025em;
}
.dcta-desc {
  font-size: 15px; color: rgba(255,255,255,0.80);
  line-height: 1.75; margin-bottom: 28px; max-width: 460px;
}
.dcta-perks { list-style: none; display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; }
.dcta-perk {
  display: flex; align-items: center; gap: 12px;
  font-size: 14px; color: rgba(255,255,255,.9);
}
.dcta-check {
  width: 22px; height: 22px; border-radius: 6px;
  background: rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; flex-shrink: 0;
}
.dcta-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-white {
  background: white; color: var(--accent);
  font-weight: 700;
}
.btn-white:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
.btn-white-ghost {
  background: rgba(255,255,255,0.1);
  border: 1.5px solid rgba(255,255,255,0.25);
  color: white;
}
.btn-white-ghost:hover { background: rgba(255,255,255,0.18); }

.dcta-float {
  position: relative; z-index: 2;
  display: flex; justify-content: center;
}
.dcta-card {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 22px; padding: 32px 28px;
  text-align: center;
  backdrop-filter: blur(12px);
  animation: float 5s ease-in-out infinite alternate;
  max-width: 240px; width: 100%;
}
.dcta-card-icon { font-size: 56px; margin-bottom: 16px; }
.dcta-card-title { font-size: 17px; font-weight: 700; color: white; margin-bottom: 18px; }
.dcta-card-stats { display: flex; gap: 10px; justify-content: center; }
.dcta-stat {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 10px; padding: 10px 14px; text-align: center;
}
.dcta-stat-num { font-size: 18px; font-weight: 800; color: white; }
.dcta-stat-lbl { font-size: 10px; color: rgba(255,255,255,0.55); margin-top: 2px; }

/* ═══════════════ TESTIMONIALS ═══════════════ */
.testimonials-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-top: 40px; }
.testi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px; padding: 28px;
  transition: all .25s;
}
.testi-card:hover { border-color: rgba(112,80,255,0.2); transform: translateY(-3px); }
.testi-stars { display: flex; gap: 3px; margin-bottom: 14px; }
.testi-star { color: var(--amber); font-size: 14px; }
.testi-text { font-size: 14px; color: var(--text-2); line-height: 1.75; font-style: italic; margin-bottom: 20px; }
.testi-author { display: flex; align-items: center; gap: 12px; }
.testi-name { font-size: 14px; font-weight: 700; }
.testi-role { font-size: 12px; color: var(--text-3); }

/* ═══════════════ FAQ ═══════════════ */
.faq-wrap { max-width: 760px; margin: 40px auto 0; display: flex; flex-direction: column; gap: 10px; }
.faq-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-sm); overflow: hidden;
  transition: all .2s;
}
.faq-item.open { border-color: rgba(112,80,255,0.25); }
.faq-trigger {
  width: 100%; padding: 20px 24px;
  display: flex; justify-content: space-between; align-items: center;
  background: none; border: none; text-align: left;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 15px; font-weight: 700; color: var(--text);
  cursor: pointer; gap: 16px;
}
.faq-icon {
  width: 28px; height: 28px; flex-shrink: 0;
  background: var(--surface-2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; color: var(--accent);
  transition: transform .25s, background .2s;
}
.faq-item.open .faq-icon { transform: rotate(180deg); background: var(--accent-glow); }
.faq-body { max-height: 0; overflow: hidden; transition: max-height .3s ease-out; }
.faq-body-inner { padding: 0 24px 20px; font-size: 14px; color: var(--text-2); line-height: 1.7; }

/* ═══════════════ FOOTER ═══════════════ */
.footer {
  background: var(--ink-soft);
  border-top: 1px solid var(--border);
  padding: 60px 80px 32px;
  position: relative; z-index: 1;
}
.footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; margin-bottom: 48px; }
.footer-brand { font-size: 20px; font-weight: 800; color: var(--text); margin-bottom: 12px; }
.footer-desc { font-size: 14px; color: var(--text-3); line-height: 1.7; margin-bottom: 20px; }
.footer-socials { display: flex; gap: 8px; }
.social-btn {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--surface); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; cursor: pointer; color: var(--text-2);
  transition: all .2s;
}
.social-btn:hover { background: var(--accent-glow); border-color: rgba(112,80,255,0.3); color: var(--text); }
.footer-heading { font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 16px; text-transform: uppercase; letter-spacing: .8px; }
.footer-link {
  display: block; font-size: 14px; color: var(--text-3);
  margin-bottom: 10px; text-decoration: none;
  transition: color .2s;
}
.footer-link:hover { color: var(--text); }
.footer-bottom {
  border-top: 1px solid var(--border);
  padding-top: 24px;
  display: flex; justify-content: space-between; align-items: center;
  font-size: 12px; color: var(--text-3);
}

/* ═══════════════ TOAST ═══════════════ */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  border-radius: 14px;
  box-shadow: var(--shadow-lg);
  padding: 14px 18px;
  display: flex; align-items: center; gap: 12px;
  min-width: 280px; max-width: 360px;
  animation: slideIn .3s both;
  transition: opacity .3s, transform .3s;
}
.toast.hiding { opacity: 0; transform: translateX(20px); }
.toast-icon { font-size: 18px; flex-shrink: 0; }
.toast-text { flex: 1; }
.toast-title { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
.toast-sub { font-size: 12px; color: var(--text-3); }
.toast-close { color: var(--text-3); cursor: pointer; font-size: 14px; transition: color .2s; }
.toast-close:hover { color: var(--text); }

/* ═══════════════ KEYFRAMES ═══════════════ */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes float {
  from { transform: translateY(0); }
  to   { transform: translateY(-12px); }
}
@keyframes slideIn {
  from { opacity: 0; transform: translateX(20px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ═══════════════ RESPONSIVE ═══════════════ */
@media (max-width: 1100px) {
  .hero { padding: 48px 40px; }
  .section { padding: 60px 40px; }
  .trust-bar { padding: 18px 40px; }
  .driver-cta { margin: 0 40px 64px; padding: 48px 48px; }
  .footer { padding: 48px 40px 28px; }
}
@media (max-width: 900px) {
  .hero { grid-template-columns: 1fr; min-height: auto; padding: 48px 32px; }
  .rides-grid { grid-template-columns: 1fr; }
  .steps-grid { grid-template-columns: 1fr; }
  .testimonials-grid { grid-template-columns: 1fr; }
  .driver-cta { grid-template-columns: 1fr; padding: 40px 32px; }
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; }
  .dcta-float { display: none; }
}
@media (max-width: 640px) {
  .navbar { padding: 0 18px; }
  .nav-links { display: none; }
  .section { padding: 48px 18px; }
  .hero { padding: 40px 18px; }
  .trust-bar { padding: 16px 18px; gap: 18px; overflow-x: auto; }
  .driver-cta { margin: 0 18px 48px; padding: 32px 24px; }
  .footer { padding: 40px 18px 24px; }
  .footer-grid { grid-template-columns: 1fr; }
  .hero-stats { gap: 24px; }
  .form-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="navbar">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">🚗</div>
    Ride<span>Share</span> LK
  </a>

  <div class="nav-links">
    <a href="index.php"   class="nav-link active">Home</a>
    <a href="results.php" class="nav-link">Find Rides</a>
    <a href="about.php"   class="nav-link">About</a>
    <a href="contact.php" class="nav-link">Contact</a>
  </div>

  <div class="nav-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($_SESSION['user_role'] === 'driver'): ?>
        <a href="driver_dashboard.php" class="btn btn-ghost btn-sm">Dashboard</a>
      <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
        <a href="admin/index.php" class="btn btn-ghost btn-sm">Admin</a>
      <?php else: ?>
        <a href="my_bookings.php" class="btn btn-ghost btn-sm">My Bookings</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-outline btn-sm">Log out</a>
      <a href="<?= $_SESSION['user_role']==='driver' ? 'driver_dashboard.php' : 'my_bookings.php' ?>" class="avatar" title="<?= htmlspecialchars($_SESSION['user_name']) ?>">
        <?= strtoupper(substr($_SESSION['user_name'],0,1)) ?>
      </a>
    <?php else: ?>
      <a href="login.php"    class="btn btn-ghost btn-sm">Log in</a>
      <a href="register.php" class="btn btn-primary btn-sm">Sign up free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-kicker">🇱🇰 Sri Lanka's #1 Ride Sharing Platform</div>
    <h1 class="hero-title">
      Travel Smart,<br>
      <span class="grad">Travel Together</span><br>
      Across Sri Lanka
    </h1>
    <p class="hero-sub">Connect with trusted drivers for safe, affordable intercity rides. Book in minutes, travel with confidence — cars, vans and three-wheelers island-wide.</p>
    <div class="hero-actions">
      <a href="results.php" class="btn btn-primary btn-xl">🔍 Find a Ride</a>
      <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
        <a href="register.php?role=driver" class="btn btn-ghost btn-xl">🚗 Offer a Ride</a>
      <?php endif; ?>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-num">2,400+</div>
        <div class="hero-stat-lbl">Rides Completed</div>
      </div>
      <div>
        <div class="hero-stat-num">850+</div>
        <div class="hero-stat-lbl">Active Drivers</div>
      </div>
      <div>
        <div class="hero-stat-num">4.8★</div>
        <div class="hero-stat-lbl">Avg Rating</div>
      </div>
    </div>
  </div>

  <div class="hero-visual">
    <div class="search-card">
      <div class="search-title">
        <div class="search-title-icon">🔍</div>
        Search Rides
      </div>
      <form action="results.php" method="GET">
        <div class="form-group route-fields">
          <div class="route-line"></div>
          <label class="form-label">From</label>
          <div class="input-wrap">
            <span class="input-icon">🟢</span>
            <input class="form-input" type="text" name="from" placeholder="Departure city (e.g. Kandy)">
          </div>
          <div style="height:12px"></div>
          <label class="form-label">To</label>
          <div class="input-wrap">
            <span class="input-icon">📍</span>
            <input class="form-input" type="text" name="to" placeholder="Destination (e.g. Colombo)">
          </div>
        </div>
        <div class="form-row" style="margin-top: 14px;">
          <div class="form-group">
            <label class="form-label">Date</label>
            <div class="input-wrap">
              <span class="input-icon">📅</span>
              <input class="form-input" type="date" name="date" id="heroDate">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle</label>
            <div class="input-wrap">
              <span class="input-icon">🚘</span>
              <select class="form-input" name="vtype">
                <option value="">All types</option>
                <option value="car">🚗 Car</option>
                <option value="van">🚐 Van</option>
                <option value="three-wheeler">🛺 Three-wheeler</option>
              </select>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="border-radius:14px;padding:15px;font-size:15px;justify-content:center;margin-top:8px">
          Search Available Rides →
        </button>
        <div style="text-align:center;margin-top:14px;font-size:12px;color:var(--text-3);display:flex;align-items:center;justify-content:center;gap:12px">
          <span>🛡️ Verified</span>
          <span>·</span>
          <span>⭐ Rated</span>
          <span>·</span>
          <span>🔒 Safe</span>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- ═══════════════ LIVE RIDES ═══════════════ -->
<section class="section section-alt">
  <div class="section-header">
    <div>
      <div class="section-eyebrow">🗺️ Live Rides</div>
      <h2 class="section-title">Rides leaving <span class="grad">today</span></h2>
    </div>
    <a href="results.php" class="btn btn-ghost">View All Rides →</a>
  </div>

  <div class="rides-grid">
    <?php if (empty($rides)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-3)">
        <div style="font-size:48px;margin-bottom:16px">🛣️</div>
        <p style="font-size:16px;font-weight:600;color:var(--text-2);margin-bottom:8px">No rides posted for today yet</p>
        <a href="results.php" class="btn btn-primary" style="margin-top:12px">Browse upcoming rides →</a>
      </div>
    <?php else: ?>
      <?php foreach ($rides as $i => $r):
          $initials = driverInitials($r['driver_name']);
          $bg       = $avatar_colors[$i % count($avatar_colors)];
          $vlabel   = vehicleLabel($r['vehicle_type']);
          $price    = !empty($r['price']) ? 'Rs. '.number_format($r['price']) : 'Rs. —';
          $seats    = (int)$r['seats_available'];
          $badgeCls = $seats === 1 ? 'badge-amber' : 'badge-green';
      ?>
      <a class="ride-mini" href="ride_detail.php?id=<?= $r['id'] ?>">
        <div class="rm-top">
          <div class="rm-route">
            <div class="rm-route-line"></div>
            <div class="rm-stop">
              <div class="rm-dot start"></div>
              <div class="rm-label">Departs <?= date('g:i A', strtotime($r['ride_time'])) ?></div>
              <div class="rm-city"><?= htmlspecialchars($r['start_location']) ?></div>
            </div>
            <div class="rm-stop" style="margin-top:12px">
              <div class="rm-dot end"></div>
              <div class="rm-label">Destination</div>
              <div class="rm-city"><?= htmlspecialchars($r['destination']) ?></div>
            </div>
          </div>
          <div class="rm-price-pill">
            <div class="rm-price-num"><?= $price ?></div>
            <div class="rm-price-lbl">per seat</div>
          </div>
        </div>

        <div class="rm-driver">
          <div class="rm-avatar" style="background:<?= $bg ?>">
            <?= $initials ?>
          </div>
          <div>
            <div class="rm-driver-name"><?= htmlspecialchars($r['driver_name']) ?></div>
            <?php if ($r['is_verified']): ?>
              <div class="rm-driver-tag">✓ Verified</div>
            <?php endif; ?>
          </div>
          <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
            <span class="badge <?= $badgeCls ?>"><?= $seats ?> seat<?= $seats != 1 ? 's' : '' ?></span>
            <span class="rm-book">Book →</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ TRUST BAR ═══════════════ -->
<div class="trust-bar">
  <div class="trust-item"><div class="trust-dot"></div>Verified Drivers Only</div>
  <div class="trust-item"><div class="trust-dot"></div>4.8/5 Average Rating</div>
  <div class="trust-item"><div class="trust-dot"></div>60% Cheaper Than Taxis</div>
  <div class="trust-item"><div class="trust-dot"></div>Real-time Seat Tracking</div>
  <div class="trust-item"><div class="trust-dot"></div>Cars, Vans & Three-wheelers</div>
  <div class="trust-item"><div class="trust-dot"></div>25+ Districts Covered</div>
</div>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="section" style="text-align:left">
  <div class="section-eyebrow">✨ Simplified</div>
  <h2 class="section-title">How RideShare Works</h2>
  <p class="section-sub">Getting from A to B safely has never been easier.</p>

  <div class="steps-grid">
    <div class="step-card">
      <div class="step-num">1</div>
      <div class="step-icon">🔍</div>
      <div class="step-title">Find or Offer a Ride</div>
      <div class="step-desc">Passengers search matching routes. Drivers post their daily commute with set pricing in seconds.</div>
    </div>
    <div class="step-card">
      <div class="step-num">2</div>
      <div class="step-icon">🎫</div>
      <div class="step-title">Secure Booking</div>
      <div class="step-desc">Our live seat tracker confirms availability instantly. No messy messages or surprise cancellations.</div>
    </div>
    <div class="step-card">
      <div class="step-num">3</div>
      <div class="step-icon">🚗</div>
      <div class="step-title">Meet &amp; Go</div>
      <div class="step-desc">Coordinate through verified profiles. Enjoy safe, reliable transit across Sri Lanka while sharing costs.</div>
    </div>
  </div>
</section>

<!-- ═══════════════ DRIVER CTA ═══════════════ -->
<?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'passenger'): ?>
<div class="driver-cta">
  <div style="position:relative;z-index:2">
    <div class="dcta-tag">🚗 For Drivers</div>
    <h2 class="dcta-title">Have empty seats?<br>Turn them into earnings.</h2>
    <p class="dcta-desc">Already driving between cities? Offer spare seats to passengers going your way — reduce fuel costs and earn money on journeys you're already making.</p>
    <ul class="dcta-perks">
      <li class="dcta-perk"><div class="dcta-check">✓</div> Post your ride in under 2 minutes</li>
      <li class="dcta-perk"><div class="dcta-check">✓</div> Share seats on routes you already drive</li>
      <li class="dcta-perk"><div class="dcta-check">✓</div> Offset fuel and vehicle running costs</li>
    </ul>
    <div class="dcta-actions">
      <a href="register.php?role=driver" class="btn btn-white btn-lg">Become a Driver →</a>
      <a href="about.php" class="btn btn-white-ghost btn-lg">Learn more</a>
    </div>
  </div>
  <div class="dcta-float">
    <div class="dcta-card">
      <div class="dcta-card-icon">🚘</div>
      <div class="dcta-card-title">Share your journey</div>
      <div class="dcta-card-stats">
        <div class="dcta-stat">
          <div class="dcta-stat-num">850+</div>
          <div class="dcta-stat-lbl">Drivers</div>
        </div>
        <div class="dcta-stat">
          <div class="dcta-stat-num">25+</div>
          <div class="dcta-stat-lbl">Cities</div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════ TESTIMONIALS ═══════════════ -->
<section class="section section-alt" style="text-align:left">
  <div style="text-align:center;margin-bottom:0">
    <div class="section-eyebrow" style="margin:0 auto 14px">💬 Reviews</div>
    <h2 class="section-title" style="text-align:center">Trusted by <span class="grad">thousands of riders</span></h2>
  </div>
  <div class="testimonials-grid">
    <div class="testi-card">
      <div class="testi-stars">★★★★★</div>
      <div class="testi-text">"Traveling from Kandy to Colombo used to be exhausting and expensive. With RideShare, I found a verified corporate driver within minutes. Safe, affordable, and incredibly convenient!"</div>
      <div class="testi-author">
        <div class="avatar" style="width:40px;height:40px;background:linear-gradient(135deg,#7050FF,#5B8EFF)">DN</div>
        <div>
          <div class="testi-name">Dilhan Perera</div>
          <div class="testi-role">Regular Passenger</div>
        </div>
      </div>
    </div>
    <div class="testi-card">
      <div class="testi-stars">★★★★★</div>
      <div class="testi-text">"As someone who drives daily down the Southern Expressway for work, sharing empty seats helps me cover over 70% of my fuel costs. The platform makes coordinating super simple."</div>
      <div class="testi-author">
        <div class="avatar" style="width:40px;height:40px;background:linear-gradient(135deg,#10B981,#3B82F6)">MK</div>
        <div>
          <div class="testi-name">Mahesh Silva</div>
          <div class="testi-role">Verified Driver</div>
        </div>
      </div>
    </div>
    <div class="testi-card">
      <div class="testi-stars">★★★★★</div>
      <div class="testi-text">"The security features give me complete peace of mind. I can check driver verifications and ratings before booking any trip. Highly recommended for weekend travelers!"</div>
      <div class="testi-author">
        <div class="avatar" style="width:40px;height:40px;background:linear-gradient(135deg,#EC4899,#F59E0B)">AF</div>
        <div>
          <div class="testi-name">Anjali Fernando</div>
          <div class="testi-role">Passenger</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="section" style="text-align:center">
  <div class="section-eyebrow" style="margin:0 auto 14px">Got Questions?</div>
  <h2 class="section-title">Frequently Asked Questions</h2>

  <div class="faq-wrap">
    <div class="faq-item">
      <button class="faq-trigger" type="button">
        <span>Is carpooling safe in Sri Lanka?</span>
        <span class="faq-icon">▼</span>
      </button>
      <div class="faq-body">
        <div class="faq-body-inner">Safety is our top priority. All drivers must submit official vehicle registration records and identification for admin approval. Users can view comprehensive driver ratings and community histories before confirming a booking.</div>
      </div>
    </div>
    <div class="faq-item">
      <button class="faq-trigger" type="button">
        <span>How are trip costs calculated and split?</span>
        <span class="faq-icon">▼</span>
      </button>
      <div class="faq-body">
        <div class="faq-body-inner">Drivers set a reasonable price per seat when creating a ride listing to offset fuel and expressway toll costs. Passengers can view and verify all seat pricing transparently before finalizing their booking.</div>
      </div>
    </div>
    <div class="faq-item">
      <button class="faq-trigger" type="button">
        <span>What happens if a driver cancels my scheduled ride?</span>
        <span class="faq-icon">▼</span>
      </button>
      <div class="faq-body">
        <div class="faq-body-inner">If a ride is canceled by a driver, passengers receive immediate notifications so they can select alternative listings. Drivers who cancel last-minute without valid reasons are flagged to protect our community metrics.</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="footer">
  <div class="footer-grid">
    <div>
      <div class="footer-brand">🚗 RideShare Sri Lanka</div>
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
        <a class="footer-link" href="register.php?role=driver">Offer a Ride</a>
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
    <span>© <?= date('Y') ?> RideShare Sri Lanka · Made with ❤️ in Sri Lanka</span>
    <span>🌿 Eco-friendly travel</span>
  </div>
</footer>

<script>
document.getElementById('heroDate').valueAsDate = new Date();

// FAQ Accordion
document.querySelectorAll('.faq-trigger').forEach(trigger => {
  trigger.addEventListener('click', () => {
    const item = trigger.parentElement;
    const body = item.querySelector('.faq-body');
    const isOpen = item.classList.contains('open');

    document.querySelectorAll('.faq-item').forEach(el => {
      el.classList.remove('open');
      el.querySelector('.faq-body').style.maxHeight = null;
    });

    if (!isOpen) {
      item.classList.add('open');
      body.style.maxHeight = body.scrollHeight + 'px';
    }
  });
});

function showToast(type, title, sub) {
  const icons  = { success:'✅', info:'ℹ️', warning:'⚠️', error:'❌' };
  const colors = { success:'var(--green)', info:'var(--accent)', warning:'var(--amber)', error:'var(--red)' };
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.style.borderLeftColor = colors[type] || colors.info;
  toast.innerHTML = `
    <div class="toast-icon">${icons[type] || '🔔'}</div>
    <div class="toast-text">
      <div class="toast-title">${title}</div>
      <div class="toast-sub">${sub}</div>
    </div>
    <div class="toast-close" onclick="this.parentElement.remove()">✕</div>
  `;
  container.appendChild(toast);
  setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 300); }, 3500);
}
</script>
</body>
</html>