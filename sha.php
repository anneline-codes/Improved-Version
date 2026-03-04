<?php
// ─── EcoSense Rwanda — Firebase Realtime Database Integration ──────────
// Auth:     PHP sessions
// Database: Firebase Realtime Database REST API (no service account needed)
// Rules:    Set your RTDB rules to allow read/write (or use auth rules)

session_start();

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION — Firebase Realtime Database
// ══════════════════════════════════════════════════════════════
define('RTDB_URL', 'https://ecosense-rwanda-default-rtdb.firebaseio.com');
// Optional: set a database secret for write auth (Firebase Console → Project Settings → Service Accounts → Database secrets)
// Leave empty if your rules allow public read/write (for dev/demo)
define('RTDB_SECRET', ''); // e.g. 'YOUR_DATABASE_SECRET_HERE'

// ══════════════════════════════════════════════════════════════
//  REALTIME DATABASE REST HELPERS
// ══════════════════════════════════════════════════════════════

/** Build URL with optional auth param */
function rtdb_url(string $path): string {
    $url = RTDB_URL . '/' . ltrim($path, '/') . '.json';
    if (RTDB_SECRET) $url .= '?auth=' . RTDB_SECRET;
    return $url;
}

/** Low-level cURL to RTDB REST API */
function rtdb_request(string $method, string $path, $body = null): mixed {
    $url = rtdb_url($path);
    $ch  = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res   = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno || !$res) return null;
    return json_decode($res, true);
}

/** GET all documents in a path — returns flat PHP array with 'id' injected */
function rtdb_get_all(string $path): array {
    $data = rtdb_request('GET', $path);
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $id => $val) {
        if (is_array($val)) {
            $val['id'] = $id;
            $out[] = $val;
        }
    }
    return $out;
}

// ══════════════════════════════════════════════════════════════
//  READ BINS FROM RTDB — Supports TWO layouts:
//
//  Layout A (your AI model writes detections):
//    detections/BIN-001/{push-key} → { fill_level, waste_type,
//      confidence, timestamp, distance_cm, ... }
//
//  Layout B (flat bin records, set by seed):
//    bins/BIN-001 → { name, fill, lat, lng, zone, type, ... }
//
//  This function reads BOTH paths, merges them, so the UI
//  always shows real AI data when available and falls back
//  to seed data otherwise.
// ══════════════════════════════════════════════════════════════

/**
 * Parse one detection push-key entry from the AI model.
 * Your AI model may write different field names — we handle
 * all common variants here.
 */
function parse_detection(array $entry): array {
    // Fill level — accept fill_level, fill, fillLevel, distance_cm (inverted)
    $fill = null;
    if (isset($entry['fill_level']))   $fill = (float)$entry['fill_level'];
    elseif (isset($entry['fill']))     $fill = (float)$entry['fill'];
    elseif (isset($entry['fillLevel']))$fill = (float)$entry['fillLevel'];
    elseif (isset($entry['fill_percent'])) $fill = (float)$entry['fill_percent'];
    elseif (isset($entry['distance_cm'])) {
        // Ultrasonic: assume 0 cm = full (100%), 40 cm = empty (0%)
        $max_cm = (float)($entry['max_distance_cm'] ?? 40);
        $d = min((float)$entry['distance_cm'], $max_cm);
        $fill = round((1 - $d / $max_cm) * 100, 1);
    }
    $fill = $fill !== null ? max(0, min(100, (float)$fill)) : null;

    // Waste type from AI classifier
    $waste_type = $entry['waste_type']
        ?? $entry['wasteType']
        ?? $entry['category']
        ?? $entry['label']
        ?? $entry['class']
        ?? null;

    // AI confidence score
    $confidence = $entry['confidence']
        ?? $entry['confidence_score']
        ?? $entry['score']
        ?? null;
    if ($confidence !== null) $confidence = round((float)$confidence * (($confidence <= 1) ? 100 : 1), 1);

    // Timestamp
    $ts = $entry['timestamp']
        ?? $entry['time']
        ?? $entry['created_at']
        ?? $entry['datetime']
        ?? null;

    // AI prediction text
    $ai_prediction = $entry['ai_prediction']
        ?? $entry['prediction']
        ?? $entry['status_message']
        ?? null;

    return array_filter([
        'fill'        => $fill,
        'waste_type'  => $waste_type,
        'confidence'  => $confidence,
        'timestamp'   => $ts,
        'ai_prediction'=> $ai_prediction,
        'raw'         => $entry,
    ], fn($v) => $v !== null);
}

/**
 * Load all bins merging detections (AI data) + bins (seed/meta data).
 * Returns array of bin records ready for the UI.
 */
/**
 * load_bins()
 *
 * SINGLE SOURCE OF TRUTH: detections/ in Firebase Realtime Database.
 *
 * Every field — fill level, waste type, confidence, AND coordinates
 * (lat/lng) — must come from the detection records your AI model/IoT
 * device writes.  No hardcoded coordinates, no bins/ fallback.
 *
 * Expected detection record written by your device:
 * {
 *   "fill_level"  : 72,          // or fill / fillLevel / distance_cm
 *   "waste_type"  : "Plastic",   // or category / label / class
 *   "confidence"  : 0.91,        // 0-1 or 0-100
 *   "lat"         : -1.9441,     // GPS latitude  ← REQUIRED for map
 *   "lng"         : 30.0619,     // GPS longitude ← REQUIRED for map
 *   "location"    : "Kigali City Tower",  // optional display name
 *   "zone"        : "Central",            // optional
 *   "timestamp"   : 1718000000           // Unix ms or ISO string
 * }
 *
 * A bin WITHOUT lat/lng in its detections will appear in the stats
 * and AI panel but will be SKIPPED on the map (marker can't be placed
 * without real coordinates).
 */
function load_bins(): array {
    $raw_detections = rtdb_request('GET', 'detections');
    if (!is_array($raw_detections)) return [];

    $bins = [];

    foreach ($raw_detections as $bin_id => $entries) {
        if (!is_array($entries)) continue;

        // Firebase push keys are lexicographically chronological — sort
        // so $latest ends up being the most recent entry.
        ksort($entries);

        $all_detections = [];
        $latest_raw     = [];

        // Per-bin aggregation counters
        $waste_counts   = [];
        $fill_sum       = 0;  $fill_count = 0;
        $conf_sum       = 0;  $conf_count = 0;

        // Location: we read lat/lng from each entry and keep the most
        // recently seen non-zero coordinate pair.
        $lat = null;
        $lng = null;
        $location_name = null;
        $zone          = null;

        foreach ($entries as $push_key => $entry) {
            if (!is_array($entry)) continue;

            $parsed         = parse_detection($entry);
            $all_detections[] = $parsed;
            $latest_raw     = $entry; // updated each iteration → last = most recent

            // ── Fill aggregation ──
            if (isset($parsed['fill'])) {
                $fill_sum += $parsed['fill'];
                $fill_count++;
            }

            // ── Waste type tally ──
            if (isset($parsed['waste_type'])) {
                $wt = strtolower(trim($parsed['waste_type']));
                $waste_counts[$wt] = ($waste_counts[$wt] ?? 0) + 1;
            }

            // ── Confidence aggregation ──
            if (isset($parsed['confidence'])) {
                $conf_sum += $parsed['confidence'];
                $conf_count++;
            }

            // ── GPS coordinates — accept common field name variants ──
            // We overwrite on every entry so the LATEST GPS reading wins.
            $entry_lat = $entry['lat']       ?? $entry['latitude']
                      ?? $entry['gps_lat']   ?? $entry['gps_latitude']
                      ?? $entry['location_lat'] ?? null;
            $entry_lng = $entry['lng']       ?? $entry['lon']
                      ?? $entry['longitude'] ?? $entry['gps_lng']
                      ?? $entry['gps_lon']   ?? $entry['gps_longitude']
                      ?? $entry['location_lng'] ?? null;

            if ($entry_lat !== null && $entry_lng !== null
                && (float)$entry_lat !== 0.0 && (float)$entry_lng !== 0.0) {
                $lat = (float)$entry_lat;
                $lng = (float)$entry_lng;
            }

            // ── Location name / zone (keep latest non-empty value) ──
            $entry_name = $entry['location'] ?? $entry['name']
                       ?? $entry['bin_name'] ?? $entry['place'] ?? null;
            $entry_zone = $entry['zone']     ?? $entry['sector']
                       ?? $entry['area']     ?? null;

            if (!empty($entry_name)) $location_name = trim($entry_name);
            if (!empty($entry_zone)) $zone           = trim($entry_zone);
        }

        // ── Derive summary values from aggregated data ──
        $latest_parsed  = end($all_detections) ?: [];
        $current_fill   = isset($latest_parsed['fill'])
                          ? (int)round($latest_parsed['fill']) : null;

        arsort($waste_counts);
        $dominant_waste = ucfirst(array_key_first($waste_counts) ?? 'Mixed');
        $avg_confidence = $conf_count > 0 ? round($conf_sum / $conf_count, 1) : null;

        $status = 'ok';
        if ($current_fill !== null) {
            $status = $current_fill >= 80 ? 'critical'
                    : ($current_fill >= 60 ? 'warning' : 'ok');
        }

        $ai_pred = $latest_parsed['ai_prediction'] ?? null;
        if (!$ai_pred && $current_fill !== null) {
            if      ($current_fill >= 95) $ai_pred = 'IMMEDIATE';
            elseif  ($current_fill >= 80) $ai_pred = 'FILL NOW';
            elseif  ($current_fill >= 60) $ai_pred = 'Fill soon';
            else                          $ai_pred = 'Normal';
        }

        // ── Human-readable "last seen" from latest timestamp ──
        $ts        = $latest_parsed['timestamp'] ?? null;
        $last_seen = 'Just now';
        if ($ts) {
            $t    = is_numeric($ts) ? (int)$ts : strtotime($ts);
            // Handle millisecond timestamps (13-digit Unix)
            if ($t > 9999999999) $t = (int)($t / 1000);
            if ($t) {
                $diff = time() - $t;
                if      ($diff < 60)    $last_seen = 'Just now';
                elseif  ($diff < 3600)  $last_seen = round($diff / 60)   . 'm ago';
                elseif  ($diff < 86400) $last_seen = round($diff / 3600) . 'h ago';
                else                    $last_seen = round($diff / 86400) . 'd ago';
            }
        }

        $bins[$bin_id] = [
            // Identity
            'id'              => $bin_id,
            'name'            => $location_name ?? $bin_id,
            'zone'            => $zone ?? 'Unknown',

            // ── Coordinates sourced ONLY from detections/ ──
            // null means this bin's device has not yet sent GPS data.
            // The map JS will skip bins where lat/lng are null/zero.
            'lat'             => $lat,
            'lng'             => $lng,
            'has_gps'         => ($lat !== null && $lng !== null),

            // AI / sensor data
            'fill'            => $current_fill ?? 0,
            'type'            => $dominant_waste,
            'status'          => $status,
            'ai_prediction'   => $ai_pred ?? 'Unknown',
            'last'            => $last_seen,
            'avg_confidence'  => $avg_confidence,
            'detection_count' => count($all_detections),
            'waste_counts'    => $waste_counts,
            'all_detections'  => $all_detections,
            'latest_raw'      => $latest_raw,
            'maintenance_score'=> $latest_raw['maintenance_score'] ?? null,
        ];
    }

    return array_values($bins);
}

/** GET a single record */
function rtdb_get(string $path, string $id): array {
    $data = rtdb_request('GET', $path . '/' . $id);
    if (!is_array($data)) return [];
    $data['id'] = $id;
    return $data;
}

/** SET (PUT) a record with known ID */
function rtdb_set(string $path, string $id, array $data): bool {
    $res = rtdb_request('PUT', $path . '/' . $id, $data);
    return $res !== null;
}

/** ADD (POST) — RTDB auto-generates push ID */
function rtdb_add(string $path, array $data): string {
    $res = rtdb_request('POST', $path, $data);
    if (is_array($res) && !empty($res['name'])) return $res['name'];
    return '';
}

/** UPDATE (PATCH) specific fields */
function rtdb_update(string $path, string $id, array $fields): bool {
    $res = rtdb_request('PATCH', $path . '/' . $id, $fields);
    return $res !== null;
}

/** DELETE a record */
function rtdb_delete(string $path, string $id): bool {
    rtdb_request('DELETE', $path . '/' . $id);
    return true;
}

