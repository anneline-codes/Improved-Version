<?php
// ─── EcoSense Rwanda — Single File PHP App ─────────────────────
session_start();

// User roles and their specific AI capabilities
$users = [
  'admin@ecosense.rw' => [
    'name' => 'Mizero Anne Line',
    'role' => 'Project Manager',
    'avatar' => 'MA',
    'ai_permissions' => ['analytics', 'predictions', 'reports', 'alerts']
  ],
  'hardware@ecosense.rw' => [
    'name' => 'HITIMANA TETA Divine',
    'role' => 'Hardware Engineer',
    'avatar' => 'HD',
    'ai_permissions' => ['sensor_analysis', 'maintenance']
  ],
  'bruce@ecosense.rw' => [
    'name' => 'NIBEZA MUGISHA Bruce',
    'role' => 'Software/AI Dev',
    'avatar' => 'NB',
    'ai_permissions' => ['model_training', 'api_access', 'debug']
  ],
  'joseph@ecosense.rw' => [
    'name' => 'IGIRANEZA JOSEPH',
    'role' => 'IoT & Network',
    'avatar' => 'IJ',
    'ai_permissions' => ['sensor_data', 'network_monitor']
  ],
  'belise@ecosense.rw' => [
    'name' => 'MBARUSHIMANA SIMBI Belise',
    'role' => 'Data Analyst & UI',
    'avatar' => 'MS',
    'ai_permissions' => ['visualization', 'trends']
  ]
];

// Enhanced bins data with AI predictions
$bins = [
  ["id"=>"BIN-001","name"=>"Kigali City Tower","lat"=>-1.9441,"lng"=>30.0619,"fill"=>82,"zone"=>"Central","type"=>"Mixed","status"=>"critical","last"=>"2h ago", "ai_prediction"=>"Fill by 18:00", "maintenance_score"=>78],
  ["id"=>"BIN-002","name"=>"Kimironko Market","lat"=>-1.9355,"lng"=>30.0878,"fill"=>45,"zone"=>"East","type"=>"Organic","status"=>"ok","last"=>"5h ago", "ai_prediction"=>"Fill by 22:00", "maintenance_score"=>92],
  ["id"=>"BIN-003","name"=>"Remera Bus Stop","lat"=>-1.9489,"lng"=>30.1079,"fill"=>91,"zone"=>"East","type"=>"Plastic","status"=>"critical","last"=>"8h ago", "ai_prediction"=>"IMMEDIATE", "maintenance_score"=>45],
  ["id"=>"BIN-004","name"=>"Nyamirambo Center","lat"=>-1.9831,"lng"=>30.0385,"fill"=>30,"zone"=>"South","type"=>"Paper","status"=>"ok","last"=>"1h ago", "ai_prediction"=>"Fill by 03:00", "maintenance_score"=>88],
  ["id"=>"BIN-005","name"=>"Kacyiru Ministry","lat"=>-1.9267,"lng"=>30.0653,"fill"=>67,"zone"=>"North","type"=>"Mixed","status"=>"warning","last"=>"3h ago", "ai_prediction"=>"Fill by 14:00", "maintenance_score"=>71],
  ["id"=>"BIN-006","name"=>"Gisozi Memorial","lat"=>-1.9196,"lng"=>30.0524,"fill"=>20,"zone"=>"North","type"=>"Organic","status"=>"ok","last"=>"30m ago", "ai_prediction"=>"Fill by 06:00", "maintenance_score"=>95],
  ["id"=>"BIN-007","name"=>"UTC Shopping Mall","lat"=>-1.9500,"lng"=>30.0588,"fill"=>75,"zone"=>"Central","type"=>"Plastic","status"=>"warning","last"=>"4h ago", "ai_prediction"=>"Fill by 19:00", "maintenance_score"=>63],
  ["id"=>"BIN-008","name"=>"Sonatubes Junction","lat"=>-1.9622,"lng"=>30.0731,"fill"=>55,"zone"=>"Central","type"=>"Metal","status"=>"ok","last"=>"2h ago", "ai_prediction"=>"Fill by 23:00", "maintenance_score"=>81],
  ["id"=>"BIN-009","name"=>"Gikondo Industry","lat"=>-1.9750,"lng"=>30.0820,"fill"=>88,"zone"=>"South","type"=>"Mixed","status"=>"critical","last"=>"6h ago", "ai_prediction"=>"FILL NOW", "maintenance_score"=>52],
  ["id"=>"BIN-010","name"=>"Kinyinya Sector","lat"=>-1.9100,"lng"=>30.0950,"fill"=>38,"zone"=>"North","type"=>"Organic","status"=>"ok","last"=>"1h ago", "ai_prediction"=>"Fill by 02:00", "maintenance_score"=>89],
];

// AI-generated insights based on user role
$ai_insights = [
  'Project Manager' => [
    ['KPI Alert', 'Collection efficiency improved 15% this week', '📈'],
    ['AI Prediction', '3 bins will reach critical level by 6 PM', '🤖'],
    ['Resource Optimization', 'Route optimization can save 23km daily', '🛣️'],
    ['Cost Analysis', 'Projected savings: 245,000 RWF this month', '💰']
  ],
  'Hardware Engineer' => [
    ['Sensor Health', 'BIN-003 ultrasonic sensor degrading', '🔧'],
    ['Battery Status', '5 bins need battery replacement within 48h', '🔋'],
    ['Firmware Update', '3 devices pending OTA update', '📡'],
    ['Diagnostic', 'Network latency: 23ms average', '📊']
  ],
  'Software/AI Dev' => [
    ['Model Accuracy', '92.3% prediction accuracy this week', '🤖'],
    ['API Status', '2,847 requests today, 0 errors', '🌐'],
    ['Training Progress', 'New model: 85% trained', '⚙️'],
    ['Data Pipeline', '1.2GB processed, 0 anomalies', '📊']
  ],
  'IoT & Network' => [
    ['Network Status', 'All 10 gateways online', '📶'],
    ['Data Flow', '124kb/s average throughput', '📡'],
    ['Device Health', '98% uptime across all sensors', '✅'],
    ['Security', 'No intrusion attempts detected', '🔒']
  ],
  'Data Analyst & UI' => [
    ['Visualization', 'Dashboard views up 34% this week', '📊'],
    ['User Engagement', '8 new reports from citizens', '👥'],
    ['Trend Analysis', 'Plastic waste up 8% week-over-week', '📈'],
    ['Export Ready', 'Monthly report ready for download', '📄']
  ]
];

$reports = [
  ["id"=>"RPT-001","location"=>"Remera Market","type"=>"Overflow","severity"=>"High","reporter"=>"Jean Baptiste","time"=>"10 min ago","status"=>"Pending", "ai_priority"=>95],
  ["id"=>"RPT-002","location"=>"Kicukiro Center","type"=>"Illegal Dumping","severity"=>"Medium","reporter"=>"Marie Claire","time"=>"25 min ago","status"=>"In Progress", "ai_priority"=>72],
  ["id"=>"RPT-003","location"=>"Nyabugogo Terminal","type"=>"Bin Damage","severity"=>"Low","reporter"=>"System AI","time"=>"1h ago","status"=>"Resolved", "ai_priority"=>34],
  ["id"=>"RPT-004","location"=>"Gikondo Industry","type"=>"Overflow","severity"=>"High","reporter"=>"Patrick K.","time"=>"2h ago","status"=>"Resolved", "ai_priority"=>88],
  ["id"=>"RPT-005","location"=>"Kimironko Market","type"=>"Bad Odor","severity"=>"Medium","reporter"=>"Alice N.","time"=>"3h ago","status"=>"Pending", "ai_priority"=>67],
];

$alerts = [
  ["bin"=>"BIN-003","location"=>"Remera Bus Stop","fill"=>91,"msg"=>"Bin critically full — AI suggests immediate dispatch","time"=>"5 min ago","level"=>"critical", "ai_action"=>"Dispatch NOW"],
  ["bin"=>"BIN-009","location"=>"Gikondo Industry","fill"=>88,"msg"=>"AI predicts overflow within 45 minutes","time"=>"12 min ago","level"=>"critical", "ai_action"=>"Schedule collection"],
  ["bin"=>"BIN-001","location"=>"Kigali City Tower","fill"=>82,"msg"=>"Fill level accelerating faster than normal","time"=>"25 min ago","level"=>"warning", "ai_action"=>"Monitor rate"],
  ["bin"=>"BIN-007","location"=>"UTC Shopping Mall","fill"=>75,"msg"=>"AI detected abnormal filling pattern","time"=>"40 min ago","level"=>"warning", "ai_action"=>"Investigate"],
  ["bin"=>"BIN-005","location"=>"Kacyiru Ministry","fill"=>67,"msg"=>"Seasonal adjustment: higher traffic detected","time"=>"1h ago","level"=>"info", "ai_action"=>"Update route"],
];

$tasks = [
  ["text"=>"Empty full bin at Kigali Central Market","time"=>"25 minutes ago","done"=>true, "ai_priority"=>"High"],
  ["text"=>"Optimize collection route for Gikondo Sector","time"=>"50 minutes ago","done"=>true, "ai_priority"=>"Medium"],
  ["text"=>"Send report to Supervising Instructor","time"=>"1 hour ago","done"=>true, "ai_priority"=>"Low"],
  ["text"=>"Deploy new sensor at Nyamirambo","time"=>"Pending","done"=>false, "ai_priority"=>"High"],
  ["text"=>"Review AI model accuracy report","time"=>"Pending","done"=>false, "ai_priority"=>"High"],
];