// ══════════════════════════════════════════════════════════════
//  SEED FUNCTION — Run once to push all initial data to RTDB
//  Visit: ?action=seed  (while logged in as admin)
// ══════════════════════════════════════════════════════════════
function seed_rtdb(): array {
    $log = [];

    // Admin password: ecosense (as requested)
    $admin_hash = password_hash('ecosense', PASSWORD_BCRYPT);
    $seed_hash  = password_hash('ecosense2024', PASSWORD_BCRYPT);

    // ── Users ──
    $users = [
        'admin001' => ['name'=>'admin',                      'firstname'=>'Anne Line',  'lastname'=>'Mizero',   'role'=>'admin',            'avatar'=>'AD','email'=>'admin@ecosense.rw',    'password'=>$admin_hash, 'ai_permissions'=>'analytics,predictions,reports,alerts,admin'],
        'admin002' => ['name'=>'HITIMANA TETA Divine',        'firstname'=>'TETA',       'lastname'=>'HITIMANA', 'role'=>'Hardware Engineer', 'avatar'=>'HD','email'=>'hardware@ecosense.rw', 'password'=>$seed_hash,  'ai_permissions'=>'sensor_analysis,maintenance'],
        'admin003' => ['name'=>'NIBEZA MUGISHA Bruce',        'firstname'=>'Bruce',      'lastname'=>'NIBEZA',   'role'=>'Software/AI Dev',  'avatar'=>'NB','email'=>'bruce@ecosense.rw',    'password'=>$seed_hash,  'ai_permissions'=>'model_training,api_access,debug'],
        'admin004' => ['name'=>'IGIRANEZA JOSEPH',            'firstname'=>'JOSEPH',     'lastname'=>'IGIRANEZA','role'=>'IoT & Network',    'avatar'=>'IJ','email'=>'joseph@ecosense.rw',   'password'=>$seed_hash,  'ai_permissions'=>'sensor_data,network_monitor'],
        'admin005' => ['name'=>'MBARUSHIMANA SIMBI Belise',   'firstname'=>'Belise',     'lastname'=>'SIMBI',    'role'=>'Data Analyst & UI','avatar'=>'MS','email'=>'belise@ecosense.rw',   'password'=>$seed_hash,  'ai_permissions'=>'visualization,trends'],
    ];
    foreach ($users as $id => $data) rtdb_set('users', $id, $data);
    $log[] = ['✅', 'users', count($users) . ' records written (admin password: ecosense)'];

    // ── Bins ──
    $bins = [
        'BIN-001' => ['name'=>'Kigali City Tower',  'lat'=>-1.9441,'lng'=>30.0619,'fill'=>82,'zone'=>'Central','type'=>'Mixed',  'status'=>'critical','last'=>'2h ago', 'ai_prediction'=>'Fill by 18:00','maintenance_score'=>78],
        'BIN-002' => ['name'=>'Kimironko Market',   'lat'=>-1.9355,'lng'=>30.0878,'fill'=>45,'zone'=>'East',   'type'=>'Organic','status'=>'ok',      'last'=>'5h ago', 'ai_prediction'=>'Fill by 22:00','maintenance_score'=>92],
        'BIN-003' => ['name'=>'Remera Bus Stop',    'lat'=>-1.9489,'lng'=>30.1079,'fill'=>91,'zone'=>'East',   'type'=>'Plastic','status'=>'critical','last'=>'8h ago', 'ai_prediction'=>'IMMEDIATE',   'maintenance_score'=>45],
        'BIN-004' => ['name'=>'Nyamirambo Center',  'lat'=>-1.9831,'lng'=>30.0385,'fill'=>30,'zone'=>'South',  'type'=>'Paper',  'status'=>'ok',      'last'=>'1h ago', 'ai_prediction'=>'Fill by 03:00','maintenance_score'=>88],
        'BIN-005' => ['name'=>'Kacyiru Ministry',   'lat'=>-1.9267,'lng'=>30.0653,'fill'=>67,'zone'=>'North',  'type'=>'Mixed',  'status'=>'warning', 'last'=>'3h ago', 'ai_prediction'=>'Fill by 14:00','maintenance_score'=>71],
        'BIN-006' => ['name'=>'Gisozi Memorial',    'lat'=>-1.9196,'lng'=>30.0524,'fill'=>20,'zone'=>'North',  'type'=>'Organic','status'=>'ok',      'last'=>'30m ago','ai_prediction'=>'Fill by 06:00','maintenance_score'=>95],
        'BIN-007' => ['name'=>'UTC Shopping Mall',  'lat'=>-1.9500,'lng'=>30.0588,'fill'=>75,'zone'=>'Central','type'=>'Plastic','status'=>'warning', 'last'=>'4h ago', 'ai_prediction'=>'Fill by 19:00','maintenance_score'=>63],
        'BIN-008' => ['name'=>'Sonatubes Junction', 'lat'=>-1.9622,'lng'=>30.0731,'fill'=>55,'zone'=>'Central','type'=>'Metal',  'status'=>'ok',      'last'=>'2h ago', 'ai_prediction'=>'Fill by 23:00','maintenance_score'=>81],
        'BIN-009' => ['name'=>'Gikondo Industry',   'lat'=>-1.9750,'lng'=>30.0820,'fill'=>88,'zone'=>'South',  'type'=>'Mixed',  'status'=>'critical','last'=>'6h ago', 'ai_prediction'=>'FILL NOW',    'maintenance_score'=>52],
        'BIN-010' => ['name'=>'Kinyinya Sector',    'lat'=>-1.9100,'lng'=>30.0950,'fill'=>38,'zone'=>'North',  'type'=>'Organic','status'=>'ok',      'last'=>'1h ago', 'ai_prediction'=>'Fill by 02:00','maintenance_score'=>89],
    ];
    foreach ($bins as $id => $data) rtdb_set('bins', $id, $data);
    $log[] = ['✅', 'bins', count($bins) . ' records written'];

    // ── Reports ──
    $reports = [
        'RPT-001' => ['location'=>'Remera Market',     'type'=>'Overflow',       'severity'=>'High',  'reporter'=>'Jean Baptiste','time'=>'10 min ago','status'=>'Pending',    'ai_priority'=>95],
        'RPT-002' => ['location'=>'Kicukiro Center',   'type'=>'Illegal Dumping','severity'=>'Medium','reporter'=>'Marie Claire', 'time'=>'25 min ago','status'=>'In Progress','ai_priority'=>72],
        'RPT-003' => ['location'=>'Nyabugogo Terminal','type'=>'Bin Damage',     'severity'=>'Low',   'reporter'=>'System AI',    'time'=>'1h ago',    'status'=>'Resolved',   'ai_priority'=>34],
        'RPT-004' => ['location'=>'Gikondo Industry',  'type'=>'Overflow',       'severity'=>'High',  'reporter'=>'Patrick K.',   'time'=>'2h ago',    'status'=>'Resolved',   'ai_priority'=>88],
        'RPT-005' => ['location'=>'Kimironko Market',  'type'=>'Bad Odor',       'severity'=>'Medium','reporter'=>'Alice N.',     'time'=>'3h ago',    'status'=>'Pending',    'ai_priority'=>67],
    ];
    foreach ($reports as $id => $data) rtdb_set('reports', $id, $data);
    $log[] = ['✅', 'reports', count($reports) . ' records written'];

    // ── Alerts ──
    $alerts = [
        'ALT-001' => ['bin'=>'BIN-003','location'=>'Remera Bus Stop',  'fill'=>91,'msg'=>'Bin critically full — AI suggests immediate dispatch','time'=>'5 min ago', 'level'=>'critical','ai_action'=>'Dispatch NOW'],
        'ALT-002' => ['bin'=>'BIN-009','location'=>'Gikondo Industry', 'fill'=>88,'msg'=>'AI predicts overflow within 45 minutes',            'time'=>'12 min ago','level'=>'critical','ai_action'=>'Schedule collection'],
        'ALT-003' => ['bin'=>'BIN-001','location'=>'Kigali City Tower','fill'=>82,'msg'=>'Fill level accelerating faster than normal',        'time'=>'25 min ago','level'=>'warning', 'ai_action'=>'Monitor rate'],
        'ALT-004' => ['bin'=>'BIN-007','location'=>'UTC Shopping Mall','fill'=>75,'msg'=>'AI detected abnormal filling pattern',              'time'=>'40 min ago','level'=>'warning', 'ai_action'=>'Investigate'],
        'ALT-005' => ['bin'=>'BIN-005','location'=>'Kacyiru Ministry', 'fill'=>67,'msg'=>'Seasonal adjustment: higher traffic detected',      'time'=>'1h ago',    'level'=>'info',    'ai_action'=>'Update route'],
    ];
    foreach ($alerts as $id => $data) rtdb_set('alerts', $id, $data);
    $log[] = ['✅', 'alerts', count($alerts) . ' records written'];

    // ── Tasks ──
    $tasks = [
        'TSK-001' => ['text'=>'Empty full bin at Kigali Central Market',     'time'=>'25 minutes ago','done'=>true, 'ai_priority'=>'High'],
        'TSK-002' => ['text'=>'Optimize collection route for Gikondo Sector','time'=>'50 minutes ago','done'=>true, 'ai_priority'=>'Medium'],
        'TSK-003' => ['text'=>'Send report to Supervising Instructor',        'time'=>'1 hour ago',    'done'=>true, 'ai_priority'=>'Low'],
        'TSK-004' => ['text'=>'Deploy new sensor at Nyamirambo',             'time'=>'Pending',        'done'=>false,'ai_priority'=>'High'],
        'TSK-005' => ['text'=>'Review AI model accuracy report',             'time'=>'Pending',        'done'=>false,'ai_priority'=>'High'],
    ];
    foreach ($tasks as $id => $data) rtdb_set('tasks', $id, $data);
    $log[] = ['✅', 'tasks', count($tasks) . ' records written'];

    return $log;
}