// Handle login with user-specific data
if (isset($_POST['login'])) {
  $email = $_POST['email'] ?? 'admin@ecosense.rw';
  // Find user or use default
  if (isset($users[$email])) {
    $_SESSION['user'] = $users[$email];
    $_SESSION['user']['email'] = $email;
  } else {
    // Default to project manager if email not found
    $_SESSION['user'] = $users['admin@ecosense.rw'];
    $_SESSION['user']['email'] = $email;
  }
  header('Location: ?page=dashboard'); exit;
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?page=login'); exit; }

$report_success = false;
if (isset($_POST['submit_report'])) $report_success = true;

// AI Chatbot response handler
$ai_response = '';
if (isset($_POST['ai_query'])) {
  $query = strtolower($_POST['ai_query']);
  $user_role = $_SESSION['user']['role'] ?? 'Project Manager';
  
  // Simple AI response based on keywords and user role
  if (strpos($query, 'bin') !== false || strpos($query, 'fill') !== false) {
    $critical_bins = array_filter($bins, fn($b) => $b['fill'] >= 80);
    $ai_response = "🤖 AI Analysis: " . count($critical_bins) . " bins need immediate attention. ";
    $ai_response .= "BIN-003 at Remera Bus Stop is at 91% and requires urgent dispatch.";
  } elseif (strpos($query, 'route') !== false || strpos($query, 'collection') !== false) {
    $ai_response = "🗺️ AI Route Optimization: Suggested priority: Central Zone (82% avg fill) → East Zone (68% avg fill) → South Zone (59% avg fill). Estimated fuel savings: 18%";
  } elseif (strpos($query, 'recycle') !== false || strpos($query, 'waste') !== false) {
    $ai_response = "♻️ AI Waste Analysis: Organic waste leads at 38%, followed by Plastic (25%). Recommend increasing composting capacity by 15% next month.";
  } elseif (strpos($query, 'predict') !== false) {
    $ai_response = "🔮 AI Predictions: BIN-001 will reach 95% by 6 PM. BIN-009 requires collection within 2 hours. Overall system efficiency: 84%";
  } else {
    $ai_response = "🤖 EcoSense AI: I'm analyzing the data for you. Based on current trends, collection efficiency could improve by 12% with route optimization.";
  }
}

$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
$user = $_SESSION['user'] ?? null;
if (!$user && !in_array($page, ['login','register'])) { header('Location: ?page=login'); exit; }

// Get user-specific AI insights
$user_insights = $ai_insights[$user['role']] ?? $ai_insights['Project Manager'];

function fillColor($f) { return $f>=80?'#e53e3e':($f>=60?'#ff8c00':'#4caf50'); }
function sevColor($s)  { return $s==='High'?'#e53e3e':($s==='Medium'?'#ff8c00':'#4caf50'); }
function statColor($s) { return $s==='Pending'?'#ff8c00':($s==='In Progress'?'#2b6cb0':'#4caf50'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EcoSense Rwanda - <?=htmlspecialchars($user['name']??'')?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ══════════════════════════════════════
   GLOBAL FONT — Plus Jakarta Sans (same as before)
══════════════════════════════════════ */
:root {
  --font: 'Plus Jakarta Sans', sans-serif;
  --forest:#1a3a2a; --dark-green:#0d2818; --sidebar:#163020;
  --emerald:#1a6b4a; --lime:#4caf50; --mint:#a8e6cf;
  --gold:#f4c430; --amber:#ff8c00; --red:#e53e3e; --blue:#2b6cb0;
  --bg:#f0f4f1; --card:#ffffff; --text:#1a2e1f; --muted:#6b7280;
  --border:#d1e8d8; --header-h:64px; --sidebar-w:220px;
}
*  { margin:0; padding:0; box-sizing:border-box; }
html { font-size:15px; }
body {
  font-family: var(--font);
  background: var(--bg); color: var(--text); min-height:100vh;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  letter-spacing: -0.01em;
}
a { text-decoration:none; color:inherit; }
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-track { background:#e8f0eb; }
::-webkit-scrollbar-thumb { background:var(--emerald); border-radius:3px; }
@keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
@keyframes pulse  { 0%,100%{opacity:1} 50%{opacity:.4} }
.fade { animation:fadeIn .45s ease forwards; }
@keyframes ai-pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
.ai-badge { animation:ai-pulse 2s infinite; }

/* ── AUTH (same) ── */
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:radial-gradient(ellipse at 30% 50%,#1a6b4a22 0%,transparent 60%),#0d2818; }
.auth-card  { width:440px; background:#163020; border:1px solid #1a6b4a55; border-radius:24px; padding:40px; box-shadow:0 40px 80px rgba(0,0,0,.5); }
.auth-logo  { text-align:center; margin-bottom:28px; }
.auth-logo .logo-text { font-family:var(--font); font-size:28px; font-weight:800; color:#fff; letter-spacing:-0.03em; }
.auth-logo .logo-text span { color:var(--lime); }
.auth-logo p { color:#9ca3af; font-size:13px; margin-top:4px; font-weight:400; }
.auth-tabs  { display:flex; background:#0d2818; border-radius:10px; padding:4px; margin-bottom:24px; }
.auth-tab   { flex:1; padding:10px; border-radius:8px; border:none; cursor:pointer; font-family:var(--font); font-size:14px; font-weight:600; transition:all .2s; letter-spacing:-0.01em; }
.auth-tab.active { background:linear-gradient(135deg,var(--emerald),var(--lime)); color:#0d2818; }
.auth-tab:not(.active) { background:transparent; color:#6b7280; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:11px; color:#9ca3af; margin-bottom:6px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }
.form-group input,.form-group select,.form-group textarea {
  width:100%; background:#0d2818; border:1px solid #1a6b4a55; border-radius:10px;
  padding:12px 14px; color:#fff; font-family:var(--font); font-size:14px; font-weight:400; outline:none; transition:border-color .2s;
}
.form-group input:focus,.form-group select:focus { border-color:var(--lime); }
.form-group select option { background:#163020; }
.btn-primary { width:100%; padding:14px; background:linear-gradient(135deg,var(--emerald),var(--lime)); border:none; border-radius:10px; color:#0d2818; font-family:var(--font); font-size:15px; font-weight:700; letter-spacing:-0.02em; cursor:pointer; transition:opacity .2s; margin-top:4px; }
.btn-primary:hover { opacity:.9; }
.auth-switch { text-align:center; margin-top:16px; font-size:13px; color:#6b7280; }
.auth-switch a { color:var(--lime); font-weight:600; }

/* ── LAYOUT (same) ── */
.layout { display:flex; min-height:100vh; }
.sidebar { width:var(--sidebar-w); background:var(--sidebar); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; border-right:1px solid #1a6b4a33; }
.sidebar-logo { padding:18px 20px 16px; border-bottom:1px solid #1a6b4a33; display:flex; align-items:center; gap:10px; }
.logo-icon { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--lime),var(--mint)); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.logo-name { font-family:var(--font); font-size:16px; font-weight:800; color:#fff; line-height:1.1; letter-spacing:-0.03em; }
.logo-name span { color:var(--lime); }
.logo-sub  { font-size:10px; color:#4b7a5e; font-weight:500; letter-spacing:0.05em; }
.nav-section { padding:12px 12px 0; }
.nav-label { font-size:10px; color:#4b7a5e; font-weight:700; letter-spacing:.1em; text-transform:uppercase; padding:0 8px 8px; }
.nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; margin-bottom:2px; cursor:pointer; transition:all .2s; border:none; background:transparent; color:#9ca3af; font-family:var(--font); font-size:13.5px; font-weight:500; width:100%; text-align:left; letter-spacing:-0.01em; }
.nav-item:hover { background:#1a6b4a22; color:#a8e6cf; }
.nav-item.active { background:linear-gradient(135deg,#1a6b4a44,#4caf5022); color:var(--lime); border-left:3px solid var(--lime); font-weight:600; }
.nav-item .nav-icon { font-size:16px; width:20px; text-align:center; flex-shrink:0; }
.nav-badge { margin-left:auto; background:var(--red); color:#fff; border-radius:10px; padding:1px 7px; font-size:10px; font-weight:700; }
.sidebar-bottom { margin-top:auto; padding:16px; border-top:1px solid #1a6b4a33; }
.user-card { display:flex; align-items:center; gap:10px; padding:10px; background:#0d2818; border-radius:12px; cursor:pointer; }
.user-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--emerald),var(--lime)); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#0d2818; flex-shrink:0; font-family:var(--font); }
.user-name { font-size:13px; font-weight:700; color:#fff; letter-spacing:-0.01em; }
.user-role { font-size:11px; color:var(--lime); font-weight:500; }
.sidebar-actions { display:flex; gap:8px; margin-top:10px; }
.sidebar-btn { flex:1; padding:8px; border-radius:8px; border:1px solid #1a6b4a44; background:transparent; color:#6b7280; font-size:12px; cursor:pointer; font-family:var(--font); font-weight:500; }
.sidebar-btn:hover { border-color:var(--lime); color:var(--lime); }

/* ── TOPBAR (same) ── */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
.topbar { height:var(--header-h); background:#fff; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:16px; padding:0 28px; position:sticky; top:0; z-index:50; }
.search-box { flex:1; max-width:520px; display:flex; align-items:center; gap:10px; background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:9px 14px; }
.search-box input { border:none; background:transparent; outline:none; font-family:var(--font); font-size:13.5px; font-weight:400; color:var(--text); width:100%; }
.topbar-right { margin-left:auto; display:flex; align-items:center; gap:12px; }
.icon-btn { width:38px; height:38px; border-radius:10px; border:1px solid var(--border); background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; font-size:16px; }
.notif-badge { position:absolute; top:-4px; right:-4px; width:16px; height:16px; background:var(--red); color:#fff; border-radius:50%; font-size:9px; font-weight:700; display:flex; align-items:center; justify-content:center; font-family:var(--font); }
.topbar-user { display:flex; align-items:center; gap:10px; padding:6px 12px; background:var(--bg); border-radius:10px; border:1px solid var(--border); cursor:pointer; }
.topbar-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--emerald),var(--lime)); display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:12px; font-family:var(--font); }
.topbar-user-info .name { font-size:13px; font-weight:700; letter-spacing:-0.01em; }
.topbar-user-info .role { font-size:11px; color:var(--muted); font-weight:400; }

/* ── PAGE (same) ── */
.page-content { padding:24px 28px; flex:1; }
.page-title { font-family:var(--font); font-size:22px; font-weight:800; margin-bottom:4px; letter-spacing:-0.03em; }
.page-sub   { font-size:13px; color:var(--muted); margin-bottom:24px; font-weight:400; }

/* ── STAT CARDS (same) ── */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px; }
.stat-card { border-radius:14px; padding:20px 22px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 12px rgba(0,0,0,.06); position:relative; overflow:hidden; }
.stat-card::after { content:''; position:absolute; top:-20px; right:-20px; width:80px; height:80px; border-radius:50%; opacity:.12; }
.stat-card.green { background:linear-gradient(135deg,#e8f5e9,#c8e6c9); } .stat-card.green::after { background:var(--lime); }
.stat-card.amber { background:linear-gradient(135deg,#fff8e1,#ffecb3); } .stat-card.amber::after { background:var(--gold); }
.stat-card.blue  { background:linear-gradient(135deg,#e3f2fd,#bbdefb); } .stat-card.blue::after  { background:var(--blue); }
.stat-card.teal  { background:linear-gradient(135deg,#e0f2f1,#b2dfdb); } .stat-card.teal::after  { background:#009688; }
.stat-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
.stat-card.green .stat-icon{background:#4caf5022;} .stat-card.amber .stat-icon{background:#ff8c0022;}
.stat-card.blue  .stat-icon{background:#2b6cb022;} .stat-card.teal  .stat-icon{background:#00968822;}
.stat-value { font-family:var(--font); font-size:28px; font-weight:800; line-height:1; letter-spacing:-0.04em; }
.stat-card.green .stat-value{color:#2e7d32;} .stat-card.amber .stat-value{color:#e65100;}
.stat-card.blue  .stat-value{color:#1565c0;} .stat-card.teal  .stat-value{color:#00695c;}
.stat-label { font-size:12px; color:#6b7280; margin-top:3px; font-weight:500; }

/* ── AI SPECIFIC STYLES ── */
.ai-section { background:linear-gradient(135deg,#f0f9ff,#e6f7e6); border-radius:16px; padding:20px; margin-bottom:20px; border:1px solid var(--lime); position:relative; overflow:hidden; }
.ai-section::before { content:'🤖'; position:absolute; right:20px; bottom:20px; font-size:48px; opacity:0.1; }
.ai-title { font-size:14px; font-weight:700; color:var(--emerald); margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.ai-badge { background:var(--emerald); color:#fff; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:600; margin-left:8px; }
.ai-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-top:12px; }
.ai-card { background:rgba(255,255,255,0.7); border-radius:12px; padding:14px; backdrop-filter:blur(5px); }
.ai-insight-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid rgba(76,175,80,0.1); }
.ai-insight-item:last-child { border-bottom:none; }
.ai-pulse { animation:ai-pulse 2s infinite; }

/* AI Chatbot */
.ai-chat { position:fixed; bottom:20px; right:20px; width:320px; background:white; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); border:1px solid var(--border); z-index:1000; transition:all 0.3s; }
.ai-chat.collapsed { transform:translateY(calc(100% - 60px)); }
.ai-chat-header { padding:15px 20px; background:linear-gradient(135deg,var(--emerald),var(--lime)); border-radius:16px 16px 0 0; color:white; cursor:pointer; display:flex; align-items:center; gap:8px; font-weight:600; }
.ai-chat-body { padding:15px; max-height:300px; overflow-y:auto; background:#f9f9f9; }
.ai-message { margin-bottom:12px; padding:10px 14px; border-radius:14px; max-width:85%; }
.ai-message.user { background:linear-gradient(135deg,var(--emerald),var(--lime)); color:white; margin-left:auto; border-radius:14px 14px 0 14px; }
.ai-message.bot { background:white; border:1px solid var(--border); margin-right:auto; border-radius:14px 14px 14px 0; }
.ai-input { display:flex; gap:8px; padding:10px; background:white; border-top:1px solid var(--border); }
.ai-input input { flex:1; padding:10px; border:1px solid var(--border); border-radius:8px; font-family:var(--font); }
.ai-input button { padding:10px 15px; background:var(--emerald); color:white; border:none; border-radius:8px; cursor:pointer; }

/* ── Rest of the styles (same as before) ── */
.grid-2-1{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;}
.grid-2  {display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.grid-4  {display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
.card { background:var(--card); border-radius:16px; border:1px solid var(--border); box-shadow:0 2px 12px rgba(0,0,0,.05); overflow:hidden; }
.card-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-title  { font-family:var(--font); font-size:14.5px; font-weight:700; letter-spacing:-0.02em; }
.card-sub    { font-size:12px; color:var(--muted); font-weight:400; }
.card-body   { padding:18px 20px; }
.card-actions{ display:flex; gap:6px; }
.card-btn { padding:5px 10px; border-radius:6px; border:1px solid var(--border); background:transparent; font-size:12px; cursor:pointer; color:var(--muted); font-family:var(--font); font-weight:500; }
.card-btn:hover { border-color:var(--lime); color:var(--emerald); }
#kigali-map { height:360px; border-radius:0 0 14px 14px; }
.fill-bar-bg    { background:#e8f0eb; border-radius:4px; height:6px; overflow:hidden; }
.fill-bar-inner { height:100%; border-radius:4px; transition:width .8s ease; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th { padding:10px 14px; font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--border); text-align:left; background:var(--bg); font-family:var(--font); }
.data-table td { padding:12px 14px; font-size:13px; border-bottom:1px solid #f0f4f1; vertical-align:middle; font-family:var(--font); }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#f8fbf9; }
.badge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; display:inline-block; font-family:var(--font); }
.badge-green{background:#e8f5e9;color:#2e7d32;} .badge-amber{background:#fff3e0;color:#e65100;}
.badge-red  {background:#fce4ec;color:#c62828;} .badge-blue {background:#e3f2fd;color:#1565c0;}
.badge-gray {background:#f3f4f6;color:#4b5563;}
.pie-legend { display:flex; flex-direction:column; gap:8px; }
.pie-legend-item { display:flex; align-items:center; gap:8px; font-size:13px; }
.pie-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.pie-pct  { margin-left:auto; font-weight:700; color:var(--text); min-width:32px; text-align:right; }
.pie-bar-val { color:var(--muted); font-size:12px; min-width:28px; text-align:right; }
.right-panel { display:flex; flex-direction:column; gap:16px; }
.tasks-card .task-item { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
.tasks-card .task-item:last-child { border-bottom:none; }
.task-check { width:18px; height:18px; border-radius:50%; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
.task-check.done { background:var(--lime); border-color:var(--lime); color:#fff; font-size:10px; }
.task-text { font-size:13px; font-weight:500; line-height:1.4; }
.task-time { font-size:11px; color:var(--muted); margin-top:2px; }
.status-item { display:flex; align-items:center; gap:8px; padding:8px 0; font-size:13px; font-weight:500; border-bottom:1px solid var(--border); }
.status-item:last-child { border-bottom:none; }
.status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.status-dot.green{background:var(--lime);} .status-dot.amber{background:var(--amber);}
.status-dot.red  {background:var(--red);animation:pulse 1.5s infinite;}
.recycle-stat  { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
.recycle-stat:last-child { border-bottom:none; }
.recycle-pct   { font-family:var(--font); font-size:20px; font-weight:800; color:var(--emerald); letter-spacing:-0.03em; }
.recycle-arrow { color:var(--lime); font-size:12px; font-weight:700; margin-right:8px; }
.recycle-label { font-size:12px; color:var(--muted); }
.recycle-icon  { font-size:22px; }
.bin-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
.bin-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; transition:all .2s; cursor:pointer; }
.bin-card:hover { border-color:var(--lime); box-shadow:0 4px 20px rgba(76,175,80,.15); transform:translateY(-2px); }
.bin-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.bin-name { font-family:var(--font); font-size:14px; font-weight:700; margin-bottom:3px; letter-spacing:-0.02em; }
.bin-id   { font-size:11px; color:var(--muted); font-weight:500; }
.bin-fill-label { display:flex; justify-content:space-between; margin-bottom:5px; font-size:12px; color:var(--muted); font-weight:500; }
.bin-fill-val { font-weight:700; }
.bin-meta { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; align-items:center; }
.bin-tag  { background:var(--bg); padding:3px 9px; border-radius:6px; font-size:11px; color:var(--muted); font-weight:600; }
.alert-item { display:flex; align-items:center; gap:16px; padding:16px 20px; border-bottom:1px solid var(--border); }
.alert-item:last-child { border-bottom:none; }
.alert-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.alert-dot.critical{background:var(--red);animation:pulse 1.2s infinite;}
.alert-dot.warning {background:var(--amber);}
.alert-dot.info    {background:var(--blue);}
.alert-msg   { flex:1; }
.alert-title { font-size:14px; font-weight:700; margin-bottom:3px; letter-spacing:-0.01em; }
.alert-sub   { font-size:12px; color:var(--muted); }
.alert-time  { font-size:12px; color:var(--muted); white-space:nowrap; }
.alert-fill  { font-size:22px; font-weight:800; min-width:48px; text-align:right; font-family:var(--font); letter-spacing:-0.03em; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-group-light label { font-size:11px; color:var(--muted); display:block; margin-bottom:5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
.form-group-light input,.form-group-light select,.form-group-light textarea { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:9px; padding:11px 13px; color:var(--text); font-family:var(--font); font-size:13.5px; font-weight:400; outline:none; transition:border-color .2s; }
.form-group-light input:focus,.form-group-light select:focus,.form-group-light textarea:focus { border-color:var(--lime); }
.btn-submit  { padding:12px 28px; background:linear-gradient(135deg,var(--emerald),var(--lime)); border:none; border-radius:9px; color:#fff; font-family:var(--font); font-size:14px; font-weight:700; letter-spacing:-0.01em; cursor:pointer; transition:opacity .2s; }
.btn-submit:hover { opacity:.9; }
.btn-outline { padding:11px 22px; background:transparent; border:1px solid var(--border); border-radius:9px; color:var(--muted); font-family:var(--font); font-size:13.5px; font-weight:500; cursor:pointer; }
.success-msg { background:#e8f5e9; border:1px solid #a5d6a7; border-radius:10px; padding:14px 18px; color:#2e7d32; font-weight:600; font-size:14px; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
.toggle-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
.toggle-row:last-child { border-bottom:none; }
.toggle-label{ font-size:13px; font-weight:600; letter-spacing:-0.01em; }
.toggle-sub  { font-size:12px; color:var(--muted); }
.toggle { width:44px; height:24px; background:#d1fae5; border-radius:12px; position:relative; cursor:pointer; transition:background .2s; border:none; }
.toggle::after { content:''; position:absolute; top:2px; right:2px; width:20px; height:20px; background:var(--lime); border-radius:50%; transition:right .2s; }
.toggle.off { background:#f3f4f6; }
.toggle.off::after { right:22px; background:#d1d5db; }
.admin-user-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
.admin-user-row:last-child { border-bottom:none; }
.admin-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--emerald),var(--lime)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:12px; flex-shrink:0; font-family:var(--font); }
.admin-name { font-size:13px; font-weight:700; letter-spacing:-0.01em; }
.admin-role { font-size:11px; color:var(--muted); }
.admin-action { margin-left:auto; display:flex; gap:6px; }
.admin-btn { padding:4px 12px; border-radius:6px; font-size:11px; cursor:pointer; border:1px solid; font-family:var(--font); font-weight:600; }
.admin-btn.edit{border-color:var(--blue);color:var(--blue);background:transparent;}
.admin-btn.del {border-color:var(--red); color:var(--red); background:transparent;}
.filter-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.filter-tab { padding:7px 16px; border-radius:20px; border:1px solid var(--border); background:transparent; color:var(--muted); font-family:var(--font); font-size:13px; font-weight:500; cursor:pointer; transition:all .2s; }
.filter-tab.active,.filter-tab:hover { border-color:var(--lime); background:#e8f5e9; color:var(--emerald); font-weight:600; }
.leaflet-popup-content-wrapper { border-radius:12px !important; font-family:var(--font) !important; border:1px solid var(--border); box-shadow:0 8px 24px rgba(0,0,0,.12) !important; }
.leaflet-popup-content { font-size:13px; font-family:var(--font) !important; }
.popup-title { font-family:var(--font); font-weight:800; font-size:14px; margin-bottom:8px; color:var(--text); letter-spacing:-0.02em; }
.popup-row { display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f0f4f1; font-size:12px; }
.popup-row:last-child { border-bottom:none; }
.popup-dispatch { width:100%; margin-top:10px; padding:8px; background:linear-gradient(135deg,var(--emerald),var(--lime)); border:none; border-radius:7px; color:#fff; font-family:var(--font); font-size:12px; font-weight:700; cursor:pointer; }
.route-card  { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px 18px; margin-bottom:12px; display:flex; align-items:center; gap:16px; }
.route-zone  { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,var(--emerald),var(--lime)); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.route-name  { font-family:var(--font); font-size:14px; font-weight:700; margin-bottom:3px; letter-spacing:-0.02em; }
.route-meta  { font-size:12px; color:var(--muted); }
.progress-bar { height:4px; background:#e8f0eb; border-radius:2px; overflow:hidden; width:80px; margin-top:4px; }
.progress-inner { height:100%; background:linear-gradient(90deg,var(--emerald),var(--lime)); border-radius:2px; }
.chart-wrap { position:relative; }
</style>
</head>
<body>

<?php if ($page === 'login' || $page === 'register'): ?>
<div class="auth-wrap">
  <div class="auth-card fade">
    <div class="auth-logo">
      <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#1a6b4a,#4caf50);display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 12px;">🌱</div>
      <div class="logo-text">Eco<span>Sense</span> Rwanda</div>
      <p>Smart Sensing for a Cleaner, Greener World</p>
    </div>
    <div class="auth-tabs">
      <button class="auth-tab <?= $page==='login'?'active':'' ?>" onclick="location='?page=login'">Sign In</button>
      <button class="auth-tab <?= $page==='register'?'active':'' ?>" onclick="location='?page=register'">Create Account</button>
    </div>
    <?php if ($page === 'login'): ?>
    <form method="POST">
      <div class="form-group"><label>Email Address</label>
        <select name="email" style="width:100%; background:#0d2818; border:1px solid #1a6b4a55; border-radius:10px; padding:12px 14px; color:#fff;">
          <option value="admin@ecosense.rw">Mizero Anne Line (Project Manager)</option>
          <option value="hardware@ecosense.rw">HITIMANA TETA Divine (Hardware Engineer)</option>
          <option value="bruce@ecosense.rw">NIBEZA MUGISHA Bruce (Software/AI Dev)</option>
          <option value="joseph@ecosense.rw">IGIRANEZA JOSEPH (IoT & Network)</option>
          <option value="belise@ecosense.rw">MBARUSHIMANA SIMBI Belise (Data Analyst)</option>
        </select>
      </div>
      <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" value="password"></div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500;"><input type="checkbox" checked> Remember me</label>
        <a href="#" style="color:var(--lime);font-weight:600;">Forgot password?</a>
      </div>
      <button type="submit" name="login" class="btn-primary">Sign In to EcoSense →</button>
    </form>
    <?php else: ?>
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group"><label>First Name</label><input type="text" placeholder="Anne Line"></div>
        <div class="form-group"><label>Last Name</label><input type="text" placeholder="Mizero"></div>
      </div>
      <div class="form-group"><label>Email Address</label><input type="email" placeholder="you@ecosense.rw"></div>
      <div class="form-group"><label>Password</label><input type="password" placeholder="Create a strong password"></div>
      <div class="form-group"><label>Role</label><select><option>Citizen</option><option>Cleaning Agency</option><option>Administrator</option><option>Farmer</option><option>Collection Driver</option></select></div>
      <button type="submit" name="login" class="btn-primary">Create My Account →</button>
    </form>
    <?php endif; ?>
    <div class="auth-switch"><?= $page==='login'?'No account? <a href="?page=register">Sign up free</a>':'Already have an account? <a href="?page=login">Sign in</a>' ?></div>
  </div>
</div>

<?php else: ?>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🌱</div>
      <div><div class="logo-name">Eco<span>Sense</span></div><div class="logo-sub">Rwanda</div></div>
    </div>
    <nav class="nav-section" style="flex:1;overflow-y:auto;">
      <div class="nav-label">Main Menu</div>
      <?php foreach ([['dashboard','🏠','Dashboard',''],['bins','🗑️','Smart Bins',''],['collection','🚛','Waste Collection',''],['analytics','♻️','Recycling Analytics',''],['reports','📋','Reports',''],['alerts','🔔','Alerts','3']] as [$p,$icon,$label,$badge]): ?>
      <a href="?page=<?=$p?>" class="nav-item <?=$page===$p?'active':''?>">
        <span class="nav-icon"><?=$icon?></span><?=$label?>
        <?php if($badge):?><span class="nav-badge"><?=$badge?></span><?php endif;?>
      </a>
      <?php endforeach; ?>
      <div class="nav-label" style="margin-top:16px;">System</div>
      <a href="?page=settings" class="nav-item <?=$page==='settings'?'active':''?>"><span class="nav-icon">⚙️</span> Settings</a>
      <a href="?page=admin"    class="nav-item <?=$page==='admin'?'active':''?>"><span class="nav-icon">👤</span> Admin Panel</a>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-card">
        <div class="user-avatar"><?=htmlspecialchars($user['avatar']??'A')?></div>
        <div><div class="user-name"><?=htmlspecialchars($user['name']??'Admin')?></div><div class="user-role"><?=htmlspecialchars($user['role']??'Administrator')?></div></div>
      </div>
      <div class="sidebar-actions">
        <button class="sidebar-btn" onclick="location='?logout'">⏻ Logout</button>
        <button class="sidebar-btn" onclick="location='?page=settings'">≡ Settings</button>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="search-box"><span style="color:var(--muted)">🔍</span><input type="text" placeholder="Enter your search here..."></div>
      <div class="topbar-right">
        <div class="icon-btn">✉️</div>
        <div class="icon-btn">🔔<span class="notif-badge">3</span></div>
        <div class="topbar-user">
          <div class="topbar-avatar"><?=htmlspecialchars($user['avatar']??'A')?></div>
          <div class="topbar-user-info"><div class="name"><?=htmlspecialchars($user['name']??'Admin')?></div><div class="role"><?=htmlspecialchars($user['role']??'Administrator')?></div></div>
          <span style="color:#9ca3af;font-size:12px;">▾</span>
        </div>
      </div>
    </header>

    <!-- AI Insights Section - Personalized based on user role -->
    <div class="page-content fade">
      <div class="ai-section">
        <div class="ai-title">
          🤖 AI-Powered Insights for <?=htmlspecialchars($user['name'])?> 
          <span class="ai-badge"><?=htmlspecialchars($user['role'])?></span>
        </div>
        <div class="ai-grid">
          <?php foreach($user_insights as $insight): ?>
          <div class="ai-card">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
              <span style="font-size:20px;"><?=$insight[2]?></span>
              <span style="font-weight:600; font-size:13px; color:var(--emerald);"><?=$insight[0]?></span>
            </div>
            <div style="font-size:13px; color:var(--text);"><?=$insight[1]?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

<?php if ($page==='dashboard'): $criticalCount=count(array_filter($bins,fn($b)=>$b['fill']>=80)); ?>
  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">🗑️</div><div><div class="stat-value"><?=count($bins)?></div><div class="stat-label">Smart Bins</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🏭</div><div><div class="stat-value">589 <span style="font-size:16px;font-weight:600">kg</span></div><div class="stat-label">Waste Collected Today</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🌿</div><div><div class="stat-value">236 <span style="font-size:16px;font-weight:600">kg</span></div><div class="stat-label">Organic Waste Treated</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">♻️</div><div><div class="stat-value">36%</div><div class="stat-label">Recycling Rate</div></div></div>
  </div>
  <div class="grid-2-1">
    <div class="card">
      <div class="card-header">
        <div class="card-title">📍 Smart Bins · <span style="color:var(--muted);font-weight:400">Kigali</span></div>
        <div class="card-actions"><select class="card-btn" style="border-radius:8px;padding:5px 10px;"><option>🟢 Waste Levels</option><option>🔵 All Bins</option><option>🔴 Critical Only</option></select></div>
      </div>
      <div id="kigali-map"></div>
    </div>
    <div class="right-panel">
      <div class="card tasks-card">
        <div class="card-header"><div class="card-title">Today's Tasks <span class="ai-badge" style="margin-left:8px;">AI Priority</span></div><button class="card-btn">···</button></div>
        <div class="card-body" style="padding:10px 18px;">
          <?php foreach(array_slice($tasks,0,4) as $t): ?>
          <div class="task-item">
            <div class="task-check <?=$t['done']?'done':''?>"><?=$t['done']?'✓':''?></div>
            <div style="flex:1;">
              <div class="task-text"><?=htmlspecialchars($t['text'])?></div>
              <div class="task-time"><?=$t['time']?> · AI Priority: <span style="color:<?=$t['ai_priority']==='High'?'var(--red)':($t['ai_priority']==='Medium'?'var(--amber)':'var(--green)')?>; font-weight:600;"><?=$t['ai_priority']?></span></div>
            </div>
          </div>
          <?php endforeach; ?>
          <div style="text-align:center;padding:10px 0 4px;"><a href="?page=reports" style="font-size:13px;color:var(--emerald);font-weight:600;">View all tasks →</a></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">System Status</div><div class="card-sub">Last 5 min ago</div></div>
          <button onclick="location='?page=reports'" style="padding:8px 16px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:8px;color:#fff;font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;">Generate Report</button>
        </div>
        <div class="card-body" style="padding:8px 18px;">
          <div class="status-item"><div class="status-dot green"></div> All Sensors Operational</div>
          <div class="status-item"><div class="status-dot amber"></div> <?=$criticalCount?> e-Bins Need Emptying</div>
          <div class="status-item"><div class="status-dot green"></div> AI Camera System Active</div>
          <div class="status-item"><div class="status-dot green"></div> Cloud Sync: Online</div>
        </div>
      </div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
    <div class="card">
      <div class="card-header"><div><div class="card-title">Waste Composition</div><div class="card-sub">Kigali Today</div></div><button class="card-btn">···</button></div>
      <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center;">
          <div style="width:130px;height:130px;flex-shrink:0;"><canvas id="pieChart" width="130" height="130"></canvas></div>
          <div class="pie-legend" style="flex:1;">
            <?php foreach([['Organic','#4caf50','38%','56%'],['Plastic','#ff8c00','25%','19%'],['Paper','#2b6cb0','19%','19%'],['Metal','#9ca3af','18%','18%']] as [$l,$c,$p,$b]): ?>
            <div class="pie-legend-item"><div class="pie-dot" style="background:<?=$c?>"></div><span><?=$l?></span><span class="pie-pct"><?=$p?></span><span class="pie-bar-val"><?=$b?></span></div>
            <?php endforeach; ?>
            <a href="?page=analytics" style="font-size:12px;color:var(--emerald);font-weight:600;display:block;margin-top:4px;">··· More all ▾</a>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Recycling Analytics</div><button class="card-btn">···</button></div>
      <div class="card-body">
        <div class="chart-wrap" style="height:100px;margin-bottom:12px;"><canvas id="lineChart"></canvas></div>
        <div style="display:flex;border-radius:10px;overflow:hidden;border:1px solid var(--border);">
          <div style="flex:1;text-align:center;padding:10px 6px;background:#e8f5e9;border-right:1px solid var(--border);"><div style="font-family:var(--font);font-size:20px;font-weight:800;color:#2e7d32;letter-spacing:-0.03em;">34%</div><div style="font-size:11px;color:var(--muted);">Today</div></div>
          <div style="flex:1;text-align:center;padding:10px 6px;border-right:1px solid var(--border);"><div style="font-family:var(--font);font-size:20px;font-weight:800;color:var(--emerald);letter-spacing:-0.03em;">36%</div><div style="font-size:11px;color:var(--muted);">This Week</div></div>
          <div style="flex:1;text-align:center;padding:10px 6px;"><div style="font-family:var(--font);font-size:20px;font-weight:800;color:var(--emerald);letter-spacing:-0.03em;">40%</div><div style="font-size:11px;color:var(--muted);">This Month</div></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Recycling Summary</div></div>
      <div class="card-body" style="padding:8px 18px;">
        <?php foreach([['34%','Today','♻️'],['36%','This Week','🗑️'],['40%','This Month','♻️'],['6.2%','Annual Rate','🌿']] as [$pct,$lbl,$icon]): ?>
        <div class="recycle-stat"><div><span class="recycle-arrow">▶</span><span class="recycle-pct"><?=$pct?></span></div><div class="recycle-label"><?=$lbl?></div><div class="recycle-icon"><?=$icon?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php elseif($page==='bins'): ?>
  <div class="page-title">Smart Bins <span class="ai-badge">AI Predictions Active</span></div>
  <div class="page-sub">Real-time fill levels and AI-powered predictions</div>
  <div class="filter-tabs">
    <?php foreach(['All Bins','Critical ≥80%','Warning 60-79%','OK <60%','Central','East','North','South'] as $f): ?>
    <button class="filter-tab <?=$f==='All Bins'?'active':''?>" onclick="filterBins('<?=$f?>')"><?=$f?></button>
    <?php endforeach; ?>
  </div>
  <div class="stats-row" style="margin-bottom:20px;">
    <?php $total=count($bins);$crit=count(array_filter($bins,fn($b)=>$b['fill']>=80));$warn=count(array_filter($bins,fn($b)=>$b['fill']>=60&&$b['fill']<80));$ok=count(array_filter($bins,fn($b)=>$b['fill']<60));?>
    <div class="stat-card green"><div class="stat-icon">🗑️</div><div><div class="stat-value"><?=$total?></div><div class="stat-label">Total Bins</div></div></div>
    <div class="stat-card" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0);"><div class="stat-icon" style="background:#e53e3e22;">🔴</div><div><div class="stat-value" style="color:#c62828;"><?=$crit?></div><div class="stat-label">Critical</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">⚠️</div><div><div class="stat-value"><?=$warn?></div><div class="stat-label">Warning</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">✅</div><div><div class="stat-value" style="color:#00695c;"><?=$ok?></div><div class="stat-label">OK</div></div></div>
  </div>
  <div class="bin-grid" id="bin-grid">
    <?php foreach($bins as $bin): $fc=fillColor($bin['fill']); $bc=$bin['status']==='critical'?'badge-red':($bin['status']==='warning'?'badge-amber':'badge-green'); ?>
    <div class="bin-card" data-zone="<?=$bin['zone']?>" data-status="<?=$bin['status']?>" data-fill="<?=$bin['fill']?>">
      <div class="bin-card-header">
        <div>
          <div class="bin-name"><?=htmlspecialchars($bin['name'])?></div>
          <div class="bin-id"><?=$bin['id']?> · <?=$bin['zone']?> Zone</div>
        </div>
        <span class="badge <?=$bc?>"><?=$bin['status']?></span>
      </div>
      <div class="bin-fill-label">
        <span>Fill Level</span>
        <span class="bin-fill-val" style="color:<?=$fc?>"><?=$bin['fill']?>%</span>
      </div>
      <div class="fill-bar-bg"><div class="fill-bar-inner" style="width:<?=$bin['fill']?>%;background:<?=$fc?>"></div></div>
      <div style="display:flex; justify-content:space-between; margin:8px 0; font-size:12px;">
        <span>🤖 AI Prediction:</span>
        <span style="font-weight:700; color:<?=$bin['ai_prediction']==='IMMEDIATE'?'var(--red)':($bin['ai_prediction']==='FILL NOW'?'var(--red)':'var(--emerald)')?>;"><?=$bin['ai_prediction']?></span>
      </div>
      <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:11px;">
        <span>Maintenance Score:</span>
        <div style="display:flex; align-items:center; gap:5px;">
          <div class="fill-bar-bg" style="width:50px;"><div class="fill-bar-inner" style="width:<?=$bin['maintenance_score']?>%;background:<?=$bin['maintenance_score']>=80?'var(--lime)':($bin['maintenance_score']>=60?'var(--amber)':'var(--red)')?>"></div></div>
          <span><?=$bin['maintenance_score']?>%</span>
        </div>
      </div>
      <div class="bin-meta">
        <span class="bin-tag">🏷️ <?=$bin['type']?></span>
        <span class="bin-tag">🕒 <?=$bin['last']?></span>
      </div>
      <?php if($bin['fill']>=80): ?>
      <button onclick="alert('🚛 AI-optimized dispatch request sent for <?=$bin['id']?>!')" style="width:100%;margin-top:12px;padding:9px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:8px;color:#fff;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;">🤖 AI Dispatch Collection</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

<?php elseif($page==='collection'): ?>
  <div class="page-title">Waste Collection <span class="ai-badge">AI Route Optimization</span></div>
  <div class="page-sub">Optimized collection routes and vehicle tracking</div>
  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">🚛</div><div><div class="stat-value">14</div><div class="stat-label">Active Vehicles</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🛣️</div><div><div class="stat-value">247 km</div><div class="stat-label">Total Route Distance</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">✅</div><div><div class="stat-value">12/14</div><div class="stat-label">Routes Completed</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">⛽</div><div><div class="stat-value">18%</div><div class="stat-label">Fuel Saved vs Last Week</div></div></div>
  </div>
  <div class="grid-2-1">
    <div class="card">
      <div class="card-header"><div class="card-title">🗺️ AI-Optimized Routes — Kigali</div></div>
      <div id="collection-map" style="height:380px;border-radius:0 0 14px 14px;"></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Collection Zones <span class="ai-badge">AI Efficiency</span></div></div>
      <div class="card-body" style="padding:12px 18px;">
        <?php foreach([['Central','6 bins','21 km','88%','🟢','High priority'],['East','8 bins','34 km','72%','🟡','Medium priority'],['North','5 bins','28 km','91%','🟢','Low priority'],['South','4 bins','19 km','85%','🟢','Medium priority']] as [$z,$bc2,$dist,$eff,$dot,$priority]): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-family:var(--font);font-weight:700;font-size:13.5px;letter-spacing:-0.02em;"><?=$dot?> <?=$z?> Zone</span>
            <span style="font-size:12px;color:var(--muted);"><?=$bc2?> · <?=$dist?></span>
          </div>
          <div class="fill-bar-bg"><div class="fill-bar-inner" style="width:<?=$eff?>;background:linear-gradient(90deg,var(--emerald),var(--lime))"></div></div>
          <div style="font-size:11px;color:var(--muted);margin-top:4px;"><?=$eff?> efficiency · AI: <?=$priority?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Active Routes with AI Predictions</div></div>
    <div class="card-body">
      <?php foreach([['Central Zone A','🏙️','6 bins','21 km','94%','Active','ETA: 45 min'],['East Zone B','🌆','8 bins','34 km','65%','Active','ETA: 1h 20min'],['North Zone C','🌄','5 bins','28 km','100%','Completed','Completed'],['South Zone D','🏘️','4 bins','19 km','100%','Completed','Completed'],['Gikondo Industrial','🏭','3 bins','12 km','20%','Pending','AI: Urgent']] as [$name,$icon,$bc3,$dist,$prog,$status,$eta]): $sc=$status==='Completed'?'badge-green':($status==='Active'?'badge-blue':'badge-gray'); ?>
      <div class="route-card"><div class="route-zone"><?=$icon?></div><div style="flex:1;"><div class="route-name"><?=$name?></div><div class="route-meta"><?=$bc3?> · <?=$dist?> · <?=$eta?></div><div class="progress-bar"><div class="progress-inner" style="width:<?=$prog?>"></div></div></div><span class="badge <?=$sc?>"><?=$status?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

<?php elseif($page==='analytics'): ?>
  <div class="page-title">Recycling Analytics <span class="ai-badge">AI Pattern Recognition</span></div>
  <div class="page-sub">Data-driven insights on waste processing and circular economy</div>
  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">🥤</div><div><div class="stat-value">120t</div><div class="stat-label">Plastic Recycled (6mo)</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">🌿</div><div><div class="stat-value">230t</div><div class="stat-label">Organic Composted</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">📰</div><div><div class="stat-value">78t</div><div class="stat-label">Paper Recycled</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🔩</div><div><div class="stat-value">45t</div><div class="stat-label">Metal Recycled</div></div></div>
  </div>
  <div class="grid-2-1">
    <div class="card">
      <div class="card-header"><div class="card-title">Monthly Waste Collection Breakdown</div><div class="card-sub">tonnes per category with AI forecast</div></div>
      <div class="card-body"><canvas id="barChart" height="200"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">🎯 Recycling Rate <span class="ai-badge">Target Tracking</span></div></div>
      <div class="card-body" style="text-align:center;padding:24px 20px;">
        <div style="font-family:var(--font);font-size:56px;font-weight:800;color:var(--emerald);letter-spacing:-0.05em;">6.2%</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:14px;">Current · Target: 25% by 2030</div>
        <div class="fill-bar-bg" style="height:10px;border-radius:5px;"><div class="fill-bar-inner" style="width:24.8%;background:linear-gradient(90deg,var(--emerald),var(--lime));height:10px;border-radius:5px;"></div></div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px;">24.8% of 2030 goal achieved</div>
        <div style="font-size:12px;margin-top:8px;padding:8px;background:var(--bg);border-radius:8px;">
          🤖 AI Forecast: 8.4% by Dec 2024
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;">
          <?php foreach([['1,850 kg','Compost/Day'],['52 kWh','Energy/Day'],['23','Farmers Served'],['8–10','Homes Powered']] as [$v,$l]): ?>
          <div style="background:var(--bg);border-radius:10px;padding:12px;text-align:center;"><div style="font-family:var(--font);font-size:18px;font-weight:800;color:var(--emerald);letter-spacing:-0.03em;"><?=$v?></div><div style="font-size:11px;color:var(--muted);font-weight:500;"><?=$l?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Waste Composition Breakdown with AI Classification</div></div>
    <div class="card-body">
      <div class="grid-4">
        <?php foreach([['🌿','Organic','49%','2318 kg','#4caf50','Converted to compost & biogas'],['🥤','Plastic','25%','1183 kg','#2b6cb0','Sorted for recycling'],['📰','Paper','16%','757 kg','#f4c430','Recycled at paper plants'],['🔩','Metal','10%','473 kg','#9ca3af','Sold to metal recyclers']] as [$icon,$type,$pct,$kg,$color,$desc]): ?>
        <div style="background:var(--bg);border-radius:14px;padding:20px;text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;"><?=$icon?></div>
          <div style="font-family:var(--font);font-size:28px;font-weight:800;color:<?=$color?>;letter-spacing:-0.04em;"><?=$pct?></div>
          <div style="font-family:var(--font);font-size:14px;font-weight:700;margin-bottom:4px;"><?=$type?></div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:10px;"><?=$kg?> today</div>
          <div class="fill-bar-bg" style="height:4px;"><div class="fill-bar-inner" style="width:<?=$pct?>;background:<?=$color?>"></div></div>
          <div style="font-size:11px;color:var(--muted);margin-top:8px;line-height:1.5;"><?=$desc?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php elseif($page==='reports'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
    <div><div class="page-title">Waste Reports <span class="ai-badge">AI Priority Scoring</span></div><div class="page-sub">Citizen and AI-generated incident reports</div></div>
    <button onclick="var f=document.getElementById('report-form');f.style.display=f.style.display==='none'?'block':'none'" style="padding:11px 22px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:10px;color:#fff;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;">+ New Report</button>
  </div>
  <?php if($report_success): ?><div class="success-msg">✅ Report submitted successfully! AI has analyzed and prioritized it.</div><?php endif; ?>
  <div id="report-form" style="display:none;margin-bottom:20px;">
    <div class="card">
      <div class="card-header"><div class="card-title">📋 Submit New Report (AI will analyze)</div></div>
      <div class="card-body">
        <form method="POST">
          <div class="form-row" style="margin-bottom:14px;">
            <div class="form-group-light"><label>Location / Area</label><input type="text" name="location" placeholder="e.g. Remera Market" required></div>
            <div class="form-group-light"><label>Your Name</label><input type="text" name="reporter" placeholder="Full name" required></div>
          </div>
          <div class="form-row" style="margin-bottom:14px;">
            <div class="form-group-light"><label>Issue Type</label><select name="type"><option>Overflow</option><option>Illegal Dumping</option><option>Bin Damage</option><option>Bad Odor</option><option>Blocked Access</option><option>Other</option></select></div>
            <div class="form-group-light"><label>Severity</label><select name="severity"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
          </div>
          <div class="form-group-light" style="margin-bottom:16px;"><label>Description (optional)</label><textarea name="description" rows="3" placeholder="Describe the issue..."></textarea></div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('report-form').style.display='none'" class="btn-outline">Cancel</button>
            <button type="submit" name="submit_report" class="btn-submit">Submit Report →</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">All Reports (Sorted by AI Priority)</div><div class="card-sub"><?=count($reports)?> total reports</div></div>
    <table class="data-table">
      <thead><tr><th>ID</th><th>Location</th><th>Type</th><th>Severity</th><th>Reporter</th><th>Time</th><th>Status</th><th>AI Priority</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($reports as $r): $sv=sevColor($r['severity']); $ss=statColor($r['status']); ?>
      <tr>
        <td style="font-weight:700;font-size:12px;color:var(--muted);"><?=$r['id']?></td>
        <td style="font-weight:600;"><?=htmlspecialchars($r['location'])?></td>
        <td><span class="badge badge-gray"><?=$r['type']?></span></td>
        <td><span style="background:<?=$sv?>22;color:<?=$sv?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?=$r['severity']?></span></td>
        <td><?=htmlspecialchars($r['reporter'])?></td>
        <td style="color:var(--muted);font-size:12px;"><?=$r['time']?></td>
        <td><span style="background:<?=$ss?>22;color:<?=$ss?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?=$r['status']?></span></td>
        <td><span style="font-weight:700;color:<?=$r['ai_priority']>=80?'var(--red)':($r['ai_priority']>=60?'var(--amber)':'var(--lime)')?>;"><?=$r['ai_priority']?></span></td>
        <td><button onclick="alert('AI assigned to appropriate team!')" style="padding:4px 12px;border-radius:6px;border:1px solid var(--emerald);color:var(--emerald);background:transparent;font-size:11px;cursor:pointer;font-family:var(--font);font-weight:600;">AI Assign</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif($page==='alerts'): ?>
  <div class="page-title">🔔 Alerts <span class="ai-badge">AI Prioritized</span></div>
  <div class="page-sub">Real-time notifications from sensors and AI systems</div>
  <div class="stats-row">
    <div class="stat-card" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0)"><div class="stat-icon" style="background:#e53e3e22">🔴</div><div><div class="stat-value" style="color:#c62828">2</div><div class="stat-label">Critical Alerts</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🟡</div><div><div class="stat-value">2</div><div class="stat-label">Warning Alerts</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">ℹ️</div><div><div class="stat-value">1</div><div class="stat-label">Info Alerts</div></div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div><div class="stat-value">18</div><div class="stat-label">Resolved Today</div></div></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Active Alerts with AI Actions</div><button class="card-btn">Mark All Read</button></div>
    <?php foreach($alerts as $a): $dc=$a['level']==='critical'?'critical':($a['level']==='warning'?'warning':'info'); $fc=$a['level']==='critical'?'#e53e3e':($a['level']==='warning'?'#ff8c00':'#2b6cb0'); ?>
    <div class="alert-item">
      <div class="alert-dot <?=$dc?>"></div>
      <div class="alert-msg"><div class="alert-title"><?=htmlspecialchars($a['location'])?></div><div class="alert-sub"><?=htmlspecialchars($a['msg'])?> · <?=$a['bin']?></div></div>
      <div class="alert-fill" style="color:<?=$fc?>"><?=$a['fill']?>%</div>
      <div class="alert-time"><?=$a['time']?></div>
      <div style="display:flex; gap:5px;">
        <span style="font-size:11px; background:var(--bg); padding:3px 8px; border-radius:12px;">🤖 <?=$a['ai_action']?></span>
        <button onclick="alert('AI dispatch initiated for <?=$a['bin']?>!')" style="padding:6px 14px;border-radius:8px;border:none;background:<?=$fc?>22;color:<?=$fc?>;font-size:12px;cursor:pointer;font-family:var(--font);font-weight:700;">Respond</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

<?php elseif($page==='settings'): ?>
  <div class="page-title">Settings</div>
  <div class="page-sub">Configure EcoSense Rwanda system preferences</div>
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><div class="card-title">👤 Profile Settings</div></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border);">
          <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;font-family:var(--font);"><?=htmlspecialchars($user['avatar']??'A')?></div>
          <div><div style="font-family:var(--font);font-weight:800;font-size:16px;letter-spacing:-0.02em;"><?=htmlspecialchars($user['name']??'')?></div><div style="font-size:13px;color:var(--muted);"><?=htmlspecialchars($user['role']??'')?></div></div>
          <button class="btn-outline" style="margin-left:auto;font-size:12px;">Change Photo</button>
        </div>
        <div class="form-row" style="gap:12px;margin-bottom:12px;">
          <div class="form-group-light"><label>Full Name</label><input type="text" value="<?=htmlspecialchars($user['name']??'')?>"></div>
          <div class="form-group-light"><label>Email</label><input type="email" value="<?=htmlspecialchars($user['email']??'')?>"></div>
        </div>
        <div class="form-group-light" style="margin-bottom:14px;"><label>Role</label><select><option selected><?=htmlspecialchars($user['role']??'')?></option></select></div>
        <button class="btn-submit">Save Changes</button>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">🔔 Notification Settings</div></div>
      <div class="card-body">
        <?php foreach([['Bin Critical Alerts','Alert when bin reaches 80%+ capacity',true],['Route Optimization','Notify when new route is generated',true],['AI Detection Alerts','Alert on new waste detection event',false],['Weekly Reports','Send weekly recycling summary',true],['System Maintenance','Notify about system updates',false]] as [$label,$sub,$on]): ?>
        <div class="toggle-row"><div><div class="toggle-label"><?=$label?></div><div class="toggle-sub"><?=$sub?></div></div><button class="toggle <?=$on?'':'off'?>" onclick="this.classList.toggle('off')"></button></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">🔒 Security</div></div>
      <div class="card-body">
        <div class="form-group-light" style="margin-bottom:12px;"><label>Current Password</label><input type="password" placeholder="••••••••"></div>
        <div class="form-group-light" style="margin-bottom:12px;"><label>New Password</label><input type="password" placeholder="Create new password"></div>
        <div class="form-group-light" style="margin-bottom:16px;"><label>Confirm New Password</label><input type="password" placeholder="Repeat new password"></div>
        <button class="btn-submit">Update Password</button>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">⚙️ AI System Configuration</div></div>
      <div class="card-body">
        <div class="form-group-light" style="margin-bottom:12px;"><label>AI Alert Threshold (%)</label><input type="number" value="80" min="50" max="100"></div>
        <div class="form-group-light" style="margin-bottom:12px;"><label>AI Sensitivity</label><select><option>High (&gt;85%)</option><option selected>Standard (&gt;75%)</option><option>Low (&gt;60%)</option></select></div>
        <div class="form-group-light" style="margin-bottom:12px;"><label>AI Prediction Interval</label><select><option selected>Real-time</option><option>Every 5 minutes</option><option>Every 15 minutes</option></select></div>
        <div class="form-group-light" style="margin-bottom:16px;"><label>AI Model Version</label><select><option selected>v2.3.1 (Current)</option><option>v2.4.0-beta</option><option>v2.2.9 (Stable)</option></select></div>
        <button class="btn-submit">Save AI Configuration</button>
      </div>
    </div>
  </div>

<?php elseif($page==='admin'): ?>
  <div class="page-title">Admin Panel</div>
  <div class="page-sub">Manage users, devices, and system access</div>
  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">👥</div><div><div class="stat-value">47</div><div class="stat-label">Total Users</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🗑️</div><div><div class="stat-value">10</div><div class="stat-label">Registered Bins</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🚛</div><div><div class="stat-value">14</div><div class="stat-label">Vehicles Tracked</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">📡</div><div><div class="stat-value">98.6%</div><div class="stat-label">Sensor Uptime</div></div></div>
  </div>
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><div class="card-title">👥 User Management</div><button class="btn-submit" style="padding:7px 14px;font-size:12px;">+ Add User</button></div>
      <div class="card-body">
        <?php foreach([['MA','Mizero Anne Line','Project Manager','admin'],['HD','HITIMANA TETA Divine','Hardware Engineer','engineer'],['NB','NIBEZA MUGISHA Bruce','Software/AI Dev','developer'],['IJ','IGIRANEZA JOSEPH','IoT & Network','specialist'],['MS','MBARUSHIMANA SIMBI Belise','Data Analyst & UI','analyst']] as [$init,$name,$role,$type]): ?>
        <div class="admin-user-row"><div class="admin-avatar"><?=$init?></div><div><div class="admin-name"><?=$name?></div><div class="admin-role"><?=$role?></div></div><span class="badge badge-green" style="margin-left:8px;"><?=$type?></span><div class="admin-action"><button class="admin-btn edit">Edit</button><button class="admin-btn del">Remove</button></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">📊 AI System Overview</div></div>
      <div class="card-body">
        <?php foreach([['API Requests Today','12,483','📡'],['Data Stored','2.4 GB','💾'],['AI Model Accuracy','87.3%','🤖'],['Route Efficiency','84.5%','🛣️'],['App Downloads','1,247','📱'],['AI Predictions Made','3,892','🎯']] as [$l,$v,$icon]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);"><span style="font-size:13px;color:var(--muted);"><?=$icon?> <?=$l?></span><span style="font-family:var(--font);font-weight:800;color:var(--emerald);letter-spacing:-0.02em;"><?=$v?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">🗑️ Registered Smart Bins (with AI Metrics)</div><button class="btn-submit" style="padding:7px 14px;font-size:12px;">+ Register Bin</button></div>
    <table class="data-table">
      <thead><tr><th>Bin ID</th><th>Location</th><th>Zone</th><th>Type</th><th>Fill</th><th>Status</th><th>AI Predict</th><th>Maint Score</th><th>Last Emptied</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($bins as $b): $fc=fillColor($b['fill']); $bc=$b['status']==='critical'?'badge-red':($b['status']==='warning'?'badge-amber':'badge-green'); ?>
      <tr>
        <td style="font-weight:700;font-size:12px;color:var(--muted);"><?=$b['id']?></td>
        <td style="font-weight:600;"><?=htmlspecialchars($b['name'])?></td>
        <td><?=$b['zone']?></td>
        <td><span class="badge badge-gray"><?=$b['type']?></span></td>
        <td><div style="display:flex;align-items:center;gap:8px;"><div class="fill-bar-bg" style="width:60px;"><div class="fill-bar-inner" style="width:<?=$b['fill']?>%;background:<?=$fc?>"></div></div><span style="font-size:12px;font-weight:700;color:<?=$fc?>"><?=$b['fill']?>%</span></div></td>
        <td><span class="badge <?=$bc?>"><?=$b['status']?></span></td>
        <td style="font-size:11px;"><?=$b['ai_prediction']?></td>
        <td><div style="display:flex; align-items:center; gap:5px;"><div class="fill-bar-bg" style="width:30px;"><div class="fill-bar-inner" style="width:<?=$b['maintenance_score']?>%;background:<?=$b['maintenance_score']>=80?'var(--lime)':($b['maintenance_score']>=60?'var(--amber)':'var(--red)')?>"></div></div><?=$b['maintenance_score']?>%</div></td>
        <td style="font-size:12px;color:var(--muted);"><?=$b['last']?></td>
        <td><div style="display:flex;gap:5px;"><button class="admin-btn edit" style="font-size:10px;">Edit</button><button class="admin-btn del" style="font-size:10px;">Remove</button></div></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

      <!-- AI Chatbot Widget -->
      <div class="ai-chat" id="aiChat">
        <div class="ai-chat-header" onclick="toggleChat()">
          <span>🤖</span> EcoSense AI Assistant
          <span style="margin-left:auto;">▼</span>
        </div>
        <div class="ai-chat-body" id="chatBody">
          <div class="ai-message bot">
            Hello <?=htmlspecialchars($user['name'])?>! I'm your AI assistant. Ask me about bins, routes, recycling, or predictions!
          </div>
          <?php if ($ai_response): ?>
          <div class="ai-message bot">
            <?=htmlspecialchars($ai_response)?>
          </div>
          <?php endif; ?>
        </div>
        <form method="POST" class="ai-input">
          <input type="text" name="ai_query" placeholder="Ask AI anything..." required>
          <button type="submit">Send</button>
        </form>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
function toggleChat() {
  const chat = document.getElementById('aiChat');
  chat.classList.toggle('collapsed');
}

<?php if($page==='dashboard'): ?>
(function(){
  const p = document.getElementById('pieChart');
  if(p) new Chart(p,{type:'doughnut',data:{labels:['Organic','Plastic','Paper','Metal'],datasets:[{data:[38,25,19,18],backgroundColor:['#4caf50','#ff8c00','#2b6cb0','#9ca3af'],borderWidth:2,borderColor:'#fff'}]},options:{cutout:'65%',plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:true}});
})();
(function(){
  const l = document.getElementById('lineChart');
  if(!l) return;
  new Chart(l,{type:'line',data:{labels:['M','T','W','T','F','S'],datasets:[{data:[30,34,28,38,35,40],borderColor:'#4caf50',backgroundColor:'#4caf5020',borderWidth:2,fill:true,tension:0.4,pointBackgroundColor:'#4caf50',pointRadius:3}]},options:{plugins:{legend:{display:false}},scales:{y:{min:0,max:45,ticks:{stepSize:10,font:{size:10,family:"'Plus Jakarta Sans',sans-serif"},color:'#9ca3af'},grid:{color:'#f0f4f1'}},x:{ticks:{font:{size:10,family:"'Plus Jakarta Sans',sans-serif"},color:'#9ca3af'},grid:{display:false}}},responsive:true,maintainAspectRatio:false}});
})();
(function(){
  const map = L.map('kigali-map',{zoomControl:true}).setView([-1.9500,30.0619],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors',maxZoom:19}).addTo(map);
  const bins = <?=json_encode(array_map(fn($b)=>['id'=>$b['id'],'name'=>$b['name'],'lat'=>$b['lat'],'lng'=>$b['lng'],'fill'=>$b['fill'],'zone'=>$b['zone'],'type'=>$b['type'],'status'=>$b['status'],'last'=>$b['last'],'ai_prediction'=>$b['ai_prediction']],$bins))?>;
  bins.forEach(b=>{
    const c=b.fill>=80?'#e53e3e':b.fill>=60?'#ff8c00':'#4caf50';
    const icon=L.divIcon({html:`<div style="background:${c};width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;"><span style="transform:rotate(45deg);font-size:10px;font-weight:800;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;">${b.fill}%</span></div>`,className:'',iconSize:[32,32],iconAnchor:[16,32]});
    L.marker([b.lat,b.lng],{icon}).addTo(map).bindPopup(`<div class="popup-title">Smart Bin — Kigali</div><div class="popup-row"><span>ID</span><strong>${b.id}</strong></div><div class="popup-row"><span>Location</span><strong>${b.name}</strong></div><div class="popup-row"><span>Fill Level</span><strong style="color:${c}">${b.fill}%</strong></div><div class="popup-row"><span>AI Prediction</span><strong>${b.ai_prediction}</strong></div><div class="popup-row"><span>Type</span><strong>${b.type}</strong></div><div class="popup-row"><span>Zone</span><strong>${b.zone}</strong></div><div class="popup-row"><span>Last Emptied</span><strong>${b.last}</strong></div><div class="popup-row"><span>Status</span><strong style="color:${c}">${b.status.toUpperCase()}</strong></div><button class="popup-dispatch" onclick="alert('AI dispatch to ${b.name}!')">🤖 AI Dispatch Collection</button>`,{maxWidth:260});
  });
})();
<?php endif; ?>

<?php if($page==='analytics'): ?>
(function(){
  const b=document.getElementById('barChart');
  if(!b) return;
  new Chart(b,{type:'bar',data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Organic',data:[28,32,35,40,45,50],backgroundColor:'#4caf5099',borderRadius:4},{label:'Plastic',data:[12,15,18,22,25,28],backgroundColor:'#ff8c0099',borderRadius:4},{label:'Paper',data:[8,10,12,14,16,18],backgroundColor:'#2b6cb099',borderRadius:4},{label:'Metal',data:[5,6,7,8,9,10],backgroundColor:'#9ca3af99',borderRadius:4}]},options:{plugins:{legend:{position:'bottom',labels:{font:{size:12,family:"'Plus Jakarta Sans',sans-serif"},boxWidth:12}}},scales:{x:{stacked:true,ticks:{color:'#6b7280',font:{family:"'Plus Jakarta Sans',sans-serif"}},grid:{display:false}},y:{stacked:true,ticks:{color:'#6b7280',font:{family:"'Plus Jakarta Sans',sans-serif"}},grid:{color:'#f0f4f1'}}},responsive:true,maintainAspectRatio:false}});
})();
<?php endif; ?>

<?php if($page==='collection'): ?>
(function(){
  const map=L.map('collection-map').setView([-1.9500,30.0619],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(map);
  const routes=[{coords:[[-1.9441,30.0619],[-1.9267,30.0653],[-1.9196,30.0524]],color:'#4caf50',name:'Central Zone A (AI Priority: High)'},{coords:[[-1.9355,30.0878],[-1.9489,30.1079],[-1.9100,30.0950]],color:'#2b6cb0',name:'East Zone B (AI Priority: Medium)'},{coords:[[-1.9831,30.0385],[-1.9750,30.0820],[-1.9622,30.0731]],color:'#ff8c00',name:'South Zone D (AI Priority: Low)'}];
  const bins=<?=json_encode(array_map(fn($b)=>['lat'=>$b['lat'],'lng'=>$b['lng'],'name'=>$b['name'],'fill'=>$b['fill'],'ai_prediction'=>$b['ai_prediction']],$bins))?>;
  bins.forEach(b=>{const c=b.fill>=80?'#e53e3e':b.fill>=60?'#ff8c00':'#4caf50';L.circleMarker([b.lat,b.lng],{color:'#fff',fillColor:c,fillOpacity:1,radius:8,weight:2}).addTo(map).bindPopup(`<b>${b.name}</b><br>Fill: ${b.fill}%<br>AI: ${b.ai_prediction}`);});
  routes.forEach(r=>L.polyline(r.coords,{color:r.color,weight:3,opacity:.8,dashArray:'8,4'}).addTo(map).bindPopup(r.name));
})();
<?php endif; ?>

function filterBins(filter){
  document.querySelectorAll('.filter-tab').forEach(t=>t.classList.remove('active'));
  event.target.classList.add('active');
  document.querySelectorAll('.bin-card').forEach(card=>{
    const fill=parseInt(card.dataset.fill),zone=card.dataset.zone;
    let show=true;
    if(filter==='Critical ≥80%') show=fill>=80;
    else if(filter==='Warning 60-79%') show=fill>=60&&fill<80;
    else if(filter==='OK <60%') show=fill<60;
    else if(['Central','East','North','South'].includes(filter)) show=zone===filter;
    card.style.display=show?'':'none';
  });
}
</script>
<?php endif; ?>
</body>
</html>