// ══════════════════════════════════════════════════════════════
//  FIRST-RUN BOOTSTRAP
//  If no admin user exists in RTDB at all, auto-seed admin001.
//  This means the very first time index.php runs against a fresh
//  RTDB, the admin account is created silently — no seed step needed.
// ══════════════════════════════════════════════════════════════
function bootstrap_admin(): void {
    $existing = rtdb_request('GET', 'users/admin001');
    if (!empty($existing['email'])) return; // already exists

    $admin_hash = password_hash('ecosense', PASSWORD_BCRYPT);
    rtdb_set('users', 'admin001', [
        'name'           => 'Admin',
        'firstname'      => 'Anne Line',
        'lastname'       => 'Mizero',
        'role'           => 'admin',
        'avatar'         => 'AD',
        'email'          => 'admin@ecosense.rw',
        'password'       => $admin_hash,
        'ai_permissions' => 'analytics,predictions,reports,alerts,admin',
        'created_at'     => date('Y-m-d H:i:s'),
    ]);
}
bootstrap_admin();
if (isset($_POST['update_bin'])) {
    $bin_id = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST['bin_id'] ?? ''));
    $fill   = max(0, min(100, (int)($_POST['fill'] ?? 0)));
    if ($bin_id) {
        $status = $fill >= 80 ? 'critical' : ($fill >= 60 ? 'warning' : 'ok');
        $ok = rtdb_update('bins', $bin_id, [
            'fill'       => $fill,
            'status'     => $status,
            'last'       => 'Just now',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        header('Content-Type: application/json');
        echo json_encode(['success' => $ok, 'bin' => $bin_id, 'fill' => $fill, 'status' => $status]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
//  AUTH — RTDB-backed login & register
// ══════════════════════════════════════════════════════════════
$auth_error   = '';
$auth_success = '';

// ── REGISTER ──────────────────────────────────────────────────
if (isset($_POST['register'])) {
    $reg_firstname = trim($_POST['firstname'] ?? '');
    $reg_lastname  = trim($_POST['lastname']  ?? '');
    $reg_email     = strtolower(trim($_POST['email']    ?? ''));
    $reg_password  = $_POST['password']  ?? '';
    $reg_confirm   = $_POST['confirm']   ?? '';
    $reg_role      = $_POST['role']      ?? 'Citizen';

    if (!$reg_firstname || !$reg_lastname || !$reg_email || !$reg_password) {
        $auth_error = 'Please fill in all fields.';
    } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
        $auth_error = 'Please enter a valid email address.';
    } elseif (strlen($reg_password) < 6) {
        $auth_error = 'Password must be at least 6 characters.';
    } elseif ($reg_password !== $reg_confirm) {
        $auth_error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $all_users = rtdb_get_all('users');
        $exists = false;
        foreach ($all_users as $u) {
            if (strtolower($u['email'] ?? '') === $reg_email) { $exists = true; break; }
        }
        if ($exists) {
            $auth_error = 'An account with this email already exists.';
        } else {
            $full_name = $reg_firstname . ' ' . $reg_lastname;
            $initials  = strtoupper(substr($reg_firstname, 0, 1) . substr($reg_lastname, 0, 1));
            $new_uid   = 'usr_' . bin2hex(random_bytes(8));
            $hashed    = password_hash($reg_password, PASSWORD_BCRYPT);

            $saved = rtdb_set('users', $new_uid, [
                'name'       => $full_name,
                'firstname'  => $reg_firstname,
                'lastname'   => $reg_lastname,
                'email'      => $reg_email,
                'role'       => $reg_role,
                'avatar'     => $initials,
                'password'   => $hashed,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($saved) {
                $auth_success = 'Account created! You can now sign in.';
            } else {
                $auth_error = 'Could not save account. Check your Firebase Realtime Database URL and rules (set read/write to true in Rules tab).';
            }
        }
    }
}

// ── LOGIN ──────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $login_email    = strtolower(trim($_POST['email']    ?? ''));
    $login_password = $_POST['password'] ?? '';

    if (!$login_email || !$login_password) {
        $auth_error = 'Please enter your email and password.';
    } else {
        $all_users  = rtdb_get_all('users');
        $found_user = null;
        foreach ($all_users as $u) {
            if (strtolower($u['email'] ?? '') === $login_email) {
                $found_user = $u; break;
            }
        }

        if (!$found_user) {
            $auth_error = 'No account found with that email.';
        } elseif (!password_verify($login_password, $found_user['password'] ?? '')) {
            $auth_error = 'Incorrect password.';
        } else {
            $_SESSION['user'] = [
                'uid'    => $found_user['id']     ?? '',
                'name'   => $found_user['name']   ?? '',
                'email'  => $found_user['email']  ?? '',
                'role'   => $found_user['role']   ?? 'Citizen',
                'avatar' => $found_user['avatar'] ?? strtoupper(substr($found_user['name'] ?? 'U', 0, 2)),
            ];
            header('Location: ?page=dashboard'); exit;
        }
    }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?page=login'); exit; }

// ── ADMIN PANEL PASSWORD VERIFY (AJAX) ────────────────────────
if (isset($_POST['verify_admin_password'])) {
    header('Content-Type: application/json');
    $entered  = $_POST['admin_password'] ?? '';
    $user_now = $_SESSION['user'] ?? null;

    if (!$user_now) { echo json_encode(['ok'=>false,'msg'=>'Not logged in.']); exit; }
    if (strtolower($user_now['role'] ?? '') !== 'admin') {
        echo json_encode(['ok'=>false,'msg'=>'You do not have admin privileges.']); exit;
    }

    $all_users   = rtdb_get_all('users');
    $stored_user = null;
    foreach ($all_users as $u) {
        if (strtolower($u['email'] ?? '') === strtolower($user_now['email'] ?? '')) {
            $stored_user = $u; break;
        }
    }

    if (!$stored_user || !password_verify($entered, $stored_user['password'] ?? '')) {
        echo json_encode(['ok'=>false,'msg'=>'Incorrect password.']);
    } else {
        $_SESSION['admin_verified'] = true;
        echo json_encode(['ok'=>true]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
//  ROLE HELPERS
// ══════════════════════════════════════════════════════════════
function is_admin(): bool {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    return strtolower($user['role'] ?? '') === 'admin';
}
function admin_verified(): bool {
    return !empty($_SESSION['admin_verified']);
}

// ══════════════════════════════════════════════════════════════
//  LOAD PAGE & LIVE DATA FROM RTDB
// ══════════════════════════════════════════════════════════════
$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
$user = $_SESSION['user'] ?? null;

if (!$user && !in_array($page, ['login', 'register'])) {
    header('Location: ?page=login'); exit;
}
if ($page === 'admin_verify' && !is_admin()) {
    header('Location: ?page=dashboard&access=denied'); exit;
}
if ($page === 'admin' && !is_admin()) {
    header('Location: ?page=dashboard&access=denied'); exit;
}
if ($page === 'admin' && is_admin() && !admin_verified()) {
    header('Location: ?page=admin_verify'); exit;
}

$seed_log = [];
if (isset($_GET['action']) && $_GET['action'] === 'seed' && $user && is_admin() && admin_verified()) {
    $seed_log = seed_rtdb();
}

$bins    = [];
$reports = [];
$alerts  = [];
$tasks   = [];

if ($user) {
    // load_bins() reads BOTH detections/ (AI model output) AND bins/ (seed/meta)
    // and merges them so the UI always shows real AI data
    $bins = load_bins();
    usort($bins, fn($a, $b) => ($b['fill'] ?? 0) <=> ($a['fill'] ?? 0));

    if ($page === 'reports') {
        $reports = rtdb_get_all('reports');
        usort($reports, fn($a, $b) => ($b['ai_priority'] ?? 0) <=> ($a['ai_priority'] ?? 0));
    }
    if (in_array($page, ['alerts', 'dashboard'])) {
        $alerts = rtdb_get_all('alerts');
    }
    if ($page === 'dashboard') {
        $tasks = rtdb_get_all('tasks');
    }
}

$report_success = false;
if (isset($_POST['submit_report'])) {
    $new_id = rtdb_add('reports', [
        'location'    => trim($_POST['location']    ?? ''),
        'reporter'    => trim($_POST['reporter']    ?? ''),
        'type'        => $_POST['type']        ?? 'Other',
        'severity'    => $_POST['severity']    ?? 'Medium',
        'description' => trim($_POST['description'] ?? ''),
        'status'      => 'Pending',
        'time'        => 'Just now',
        'ai_priority' => 50,
        'submitted_at'=> date('Y-m-d H:i:s'),
    ]);
    $report_success = !empty($new_id);
    $reports = rtdb_get_all('reports');
    usort($reports, fn($a, $b) => ($b['ai_priority'] ?? 0) <=> ($a['ai_priority'] ?? 0));
}

// ── AI Insights (role-based) ──
$ai_insights = [
    'admin'            => [['System Overview','All bins monitored · Live from Realtime DB','🔒'],['User Management','Registered accounts in RTDB','👥'],['AI Prediction','Critical bins detected by AI','🤖'],['Cost Analysis','Projected savings: 245,000 RWF this month','💰']],
    'Project Manager'  => [['KPI Alert','Collection efficiency improved 15% this week','📈'],['AI Prediction','Critical bins detected by AI','🤖'],['Resource Optimization','Route optimization can save 23km daily','🛣️'],['Cost Analysis','Projected savings: 245,000 RWF this month','💰']],
    'Hardware Engineer'=> [['Sensor Health','BIN-003 ultrasonic sensor degrading','🔧'],['Battery Status','5 bins need battery replacement within 48h','🔋'],['Firmware Update','3 devices pending OTA update','📡'],['Diagnostic','Network latency: 23ms average','📊']],
    'Software/AI Dev'  => [['Model Accuracy','92.3% prediction accuracy this week','🤖'],['API Status','2,847 requests today, 0 errors','🌐'],['Training Progress','New model: 85% trained','⚙️'],['Data Pipeline','1.2GB processed, 0 anomalies','📊']],
    'IoT & Network'    => [['Network Status','All gateways online','📶'],['Data Flow','124kb/s average throughput','📡'],['Device Health','98% uptime across all sensors','✅'],['Security','No intrusion attempts detected','🔒']],
    'Data Analyst & UI'=> [['Visualization','Dashboard views up 34% this week','📊'],['User Engagement','8 new reports from citizens','👥'],['Trend Analysis','Plastic waste up 8% week-over-week','📈'],['Export Ready','Monthly report ready for download','📄']],
];
$user_insights = $ai_insights[$user['role'] ?? ''] ?? $ai_insights['Project Manager'];

function fillColor($f) { return $f>=80?'#e53e3e':($f>=60?'#ff8c00':'#4caf50'); }
function sevColor($s)  { return $s==='High'?'#e53e3e':($s==='Medium'?'#ff8c00':'#4caf50'); }
function statColor($s) { return $s==='Pending'?'#ff8c00':($s==='In Progress'?'#2b6cb0':'#4caf50'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EcoSense Rwanda — <?=htmlspecialchars($user['name'] ?? 'Login')?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Firebase JS SDK — Real-time listener on Realtime Database -->
<script type="module">
import { initializeApp }                          from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
import { getDatabase, ref, onValue, update, push } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js';

// ── YOUR FIREBASE CONFIG ─────────────────────────────────────
// Firebase Console → Project Settings → General → Your apps → Web
const firebaseConfig = {
    apiKey:            "PASTE_YOUR_API_KEY_HERE",
    authDomain:        "ecosense-rwanda.firebaseapp.com",
    databaseURL:       "https://ecosense-rwanda-default-rtdb.firebaseio.com",
    projectId:         "ecosense-rwanda",
    storageBucket:     "ecosense-rwanda.appspot.com",
    messagingSenderId: "412012569340",
    appId:             "PASTE_YOUR_APP_ID_HERE"
};
// ────────────────────────────────────────────────────────────

const app = initializeApp(firebaseConfig);
const db  = getDatabase(app);

// ── Helper: derive fill from a detection entry (mirrors PHP parse_detection) ──
function parseFill(entry) {
    if (entry.fill_level   !== undefined) return Math.min(100, Math.max(0, parseFloat(entry.fill_level)));
    if (entry.fill         !== undefined) return Math.min(100, Math.max(0, parseFloat(entry.fill)));
    if (entry.fillLevel    !== undefined) return Math.min(100, Math.max(0, parseFloat(entry.fillLevel)));
    if (entry.fill_percent !== undefined) return Math.min(100, Math.max(0, parseFloat(entry.fill_percent)));
    if (entry.distance_cm  !== undefined) {
        const max = parseFloat(entry.max_distance_cm || 40);
        return Math.min(100, Math.max(0, (1 - parseFloat(entry.distance_cm) / max) * 100));
    }
    return null;
}

function parseWasteType(entry) {
    return entry.waste_type || entry.wasteType || entry.category || entry.label || entry.class || null;
}

function fillColor(fill) {
    return fill >= 80 ? '#e53e3e' : fill >= 60 ? '#ff8c00' : '#4caf50';
}

// ── Real-time listener on detections/ ──────────────────────
// Fires whenever the AI model writes a new detection for any bin
onValue(ref(db, 'detections'), (snapshot) => {
    const all = snapshot.val();
    if (!all) return;

    let critCount = 0;
    let totalDetections = 0;
    let topFill = 0;
    let topBinName = '';

    Object.entries(all).forEach(([binId, entries]) => {
        if (!entries || typeof entries !== 'object') return;

        // Get the latest entry (last push key alphabetically = most recent)
        const keys  = Object.keys(entries).sort();
        const entry = entries[keys[keys.length - 1]];
        if (!entry) return;

        const fill      = parseFill(entry);
        const wasteType = parseWasteType(entry);
        const confidence= entry.confidence ?? entry.confidence_score ?? entry.score ?? null;
        const confPct   = confidence !== null
            ? Math.round(parseFloat(confidence) * (parseFloat(confidence) <= 1 ? 100 : 1) * 10) / 10
            : null;
        const detCount  = keys.length;
        totalDetections += detCount;

        if (fill !== null) {
            if (fill >= 80) critCount++;
            if (fill > topFill) { topFill = fill; topBinName = binId; }
        }

        // ── Update bin card on bins page ──
        const card = document.querySelector(`[data-bin-id="${binId}"]`);
        if (card && fill !== null) {
            const color = fillColor(fill);
            const bar   = card.querySelector('.fill-bar-inner');
            const label = card.querySelector('.bin-fill-val');
            if (bar)   { bar.style.width = Math.round(fill) + '%'; bar.style.background = color; }
            if (label) { label.textContent = Math.round(fill) + '%'; label.style.color = color; }
            card.dataset.fill = Math.round(fill);
            // Flash ring
            card.style.boxShadow = `0 0 0 3px ${color}55`;
            setTimeout(() => card.style.boxShadow = '', 2000);
        }

        // ── Update AI detection card on dashboard ──
        const detCard = document.querySelector(`[data-det-bin="${binId}"]`);
        if (detCard) {
            const color = fill !== null ? fillColor(fill) : '#4caf50';
            const fillBar = detCard.querySelector('.det-fill-bar');
            const fillLabel = detCard.querySelector('.det-fill-label');
            const confEl    = detCard.querySelector('.det-confidence');
            const typeEl    = detCard.querySelector('.det-waste-type');
            const countEl   = detCard.querySelector('.det-count');

            if (fillBar)   { fillBar.style.width = (fill !== null ? Math.round(fill) : 0) + '%'; fillBar.style.background = color; }
            if (fillLabel) { fillLabel.textContent = (fill !== null ? Math.round(fill) : '—') + '%'; fillLabel.style.color = color; }
            if (confEl && confPct !== null)  confEl.textContent = confPct + '%';
            if (typeEl && wasteType)         typeEl.textContent = wasteType;
            if (countEl)                     countEl.textContent = detCount + ' records';
        }

        // ── Update map marker fill if map is open ──
        if (window._mapMarkers && window._mapMarkers[binId]) {
            const color = fill !== null ? fillColor(fill) : '#4caf50';
            const fillVal = fill !== null ? Math.round(fill) : 0;
            window._mapMarkers[binId].setIcon(L.divIcon({
                html: `<div style="background:${color};width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;"><span style="transform:rotate(45deg);font-size:10px;font-weight:800;color:#fff;">${fillVal}%</span></div>`,
                className:'', iconSize:[32,32], iconAnchor:[16,32]
            }));
        }
    });

    // Update critical badge in topbar
    const badge = document.querySelector('.notif-badge');
    if (badge) badge.textContent = critCount;

    // Update total detections counter
    const detTotal = document.getElementById('det-total-count');
    if (detTotal) detTotal.textContent = totalDetections;

    // Update top bin in chatbot context
    window._rtdbStats = window._rtdbStats || {};
    window._rtdbStats.critical = critCount;
    window._rtdbStats.topBin   = topBinName;
    window._rtdbStats.topFill  = Math.round(topFill);
});

// ── updateBinFill() — for IoT dispatch / testing ──
window.updateBinFill = async (binId, fill) => {
    // Write a new detection entry so it goes through the AI pipeline
    await push(ref(db, 'detections/' + binId), {
        fill_level:  fill,
        fill:        fill,
        timestamp:   Date.now(),
        source:      'manual_dispatch',
    });
    // Also update the bins/ meta record
    await update(ref(db, 'bins/' + binId), {
        fill, status: fill >= 80 ? 'critical' : fill >= 60 ? 'warning' : 'ok',
        last: 'Just now', updated_at: new Date().toISOString()
    });
};
</script>

<style>
:root {
  --font:'Plus Jakarta Sans',sans-serif;
  --forest:#1a3a2a; --dark-green:#0d2818; --sidebar:#163020;
  --emerald:#1a6b4a; --lime:#4caf50; --mint:#a8e6cf;
  --gold:#f4c430; --amber:#ff8c00; --red:#e53e3e; --blue:#2b6cb0;
  --bg:#f0f4f1; --card:#ffffff; --text:#1a2e1f; --muted:#6b7280;
  --border:#d1e8d8; --header-h:64px; --sidebar-w:220px;
}
*{margin:0;padding:0;box-sizing:border-box;}
html{font-size:15px;}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;letter-spacing:-0.01em;}
a{text-decoration:none;color:inherit;}
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:#e8f0eb;}
::-webkit-scrollbar-thumb{background:var(--emerald);border-radius:3px;}
@keyframes fadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
@keyframes ai-pulse{0%,100%{opacity:1}50%{opacity:.7}}
.fade{animation:fadeIn .45s ease forwards;}

/* ── AUTH ── */
.auth-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(ellipse at 30% 50%,#1a6b4a22 0%,transparent 60%),#0d2818;}
.auth-card{width:440px;background:#163020;border:1px solid #1a6b4a55;border-radius:24px;padding:40px;box-shadow:0 40px 80px rgba(0,0,0,.5);}
.auth-logo{text-align:center;margin-bottom:28px;}
.auth-logo .logo-text{font-size:28px;font-weight:800;color:#fff;letter-spacing:-0.03em;}
.auth-logo .logo-text span{color:var(--lime);}
.auth-logo p{color:#9ca3af;font-size:13px;margin-top:4px;}
.auth-tabs{display:flex;background:#0d2818;border-radius:10px;padding:4px;margin-bottom:24px;}
.auth-tab{flex:1;padding:10px;border-radius:8px;border:none;cursor:pointer;font-family:var(--font);font-size:14px;font-weight:600;transition:all .2s;}
.auth-tab.active{background:linear-gradient(135deg,var(--emerald),var(--lime));color:#0d2818;}
.auth-tab:not(.active){background:transparent;color:#6b7280;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:11px;color:#9ca3af;margin-bottom:6px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
.form-group input,.form-group select,.form-group textarea{width:100%;background:#0d2818;border:1px solid #1a6b4a55;border-radius:10px;padding:12px 14px;color:#fff;font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s;}
.form-group input:focus,.form-group select:focus{border-color:var(--lime);}
.form-group select option{background:#163020;}
.btn-primary{width:100%;padding:14px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:10px;color:#0d2818;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;margin-top:4px;}
.btn-primary:hover{opacity:.9;}
.auth-switch{text-align:center;margin-top:16px;font-size:13px;color:#6b7280;}
.auth-switch a{color:var(--lime);font-weight:600;}

/* ── LAYOUT ── */
.layout{display:flex;min-height:100vh;}
.sidebar{width:var(--sidebar-w);background:var(--sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;border-right:1px solid #1a6b4a33;}
.sidebar-logo{padding:18px 20px 16px;border-bottom:1px solid #1a6b4a33;display:flex;align-items:center;gap:10px;}
.logo-icon{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--lime),var(--mint));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.logo-name{font-size:16px;font-weight:800;color:#fff;line-height:1.1;letter-spacing:-0.03em;}
.logo-name span{color:var(--lime);}
.logo-sub{font-size:10px;color:#4b7a5e;font-weight:500;letter-spacing:.05em;}
.nav-section{padding:12px 12px 0;flex:1;overflow-y:auto;}
.nav-label{font-size:10px;color:#4b7a5e;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:0 8px 8px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;margin-bottom:2px;cursor:pointer;transition:all .2s;border:none;background:transparent;color:#9ca3af;font-family:var(--font);font-size:13.5px;font-weight:500;width:100%;text-align:left;letter-spacing:-0.01em;}
.nav-item:hover{background:#1a6b4a22;color:#a8e6cf;}
.nav-item.active{background:linear-gradient(135deg,#1a6b4a44,#4caf5022);color:var(--lime);border-left:3px solid var(--lime);font-weight:600;}
.nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0;}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;}
.sidebar-bottom{margin-top:auto;padding:16px;border-top:1px solid #1a6b4a33;}
.user-card{display:flex;align-items:center;gap:10px;padding:10px;background:#0d2818;border-radius:12px;}
.user-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#0d2818;flex-shrink:0;}
.user-name{font-size:13px;font-weight:700;color:#fff;letter-spacing:-0.01em;}
.user-role{font-size:11px;color:var(--lime);font-weight:500;}
.sidebar-actions{display:flex;gap:8px;margin-top:10px;}
.sidebar-btn{flex:1;padding:8px;border-radius:8px;border:1px solid #1a6b4a44;background:transparent;color:#6b7280;font-size:12px;cursor:pointer;font-family:var(--font);font-weight:500;}
.sidebar-btn:hover{border-color:var(--lime);color:var(--lime);}

/* ── TOPBAR ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
.topbar{height:var(--header-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:16px;padding:0 28px;position:sticky;top:0;z-index:50;}
.search-box{flex:1;max-width:520px;display:flex;align-items:center;gap:10px;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:9px 14px;}
.search-box input{border:none;background:transparent;outline:none;font-family:var(--font);font-size:13.5px;color:var(--text);width:100%;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px;}
.rtdb-pill{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:var(--emerald);background:#e8f5e9;border:1px solid var(--lime);border-radius:20px;padding:5px 12px;}
.rtdb-dot{width:7px;height:7px;border-radius:50%;background:var(--lime);animation:pulse 2s infinite;}
.icon-btn{width:38px;height:38px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;font-size:16px;}
.notif-badge{position:absolute;top:-4px;right:-4px;width:16px;height:16px;background:var(--red);color:#fff;border-radius:50%;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;}
.topbar-user{display:flex;align-items:center;gap:10px;padding:6px 12px;background:var(--bg);border-radius:10px;border:1px solid var(--border);cursor:pointer;}
.topbar-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:12px;}
.topbar-user-info .name{font-size:13px;font-weight:700;letter-spacing:-0.01em;}
.topbar-user-info .role{font-size:11px;color:var(--muted);}

/* ── PAGE ── */
.page-content{padding:24px 28px;flex:1;}
.page-title{font-size:22px;font-weight:800;margin-bottom:4px;letter-spacing:-0.03em;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:24px;}
.ai-badge{background:var(--emerald);color:#fff;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;animation:ai-pulse 2s infinite;}

/* ── STAT CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
.stat-card{border-radius:14px;padding:20px 22px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);position:relative;overflow:hidden;}
.stat-card.green{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);}
.stat-card.amber{background:linear-gradient(135deg,#fff8e1,#ffecb3);}
.stat-card.blue {background:linear-gradient(135deg,#e3f2fd,#bbdefb);}
.stat-card.teal {background:linear-gradient(135deg,#e0f2f1,#b2dfdb);}
.stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
.stat-card.green .stat-icon{background:#4caf5022;}.stat-card.amber .stat-icon{background:#ff8c0022;}
.stat-card.blue  .stat-icon{background:#2b6cb022;}.stat-card.teal  .stat-icon{background:#00968822;}
.stat-value{font-size:28px;font-weight:800;line-height:1;letter-spacing:-0.04em;}
.stat-card.green .stat-value{color:#2e7d32;}.stat-card.amber .stat-value{color:#e65100;}
.stat-card.blue  .stat-value{color:#1565c0;}.stat-card.teal  .stat-value{color:#00695c;}
.stat-label{font-size:12px;color:#6b7280;margin-top:3px;font-weight:500;}

/* ── AI SECTION ── */
.ai-section{background:linear-gradient(135deg,#f0f9ff,#e6f7e6);border-radius:16px;padding:20px;margin-bottom:20px;border:1px solid var(--lime);position:relative;overflow:hidden;}
.ai-section::before{content:'🤖';position:absolute;right:20px;bottom:20px;font-size:48px;opacity:0.1;}
.ai-title{font-size:14px;font-weight:700;color:var(--emerald);margin-bottom:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.ai-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:12px;}
.ai-card{background:rgba(255,255,255,0.7);border-radius:12px;padding:14px;backdrop-filter:blur(5px);}

/* ── GRIDS ── */
.grid-2-1{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;}
.grid-2  {display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.grid-3  {display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;}
.grid-4  {display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}

/* ── CARD ── */
.card{background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden;}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-title{font-size:14.5px;font-weight:700;letter-spacing:-0.02em;}
.card-sub{font-size:12px;color:var(--muted);}
.card-body{padding:18px 20px;}
.card-btn{padding:5px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;font-size:12px;cursor:pointer;color:var(--muted);font-family:var(--font);font-weight:500;}
.card-btn:hover{border-color:var(--lime);color:var(--emerald);}

/* ── MAP ── */
#kigali-map,#collection-map{height:360px;border-radius:0 0 14px 14px;}
.leaflet-popup-content-wrapper{border-radius:12px!important;font-family:var(--font)!important;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.12)!important;}
.leaflet-popup-content{font-size:13px;font-family:var(--font)!important;}
.popup-title{font-weight:800;font-size:14px;margin-bottom:8px;color:var(--text);}
.popup-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f0f4f1;font-size:12px;}
.popup-dispatch{width:100%;margin-top:10px;padding:8px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:7px;color:#fff;font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;}

/* ── FILL BAR ── */
.fill-bar-bg{background:#e8f0eb;border-radius:4px;height:6px;overflow:hidden;}
.fill-bar-inner{height:100%;border-radius:4px;transition:width .8s ease;}

/* ── TABLE ── */
.data-table{width:100%;border-collapse:collapse;}
.data-table th{padding:10px 14px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);text-align:left;background:var(--bg);}
.data-table td{padding:12px 14px;font-size:13px;border-bottom:1px solid #f0f4f1;vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:#f8fbf9;}

/* ── BADGES ── */
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block;}
.badge-green{background:#e8f5e9;color:#2e7d32;}.badge-amber{background:#fff3e0;color:#e65100;}
.badge-red  {background:#fce4ec;color:#c62828;}.badge-blue {background:#e3f2fd;color:#1565c0;}
.badge-gray {background:#f3f4f6;color:#4b5563;}

/* ── BINS GRID ── */
.bin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
.bin-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px;transition:all .2s;cursor:pointer;}
.bin-card:hover{border-color:var(--lime);box-shadow:0 4px 20px rgba(76,175,80,.15);transform:translateY(-2px);}
.bin-name{font-size:14px;font-weight:700;margin-bottom:3px;letter-spacing:-0.02em;}
.bin-id  {font-size:11px;color:var(--muted);font-weight:500;}
.bin-fill-label{display:flex;justify-content:space-between;margin-bottom:5px;font-size:12px;color:var(--muted);font-weight:500;}
.bin-fill-val{font-weight:700;}
.bin-tag {background:var(--bg);padding:3px 9px;border-radius:6px;font-size:11px;color:var(--muted);font-weight:600;}

/* ── ALERTS ── */
.alert-item{display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid var(--border);}
.alert-item:last-child{border-bottom:none;}
.alert-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
.alert-dot.critical{background:var(--red);animation:pulse 1.2s infinite;}
.alert-dot.warning {background:var(--amber);}
.alert-dot.info    {background:var(--blue);}
.alert-title{font-size:14px;font-weight:700;margin-bottom:3px;}
.alert-sub  {font-size:12px;color:var(--muted);}
.alert-time {font-size:12px;color:var(--muted);white-space:nowrap;}
.alert-fill {font-size:22px;font-weight:800;min-width:48px;text-align:right;letter-spacing:-0.03em;}

/* ── FORMS ── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group-light label{font-size:11px;color:var(--muted);display:block;margin-bottom:5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
.form-group-light input,.form-group-light select,.form-group-light textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:11px 13px;color:var(--text);font-family:var(--font);font-size:13.5px;outline:none;transition:border-color .2s;}
.form-group-light input:focus,.form-group-light select:focus{border-color:var(--lime);}
.btn-submit {padding:12px 28px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:9px;color:#fff;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;transition:opacity .2s;}
.btn-submit:hover{opacity:.9;}
.btn-outline{padding:11px 22px;background:transparent;border:1px solid var(--border);border-radius:9px;color:var(--muted);font-family:var(--font);font-size:13.5px;font-weight:500;cursor:pointer;}
.success-msg{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;padding:14px 18px;color:#2e7d32;font-weight:600;font-size:14px;margin-bottom:18px;display:flex;align-items:center;gap:8px;}

/* ── TASKS ── */
.task-item{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);}
.task-item:last-child{border-bottom:none;}
.task-check{width:18px;height:18px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
.task-check.done{background:var(--lime);border-color:var(--lime);color:#fff;font-size:10px;}
.task-text{font-size:13px;font-weight:500;line-height:1.4;}
.task-time{font-size:11px;color:var(--muted);margin-top:2px;}

/* ── STATUS ── */
.status-item{display:flex;align-items:center;gap:8px;padding:8px 0;font-size:13px;font-weight:500;border-bottom:1px solid var(--border);}
.status-item:last-child{border-bottom:none;}
.status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.status-dot.green{background:var(--lime);}.status-dot.amber{background:var(--amber);}
.status-dot.red  {background:var(--red);animation:pulse 1.5s infinite;}

/* ── TOGGLE ── */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);}
.toggle-row:last-child{border-bottom:none;}
.toggle-label{font-size:13px;font-weight:600;}.toggle-sub{font-size:12px;color:var(--muted);}
.toggle{width:44px;height:24px;background:#d1fae5;border-radius:12px;position:relative;cursor:pointer;transition:background .2s;border:none;}
.toggle::after{content:'';position:absolute;top:2px;right:2px;width:20px;height:20px;background:var(--lime);border-radius:50%;transition:right .2s;}
.toggle.off{background:#f3f4f6;}.toggle.off::after{right:22px;background:#d1d5db;}

/* ── RECYCLE ── */
.recycle-stat{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);}
.recycle-stat:last-child{border-bottom:none;}
.recycle-pct {font-size:20px;font-weight:800;color:var(--emerald);letter-spacing:-0.03em;}
.recycle-label{font-size:12px;color:var(--muted);}

/* ── PIE LEGEND ── */
.pie-legend{display:flex;flex-direction:column;gap:8px;}
.pie-legend-item{display:flex;align-items:center;gap:8px;font-size:13px;}
.pie-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.pie-pct{margin-left:auto;font-weight:700;min-width:32px;text-align:right;}

/* ── FILTER TABS ── */
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
.filter-tab{padding:7px 16px;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--muted);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;}
.filter-tab.active,.filter-tab:hover{border-color:var(--lime);background:#e8f5e9;color:var(--emerald);font-weight:600;}

/* ── ROUTE CARD ── */
.route-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:12px;display:flex;align-items:center;gap:16px;}
.route-zone{width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.route-name{font-size:14px;font-weight:700;margin-bottom:3px;letter-spacing:-0.02em;}
.route-meta{font-size:12px;color:var(--muted);}
.progress-bar{height:4px;background:#e8f0eb;border-radius:2px;overflow:hidden;width:80px;margin-top:4px;}
.progress-inner{height:100%;background:linear-gradient(90deg,var(--emerald),var(--lime));border-radius:2px;}

/* ── ADMIN ── */
.admin-user-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.admin-user-row:last-child{border-bottom:none;}
.admin-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;flex-shrink:0;}
.admin-name{font-size:13px;font-weight:700;}.admin-role{font-size:11px;color:var(--muted);}
.admin-action{margin-left:auto;display:flex;gap:6px;}
.admin-btn{padding:4px 12px;border-radius:6px;font-size:11px;cursor:pointer;border:1px solid;font-family:var(--font);font-weight:600;}
.admin-btn.edit{border-color:var(--blue);color:var(--blue);background:transparent;}
.admin-btn.del {border-color:var(--red); color:var(--red); background:transparent;}

/* ── AI CHAT ── */
.ai-chat{position:fixed;bottom:20px;right:20px;width:320px;background:white;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.2);border:1px solid var(--border);z-index:1000;transition:all .3s;}
.ai-chat.collapsed{transform:translateY(calc(100% - 60px));}
.ai-chat-header{padding:15px 20px;background:linear-gradient(135deg,var(--emerald),var(--lime));border-radius:16px 16px 0 0;color:white;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;}
.ai-chat-body{padding:15px;max-height:300px;overflow-y:auto;background:#f9f9f9;}
.ai-message{margin-bottom:12px;padding:10px 14px;border-radius:14px;max-width:85%;font-size:13px;line-height:1.5;}
.ai-message.user{background:linear-gradient(135deg,var(--emerald),var(--lime));color:white;margin-left:auto;border-radius:14px 14px 0 14px;}
.ai-message.bot {background:white;border:1px solid var(--border);border-radius:14px 14px 14px 0;}
.ai-input{display:flex;gap:8px;padding:10px;background:white;border-top:1px solid var(--border);}
.ai-input input {flex:1;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:13px;outline:none;}
.ai-input button{padding:10px 15px;background:var(--emerald);color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;}

/* ── SEED CARD ── */
.seed-card{background:linear-gradient(135deg,#fff8e1,#fff3e0);border:1px solid var(--amber);border-radius:14px;padding:18px 22px;margin-bottom:20px;display:flex;align-items:center;gap:16px;}
.seed-card h3{font-size:14px;font-weight:700;color:#e65100;margin-bottom:4px;}
.seed-card p {font-size:12px;color:var(--muted);}
.seed-btn{padding:10px 20px;background:linear-gradient(135deg,#e65100,#ff8c00);border:none;border-radius:9px;color:#fff;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block;}
</style>
</head>
<body>

<?php if ($page === 'login' || $page === 'register'): ?>
<!-- ═══════════════════════════════════════════════════
     AUTH
═══════════════════════════════════════════════════ -->
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

    <?php if ($auth_error): ?>
    <div style="background:#fce4ec;border:1px solid #f48fb1;border-radius:10px;padding:12px 16px;color:#c62828;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;">⚠️ <?=htmlspecialchars($auth_error)?></div>
    <?php endif; ?>
    <?php if ($auth_success): ?>
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;padding:12px 16px;color:#2e7d32;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;">✅ <?=htmlspecialchars($auth_success)?></div>
    <?php endif; ?>

    <?php if ($page === 'login'): ?>
    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="your@email.com" required value="<?=htmlspecialchars($_POST['email']??'')?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative;">
          <input type="password" name="password" id="loginPwd" placeholder="••••••••" required style="padding-right:42px;">
          <button type="button" onclick="togglePwd('loginPwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;cursor:pointer;font-size:16px;">👁</button>
        </div>
      </div>
      <button type="submit" name="login" class="btn-primary">Sign In to EcoSense →</button>
    </form>

    <?php else: ?>
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="firstname" placeholder="First name" required value="<?=htmlspecialchars($_POST['firstname']??'')?>">
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="lastname" placeholder="Last name" required value="<?=htmlspecialchars($_POST['lastname']??'')?>">
        </div>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="your@email.com" required value="<?=htmlspecialchars($_POST['email']??'')?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative;">
          <input type="password" name="password" id="regPwd" placeholder="Min. 6 characters" required style="padding-right:42px;">
          <button type="button" onclick="togglePwd('regPwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;cursor:pointer;font-size:16px;">👁</button>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat your password" required>
      </div>
      <div class="form-group">
        <label>Your Role</label>
        <select name="role">
          <option value="Citizen">Citizen</option>
          <option value="Cleaning Agency">Cleaning Agency</option>
          <option value="Hardware Engineer">Hardware Engineer</option>
          <option value="Software/AI Dev">Software / AI Developer</option>
          <option value="IoT & Network">IoT &amp; Network</option>
          <option value="Data Analyst & UI">Data Analyst &amp; UI</option>
          <option value="Collection Driver">Collection Driver</option>
          <option value="admin">Administrator (Admin)</option>
        </select>
      </div>
      <button type="submit" name="register" class="btn-primary">Create My Account →</button>
    </form>
    <?php endif; ?>

    <div class="auth-switch"><?= $page==='login'?'No account? <a href="?page=register">Sign up free</a>':'Already have an account? <a href="?page=login">Sign in</a>' ?></div>
  </div>
</div>

<?php elseif ($page === 'admin_verify' && is_admin()): ?>
<!-- ═══════════════════════════════════════════════════
     ADMIN PASSWORD VERIFY PAGE
═══════════════════════════════════════════════════ -->
<div class="auth-wrap">
  <div class="auth-card fade" style="max-width:400px;">
    <div class="auth-logo">
      <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#c62828,#e53e3e);display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 12px;">🔒</div>
      <div class="logo-text" style="font-size:22px;">Admin Access</div>
      <p>Confirm your identity to enter the Admin Panel</p>
    </div>
    <div id="verify-error" style="display:none;background:#fce4ec;border:1px solid #f48fb1;border-radius:10px;padding:12px 16px;color:#c62828;font-size:13px;font-weight:600;margin-bottom:16px;">⚠️ Incorrect password. Try again.</div>
    <div class="form-group">
      <label>Signed in as</label>
      <div style="background:#0d2818;border-radius:10px;padding:12px 14px;color:#a8e6cf;font-size:14px;font-weight:600;"><?=htmlspecialchars($user['name'] ?? '')?> · <?=htmlspecialchars($user['email'] ?? '')?></div>
    </div>
    <div class="form-group">
      <label>Your Password</label>
      <div style="position:relative;">
        <input type="password" id="adminVerifyPwd" placeholder="Enter your account password" style="width:100%;background:#0d2818;border:1px solid #1a6b4a55;border-radius:10px;padding:12px 44px 12px 14px;color:#fff;font-family:var(--font);font-size:14px;outline:none;" onkeydown="if(event.key==='Enter')verifyAdminPwd()">
        <button type="button" onclick="togglePwd('adminVerifyPwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;cursor:pointer;font-size:16px;">👁</button>
      </div>
    </div>
    <button onclick="verifyAdminPwd()" class="btn-primary" id="verifyBtn">Confirm &amp; Enter Admin Panel →</button>
    <div style="text-align:center;margin-top:14px;"><a href="?page=dashboard" style="font-size:13px;color:#6b7280;">← Back to Dashboard</a></div>
  </div>
</div>
<script>
function togglePwd(id, btn) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁' : '🙈';
}
async function verifyAdminPwd() {
  const pwd = document.getElementById('adminVerifyPwd').value;
  const err = document.getElementById('verify-error');
  const btn = document.getElementById('verifyBtn');
  if (!pwd) return;
  btn.textContent = 'Verifying…'; btn.disabled = true;
  const fd = new FormData();
  fd.append('verify_admin_password','1');
  fd.append('admin_password', pwd);
  const res  = await fetch('', {method:'POST', body: fd});
  const data = await res.json();
  if (data.ok) {
    window.location = '?page=admin';
  } else {
    err.textContent = '⚠️ ' + data.msg;
    err.style.display = 'block';
    btn.textContent = 'Confirm & Enter Admin Panel →';
    btn.disabled = false;
    document.getElementById('adminVerifyPwd').value = '';
    document.getElementById('adminVerifyPwd').focus();
  }
}
</script>

<?php elseif ($page !== 'admin_verify'): ?>
<!-- ═══════════════════════════════════════════════════
     MAIN APP
═══════════════════════════════════════════════════ -->
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🌱</div>
      <div><div class="logo-name">Eco<span>Sense</span></div><div class="logo-sub">Rwanda · RTDB Live</div></div>
    </div>
    <nav class="nav-section">
      <div class="nav-label">Main Menu</div>
      <?php foreach([['dashboard','🏠','Dashboard',''],['bins','🗑️','Smart Bins',''],['collection','🚛','Waste Collection',''],['analytics','♻️','Recycling Analytics',''],['reports','📋','Reports',''],['alerts','🔔','Alerts',count(array_filter($alerts,fn($a)=>($a['level']??'')==='critical'))]] as [$p,$icon,$label,$badge]): ?>
      <a href="?page=<?=$p?>" class="nav-item <?=$page===$p?'active':''?>">
        <span class="nav-icon"><?=$icon?></span><?=$label?>
        <?php if($badge): ?><span class="nav-badge"><?=(int)$badge?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
      <div class="nav-label" style="margin-top:16px;">System</div>
      <a href="?page=settings" class="nav-item <?=$page==='settings'?'active':''?>"><span class="nav-icon">⚙️</span> Settings</a>
      <?php if(is_admin()): ?>
      <a href="?page=admin" class="nav-item <?=$page==='admin'?'active':''?>"><span class="nav-icon">👤</span> Admin Panel</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-card">
        <div class="user-avatar"><?=htmlspecialchars($user['avatar'] ?? strtoupper(substr($user['name'] ?? 'U', 0, 2)))?></div>
        <div>
          <div class="user-name"><?=htmlspecialchars($user['name'] ?? 'User')?></div>
          <div class="user-role"><?=htmlspecialchars($user['role'] ?? '')?></div>
        </div>
      </div>
      <div class="sidebar-actions">
        <button class="sidebar-btn" onclick="location='?logout'">⏻ Logout</button>
        <button class="sidebar-btn" onclick="location='?page=settings'">≡ Settings</button>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="search-box">
        <span style="color:var(--muted)">🔍</span>
        <input type="text" placeholder="Search bins, reports, zones...">
      </div>
      <div class="topbar-right">
        <div class="rtdb-pill"><div class="rtdb-dot"></div>RTDB Live</div>
        <div class="icon-btn">✉️</div>
        <div class="icon-btn">🔔<span class="notif-badge"><?=(int)count(array_filter($alerts,fn($a)=>($a['level']??'')==='critical'))?></span></div>
        <div class="topbar-user">
          <div class="topbar-avatar"><?=htmlspecialchars($user['avatar'] ?? strtoupper(substr($user['name'] ?? 'U', 0, 2)))?></div>
          <div class="topbar-user-info">
            <div class="name"><?=htmlspecialchars($user['name'] ?? 'User')?></div>
            <div class="role"><?=htmlspecialchars($user['role'] ?? '')?></div>
          </div>
          <span style="color:#9ca3af;font-size:12px;">▾</span>
        </div>
      </div>
    </header>

    <div class="page-content fade">

    <?php if (isset($_GET['access']) && $_GET['access'] === 'denied'): ?>
    <div style="background:#fce4ec;border:1px solid #f48fb1;border-radius:12px;padding:14px 18px;color:#c62828;font-weight:600;font-size:14px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
      🔒 Access denied — the Admin Panel is only available to the Administrator role.
    </div>
    <?php endif; ?>

    <?php if (empty($bins) && $page !== 'admin'): ?>
    <div class="seed-card">
      <div style="font-size:28px;">🌱</div>
      <div style="flex:1;">
        <h3>Realtime Database is empty — seed your database first</h3>
        <p>Login as admin and visit the Admin Panel to seed bins, reports, alerts, tasks, and users.</p>
      </div>
      <?php if(is_admin() && admin_verified()): ?>
      <a href="?page=admin&action=seed" class="seed-btn">Seed RTDB →</a>
      <?php else: ?>
      <a href="?page=admin" class="seed-btn">Go to Admin Panel →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($seed_log)): ?>
    <div class="success-msg" style="flex-direction:column;align-items:flex-start;gap:3px;margin-bottom:20px;">
      <strong>🔥 Realtime Database seeded successfully!</strong>
      <?php foreach($seed_log as [$icon,$col,$msg]): ?>
      <span><?=$icon?> <strong><?=$col?></strong> — <?=$msg?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- AI Insights (role-specific) -->
    <div class="ai-section">
      <div class="ai-title">
        🤖 AI Insights — <?=htmlspecialchars($user['name'])?>
        <span class="ai-badge"><?=htmlspecialchars($user['role'])?></span>
        <span style="margin-left:auto;font-size:11px;color:var(--muted);font-weight:400;animation:none;">RTDB · <?=date('H:i')?></span>
      </div>
      <div class="ai-grid">
        <?php foreach($user_insights as [$title,$msg,$icon]): ?>
        <div class="ai-card">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <span style="font-size:20px;"><?=$icon?></span>
            <span style="font-weight:600;font-size:13px;color:var(--emerald);"><?=$title?></span>
          </div>
          <div style="font-size:13px;color:var(--text);"><?=$msg?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

<!-- ══════════════════════════════════════════════════
     DASHBOARD
══════════════════════════════════════════════════ -->
<?php if ($page === 'dashboard'):
  $criticalCount  = count(array_filter($bins, fn($b) => ($b['fill']??0) >= 80));
  $warningCount   = count(array_filter($bins, fn($b) => ($b['fill']??0) >= 60 && ($b['fill']??0) < 80));
  $totalBins      = count($bins);

  // Aggregate AI detection stats across all bins
  $total_detections = array_sum(array_column($bins, 'detection_count'));
  $waste_totals = [];
  foreach ($bins as $b) {
      foreach (($b['waste_counts'] ?? []) as $wtype => $cnt) {
          $waste_totals[$wtype] = ($waste_totals[$wtype] ?? 0) + $cnt;
      }
  }
  arsort($waste_totals);
  $top_waste    = ucfirst(array_key_first($waste_totals) ?? 'N/A');
  $avg_conf_all = 0; $conf_bins = 0;
  foreach ($bins as $b) {
      if (!empty($b['avg_confidence'])) { $avg_conf_all += $b['avg_confidence']; $conf_bins++; }
  }
  $avg_conf_all = $conf_bins > 0 ? round($avg_conf_all / $conf_bins, 1) : 0;
?>
  <div class="stats-row">
    <div class="stat-card green">
      <div class="stat-icon">🗑️</div>
      <div>
        <div class="stat-value"><?=$totalBins?></div>
        <div class="stat-label">Active Bins · Live</div>
      </div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0);">
      <div class="stat-icon" style="background:#e53e3e22;">🔴</div>
      <div>
        <div class="stat-value" style="color:#c62828;"><?=$criticalCount?></div>
        <div class="stat-label">Critical (≥80%)</div>
      </div>
    </div>
    <div class="stat-card amber">
      <div class="stat-icon">🤖</div>
      <div>
        <div class="stat-value"><?=$total_detections ?: '—'?></div>
        <div class="stat-label">AI Detections Total</div>
      </div>
    </div>
    <div class="stat-card teal">
      <div class="stat-icon">🎯</div>
      <div>
        <div class="stat-value"><?=$avg_conf_all > 0 ? $avg_conf_all.'%' : '—'?></div>
        <div class="stat-label">Avg AI Confidence</div>
      </div>
    </div>
  </div>

  <?php if ($total_detections > 0): ?>
  <!-- ── AI Detections Live Feed ── -->
  <div style="background:linear-gradient(135deg,#0d2818,#163020);border-radius:16px;padding:20px 24px;margin-bottom:20px;border:1px solid #1a6b4a55;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
      <span style="font-size:16px;font-weight:800;color:#fff;letter-spacing:-0.02em;">🤖 AI Model · Live Detections</span>
      <span class="ai-badge">Realtime DB</span>
      <span style="font-size:12px;color:#4b7a5e;margin-left:auto;"><?=$total_detections?> total records · <?=date('H:i:s')?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
      <?php foreach ($bins as $b):
        if (empty($b['all_detections'])) continue;
        $fill = (int)($b['fill'] ?? 0);
        $fc   = $fill>=80?'#e53e3e':($fill>=60?'#ff8c00':'#4caf50');
        $latest = end($b['all_detections']);
        $det_count = $b['detection_count'] ?? 0;
      ?>
      <div data-det-bin="<?=htmlspecialchars($b['id']??'')?>" style="background:rgba(255,255,255,0.05);border:1px solid #1a6b4a55;border-radius:12px;padding:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="color:#fff;font-weight:700;font-size:13px;"><?=htmlspecialchars($b['id']??'')?></span>
          <span style="background:<?=$fc?>22;color:<?=$fc?>;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?=$b['status']??'ok'?></span>
        </div>
        <!-- Fill bar -->
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#6b7280;margin-bottom:4px;">
          <span>Fill Level</span><span class="det-fill-label" style="color:<?=$fc?>;font-weight:700;"><?=$fill?>%</span>
        </div>
        <div style="background:#0d2818;border-radius:3px;height:5px;margin-bottom:10px;">
          <div class="det-fill-bar" style="width:<?=$fill?>%;height:100%;background:<?=$fc?>;border-radius:3px;transition:width 1s;"></div>
        </div>
        <!-- AI data rows -->
        <?php if (!empty($b['type'])): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #1a6b4a33;">
          <span style="color:#6b7280;">Waste Type</span>
          <span class="det-waste-type" style="color:#a8e6cf;font-weight:600;"><?=htmlspecialchars($b['type'])?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($b['avg_confidence'])): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #1a6b4a33;">
          <span style="color:#6b7280;">AI Confidence</span>
          <span class="det-confidence" style="color:<?=$b['avg_confidence']>=80?'#4caf50':($b['avg_confidence']>=60?'#ff8c00':'#e53e3e')?>;font-weight:700;"><?=$b['avg_confidence']?>%</span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #1a6b4a33;">
          <span style="color:#6b7280;">Detections</span>
          <span class="det-count" style="color:#a8e6cf;font-weight:600;"><?=$det_count?> records</span>
        </div>
        <?php if (!empty($b['ai_prediction'])): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #1a6b4a33;">
          <span style="color:#6b7280;">AI Prediction</span>
          <span style="color:<?=in_array($b['ai_prediction'],['IMMEDIATE','FILL NOW'])?'#e53e3e':'#4caf50'?>;font-weight:700;"><?=htmlspecialchars($b['ai_prediction'])?></span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;">
          <span style="color:#6b7280;">Last Seen</span>
          <span style="color:#a8e6cf;"><?=htmlspecialchars($b['last']??'—')?></span>
        </div>
        <?php
        $raw = $b['latest_raw'] ?? [];
        $skip_keys = ['fill_level','fill','fillLevel','fill_percent','distance_cm','waste_type','wasteType','category','label','class','confidence','confidence_score','score','timestamp','time','created_at','datetime','ai_prediction','prediction','status_message'];
        $extra = array_diff_key($raw, array_flip($skip_keys));
        if (!empty($extra)): ?>
        <div style="margin-top:8px;padding:8px;background:#0d2818;border-radius:6px;">
          <div style="font-size:10px;color:#4b7a5e;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Raw AI Fields</div>
          <?php foreach(array_slice($extra, 0, 4) as $k => $v): ?>
          <div style="display:flex;justify-content:space-between;font-size:11px;padding:2px 0;">
            <span style="color:#6b7280;"><?=htmlspecialchars($k)?></span>
            <span style="color:#9ca3af;"><?=htmlspecialchars(is_array($v)?json_encode($v):(string)$v)?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid-2-1">
    <div class="card">
      <div class="card-header">
        <div class="card-title">📍 Smart Bins · Kigali <span style="font-weight:400;color:var(--muted);font-size:12px;">Live from RTDB</span></div>
        <select class="card-btn" style="border-radius:8px;padding:5px 10px;">
          <option>🟢 Waste Levels</option><option>🔵 All Bins</option><option>🔴 Critical Only</option>
        </select>
      </div>
      <div id="kigali-map"></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Today's Tasks <span class="ai-badge">AI Priority</span></div><button class="card-btn">···</button></div>
        <div class="card-body" style="padding:10px 18px;">
          <?php if (empty($tasks)): ?>
          <div style="text-align:center;padding:10px;color:var(--muted);font-size:13px;">No tasks in RTDB yet</div>
          <?php else: foreach(array_slice($tasks, 0, 4) as $t): $done = (bool)($t['done'] ?? false); ?>
          <div class="task-item">
            <div class="task-check <?=$done?'done':''?>"><?=$done?'✓':''?></div>
            <div style="flex:1;">
              <div class="task-text"><?=htmlspecialchars($t['text']??'')?></div>
              <div class="task-time"><?=$t['time']??''?> · AI: <span style="color:<?=($t['ai_priority']??'')===
'High'?'var(--red)':'var(--amber)'?>;font-weight:600;"><?=$t['ai_priority']??''?></span></div>
            </div>
          </div>
          <?php endforeach; endif; ?>
          <div style="text-align:center;padding:10px 0 4px;"><a href="?page=reports" style="font-size:13px;color:var(--emerald);font-weight:600;">View all tasks →</a></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div><div class="card-title">System Status</div><div class="card-sub">RTDB connected</div></div>
          <button onclick="location='?page=reports'" style="padding:8px 16px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:8px;color:#fff;font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;">Generate Report</button>
        </div>
        <div class="card-body" style="padding:8px 18px;">
          <div class="status-item"><div class="status-dot green"></div> Realtime DB: Connected</div>
          <div class="status-item"><div class="status-dot <?=$criticalCount>0?'red':'green'?>"></div> <?=$criticalCount?> Bins Critical</div>
          <div class="status-item"><div class="status-dot green"></div> AI Camera System Active</div>
          <div class="status-item"><div class="status-dot green"></div> Real-time Sync: ON</div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid-3">
    <div class="card">
      <div class="card-header"><div><div class="card-title">Waste Composition</div><div class="card-sub">Kigali Today</div></div></div>
      <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center;">
          <div style="width:130px;height:130px;flex-shrink:0;"><canvas id="pieChart" width="130" height="130"></canvas></div>
          <div class="pie-legend" style="flex:1;">
            <?php foreach([['Organic','#4caf50','38%'],['Plastic','#ff8c00','25%'],['Paper','#2b6cb0','19%'],['Metal','#9ca3af','18%']] as [$l,$c,$p]): ?>
            <div class="pie-legend-item"><div class="pie-dot" style="background:<?=$c?>"></div><span><?=$l?></span><span class="pie-pct"><?=$p?></span></div>
            <?php endforeach; ?>
            <a href="?page=analytics" style="font-size:12px;color:var(--emerald);font-weight:600;display:block;margin-top:4px;">··· More ▾</a>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Recycling Analytics</div></div>
      <div class="card-body">
        <div style="height:100px;margin-bottom:12px;"><canvas id="lineChart"></canvas></div>
        <div style="display:flex;border-radius:10px;overflow:hidden;border:1px solid var(--border);">
          <?php foreach([['34%','Today'],['36%','This Week'],['40%','This Month']] as [$v,$l]): ?>
          <div style="flex:1;text-align:center;padding:10px 6px;border-right:1px solid var(--border);"><div style="font-size:20px;font-weight:800;color:var(--emerald);"><?=$v?></div><div style="font-size:11px;color:var(--muted);"><?=$l?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Recycling Summary</div></div>
      <div class="card-body" style="padding:8px 18px;">
        <?php foreach([['34%','Today','♻️'],['36%','This Week','🗑️'],['40%','This Month','♻️'],['6.2%','Annual Rate','🌿']] as [$pct,$lbl,$icon]): ?>
        <div class="recycle-stat"><span class="recycle-pct"><?=$pct?></span><span class="recycle-label"><?=$lbl?></span><span style="font-size:22px;"><?=$icon?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<!-- ══════════════════════════════════════════════════
     SMART BINS
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'bins'):
  $total = count($bins);
  $crit  = count(array_filter($bins, fn($b) => ($b['fill']??0) >= 80));
  $warn  = count(array_filter($bins, fn($b) => ($b['fill']??0) >= 60 && ($b['fill']??0) < 80));
  $ok    = count(array_filter($bins, fn($b) => ($b['fill']??0) < 60));
?>
  <div class="page-title">Smart Bins <span class="ai-badge">Live · RTDB</span></div>
  <div class="page-sub">Real-time fill levels from Firebase Realtime Database</div>

  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">🗑️</div><div><div class="stat-value"><?=$total?></div><div class="stat-label">Total Bins</div></div></div>
    <div class="stat-card" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0);"><div class="stat-icon" style="background:#e53e3e22;">🔴</div><div><div class="stat-value" style="color:#c62828;"><?=$crit?></div><div class="stat-label">Critical</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">⚠️</div><div><div class="stat-value"><?=$warn?></div><div class="stat-label">Warning</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">✅</div><div><div class="stat-value" style="color:#00695c;"><?=$ok?></div><div class="stat-label">OK</div></div></div>
  </div>

  <div class="filter-tabs">
    <?php foreach(['All Bins','Critical ≥80%','Warning 60-79%','OK <60%','Central','East','North','South'] as $f): ?>
    <button class="filter-tab <?=$f==='All Bins'?'active':''?>" onclick="filterBins('<?=$f?>',this)"><?=$f?></button>
    <?php endforeach; ?>
  </div>

  <?php if (empty($bins)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--muted);">
    <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
    <strong>No bins found in Realtime Database</strong><br><br>
    <a href="?page=admin" class="btn-submit">Go to Admin Panel to Seed →</a>
  </div>
  <?php else: ?>
  <div class="bin-grid" id="bin-grid">
    <?php foreach($bins as $bin):
      $fill   = (int)($bin['fill'] ?? 0);
      $id     = $bin['id'] ?? '';
      $status = $bin['status'] ?? 'ok';
      $fc     = fillColor($fill);
      $bc     = $status==='critical'?'badge-red':($status==='warning'?'badge-amber':'badge-green');
    ?>
    <div class="bin-card" data-bin-id="<?=htmlspecialchars($id)?>" data-zone="<?=htmlspecialchars($bin['zone']??'')?>" data-status="<?=$status?>" data-fill="<?=$fill?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div>
          <div class="bin-name"><?=htmlspecialchars($bin['name']??$id)?></div>
          <div class="bin-id"><?=$id?> · <?=htmlspecialchars($bin['zone']??'')?> Zone</div>
        </div>
        <span class="badge <?=$bc?>"><?=$status?></span>
      </div>
      <div class="bin-fill-label">
        <span>Fill Level</span>
        <span class="bin-fill-val" style="color:<?=$fc?>"><?=$fill?>%</span>
      </div>
      <div class="fill-bar-bg"><div class="fill-bar-inner" style="width:<?=$fill?>%;background:<?=$fc?>"></div></div>
      <div style="display:flex;justify-content:space-between;margin:8px 0;font-size:12px;">
        <span>🤖 AI Prediction:</span>
        <span style="font-weight:700;color:<?=in_array($bin['ai_prediction']??'',['IMMEDIATE','FILL NOW'])?'var(--red)':'var(--emerald)'?>;"><?=htmlspecialchars($bin['ai_prediction']??'—')?></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:10px;">
        <span style="color:var(--muted);">Maintenance Score</span>
        <div style="display:flex;align-items:center;gap:5px;">
          <div class="fill-bar-bg" style="width:50px;"><div class="fill-bar-inner" style="width:<?=$bin['maintenance_score']??0?>%;background:<?=($bin['maintenance_score']??0)>=80?'var(--lime)':(($bin['maintenance_score']??0)>=60?'var(--amber)':'var(--red)')?>"></div></div>
          <span><?=$bin['maintenance_score']??'—'?>%</span>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <span class="bin-tag">🏷️ <?=htmlspecialchars($bin['type']??'Mixed')?></span>
        <span class="bin-tag">🕒 <?=htmlspecialchars($bin['last']??'—')?></span>
      </div>
      <?php if ($fill >= 80): ?>
      <button onclick="dispatchBin('<?=$id?>')" style="width:100%;margin-top:12px;padding:9px;background:linear-gradient(135deg,var(--emerald),var(--lime));border:none;border-radius:8px;color:#fff;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;">🤖 AI Dispatch Collection</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<!-- ══════════════════════════════════════════════════
     WASTE COLLECTION
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'collection'): ?>
  <div class="page-title">Waste Collection <span class="ai-badge">AI Route Optimization</span></div>
  <div class="page-sub">Optimized collection routes and vehicle tracking</div>
  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">🚛</div><div><div class="stat-value">14</div><div class="stat-label">Active Vehicles</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🛣️</div><div><div class="stat-value">247 km</div><div class="stat-label">Total Route Distance</div></div></div>
    <div class="stat-card blue" ><div class="stat-icon">✅</div><div><div class="stat-value">12/14</div><div class="stat-label">Routes Completed</div></div></div>
    <div class="stat-card teal" ><div class="stat-icon">⛽</div><div><div class="stat-value">18%</div><div class="stat-label">Fuel Saved</div></div></div>
  </div>
  <div class="grid-2-1">
    <div class="card">
      <div class="card-header"><div class="card-title">🗺️ AI-Optimized Routes — Kigali</div></div>
      <div id="collection-map" style="height:380px;border-radius:0 0 14px 14px;"></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Collection Zones</div></div>
      <div class="card-body" style="padding:12px 18px;">
        <?php foreach([['Central','6 bins','21 km','88%','High'],['East','8 bins','34 km','72%','Medium'],['North','5 bins','28 km','91%','Low'],['South','4 bins','19 km','85%','Medium']] as [$z,$bc2,$dist,$eff,$pri]): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-weight:700;font-size:13.5px;"><?=$z?> Zone</span>
            <span style="font-size:12px;color:var(--muted);"><?=$bc2?> · <?=$dist?></span>
          </div>
          <div class="fill-bar-bg"><div class="fill-bar-inner" style="width:<?=$eff?>;background:linear-gradient(90deg,var(--emerald),var(--lime))"></div></div>
          <div style="font-size:11px;color:var(--muted);margin-top:4px;"><?=$eff?> efficiency · AI: <?=$pri?> priority</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Active Routes</div></div>
    <div class="card-body">
      <?php foreach([['Central Zone A','🏙️','6 bins','21 km','94%','Active','ETA: 45 min'],['East Zone B','🌆','8 bins','34 km','65%','Active','ETA: 1h 20min'],['North Zone C','🌄','5 bins','28 km','100%','Completed','Done'],['South Zone D','🏘️','4 bins','19 km','100%','Completed','Done'],['Gikondo Industrial','🏭','3 bins','12 km','20%','Pending','AI: Urgent']] as [$name,$icon,$bc3,$dist,$prog,$status,$eta]):
        $sc = $status==='Completed'?'badge-green':($status==='Active'?'badge-blue':'badge-gray');
      ?>
      <div class="route-card">
        <div class="route-zone"><?=$icon?></div>
        <div style="flex:1;">
          <div class="route-name"><?=$name?></div>
          <div class="route-meta"><?=$bc3?> · <?=$dist?> · <?=$eta?></div>
          <div class="progress-bar"><div class="progress-inner" style="width:<?=$prog?>"></div></div>
        </div>
        <span class="badge <?=$sc?>"><?=$status?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

<!-- ══════════════════════════════════════════════════
     RECYCLING ANALYTICS
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'analytics'): ?>
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
      <div class="card-header"><div class="card-title">🎯 Recycling Rate</div></div>
      <div class="card-body" style="text-align:center;padding:24px 20px;">
        <div style="font-size:56px;font-weight:800;color:var(--emerald);letter-spacing:-0.05em;">6.2%</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:14px;">Current · Target: 25% by 2030</div>
        <div class="fill-bar-bg" style="height:10px;border-radius:5px;"><div class="fill-bar-inner" style="width:24.8%;background:linear-gradient(90deg,var(--emerald),var(--lime));height:10px;border-radius:5px;"></div></div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px;">24.8% of 2030 goal achieved</div>
        <div style="font-size:12px;margin-top:8px;padding:8px;background:var(--bg);border-radius:8px;">🤖 AI Forecast: 8.4% by Dec 2024</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;">
          <?php foreach([['1,850 kg','Compost/Day'],['52 kWh','Energy/Day'],['23','Farmers Served'],['8–10','Homes Powered']] as [$v,$l]): ?>
          <div style="background:var(--bg);border-radius:10px;padding:12px;text-align:center;"><div style="font-size:18px;font-weight:800;color:var(--emerald);"><?=$v?></div><div style="font-size:11px;color:var(--muted);"><?=$l?></div></div>
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
          <div style="font-size:28px;font-weight:800;color:<?=$color?>;letter-spacing:-0.04em;"><?=$pct?></div>
          <div style="font-size:14px;font-weight:700;margin-bottom:4px;"><?=$type?></div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:10px;"><?=$kg?> today</div>
          <div class="fill-bar-bg" style="height:4px;"><div class="fill-bar-inner" style="width:<?=$pct?>;background:<?=$color?>"></div></div>
          <div style="font-size:11px;color:var(--muted);margin-top:8px;line-height:1.5;"><?=$desc?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<!-- ══════════════════════════════════════════════════
     REPORTS
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'reports'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <div>
      <div class="page-title">Waste Reports <span class="ai-badge">RTDB</span></div>
      <div class="page-sub">Reports saved to and loaded from Firebase Realtime Database</div>
    </div>
    <button onclick="document.getElementById('report-form').style.display='block'" class="btn-submit" style="padding:11px 22px;">+ New Report</button>
  </div>

  <?php if ($report_success): ?>
  <div class="success-msg">✅ Report saved to Realtime Database successfully!</div>
  <?php endif; ?>

  <div id="report-form" style="display:none;margin-bottom:20px;">
    <div class="card">
      <div class="card-header"><div class="card-title">📋 Submit New Report → Saves to RTDB</div></div>
      <div class="card-body">
        <form method="POST">
          <div class="form-row" style="margin-bottom:14px;">
            <div class="form-group-light"><label>Location / Area</label><input type="text" name="location" required placeholder="e.g. Remera Market"></div>
            <div class="form-group-light"><label>Your Name</label><input type="text" name="reporter" required placeholder="Full name"></div>
          </div>
          <div class="form-row" style="margin-bottom:14px;">
            <div class="form-group-light"><label>Issue Type</label><select name="type"><option>Overflow</option><option>Illegal Dumping</option><option>Bin Damage</option><option>Bad Odor</option><option>Blocked Access</option><option>Other</option></select></div>
            <div class="form-group-light"><label>Severity</label><select name="severity"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
          </div>
          <div class="form-group-light" style="margin-bottom:16px;"><label>Description</label><textarea name="description" rows="3" placeholder="Describe the issue in detail..."></textarea></div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('report-form').style.display='none'" class="btn-outline">Cancel</button>
            <button type="submit" name="submit_report" class="btn-submit">Save to RTDB →</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">All Reports <span style="font-weight:400;color:var(--muted);font-size:12px;"><?=count($reports)?> total · sorted by AI priority</span></div>
    </div>
    <?php if (empty($reports)): ?>
    <div style="padding:30px;text-align:center;color:var(--muted);">No reports in RTDB yet. Submit one above or seed from Admin Panel.</div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>Location</th><th>Type</th><th>Severity</th><th>Reporter</th><th>Time</th><th>Status</th><th>AI Priority</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($reports as $r):
        $sv = sevColor($r['severity'] ?? 'Low');
        $ss = statColor($r['status'] ?? 'Pending');
      ?>
      <tr>
        <td style="font-weight:700;font-size:12px;color:var(--muted);"><?=htmlspecialchars($r['id']??'—')?></td>
        <td style="font-weight:600;"><?=htmlspecialchars($r['location']??'')?></td>
        <td><span class="badge badge-gray"><?=htmlspecialchars($r['type']??'')?></span></td>
        <td><span style="background:<?=$sv?>22;color:<?=$sv?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?=htmlspecialchars($r['severity']??'')?></span></td>
        <td><?=htmlspecialchars($r['reporter']??'')?></td>
        <td style="color:var(--muted);font-size:12px;"><?=htmlspecialchars($r['time']??'')?></td>
        <td><span style="background:<?=$ss?>22;color:<?=$ss?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?=htmlspecialchars($r['status']??'')?></span></td>
        <td><span style="font-weight:700;color:<?=($r['ai_priority']??0)>=80?'var(--red)':(($r['ai_priority']??0)>=60?'var(--amber)':'var(--lime)')?>;"><?=$r['ai_priority']??'—'?></span></td>
        <td><button onclick="alert('AI assigned!')" style="padding:4px 12px;border-radius:6px;border:1px solid var(--emerald);color:var(--emerald);background:transparent;font-size:11px;cursor:pointer;font-weight:600;">AI Assign</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

<!-- ══════════════════════════════════════════════════
     ALERTS
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'alerts'):
  $crit2 = count(array_filter($alerts, fn($a) => ($a['level']??'') === 'critical'));
  $warn2 = count(array_filter($alerts, fn($a) => ($a['level']??'') === 'warning'));
  $info2 = count(array_filter($alerts, fn($a) => ($a['level']??'') === 'info'));
?>
  <div class="page-title">🔔 Alerts <span class="ai-badge">RTDB · Live</span></div>
  <div class="page-sub">Real-time notifications loaded from Firebase Realtime Database</div>
  <div class="stats-row">
    <div class="stat-card" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0)"><div class="stat-icon" style="background:#e53e3e22">🔴</div><div><div class="stat-value" style="color:#c62828"><?=$crit2?></div><div class="stat-label">Critical Alerts</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🟡</div><div><div class="stat-value"><?=$warn2?></div><div class="stat-label">Warning Alerts</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">ℹ️</div><div><div class="stat-value"><?=$info2?></div><div class="stat-label">Info Alerts</div></div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div><div class="stat-value">18</div><div class="stat-label">Resolved Today</div></div></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Active Alerts with AI Actions</div><button class="card-btn">Mark All Read</button></div>
    <?php if (empty($alerts)): ?>
    <div style="padding:30px;text-align:center;color:var(--muted);">No alerts in RTDB. Go to Admin Panel to seed data.</div>
    <?php else: foreach ($alerts as $a):
      $level = $a['level'] ?? 'info';
      $fc    = $level==='critical'?'#e53e3e':($level==='warning'?'#ff8c00':'#2b6cb0');
    ?>
    <div class="alert-item">
      <div class="alert-dot <?=$level?>"></div>
      <div style="flex:1;">
        <div class="alert-title"><?=htmlspecialchars($a['location']??'')?></div>
        <div class="alert-sub"><?=htmlspecialchars($a['msg']??'')?> · <?=htmlspecialchars($a['bin']??'')?></div>
      </div>
      <div class="alert-fill" style="color:<?=$fc?>"><?=$a['fill']??0?>%</div>
      <div class="alert-time"><?=htmlspecialchars($a['time']??'')?></div>
      <div style="display:flex;gap:6px;">
        <span style="font-size:11px;background:var(--bg);padding:3px 8px;border-radius:12px;">🤖 <?=htmlspecialchars($a['ai_action']??'')?></span>
        <button onclick="alert('Responding to <?=htmlspecialchars($a['bin']??'')?>')" style="padding:6px 14px;border-radius:8px;border:none;background:<?=$fc?>22;color:<?=$fc?>;font-size:12px;cursor:pointer;font-weight:700;">Respond</button>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

<!-- ══════════════════════════════════════════════════
     SETTINGS
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'settings'): ?>
  <div class="page-title">Settings</div>
  <div class="page-sub">Configure EcoSense Rwanda system preferences</div>
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><div class="card-title">👤 Profile Settings</div></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border);">
          <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),var(--lime));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;"><?=htmlspecialchars($user['avatar']??'A')?></div>
          <div><div style="font-weight:800;font-size:16px;"><?=htmlspecialchars($user['name']??'')?></div><div style="font-size:13px;color:var(--muted);"><?=htmlspecialchars($user['role']??'')?></div></div>
          <button class="btn-outline" style="margin-left:auto;font-size:12px;">Change Photo</button>
        </div>
        <div class="form-row" style="gap:12px;margin-bottom:12px;">
          <div class="form-group-light"><label>Full Name</label><input type="text" value="<?=htmlspecialchars($user['name']??'')?>"></div>
          <div class="form-group-light"><label>Email</label><input type="email" value="<?=htmlspecialchars($user['email']??'')?>"></div>
        </div>
        <div class="form-group-light" style="margin-bottom:16px;"><label>Role</label><select><option><?=htmlspecialchars($user['role']??'')?></option></select></div>
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
        <div class="form-group-light" style="margin-bottom:12px;"><label>AI Sensitivity</label><select><option>High (≥85%)</option><option selected>Standard (≥75%)</option><option>Low (≥60%)</option></select></div>
        <div class="form-group-light" style="margin-bottom:12px;"><label>AI Prediction Interval</label><select><option selected>Real-time</option><option>Every 5 minutes</option><option>Every 15 minutes</option></select></div>
        <div class="form-group-light" style="margin-bottom:16px;"><label>AI Model Version</label><select><option selected>v2.3.1 (Current)</option><option>v2.4.0-beta</option></select></div>
        <button class="btn-submit">Save AI Configuration</button>
      </div>
    </div>
  </div>

<!-- ══════════════════════════════════════════════════
     ADMIN PANEL
══════════════════════════════════════════════════ -->
<?php elseif ($page === 'admin'):
  if (!is_admin()): ?>
  <div style="padding:40px;text-align:center;color:#c62828;font-family:var(--font);">
    <div style="font-size:48px;margin-bottom:16px;">🔒</div>
    <strong style="font-size:18px;">Access Denied</strong>
    <p style="margin-top:8px;color:var(--muted);">The Admin Panel is only available to Administrator accounts.</p>
    <a href="?page=dashboard" style="display:inline-block;margin-top:16px;padding:10px 22px;background:linear-gradient(135deg,var(--emerald),var(--lime));border-radius:9px;color:#fff;font-weight:700;font-size:14px;">← Back to Dashboard</a>
  </div>
  <?php else: ?>
  <div class="page-title">Admin Panel</div>
  <div class="page-sub">Manage users, devices, and seed Realtime Database</div>

  <div class="seed-card">
    <div style="font-size:28px;">🌱</div>
    <div style="flex:1;">
      <h3>Seed Firebase Realtime Database</h3>
      <p>Writes all sample bins, reports, alerts, users and tasks to your RTDB project. Safe to re-run — overwrites existing data with latest seed.</p>
    </div>
    <a href="?page=admin&action=seed" class="seed-btn">🔥 Seed RTDB →</a>
  </div>

  <!-- RTDB Rules reminder -->
  <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px;">
    <strong style="color:#1565c0;">📋 Firebase RTDB Rules Required</strong><br>
    <span style="color:#1565c0;">Go to Firebase Console → Realtime Database → Rules and set:</span>
    <pre style="background:#0d2818;color:#a8e6cf;border-radius:8px;padding:10px;margin-top:8px;font-size:12px;overflow-x:auto;">{
  "rules": {
    ".read": true,
    ".write": true
  }
}</pre>
    <span style="color:#6b7280;font-size:11px;">⚠️ This is for development only. Secure your rules before going to production.</span>
  </div>

  <div class="stats-row">
    <div class="stat-card green"><div class="stat-icon">👥</div><div><div class="stat-value">47</div><div class="stat-label">Total Users</div></div></div>
    <div class="stat-card amber"><div class="stat-icon">🗑️</div><div><div class="stat-value"><?=count($bins)?></div><div class="stat-label">Bins in RTDB</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🚛</div><div><div class="stat-value">14</div><div class="stat-label">Vehicles Tracked</div></div></div>
    <div class="stat-card teal"><div class="stat-icon">📡</div><div><div class="stat-value">98.6%</div><div class="stat-label">Sensor Uptime</div></div></div>
  </div>

  <div class="grid-2">
    <div class="card">
      <div class="card-header"><div class="card-title">👥 Team Members</div><button class="btn-submit" style="padding:7px 14px;font-size:12px;">+ Add User</button></div>
      <div class="card-body">
        <?php foreach([['AD','admin','Administrator','admin'],['HD','HITIMANA TETA Divine','Hardware Engineer','engineer'],['NB','NIBEZA MUGISHA Bruce','Software/AI Dev','developer'],['IJ','IGIRANEZA JOSEPH','IoT & Network','specialist'],['MS','MBARUSHIMANA SIMBI Belise','Data Analyst & UI','analyst']] as [$init,$name,$role,$type]): ?>
        <div class="admin-user-row"><div class="admin-avatar"><?=$init?></div><div><div class="admin-name"><?=$name?></div><div class="admin-role"><?=$role?></div></div><span class="badge badge-green" style="margin-left:8px;"><?=$type?></span><div class="admin-action"><button class="admin-btn edit">Edit</button><button class="admin-btn del">Remove</button></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">📊 RTDB Overview</div></div>
      <div class="card-body">
        <?php
          $rpt_count = count(rtdb_get_all('reports'));
          $alt_count = count(rtdb_get_all('alerts'));
        ?>
        <?php foreach([['Bins Collection','bins · '.count($bins).' records','🗑️'],['Reports Collection','reports · '.$rpt_count.' records','📋'],['Alerts Collection','alerts · '.$alt_count.' records','🔔'],['Tasks Collection','tasks · loaded on dashboard','✅'],['Users Collection','users · seeded team members','👤'],['Real-time Listeners','active on bins path','📡']] as [$l,$v,$icon]): ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);"><span style="font-size:13px;color:var(--muted);"><?=$icon?> <?=$l?></span><span style="font-weight:700;color:var(--emerald);font-size:13px;"><?=$v?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($bins)): ?>
  <div class="card">
    <div class="card-header"><div class="card-title">🗑️ Bins in RTDB</div></div>
    <table class="data-table">
      <thead><tr><th>ID</th><th>Location</th><th>Zone</th><th>Type</th><th>Fill</th><th>Status</th><th>AI Prediction</th><th>Maint Score</th><th>Last</th></tr></thead>
      <tbody>
      <?php foreach ($bins as $b):
        $fill = (int)($b['fill']??0); $fc = fillColor($fill);
        $status = $b['status']??'ok';
        $bc = $status==='critical'?'badge-red':($status==='warning'?'badge-amber':'badge-green');
      ?>
      <tr>
        <td style="font-weight:700;font-size:12px;color:var(--muted);"><?=htmlspecialchars($b['id']??'—')?></td>
        <td style="font-weight:600;"><?=htmlspecialchars($b['name']??'')?></td>
        <td><?=htmlspecialchars($b['zone']??'')?></td>
        <td><span class="badge badge-gray"><?=htmlspecialchars($b['type']??'')?></span></td>
        <td><div style="display:flex;align-items:center;gap:8px;"><div class="fill-bar-bg" style="width:60px;"><div class="fill-bar-inner" style="width:<?=$fill?>%;background:<?=$fc?>"></div></div><span style="font-size:12px;font-weight:700;color:<?=$fc?>"><?=$fill?>%</span></div></td>
        <td><span class="badge <?=$bc?>"><?=$status?></span></td>
        <td style="font-size:11px;"><?=htmlspecialchars($b['ai_prediction']??'—')?></td>
        <td><?=$b['maintenance_score']??'—'?>%</td>
        <td style="font-size:12px;color:var(--muted);"><?=htmlspecialchars($b['last']??'—')?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>

<?php endif; // end page routing ?>

    <!-- AI Chatbot -->
    <div class="ai-chat collapsed" id="aiChat">
      <div class="ai-chat-header" onclick="document.getElementById('aiChat').classList.toggle('collapsed')">
        <span>🤖</span> EcoSense AI · RTDB Live
        <span style="margin-left:auto;">▼</span>
      </div>
      <div class="ai-chat-body" id="chatBody">
        <div class="ai-message bot">Hi <?=htmlspecialchars($user['name'])?>! I have live access to your Realtime Database. Ask about bins, routes, alerts, or predictions.</div>
      </div>
      <form onsubmit="sendAI(event)" class="ai-input">
        <input type="text" id="aiInput" placeholder="Ask about bins, routes..." required>
        <button type="submit">→</button>
      </form>
    </div>

    </div><!-- /page-content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
function togglePwd(id, btn) {
  const f = document.getElementById(id);
  if (!f) return;
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁' : '🙈';
}

function filterBins(filter, btn) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.bin-card').forEach(card => {
    const fill = parseInt(card.dataset.fill), zone = card.dataset.zone;
    let show = true;
    if      (filter === 'Critical ≥80%')  show = fill >= 80;
    else if (filter === 'Warning 60-79%') show = fill >= 60 && fill < 80;
    else if (filter === 'OK <60%')        show = fill < 60;
    else if (['Central','East','North','South'].includes(filter)) show = zone === filter;
    card.style.display = show ? '' : 'none';
  });
}

function dispatchBin(binId) {
  if (!confirm('Dispatch collection vehicle to ' + binId + '?')) return;
  if (typeof window.updateBinFill === 'function') {
    window.updateBinFill(binId, 0).then(() => alert('✅ RTDB updated: ' + binId + ' reset to 0% after dispatch'));
  } else {
    fetch('', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'update_bin=1&bin_id=' + binId + '&fill=0'
    }).then(() => alert('✅ Dispatch recorded for ' + binId));
  }
}

<?php
  $chat_critical_count  = count(array_filter($bins, fn($b) => ($b['fill']??0) >= 80));
  $chat_immediate_count = count(array_filter($bins, fn($b) => in_array($b['ai_prediction']??'', ['IMMEDIATE','FILL NOW'])));
  $chat_alert_count     = count($alerts);
  $chat_alert_crit      = count(array_filter($alerts, fn($a) => ($a['level']??'') === 'critical'));
  $chat_report_count    = count($reports);
  $sorted_bins = $bins;
  usort($sorted_bins, fn($a,$b) => ($b['fill']??0) <=> ($a['fill']??0));
  $top_bin_name = $sorted_bins[0]['name'] ?? 'N/A';
  $top_bin_fill = (int)($sorted_bins[0]['fill'] ?? 0);
?>
const _stats = {
  critical:  <?=(int)$chat_critical_count?>,
  immediate: <?=(int)$chat_immediate_count?>,
  alerts:    <?=(int)$chat_alert_count?>,
  alertCrit: <?=(int)$chat_alert_crit?>,
  reports:   <?=(int)$chat_report_count?>,
  topBin:    <?=json_encode($top_bin_name)?>,
  topFill:   <?=(int)$top_bin_fill?>
};

function sendAI(e) {
  e.preventDefault();
  const input = document.getElementById('aiInput');
  const body  = document.getElementById('chatBody');
  const q     = input.value.trim().toLowerCase();
  if (!q) return;
  body.innerHTML += `<div class="ai-message user">${input.value}</div>`;
  input.value = '';
  // Merge PHP stats with any live RTDB updates
  const live  = window._rtdbStats || {};
  const stats = {
    critical:  live.critical  !== undefined ? live.critical  : _stats.critical,
    topBin:    live.topBin    !== undefined ? live.topBin    : _stats.topBin,
    topFill:   live.topFill   !== undefined ? live.topFill   : _stats.topFill,
    alerts:    _stats.alerts,
    alertCrit: _stats.alertCrit,
    reports:   _stats.reports,
    immediate: _stats.immediate,
  };
  setTimeout(() => {
    let r = '';
    if (q.includes('bin') || q.includes('fill'))
      r = `🗑️ Live RTDB: ${stats.critical} critical bins. Highest: ${stats.topBin} at ${stats.topFill}%.`;
    else if (q.includes('route') || q.includes('collect'))
      r = '🗺️ AI Route: Central → East → South Zone. Estimated fuel saving: 18%.';
    else if (q.includes('predict'))
      r = `🔮 AI: ${stats.immediate} bins need IMMEDIATE attention right now.`;
    else if (q.includes('alert'))
      r = `🔔 RTDB alerts: ${stats.alerts} active · ${stats.alertCrit} critical.`;
    else if (q.includes('report'))
      r = `📋 RTDB reports: ${stats.reports} loaded.`;
    else if (q.includes('detect') || q.includes('ai') || q.includes('model'))
      r = `🤖 AI model detections are live from RTDB. Highest fill: ${stats.topBin} at ${stats.topFill}%. ${stats.critical} bins critical.`;
    else
      r = '🤖 I can help with bins, routes, alerts, reports, detections, and AI predictions.';
    body.innerHTML += `<div class="ai-message bot">${r}</div>`;
    body.scrollTop = body.scrollHeight;
  }, 500);
}

<?php if ($page === 'dashboard'): ?>
(function(){
  const p = document.getElementById('pieChart');
  if (p) new Chart(p,{type:'doughnut',data:{labels:['Organic','Plastic','Paper','Metal'],datasets:[{data:[38,25,19,18],backgroundColor:['#4caf50','#ff8c00','#2b6cb0','#9ca3af'],borderWidth:2,borderColor:'#fff'}]},options:{cutout:'65%',plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:true}});
  const l = document.getElementById('lineChart');
  if (l) new Chart(l,{type:'line',data:{labels:['M','T','W','T','F','S'],datasets:[{data:[30,34,28,38,35,40],borderColor:'#4caf50',backgroundColor:'#4caf5020',borderWidth:2,fill:true,tension:0.4,pointBackgroundColor:'#4caf50',pointRadius:3}]},options:{plugins:{legend:{display:false}},scales:{y:{min:0,max:45,ticks:{stepSize:10,font:{size:10},color:'#9ca3af'},grid:{color:'#f0f4f1'}},x:{ticks:{font:{size:10},color:'#9ca3af'},grid:{display:false}}},responsive:true,maintainAspectRatio:false}});
  const map = L.map('kigali-map').setView([-1.9500,30.0619],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);
  window._mapMarkers = {};

  // Bins array — lat/lng come EXCLUSIVELY from detections/ in RTDB.
  // has_gps=false means the device has not yet sent GPS coordinates.
  const bins = <?=json_encode(array_values(array_map(fn($b)=>[
    'id'         => $b['id']   ?? '',
    'name'       => $b['name'] ?? '',
    'lat'        => $b['lat']  !== null ? (float)$b['lat'] : null,
    'lng'        => $b['lng']  !== null ? (float)$b['lng'] : null,
    'has_gps'    => (bool)($b['has_gps'] ?? false),
    'fill'       => (int)($b['fill']    ?? 0),
    'zone'       => $b['zone']          ?? '—',
    'type'       => $b['type']          ?? '—',
    'status'     => $b['status']        ?? 'ok',
    'last'       => $b['last']          ?? '—',
    'ai_prediction'=> $b['ai_prediction'] ?? '—',
    'confidence' => $b['avg_confidence'] ?? null,
    'det_count'  => $b['detection_count'] ?? 0,
  ], $bins)))?>;

  let placedCount = 0;
  const noGpsBins = [];

  bins.forEach(b => {
    if (!b.has_gps || !b.lat || !b.lng) {
      noGpsBins.push(b.id);
      return; // no GPS from detections yet — skip map placement
    }
    placedCount++;
    const c    = b.fill>=80 ? '#e53e3e' : b.fill>=60 ? '#ff8c00' : '#4caf50';
    const icon = L.divIcon({
      html: `<div style="background:${c};width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 3px 12px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;">
               <span style="transform:rotate(45deg);font-size:10px;font-weight:800;color:#fff;">${b.fill}%</span>
             </div>`,
      className:'', iconSize:[32,32], iconAnchor:[16,32]
    });
    const marker = L.marker([b.lat, b.lng], {icon}).addTo(map);
    marker.bindPopup(`
      <div class="popup-title">${b.name || b.id}</div>
      <div class="popup-row"><span>ID</span><strong>${b.id}</strong></div>
      <div class="popup-row"><span>GPS Source</span><strong style="color:#4caf50;">✔ From detections/</strong></div>
      <div class="popup-row"><span>Coordinates</span><strong>${b.lat.toFixed(5)}, ${b.lng.toFixed(5)}</strong></div>
      <div class="popup-row"><span>Fill</span><strong style="color:${c}">${b.fill}%</strong></div>
      <div class="popup-row"><span>AI Prediction</span><strong>${b.ai_prediction||'—'}</strong></div>
      <div class="popup-row"><span>Waste Type</span><strong>${b.type||'—'}</strong></div>
      ${b.confidence ? `<div class="popup-row"><span>Confidence</span><strong>${b.confidence}%</strong></div>` : ''}
      ${b.det_count  ? `<div class="popup-row"><span>Detections</span><strong>${b.det_count} records</strong></div>` : ''}
      <div class="popup-row"><span>Zone</span><strong>${b.zone||'—'}</strong></div>
      <div class="popup-row"><span>Last seen</span><strong>${b.last||'—'}</strong></div>
      <button class="popup-dispatch" onclick="dispatchBin('${b.id}')">🤖 AI Dispatch</button>
    `, {maxWidth:280});
    window._mapMarkers[b.id] = marker;
  });

  // Show notice if some bins have no GPS in detections yet
  if (noGpsBins.length > 0) {
    const notice = L.control({position:'bottomleft'});
    notice.onAdd = () => {
      const d = L.DomUtil.create('div');
      d.style.cssText = 'background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:8px 12px;font-size:12px;max-width:240px;line-height:1.5;';
      d.innerHTML = `<strong>⚠️ ${noGpsBins.length} bin(s) have no GPS yet</strong><br>
        <span style="color:#6b7280;">${noGpsBins.join(', ')}</span><br>
        <span style="color:#6b7280;font-size:11px;">Add <code>lat</code> &amp; <code>lng</code> fields to your detection records.</span>`;
      return d;
    };
    notice.addTo(map);
  }
<?php endif; ?>

<?php if ($page === 'analytics'): ?>
(function(){
  const b = document.getElementById('barChart');
  if (!b) return;
  new Chart(b,{type:'bar',data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Organic',data:[28,32,35,40,45,50],backgroundColor:'#4caf5099',borderRadius:4},{label:'Plastic',data:[12,15,18,22,25,28],backgroundColor:'#ff8c0099',borderRadius:4},{label:'Paper',data:[8,10,12,14,16,18],backgroundColor:'#2b6cb099',borderRadius:4},{label:'Metal',data:[5,6,7,8,9,10],backgroundColor:'#9ca3af99',borderRadius:4}]},options:{plugins:{legend:{position:'bottom',labels:{font:{size:12},boxWidth:12}}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,grid:{color:'#f0f4f1'}}},responsive:true,maintainAspectRatio:false}});
})();
<?php endif; ?>

<?php if ($page === 'collection'): ?>
(function(){
  const map = L.map('collection-map').setView([-1.9500,30.0619],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);
  const routes=[{coords:[[-1.9441,30.0619],[-1.9267,30.0653],[-1.9196,30.0524]],color:'#4caf50',name:'Central Zone A'},{coords:[[-1.9355,30.0878],[-1.9489,30.1079],[-1.9100,30.0950]],color:'#2b6cb0',name:'East Zone B'},{coords:[[-1.9831,30.0385],[-1.9750,30.0820],[-1.9622,30.0731]],color:'#ff8c00',name:'South Zone D'}];
  const bins=<?=json_encode(array_values(array_map(fn($b)=>['lat'=>(float)($b['lat']??0),'lng'=>(float)($b['lng']??0),'name'=>$b['name']??'','fill'=>(int)($b['fill']??0),'ai_prediction'=>$b['ai_prediction']??'—'],$bins)))?>;
  bins.forEach(b=>{if(!b.lat||!b.lng)return;const c=b.fill>=80?'#e53e3e':b.fill>=60?'#ff8c00':'#4caf50';L.circleMarker([b.lat,b.lng],{color:'#fff',fillColor:c,fillOpacity:1,radius:8,weight:2}).addTo(map).bindPopup(`<b>${b.name}</b><br>Fill: ${b.fill}%<br>AI: ${b.ai_prediction}`);});
  routes.forEach(r=>L.polyline(r.coords,{color:r.color,weight:3,opacity:.8,dashArray:'8,4'}).addTo(map).bindPopup(r.name));
})();
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>