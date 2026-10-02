<?php
/*
 * میز وقت سفارت — نسخه هاستینگر (یک فایل)
 * این فایل را با نام index.php در یک پوشه روی هاست بگذارید (مثلاً public_html/desk).
 * داده‌ها و فایل پاسپورت‌ها بیرون از public_html در پوشه vfs-desk-data ذخیره می‌شوند.
 */

// ================== تنظیمات — فقط این قسمت را عوض کنید ==================
$CONFIG = [
  'password'          => 'CHANGE-ME',          // رمز ورود به صفحه (حتماً عوض کنید)
  'anthropic_api_key' => '',                   // اختیاری: کلید Claude API برای خواندن خودکار پاسپورت (sk-ant-...)
  'workspace_id'      => '',                   // فقط اگر Claude خطای workspace داد: شناسه workspace (wrkspc_...)
  'model'             => 'claude-sonnet-5-5',  // مدلی که پاسپورت را می‌خواند
  'keep_days'         => 30,                   // پاسپورت‌ها چند روز بعد از آپلود پاک شوند
  'telegram_token'    => '',                   // اختیاری: توکن ربات تلگرام از @BotFather
  'telegram_chat'     => '',                   // شناسه چت هر نفر (یا گروه)، با ویرگول، مثلاً '111,222'. ربات خودش شناسه را می‌گوید
  'telegram_notify'   => true,                 // نوتیف خودکار در گروه (اکانت جدید، مسافر، وقت)
];
// ==========================================================================

error_reporting(0);
ini_set('display_errors', '0');

$pos = strpos(__DIR__, '/public_html');
$DATA = ($pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__)) . '/vfs-desk-data';
if (!is_dir($DATA) && !@mkdir($DATA, 0750, true)) { $DATA = __DIR__ . '/vfs-desk-data'; @mkdir($DATA, 0750, true); }
if (!is_file("$DATA/.htaccess")) @file_put_contents("$DATA/.htaccess", "Require all denied\nDeny from all\n");
if (!is_dir("$DATA/files")) @mkdir("$DATA/files", 0750, true);

session_name('vfsdesk');
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
session_start();

const COLS = ['sims', 'emails', 'portals', 'accounts', 'regs', 'people', 'logs', 'passports'];
const COUNTRY_FA = ['GR'=>'یونان','IT'=>'ایتالیا','FR'=>'فرانسه','CZ'=>'چک','FI'=>'فنلاند','ES'=>'اسپانیا','DE'=>'آلمان','NL'=>'هلند','BE'=>'بلژیک','AT'=>'اتریش','CH'=>'سوئیس','PT'=>'پرتغال','PL'=>'لهستان','HU'=>'مجارستان','SE'=>'سوئد','DK'=>'دانمارک','NO'=>'نروژ','MT'=>'مالت','CY'=>'قبرس','HR'=>'کرواسی','SI'=>'اسلوونی','SK'=>'اسلواکی','LU'=>'لوکزامبورگ','LV'=>'لتونی','LT'=>'لیتوانی','EE'=>'استونی','IS'=>'ایسلند','BG'=>'بلغارستان','RO'=>'رومانی','UK'=>'بریتانیا','CA'=>'کانادا','AU'=>'استرالیا','US'=>'آمریکا'];
const TG_CAP = 5, TG_FRESH = 30;   // Telegram bot: places per account, days an account counts as active
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

function json_out($a, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg, $code = 'invalid_argument', $http = 400) { json_out(['error' => $msg, 'code' => $code], $http); }

// ---------- web app manifest (install to phone home screen) ----------
if (isset($_GET['manifest'])) {
  header('Content-Type: application/manifest+json; charset=utf-8');
  echo json_encode(['name' => 'میز وقت سفارت', 'short_name' => 'میز سفارت', 'start_url' => './', 'scope' => './', 'display' => 'standalone',
    'orientation' => 'portrait', 'dir' => 'rtl', 'lang' => 'fa', 'background_color' => '#f3f5fc', 'theme_color' => '#053F5C',
    'icons' => [['src' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMAAAADACAIAAADdvvtQAAACdklEQVR42u3cu1HDUBRFUVmDU1pyQBuqgWLoh6aogIzAAYF/0j1n7RgGyW/p6sljczpftkW6tdVLIIAEkAASQBJAAkgACSAJIAEkgASQBJAAEkACSAJIAAkgASSAJIAEkAASQBJAAkgACSAJIAEkgASQBJAAEkACSABJAAkgASSAJIAEkAASQBJAAkgACSAJIAEkgASQBJAAEkACSI29eQmu+vn8/v8H3r8+vEp/nc6XjZh7fr3cUy+gO92Q1Avo4XSaGXUBeiqdTkYtgF5Gp43RSk/e3wUoZxXjDSXfwg61eKm3s5UeowigGasVaWilx7EBNGmFwgyt9DhOgARQ2WUdM4RWehxzO6C5KxFgyB5I3YCmX8TTj98EUjGgjH3o6LMwgdQKKOn93LnnYgIJIAHUfP8afUYmkAASQAJIANlvOi8TSAAJIAEkASSABJAAkgASQALo3lL/29fE8zKBBJAAEkACyH7TGZlAAkgAmfkd52ICqRhQxhAafRYmkLoBTR9C04/fBFI9oLkXccAeLmQCTVyJjCeAnFvYrPWIeRPLHkgATbusk95DT5tAx1+bsM8RBN7CjrxCeZ9CydwDHXOdIr8LELuJPtpqpX6T5HS+bNmPCbv/269UOi2P8fuuX7aepeR9oL1WMV5PxS1sl9tZA51GQC9g1EOnF9CTGLXRaQf0KEmdbgC63VO5GIDkMV4ACSAJIAEkgASQAJIAEkACSABJAAkgASSAJIAEkAASQBJAAkgACSABJAEkgASQAJIAEkACSABJAAkgASSAJIAEkAASQAJIAkgACSABJAEkgASQAJIAEkACSABJAAkgASSApGX5BZpnizVokL3rAAAAAElFTkSuQmCC', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => 'data:image/png;base64,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', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// ---------- login / logout ----------
$pwNotSet = ($CONFIG['password'] === 'CHANGE-ME' || $CONFIG['password'] === '');
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: ?'); exit; }
$loginError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
  if (!$pwNotSet && hash_equals((string)$CONFIG['password'], (string)$_POST['login_pw'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: ?'); exit; }
  sleep(1); $loginError = true;
}
$authed = !empty($_SESSION['ok']);
if (isset($_GET['tg'])) tg_webhook();
if (isset($_GET['tgsetup']) && $authed) tg_setup();

// ---------- storage ----------
function pdo() {
  static $p; global $DATA;
  if (!$p) {
    $p = new PDO('sqlite:' . $DATA . '/desk.sqlite');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->exec('PRAGMA journal_mode=WAL');
    $p->exec('CREATE TABLE IF NOT EXISTS docs(col TEXT NOT NULL, id TEXT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(col, id))');
    $p->exec('CREATE TABLE IF NOT EXISTS files(id TEXT PRIMARY KEY, type TEXT, size INTEGER, created TEXT)');
    if ((int)$p->query('SELECT COUNT(*) FROM docs')->fetchColumn() === 0) seed($p);
    if (!file_exists($DATA . '/.m_us')) { $n = (int)$p->query("SELECT COUNT(*) FROM docs WHERE col='portals'")->fetchColumn(); $p->prepare('INSERT OR IGNORE INTO docs(col, id, data) VALUES(?,?,?)')->execute(['portals', 'US', json_encode(['code' => 'US', 'order' => $n])]); @touch($DATA . '/.m_us'); }
  }
  return $p;
}
function seed($p) {
  $d = date('Y-m-d'); $ins = $p->prepare('INSERT OR IGNORE INTO docs(col, id, data) VALUES(?,?,?)');
  foreach (['GR', 'IT', 'FR', 'CZ', 'FI', 'US'] as $i => $c) $ins->execute(['portals', $c, json_encode(['code' => $c, 'order' => $i])]);
  foreach (['+971557223724', '+971558014368', '+971557048118', '+971554898741', '+971559488451'] as $i => $n) { $id = sprintf('SIM-%02d', $i + 1); $ins->execute(['sims', $id, json_encode(['code' => $id, 'number' => $n, 'operator' => 'du', 'expiry' => '', 'status' => 'active', 'note' => '', 'createdAt' => $d])]); }
  foreach (['sorousht1987@gmail.com', 'golimoli5533@gmail.com', 'solimoli5533@gmail.com', 'alidarabi5533@gmail.com', 'soroushtavakoli87@gmail.com'] as $i => $a) { $id = sprintf('EM-%02d', $i + 1); $ins->execute(['emails', $id, json_encode(['code' => $id, 'address' => $a, 'status' => 'active', 'note' => '', 'createdAt' => $d])]); }
}
function body() { $b = json_decode((string)file_get_contents('php://input'), true); return is_array($b) ? $b : []; }
function vcol($c) { if (!in_array($c, COLS, true)) fail('bad collection'); return $c; }
function vid($i) { if (!is_string($i) || !preg_match('/^[A-Za-z0-9_\-.~:@+]{1,200}$/', $i)) fail('bad id'); return $i; }
function getdoc($c, $i) { $s = pdo()->prepare('SELECT data FROM docs WHERE col=? AND id=?'); $s->execute([$c, $i]); $r = $s->fetchColumn(); return $r === false ? null : json_decode($r, true); }
function putdoc($c, $i, $d) { pdo()->prepare('INSERT OR REPLACE INTO docs(col, id, data) VALUES(?,?,?)')->execute([$c, $i, json_encode($d, JSON_UNESCAPED_UNICODE)]); }
function deldoc($c, $i) { pdo()->prepare('DELETE FROM docs WHERE col=? AND id=?')->execute([$c, $i]); }
function delfile($id) { global $DATA; if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) return; @unlink("$DATA/files/$id"); pdo()->prepare('DELETE FROM files WHERE id=?')->execute([$id]); }
function purge() {
  global $CONFIG;
  $limit = date('Y-m-d', time() - (int)$CONFIG['keep_days'] * 86400);
  foreach (pdo()->query("SELECT id, data FROM docs WHERE col='passports'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode($r['data'], true);
    if (!empty($d['uploadedAt']) && $d['uploadedAt'] <= $limit) { if (!empty($d['assetId'])) delfile($d['assetId']); deldoc('passports', $r['id']); }
  }
  // finished travellers («اتمام کار») stay visible for 14 days, then go
  $done = date('Y-m-d', time() - 14 * 86400);
  foreach (pdo()->query("SELECT id, data FROM docs WHERE col='people'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode($r['data'], true);
    if (!in_array($d['status'] ?? '', ['removed', 'done'], true)) continue;
    if (empty($d['doneAt'])) { $d['doneAt'] = date('Y-m-d'); putdoc('people', $r['id'], $d); }
    elseif ($d['doneAt'] <= $done) deldoc('people', $r['id']);
  }
}

// ---------- Claude API: read passports ----------
function anthropic($payload) {
  global $CONFIG;
  if (!function_exists('curl_init')) return [null, 'curl روی هاست فعال نیست'];
  $ch = curl_init('https://api.anthropic.com/v1/messages');
  $hdr = ['content-type: application/json', 'x-api-key: ' . $CONFIG['anthropic_api_key'], 'anthropic-version: 2023-06-01'];
  if (!empty($CONFIG['workspace_id'])) $hdr[] = 'anthropic-workspace-id: ' . $CONFIG['workspace_id'];
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => $hdr,
    CURLOPT_POSTFIELDS => json_encode($payload)]);
  $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
  if ($r === false) return [null, 'اتصال به سرویس خواندن برقرار نشد' . ($cerr ? " ($cerr)" : '')];
  $j = json_decode($r, true);
  if ($code !== 200) {
    $msg = $j['error']['message'] ?? ('HTTP ' . $code);
    if ($code === 401) $msg = 'کلید API درست نیست';
    elseif (stripos($msg, 'workspace') !== false) $msg = 'کلید سرویس به یک workspace وصل نیست؛ از داخل یک workspace کلید بسازید یا workspace_id را در تنظیمات فایل بگذارید';
    elseif ($code === 400 && stripos($msg, 'credit') !== false) $msg = 'اعتبار سرویس خواندن تمام شده';
    elseif ($code === 429) $msg = 'درخواست زیاد؛ چند ثانیه بعد دوباره';
    elseif ($code === 529 || $code >= 500) $msg = 'سرویس خواندن موقتاً شلوغ است؛ دوباره امتحان کنید';
    return [null, $msg];
  }
  return [$j, ''];
}
function image_for_api($path, $type) {
  $bytes = file_get_contents($path);
  if ($type !== 'application/pdf' && function_exists('imagecreatefromstring')) {
    $info = @getimagesizefromstring($bytes);
    if (strlen($bytes) > 3500000 || ($info && max($info[0], $info[1]) > 2400)) {
      $im = @imagecreatefromstring($bytes);
      if ($im) {
        $w = imagesx($im); $h = imagesy($im); $s = min(1, 2000 / max($w, $h));
        $dst = imagecreatetruecolor((int)($w * $s), (int)($h * $s));
        imagecopyresampled($dst, $im, 0, 0, 0, 0, (int)($w * $s), (int)($h * $s), $w, $h);
        ob_start(); imagejpeg($dst, null, 85); $bytes = ob_get_clean(); $type = 'image/jpeg';
      }
    }
  }
  return [base64_encode($bytes), $type];
}
function ai_read($ids) {
  global $CONFIG, $DATA;
  if ($CONFIG['anthropic_api_key'] === '') fail('خواندن خودکار فعال نیست؛ کلید API در تنظیمات فایل خالی است', 'not_granted');
  @set_time_limit(600);
  $ok = 0; $errors = [];
  $ids = array_slice(array_values(array_filter(is_array($ids) ? $ids : [], 'is_string')), 0, 40);
  $prompt = 'This is a passport scan. Read these fields of the passport data page, preferring the machine-readable zone (MRZ) at the bottom and checking it against the printed text: given name(s), surname, passport number, nationality, and date of expiry. Return nothing else. Reply with only one JSON object like {"first":"GIVEN NAMES","last":"SURNAME","passportNo":"X12345678","nationality":"IRAN","expiry":"2031-05-20"}. Names and nationality in Latin capital letters (nationality as the country name in English); expiry as YYYY-MM-DD. Use "" for a field you cannot read or if this is not a passport.';
  foreach ($ids as $pid) {
    $d = getdoc('passports', $pid);
    if (!$d || empty($d['assetId'])) { $errors[$pid] = 'فایل پیدا نشد'; continue; }
    $s = pdo()->prepare('SELECT type FROM files WHERE id=?'); $s->execute([$d['assetId']]); $t = $s->fetchColumn();
    $path = "$DATA/files/" . $d['assetId'];
    if (!$t || !is_file($path)) { $errors[$pid] = 'فایل پیدا نشد'; continue; }
    [$b64, $t2] = image_for_api($path, $t);
    $block = $t2 === 'application/pdf'
      ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]]
      : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $t2, 'data' => $b64]];
    $res = null; $err = '';
    for ($try = 0; $try < 2 && !$res; $try++) { [$res, $err] = anthropic(['model' => $CONFIG['model'], 'max_tokens' => 400, 'messages' => [['role' => 'user', 'content' => [$block, ['type' => 'text', 'text' => $prompt]]]]]); if (!$res && $try === 0) sleep(1); }
    if (!$res) { $errors[$pid] = $err; continue; }
    $txt = ''; foreach (($res['content'] ?? []) as $blk) if (($blk['type'] ?? '') === 'text') $txt .= $blk['text'];
    $r = preg_match('/\{.*\}/s', $txt, $m) ? json_decode($m[0], true) : null;
    if (!is_array($r)) { $errors[$pid] = 'پاسخ قابل خواندن نبود'; continue; }
    $first = trim((string)($r['first'] ?? '')); $last = trim((string)($r['last'] ?? ''));
    $no = strtoupper(trim((string)($r['passportNo'] ?? ''))); $exp = trim((string)($r['expiry'] ?? ''));
    if ($first === '' && $last === '' && $no === '') { $errors[$pid] = 'چیزی روی تصویر خوانده نشد'; continue; }
    putdoc('passports', $pid, array_merge($d, ['firstName' => $first, 'lastName' => $last, 'name' => trim("$first $last"), 'passportNo' => $no,
      'nationality' => trim((string)($r['nationality'] ?? '')), 'expiry' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp : '']));
    $ok++;
  }
  json_out(['ok' => 1, 'updated' => $ok, 'failed' => count($errors), 'errors' => (object)$errors]);
}

// ---------- Claude API: read a VFS appointment confirmation (file or pasted email text) ----------
function ai_read_appt($fileId, $text) {
  global $CONFIG, $DATA;
  if ($CONFIG['anthropic_api_key'] === '') fail('خواندن خودکار فعال نیست؛ کلید API در تنظیمات فایل خالی است', 'not_granted');
  @set_time_limit(180);
  $content = [];
  if (is_string($fileId) && preg_match('/^[a-f0-9]{32}$/', $fileId)) {
    $s = pdo()->prepare('SELECT type FROM files WHERE id=?'); $s->execute([$fileId]); $t = $s->fetchColumn();
    $path = "$DATA/files/$fileId";
    if (!$t || !is_file($path)) fail('فایل پیدا نشد');
    [$b64, $t2] = image_for_api($path, $t);
    $content[] = $t2 === 'application/pdf'
      ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]]
      : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $t2, 'data' => $b64]];
  } elseif (is_string($text) && trim($text) !== '') {
    $content[] = ['type' => 'text', 'text' => "Email text:\n" . mb_substr($text, 0, 20000)];
  } else fail('فایل یا متنی برای خواندن نیست');
  $content[] = ['type' => 'text', 'text' => 'This is a visa appointment confirmation (usually from VFS Global): a letter, an email, or a screenshot. Extract the appointment details. Reply with only one JSON object like {"country":"GR","date":"2026-10-12","time":"10:30","center":"Dubai","reference":"GRDU123456","applicants":[{"name":"GIVEN NAMES SURNAME","passportNo":"X12345678"}]}. country is the two-letter code of the destination country whose visa this is (use UK for the United Kingdom). date is YYYY-MM-DD, time is 24-hour HH:MM. List every applicant named. Use an empty string for anything not shown. If this is not an appointment confirmation, reply {"error":"not_appointment"}.'];
  $res = null; $err = '';
  for ($try = 0; $try < 2 && !$res; $try++) { [$res, $err] = anthropic(['model' => $CONFIG['model'], 'max_tokens' => 800, 'messages' => [['role' => 'user', 'content' => $content]]]); if (!$res && $try === 0) sleep(1); }
  if (is_string($fileId) && preg_match('/^[a-f0-9]{32}$/', $fileId)) delfile($fileId);
  if (!$res) fail($err, 'upstream_error', 502);
  $txt = ''; foreach (($res['content'] ?? []) as $blk) if (($blk['type'] ?? '') === 'text') $txt .= $blk['text'];
  $r = preg_match('/\{.*\}/s', $txt, $m) ? json_decode($m[0], true) : null;
  if (!is_array($r)) fail('پاسخ قابل خواندن نبود', 'upstream_error', 502);
  if (!empty($r['error'])) fail('این فایل نامه تأیید وقت به نظر نمی‌رسد', 'invalid_argument');
  $str = fn($k) => trim((string)($r[$k] ?? ''));
  $apps = [];
  foreach ((is_array($r['applicants'] ?? null) ? $r['applicants'] : []) as $a) if (is_array($a)) $apps[] = ['name' => trim((string)($a['name'] ?? '')), 'passportNo' => strtoupper(trim((string)($a['passportNo'] ?? '')))];
  json_out(['ok' => 1, 'appt' => ['country' => strtoupper($str('country')), 'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str('date')) ? $str('date') : '',
    'time' => preg_match('/^\d{1,2}:\d{2}$/', $str('time')) ? $str('time') : '', 'center' => $str('center'), 'reference' => $str('reference'), 'applicants' => $apps]]);
}

// ---------- Telegram bot: menu of countries / emails / phones / free places, plus notifications ----------
function tg_api($method, $params) {
  global $CONFIG;
  if ($CONFIG['telegram_token'] === '' || !function_exists('curl_init')) return null;
  $ch = curl_init('https://api.telegram.org/bot' . $CONFIG['telegram_token'] . '/' . $method);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_HTTPHEADER => ['content-type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE)]);
  $r = curl_exec($ch); curl_close($ch);
  return $r === false ? null : json_decode($r, true);
}
function tg_secret() { global $CONFIG; return substr(hash('sha256', 'miz:' . $CONFIG['telegram_token']), 0, 32); }
function tg_chats() { global $CONFIG; return array_values(array_filter(array_map('trim', explode(',', (string)$CONFIG['telegram_chat'])), 'strlen')); }
function tg_all() { $o = []; foreach (pdo()->query("SELECT col, id, data FROM docs WHERE col IN ('sims','emails','portals','accounts','regs','people')")->fetchAll(PDO::FETCH_ASSOC) as $r) $o[$r['col']][$r['id']] = json_decode($r['data'], true) + ['id' => $r['id']]; return $o; }
function tg_flag($pc) { $pc = $pc === 'UK' ? 'GB' : $pc; if (!preg_match('/^[A-Z]{2}$/', $pc)) return '🏳'; return mb_chr(0x1F1E6 + ord($pc[0]) - 65) . mb_chr(0x1F1E6 + ord($pc[1]) - 65); }
function tg_cname($pc) { return COUNTRY_FA[$pc] ?? $pc; }
function tg_fa($n) { return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function tg_phone($n) { if (preg_match('/^\+971(\d{2})(\d{3})(\d{4})$/', (string)$n, $m)) return "0$m[1] $m[2] $m[3]"; return (string)$n; }
function tg_h($t) { return htmlspecialchars((string)$t, ENT_QUOTES); }
function tg_st($a) { // same rule as the site: active = login confirmed in the last 30 days
  if (!$a || empty($a['status']) || $a['status'] === 'unknown') return 'unused';
  if ($a['status'] === 'none') return 'none';
  $d = empty($a['lastVerified']) ? null : (int)floor((strtotime(date('Y-m-d')) - strtotime($a['lastVerified'])) / 86400);
  return $d !== null && $d <= TG_FRESH ? 'active' : 'check';
}
function tg_occ($all, $accId) { $n = 0; foreach ($all['people'] ?? [] as $p) if (($p['accountId'] ?? '') === $accId && !in_array($p['status'] ?? '', ['removed', 'done'], true)) $n++; return $n; }
function tg_bar($n) { return str_repeat('▰', min($n, TG_CAP)) . str_repeat('▱', max(0, TG_CAP - $n)); }
// live accounts whose email still exists in the desk (accounts of deleted/inactive emails are ignored)
function tg_live($all) { return array_filter($all['accounts'] ?? [], fn($a) => in_array(tg_st($a), ['active', 'check'], true) && isset($all['emails'][$a['emailId'] ?? '']) && ($all['emails'][$a['emailId']]['status'] ?? '') !== 'inactive'); }
function tg_portals($all) { $p = $all['portals'] ?? []; uasort($p, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99)); return array_keys($p); }
function tg_acc_line($all, $a) {
  $e = $all['emails'][$a['emailId']]['address'] ?? $a['emailId']; $m = !empty($a['simId']) ? tg_phone($all['sims'][$a['simId']]['number'] ?? '') : 'بدون شماره';
  $n = tg_occ($all, $a['id']);
  return '<code>' . tg_h($e) . '</code> + <code>' . tg_h($m) . '</code>' . "\n   " . tg_bar($n) . ' ' . tg_fa($n) . '/' . tg_fa(TG_CAP) . ($n >= TG_CAP ? ' (پر)' : '') . (tg_st($a) === 'check' ? ' · ⚠ چک شود' : '');
}
function tg_view($key) {
  $all = tg_all(); $back = [[['text' => '← منو', 'callback_data' => 'm']]];
  // Simple menu: countries → that country's accounts (email · phone · free places) → the travellers on one account
  if ($key === 'm' || $key === 'c' || $key === 'f') {
    $rows = []; foreach (tg_portals($all) as $pc) $rows[] = ['text' => tg_flag($pc) . ' ' . tg_cname($pc), 'callback_data' => 'c:' . $pc];
    $kb = array_chunk($rows, 3); $kb[] = [['text' => '✨ اکانت پیشنهادی', 'callback_data' => 'n']];
    return ['🌍 <b>کدام کشور؟</b>', $kb];
  }
  // suggested new accounts: free email + free phone pairs for one country (same pairing as the site's planner)
  if ($key === 'n') {
    $rows = []; foreach (tg_portals($all) as $pc) $rows[] = ['text' => tg_flag($pc) . ' ' . tg_cname($pc), 'callback_data' => 'n:' . $pc];
    $kb = array_chunk($rows, 3); $kb[] = [['text' => '← کشورها', 'callback_data' => 'm']];
    return ['✨ <b>اکانت پیشنهادی برای کدام کشور؟</b>', $kb];
  }
  if (preg_match('/^n:([A-Z]{2})$/', $key, $m)) {
    $pc = $m[1]; $live = array_filter(tg_live($all), fn($a) => $a['portal'] === $pc);
    $fe = []; foreach ($all['emails'] ?? [] as $e) { if (($e['status'] ?? '') === 'inactive') continue; if (in_array(tg_st($all['accounts']["{$pc}__{$e['id']}"] ?? null), ['unused', 'none'], true)) $fe[] = $e; }
    $fs = []; foreach ($all['sims'] ?? [] as $sm) { if (($sm['status'] ?? '') === 'inactive') continue; $busy = false; foreach ($live as $a) if (($a['simId'] ?? '') === $sm['id']) $busy = true;
      if (!$busy && ($all['regs']["{$pc}__{$sm['id']}"]['status'] ?? '') !== 'registered') $fs[] = $sm; }
    usort($fe, fn($a, $b) => strcmp($a['id'], $b['id'])); usort($fs, fn($a, $b) => strcmp($a['id'], $b['id']));
    $n = min(4, count($fe), count($fs)); $lines = [];
    for ($i = 0; $i < $n; $i++) $lines[] = tg_fa($i + 1) . ") 📧 <code>" . tg_h($fe[$i]['address']) . "</code>\n    📱 <code>" . tg_h(tg_phone($fs[$i]['number'])) . '</code>';
    $room = 0; foreach ($live as $a) if (tg_st($a) === 'active' && tg_occ($all, $a['id']) < TG_CAP) $room++;
    $t = '✨ <b>اکانت پیشنهادی · ' . tg_flag($pc) . ' ' . tg_cname($pc) . "</b>\n" . ($room ? '🟢 ' . tg_fa($room) . " اکانت موجود هنوز جای خالی دارد\n" : '') . "\n"
      . ($lines ? implode("\n\n", $lines) : '— ' . (!$fe ? 'ایمیل آزاد' : 'شماره آزاد') . ' برای ' . tg_cname($pc) . ' نمانده؛ در سایت اضافه کنید');
    return [$t, [[['text' => '← کشورها', 'callback_data' => 'n'], ['text' => 'اکانت‌های ' . tg_cname($pc), 'callback_data' => 'c:' . $pc]]]];
  }
  if (preg_match('/^c:([A-Z]{2})$/', $key, $m)) {
    $pc = $m[1]; $accs = array_filter(tg_live($all), fn($a) => $a['portal'] === $pc); $kb = [];
    foreach ($accs as $a) {
      $e = $all['emails'][$a['emailId']]['address'] ?? $a['emailId']; $ph = !empty($a['simId']) ? tg_phone($all['sims'][$a['simId']]['number'] ?? '') : '—';
      $free = max(0, TG_CAP - tg_occ($all, $a['id']));
      $kb[] = [['text' => explode('@', $e)[0] . ' · ' . str_replace(' ', '', $ph) . ' · ' . ($free ? tg_fa($free) . ' خالی' : 'پر'), 'callback_data' => 'a:' . $a['id']]];
    }
    $kb[] = [['text' => '← کشورها', 'callback_data' => 'm']];
    return [tg_flag($pc) . ' <b>' . tg_cname($pc) . '</b>' . "\n" . ($accs ? 'روی هر اکانت بزنید تا مسافرهایش را ببینید' : 'هنوز اکانتی ساخته نشده'), $kb];
  }
  if (preg_match('/^a:([A-Za-z0-9_\-.~:@+]+)$/', $key, $m)) {
    $a = $all['accounts'][$m[1]] ?? null; if (!$a) return ['این اکانت دیگر نیست', [[['text' => '← کشورها', 'callback_data' => 'm']]]];
    $pc = $a['portal']; $e = $all['emails'][$a['emailId']]['address'] ?? $a['emailId']; $ph = !empty($a['simId']) ? tg_phone($all['sims'][$a['simId']]['number'] ?? '') : 'بدون شماره';
    $ppl = array_values(array_filter($all['people'] ?? [], fn($p) => ($p['accountId'] ?? '') === $a['id'] && !in_array($p['status'] ?? '', ['removed', 'done'], true)));
    $free = max(0, TG_CAP - count($ppl)); $lines = [];
    foreach ($ppl as $i => $p) $lines[] = tg_fa($i + 1) . '. <b>' . tg_h($p['name'] ?? '') . '</b> — ' . (($p['status'] ?? '') === 'booked' ? '📅 ' . tg_h(trim(($p['apptDate'] ?? '') . ' ' . ($p['apptTime'] ?? ''))) : 'Waitlist');
    $t = tg_flag($pc) . ' <b>' . tg_cname($pc) . "</b>\n📧 <code>" . tg_h($e) . "</code>\n📱 <code>" . tg_h($ph) . "</code>\n" . ($free ? '🟢 ' . tg_fa($free) . ' جای خالی از ' . tg_fa(TG_CAP) : '🔴 پر') . "\n━━━━━━━━━━\n" . ($lines ? implode("\n", $lines) : 'هنوز مسافری روی این اکانت نیست');
    return [$t, [[['text' => '← اکانت‌های ' . tg_cname($pc), 'callback_data' => 'c:' . $pc], ['text' => 'کشورها', 'callback_data' => 'm']]]];
  }
  if ($key === 'e' || $key === 's') {
    $col = $key === 'e' ? 'emails' : 'sims'; $rows = [];
    foreach ($all[$col] ?? [] as $x) if (($x['status'] ?? '') !== 'inactive') $rows[] = ['text' => $key === 'e' ? $x['address'] : tg_phone($x['number']), 'callback_data' => $key . ':' . $x['id']];
    $kb = array_chunk($rows, $key === 'e' ? 1 : 2); $kb[] = $back[0];
    return [$key === 'e' ? '📧 <b>کدام ایمیل؟</b>' : '📱 <b>کدام شماره؟</b>', $kb];
  }
  if (preg_match('/^(e|s):([A-Za-z0-9_\-]+)$/', $key, $m)) {
    $isE = $m[1] === 'e'; $id = $m[2]; $x = $all[$isE ? 'emails' : 'sims'][$id] ?? null; if (!$x) return ['پیدا نشد', $back];
    $lines = [];
    foreach (tg_live($all) as $a) if (($isE ? $a['emailId'] : ($a['simId'] ?? '')) === $id) {
      $n = tg_occ($all, $a['id']); $other = $isE ? (!empty($a['simId']) ? tg_phone($all['sims'][$a['simId']]['number'] ?? '') : 'بدون شماره') : ($all['emails'][$a['emailId']]['address'] ?? '');
      $lines[] = tg_flag($a['portal']) . ' ' . tg_cname($a['portal']) . ' · <code>' . tg_h($other) . '</code> · ' . tg_fa($n) . '/' . tg_fa(TG_CAP) . ($n >= TG_CAP ? ' (پر)' : '');
    }
    if (!$isE) { $dup = []; foreach ($all['regs'] ?? [] as $r) if (($r['simId'] ?? '') === $id && ($r['status'] ?? '') === 'registered') { $has = false; foreach (tg_live($all) as $a) if ($a['portal'] === $r['portal'] && ($a['simId'] ?? '') === $id) $has = true; if (!$has) $dup[] = tg_flag($r['portal']) . ' ' . tg_cname($r['portal']); }
      if ($dup) $lines[] = '❌ تکراری در VFS: ' . implode('، ', $dup); }
    $title = $isE ? '📧 <code>' . tg_h($x['address']) . '</code>' : '📱 <code>' . tg_h(tg_phone($x['number'])) . '</code>';
    return [$title . ' روی ' . tg_fa(count(array_filter($lines, fn($l) => strpos($l, '❌') !== 0))) . " کشور:\n" . ($lines ? implode("\n", $lines) : 'هنوز روی هیچ کشوری نیست'), [[['text' => $isE ? '← ایمیل‌ها' : '← شماره‌ها', 'callback_data' => $m[1]], ['text' => 'منو', 'callback_data' => 'm']]]];
  }
  return tg_view('m');
}
function tg_webhook() {
  if (!hash_equals(tg_secret(), (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) { http_response_code(403); exit; }
  $u = json_decode(file_get_contents('php://input'), true) ?: [];
  $cb = $u['callback_query'] ?? null; $msg = $cb['message'] ?? ($u['message'] ?? null);
  $chat = (string)($msg['chat']['id'] ?? ''); if ($chat === '') exit;
  if (!in_array($chat, tg_chats(), true)) {
    if ($cb) tg_api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
    else tg_api('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML', 'text' => "این چت هنوز اجازه ندارد.\nشناسه این چت: <code>$chat</code>\nآن را در telegram_chat بالای فایل index.php بگذارید."]);
    exit;
  }
  try {
    if ($cb) { [$t, $kb] = tg_view((string)($cb['data'] ?? 'm')); tg_api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
      tg_api('editMessageText', ['chat_id' => $chat, 'message_id' => $msg['message_id'], 'text' => $t, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]); }
    elseif (preg_match('#^/(start|menu|m)\b#', (string)($msg['text'] ?? ''))) { [$t, $kb] = tg_view('m'); tg_api('sendMessage', ['chat_id' => $chat, 'text' => $t, 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]); }
  } catch (Throwable $e) {}
  exit;
}
function tg_setup() {
  global $CONFIG;
  header('Content-Type: text/html; charset=utf-8');
  if ($CONFIG['telegram_token'] === '') { echo '<p dir="rtl">اول telegram_token را بالای فایل بگذارید.</p>'; exit; }
  $url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . '?tg=1';
  $r = tg_api('setWebhook', ['url' => $url, 'secret_token' => tg_secret(), 'allowed_updates' => ['message', 'callback_query']]);
  tg_api('setMyCommands', ['commands' => [['command' => 'menu', 'description' => 'کشورها و اکانت‌ها']]]);
  echo '<p dir="rtl" style="font:16px Tahoma">' . (!empty($r['ok']) ? '✅ ربات وصل شد. در گروه /menu بزنید.' : '❌ وصل نشد: ' . tg_h($r['description'] ?? 'اتصال به تلگرام برقرار نشد')) . '</p>'; exit;
}
// one-line notification to the team chat when something worth knowing changes
function tg_notify_change($c, $id, $old, $new) {
  global $CONFIG;
  if ($CONFIG['telegram_token'] === '' || empty($CONFIG['telegram_notify']) || !tg_chats()) return;
  try {
    $all = tg_all(); $t = '';
    if ($c === 'accounts' && $new && tg_st($new) === 'active' && (!$old || !in_array(tg_st($old), ['active', 'check'], true))) {
      $t = tg_flag($new['portal']) . ' اکانت جدید ' . tg_cname($new['portal']) . ': ' . tg_acc_line($all, $new + ['id' => $id]);
    } elseif ($c === 'people' && $new) {
      $acc = $all['accounts'][$new['accountId'] ?? ''] ?? null; $pc = $new['portal'] ?? '';
      if (!$old) $t = '👤 ' . tg_flag($pc) . ' <b>' . tg_h($new['name'] ?? '') . '</b> اضافه شد' . ($acc ? "\n" . tg_acc_line($all, $acc + ['id' => $new['accountId']]) : '');
      elseif (($new['status'] ?? '') === 'booked' && ($old['status'] ?? '') !== 'booked') $t = '📅 ' . tg_flag($pc) . ' وقت گرفته شد: <b>' . tg_h($new['name'] ?? '') . '</b> · ' . tg_h(trim(($new['apptDate'] ?? '') . ' ' . ($new['apptTime'] ?? '')));
      elseif (in_array($new['status'] ?? '', ['removed', 'done'], true) && !in_array($old['status'] ?? '', ['removed', 'done'], true) && $acc) $t = '🟢 ' . tg_flag($pc) . ' یک جا آزاد شد' . "\n" . tg_acc_line($all, $acc + ['id' => $new['accountId']]);
    }
    if ($t !== '') foreach (tg_chats() as $chat) tg_api('sendMessage', ['chat_id' => $chat, 'text' => $t, 'parse_mode' => 'HTML']);
  } catch (Throwable $e) {}
}

// ---------- API ----------
if (isset($_GET['api'])) {
  if (!$authed) fail('login', 'unauthenticated', 401);
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_X_REQ'] ?? '') !== '1') fail('bad request');
  session_write_close();
  try {
    switch ($_GET['api']) {
      case 'all':
        purge();
        $out = []; foreach (COLS as $c) $out[$c] = [];
        foreach (pdo()->query('SELECT col, id, data FROM docs')->fetchAll(PDO::FETCH_ASSOC) as $r) if (isset($out[$r['col']])) $out[$r['col']][$r['id']] = json_decode($r['data']);
        foreach ($out as $c => $v) $out[$c] = (object)$v;
        json_out(['ok' => 1, 'ai' => $CONFIG['anthropic_api_key'] !== '', 'data' => $out]);
      case 'set':
        $b = body(); $c = vcol($b['col'] ?? ''); $i = vid($b['id'] ?? '');
        if (!is_array($b['data'] ?? null)) fail('bad data');
        $old = in_array($c, ['accounts', 'people'], true) ? getdoc($c, $i) : null;
        putdoc($c, $i, $b['data']); if ($old !== null || in_array($c, ['accounts', 'people'], true)) tg_notify_change($c, $i, $old, $b['data']); json_out(['ok' => 1]);
      case 'update':
        $b = body(); $c = vcol($b['col'] ?? ''); $i = vid($b['id'] ?? '');
        if (!is_array($b['data'] ?? null)) fail('bad data');
        $cur = getdoc($c, $i); if ($cur === null) fail('not found');
        $new = array_merge($cur, $b['data']); putdoc($c, $i, $new); tg_notify_change($c, $i, $cur, $new); json_out(['ok' => 1, 'doc' => $new]);
      case 'delete':
        $b = body(); $c = vcol($b['col'] ?? ''); $i = vid($b['id'] ?? '');
        if (in_array($c, ['sims', 'emails'], true) && ($b['confirm'] ?? '') !== 'CONFIRMED:' . $i) fail('حذف شماره یا ایمیل فقط با تأیید صریح انجام می‌شود', 'invalid_argument', 403);
        deldoc($c, $i); json_out(['ok' => 1]);
      case 'upload':
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) fail('آپلود نشد', 'upstream_error');
        $f = $_FILES['file'];
        if ($f['size'] > 20 * 1024 * 1024) fail('بیش از ۲۰ مگابایت', 'too_large');
        $type = '';
        if (class_exists('finfo')) $type = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        elseif (function_exists('mime_content_type')) $type = mime_content_type($f['tmp_name']);
        if (!in_array($type, ALLOWED_TYPES, true)) fail('فرمت قبول نیست', 'unsupported_type');
        $id = bin2hex(random_bytes(16));
        if (!move_uploaded_file($f['tmp_name'], "$DATA/files/$id")) fail('ذخیره نشد', 'upstream_error', 500);
        pdo()->prepare('INSERT INTO files(id, type, size, created) VALUES(?,?,?,?)')->execute([$id, $type, $f['size'], date('c')]);
        json_out(['ok' => 1, 'id' => $id, 'type' => $type, 'size' => $f['size']]);
      case 'file':
        $id = (string)($_GET['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { http_response_code(404); exit; }
        $s = pdo()->prepare('SELECT type FROM files WHERE id=?'); $s->execute([$id]); $t = $s->fetchColumn();
        $path = "$DATA/files/$id";
        if (!$t || !is_file($path)) { http_response_code(404); exit; }
        header('Content-Type: ' . $t); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline'); header('Content-Length: ' . filesize($path));
        readfile($path); exit;
      case 'deletefile':
        $b = body(); delfile($b['id'] ?? ''); json_out(['ok' => 1]);
      case 'read':
        $b = body(); ai_read($b['ids'] ?? []);
      case 'readappt':
        $b = body(); ai_read_appt($b['fileId'] ?? null, $b['text'] ?? null);
      case 'export':
        $all = []; foreach (pdo()->query("SELECT col, id, data FROM docs WHERE col IN ('logs','emails','sims')")->fetchAll(PDO::FETCH_ASSOC) as $r) $all[$r['col']][$r['id']] = json_decode($r['data'], true);
        $names = ['login_ok'=>'ورود موفق','no_account'=>'اکانت پاک شده بود','created'=>'اکانت ساخته شد','phone_taken'=>'شماره تکراری بود','email_taken'=>'ایمیل قبلاً اکانت داشت','added'=>'مسافر اضافه شد','booked'=>'وقت گرفته شد','status'=>'تغییر وضعیت مسافر','edit'=>'ویرایش اکانت','upload'=>'پاسپورت آپلود شد'];
        $logs = array_values($all['logs'] ?? []); usort($logs, function ($a, $b) { return strcmp($b['at'] ?? '', $a['at'] ?? ''); });
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="vfs-log-' . date('Y-m-d') . '.csv"');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['date', 'event', 'country', 'email', 'phone', 'note']);
        foreach ($logs as $l) fputcsv($o, [$l['date'] ?? '', $names[$l['type'] ?? ''] ?? ($l['type'] ?? ''), COUNTRY_FA[$l['portal'] ?? ''] ?? ($l['portal'] ?? ''), $all['emails'][$l['emailId'] ?? '']['address'] ?? '', $all['sims'][$l['simId'] ?? '']['number'] ?? '', $l['note'] ?? '']);
        fclose($o); exit;
      case 'backup':
        pdo()->exec('PRAGMA wal_checkpoint(FULL)');
        header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="desk-backup-' . date('Y-m-d') . '.sqlite"');
        readfile("$DATA/desk.sqlite"); exit;
      default: fail('unknown');
    }
  } catch (Throwable $e) { fail('خطای سرور', 'unavailable', 500); }
}

// ---------- pages ----------
if (!$authed) { ?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><link rel="manifest" href="?manifest=1"><meta name="theme-color" content="#053F5C"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="میز سفارت"><title>ورود · میز وقت سفارت</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='15' fill='%23053F5C'/><circle cx='32' cy='32' r='12' fill='%23F7AD19'/></svg>"><link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALQAAAC0CAYAAAA9zQYyAAADC0lEQVR42u3dPU4rSxSF0a4SpEyJgGkwBgbDGDwNAk+J1IGJkRAyuH/q7ForftJ93f7u5jjhtmUQj8+v14WyLudTG+H/owmYpMCbiEmKu4mYpLibkEkKuwmZpLC7mDnS2s00IZO01l3MJK11FzNJUTchk3SCdDGTtNZdzCRF3cVMUtTd6yJJX/tvCBy50l3MJEXdxUxS1G5o5rihrTMVV7qLmaSonRxknxzWmcorbaHJXWjrTPWVttBk39AQEbRzg4Szw0Lj5IChg3ZukHJ2WGicHCBo2EFzP2OhQdAgaBA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgETQIGg704BWs7/Pt4+b/9un9xQtbkV9jsHPAAhd0fMTiFnRsxOL2pTA+5hH+fAstZGttocVsrS301MFYawsdtX7WWtBxcYha0HFRiFrQcTGIWtBxEcwedRez5xI0CNqKeT5B+7A9p6BxclhnzytoH67nFjQIGgTt3Jjw+S00ggZB+3HrPQgaBI2gQdDuRu9D0CBoEDSCBkGDoEHQIGgEDYIGQW/PLwSf631YaAQNggZBuxu9B0FjoUHQftx6fkGDoEHQzo4Zn7v7cD2voEHQVstzCtqH7fkEjZPDSnsuQfvwPY+gReA5BC0GMQt6mijELOiYOMT8XXt8fr16DT8b+ZeDC9lCx0QjZgsdsdZCttAxMYnZQpdfbBELunzcIhZ06cAFLGjwpRBBg6BB0CBoEDSCBkGDoEHQIGgEDYIGQYOgQdAIGgQNggZBM3vQl/OpeQ0kuJxPzULj5ABBw15Bu6NJuJ8tNE4OKBG0s4Pq54aFxskBZYJ2dlD53LDQ5J8cVpqq62yhmeNLoZWm4jr/utCiplrMTg7mODmsNBXX+aaFFjVVYr755BA1FWJ2QzPXDW2lqbTOf15oUTNyzP86OUTNqDEvy7LcFad/+J5RQl7lS6G1ZqSY7w5a1IwU890nhxOEUUJebaGtNSM1s1mA1pojxm/zRRU2e/4U3/VEELeIt3bYzStuEUcFLXABb+ELPztIlaSm4tQAAAAASUVORK5CYII="><link rel="apple-touch-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALQAAAC0CAYAAAA9zQYyAAADC0lEQVR42u3dPU4rSxSF0a4SpEyJgGkwBgbDGDwNAk+J1IGJkRAyuH/q7ForftJ93f7u5jjhtmUQj8+v14WyLudTG+H/owmYpMCbiEmKu4mYpLibkEkKuwmZpLC7mDnS2s00IZO01l3MJK11FzNJUTchk3SCdDGTtNZdzCRF3cVMUtTd6yJJX/tvCBy50l3MJEXdxUxS1G5o5rihrTMVV7qLmaSonRxknxzWmcorbaHJXWjrTPWVttBk39AQEbRzg4Szw0Lj5IChg3ZukHJ2WGicHCBo2EFzP2OhQdAgaBA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgETQIGg704BWs7/Pt4+b/9un9xQtbkV9jsHPAAhd0fMTiFnRsxOL2pTA+5hH+fAstZGttocVsrS301MFYawsdtX7WWtBxcYha0HFRiFrQcTGIWtBxEcwedRez5xI0CNqKeT5B+7A9p6BxclhnzytoH67nFjQIGgTt3Jjw+S00ggZB+3HrPQgaBI2gQdDuRu9D0CBoEDSCBkGDoEHQIGgEDYIGQW/PLwSf631YaAQNggZBuxu9B0FjoUHQftx6fkGDoEHQzo4Zn7v7cD2voEHQVstzCtqH7fkEjZPDSnsuQfvwPY+gReA5BC0GMQt6mijELOiYOMT8XXt8fr16DT8b+ZeDC9lCx0QjZgsdsdZCttAxMYnZQpdfbBELunzcIhZ06cAFLGjwpRBBg6BB0CBoEDSCBkGDoEHQIGgEDYIGQYOgQdAIGgQNggZBM3vQl/OpeQ0kuJxPzULj5ABBw15Bu6NJuJ8tNE4OKBG0s4Pq54aFxskBZYJ2dlD53LDQ5J8cVpqq62yhmeNLoZWm4jr/utCiplrMTg7mODmsNBXX+aaFFjVVYr755BA1FWJ2QzPXDW2lqbTOf15oUTNyzP86OUTNqDEvy7LcFad/+J5RQl7lS6G1ZqSY7w5a1IwU890nhxOEUUJebaGtNSM1s1mA1pojxm/zRRU2e/4U3/VEELeIt3bYzStuEUcFLXABb+ELPztIlaSm4tQAAAAASUVORK5CYII=">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<style>
:root{--ink:#1f2a4d;--muted:#7a84a6;--accent:#5b6cff;--line:#e6e9f4;--soft:#f5f7fd;--accent-soft:#eceeff;--amber:#F7AD19;--navy:#053F5C;--red-soft:#ffebed;--r-xs:6px; --r-sm:10px; --r-md:14px; --r-lg:20px; --r-xl:28px; --r-pill:999px; --fs-2xs:11px; --fs-xs:12px; --fs-sm:13px; --fs-base:14.5px; --fs-md:16px; --fs-lg:19px; --fs-xl:24px; --fs-2xl:30px; --fs-3xl:38px; --sh-1:0 1px 2px rgba(40,50,110,.05),0 4px 10px rgba(40,50,110,.07); --sh-2:0 2px 4px rgba(40,50,110,.04),0 12px 30px rgba(40,50,110,.08); --sh-3:0 4px 8px rgba(40,50,110,.06),0 20px 44px rgba(40,50,110,.16); --sh-pop:0 30px 80px rgba(31,42,77,.28); --ease:cubic-bezier(.2,.7,.2,1); --dur:.16s; --red-ink:#d8394a; --green-ink:#12936a; --amber-ink:#9a6400; --line-strong:#cdd3f3;}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;color:var(--ink);background:#eef1fb;display:grid;place-items:center;padding:20px;overflow:hidden;position:relative}
.blob{position:fixed;border-radius:50%;filter:blur(60px);opacity:.7;z-index:0}
.b1{width:520px;height:520px;background:#bfe3ff;top:-160px;right:-120px}
.b2{width:460px;height:460px;background:#f3d7ff;bottom:-180px;left:-120px}
.b3{width:300px;height:300px;background:#ffe7b3;bottom:10%;right:20%;opacity:.5}
.card{position:relative;z-index:1;width:min(960px,100%);min-height:580px;display:grid;grid-template-columns:1.05fr 1fr;background:rgba(255,255,255,.75);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.9);border-radius:var(--r-xl);box-shadow:var(--sh-pop);overflow:hidden}
.art{position:relative;background:#6fa6de url("data:image/jpeg;base64,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") center 38%/cover no-repeat;color:#fff;padding:32px;display:flex;flex-direction:column;justify-content:flex-start;gap:14px;overflow:hidden;border-radius:var(--r-xl);margin:10px}
.art::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,40,90,.28) 0%,rgba(10,40,90,0) 38%);pointer-events:none}
.art > *{position:relative;z-index:1}
.art h1{margin:0;font-size:var(--fs-2xl);font-weight:800;line-height:1.35;text-shadow:0 2px 14px rgba(0,40,90,.35)}
.art p{margin:8px 0 0;color:#b9c1e4;font-size:var(--fs-sm);max-width:32ch}
.logo{display:flex;align-items:center;gap:10px;font-weight:800;text-shadow:0 1px 8px rgba(0,40,90,.3)}
.logo i{width:38px;height:38px;border-radius:var(--r-md);background:var(--navy);display:grid;place-items:center;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
.logo i::after{content:"";width:13px;height:13px;border-radius:50%;background:var(--amber)}
.tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:26px 0;transform:rotate(-6deg)}
.t{aspect-ratio:1;border-radius:var(--r-lg);display:grid;place-items:center;font-family:"IBM Plex Mono",monospace;font-weight:600;font-size:var(--fs-base);color:#fff;box-shadow:var(--sh-2);animation:float 6s ease-in-out infinite}
.t:nth-child(2n){animation-delay:-2s}.t:nth-child(3n){animation-delay:-4s}
.t.dim{opacity:.35}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.art small{color:#8d96c2;font-size:var(--fs-xs)}
.form{padding:44px 40px;display:flex;flex-direction:column;justify-content:center;gap:16px}
.form h2{margin:0;font-size:var(--fs-xl);font-weight:800}
.form .sub{margin:-8px 0 6px;color:var(--muted);font-size:var(--fs-sm)}
label{font-size:var(--fs-sm);font-weight:700;color:var(--muted)}
.pw{position:relative}
.pw input{width:100%;border:1.5px solid var(--line);background:#fff;border-radius:var(--r-md);padding:12px 16px 12px 52px;font:inherit;font-size:var(--fs-md);min-height:54px;outline:none;transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.pw input:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-soft)}
.eye{position:absolute;left:8px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;background:var(--soft);border-radius:var(--r-md);cursor:pointer;display:grid;place-items:center;color:var(--muted)}
.eye svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}
.go{border:0;background:var(--accent);color:#fff;border-radius:var(--r-md);padding:12px;font:inherit;font-size:var(--fs-md);font-weight:800;min-height:54px;cursor:pointer;box-shadow:var(--sh-2);transition:transform var(--dur) var(--ease)}
.go:hover{transform:translateY(-1px)}
.go:focus-visible,.eye:focus-visible{outline:3px solid var(--amber);outline-offset:2px}
.err{background:var(--red-soft);color:var(--red-ink);border-radius:var(--r-md);padding:10px 14px;font-weight:700;font-size:var(--fs-sm)}
.shake{animation:shake .4s}
@keyframes shake{20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.foot{color:var(--muted);font-size:var(--fs-xs);text-align:center}
@media (max-width:760px){body{overflow:auto;place-items:start center}.card{grid-template-columns:1fr;min-height:0}.art{min-height:340px;padding:22px;background-position:center 8%}.art h1{font-size:var(--fs-xl)}.form{padding:24px 22px 28px}}
@media (prefers-reduced-motion:reduce){.t,.shake{animation:none}}
</style></head><body>
<span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
<main class="card">
  <section class="art" role="img" aria-label="مسافران با چمدان روی دشت نمک">
    <div class="logo"><i></i>Travel Market</div>
    <h1>میز وقت سفارت</h1>
  </section>
  <form class="form" method="post" autocomplete="on">
    <h2>خوش آمدید</h2>
    <p class="sub">برای ورود، رمز تیم را وارد کنید.</p>
    <?php if ($pwNotSet) { ?><div class="err">رمز ورود هنوز تنظیم نشده. در File Manager فایل index.php را باز کنید و بالای فایل، جلوی password رمز دلخواه را بنویسید.</div><?php } ?>
    <?php if ($loginError && !$pwNotSet) { ?><div class="err shake">رمز اشتباه است. دوباره امتحان کنید.</div><?php } ?>
    <label for="pw">رمز ورود</label>
    <div class="pw"><input id="pw" type="password" name="login_pw" autocomplete="current-password" autofocus required>
      <button class="eye" type="button" aria-label="نمایش رمز" onclick="const i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
    <button class="go" type="submit">ورود</button>
    <div class="foot">فقط برای تیم Travel Market</div>
  </form>
</main>
</body></html>
<?php exit; }
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><link rel="manifest" href="?manifest=1"><meta name="theme-color" content="#053F5C"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="میز سفارت">
<title>میز وقت سفارت</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='15' fill='%23053F5C'/><circle cx='32' cy='32' r='12' fill='%23F7AD19'/></svg>"><link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALQAAAC0CAYAAAA9zQYyAAADC0lEQVR42u3dPU4rSxSF0a4SpEyJgGkwBgbDGDwNAk+J1IGJkRAyuH/q7ForftJ93f7u5jjhtmUQj8+v14WyLudTG+H/owmYpMCbiEmKu4mYpLibkEkKuwmZpLC7mDnS2s00IZO01l3MJK11FzNJUTchk3SCdDGTtNZdzCRF3cVMUtTd6yJJX/tvCBy50l3MJEXdxUxS1G5o5rihrTMVV7qLmaSonRxknxzWmcorbaHJXWjrTPWVttBk39AQEbRzg4Szw0Lj5IChg3ZukHJ2WGicHCBo2EFzP2OhQdAgaBA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgETQIGg704BWs7/Pt4+b/9un9xQtbkV9jsHPAAhd0fMTiFnRsxOL2pTA+5hH+fAstZGttocVsrS301MFYawsdtX7WWtBxcYha0HFRiFrQcTGIWtBxEcwedRez5xI0CNqKeT5B+7A9p6BxclhnzytoH67nFjQIGgTt3Jjw+S00ggZB+3HrPQgaBI2gQdDuRu9D0CBoEDSCBkGDoEHQIGgEDYIGQW/PLwSf631YaAQNggZBuxu9B0FjoUHQftx6fkGDoEHQzo4Zn7v7cD2voEHQVstzCtqH7fkEjZPDSnsuQfvwPY+gReA5BC0GMQt6mijELOiYOMT8XXt8fr16DT8b+ZeDC9lCx0QjZgsdsdZCttAxMYnZQpdfbBELunzcIhZ06cAFLGjwpRBBg6BB0CBoEDSCBkGDoEHQIGgEDYIGQYOgQdAIGgQNggZBM3vQl/OpeQ0kuJxPzULj5ABBw15Bu6NJuJ8tNE4OKBG0s4Pq54aFxskBZYJ2dlD53LDQ5J8cVpqq62yhmeNLoZWm4jr/utCiplrMTg7mODmsNBXX+aaFFjVVYr755BA1FWJ2QzPXDW2lqbTOf15oUTNyzP86OUTNqDEvy7LcFad/+J5RQl7lS6G1ZqSY7w5a1IwU890nhxOEUUJebaGtNSM1s1mA1pojxm/zRRU2e/4U3/VEELeIt3bYzStuEUcFLXABb+ELPztIlaSm4tQAAAAASUVORK5CYII="><link rel="apple-touch-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALQAAAC0CAYAAAA9zQYyAAADC0lEQVR42u3dPU4rSxSF0a4SpEyJgGkwBgbDGDwNAk+J1IGJkRAyuH/q7ForftJ93f7u5jjhtmUQj8+v14WyLudTG+H/owmYpMCbiEmKu4mYpLibkEkKuwmZpLC7mDnS2s00IZO01l3MJK11FzNJUTchk3SCdDGTtNZdzCRF3cVMUtTd6yJJX/tvCBy50l3MJEXdxUxS1G5o5rihrTMVV7qLmaSonRxknxzWmcorbaHJXWjrTPWVttBk39AQEbRzg4Szw0Lj5IChg3ZukHJ2WGicHCBo2EFzP2OhQdAgaBA0ggZBg6BB0CBoBA2CBkGDoEHQCBoEDYIGQYOgETQIGg704BWs7/Pt4+b/9un9xQtbkV9jsHPAAhd0fMTiFnRsxOL2pTA+5hH+fAstZGttocVsrS301MFYawsdtX7WWtBxcYha0HFRiFrQcTGIWtBxEcwedRez5xI0CNqKeT5B+7A9p6BxclhnzytoH67nFjQIGgTt3Jjw+S00ggZB+3HrPQgaBI2gQdDuRu9D0CBoEDSCBkGDoEHQIGgEDYIGQW/PLwSf631YaAQNggZBuxu9B0FjoUHQftx6fkGDoEHQzo4Zn7v7cD2voEHQVstzCtqH7fkEjZPDSnsuQfvwPY+gReA5BC0GMQt6mijELOiYOMT8XXt8fr16DT8b+ZeDC9lCx0QjZgsdsdZCttAxMYnZQpdfbBELunzcIhZ06cAFLGjwpRBBg6BB0CBoEDSCBkGDoEHQIGgEDYIGQYOgQdAIGgQNggZBM3vQl/OpeQ0kuJxPzULj5ABBw15Bu6NJuJ8tNE4OKBG0s4Pq54aFxskBZYJ2dlD53LDQ5J8cVpqq62yhmeNLoZWm4jr/utCiplrMTg7mODmsNBXX+aaFFjVVYr755BA1FWJ2QzPXDW2lqbTOf15oUTNyzP86OUTNqDEvy7LcFad/+J5RQl7lS6G1ZqSY7w5a1IwU890nhxOEUUJebaGtNSM1s1mA1pojxm/zRRU2e/4U3/VEELeIt3bYzStuEUcFLXABb+ELPztIlaSm4tQAAAAASUVORK5CYII=">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Manrope:wght@500;600;700&display=swap">
<script>try{var t=localStorage.getItem("theme");if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<style>
/* Layout: soft pastel wash; sidebar nav (top bar on phones); home = grid of vivid colour tiles, one colour per country; country page opens with a tile-coloured hero holding its numbers, then white floating panels for emails, phones, passports, travellers, history. Single light look by choice. */
:root{
  color-scheme:light;
  --bg:#f3f5fc; --surface:#ffffff; --glass:rgba(255,255,255,.82); --line:#e6e9f4; --soft:#f5f7fd;
  --ink:#1f2a4d; --muted:#7a84a6; --navy:#053F5C; --teal:#429EBD; --teal-soft:#e4f3f8; --cyan:#9FE7F5; --cyan-soft:#e9fafd; --amber:#F7AD19; --amber-soft:#fff3d9;
  --accent:#5b6cff; --accent-soft:#eceeff;
  --red:#ff5d6c; --red-soft:#ffebed; --green:#22c38e; --green-soft:#e2f8f0;
  --shadow:var(--sh-2);
  --f-body:"Vazirmatn",Tahoma,"Segoe UI",sans-serif; --f-mono:"Manrope","Segoe UI",system-ui,sans-serif; /* emails, phones, codes: tabular digits via .mono */
  --r:var(--r-lg); --cc:var(--accent);
  /* ── Design tokens ──────────────────────────────────────────────
     Radius:  xs 6 · sm 10 · md 14 · lg 20 · xl 28 · pill
     Type:    2xs 11 · xs 12 · sm 13 · base 14.5 · md 16 · lg 19 · xl 24 · 2xl 30 · 3xl 38
     Weight:  regular 400 · medium 600 · bold 700 · heavy 800 (loaded font weights only)
     Shadow:  1 rest · 2 raised · 3 floating · pop (dialogs)
     Always use tokens; raw values only for country-tinted (--cc) effects. */
  --r-xs:6px; --r-sm:10px; --r-md:14px; --r-lg:20px; --r-xl:28px; --r-pill:999px;
  --fs-2xs:11px; --fs-xs:12px; --fs-sm:13px; --fs-base:14.5px; --fs-md:16px; --fs-lg:19px; --fs-xl:24px; --fs-2xl:30px; --fs-3xl:38px;
  --sh-1:0 1px 2px rgba(40,50,110,.05),0 4px 10px rgba(40,50,110,.07);
  --sh-2:0 2px 4px rgba(40,50,110,.04),0 12px 30px rgba(40,50,110,.08);
  --sh-3:0 4px 8px rgba(40,50,110,.06),0 20px 44px rgba(40,50,110,.16);
  --sh-pop:0 30px 80px rgba(31,42,77,.28);
  --ease:cubic-bezier(.2,.7,.2,1); --dur:.16s;
  --red-ink:#d8394a; --green-ink:#12936a; --amber-ink:#9a6400; --line-strong:#cdd3f3;
  /* Navy: data surfaces — table headers, account (email + phone) rows */
  --nv:#0f2547; --nv-2:#1c3d70; --nv-ink:#ffffff; --nv-muted:rgba(255,255,255,.62); --nv-chip:rgba(255,255,255,.14);
}
*{box-sizing:border-box}
[hidden]{display:none!important}
html,body{direction:rtl;height:100%}
body{margin:0;color:var(--ink);font-family:var(--f-body);font-size:var(--fs-base);line-height:1.7;background:var(--bg)}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(900px 500px at 100% 0%,var(--wash1) 0%,transparent 60%),radial-gradient(800px 600px at 0% 100%,var(--wash2) 0%,transparent 55%)}
button,input,select,textarea{font:inherit;color:inherit}
.mono{font-family:var(--f-mono);direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums}
/* shell */
.shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100%}
.side{position:sticky;top:0;height:100vh;padding:calc(22px + env(safe-area-inset-top,0px)) 16px 22px;display:flex;flex-direction:column;gap:22px;background:var(--glass);backdrop-filter:blur(16px);border-inline-start:1px solid var(--frame);box-shadow:var(--shadow)}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:var(--fs-md);color:var(--ink)}
.brand i{width:34px;height:34px;border-radius:var(--r-sm);background:var(--navy);display:grid;place-items:center;flex:none}
.brand i::after{content:"";width:12px;height:12px;border-radius:50%;background:var(--amber)}
.tabs{display:flex;flex-direction:column;gap:4px}
.tabs .cap{font-size:var(--fs-2xs);font-weight:800;color:var(--muted);letter-spacing:.04em;margin:0 10px 4px}
.tab{border:0;background:transparent;color:var(--muted);padding:9px 12px;border-radius:var(--r-md);cursor:pointer;font-weight:700;display:flex;align-items:center;gap:10px;text-align:right;white-space:nowrap}
.tab svg{width:18px;height:18px;flex:none;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.tab:hover{background:var(--soft);color:var(--ink)}
.tab[aria-selected="true"]{background:var(--accent-soft);color:var(--accent)}
.tab .n{margin-inline-start:auto;font-family:var(--f-mono);font-size:var(--fs-2xs);background:var(--accent);color:#fff;border-radius:var(--r-pill);padding:0 7px;line-height:18px}
.tab .n:empty{display:none}
.side-foot{margin-top:auto;display:flex;justify-content:space-between;gap:10px;font-size:var(--fs-xs);color:var(--muted);background:var(--soft);border-radius:var(--r-md);padding:10px 12px}
.mtop{display:none}
@media (max-width:900px){
  .shell{grid-template-columns:minmax(0,1fr)}
  .side{position:fixed;inset-inline:0;bottom:0;top:auto;height:auto;z-index:15;flex-direction:row;padding:6px 6px calc(6px + env(safe-area-inset-bottom,0px));border-inline-start:0;border-top:1px solid var(--line);background:var(--glass);backdrop-filter:blur(16px);box-shadow:var(--sh-2)}
  .side .brand,.side-foot,.tabs .cap{display:none}
  .tabs{flex-direction:row;justify-content:space-around;gap:2px;flex:1;min-width:0;overflow:visible}
  .tab{flex:1 1 0;min-width:0;flex-direction:column;gap:2px;padding:6px 2px;border-radius:var(--r-md);font-size:var(--fs-2xs);line-height:1.25;text-align:center;white-space:normal;position:relative}
  .tab svg{width:22px;height:22px}
  .tab .n{position:absolute;top:2px;inset-inline-end:calc(50% - 22px);margin:0;font-size:var(--fs-2xs);line-height:16px;padding:0 5px}
  .wrap{padding-block:12px calc(96px + env(safe-area-inset-bottom,0px))}
  .mtop{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:calc(10px + env(safe-area-inset-top,0px)) 16px 0}
  .mtop .brand{display:flex;font-size:var(--fs-base)}
  .mtop .brand i{width:30px;height:30px;border-radius:var(--r-sm)}
  .mtop .links{display:flex;gap:12px;font-size:var(--fs-xs);font-weight:700}
  .page-h h1{font-size:var(--fs-xl)}
  .sheet{border-radius:0}
}
.wrap{max-width:1100px;margin:0 auto;padding-inline:20px;padding-block:24px 70px}
@media (max-width:560px){.wrap{padding-inline:16px}}
/* buttons */
.btn{border:1px solid var(--line);background:var(--surface);border-radius:var(--r-pill);padding:7px 16px;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;white-space:nowrap;color:var(--ink);transition:transform var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.btn:hover{transform:translateY(-1px);box-shadow:var(--sh-1)}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 6px 16px color-mix(in srgb,var(--accent) 35%,transparent)}
.btn.teal{background:var(--teal);border-color:var(--teal);color:#fff}
.btn.amber{background:var(--amber);border-color:var(--amber);color:var(--navy)}
.btn.ghost-red{color:var(--red);border-color:var(--red-soft);background:var(--surface)}
.btn.sm{padding:2px 12px;min-height:32px;font-size:var(--fs-xs)}
.btn[disabled]{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
.btn:focus-visible,.tab:focus-visible,.ccard:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid var(--amber);outline-offset:2px}
.banner{background:var(--amber-soft);color:var(--amber-ink);border-radius:var(--r-md);padding:10px 16px;margin-bottom:14px;font-size:var(--fs-sm)}
.page-h{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.page-h h1{margin:0;font-size:var(--fs-xl);font-weight:800;text-wrap:balance;letter-spacing:-.01em}
.page-h p{margin:2px 0 0;color:var(--muted);font-size:var(--fs-sm);max-width:62ch}
.crumb{border:0;background:var(--surface);color:var(--ink);font:inherit;font-weight:800;cursor:pointer;padding:6px 14px 6px 16px;border-radius:var(--r-pill);margin-bottom:12px;font-size:var(--fs-sm);display:inline-flex;align-items:center;gap:6px;box-shadow:var(--sh-1)}.crumb svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}.crumb:hover{transform:translateX(2px)}
/* status vocabulary */
.st{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:var(--r-pill);font-size:var(--fs-xs);font-weight:700;white-space:nowrap}
.st::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.st-active{background:var(--green-soft);color:var(--green-ink)}
.st-check{background:var(--amber-soft);color:var(--amber-ink)}
.st-unused{background:var(--soft);color:var(--muted)}
.st-none{background:var(--red-soft);color:var(--red-ink)}
.st-reg{background:var(--amber-soft);color:var(--amber-ink)}
.tag{display:inline-flex;align-items:center;padding:0 8px;border-radius:var(--r-xs);font-size:var(--fs-xs);font-weight:700;background:var(--soft);color:var(--ink);font-family:var(--f-mono);direction:ltr}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:var(--fs-xs);color:var(--muted);margin-bottom:16px}
.legend span{display:inline-flex;gap:6px;align-items:center}
/* country tiles */
.cgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}
.ccard{--c2:color-mix(in srgb,var(--cc) 82%,var(--surface));background:var(--cc);color:#fff;border:0;border-radius:var(--r-xl);padding:18px;cursor:pointer;text-align:right;display:flex;flex-direction:column;gap:14px;box-shadow:0 14px 28px color-mix(in srgb,var(--cc) 32%,transparent);transition:transform var(--dur) var(--ease),box-shadow var(--dur) var(--ease);position:relative;overflow:hidden}
.ccard::after{content:"";position:absolute;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.13);top:-60px;left:-50px}
.ccard:hover{transform:translateY(-3px);box-shadow:0 18px 34px color-mix(in srgb,var(--cc) 40%,transparent)}
.ccard > *{position:relative;z-index:1}
.ccard-h{display:flex;align-items:center;gap:12px}
.mono-b{width:46px;height:46px;border-radius:var(--r-md);background:var(--glass);color:var(--cc);display:grid;place-items:center;font-family:var(--f-mono);font-weight:700;font-size:var(--fs-base);flex:none;box-shadow:var(--sh-1)}
.ccard-h b{font-size:var(--fs-lg);font-weight:800;display:block;line-height:1.3}
.ccard-h small{font-family:var(--f-mono);font-size:var(--fs-xs);opacity:.85}
.big{display:flex;align-items:baseline;gap:8px}
.big b{font-size:var(--fs-3xl);font-weight:800;line-height:1;font-variant-numeric:tabular-nums}
.big span{font-weight:700;opacity:.92}
.minis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
.mini{background:rgba(255,255,255,.2);border-radius:var(--r-md);padding:5px 8px;text-align:center;line-height:1.35}
.mini b{display:block;font-size:var(--fs-md);font-weight:800}
.mini span{font-size:var(--fs-2xs);font-weight:700;opacity:.92}
.ccard-f{font-size:var(--fs-xs);display:flex;gap:6px;flex-wrap:wrap}
.ccard-f span{background:rgba(255,255,255,.2);border-radius:var(--r-pill);padding:1px 10px;font-weight:700}
.ccard.add{background:var(--glass);color:var(--accent);border:2px dashed var(--line-strong);box-shadow:none;align-items:center;justify-content:center;font-weight:800;min-height:190px}
.ccard.add::after{display:none}
.alert{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;background:var(--surface);border-radius:var(--r-lg);padding:11px 16px;margin-bottom:10px;box-shadow:var(--shadow);border-inline-start:5px solid var(--lvl)}
.alert .t{font-weight:700}.alert .d{color:var(--muted);font-size:var(--fs-sm)}
.lvl-red{--lvl:var(--red)}.lvl-amber{--lvl:var(--amber)}.lvl-teal{--lvl:var(--teal)}
/* country hero */
.hero{background:var(--cc);color:#fff;border-radius:var(--r-xl);padding:22px 24px;margin-bottom:20px;box-shadow:0 16px 34px color-mix(in srgb,var(--cc) 30%,transparent);position:relative;overflow:hidden}
.hero::after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.12);top:-110px;left:-80px}
.hero > *{position:relative;z-index:1}
.hero-top{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap}
.hero h1{margin:0;font-size:var(--fs-2xl);font-weight:800;display:flex;align-items:center;gap:12px}
.hero .btn{background:var(--surface);border-color:transparent;color:var(--ink);box-shadow:var(--sh-1)}
.hero .btn.primary{background:linear-gradient(180deg,#3d7bff,#1f5eff);border-color:transparent;color:#fff;box-shadow:0 6px 18px rgba(31,94,255,.45),inset 0 1px 0 rgba(255,255,255,.25)}
.hero .btn.primary:hover{background:linear-gradient(180deg,#5a8fff,#2f6bff);box-shadow:0 8px 22px rgba(31,94,255,.55),inset 0 1px 0 rgba(255,255,255,.3)}
.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:18px}
.stat{background:var(--surface);color:var(--ink);border-radius:var(--r-md);padding:10px 12px;box-shadow:var(--sh-1)}
.stat b{display:block;font-size:var(--fs-xl);font-weight:800;line-height:1.3;font-variant-numeric:tabular-nums}
.stat span{font-size:var(--fs-xs);font-weight:700;color:var(--muted)}
.stat.active,.stat.check{background:var(--surface);color:var(--ink)}
.stat.active b{color:var(--green-ink)}.stat.check b{color:var(--amber-ink)}
@media (max-width:760px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
/* panels */
.sec{background:var(--surface);border-radius:var(--r-lg);margin-bottom:18px;box-shadow:var(--shadow);overflow:hidden;border:1px solid var(--frame)}
.sec-h{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 18px 10px}
.sec-h h2{margin:0;font-size:var(--fs-md);font-weight:800}
.sec-h small{color:var(--muted);font-weight:600;font-size:var(--fs-xs)}
.rows{display:flex;flex-direction:column;gap:8px;padding:0 12px 12px}
.row{display:grid;grid-template-columns:minmax(0,2.2fr) minmax(0,1.6fr) auto minmax(0,1fr) auto;gap:12px;align-items:center;padding:10px 14px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--line);transition:box-shadow var(--dur) var(--ease),border-color var(--dur) var(--ease)}
.row:hover{border-color:transparent;box-shadow:var(--sh-2)}
.row.head{font-size:var(--fs-xs);color:var(--muted);font-weight:700;padding-block:2px;background:transparent;border:0;box-shadow:none}
.row.dim{background:var(--soft)}
.row.phone{grid-template-columns:minmax(0,1.6fr) minmax(0,2fr) auto auto}
.cellx{min-width:0;display:flex;flex-direction:column;gap:1px}
.cellx .mono{font-size:var(--fs-sm);overflow-wrap:anywhere}
.cellx small{color:var(--muted);font-size:var(--fs-xs)}
.lbl-m{display:none;font-size:var(--fs-2xs);color:var(--muted);font-weight:700}
.acts{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.copy{border:0;background:var(--accent-soft);color:var(--accent);border-radius:var(--r-xs);padding:0 8px;font-size:var(--fs-2xs);font-weight:800;cursor:pointer;margin-inline-start:4px}
.slots{display:inline-flex;gap:3px;vertical-align:middle}
.slots i{width:11px;height:11px;border-radius:var(--r-xs);background:var(--soft);border:1px solid var(--line)}
.slots i.on{background:var(--cc);border-color:var(--cc)}
.cnt{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:var(--fs-xs)}
.cnt b{min-width:28px;height:28px;border-radius:var(--r-sm);display:inline-grid;place-items:center;background:var(--accent);color:#fff;font-size:var(--fs-sm);font-family:var(--f-mono)}
.cnt b.zero{background:var(--soft);color:var(--muted)}
@media (max-width:760px){
  .row,.row.phone{grid-template-columns:1fr auto;gap:6px 10px}
  .row.head{display:none}
  .row > .c-status{grid-column:2;grid-row:1;justify-self:end}
  .row > .c-main{grid-column:1;grid-row:1}
  .row > .c-2,.row > .c-3{grid-column:1/-1}
  .row > .acts{grid-column:1/-1;justify-content:flex-start}
  .lbl-m{display:inline}
}
.chips{display:flex;gap:5px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:4px;padding:1px 10px;border-radius:var(--r-pill);font-size:var(--fs-xs);font-weight:700;background:var(--soft);color:var(--ink);white-space:nowrap}
.pill.ok{background:var(--green-soft);color:var(--green-ink)}
.pill.sun{background:var(--amber-soft);color:var(--amber-ink)}
.empty{padding:24px 16px;text-align:center;color:var(--muted)}
.table-wrap{overflow-x:auto;padding:0 6px 6px}
table{width:100%;border-collapse:separate;border-spacing:0;font-size:var(--fs-sm)}
th{text-align:right;font-weight:700;color:var(--muted);font-size:var(--fs-xs);padding:8px 14px;white-space:nowrap}
td{padding:10px 14px;border-top:1px solid var(--line);vertical-align:middle}
td small{display:block;color:var(--muted);font-size:var(--fs-xs)}
tbody tr:hover td{background:var(--soft)}
.ev{display:grid;grid-template-columns:92px 1fr auto;gap:12px;padding:11px 18px;border-top:1px solid var(--line);align-items:start}
.ev .date{font-family:var(--f-mono);font-size:var(--fs-xs);color:var(--muted);direction:ltr;text-align:right}
.ev p{margin:1px 0 0;color:var(--muted);font-size:var(--fs-sm);overflow-wrap:anywhere}
@media (max-width:560px){.ev{grid-template-columns:1fr auto}.ev .date{grid-column:1/-1}}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.toolbar .tab{background:var(--surface);border:1px solid var(--line);color:var(--ink);padding:5px 14px;border-radius:var(--r-pill)}
.toolbar input,.toolbar select{border:1px solid var(--line);background:var(--surface);border-radius:var(--r-pill);padding:6px 16px;min-height:40px;min-width:0;box-shadow:var(--shadow)}
.toolbar input{flex:1 1 220px}
.note{background:var(--accent-soft);border-radius:var(--r-md);padding:10px 14px;font-size:var(--fs-sm);color:var(--ink)}
/* sheet */
.scrim{position:fixed;inset:0;background:rgba(31,42,77,.28);backdrop-filter:blur(3px);z-index:20}
.sheet{position:fixed;inset-block:0;inset-inline-start:0;width:min(480px,100%);background:var(--surface);z-index:21;display:flex;flex-direction:column;box-shadow:var(--sh-1);border-start-end-radius:28px;border-end-end-radius:28px;overflow:hidden}
.sheet header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:calc(16px + env(safe-area-inset-top,0px)) 20px 14px;border-bottom:1px solid var(--line)}
.sheet header h3{margin:0;font-size:var(--fs-md);font-weight:800}
.sheet .body{padding:20px 22px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:20px}
.sheet footer{padding:14px 22px calc(14px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--line);display:flex;gap:12px 10px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.field{display:flex;flex-direction:column;gap:4px}
.field label{font-size:var(--fs-xs);color:var(--muted);font-weight:700}
.field input,.field select,.field textarea{border:1px solid var(--line);background:var(--soft);border-radius:var(--r-md);padding:8px 14px;min-height:44px;width:100%}
.field input:focus,.field select:focus,.field textarea:focus{background:var(--surface);border-color:var(--accent)}
.field textarea{min-height:88px;resize:vertical}
.field .hint{font-size:var(--fs-xs);color:var(--muted)}
.field-row{display:flex;gap:6px;align-items:stretch}
.field-row select{flex:1;min-width:0}
.ltr{direction:ltr;text-align:left;font-family:var(--f-mono)}
.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.step{display:flex;gap:10px;align-items:flex-start}
.step > i{flex:none;width:26px;height:26px;border-radius:var(--r-xs);background:var(--accent);color:#fff;display:grid;place-items:center;font-style:normal;font-weight:800;font-size:var(--fs-xs);margin-top:22px}
.step > div{flex:1;min-width:0;display:flex;flex-direction:column;gap:10px}.step > div > .btn{align-self:flex-start}
#newSteps{display:flex;flex-direction:column;gap:20px}
.btn-row{display:flex;gap:8px;flex-wrap:wrap}
.plist{display:flex;flex-direction:column;gap:6px}
.prow{display:flex;justify-content:space-between;align-items:center;gap:8px;background:var(--soft);border-radius:var(--r-md);padding:6px 10px}
.err{color:var(--red-ink);font-size:var(--fs-xs);font-weight:700}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;padding:4px 16px 16px}
.pcard{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);overflow:hidden;display:flex;flex-direction:column;position:relative;transition:box-shadow var(--dur) var(--ease)}
.pcard:hover{box-shadow:var(--sh-2)}
.pcard.sel{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.pcard .thumb{aspect-ratio:4/3;background:var(--soft);display:grid;place-items:center;overflow:hidden;max-width:100%}
.pcard .thumb img{width:100%;height:100%;object-fit:cover}
.pcard .thumb span{font-weight:800;color:var(--accent);font-size:var(--fs-lg)}
.pcard .pb{padding:9px 12px;display:flex;flex-direction:column;gap:4px;min-width:0}
.pcard .pb b{font-size:var(--fs-sm);overflow-wrap:anywhere}
.pcard .pb small{color:var(--muted);font-size:var(--fs-xs);overflow-wrap:anywhere}
.pcard .pick{position:absolute;top:10px;inset-inline-start:10px;width:22px;height:22px;accent-color:var(--accent)}
.pcard .pact{display:flex;gap:8px;padding:0 12px 12px;flex-wrap:wrap}
.bulkbar{position:sticky;bottom:calc(14px + env(safe-area-inset-bottom,0px));z-index:6;background:var(--toast);color:#fff;border:1px solid var(--toast-line);border-radius:var(--r-lg);padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:12px;box-shadow:var(--sh-2)}
.drop{border:2px dashed var(--line-strong);border-radius:var(--r-lg);padding:20px;text-align:center;color:var(--muted);background:var(--soft)}
.drop.over{border-color:var(--accent);background:var(--accent-soft)}
.flist{display:flex;flex-direction:column;gap:4px;font-size:var(--fs-xs);max-height:220px;overflow:auto}
.flist div{display:flex;justify-content:space-between;gap:8px;background:var(--soft);border-radius:var(--r-sm);padding:4px 10px}
.checks{display:flex;flex-direction:column;gap:6px}
.checks label{display:flex;gap:8px;align-items:center;background:var(--soft);border-radius:var(--r-md);padding:7px 12px;font-weight:700;color:var(--ink);font-size:var(--fs-sm)}
.checks input{width:18px;height:18px;accent-color:var(--accent)}
.av{width:34px;height:34px;border-radius:var(--r-md);background:color-mix(in srgb,var(--avc,var(--accent)) 14%,var(--surface));color:var(--avc,var(--accent));display:inline-grid;place-items:center;font-family:var(--f-mono);font-weight:700;font-size:var(--fs-xs);flex:none}
.who{display:flex;gap:10px;align-items:center;min-width:0}
.who > div{min-width:0;display:flex;flex-direction:column}
/* upload sheet */
.up-step{display:flex;flex-direction:column;gap:10px}
.up-lbl{display:flex;align-items:center;gap:8px;font-weight:800;font-size:var(--fs-sm)}
.up-lbl i{width:24px;height:24px;border-radius:var(--r-xs);background:var(--toast);color:#fff;display:grid;place-items:center;font-style:normal;font-size:var(--fs-xs)}
.cchips{display:flex;gap:8px;flex-wrap:wrap}
.cchip{display:inline-flex;align-items:center;gap:7px;border:2px solid transparent;background:color-mix(in srgb,var(--cc) 12%,var(--surface));color:color-mix(in srgb,var(--cc) 75%,#000);border-radius:var(--r-md);padding:6px 14px;font-weight:800;cursor:pointer}
.cchip.on{background:var(--cc);color:#fff;box-shadow:0 6px 14px color-mix(in srgb,var(--cc) 35%,transparent)}
.cchip:disabled{cursor:default;opacity:.6}
.dropz{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;border:2px dashed var(--line-strong);border-radius:var(--r-lg);padding:26px 16px;background:var(--soft);cursor:pointer;transition:background var(--dur) var(--ease),border-color var(--dur) var(--ease),transform var(--dur) var(--ease);position:relative}
.dropz svg{width:40px;height:40px;stroke:var(--accent);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.dropz b{font-size:var(--fs-base)}
.dropz span{font-size:var(--fs-xs);color:var(--muted)}
.dropz.over{border-color:var(--accent);background:var(--accent-soft);transform:scale(1.01)}
.dropz.has{padding:14px 16px;flex-direction:row;justify-content:center;gap:10px}
.dropz.has svg{width:24px;height:24px}
.dropz.off{pointer-events:none;opacity:.5}
.dropz input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.up-count{font-size:var(--fs-sm);font-weight:800;color:var(--accent)}
.flist2{display:flex;flex-direction:column;gap:8px}
.frow{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:12px;align-items:center;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);padding:8px 10px 8px 12px}
.frow.new{animation:pop .35s ease-out}
@keyframes pop{from{transform:scale(.96);background:var(--accent-soft)}to{transform:none}}
.fthumb{width:52px;height:52px;border-radius:var(--r-md);overflow:hidden;background:var(--accent-soft);display:grid;place-items:center;color:var(--accent);font-weight:800;font-size:var(--fs-xs)}
.fthumb img{width:100%;height:100%;object-fit:cover}
.finfo{min-width:0;display:flex;flex-direction:column;line-height:1.5}
.finfo b{font-size:var(--fs-sm);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.finfo small{font-size:var(--fs-xs);color:var(--muted);overflow-wrap:anywhere}
.finfo .facts{display:flex;gap:6px;flex-wrap:wrap;margin-top:2px}
.finfo .facts span{background:var(--soft);border-radius:var(--r-xs);padding:0 8px;font-size:var(--fs-xs);font-weight:700;color:var(--ink)}
.fx{width:30px;height:30px;border-radius:var(--r-sm);border:0;background:var(--soft);color:var(--muted);font-size:var(--fs-md);cursor:pointer}
.fx:hover{background:var(--red-soft);color:var(--red-ink)}
.fstate{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:var(--fs-sm)}
.fstate.ok{background:var(--green-soft);color:var(--green-ink)}
.fstate.err{background:var(--red-soft);color:var(--red-ink)}
.fstate.spin{border:3px solid var(--accent-soft);border-top-color:var(--accent);animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.sheet footer .btn.primary{min-width:170px;min-height:46px;font-size:var(--fs-base)}
/* passport card */
.pc-top{display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap}
.pname{font-size:var(--fs-base);font-weight:800;line-height:1.4;overflow-wrap:anywhere}
.pdl{display:grid;grid-template-columns:auto 1fr;gap:3px 10px;margin:2px 0 0;font-size:var(--fs-xs)}
.pdl dt{color:var(--muted);font-weight:600}
.pdl dd{margin:0;font-weight:700;min-width:0;overflow-wrap:anywhere}
.pdl dd.warn{color:var(--amber-ink)}.pdl dd.bad{color:var(--red-ink)}
.pdl dd em{font-style:normal;font-size:var(--fs-2xs);font-weight:700}
.pacc{background:var(--green-soft);border-radius:var(--r-sm);padding:5px 8px;font-size:var(--fs-xs);display:flex;flex-direction:column}
.pdel{color:var(--muted);font-size:var(--fs-xs);border-top:1px dashed var(--line);padding-top:6px;margin-top:2px}
.pdf{display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--accent)}
.pdf svg{width:34px;height:34px;stroke:currentColor;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.pdf b{font-size:var(--fs-xs)}
/* work queue */
.qdrop{display:flex;align-items:center;justify-content:center;gap:14px;border:2px dashed var(--line-strong);border-radius:var(--r-lg);padding:22px;background:var(--glass);cursor:pointer;margin-bottom:22px;transition:background var(--dur) var(--ease),border-color var(--dur) var(--ease)}
.qdrop svg{width:34px;height:34px;stroke:var(--accent);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.qdrop b{font-size:var(--fs-md)}.qdrop span{display:block;color:var(--muted);font-size:var(--fs-sm)}
.qdrop.over{border-color:var(--accent);background:var(--accent-soft)}
.qsec{background:var(--surface);border-radius:var(--r-xl);box-shadow:var(--shadow);margin-bottom:22px;overflow:hidden}
.qhead{background:var(--cc);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.qhead h2{margin:0;font-size:var(--fs-lg);font-weight:800;display:flex;align-items:center;gap:10px}
.qhead .mono-b{width:36px;height:36px;border-radius:var(--r-md);font-size:var(--fs-xs)}
.qhead .qn{background:rgba(255,255,255,.22);border-radius:var(--r-pill);padding:2px 12px;font-weight:800;font-size:var(--fs-sm)}
.qbody{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.25fr);gap:18px;padding:18px}
@media (max-width:900px){.qbody{grid-template-columns:1fr}}
.qcol h3{margin:0 0 10px;font-size:var(--fs-sm);color:var(--muted);font-weight:800}
.plist2{display:flex;flex-direction:column;gap:8px}
.prow2{display:grid;grid-template-columns:44px minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid var(--line);border-radius:var(--r-md);padding:7px 10px;background:var(--surface)}
.prow2 .fthumb{width:44px;height:44px;border-radius:var(--r-sm)}
.prow2 b{font-size:var(--fs-sm);display:block;overflow-wrap:anywhere}
.prow2 small{display:flex;gap:6px;flex-wrap:wrap;color:var(--muted);font-size:var(--fs-xs)}
.prow2 small .warn{color:var(--amber-ink);font-weight:700}.prow2 small .bad{color:var(--red-ink);font-weight:700}
.gtag{display:inline-flex;align-items:center;gap:4px;font-size:var(--fs-2xs);font-weight:800;border-radius:var(--r-pill);padding:0 8px;background:var(--accent-soft);color:var(--accent)}
.stepc{border:1.5px solid var(--line);border-radius:var(--r-lg);padding:14px 16px;display:flex;flex-direction:column;gap:10px;background:var(--surface)}
.stepc + .stepc{margin-top:12px}
.stepc.newacc{border-style:dashed;border-color:color-mix(in srgb,var(--cc) 45%,var(--surface))}
.sc-h{display:flex;align-items:center;gap:10px}
.sc-n{width:30px;height:30px;border-radius:var(--r-sm);background:var(--cc);color:#fff;display:grid;place-items:center;font-weight:800;flex:none}
.sc-h b{font-size:var(--fs-base);display:block}
.sc-h small{color:var(--muted);font-size:var(--fs-xs)}
.sc-cred{display:flex;flex-direction:column;gap:4px;background:var(--soft);border-radius:var(--r-md);padding:8px 12px}
.sc-cred div{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0}
.sc-cred .mono{font-size:var(--fs-sm);overflow-wrap:anywhere}
.sc-cred .lbl{font-size:var(--fs-xs);color:var(--muted);font-weight:700;min-width:44px}
.sc-people{display:flex;gap:6px;flex-wrap:wrap}
.sc-people span{background:color-mix(in srgb,var(--cc) 12%,var(--surface));color:color-mix(in srgb,var(--cc) 70%,#000);border-radius:var(--r-pill);padding:2px 10px;font-size:var(--fs-xs);font-weight:700}
.sc-acts{display:flex;gap:8px;flex-wrap:wrap}
.sc-acts .btn.primary{background:var(--cc);border-color:var(--cc);box-shadow:0 6px 14px color-mix(in srgb,var(--cc) 35%,transparent)}
.sc-warn{background:var(--amber-soft);color:var(--amber-ink);border-radius:var(--r-md);padding:6px 10px;font-size:var(--fs-xs);font-weight:700}
.sw{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--soft);border-radius:var(--r-md);padding:10px 14px;font-weight:700;font-size:var(--fs-sm);cursor:pointer}
.sw input{appearance:none;width:44px;height:26px;border-radius:var(--r-pill);background:#d5dbee;position:relative;cursor:pointer;transition:background var(--dur) var(--ease);flex:none}
.sw input::after{content:"";position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:50%;background:var(--surface);box-shadow:var(--sh-1);transition:right var(--dur) var(--ease)}
.sw input:checked{background:var(--accent)}
.sw input:checked::after{right:21px}
.rv{display:grid;grid-template-columns:120px minmax(0,1fr);gap:14px;border:1.5px solid var(--line);border-radius:var(--r-lg);padding:12px;background:var(--surface)}
.rv.miss{border-color:var(--red-line);background:var(--red-soft)}
.rv-img{border-radius:var(--r-md);overflow:hidden;background:var(--accent-soft);display:grid;place-items:center;aspect-ratio:4/3;color:var(--accent);font-weight:800;align-self:start}
.rv-img img{width:100%;height:100%;object-fit:cover}
.rv-f{display:flex;flex-direction:column;gap:8px;min-width:0}
.rv-st{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;font-size:var(--fs-xs);font-weight:800}
.rv-st .ok{color:var(--green-ink)}.rv-st .bad{color:var(--red-ink)}
.rv-g{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.rv-g label{display:flex;flex-direction:column;gap:2px;font-size:var(--fs-2xs);color:var(--muted);font-weight:700;min-width:0}
.rv-g input{border:1px solid var(--line);background:var(--soft);border-radius:var(--r-sm);padding:6px 10px;min-height:38px;width:100%;font-size:var(--fs-sm);min-width:0}
.rv-g input:focus{background:var(--surface);border-color:var(--accent);outline:none}
.rv-g input.empty{border-color:var(--red-line);background:var(--red-soft)}
@media (max-width:520px){.rv{grid-template-columns:1fr}.rv-img{aspect-ratio:16/9}}
.flag{display:inline-block;width:48px;height:32px;border-radius:var(--r-xs);overflow:hidden;flex:none;box-shadow:0 0 0 1px rgba(0,0,0,.14),0 3px 8px rgba(0,0,0,.18);background:var(--surface)}
.flag svg{width:100%;height:100%;display:block}
.flag.sm{width:21px;height:14px;border-radius:3px;box-shadow:0 0 0 1px rgba(0,0,0,.12)}
.flag.md{width:42px;height:28px;border-radius:var(--r-xs)}
.pks{display:grid;grid-template-columns:1fr;gap:8px}
.pk{display:grid;grid-template-columns:44px minmax(0,1fr) 28px;gap:10px;align-items:center;text-align:right;border:1.5px solid var(--line);background:var(--surface);border-radius:var(--r-md);padding:7px 10px;cursor:pointer;transition:border-color var(--dur) var(--ease),background var(--dur) var(--ease)}
.pk:hover{border-color:var(--accent)}
.pk.on{border-color:var(--accent);background:var(--accent-soft)}
.pk:disabled{opacity:.4;cursor:not-allowed}
.pk .fthumb{width:44px;height:44px;border-radius:var(--r-sm)}
.pk-t{min-width:0;display:flex;flex-direction:column}
.pk-t b{font-size:var(--fs-sm);overflow-wrap:anywhere}
.pk-t small{color:var(--muted);font-size:var(--fs-xs);display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.pk-c{width:26px;height:26px;border-radius:50%;border:2px solid var(--line);display:grid;place-items:center;font-weight:800;color:#fff}
.pk.on .pk-c{background:var(--accent);border-color:var(--accent)}
details.more{border:1px solid var(--line);border-radius:var(--r-md);padding:8px 12px}
details.more summary{cursor:pointer;font-weight:700;font-size:var(--fs-sm);color:var(--muted)}
details.more[open] summary{margin-bottom:8px}
.capx{display:inline-flex;align-items:center;padding:2px 10px;border-radius:var(--r-pill);font-size:var(--fs-xs);font-weight:700;background:var(--accent-soft);color:var(--accent);white-space:nowrap;align-self:flex-start}
.capx.empty0{background:var(--soft);color:var(--muted)}
.capx.full{background:var(--amber-soft);color:var(--amber-ink)}
.dz-scrim{position:fixed;inset:0;z-index:40;background:rgba(120,10,20,.35);backdrop-filter:blur(4px);display:grid;place-items:center;padding:16px}
.dz{width:min(440px,100%);background:var(--surface);border-radius:var(--r-xl);padding:24px;box-shadow:0 30px 80px rgba(120,10,20,.35);border-top:8px solid var(--red-ink);display:flex;flex-direction:column;gap:12px;text-align:center}
.dz-ic{width:56px;height:56px;border-radius:50%;background:var(--red-soft);color:var(--red-ink);font-size:var(--fs-2xl);font-weight:800;display:grid;place-items:center;margin:0 auto}
.dz h3{margin:0;font-size:var(--fs-lg);font-weight:800;color:var(--red-ink)}
.dz-body{font-size:var(--fs-sm);color:var(--ink);text-align:right;display:flex;flex-direction:column;gap:8px}
.dz-body .dz-what{background:var(--red-soft);color:var(--ink);border:1px solid var(--red-line);border-radius:var(--r-md);padding:10px 12px;font-weight:800;direction:ltr;text-align:center;font-family:var(--f-mono)}
.dz-body ul{margin:0;padding-inline-start:18px;color:var(--red-ink)}
.dz-lbl{font-size:var(--fs-xs);font-weight:700;color:var(--muted);text-align:right}
.dz-in{border:2px solid var(--red-line);background:var(--surface);color:var(--ink);border-radius:var(--r-md);padding:10px 14px;font:inherit;font-family:var(--f-mono);font-size:var(--fs-md);text-align:center;min-height:48px;outline:none}
.dz-in:focus{border-color:var(--red-ink)}
.dz-acts{display:flex;gap:8px;justify-content:center;margin-top:4px}
.dz-go{background:var(--red-ink);border-color:var(--red-ink);color:#fff;min-width:150px}
.dz-go[disabled]{background:var(--red-line);border-color:var(--red-line);opacity:1}
.btn.del-hard{color:var(--red-ink);border-color:var(--red-line)}
.swap-list{display:flex;flex-direction:column;gap:8px}
.swap-acc{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--soft);border-radius:var(--r-md);padding:8px 12px}
.swap-acc .mono{font-size:var(--fs-sm);direction:ltr}.swap-acc small,.swap-opt small{color:var(--muted);font-size:var(--fs-xs);margin-inline-start:auto}
.swap-opt{display:flex;align-items:center;gap:10px;border:1.5px solid var(--line);border-radius:var(--r-md);padding:10px 12px;cursor:pointer;background:var(--surface)}
.swap-opt:has(input:checked){border-color:var(--accent);background:#eef0ff}
.swap-opt .mono{font-size:var(--fs-base);font-weight:700;direction:ltr}
.swap-note{margin-top:14px;background:var(--amber-soft);color:var(--amber-ink);border-radius:var(--r-md);padding:10px 12px;font-size:var(--fs-sm);font-weight:600}
.fchips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.fchip{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--line);background:var(--surface);border-radius:var(--r-md);padding:6px 12px;font-weight:800;cursor:pointer;color:var(--ink)}
.fchip b{background:var(--soft);border-radius:var(--r-pill);padding:0 8px;font-size:var(--fs-xs);color:var(--muted)}
.fchip.on{border-color:var(--cc,var(--accent));background:color-mix(in srgb,var(--cc,var(--accent)) 12%,var(--surface))}
.fchip.on b{background:var(--cc,var(--accent));color:#fff}
.fchip.sm{padding:4px 10px;font-size:var(--fs-xs);border-radius:var(--r-pill)}
.ptable{display:flex;flex-direction:column;gap:8px;padding:10px 12px 12px}
.prow3{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) minmax(0,1.3fr) auto auto;gap:14px;align-items:center;border:1px solid var(--line);border-inline-start:5px solid var(--cc);border-radius:var(--r-md);padding:10px 14px;background:var(--surface)}
.prow3.dim{opacity:.55}
.p3-who{display:flex;align-items:center;gap:12px;min-width:0}
.p3-who > div{min-width:0;display:flex;flex-direction:column}
.p3-who b{font-size:var(--fs-base);overflow-wrap:anywhere}
.p3-who small{color:var(--muted);font-size:var(--fs-xs)}
.p3-cred{min-width:0;font-size:var(--fs-sm);line-height:1.9;overflow-wrap:anywhere}
.p3-st{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
.p3-st small{font-size:var(--fs-xs);color:var(--muted)}
.pill.bad{background:var(--red-soft);color:var(--red-ink)}
.p3-info{font-size:var(--fs-xs);display:flex;flex-direction:column;gap:1px;min-width:0}
.p3-info .k{display:inline-block;min-width:52px;color:var(--muted);font-weight:700;font-size:var(--fs-xs)}
.p3-info .warn{color:var(--amber-ink);font-weight:800}.p3-info .bad{color:var(--red-ink);font-weight:800}
@media (max-width:1100px){.prow3{grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto}.p3-cred{grid-column:1/3;grid-row:2}.prow3 > .btn{grid-row:2;grid-column:3;align-self:center}}
@media (max-width:640px){.prow3{grid-template-columns:1fr auto}.p3-info{grid-column:1/-1;grid-row:2;flex-direction:row;flex-wrap:wrap;gap:4px 14px;background:var(--soft);border-radius:var(--r-sm);padding:6px 10px}.p3-info .k{min-width:0;margin-inline-end:4px}.p3-cred{grid-column:1/-1;grid-row:3}.p3-st{grid-row:1;grid-column:2;align-items:flex-end}.prow3 > .btn{grid-column:1/-1;grid-row:4;justify-self:start}}
.stages{display:flex;align-items:center;gap:6px;padding:14px 18px;border-bottom:1px solid var(--line);overflow-x:auto}
.stages i{flex:1;min-width:14px;height:2px;background:var(--line);border-radius:2px}
.stg{display:flex;align-items:center;gap:6px;font-size:var(--fs-xs);font-weight:700;color:var(--muted);white-space:nowrap}
.stg span{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:var(--soft);font-size:var(--fs-xs);font-weight:800}
.stg.done{color:var(--green-ink)}.stg.done span{background:var(--green-soft);color:var(--green-ink)}
.stg.cur{color:var(--ink)}.stg.cur span{background:var(--cc);color:#fff;box-shadow:0 0 0 4px color-mix(in srgb,var(--cc) 20%,transparent)}
.tasks{display:flex;flex-direction:column;gap:14px;padding:16px 18px 18px}
.task{border:1.5px solid color-mix(in srgb,var(--cc) 35%,var(--line));border-radius:var(--r-lg);padding:16px;display:flex;flex-direction:column;gap:12px;background:var(--surface)}
.task.newacc{border-style:dashed}
.task-h{display:flex;gap:12px;align-items:flex-start}
.task-h > div{display:flex;flex-direction:column;gap:1px;min-width:0}
.task-h b{font-size:var(--fs-base);line-height:1.6}
.task-h small{color:var(--muted);font-size:var(--fs-xs)}
.task-h .task-k{color:var(--cc);font-weight:800;font-size:var(--fs-xs)}
.task-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:14px}
@media (max-width:760px){.task-grid{grid-template-columns:1fr}}
.task-lbl{font-size:var(--fs-xs);font-weight:800;color:var(--muted);margin-bottom:6px}
.task-acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center;border-top:1px dashed var(--line);padding-top:12px}
.task-acts .btn.primary{background:var(--cc);border-color:var(--cc);box-shadow:0 6px 14px color-mix(in srgb,var(--cc) 35%,transparent)}
.task-acts{flex-direction:column;align-items:stretch}
.res-q{font-weight:800;font-size:var(--fs-sm)}
.res-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:8px}
@media (max-width:860px){.res-grid{grid-template-columns:1fr}}
.res{display:flex;flex-direction:column;gap:3px;text-align:right;border:1.5px solid var(--line);background:var(--surface);border-radius:var(--r-md);padding:10px 12px;cursor:pointer;color:var(--ink);transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.res:hover{border-color:var(--cc);box-shadow:var(--sh-1)}
.res b{font-size:var(--fs-sm)}
.res small{font-size:var(--fs-xs);color:var(--muted);line-height:1.6}
.res.main{background:var(--cc);border-color:var(--cc);color:#fff;box-shadow:0 8px 18px color-mix(in srgb,var(--cc) 35%,transparent)}
.res.main small{color:rgba(255,255,255,.88)}
.res.bad b{color:var(--red-ink)}
.res[disabled]{opacity:.5;cursor:wait}
.wl-sum{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}
.wl-sum div{background:var(--surface);border-radius:var(--r-lg);padding:12px 16px;box-shadow:var(--shadow)}
.wl-sum b{display:block;font-size:var(--fs-xl);font-weight:800;font-variant-numeric:tabular-nums}
.wl-sum span{font-size:var(--fs-xs);color:var(--muted);font-weight:700}
.wl-acc{border:1.5px solid color-mix(in srgb,var(--cc) 30%,var(--line));border-radius:var(--r-lg);padding:14px;display:flex;flex-direction:column;gap:10px}
.wl-acc-h{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap}
.wl-acc-side{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.wl-acc-side small{font-size:var(--fs-xs);color:var(--muted)}
.wl-list{display:flex;flex-direction:column;gap:6px}
.wl-row{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) auto;gap:12px;align-items:center;background:var(--soft);border-radius:var(--r-md);padding:8px 12px}
.wl-row.bk{background:var(--green-soft)}
.wl-who{display:flex;flex-direction:column;min-width:0}
.wl-who b{font-size:var(--fs-sm);overflow-wrap:anywhere}
.wl-who small{font-size:var(--fs-xs);color:var(--muted)}
.wl-st{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.wl-st b{font-size:var(--fs-sm)}
.wl-act{display:flex;gap:8px}
@media (max-width:640px){.wl-acc-h{flex-direction:column;align-items:stretch}.wl-acc-h .sc-cred{width:100%}.wl-acc-side{flex-direction:row;align-items:center}.sc-cred div{flex-wrap:nowrap}.sc-cred .mono{font-size:var(--fs-xs);overflow-wrap:normal;word-break:normal;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;flex:1}.wl-row{grid-template-columns:1fr}.wl-sum{grid-template-columns:repeat(3,minmax(0,1fr))}.wl-sum b{font-size:var(--fs-lg)}}
.toast{display:flex;align-items:center;gap:12px;position:fixed;bottom:calc(18px + env(safe-area-inset-bottom,0px));left:50%;transform:translateX(-50%);background:var(--toast);color:#fff;border:1px solid var(--toast-line);padding:10px 20px;border-radius:var(--r-pill);z-index:30;font-size:var(--fs-sm);max-width:calc(100% - 32px);box-shadow:var(--sh-2)}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
@media (prefers-reduced-motion:no-preference){.sheet{animation:slide .2s ease-out}@keyframes slide{from{transform:translateX(-20px);opacity:.5}to{transform:none;opacity:1}}.ccard{animation:rise .35s ease-out both}@keyframes rise{from{transform:translateY(8px)}to{transform:none}}}

.sec-h h2{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.hcount{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 9px;border-radius:var(--r-sm);background:var(--accent);color:#fff;font-size:var(--fs-sm);font-weight:800;box-shadow:var(--sh-1)}
.hcount2{font-size:var(--fs-xs);font-weight:700;color:var(--muted);background:var(--soft);border-radius:var(--r-pill);padding:3px 10px}
.rs-none{color:var(--muted);font-size:var(--fs-sm)}
.rs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px;padding:4px 18px 18px}
.rs-card{border:1.5px solid color-mix(in srgb,var(--cc) 35%,var(--line));border-radius:var(--r-lg);overflow:hidden;background:var(--surface)}
.rs-card-h{background:var(--cc);color:#fff;display:flex;align-items:center;gap:10px;padding:10px 14px}
.rs-card-h b{font-size:var(--fs-md);font-weight:800}
.rs-card-h > span:not(.flag){margin-inline-start:auto;background:rgba(255,255,255,.22);border-radius:var(--r-pill);padding:1px 10px;font-size:var(--fs-xs);font-weight:800}
.rs-card-b{display:flex;flex-direction:column;gap:6px;padding:10px}
.rs-pair{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto auto;align-items:center;gap:8px;background:var(--soft);border:0;border-radius:var(--r-md);padding:8px 10px;text-align:right;color:var(--ink);font:inherit;cursor:pointer;width:100%}
.rs-pair:hover{box-shadow:inset 0 0 0 1.5px var(--cc)}
.rs-pair.orphan{background:var(--amber-soft);cursor:default}.rs-pair.orphan:hover{box-shadow:none}
.rs-pair.orphan .rs-e{font-size:var(--fs-xs);font-weight:700;color:var(--amber-ink)}
.rs-dot{width:9px;height:9px;border-radius:50%;padding:0}
.rs-dot.st-active{background:var(--green)}.rs-dot.st-check{background:var(--amber)}.rs-dot.st-reg{background:#e09a00}
.rs-e{font-size:var(--fs-xs);direction:ltr;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rs-link{width:20px;height:20px;border-radius:50%;background:var(--cc);color:#fff;display:grid;place-items:center;font-weight:800;font-size:var(--fs-sm);line-height:1}
.rs-p{font-size:var(--fs-xs);direction:ltr;white-space:nowrap}.rs-p i{font-family:var(--f-body);color:var(--muted);font-style:normal;direction:rtl}
.rs-occ{font-size:var(--fs-xs);font-weight:800;background:var(--surface);border-radius:var(--r-pill);padding:1px 8px;color:var(--muted)}.rs-occ.full{background:var(--red-soft);color:var(--red-ink)}
.rs-chip{display:inline-flex;align-items:center;gap:6px;border-radius:var(--r-md);padding:3px 10px 3px 6px;font-size:var(--fs-xs);border:1.5px solid color-mix(in srgb,var(--cc) 35%,transparent);background:color-mix(in srgb,var(--cc) 9%,var(--surface))}
.rs-chip b{font-weight:800;color:var(--ink)}.rs-chip .mono{font-size:var(--fs-xs);color:var(--ink);direction:ltr}
.rs-chip.st-check{border-style:dashed}.rs-chip.st-reg{background:var(--amber-soft);border-color:#f3d28a}
.rs-chip::before{display:none}
tr.rs-off td{opacity:.55}tr.rs-off td:last-child{opacity:1}
.rs-offtag{display:inline-block;margin-top:2px;background:var(--soft);color:var(--muted);border-radius:var(--r-pill);padding:0 8px;font-weight:700}
@media (max-width:640px){.rs-table thead{display:none}.rs-table,.rs-table tbody{display:block;width:100%}.rs-table tr{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:6px 10px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--line)}.rs-table td{display:block;padding:0!important;border:0!important;min-width:0}.rs-table td:nth-child(3){grid-column:1/-1;grid-row:2}.rs-table td:nth-child(2) .mono{white-space:nowrap;font-size:var(--fs-xs)!important}.rs-table td:nth-child(2) small{display:inline-block;margin-inline-start:8px}.rs-table .tag{white-space:nowrap}.rs-grid{grid-template-columns:1fr;padding:4px 10px 12px}.rs-pair{grid-template-columns:auto minmax(0,1fr) auto;row-gap:4px}.rs-pair .rs-link{display:none}.rs-pair .rs-p{grid-column:2;grid-row:2}.rs-pair .rs-occ{grid-column:3;grid-row:1/3}}


/* hero (first page) */
.fhero{position:relative;overflow:hidden;border-radius:var(--r-xl);margin-bottom:22px;background:var(--hero-base);box-shadow:var(--sh-3)}
.hero-sky{position:absolute;inset:0;background:var(--hero-sky)}
.hero-sky i{position:absolute;border-radius:50%;background:var(--cloud);filter:blur(28px);opacity:.85}
.hero-sky .c1{width:340px;height:160px;left:-40px;top:300px}.hero-sky .c2{width:260px;height:130px;left:260px;top:330px;opacity:.7}
.hero-sky .c3{width:220px;height:90px;left:120px;top:40px;opacity:.45}.hero-sky .c4{width:300px;height:200px;right:-80px;top:-90px;opacity:.5}
.hero-plane{position:absolute;left:12%;top:18px;width:300px;transform:rotate(-8deg);filter:drop-shadow(0 30px 24px rgba(30,60,130,.28))}
.hero-plane svg{width:100%;display:block}
.hero-body{position:relative;z-index:1;width:min(420px,100%);margin-inline-end:auto;padding:48px 40px 8px;display:flex;flex-direction:column;gap:14px;min-height:300px}
.hero-body h1{margin:0;font-size:var(--fs-3xl);line-height:1.25;font-weight:800;color:var(--hero-ink)}
.hero-body p{margin:-6px 0 6px;color:var(--hero-sub);font-size:var(--fs-sm);font-weight:700}
.hero-drop.qdrop{margin:0;background:var(--navy);color:#fff;border:2px dashed rgba(255,255,255,.35);border-radius:var(--r-lg);padding:26px 22px;justify-content:flex-start;gap:16px;box-shadow:0 18px 34px rgba(5,63,92,.35);transition:transform var(--dur) var(--ease),background var(--dur) var(--ease)}
.hero-drop.qdrop:hover{transform:translateY(-2px);background:#07496b}
.hero-drop .hd-ic{display:grid;place-items:center;width:58px;height:58px;border-radius:var(--r-lg);background:var(--amber);flex:none}
.hero-drop .hd-ic svg{width:28px;height:28px;stroke:var(--navy);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.hero-drop b{font-size:var(--fs-lg);font-weight:800;color:#fff;display:block}
.hero-drop span{color:rgba(255,255,255,.75);font-size:var(--fs-xs);font-weight:600}
.hero-drop.over{background:#0a5a82;border-color:var(--amber)}
.hero-res{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:18px 40px 32px}
.hx{background:var(--glass);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid var(--frame);border-radius:var(--r-lg);padding:12px 14px;box-shadow:var(--sh-2);min-width:0}
.hx-h{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.hx-h b{font-size:var(--fs-base);font-weight:800;color:var(--ink)}.hx-h small{margin-inline-start:auto;font-size:var(--fs-xs);font-weight:700;color:var(--green-ink);background:var(--green-soft);border-radius:var(--r-pill);padding:1px 10px}
.hx-h .hcount{min-width:24px;height:24px;font-size:var(--fs-xs);border-radius:var(--r-xs);box-shadow:none;background:var(--navy)}
.hx-ic{display:grid;place-items:center;width:30px;height:30px;border-radius:var(--r-sm)}.hx-ic svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.hx-ic.mail{background:var(--amber-soft);color:var(--amber-ink)}.hx-ic.sim{background:var(--teal-soft);color:#1f86a8}
.hx ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column}
.hx li{display:flex;align-items:center;gap:8px;padding:6px 2px;border-top:1px solid var(--line);min-width:0}
.hx li .mono{font-size:var(--fs-xs);color:var(--ink);direction:ltr;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
.hx-flags{display:flex;gap:4px;margin-inline-start:auto;flex:none}
.hx-free{margin-inline-start:auto;flex:none;font-size:var(--fs-2xs);font-weight:700;color:var(--muted);background:var(--soft);border-radius:var(--r-pill);padding:0 8px}
.hx-empty{color:var(--muted);font-size:var(--fs-sm)}
@media (max-width:760px){.hero-plane{left:auto;right:-10px;top:-40px;width:200px;opacity:.95}.hero-body{width:100%;padding:190px 16px 4px;min-height:0}.hero-body h1{font-size:var(--fs-2xl)}.hero-res{grid-template-columns:1fr;padding:14px 16px 16px}.hero-drop.qdrop{padding:20px 16px}}
/* icon buttons */
.ibtn i{display:grid;place-items:center;width:26px;height:26px;border-radius:var(--r-xs);margin-inline-start:-6px}
.ibtn svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.i-sim i{background:var(--teal-soft);color:#1f86a8}.i-mail i{background:var(--amber-soft);color:var(--amber-ink)}.i-bulk i{background:rgba(255,255,255,.22);color:#fff}
/* ── Navy data surfaces ─────────────────────────────────────────────
   Table headers take a gradient from the country's flag colours (navy when a
   country has no flag); account rows are navy. */
.qsec,.rs-card{--cc:var(--nv-2)}
.qhead,.rs-card-h{--g:linear-gradient(120deg,var(--nv-2),var(--nv));--gk:var(--g);position:relative;overflow:hidden;background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.32)),var(--g);color:var(--nv-ink)}
@supports (background:linear-gradient(in oklab,red,blue)){.qhead,.rs-card-h{background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.32)),var(--gk)}}
.qhead{padding:18px 22px}
.rs-card-h{padding:12px 16px}
.qhead h2,.rs-card-h b{text-shadow:0 1px 3px rgba(0,0,0,.35)}
.qhead .qn,.rs-card-h > span:not(.flag){background:var(--nv-chip)}
/* Email & phone map: quiet cards — white header, flag colours only as a thin top strip */
.rs-card{border:1px solid var(--line);box-shadow:var(--sh-1)}
.rs-card-h,.rs-card-h[style]{position:relative;background:var(--surface);color:var(--ink);border-bottom:1px solid var(--line);padding:16px 16px 12px}
.rs-card-h::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:var(--g)}
.rs-card-h b{text-shadow:none}
.rs-card-h > span:not(.flag){background:var(--soft);color:var(--muted)}
.rs-chip{border:1px solid var(--line);background:var(--soft)}
.sc-cred,.rs-pair{background:var(--nv);color:var(--nv-ink)}
.sc-cred .lbl,.sc-cred span[style*="--muted"]{color:var(--nv-muted)!important}
.sc-cred .copy{background:var(--nv-chip);color:var(--nv-ink)}
.sc-cred .copy:hover{background:rgba(255,255,255,.24)}
.nofree{color:#ff9aa5;font-weight:700}
.rs-pair:hover{background:var(--nv-2);box-shadow:none}
.rs-p i{color:var(--nv-muted)}
.rs-link{background:var(--nv-chip)}
.rs-occ{background:var(--nv-chip);color:var(--nv-ink)}
.rs-pair.orphan{color:var(--ink)}
.toast .undo{border:0;background:var(--amber);color:var(--nv);font:inherit;font-weight:800;border-radius:var(--r-pill);padding:2px 14px;cursor:pointer}
.toast .undo:focus-visible{outline:3px solid #fff;outline-offset:2px}
.vfs-row{margin-top:8px}
.btn.vfs{text-decoration:none;background:var(--nv);color:#fff;border-color:var(--nv)}
.btn.vfs:hover{background:var(--nv-2)}
.appt-row{display:flex;flex-direction:column;gap:6px;border:1px solid var(--line);border-radius:var(--r-md);padding:10px 12px}
.appt-read{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.appt-read .lbl{font-size:var(--fs-xs);color:var(--muted);font-weight:700}
.appt-read small{color:var(--muted)}
.ic-btn{min-width:34px;padding:0 10px;font-size:var(--fs-md)}
.ic-btn:hover{border-color:var(--green);color:var(--green-ink)}
.ic-btn.del:hover{border-color:var(--red);color:var(--red-ink);background:var(--red-soft)}
.p3-act{display:flex;gap:6px;align-items:center}
button.rs-chip{font:inherit;cursor:pointer}
button.rs-chip:hover{border-color:var(--nv-2);background:var(--surface)}
.rs-chip small{color:var(--muted);font-weight:700;margin-inline-start:2px}
.rs-table .tag,.rs-table .mono{white-space:nowrap}
.rs-chip.empty{opacity:.5;border-style:dashed;background:transparent}
.rs-chip.empty:hover{opacity:1}
.sec-hint{color:var(--muted);font-size:var(--fs-xs)}
.accl{display:flex;flex-direction:column;gap:10px;padding:4px 18px 18px}
.accb{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1.6fr) auto;gap:14px;align-items:center;background:var(--nv);color:#fff;border:0;border-radius:var(--r-lg);padding:14px 18px;text-align:right;font:inherit;cursor:pointer}
.accb:hover{background:var(--nv-2)}
.accb-c{display:flex;flex-direction:column;gap:2px;min-width:0}
.accb-e{font-size:var(--fs-md);font-weight:700;overflow:hidden;text-overflow:ellipsis;direction:ltr;text-align:left}
.accb-p{font-size:var(--fs-sm);opacity:.8;direction:ltr;text-align:left}
.accb-n{display:flex;flex-wrap:wrap;gap:6px}
.accb-n i{font-style:normal;font-weight:700;font-size:var(--fs-sm);background:var(--nv-chip);border-radius:var(--r-pill);padding:2px 10px}
.accb-n em{font-style:normal;opacity:.6;font-size:var(--fs-sm)}
.accb-o{display:flex;flex-direction:column;align-items:center;gap:2px}
.accb-o b{background:var(--nv-chip);border-radius:var(--r-pill);padding:2px 12px;font-size:var(--fs-sm)}
.accb-o.full b{background:var(--red-soft);color:var(--red-ink)}
.accb-o small{font-size:var(--fs-2xs);color:var(--amber)}
@media (max-width:640px){.accb{grid-template-columns:1fr auto}.accb-n{grid-column:1/-1;grid-row:2}}
.res-open{border:0;background:none;padding:0;font:inherit;color:var(--accent);cursor:pointer;text-decoration:underline;text-underline-offset:3px}
.res-big{display:flex;align-items:center;gap:10px;font-size:var(--fs-xl);font-weight:800;color:var(--nv);direction:ltr;justify-content:flex-end}
.res-acc{display:flex;flex-direction:column;gap:6px;background:var(--nv);color:#fff;border-radius:var(--r-lg);padding:14px 16px}
.res-acc-h{display:flex;align-items:center;gap:10px;font-size:var(--fs-lg)}
.res-acc-h .accb-o{margin-inline-start:auto}
.res-acc-k{font-size:var(--fs-xs);opacity:.65;font-weight:700}
.res-acc-v{font-size:var(--fs-md);font-weight:700;direction:ltr;text-align:left}
.res-acc .btn{align-self:flex-start;margin-top:4px}
.res-more{margin-top:8px}
.res-more summary{cursor:pointer;color:var(--muted);font-weight:700;font-size:var(--fs-sm);padding:6px 0}
/* Stage bar: the four steps of the work, on every page */
.flow{display:grid;grid-template-columns:1fr auto 1fr auto 1fr auto 1fr;align-items:center;gap:6px;margin-bottom:20px}
.fl{display:flex;align-items:center;gap:10px;min-width:0;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:10px 12px;text-align:right;font:inherit;color:var(--muted);cursor:pointer;box-shadow:var(--sh-1);transition:transform var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.fl:hover{transform:translateY(-1px);box-shadow:var(--sh-2)}
.fl-n{flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:var(--soft);color:var(--muted);font-weight:800;font-size:var(--fs-sm)}
.fl-t{display:flex;flex-direction:column;min-width:0;line-height:1.35}
.fl-t b{color:var(--ink);font-size:var(--fs-sm);font-weight:800;white-space:nowrap}
.fl-t small{font-size:var(--fs-2xs);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fl-c{margin-inline-start:auto;flex:none;min-width:28px;height:24px;padding:0 8px;border-radius:var(--r-pill);display:grid;place-items:center;background:var(--soft);color:var(--muted);font-weight:800;font-size:var(--fs-xs)}
.fl.has .fl-c{background:var(--nv);color:#fff}
.fl-c.plus{background:var(--accent);color:#fff;font-size:var(--fs-md)}
.fl.cur{border-color:var(--nv-2);box-shadow:0 0 0 3px color-mix(in srgb,var(--nv-2) 18%,transparent),var(--sh-1)}
.fl.cur .fl-n{background:var(--nv);color:#fff}
.fl-a{width:14px;height:14px;border-top:2px solid var(--line-strong);border-left:2px solid var(--line-strong);transform:rotate(-45deg)}
@media (max-width:900px){.flow{grid-template-columns:1fr 1fr;gap:8px}.fl-a{display:none}.fl-t small{display:none}.fl{padding:8px 10px;gap:8px}.fl-t b{white-space:normal;font-size:var(--fs-xs)}}
/* Add-traveller window: upload or type passport details */
.ap-add{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ap-drop{padding:16px 12px;flex-direction:row;text-align:right;gap:12px;border-color:var(--accent);background:var(--accent-soft)}
.ap-drop svg{flex:none}
.ap-drop span,.ap-manual span{font-size:var(--fs-xs);color:var(--muted);font-weight:600}
.ap-manual{display:flex;align-items:center;gap:12px;text-align:right;border:2px dashed var(--line-strong);border-radius:var(--r-lg);padding:16px 12px;background:var(--surface);cursor:pointer;font:inherit;color:var(--ink)}
.ap-manual:hover,.ap-drop:hover{border-style:solid}
.ap-plus{flex:none;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--nv);color:#fff!important;font-size:var(--fs-lg)!important;font-weight:800}
.mrows{display:flex;flex-direction:column;gap:10px}.mrows:empty{display:none}
.mrow{border:1.5px solid var(--nv-2);border-radius:var(--r-lg);padding:12px 14px;display:flex;flex-direction:column;gap:8px;background:var(--surface)}
.mrow-h{display:flex;align-items:center;justify-content:space-between}
.mrow-h b{font-size:var(--fs-sm);color:var(--nv-2)}
@media (max-width:640px){.ap-add{grid-template-columns:1fr}}
/* Country page hero: same flag gradient as its card */
.hero[style*="--g"]{background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.36)),var(--g)}
@supports (background:linear-gradient(in oklab,red,blue)){.hero[style*="--g"]{background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.36)),var(--gk)}}
/* Country cards: gradient drawn from the country's own flag colours */
.ccard.fg{background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.42)),var(--g);text-shadow:0 1px 2px rgba(0,0,0,.3)}
@supports (background:linear-gradient(in oklab,red,blue)){.ccard.fg{background:linear-gradient(180deg,rgba(8,18,40,.05),rgba(8,18,40,.42)),var(--gk)}}
.ccard.fg .mini,.ccard.fg .ccard-f span{background:rgba(10,20,45,.24)}
/* ── Dark mode: follows the device, or the 🌙 button (data-theme on <html>) ── */
:root{--red-line:#ffc2ca;--toast:#1f2a4d;--toast-line:transparent;--wash1:#e3f4ff;--wash2:#f7e8ff;--frame:rgba(255,255,255,.9);--cloud:#fff;--hero-base:#bcd8f7;--hero-sky:linear-gradient(120deg,#7fa8ec 0%,#a9ccf5 40%,#d3ecf6 75%,#e8f5f1 100%);--hero-ink:#0b2447;--hero-sub:#3b5578}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){color-scheme:dark;
  --bg:#0b1220;--surface:#141c2e;--glass:rgba(20,28,46,.86);--line:#26304a;--line-strong:#34405e;--soft:#1a2338;
  --ink:#e6ebf7;--muted:#8f9bbd;--accent:#6b7dff;--accent-soft:#232a52;--teal-soft:#13303a;--cyan-soft:#12313a;--amber-soft:#3a2d10;
  --red-soft:#3d1a21;--green-soft:#10342a;--red-ink:#ff8d98;--green-ink:#4fd6a5;--amber-ink:#f5c35a;--nv:#1c2c4e;--nv-2:#28406f;
  --red-line:#6e2a37;--toast:#2a3656;--toast-line:#3a4870;--wash1:#13254a;--wash2:#26173d;--frame:rgba(255,255,255,.06);--cloud:#2b4a80;--hero-base:#162c52;--hero-sky:linear-gradient(120deg,#183463 0%,#1d3b6c 45%,#16304a 80%,#132638 100%);--hero-ink:#e6ebf7;--hero-sub:#9fb0d6;--sh-1:0 1px 2px rgba(0,0,0,.3),0 4px 10px rgba(0,0,0,.25);--sh-2:0 2px 4px rgba(0,0,0,.3),0 12px 30px rgba(0,0,0,.35);
  --sh-3:0 4px 8px rgba(0,0,0,.35),0 20px 44px rgba(0,0,0,.45);--shadow:var(--sh-2)}}
:root[data-theme="dark"]{color-scheme:dark;
  --bg:#0b1220;--surface:#141c2e;--glass:rgba(20,28,46,.86);--line:#26304a;--line-strong:#34405e;--soft:#1a2338;
  --ink:#e6ebf7;--muted:#8f9bbd;--accent:#6b7dff;--accent-soft:#232a52;--teal-soft:#13303a;--cyan-soft:#12313a;--amber-soft:#3a2d10;
  --red-soft:#3d1a21;--green-soft:#10342a;--red-ink:#ff8d98;--green-ink:#4fd6a5;--amber-ink:#f5c35a;--nv:#1c2c4e;--nv-2:#28406f;
  --red-line:#6e2a37;--toast:#2a3656;--toast-line:#3a4870;--wash1:#13254a;--wash2:#26173d;--frame:rgba(255,255,255,.06);--cloud:#2b4a80;--hero-base:#162c52;--hero-sky:linear-gradient(120deg,#183463 0%,#1d3b6c 45%,#16304a 80%,#132638 100%);--hero-ink:#e6ebf7;--hero-sub:#9fb0d6;--sh-1:0 1px 2px rgba(0,0,0,.3),0 4px 10px rgba(0,0,0,.25);--sh-2:0 2px 4px rgba(0,0,0,.3),0 12px 30px rgba(0,0,0,.35);
  --sh-3:0 4px 8px rgba(0,0,0,.35),0 20px 44px rgba(0,0,0,.45);--shadow:var(--sh-2)}
.theme-sw{display:flex;align-items:center;gap:10px;width:100%;margin-top:auto;border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:var(--r-md);padding:10px 12px;font:inherit;font-weight:700;cursor:pointer;text-align:right;transition:border-color var(--dur) var(--ease)}
.theme-sw:hover{border-color:var(--line-strong)}
.theme-sw + .side-foot{margin-top:0}
@media (max-width:900px){.theme-sw{display:none}}
.ts-ic{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:var(--soft);font-size:15px;flex:none}
.ts-t{flex:1}
.ts-k{position:relative;width:42px;height:24px;border-radius:var(--r-pill);background:var(--line-strong);flex:none;transition:background var(--dur) var(--ease)}
.ts-k i{position:absolute;top:3px;right:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform var(--dur) var(--ease)}
.theme-sw[aria-checked="true"] .ts-k{background:var(--accent)}
.theme-sw[aria-checked="true"] .ts-k i{transform:translateX(-18px)}
.theme-sw[aria-checked="true"] .ts-ic{background:var(--nv);}
.theme-btn{border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:var(--r-pill);width:34px;height:34px;display:grid;place-items:center;cursor:pointer;font-size:16px;padding:0}
</style>
</head><body>

<div class="shell">
<aside class="side">
  <div class="brand"><i></i><span>میز وقت سفارت</span></div>
  <nav class="tabs" id="tabs" role="tablist">
    <div class="cap">منو</div>
    <button class="tab" role="tab" data-tab="pass" type="button"><svg viewBox="0 0 24 24"><path d="M4 13h4l2 3h4l2-3h4"/><path d="M5.5 5h13L20 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5z"/></svg>در انتظار ثبت<span class="n" id="nPass"></span></button>
    <button class="tab" role="tab" data-tab="wl" type="button"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="M9 15l2 2 4-4"/></svg>ویت‌لیست و وقت‌ها<span class="n" id="nWl"></span></button>
    <button class="tab" role="tab" data-tab="countries" type="button"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 3 2.8 15 0 18M12 3c-2.8 3-2.8 15 0 18"/></svg>کشورها</button>
    <button class="tab" role="tab" data-tab="res" type="button"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>ایمیل و شماره</button>
    <button class="tab" role="tab" data-tab="people" type="button"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M16 4.5a3.3 3.3 0 0 1 0 6.5M18 14.8c1.9.7 3.1 2.4 3.5 5.2"/></svg>مسافران<span class="n" id="nPeople"></span></button>
  </nav>
  <button class="theme-sw" type="button" data-act="theme" role="switch" aria-checked="false"><span class="ts-ic" aria-hidden="true">🌙</span><span class="ts-t">حالت شب</span><span class="ts-k" aria-hidden="true"><i></i></span></button>
  <div class="side-foot"><a href="?api=backup" style="color:var(--accent);font-weight:700">دانلود بک‌آپ</a><a href="?logout=1" style="color:var(--red-ink);font-weight:700">خروج</a></div>
</aside>
<div class="main"><div class="mtop"><div class="brand"><i></i><span>میز وقت سفارت</span></div><div class="links"><button class="theme-btn" type="button" data-act="theme" aria-label="حالت شب / روز">🌙</button><a href="?api=backup" style="color:var(--accent)">بک‌آپ</a><a href="?logout=1" style="color:var(--red-ink)">خروج</a></div></div><div class="wrap">
  <div id="banner" class="banner" hidden></div>
  <main id="view"><div class="empty">در حال بارگذاری…</div></main>
</div></div>
</div>

<div id="scrim" class="scrim" hidden></div>
<aside id="sheet" class="sheet" hidden role="dialog" aria-modal="true" aria-labelledby="sheetTitle">
  <header><h3 id="sheetTitle"></h3><button class="btn sm" type="button" id="sheetClose">بستن</button></header>
  <form id="sheetForm" class="body" autocomplete="off"></form>
  <footer><div id="sheetLeft"></div><div style="display:flex;gap:8px"><button class="btn" type="button" id="sheetCancel">انصراف</button><button class="btn primary" type="submit" form="sheetForm" id="sheetSave">ذخیره</button></div></footer>
</aside>
<div id="dz" class="dz-scrim" hidden><div class="dz" role="alertdialog" aria-modal="true" aria-labelledby="dzTitle" aria-describedby="dzBody">
  <div class="dz-ic">!</div><h3 id="dzTitle"></h3><div id="dzBody" class="dz-body"></div>
  <label class="dz-lbl" for="dzInput" id="dzLbl"></label><input id="dzInput" class="dz-in" autocomplete="off" dir="ltr">
  <div class="dz-acts"><button type="button" class="btn" id="dzCancel">انصراف</button><button type="button" class="btn dz-go" id="dzGo" disabled>حذف کامل</button></div>
</div></div>
<div id="toast" class="toast" hidden></div>

<script>
(() => {
"use strict";
// ---------- server bridge (Hostinger) ----------
let SERVER_AI = false;
const API = async (a, body) => {
  const opt = body === undefined ? {credentials:"same-origin", headers:{"X-Req":"1"}} : {method:"POST", credentials:"same-origin", headers:{"Content-Type":"application/json", "X-Req":"1"}, body:JSON.stringify(body)};
  let r; try { r = await fetch("?api=" + a, opt); } catch { throw {code:"unavailable", message:"اتصال به سرور برقرار نشد"}; }
  if (r.status === 401) { location.reload(); throw {code:"unavailable", message:"login"}; }
  let j = {}; try { j = await r.json(); } catch { throw {code:"unavailable", message:"پاسخ نامعتبر از سرور"}; }
  if (!r.ok || j.error) throw {code:j.code || "unavailable", message:j.error || "خطا"};
  return j;
};
const store = {data:{}, subs:new Set()};
const newId = () => [...crypto.getRandomValues(new Uint8Array(10))].map(b => b.toString(16).padStart(2, "0")).join("");
function snapOf(s){
  let arr = Object.entries(store.data[s.col] || {}).map(([id, d]) => ({id, d}));
  if (s.order){ const [f, dir] = s.order; arr.sort((a,b) => String(a.d[f] ?? "").localeCompare(String(b.d[f] ?? "")) * (dir === "desc" ? -1 : 1)); }
  if (s.lim) arr = arr.slice(0, s.lim);
  const docs = arr.map(x => ({id:x.id, exists:true, data:() => x.d}));
  return {docs, size:docs.length, empty:!docs.length};
}
function emit(){ for (const s of store.subs) s.next(snapOf(s)); }
async function refresh(){ const j = await API("all"); store.data = j.data || {}; SERVER_AI = !!j.ai; emit(); }
// Undo journal: while an undoable action runs, the first prior state of every touched doc is kept.
let journal = null;
const jot = (col, id) => { if (journal && !journal.some(j => j.col === col && j.id === id)) { const d = store.data[col]?.[id]; journal.push({col, id, prev:d ? JSON.parse(JSON.stringify(d)) : null}); } };
function docRef(col, id){ return {id, path:col + "/" + id,
  async set(data){ jot(col, id); await API("set", {col, id, data}); (store.data[col] = store.data[col] || {})[id] = {...data}; emit(); },
  async update(data){ jot(col, id); const r = await API("update", {col, id, data}); (store.data[col] = store.data[col] || {})[id] = r.doc; emit(); },
  async delete(confirm){ jot(col, id); await API("delete", {col, id, confirm: confirm || ""}); if (store.data[col]) delete store.data[col][id]; emit(); },
  async get(){ const d = store.data[col]?.[id]; return {id, exists:!!d, data:() => d}; } }; }
function query(col, order = null, lim = null){ return {path:col,
  orderBy:(f, dir = "asc") => query(col, [f, dir], lim), limit:n => query(col, order, n),
  doc:id => docRef(col, id || newId()),
  async add(data){ const r = docRef(col, newId()); await r.set(data); return r; },
  onSnapshot(next){ const s = {col, order, lim, next}; store.subs.add(s); setTimeout(() => next(snapOf(s)), 0); return () => store.subs.delete(s); } }; }
const makeDb = () => ({collection:col => query(col), doc:path => { const [c, i] = path.split("/"); return docRef(c, i); }});
const makeAssets = () => ({
  async upload(file, opt){ const fd = new FormData(); fd.append("file", file);
    let r; try { r = await fetch("?api=upload", {method:"POST", body:fd, credentials:"same-origin", headers:{"X-Req":"1"}}); } catch { throw {code:"upstream_error"}; }
    let j = {}; try { j = await r.json(); } catch {}
    if (!r.ok || j.error) throw {code:j.code || "upstream_error", message:j.error || ""};
    return {id:j.id, url:"?api=file&id=" + j.id, contentType:j.type, sizeBytes:j.size}; },
  async delete(id){ return API("deletefile", {id}); } });

const FRESH = 30, STALE = 60, CAP = 5, KEEP_DAYS = 30;   // passports are deleted 30 days after upload
const CATALOG = [["GR","یونان"],["IT","ایتالیا"],["FR","فرانسه"],["CZ","چک"],["FI","فنلاند"],["ES","اسپانیا"],["DE","آلمان"],["NL","هلند"],["BE","بلژیک"],["AT","اتریش"],["CH","سوئیس"],["PT","پرتغال"],["PL","لهستان"],["HU","مجارستان"],["SE","سوئد"],["DK","دانمارک"],["NO","نروژ"],["MT","مالت"],["CY","قبرس"],["HR","کرواسی"],["SI","اسلوونی"],["SK","اسلواکی"],["LU","لوکزامبورگ"],["LV","لتونی"],["LT","لیتوانی"],["EE","استونی"],["IS","ایسلند"],["BG","بلغارستان"],["RO","رومانی"],["UK","بریتانیا"],["US","آمریکا"],["CA","کانادا"],["AU","استرالیا"]];
const ST_LABEL = {active:"فعال", check:"باید چک شود", unused:"استفاده نشده", none:"پاک شده / ندارد"};
const P_STATUS = {waitlist:"Waitlist", booked:"وقت گرفت", removed:"اتمام کار"};   // "removed" = work finished; kept 14 days, then deleted
const P_OPTS = Object.entries(P_STATUS);
const DONE_DAYS = 14;
const LOG = {login_ok:"ورود موفق", no_account:"اکانت پاک شده بود", created:"اکانت ساخته شد", phone_taken:"شماره تکراری بود", email_taken:"ایمیل قبلاً اکانت داشت", added:"مسافر اضافه شد", booked:"وقت گرفته شد", status:"تغییر وضعیت مسافر", edit:"ویرایش اکانت", upload:"پاسپورت آپلود شد", deleted:"حذف", sim_swap:"شماره جابجا شد"};

const $ = s => document.querySelector(s);
const esc = v => String(v ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const faN = n => Number(n).toLocaleString("fa-IR");
const today = () => { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,10); };
const ago = iso => iso ? Math.round((Date.parse(today() + "T00:00:00Z") - Date.parse(iso + "T00:00:00Z")) / 864e5) : null;
const rel = iso => { const d = ago(iso); if (d === null) return "—"; if (d === 0) return "امروز"; if (d === 1) return "دیروز"; if (d === -1) return "فردا"; return d > 0 ? faN(d) + " روز پیش" : faN(-d) + " روز دیگر"; };
const TILE = ["#20c3b0","#9b5de5","#ffb020","#4cb8ff","#6c6cff","#ff7a45","#ff5d8f","#c77dff","#2fd0e8","#28c47e"];
const colorOf = pc => { const i = portalsList().findIndex(p => p.code === pc); return TILE[(i < 0 ? 0 : i) % TILE.length]; };

// ---------- flags (inline SVG, 30x20) ----------
const _r = (x, y, w, hh, c) => `<rect x="${x}" y="${y}" width="${w}" height="${hh}" fill="${c}"/>`;
const vtri = (a, b, c) => _r(0,0,10,20,a) + _r(10,0,10,20,b) + _r(20,0,10,20,c);
const htri = (a, b, c) => _r(0,0,30,6.67,a) + _r(0,6.67,30,6.67,b) + _r(0,13.33,30,6.67,c);
const nordic = (bg, cr, inner) => _r(0,0,30,20,bg) + _r(8,0,5,20,cr) + _r(0,7.5,30,5,cr) + (inner ? _r(9.5,0,2,20,inner) + _r(0,9,30,2,inner) : "");
const FLAGS = {
  GR: () => { let s = ""; for (let i = 0; i < 9; i++) s += _r(0, i * 20/9, 30, 20/9 + .05, i % 2 ? "#fff" : "#0D5EAF"); return s + _r(0,0,11.1,11.1,"#0D5EAF") + _r(4.44,0,2.22,11.1,"#fff") + _r(0,4.44,11.1,2.22,"#fff"); },
  IT: () => vtri("#009246","#fff","#CE2B37"), FR: () => vtri("#0055A4","#fff","#EF4135"), BE: () => vtri("#111","#FDDA24","#EF3340"),
  RO: () => vtri("#002B7F","#FCD116","#CE1126"), DE: () => htri("#111","#DD0000","#FFCE00"), NL: () => htri("#AE1C28","#fff","#21468B"),
  AT: () => htri("#ED2939","#fff","#ED2939"), HU: () => htri("#CE2939","#fff","#477050"), LU: () => htri("#ED2939","#fff","#00A1DE"),
  LT: () => htri("#FDB913","#006A44","#C1272D"), EE: () => htri("#0072CE","#111","#fff"), BG: () => htri("#fff","#00966E","#D62612"),
  HR: () => htri("#FF0000","#fff","#171796") + _r(12,4.5,6,7,"#fff") + _r(12,4.5,2,2.3,"#FF0000") + _r(16,4.5,2,2.3,"#FF0000") + _r(14,6.8,2,2.3,"#FF0000") + _r(12,9.1,2,2.4,"#FF0000") + _r(16,9.1,2,2.4,"#FF0000"),
  SI: () => htri("#fff","#005DA4","#ED1C24") + `<path d="M6 3.5h6v4.5c0 2.2-1.5 3.6-3 4.2-1.5-.6-3-2-3-4.2z" fill="#005DA4" stroke="#ED1C24" stroke-width=".6"/>`,
  SK: () => htri("#fff","#0B4EA2","#EE1C25") + `<path d="M6 4h7v5.5c0 2.6-1.7 4.2-3.5 5-1.8-.8-3.5-2.4-3.5-5z" fill="#EE1C25" stroke="#fff" stroke-width=".7"/><path d="M9.5 6v6M8 8h3" stroke="#fff" stroke-width="1"/>`,
  LV: () => _r(0,0,30,8,"#9E3039") + _r(0,8,30,4,"#fff") + _r(0,12,30,8,"#9E3039"),
  ES: () => _r(0,0,30,5,"#AA151B") + _r(0,5,30,10,"#F1BF00") + _r(0,15,30,5,"#AA151B"),
  PL: () => _r(0,0,30,10,"#fff") + _r(0,10,30,10,"#DC143C"),
  CZ: () => _r(0,0,30,10,"#fff") + _r(0,10,30,10,"#D7141A") + `<path d="M0 0L15 10L0 20z" fill="#11457E"/>`,
  FI: () => nordic("#fff","#002F6C"), SE: () => nordic("#006AA7","#FECC00"), DK: () => nordic("#C8102E","#fff"),
  NO: () => nordic("#BA0C2F","#fff","#00205B"), IS: () => nordic("#02529C","#fff","#DC1E35"),
  CH: () => _r(0,0,30,20,"#DA291C") + _r(13,4,4,12,"#fff") + _r(9,8,12,4,"#fff"),
  PT: () => _r(0,0,12,20,"#006600") + _r(12,0,18,20,"#FF0000") + `<circle cx="12" cy="10" r="4" fill="#FFCC00"/><circle cx="12" cy="10" r="2.4" fill="#fff" stroke="#FF0000" stroke-width=".8"/>`,
  MT: () => _r(0,0,15,20,"#fff") + _r(15,0,15,20,"#CF142B") + _r(2,2,3,3,"#999"),
  CY: () => _r(0,0,30,20,"#fff") + `<path d="M8 9c3-3 9-4 14-2-1 2-3 3-5 3.5-3 .5-6 0-9-1.5z" fill="#D57800"/><path d="M11 14c2 1 6 1 8 0" stroke="#4E5B31" stroke-width="1" fill="none"/>`,
  US: () => { let s = ""; for (let i = 0; i < 13; i++) s += _r(0, i * 20/13, 30, 20/13 + .05, i % 2 ? "#fff" : "#B22234"); s += _r(0,0,12,20*7/13,"#3C3B6E"); for (let r = 0; r < 5; r++) for (let c = 0; c < 6; c++) s += `<circle cx="${1 + c * 2}" cy="${1.1 + r * 2.1}" r=".45" fill="#fff"/>`; return s; },
  UK: () => _r(0,0,30,20,"#012169") + `<path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="4"/><path d="M0 0L30 20M30 0L0 20" stroke="#C8102E" stroke-width="1.6"/>` + _r(12,0,6,20,"#fff") + _r(0,7,30,6,"#fff") + _r(13.2,0,3.6,20,"#C8102E") + _r(0,8.2,30,3.6,"#C8102E"),
  CA: () => _r(0,0,7.5,20,"#D80621") + _r(7.5,0,15,20,"#fff") + _r(22.5,0,7.5,20,"#D80621") + `<path d="M15 4l1 2 1.6-.6-.5 3 1.6-1.2.5 1.2 1.3-.3-.6 1.9.8.5-3 2.3.3 1.3-2.4-.3V16h-1.2v-2.3l-2.4.3.3-1.3-3-2.3.8-.5-.6-1.9 1.3.3.5-1.2 1.6 1.2-.5-3 1.6.6z" fill="#D80621"/>`,
  AU: () => _r(0,0,30,20,"#012169") + `<g transform="scale(.5)"><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="4"/><path d="M0 0L30 20M30 0L0 20" stroke="#C8102E" stroke-width="1.6"/>${_r(12,0,6,20,"#fff")}${_r(0,7,30,6,"#fff")}${_r(13.2,0,3.6,20,"#C8102E")}${_r(0,8.2,30,3.6,"#C8102E")}</g><circle cx="7.5" cy="15" r="1.8" fill="#fff"/><circle cx="22" cy="4" r=".9" fill="#fff"/><circle cx="25" cy="8" r=".9" fill="#fff"/><circle cx="19.5" cy="9" r=".9" fill="#fff"/><circle cx="22" cy="16" r="1" fill="#fff"/>`,
};
const flagSVG = pc => `<svg viewBox="0 0 30 20" preserveAspectRatio="none" aria-hidden="true">${(FLAGS[pc] || (() => _r(0,0,30,20,"#dfe4f2")))()}</svg>`;
const FLAG_Y = {GR:"YMin", UK:"YMin", AU:"YMin"};
const flagBand = pc => `<span class="flag-band" aria-hidden="true"><svg viewBox="0 0 30 20" preserveAspectRatio="xMid${FLAG_Y[pc] || "YMid"} slice">${(FLAGS[pc] || (() => _r(0,0,30,20,"#dfe4f2")))()}</svg></span>`;
const flagColors = pc => FLAGS[pc] ? [...new Set((FLAGS[pc]().match(/fill="#[0-9A-Fa-f]{3,6}"/g) || []).map(m => m.slice(6, -1).toUpperCase()))].filter(c => !["#FFF", "#FFFFFF", "#999"].includes(c)).slice(0, 3) : [];
const flagGrad = pc => { const c = flagColors(pc); if (!c.length) return ""; if (c.length === 1) c.push(`color-mix(in srgb,${c[0]} 70%,#0f2547)`); return `linear-gradient(135deg,${c.map((x, i) => `${x} ${Math.round(i * 100 / (c.length - 1))}%`).join(",")})`; };
const flagBg = pc => { const g = flagGrad(pc); return g ? ` style="--g:${g};--gk:${g.replace("linear-gradient(", "linear-gradient(in oklab ")}"` : ""; };
const flagEl = (pc, cls = "") => `<span class="flag ${cls}">${flagSVG(pc)}</span>`;
const pName = pc => (CATALOG.find(c => c[0] === pc) || [pc, pc])[1];
// VFS Global UAE login page per destination. If a country books somewhere else, put its full link in VFS_URL.
const VFS_ISO3 = {GR:"grc",IT:"ita",FR:"fra",CZ:"cze",FI:"fin",ES:"esp",DE:"deu",NL:"nld",BE:"bel",AT:"aut",CH:"che",PT:"prt",PL:"pol",HU:"hun",SE:"swe",DK:"dnk",NO:"nor",MT:"mlt",CY:"cyp",HR:"hrv",SI:"svn",SK:"svk",LU:"lux",LV:"lva",LT:"ltu",EE:"est",IS:"isl",BG:"bgr",RO:"rou",UK:"gbr",CA:"can",AU:"aus"};
const VFS_URL = {};
const vfsUrl = pc => VFS_URL[pc] || (VFS_ISO3[pc] ? `https://visa.vfsglobal.com/are/en/${VFS_ISO3[pc]}/login` : "https://www.vfsglobal.com/");
const vfsBtn = pc => `<a class="btn sm vfs" href="${esc(vfsUrl(pc))}" target="_blank" rel="noopener">باز کردن VFS ${esc(pName(pc))} <span aria-hidden="true">↗</span></a>`;
function normPhone(raw){ let d = String(raw).replace(/[^\d+]/g, ""); if (d.startsWith("00")) d = "+" + d.slice(2); if (/^05\d{8}$/.test(d)) d = "+971" + d.slice(1); else if (/^9715\d{8}$/.test(d)) d = "+" + d; else if (/^5\d{8}$/.test(d)) d = "+971" + d; return d; }
const localPhone = p => { const m = /^\+971(\d{9})$/.exec(p || ""); return m ? "0" + m[1] : (p || ""); };
const prettyPhone = p => { const l = localPhone(p); const m = /^(0\d{2})(\d{3})(\d{4})$/.exec(l); return m ? `${m[1]} ${m[2]} ${m[3]}` : l; };
function guessOperator(p){ const m = /^\+971(5\d)/.exec(p || ""); if (!m) return ""; return ["50","54","56"].includes(m[1]) ? "Etisalat" : ["52","55","58"].includes(m[1]) ? "du" : ""; }
function nextCode(prefix, ids){ let max = 0; for (const id of ids){ const m = new RegExp("^" + prefix + "-(\\d+)$").exec(id); if (m) max = Math.max(max, +m[1]); } return prefix + "-" + String(max + 1).padStart(2, "0"); }
let toastT; function toast(msg){ const t = $("#toast"); t.textContent = msg; t.hidden = false; clearTimeout(toastT); toastT = setTimeout(() => t.hidden = true, 2800); }
// Toast with a «برگرد» button; undo() runs once, within 10 seconds.
function toastUndo(msg, undo){
  const t = $("#toast"); t.innerHTML = `<span>${esc(msg)}</span><button type="button" class="undo">برگرد</button>`; t.hidden = false;
  clearTimeout(toastT); toastT = setTimeout(() => t.hidden = true, 10000);
  t.querySelector(".undo").onclick = async e => { e.currentTarget.disabled = true; clearTimeout(toastT); t.hidden = true; await undo(); };
}
async function undoable(fn){
  const t = $("#toast"); journal = [];
  try { await fn(); } finally {
    const j = journal; journal = null;
    if (j.length) toastUndo(t.hidden ? "انجام شد" : t.textContent, async () => {
      for (const x of j.reverse()) await write(() => x.prev ? db.doc(x.col + "/" + x.id).set(x.prev) : db.doc(x.col + "/" + x.id).delete());
      toast("برگشت؛ همه‌چیز مثل قبل شد"); });
  }
}
const lsGet = k => { try { return localStorage.getItem(k); } catch { return null; } };
const lsSet = (k, v) => { try { localStorage.setItem(k, v); } catch {} };
const accId = (pc, em) => `${pc}__${em}`, regId = (pc, sim) => `${pc}__${sim}`;

const S = {sims:new Map(), emails:new Map(), portals:new Map(), accounts:new Map(), regs:new Map(), people:[], logs:[], passports:[]};
const loaded = {sims:0, emails:0, portals:0, accounts:0, regs:0, people:0, logs:0, passports:0};
let db = null, canWrite = true, assets = null, sampler = null, imgLimits = null;
let passFilter = "", passStatus = "waiting", passQ = "";
const picked = new Set();
let tab = ["pass","wl","countries","res","people"].includes(lsGet("tab4")) ? lsGet("tab4") : "pass", country = lsGet("country3") || "", peopleQ = "", logPortal = "";

const emailsAll = () => [...S.emails.values()].filter(e => e.status !== "inactive").sort((a,b) => a.code.localeCompare(b.code));
const simsAll = () => [...S.sims.values()].filter(m => m.status !== "inactive").sort((a,b) => a.code.localeCompare(b.code));
const portalsList = () => [...S.portals.values()].sort((a,b) => (a.order ?? 99) - (b.order ?? 99) || a.code.localeCompare(b.code));
const occupants = id => S.people.filter(p => p.accountId === id && p.status !== "removed");

// status of an email on one VFS country
function accStatus(a){
  if (!a || a.status === "unknown" || !a.status) return "unused";
  if (a.status === "none") return "none";
  const d = ago(a.lastVerified);
  return d !== null && d <= FRESH ? "active" : "check";
}
// what a phone is doing on one VFS country
function phoneOn(pc, sim){
  const acc = [...S.accounts.values()].find(a => a.portal === pc && a.simId === sim && accStatus(a) !== "none" && accStatus(a) !== "unused");
  if (acc) return {kind:"acc", acc};
  const r = S.regs.get(regId(pc, sim));
  if (r?.status === "registered") return {kind:"orphan"};
  return {kind:"free"};
}
// accounts a phone has created, across all countries
function phoneAccounts(sim){
  return [...S.accounts.values()].filter(a => a.simId === sim && (accStatus(a) === "active" || accStatus(a) === "check"));
}
function countryStats(pc){
  const s = {active:0, check:0, unused:0, none:0, free:0, people:0, freePhones:0};
  for (const e of emailsAll()){
    const id = accId(pc, e.code), st = accStatus(S.accounts.get(id)); s[st]++;
    if (st === "active") s.free += Math.max(0, CAP - occupants(id).length);
  }
  s.people = S.people.filter(p => p.portal === pc && (p.status === "waitlist" || p.status === "booked")).length;
  s.freePhones = simsAll().filter(m => phoneOn(pc, m.code).kind === "free").length;
  return s;
}

// ---------- rendering ----------
let lastView = "";
const isDark = () => { const t = document.documentElement.dataset.theme; return t ? t === "dark" : matchMedia("(prefers-color-scheme: dark)").matches; };
function syncTheme(){ const d = isDark();
  document.querySelectorAll(".theme-sw").forEach(b => b.setAttribute("aria-checked", String(d)));
  document.querySelectorAll(".theme-btn").forEach(b => { b.textContent = d ? "☀️" : "🌙"; b.setAttribute("aria-label", d ? "حالت روز" : "حالت شب"); }); }
function render(){
  syncTheme();
  document.querySelectorAll(".tab").forEach(b => b.setAttribute("aria-selected", String(b.dataset.tab === tab)));
  const live = S.people.filter(p => p.status === "waitlist" || p.status === "booked").length;
  $("#nPeople").textContent = live ? faN(live) : "";
  const wlN = S.people.filter(p => p.status === "waitlist").length;
  $("#nWl").textContent = wlN ? faN(wlN) : "";
  const waitingP = S.passports.filter(x => x.status === "waiting").length;
  $("#nPass").textContent = waitingP ? faN(waitingP) : "";
  if (db && Object.values(loaded).some(v => !v)) { $("#view").innerHTML = `<div class="empty">در حال بارگذاری…</div>`; lastView = ""; return; }
  let html;
  if (tab === "countries") html = country && S.portals.has(country) ? renderCountry(country) : renderCountries();
  else html = ({pass:renderQueue, wl:renderWl, res:renderRes, people:renderPeople}[tab] || renderQueue)();
  html = flowBar() + html;
  if (html !== lastView) { $("#view").innerHTML = html; lastView = html; }
}

const legendHTML = () => `<div class="legend"><span><span class="st st-active">${ST_LABEL.active}</span> ورود تأییدشده در ${faN(FRESH)} روز اخیر</span><span><span class="st st-check">${ST_LABEL.check}</span> اکانت داشت ولی بیش از ${faN(FRESH)} روز واردش نشده‌اید</span><span><span class="st st-unused">${ST_LABEL.unused}</span> روی این کشور سابقه‌ای ثبت نشده</span><span><span class="st st-none">${ST_LABEL.none}</span> VFS گفت اکانت نیست</span></div>`;

function renderCountries(){
  let h = `<div class="page-h"><div><h1>کشورها</h1></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" type="button" data-act="sim">+ شماره</button><button class="btn" type="button" data-act="email">+ ایمیل</button></div></div>`;
  h += `<div class="cgrid">${portalsList().map(p => { const s = countryStats(p.code), wp = S.passports.filter(x => x.portal === p.code && x.status === "waiting").length;
    const g = flagGrad(p.code);
    return `<button class="ccard${g ? " fg" : ""}" type="button" data-act="open" data-id="${esc(p.code)}" style="--cc:${g ? flagColors(p.code)[0] : colorOf(p.code)}${g ? `;--g:${g};--gk:${g.replace("linear-gradient(", "linear-gradient(in oklab ")}` : ""}">
      <div class="ccard-h">${flagEl(p.code)}<div><b>${esc(pName(p.code))}</b><small>VFS ${esc(p.code)}</small></div></div>
      <div class="big"><b>${faN(s.active)}</b><span>اکانت فعال</span></div>
      <div class="minis"><div class="mini"><b>${faN(s.check)}</b><span>چک شود</span></div><div class="mini"><b>${faN(s.free)}</b><span>جای خالی</span></div><div class="mini"><b>${faN(s.people)}</b><span>مسافر</span></div></div>
      <div class="ccard-f"><span>${faN(s.unused + s.none)} ایمیل آزاد</span><span>${faN(s.freePhones)} شماره آزاد</span>${wp ? `<span>${faN(wp)} پاسپورت منتظر</span>` : ""}</div></button>`; }).join("")}
    <button class="ccard add" type="button" data-act="addportal">+ افزودن کشور</button></div>`;
  return h;
}

function globalAlerts(pcOnly){
  const out = [];
  for (const a of S.accounts.values()){
    if (pcOnly && a.portal !== pcOnly) continue;
    const live = occupants(a.id).filter(p => p.status === "waitlist" || p.status === "booked").length;
    const d = ago(a.lastVerified);
    if (live && a.status === "active" && d !== null && d > FRESH)
      out.push({lvl: d > STALE ? "red" : "amber", t:`${pName(a.portal)} · ${S.emails.get(a.emailId)?.address || a.emailId}`, d:`${faN(live)} مسافر فعال دارد و ${faN(d)} روز است واردش نشده‌اید${d > STALE ? " — شاید پاک شده باشد" : ""}.`, id:a.id});
  }
  for (const p of S.people){
    if (pcOnly && p.portal !== pcOnly) continue;
    if (p.status === "booked" && p.apptDate){ const d = -ago(p.apptDate); if (d >= 0 && d <= 7) out.push({lvl:"teal", t:`وقت ${p.name} · ${pName(p.portal)}`, d:`${p.apptDate} (${rel(p.apptDate)})`, id:p.accountId}); }
  }
  return out.map(a => `<div class="alert lvl-${a.lvl}"><div><div class="t">${esc(a.t)}</div><div class="d">${esc(a.d)}</div></div><button class="btn sm" type="button" data-act="acc" data-id="${esc(a.id)}">باز کردن</button></div>`).join("");
}

const capPill = n => n >= CAP ? `<span class="capx full">پر · ${faN(CAP)} مسافر</span>` : n === 0 ? `<span class="capx empty0">بدون مسافر · ${faN(CAP)} جای خالی</span>` : `<span class="capx">${faN(n)} مسافر · ${faN(CAP - n)} جای خالی</span>`;
const slotsBar = n => `<span class="slots">${Array.from({length:CAP}, (_, i) => `<i class="${i < n ? "on" : ""}"></i>`).join("")}</span>`;
const copyBtn = t => `<button class="copy" type="button" data-copy="${esc(t)}">کپی</button>`;

function renderCountry(pc){
  const s = countryStats(pc);
  let h = `<div style="--cc:${flagColors(pc)[0] || colorOf(pc)}"><div class="hero"${flagBg(pc)}><div class="hero-top"><div><button class="crumb" type="button" data-act="back"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>همه کشورها</button><h1>${flagEl(pc)}${esc(pName(pc))}</h1></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn primary" type="button" data-act="upload" data-id="${esc(pc)}">+ آپلود پاسپورت</button></div></div>
    <div class="stats"><div class="stat active"><b>${faN(s.active)}</b><span>اکانت فعال</span></div><div class="stat check"><b>${faN(s.check)}</b><span>باید چک شود</span></div><div class="stat"><b>${faN(s.free)}</b><span>جای خالی مسافر</span></div><div class="stat"><b>${faN(s.people)}</b><span>مسافر در جریان</span></div><div class="stat"><b>${faN(s.unused + s.none)}</b><span>ایمیل آزاد</span></div><div class="stat"><b>${faN(s.freePhones)}</b><span>شماره آزاد</span></div></div></div>`;
  h += globalAlerts(pc);
  h += `<section class="sec"><div class="sec-h"><h2>اکانت‌های ${esc(pName(pc))}</h2><small class="sec-hint">روی هر اکانت بزنید تا مسافرهایش را ببینید</small></div>${accountsList(pc) || `<div class="empty">هنوز اکانتی برای ${esc(pName(pc))} نیست. پاسپورت آپلود کنید؛ سایت ایمیل و شماره برای ساخت اکانت پیشنهاد می‌دهد.</div>`}</section>`;
  h += `</div>`;
  return h;
}

const liveAcc = a => a && S.emails.has(a.emailId) && (accStatus(a) === "active" || accStatus(a) === "check");
function accountsList(pc){
  const accs = [...S.accounts.values()].filter(a => a.portal === pc && liveAcc(a)).sort((x, y) => x.emailId.localeCompare(y.emailId));
  if (!accs.length) return "";
  return `<div class="accl">${accs.map(a => { const e = S.emails.get(a.emailId), m = a.simId ? S.sims.get(a.simId) : null, occ = occupants(a.id), st = accStatus(a);
    return `<button type="button" class="accb" data-act="acc" data-id="${esc(a.id)}">
      <span class="accb-c"><span class="mono accb-e">${esc(e?.address || a.emailId)}</span><span class="mono accb-p">${m ? esc(prettyPhone(m.number)) : "بدون شماره"}</span></span>
      <span class="accb-n">${occ.length ? occ.map(p => `<i>${esc(p.name)}</i>`).join("") : `<em>بدون مسافر</em>`}</span>
      <span class="accb-o ${occ.length >= CAP ? "full" : ""}">${st === "check" ? `<small>⚠ چک شود</small>` : ""}<b>${faN(occ.length)}/${faN(CAP)}</b></span></button>`; }).join("")}</div>`;
}
// popup for one phone or email: every country it is on, the paired email/phone and the travellers
function openRes(kind, code){
  const x = kind === "sim" ? S.sims.get(code) : S.emails.get(code); if (!x) return;
  const accs = [...S.accounts.values()].filter(a => liveAcc(a) && (kind === "sim" ? a.simId === code : a.emailId === code));
  const taken = kind === "sim" ? portalsList().filter(p => phoneOn(p.code, code).kind === "orphan") : [];
  const val = kind === "sim" ? prettyPhone(x.number) : x.address;
  openSheet(kind === "sim" ? "شماره" : "ایمیل",
    `<div class="res-big"><span class="mono">${esc(val)}</span>${copyBtn(kind === "sim" ? localPhone(x.number) : x.address)}</div>
    ${accs.length ? accs.map(a => { const occ = occupants(a.id), other = kind === "sim" ? (S.emails.get(a.emailId)?.address || a.emailId) : (a.simId ? prettyPhone(S.sims.get(a.simId)?.number || "") : "بدون شماره");
      return `<div class="res-acc"><div class="res-acc-h">${flagEl(a.portal, "md")}<b>${esc(pName(a.portal))}</b><span class="accb-o ${occ.length >= CAP ? "full" : ""}"><b>${faN(occ.length)}/${faN(CAP)}</b></span></div>
        <div class="res-acc-k">${kind === "sim" ? "با ایمیل" : "با شماره"}</div><div class="mono res-acc-v">${esc(other)}</div>
        <div class="res-acc-k">مسافرها</div><div class="accb-n">${occ.length ? occ.map(p => `<i>${esc(p.name)}</i>`).join("") : `<em>بدون مسافر</em>`}</div>
        <button class="btn sm" type="button" data-act="acc" data-id="${esc(a.id)}">باز کردن اکانت</button></div>`; }).join("") : `<div class="empty">هنوز روی هیچ کشوری اکانت ندارد.</div>`}
    ${taken.length ? `<div class="res-acc-k">ثبت‌شده در VFS بدون اکانت ما</div><div class="chips">${taken.map(p => `<button type="button" class="rs-chip st-reg" data-act="phonefree" data-id="${esc(p.code + "__" + code)}">${flagEl(p.code, "sm")}<b>${esc(pName(p.code))}</b><small>×</small></button>`).join("")}</div>` : ""}`,
    null, `<button class="btn sm" type="button" data-act="${kind}" data-id="${esc(code)}">ویرایش</button>`);
}
function emailRow(pc, {e, id, a, st}){
  const sim = a?.simId ? S.sims.get(a.simId) : null, occ = occupants(id).length, d = ago(a?.lastVerified);
  let acts;
  if (st === "active") acts = `<button class="btn sm teal" type="button" data-act="addpeople" data-id="${esc(id)}" ${occ >= CAP ? "disabled" : ""}>+ مسافر</button><button class="btn sm" type="button" data-act="acc" data-id="${esc(id)}">جزئیات</button>`;
  else if (st === "check") acts = `<button class="btn sm teal" type="button" data-act="ok" data-id="${esc(id)}">وارد شدم</button><button class="btn sm ghost-red" type="button" data-act="gone" data-id="${esc(id)}">پاک شده بود</button><button class="btn sm" type="button" data-act="acc" data-id="${esc(id)}">جزئیات</button>`;
  else acts = `<button class="btn sm amber" type="button" data-act="newacc" data-id="${esc(pc)}" data-email="${esc(e.code)}">ثبت اکانت</button>${st === "unused" ? `<button class="btn sm ghost-red" type="button" data-act="gone" data-id="${esc(id)}">اکانت ندارد</button>` : ""}`;
  const when = st === "active" || st === "check" ? (a.lastVerified ? `آخرین ورود ${rel(a.lastVerified)}${d > STALE ? " — احتمالاً پاک شده" : ""}` : "تاریخ ورود ثبت نشده") : (st === "none" && a?.updated ? `ثبت ${rel(a.updated)}` : "");
  return `<div class="row ${st === "active" || st === "check" ? "" : "dim"}">
    <div class="who c-main"><span class="av" style="--avc:${st === "active" ? "#12936a" : st === "check" ? "#c07d00" : st === "none" ? "#d8394a" : "#7a84a6"}">${esc(e.code.replace("EM-",""))}</span><div class="cellx"><span><span class="mono">${esc(e.address)}</span>${copyBtn(e.address)}</span><small>${esc(e.code)}${when ? " · " + esc(when) : ""}</small></div></div>
    <div class="cellx c-2"><span class="lbl-m">شماره: </span>${sim ? `<span><span class="tag">${esc(sim.code)}</span> <span class="mono">${esc(prettyPhone(sim.number))}</span>${copyBtn(localPhone(sim.number))}</span>` : `<small>—</small>`}</div>
    <div class="c-status"><span class="st st-${st}">${ST_LABEL[st]}</span></div>
    <div class="cellx c-3">${st === "active" || st === "check" ? capPill(occ) : ""}</div>
    <div class="acts">${acts}</div></div>`;
}

function evHTML(l){
  const em = S.emails.get(l.emailId)?.address, ph = S.sims.get(l.simId);
  return `<div class="ev"><div class="date">${esc(l.date)}</div><div><b>${esc(LOG[l.type] || l.type)}</b> · ${esc(pName(l.portal))}<p>${[em, ph && localPhone(ph.number), l.note].filter(Boolean).map(esc).join(" · ")}</p></div><span class="pill">${rel(l.date)}</span></div>`;
}

function credCell(p){
  const a = S.accounts.get(p.accountId), m = a?.simId ? S.sims.get(a.simId) : null;
  return `<span class="mono" style="font-size:12.5px">${esc(S.emails.get(p.emailId)?.address || p.emailId)}</span>${m ? `<small class="mono">${esc(prettyPhone(m.number))}</small>` : ""}`;
}
const blobUrl = id => "?api=file&id=" + encodeURIComponent(id);
const addDaysISO = (iso, n) => { const d = new Date((iso || today()) + "T00:00:00Z"); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0,10); };
function expiryChip(exp){
  const left = -ago(exp);
  if (left === null || isNaN(left)) return "";
  const cls = left < 0 ? "st-none" : left < 183 ? "st-check" : "st-unused";
  const txt = left < 0 ? "پاسپورت منقضی شده" : left < 183 ? "کمتر از ۶ ماه اعتبار" : "انقضا";
  return `<span class="st ${cls}" style="align-self:flex-start">${txt} · <span class="mono">${esc(exp)}</span></span>`;
}
function passCard(x){
  const a = x.accountId ? S.accounts.get(x.accountId) : null, m = a?.simId ? S.sims.get(a.simId) : null;
  const isImg = (x.contentType || "").startsWith("image/");
  const left = x.expiry ? -ago(x.expiry) : null;
  const expCls = left === null ? "" : left < 0 ? "bad" : left < 183 ? "warn" : "";
  const expNote = left === null ? "" : left < 0 ? " <em>منقضی</em>" : left < 183 ? " <em>کمتر از ۶ ماه</em>" : "";
  return `<div class="pcard ${picked.has(x.id) ? "sel" : ""}">
    ${x.status === "waiting" && canWrite ? `<input type="checkbox" class="pick" data-pick="${esc(x.id)}" ${picked.has(x.id) ? "checked" : ""} aria-label="انتخاب ${esc(x.name || x.fileName)}">` : ""}
    <a class="thumb" href="${esc(blobUrl(x.assetId))}" target="_blank" rel="noopener">${isImg ? `<img src="${esc(blobUrl(x.assetId))}" alt="" loading="lazy">` : `<div class="pdf"><svg viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg><b>PDF</b></div>`}</a>
    <div class="pb">
      <div class="pc-top">${x.status === "assigned" ? `<span class="st st-active">در اکانت</span>` : `<span class="st st-check">منتظر اکانت</span>`}<span class="tag" style="font-family:var(--f-body)">${esc(pName(x.portal))}</span></div>
      <div class="pname">${x.name ? esc(x.name) : `<span style="color:var(--muted);font-weight:600">اسم وارد نشده</span>`}</div>
      <dl class="pdl">
        <dt>ملیت</dt><dd>${esc(x.nationality || "—")}</dd>
        <dt>پاسپورت</dt><dd><span class="mono">${esc(x.passportNo || "—")}</span></dd>
        <dt>انقضا</dt><dd class="${expCls}"><span class="mono">${esc(x.expiry || "—")}</span>${expNote}</dd>
      </dl>
      ${x.status === "assigned" ? `<div class="pacc"><span class="mono">${esc(S.emails.get(x.emailId)?.address || x.emailId || "")}</span>${m ? `<span class="mono">${esc(prettyPhone(m.number))}</span>` : ""}</div>` : ""}
      <div class="pdel">حذف خودکار ${rel(addDaysISO(x.uploadedAt, KEEP_DAYS))}</div>
    </div>
    <div class="pact"><button class="btn sm" type="button" data-act="editpass" data-id="${esc(x.id)}">ویرایش</button></div></div>`;
}
function renderPassports(){
  const q = passQ.trim().toLowerCase();
  const list = S.passports.filter(x => (!passFilter || x.portal === passFilter) && (!passStatus || x.status === passStatus) && (!q || [x.name, x.passportNo, x.nationality, x.fileName].join(" ").toLowerCase().includes(q)))
    .sort((a,b) => (b.at || "").localeCompare(a.at || ""));
  const chip = (v, t) => `<button class="tab" style="color:${passFilter === v ? "var(--navy)" : "var(--ink)"};background:${passFilter === v ? "var(--cyan)" : "var(--surface)"};border:1px solid var(--line)" type="button" data-act="pfilter" data-id="${esc(v)}">${t}</button>`;
  const stChip = (v, t) => `<button class="tab" style="color:${passStatus === v ? "var(--navy)" : "var(--ink)"};background:${passStatus === v ? "var(--cyan)" : "var(--surface)"};border:1px solid var(--line)" type="button" data-act="pstatus" data-id="${esc(v)}">${t}</button>`;
  const selCount = [...picked].filter(id => S.passports.some(x => x.id === id && x.status === "waiting")).length;
  let h = `<div class="page-h"><div><h1>پاسپورت‌ها</h1><p>پاسپورت‌هایی که مشتری‌ها فرستاده‌اند. انتخابشان کنید و به یک اکانت اضافه کنید؛ از آن به بعد معلوم است برای هر نفر از کدام ایمیل و شماره استفاده شده. هر پاسپورت ${faN(KEEP_DAYS)} روز بعد از آپلود پاک می‌شود.</p></div>
    <button class="btn primary" type="button" data-act="upload" data-id="${esc(passFilter)}">+ آپلود پاسپورت</button></div>
    <div class="toolbar" style="gap:6px">${chip("", "همه کشورها")}${portalsList().map(p => chip(p.code, esc(pName(p.code)))).join("")}</div>
    <div class="toolbar" style="gap:6px">${stChip("waiting", "منتظر اکانت")}${stChip("assigned", "در اکانت")}${stChip("", "همه")}<input id="passQ" type="search" placeholder="جستجوی اسم یا شماره پاسپورت…" aria-label="جستجوی پاسپورت" value="${esc(passQ)}"></div>`;
  h += `<section class="sec">${list.length ? `<div class="pgrid">${list.map(passCard).join("")}</div>` : `<div class="empty">${S.passports.length ? "چیزی با این فیلتر نیست." : "هنوز پاسپورتی آپلود نشده. عکس‌ها یا PDFهای واتس‌اپ را با «+ آپلود پاسپورت» یکجا بفرستید."}</div>`}</section>`;
  if (selCount) h += `<div class="bulkbar"><span>${faN(selCount)} پاسپورت انتخاب شده</span><div class="btn-row"><button class="btn sm" type="button" data-act="clearpick">لغو انتخاب</button><button class="btn sm amber" type="button" data-act="assign">اضافه به اکانت…</button></div></div>`;
  return h;
}

// ---------- work queue ----------
const skipAcc = new Map();
const lastPlans = {};
function planFor(pc){
  const waiting = S.passports.filter(x => x.portal === pc && x.status === "waiting").sort((a,b) => (a.at || "").localeCompare(b.at || ""));
  const units = [], byG = new Map();
  for (const x of waiting){
    if (x.groupId){ if (!byG.has(x.groupId)) { const u = {items:[], group:x.groupId}; byG.set(x.groupId, u); units.push(u); } byG.get(x.groupId).items.push(x); }
    else units.push({items:[x]});
  }
  const U = [];
  for (const u of units) for (let i = 0; i < u.items.length; i += CAP) U.push({items:u.items.slice(i, i + CAP), group:u.group});
  const skip = skipAcc.get(pc) || new Set();
  const pool = emailsAll().map(e => { const id = accId(pc, e.code), a = S.accounts.get(id), st = accStatus(a);
      return {kind:"existing", id, e, a, st, sim:a?.simId ? S.sims.get(a.simId) : null, occ:occupants(id).length, add:[]}; })
    .filter(x => (x.st === "active" || x.st === "check") && x.occ < CAP && !skip.has(x.id))
    .sort((a,b) => (a.st === b.st ? 0 : a.st === "active" ? -1 : 1) || b.occ - a.occ);
  const leftovers = [];
  for (const u of U){ const t = pool.find(x => CAP - x.occ - x.add.length >= u.items.length); if (t) t.add.push(...u.items); else leftovers.push(u); }
  const freeEmails = emailsAll().filter(e => { const st = accStatus(S.accounts.get(accId(pc, e.code))); return st === "unused" || st === "none"; });
  const freeSims = simsAll().filter(m => phoneOn(pc, m.code).kind === "free");
  const news = []; let cur = null;
  for (const u of leftovers){
    if (!cur || CAP - cur.add.length < u.items.length){ cur = {kind:"new", e:freeEmails[news.length] || null, sim:freeSims[news.length] || null, add:[]}; news.push(cur); }
    cur.add.push(...u.items);
  }
  const steps = [...pool.filter(x => x.add.length), ...news];
  lastPlans[pc] = steps;
  return {waiting, steps};
}
const credLine = (lbl, val, copyVal) => `<div><span class="lbl">${lbl}</span>${val ? `<span class="mono">${esc(val)}</span>${copyBtn(copyVal || val)}` : `<span class="nofree">آزاد نمانده</span>`}</div>`;
function stepCard(pc, s, i, total){
  const ex = s.kind === "existing", ok = ex || (s.e && s.sim);
  const title = ex ? `وارد این اکانت VFS شوید و ${s.add.length > 1 ? `این ${faN(s.add.length)} نفر` : "این مسافر"} را اضافه کنید`
                   : `با این ایمیل و شماره اکانت جدید بسازید و ${s.add.length > 1 ? `این ${faN(s.add.length)} نفر` : "این مسافر"} را اضافه کنید`;
  const sub = ex ? `اکانت موجود · ${faN(s.occ)} مسافر دارد ← بعد از این ${faN(s.occ + s.add.length)} از ${faN(CAP)}` : `اکانت جدید · VFS ${esc(pName(pc))}`;
  const who = s.add.length > 1 ? "مسافرها" : "مسافر";
  const opt = (act, cls, t, d) => `<button type="button" class="res ${cls}" data-act="${act}" data-id="${esc(pc)}|${i}"><b>${t}</b><small>${d}</small></button>`;
  const acts = ex
    ? opt("q_ok", "main", `✓ ${who} را در داشبورد VFS اضافه کردم`, "از این صفحه بیرون می‌روند و در «مسافران» با وضعیت Waitlist ثبت می‌شوند")
      + opt("q_gone", "bad", "وارد شدم ولی اکانت پاک شده بود", "این اکانت «پاک شده» ثبت می‌شود و سیستم اکانت دیگری پیشنهاد می‌دهد")
      + opt("q_skip", "", "نمی‌خواهم از این اکانت استفاده کنم", "فعلاً کنار می‌رود و اکانت بعدی پیشنهاد می‌شود")
    : ok ? opt("q_new", "main", `✓ اکانت را ساختم و ${who} را اضافه کردم`, "اکانت ثبت می‌شود و مسافرها با وضعیت Waitlist به «مسافران» می‌روند")
      + opt("q_phone", "", "VFS گفت این شماره قبلاً ثبت شده", "این شماره برای این کشور کنار می‌رود و شماره بعدی پیشنهاد می‌شود")
      + opt("q_email", "", "VFS گفت این ایمیل قبلاً اکانت دارد", "به جای ساخت، وارد همین اکانت شوید؛ کارت به «ورود به اکانت» تبدیل می‌شود")
    : `<div class="sc-warn" style="flex:1">${!s.e ? "ایمیل آزاد برای این کشور نمانده. " : ""}${!s.sim ? "شماره آزاد برای این کشور نمانده. " : ""}یکی اضافه کنید.</div>${!s.e ? `<button class="btn sm" type="button" data-act="email">+ ایمیل</button>` : ""}${!s.sim ? `<button class="btn sm" type="button" data-act="sim">+ شماره</button>` : ""}`;
  return `<div class="task ${ex ? "" : "newacc"}">
    <div class="task-h"><span class="sc-n">${faN(i + 1)}</span><div><small class="task-k">${total > 1 ? `کار ${faN(i + 1)} از ${faN(total)} · ` : ""}قدم بعدی</small><b>${title}</b><small>${sub}</small></div></div>
    ${ex && s.st === "check" ? `<div class="sc-warn">${s.a?.lastVerified ? `آخرین ورود ${rel(s.a.lastVerified)}` : "آخرین ورود ثبت نشده"} · ممکن است پاک شده باشد</div>` : ""}
    <div class="task-grid">
      <div><div class="task-lbl">مسافران</div><div class="plist2">${s.add.map(prow2).join("")}</div></div>
      <div><div class="task-lbl">${ex ? "ورود به اکانت" : "ساخت اکانت"}</div><div class="sc-cred">${credLine("ایمیل", s.e?.address)}${ex ? (s.sim ? credLine("شماره", prettyPhone(s.sim.number), localPhone(s.sim.number)) : "") : credLine("شماره", s.sim ? prettyPhone(s.sim.number) : "", s.sim ? localPhone(s.sim.number) : "")}</div><div class="vfs-row">${vfsBtn(pc)}</div></div>
    </div>
    <div class="task-acts">${ex || ok ? (() => { const [main, ...rest] = acts.split('<button type="button" class="res').filter(Boolean).map(x => '<button type="button" class="res' + x);
      return `${main}<details class="res-more"><summary>مشکل داشت؟</summary><div class="res-grid">${rest.join("")}</div></details>`; })() : acts}</div></div>`;
}
const STAGES = ["آپلود پاسپورت", "ثبت در اکانت VFS", "Waitlist", "وقت گرفته شد"];
// The four stages of the work, on top of every page: where each traveller is and what comes next.
function flowBar(){
  const n = {pass:S.passports.filter(x => x.status === "waiting").length, wl:S.people.filter(p => p.status === "waitlist").length, bk:S.people.filter(p => p.status === "booked").length};
  const cur = tab === "pass" ? 2 : tab === "wl" ? 3 : 0;
  const st = (i, go, t, sub, cnt) => `<button type="button" class="fl ${i === cur ? "cur" : ""} ${cnt ? "has" : ""}" data-act="flow" data-id="${go}"><span class="fl-n">${faN(i)}</span><span class="fl-t"><b>${t}</b><small>${sub}</small></span>${cnt === null ? `<span class="fl-c plus">+</span>` : `<span class="fl-c">${faN(cnt)}</span>`}</button>`;
  return `<nav class="flow" aria-label="مراحل کار">${[
    st(1, "up", "آپلود پاسپورت", "خواندن خودکار اطلاعات", null),
    st(2, "pass", "ثبت در اکانت VFS", "با ایمیل و شماره پیشنهادی", n.pass),
    st(3, "wl", "Waitlist", "منتظر وقت", n.wl),
    st(4, "wl", "وقت گرفته شد", "تاریخ وقت ثبت شده", n.bk)].join(`<i class="fl-a" aria-hidden="true"></i>`)}</nav>`;
}
const stageBar = cur =>`<div class="stages">${STAGES.map((t, i) => `<div class="stg ${i < cur ? "done" : i === cur ? "cur" : ""}"><span>${i < cur ? "✓" : faN(i + 1)}</span>${t}</div>`).join(`<i></i>`)}</div>`;
function prow2(x){
  const isImg = (x.contentType || "").startsWith("image/");
  const left = x.expiry ? -ago(x.expiry) : null;
  const exp = x.expiry ? `<span class="${left < 0 ? "bad" : left < 183 ? "warn" : ""}">انقضا <span class="mono">${esc(x.expiry)}</span></span>` : "";
  return `<div class="prow2"><a class="fthumb" href="${esc(blobUrl(x.assetId))}" target="_blank" rel="noopener">${isImg ? `<img src="${esc(blobUrl(x.assetId))}" alt="" loading="lazy">` : "PDF"}</a>
    <div><b>${x.name ? esc(x.name) : `<span style="color:var(--muted)">اسم وارد نشده</span>`} ${x.groupId ? `<span class="gtag">گروه</span>` : ""}</b>
      <small>${x.nationality ? `<span>${esc(x.nationality)}</span>` : ""}${x.passportNo ? `<span class="mono">${esc(x.passportNo)}</span>` : ""}${exp}</small></div>
    <button class="btn sm" type="button" data-act="editpass" data-id="${esc(x.id)}">ویرایش</button></div>`;
}
function renderQueue(){
  const liveA = [...S.accounts.values()].filter(a => S.emails.has(a.emailId) && (accStatus(a) === "active" || accStatus(a) === "check"));
  const flagsOf = list => list.length ? `<span class="hx-flags">${[...new Set(list.map(a => a.portal))].map(pc => flagEl(pc, "sm")).join("")}</span>` : `<span class="hx-free">آزاد</span>`;
  const ems = emailsAll(), sims = simsAll();
  const emHTML = ems.map(e => `<li><span class="mono">${esc(e.address)}</span>${flagsOf(liveA.filter(a => a.emailId === e.code))}</li>`).join("");
  const smHTML = sims.map(m => `<li><span class="mono">${esc(prettyPhone(m.number))}</span>${flagsOf(liveA.filter(a => a.simId === m.code))}</li>`).join("");
  const freeE = ems.filter(e => !liveA.some(a => a.emailId === e.code)).length, freeS = sims.filter(m => !liveA.some(a => a.simId === m.code)).length;
  let h = `<section class="fhero">
    <div class="hero-sky"><i class="c1"></i><i class="c2"></i><i class="c3"></i><i class="c4"></i></div>
    <div class="hero-plane">${PLANE_SVG}</div>
    <div class="hero-body">
      <h1>به میز سفارت<br>خوش آمدید</h1>
      <p>${esc(new Intl.DateTimeFormat("fa-IR-u-ca-gregory", {weekday:"long", day:"numeric", month:"long", year:"numeric"}).format(new Date()))}</p>
      <label class="hero-drop qdrop" id="qdrop" for="qfiles"><span class="hd-ic">${UP_ICON}</span><div><b>آپلود پاسپورت</b><span>رها کنید یا بزنید و انتخاب کنید</span></div><input id="qfiles" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" style="position:absolute;opacity:0;width:1px;height:1px"></label>
    </div>
    <div class="hero-res">
      <div class="hx"><div class="hx-h"><i class="hx-ic mail"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg></i><b>ایمیل‌ها</b><span class="hcount">${faN(ems.length)}</span>${freeE ? `<small>${faN(freeE)} آزاد</small>` : ""}</div><ul>${emHTML || `<li class="hx-empty">ایمیلی نیست</li>`}</ul></div>
      <div class="hx"><div class="hx-h"><i class="hx-ic sim"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></i><b>شماره‌ها</b><span class="hcount">${faN(sims.length)}</span>${freeS ? `<small>${faN(freeS)} آزاد</small>` : ""}</div><ul>${smHTML || `<li class="hx-empty">شماره‌ای نیست</li>`}</ul></div>
    </div></section>`;
  h += globalAlerts();
  const withWork = portalsList().filter(p => S.passports.some(x => x.portal === p.code && x.status === "waiting"));
  if (!withWork.length) return h + `<div class="sec"><div class="empty">پاسپورتی در انتظار ثبت نیست.</div></div>`;
  for (const p of withWork){
    const {waiting, steps} = planFor(p.code);
    h += `<section class="qsec"><div class="qhead"${flagBg(p.code)}><h2>${flagEl(p.code, "md")}${esc(pName(p.code))}</h2><span class="qn">${faN(waiting.length)} پاسپورت</span></div>
      <div class="tasks">${steps.map((s, i) => stepCard(p.code, s, i, steps.length)).join("")}</div></section>`;
  }
  return h;
}
async function assignTo(pc, em, items, simId){
  const id = accId(pc, em);
  for (const x of items){ const ref = db.collection("people").doc();
    if (!await write(() => ref.set({name:x.name || "بی‌نام", portal:pc, emailId:em, accountId:id, status:"waitlist", apptDate:"", note:"", passportId:x.id, addedAt:today()}))) return false;
    await write(() => db.doc("passports/" + x.id).update({status:"assigned", accountId:id, emailId:em, personId:ref.id})); }
  await saveAccount(pc, em, {status:"active", lastVerified:today(), ...(simId ? {simId} : {})});
  await addLog("added", pc, {emailId:em, simId:simId || S.accounts.get(id)?.simId || "", note:items.map(x => x.name).join("، ")});
  return true;
}
async function queueAct(a, key){
  const [pc, i] = key.split("|"), s = (lastPlans[pc] || [])[+i]; if (!s) return;
  if (a === "q_ok"){ if (await assignTo(pc, s.e.code, s.add)) toast(`${faN(s.add.length)} نفر به Waitlist رفتند`); }
  if (a === "q_gone"){ await quick("gone", s.id); }
  if (a === "q_skip"){ if (!skipAcc.has(pc)) skipAcc.set(pc, new Set()); skipAcc.get(pc).add(s.id); render(); toastUndo("این اکانت کنار رفت", () => { skipAcc.get(pc)?.delete(s.id); render(); }); }
  if (a === "q_new"){
    if (!await saveAccount(pc, s.e.code, {status:"active", lastVerified:today(), simId:s.sim.code, createdAt:today()})) return;
    await setReg(pc, s.sim.code, "registered", s.e.code);
    await addLog("created", pc, {emailId:s.e.code, simId:s.sim.code});
    if (await assignTo(pc, s.e.code, s.add, s.sim.code)) toast(`اکانت ساخته شد و ${faN(s.add.length)} نفر به Waitlist رفتند`);
  }
  if (a === "q_phone"){ if (await setReg(pc, s.sim.code, "registered")) { await addLog("phone_taken", pc, {simId:s.sim.code, emailId:s.e.code}); toast("شماره بعدی پیشنهاد شد"); } }
  if (a === "q_email"){ if (await saveAccount(pc, s.e.code, {status:"active", lastVerified:"", note:"VFS گفت ایمیل اکانت دارد"})) { await addLog("email_taken", pc, {emailId:s.e.code}); toast("این ایمیل حالا اکانت موجود حساب می‌شود"); } }
}

// ---------- waitlist board: country → account → travellers ----------
let wlShow = "open";   // open = waitlist + booked, all = everything
function renderWl(){
  const keep = p => wlShow === "all" ? true : (p.status === "waitlist" || p.status === "booked");
  const ppl = S.people.filter(keep);
  const tot = s => S.people.filter(p => p.status === s).length;
  let h = `<div class="page-h"><div><h1>ویت‌لیست و وقت‌ها</h1></div>
  <button class="btn primary" type="button" data-act="readappt">خواندن نامه تأیید وقت</button></div>
    <div class="wl-sum"><div><b>${faN(tot("waitlist"))}</b><span>در ویت‌لیست</span></div><div><b>${faN(tot("booked"))}</b><span>وقت گرفته</span></div><div><b>${faN(new Set(ppl.map(p => p.accountId)).size)}</b><span>اکانت درگیر</span></div></div>`;
  const countries = portalsList().filter(c => ppl.some(p => p.portal === c.code));
  if (!countries.length) return h + `<section class="sec"><div class="empty">هنوز مسافری در Waitlist نیست.</div></section>`;
  for (const c of countries){
    const cp = ppl.filter(p => p.portal === c.code);
    const accIds = [...new Set(cp.map(p => p.accountId))];
    h += `<section class="qsec"><div class="qhead"${flagBg(c.code)}><h2>${flagEl(c.code, "md")}${esc(pName(c.code))}</h2><span class="qn">${faN(cp.filter(p => p.status === "waitlist").length)} در ویت‌لیست · ${faN(cp.filter(p => p.status === "booked").length)} وقت گرفته</span></div><div class="tasks">`;
    for (const aid of accIds){
      const a = S.accounts.get(aid), e = S.emails.get(a?.emailId || aid.split("__")[1]), m = a?.simId ? S.sims.get(a.simId) : null;
      const list = cp.filter(p => p.accountId === aid).sort((x, y) => (x.status === "booked") - (y.status === "booked") || (x.apptDate || "").localeCompare(y.apptDate || ""));
      const waiting = list.filter(p => p.status === "waitlist");
      h += `<div class="wl-acc">
        <div class="wl-acc-h"><div class="sc-cred" style="flex:1">${credLine("ایمیل", e?.address || aid)}${m ? credLine("شماره", prettyPhone(m.number), localPhone(m.number)) : ""}</div>
          <div class="wl-acc-side">${capPill(occupants(aid).length)}${vfsBtn(c.code)}</div></div>
        <div class="wl-list">${list.map(p => {
          const pp = p.passportId ? S.passports.find(x => x.id === p.passportId) : null;
          const booked = p.status === "booked";
          return `<div class="wl-row ${booked ? "bk" : ""}">
            <div class="wl-who"><b>${esc(p.name)}</b></div>
            <div class="wl-st">${booked ? `<span class="pill ok">وقت گرفته</span><b class="mono">${esc(p.apptDate || "—")}</b>${p.apptTime ? `<small class="mono">${esc(p.apptTime)}</small>` : ""}` : p.status === "waitlist" ? `<span class="pill sun">Waitlist</span>` : `<span class="pill">${esc(P_STATUS[p.status])}</span>`}</div>
            <div class="wl-act">${p.status === "waitlist" ? `<button class="btn sm primary" type="button" data-act="book" data-id="${esc(p.id)}">ثبت وقت</button>` : ""}<button class="btn sm" type="button" data-act="person" data-id="${esc(p.id)}">ویرایش</button><button class="btn sm ic-btn" type="button" data-act="pfinish" data-id="${esc(p.id)}" title="اتمام کار" aria-label="اتمام کار ${esc(p.name)}">✓</button><button class="btn sm ic-btn del" type="button" data-act="pdel" data-id="${esc(p.id)}" title="حذف" aria-label="حذف ${esc(p.name)}">×</button></div></div>`; }).join("")}</div>
      </div>`;
    }
    h += `</div></section>`;
  }
  return h;
}
// ---------- read a VFS appointment confirmation and book the matching travellers ----------
const normName = v => String(v || "").toUpperCase().replace(/[^A-Z؀-ۿ ]+/g, " ").split(/\s+/).filter(Boolean);
function matchPerson(app, pool){
  if (app.passportNo){ const hit = pool.find(p => { const pp = p.passportId && S.passports.find(x => x.id === p.passportId), no = pp?.passportNo || p.passportNo; return no && no.toUpperCase() === app.passportNo; }); if (hit) return hit; }
  const a = normName(app.name); if (!a.length) return null;
  let best = null, bestScore = 0;
  for (const p of pool){ const pp = p.passportId && S.passports.find(x => x.id === p.passportId);
    for (const cand of [p.name, pp?.name, pp ? `${pp.firstName || ""} ${pp.lastName || ""}` : `${p.firstName || ""} ${p.lastName || ""}`]){
      const b = normName(cand); if (!b.length) continue;
      const score = a.filter(t => b.includes(t)).length / Math.max(a.length, b.length);
      if (score > bestScore){ bestScore = score; best = p; } } }
  return bestScore >= .5 ? best : null;
}
function apptReadSheet(){
  if (!SERVER_AI){ toast("خواندن خودکار فعال نیست؛ کلید Claude API را بالای فایل index.php بگذارید"); return; }
  openSheet("خواندن نامه تأیید وقت",
    `<p class="hint" style="margin:0">PDF یا اسکرین‌شات نامه تأیید VFS را انتخاب کنید، یا متن ایمیل تأیید را اینجا paste کنید. تاریخ، ساعت و مسافرها خودکار پیدا می‌شوند و قبل از ثبت نشانتان می‌دهیم.</p>
    <div class="field"><label for="apptFile">فایل نامه (PDF یا عکس)</label><input type="file" id="apptFile" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
    ${F.area("apptText", "یا متن ایمیل تأیید", "", {ltr:true, ph:"Your appointment has been confirmed…"})}`,
    async () => {
      const f = $("#apptFile").files[0], text = $("#apptText").value.trim();
      if (!f && !text){ toast("یک فایل انتخاب کنید یا متن ایمیل را paste کنید"); return false; }
      $("#sheetSave").textContent = "در حال خواندن…";
      try {
        const body = f ? {fileId:(await assets.upload(f)).id} : {text};
        const r = await API("readappt", body); apptConfirmSheet(r.appt);
      } catch (e) { toast(e?.message || "خوانده نشد؛ دوباره امتحان کنید"); $("#sheetSave").textContent = "بخوان"; }
      return false; }, "", "بخوان");
}
function apptConfirmSheet(ap){
  const byCountry = S.people.filter(p => p.status === "waitlist" && (!ap.country || p.portal === ap.country));
  const pool = byCountry.length ? byCountry : S.people.filter(p => p.status === "waitlist");
  const apps = ap.applicants.length ? ap.applicants : [{name:"", passportNo:""}];
  const used = new Set();
  const rows = apps.map((a, i) => { const m = matchPerson(a, pool.filter(p => !used.has(p.id))); if (m) used.add(m.id);
    const opts = [["", "— ثبت نشود —"], ...pool.map(p => [p.id, `${p.name} · ${pName(p.portal)} · ${S.emails.get(p.emailId)?.address || ""}`])];
    return `<div class="appt-row"><div class="appt-read"><span class="lbl">در نامه</span><b class="mono">${esc(a.name || "—")}</b>${a.passportNo ? `<small class="mono">${esc(a.passportNo)}</small>` : ""}${m ? `<span class="pill ok">پیدا شد</span>` : `<span class="pill sun">دستی انتخاب کنید</span>`}</div>
      ${F.sel("who" + i, "مسافر در میز سفارت", opts, m?.id || "")}</div>`; }).join("");
  openSheet("تأیید وقت از روی نامه",
    `<div class="sc-cred"><div><span class="lbl">کشور</span>${ap.country && FLAGS[ap.country] ? flagEl(ap.country, "sm") : ""}<b style="font-size:13px">${esc(ap.country ? pName(ap.country) : "پیدا نشد")}</b></div>${ap.center ? `<div><span class="lbl">مرکز</span><span>${esc(ap.center)}</span></div>` : ""}${ap.reference ? credLine("رزرو", ap.reference) : ""}</div>
    <div class="two">${F.date("apptDate", "تاریخ وقت سفارت", ap.date)}<div class="field"><label for="apptTime">ساعت</label><input type="time" id="apptTime" name="apptTime" dir="ltr" value="${esc(ap.time)}"></div></div>
    ${rows}`,
    async () => { const d = formData(); if (!d.apptDate){ toast("تاریخ وقت را وارد کنید"); return false; }
      const ids = [...new Set(apps.map((_, i) => d["who" + i]).filter(Boolean))], ps = ids.map(id => S.people.find(p => p.id === id)).filter(Boolean);
      if (!ps.length){ toast("حداقل یک مسافر انتخاب کنید"); return false; }
      const note = [ap.reference && "رزرو " + ap.reference, ap.center].filter(Boolean).join(" · ");
      await undoable(async () => {
        for (const p of ps) if (!await write(() => db.doc("people/" + p.id).update({status:"booked", apptDate:d.apptDate, apptTime:d.apptTime || "", note:[p.note, note].filter(Boolean).join(" · ")}))) return;
        for (const pc of new Set(ps.map(p => p.portal))){ const g = ps.filter(p => p.portal === pc);
          await addLog("booked", pc, {emailId:g[0].emailId, simId:S.accounts.get(g[0].accountId)?.simId || "", note:`${g.map(p => p.name).join("، ")} · ${d.apptDate}${d.apptTime ? " " + d.apptTime : ""}`}); }
        toast(`وقت ${faN(ps.length)} نفر ثبت شد`); });
      return true; }, "", "ثبت وقت");
}
function bookSheet(ids){
  const ps = ids.map(i => S.people.find(p => p.id === i)).filter(Boolean); if (!ps.length) return;
  const p0 = ps[0], a = S.accounts.get(p0.accountId), e = S.emails.get(p0.emailId);
  openSheet(ps.length > 1 ? `ثبت وقت · ${faN(ps.length)} نفر` : `ثبت وقت · ${p0.name}`,
    `<div class="sc-cred"><div><span class="lbl">کشور</span>${flagEl(p0.portal, "sm")}<b style="font-size:13px">${esc(pName(p0.portal))}</b></div>${credLine("ایمیل", e?.address || p0.emailId)}</div>
    <div class="sc-people" style="--cc:${colorOf(p0.portal)}">${ps.map(p => `<span>${esc(p.name)}</span>`).join("")}</div>
    <div class="two">${F.date("apptDate","تاریخ وقت سفارت","")}<div class="field"><label for="apptTime">ساعت</label><input type="time" id="apptTime" name="apptTime" dir="ltr"></div></div>
    ${F.text("apptNote","یادداشت (مثلاً شماره رزرو یا مرکز)","")}`,
    async () => { const d = formData(); if (!d.apptDate) { toast("تاریخ وقت را وارد کنید"); return false; }
      for (const p of ps) if (!await write(() => db.doc("people/" + p.id).update({status:"booked", apptDate:d.apptDate, apptTime:d.apptTime || "", note:[p.note, d.apptNote].filter(Boolean).join(" · ")}))) return false;
      await addLog("booked", p0.portal, {emailId:p0.emailId, simId:a?.simId || "", note:`${ps.map(p => p.name).join("، ")} · ${d.apptDate}${d.apptTime ? " " + d.apptTime : ""}`});
      toast(`وقت ${faN(ps.length)} نفر ثبت شد`); return true; }, "", "ثبت وقت");
}

function renderRes(){
  const none = `<span class="rs-none">هنوز روی هیچ کشوری ست نشده</span>`;
  const local = e => (e?.address || "").split("@")[0];
  const live = a => a && S.emails.has(a.emailId) && (accStatus(a) === "active" || accStatus(a) === "check");
  const liveAccs = [...S.accounts.values()].filter(live);
  // 1) country map: which email + which phone on each country
  const map = portalsList().map(p => {
    const accs = liveAccs.filter(a => a.portal === p.code).sort((x,y) => x.emailId.localeCompare(y.emailId));
    const orphan = [];   // phones VFS reported as taken are listed in the phone table, not here
    if (!accs.length) return "";
    const rows = accs.map(a => { const e = S.emails.get(a.emailId), m = a.simId ? S.sims.get(a.simId) : null, n = occupants(a.id).length;
      return `<button type="button" class="rs-pair" data-act="acc" data-id="${esc(a.id)}"><span class="rs-dot st-${accStatus(a)}"></span>
        <span class="rs-e mono">${esc(e?.address || a.emailId)}</span><span class="rs-link">+</span>
        <span class="rs-p mono">${m ? esc(prettyPhone(m.number)) : `<i>بدون شماره</i>`}</span>
        <span class="rs-occ ${n >= CAP ? "full" : ""}">${faN(n)}/${faN(CAP)}</span></button>`; }).join("")
      + orphan.map(m => `<div class="rs-pair orphan"><span class="rs-dot st-reg"></span><span class="rs-e">شماره تکراری، بدون اکانت</span><span class="rs-link">+</span><span class="rs-p mono">${esc(prettyPhone(m.number))}</span><span></span></div>`).join("");
    return `<div class="rs-card"><div class="rs-card-h"${flagBg(p.code)}>${flagEl(p.code, "md")}<b>${esc(pName(p.code))}</b><span>${faN(accs.length)} اکانت</span></div><div class="rs-card-b">${rows}</div></div>`;
  }).join("");
  const cnt = (n, extra) => `<span class="hcount">${faN(n)}</span>${extra ? `<span class="hcount2">${extra}</span>` : ""}`;
  // a chip per account: click opens that account's window (travellers, add, delete)
  const chip = (pc, st, txt, accId) => accId
    ? `<button type="button" class="rs-chip st-${st} ${occupants(accId).length ? "" : "empty"}" data-act="acc" data-id="${esc(accId)}" ${occupants(accId).length ? "" : `title="اکانت ساخته شده ولی هنوز مسافری ندارد"`}>${flagEl(pc, "sm")}<b>${esc(pName(pc))}</b><span class="mono">${esc(txt)}</span><small>${faN(occupants(accId).length)}/${faN(CAP)}</small></button>`
    : `<span class="rs-chip st-${st}">${flagEl(pc, "sm")}<b>${esc(pName(pc))}</b><span class="mono">${esc(txt)}</span></span>`;
  const rowBtns = (kind, code) => `<div class="p3-act"><button class="btn sm" type="button" data-act="${kind}" data-id="${esc(code)}">ویرایش</button><button class="btn sm ic-btn del" type="button" data-act="harddel" data-kind="${kind}" data-id="${esc(code)}" title="حذف" aria-label="حذف">×</button></div>`;
  const off = x => x.status === "inactive";
  const ord = (x, y) => off(x) - off(y) || x.code.localeCompare(y.code);
  const sims = [...S.sims.values()].sort(ord), ems = [...S.emails.values()].sort(ord);
  const sRows = sims.map(m => {
    const accs = liveAccs.filter(a => a.simId === m.code).sort((x, y) => occupants(y.id).length - occupants(x.id).length), orphan = portalsList().filter(p => phoneOn(p.code, m.code).kind === "orphan");
    const used = accs.length || orphan.length ? `<div class="chips">${accs.map(a => chip(a.portal, accStatus(a), local(S.emails.get(a.emailId)) || a.emailId, a.id)).join("")}${orphan.map(p => `<button type="button" class="rs-chip st-reg" data-act="phonefree" data-id="${esc(p.code + "__" + m.code)}" title="این شماره دیگر برای ${esc(pName(p.code))} ثبت‌شده حساب نشود">${flagEl(p.code, "sm")}<b>${esc(pName(p.code))}</b><span>ثبت‌شده در VFS</span><small>×</small></button>`).join("")}</div>` : none;
    return `<tr class="${off(m) ? "rs-off" : ""}"><td><span class="tag">${esc(m.code)}</span></td><td><button type="button" class="res-open mono" data-act="resopen" data-kind="sim" data-id="${esc(m.code)}">${esc(prettyPhone(m.number))}</button>${off(m) ? `<small class="rs-offtag">فعلاً استفاده نشه</small>` : m.operator || m.note ? `<small>${esc([m.operator, m.note].filter(Boolean).join(" · "))}</small>` : ""}</td><td>${used}</td><td>${rowBtns("sim", m.code)}</td></tr>`; }).join("");
  const eRows = ems.map(e => {
    const accs = liveAccs.filter(a => a.emailId === e.code).sort((x, y) => occupants(y.id).length - occupants(x.id).length);
    const used = accs.length ? `<div class="chips">${accs.map(a => chip(a.portal, accStatus(a), a.simId ? localPhone(S.sims.get(a.simId)?.number || "") || a.simId : "بدون شماره", a.id)).join("")}</div>` : none;
    return `<tr class="${off(e) ? "rs-off" : ""}"><td><span class="tag">${esc(e.code)}</span></td><td><button type="button" class="res-open mono" data-act="resopen" data-kind="email" data-id="${esc(e.code)}">${esc(e.address)}</button>${off(e) ? `<small class="rs-offtag">فعلاً استفاده نشه</small>` : ""}</td><td>${used}</td><td>${rowBtns("email", e.code)}</td></tr>`; }).join("");
  const sFree = sims.filter(m => !off(m) && !liveAccs.some(a => a.simId === m.code)).length, sOff = sims.filter(off).length;
  const eFree = ems.filter(e => !off(e) && !liveAccs.some(a => a.emailId === e.code)).length, eOff = ems.filter(off).length;
  const extra = (f, o) => [f ? `${faN(f)} بدون کشور` : "", o ? `${faN(o)} استفاده نشه` : ""].filter(Boolean).join(" · ");
  return `<div class="page-h"><div><h1>ایمیل و شماره</h1></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn ibtn i-sim" type="button" data-act="sim"><i><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></i>شماره جدید</button><button class="btn ibtn i-mail" type="button" data-act="email"><i><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg></i>ایمیل جدید</button><button class="btn primary ibtn i-bulk" type="button" data-act="bulk"><i><svg viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/></svg></i>افزودن گروهی</button></div></div>

    <section class="sec"><div class="sec-h"><h2>شماره‌ها ${cnt(S.sims.size, extra(sFree, sOff))}</h2></div>${S.sims.size ? `<div class="table-wrap"><table class="rs-table"><thead><tr><th>کد</th><th>شماره</th><th>کشور و ایمیلی که با این شماره ست شده</th><th></th></tr></thead><tbody>${sRows}</tbody></table></div>` : `<div class="empty">شماره‌ای نیست.</div>`}</section>
    <section class="sec"><div class="sec-h"><h2>ایمیل‌ها ${cnt(S.emails.size, extra(eFree, eOff))}</h2></div>${S.emails.size ? `<div class="table-wrap"><table class="rs-table"><thead><tr><th>کد</th><th>آدرس</th><th>کشور و شماره‌ای که با این ایمیل ست شده</th><th></th></tr></thead><tbody>${eRows}</tbody></table></div>` : `<div class="empty">ایمیلی نیست.</div>`}</section>`;
}

let peopleCountry = "", peopleStatus = "";
function renderPeople(){
  const q = peopleQ.trim().toLowerCase();
  const base = S.people.filter(p => !q || [p.name, p.note, pName(p.portal), S.emails.get(p.emailId)?.address, S.sims.get(S.accounts.get(p.accountId)?.simId)?.number].join(" ").toLowerCase().includes(q));
  const list = base.filter(p => (!peopleCountry || p.portal === peopleCountry) && (!peopleStatus || p.status === peopleStatus))
    .sort((a,b) => (a.status === "removed") - (b.status === "removed") || (b.addedAt || "").localeCompare(a.addedAt || ""));
  const cnt = pc => base.filter(p => p.portal === pc && p.status !== "removed").length;
  const used = portalsList().filter(p => cnt(p.code));
  const stCnt = s => base.filter(p => (!peopleCountry || p.portal === peopleCountry) && p.status === s).length;
  let h = `<div class="page-h"><div><h1>مسافران</h1></div></div>`;
  if (!S.people.length) return h + `<section class="sec"><div class="empty">هنوز مسافری ثبت نشده.</div></section>`;
  h += `<div class="fchips"><button type="button" class="fchip ${peopleCountry ? "" : "on"}" data-act="pcountry" data-id="">همه <b>${faN(base.filter(p => p.status !== "removed").length)}</b></button>
    ${used.map(p => `<button type="button" class="fchip ${peopleCountry === p.code ? "on" : ""}" data-act="pcountry" data-id="${esc(p.code)}" style="--cc:${colorOf(p.code)}">${flagEl(p.code, "sm")}${esc(pName(p.code))} <b>${faN(cnt(p.code))}</b></button>`).join("")}</div>
    <div class="toolbar"><input id="peopleQ" type="search" placeholder="جستجوی اسم، ایمیل یا شماره…" aria-label="جستجوی مسافر" value="${esc(peopleQ)}">
      ${[["", "همه وضعیت‌ها"], ["waitlist", "Waitlist"], ["booked", "وقت گرفت"], ["removed", "اتمام کار"]].map(([v, t]) => `<button type="button" class="fchip sm ${peopleStatus === v ? "on" : ""}" data-act="pstatus2" data-id="${v}">${t}${v ? ` <b>${faN(stCnt(v))}</b>` : ""}</button>`).join("")}</div>`;
  if (!list.length) return h + `<section class="sec"><div class="empty">کسی با این فیلتر پیدا نشد.</div></section>`;
  return h + `<section class="sec"><div class="ptable">${list.map(p => {
    const a = S.accounts.get(p.accountId), m = a?.simId ? S.sims.get(a.simId) : null, e = S.emails.get(p.emailId);
    const pill = {waitlist:"sun", booked:"ok", removed:""}[p.status] || "";
    return `<div class="prow3 ${p.status === "removed" ? "dim" : ""}" style="--cc:${colorOf(p.portal)}">
      <div class="p3-who">${flagEl(p.portal, "md")}<div><b>${esc(p.name)}</b><small>${esc(pName(p.portal))}</small></div></div>
      <div class="p3-info">${(() => { const pp = p.passportId ? S.passports.find(x => x.id === p.passportId) : null;
        const src = pp || p;
        if (!(src.passportNo || src.nationality || src.expiry)) return `<small style="color:var(--muted)">اطلاعات پاسپورت ثبت نشده</small>`;
        const left = src.expiry ? -ago(src.expiry) : null, ec = left === null ? "" : left < 0 ? "bad" : left < 183 ? "warn" : "";
        return `<div><span class="k">پاسپورت</span><span class="mono">${esc(src.passportNo || "—")}</span></div><div><span class="k">ملیت</span>${esc(src.nationality || "—")}</div><div><span class="k">انقضا</span><span class="mono ${ec}">${esc(src.expiry || "—")}</span></div>`; })()}</div>
      <div class="p3-cred"><span class="mono">${esc(e?.address || p.emailId)}</span>${e ? copyBtn(e.address) : ""}<br>${m ? `<span class="mono">${esc(prettyPhone(m.number))}</span>${copyBtn(localPhone(m.number))}` : `<small style="color:var(--muted)">شماره ثبت نشده</small>`}</div>
      <div class="p3-st"><span class="pill ${pill}">${esc(P_STATUS[p.status] || p.status)}</span>${p.status === "removed" ? `<small>حذف خودکار ${(() => { const d = DONE_DAYS - (ago(p.doneAt || today()) || 0); return d > 0 ? faN(d) + " روز دیگر" : "امروز"; })()}</small>` : p.apptDate ? `<small class="mono">${esc(p.apptDate)}</small>` : ""}</div>
      <div class="p3-act"><button class="btn sm" type="button" data-act="person" data-id="${esc(p.id)}">ویرایش</button>${p.status !== "removed" ? `<button class="btn sm ic-btn" type="button" data-act="pfinish" data-id="${esc(p.id)}" title="اتمام کار" aria-label="اتمام کار ${esc(p.name)}">✓</button>` : ""}<button class="btn sm ic-btn del" type="button" data-act="pdel" data-id="${esc(p.id)}" title="حذف" aria-label="حذف ${esc(p.name)}">×</button></div></div>`; }).join("")}</div></section>`;
}

function renderLog(){
  const opts = portalsList().map(p => `<option value="${esc(p.code)}" ${logPortal === p.code ? "selected" : ""}>${esc(pName(p.code))}</option>`).join("");
  const list = logPortal ? S.logs.filter(l => l.portal === logPortal) : S.logs;
  return `<div class="page-h"><div><h1>تاریخچه</h1></div></div>
    <div class="toolbar"><select id="logPortal" aria-label="فیلتر کشور"><option value="">همه کشورها</option>${opts}</select><button class="btn" type="button" data-act="export">خروجی CSV</button></div>
    <section class="sec">${list.length ? list.map(evHTML).join("") : `<div class="empty">هنوز چیزی ثبت نشده.</div>`}</section>`;
}

// ---------- writes ----------
async function write(fn){
  if (!db) { toast("ذخیره‌سازی در دسترس نیست"); return false; }
  try { await fn(); return true; }
  catch (e) {
    if (e?.code === "invalid_argument") { canWrite = false; showBanner("اجازه ویرایش ندارید؛ این صفحه برای شما فقط خواندنی است."); }
    else toast("ذخیره نشد. دوباره امتحان کنید.");
    return false;
  }
}
const addLog = (type, pc, extra = {}) => write(() => db.collection("logs").add({type, portal:pc, date:today(), at:new Date().toISOString(), emailId:"", simId:"", note:"", ...extra}));
function saveAccount(pc, em, patch){
  const id = accId(pc, em), cur = S.accounts.get(id);
  return write(() => db.doc("accounts/" + id).set({id, portal:pc, emailId:em, simId:"", status:"unknown", lastVerified:"", createdAt:"", note:"", ...(cur || {}), ...patch, updated:today()}));
}
function setReg(pc, sim, status, emailId = ""){
  const id = regId(pc, sim);
  return write(() => db.doc("regs/" + id).set({id, portal:pc, simId:sim, status, emailId, seen:today()}));
}

async function quick(act, id){
  if (act === "ok"){ const [pc, em] = id.split("__"); if (await saveAccount(pc, em, {status:"active", lastVerified:today()})) { await addLog("login_ok", pc, {emailId:em, simId:S.accounts.get(id)?.simId || ""}); toast("ثبت شد: اکانت فعال است"); } }
  if (act === "gone"){ const [pc, em] = id.split("__"), a = S.accounts.get(id);
    if (!await saveAccount(pc, em, {status:"none", lastVerified:""})) return;
    if (a?.simId) await setReg(pc, a.simId, "registered", "");
    for (const p of occupants(id)) { await write(() => db.doc("people/" + p.id).update({status:"removed", doneAt:today(), note:(p.note ? p.note + " · " : "") + "اکانت پاک شده بود"})); if (p.passportId) await releasePassport(p.passportId); }
    await addLog("no_account", pc, {emailId:em, simId:a?.simId || ""}); toast("ثبت شد: این ایمیل برای این کشور آزاد است"); }
  if (act === "phonetaken"){ const [pc, sim] = id.split("__"); if (await setReg(pc, sim, "registered")) { await addLog("phone_taken", pc, {simId:sim}); toast("این شماره برای این کشور کنار رفت"); } }
  if (act === "phonefree"){ const [pc, sim] = id.split("__"); if (await setReg(pc, sim, "unknown")) toast("شماره آزاد شد"); }
}

// ---------- sheets ----------
let sheetSave = null;
function openSheet(title, bodyHTML, onSave, leftHTML = "", saveLabel = "ذخیره"){
  const sf = $("#sheetForm"); sf.ondragover = sf.ondragleave = sf.ondrop = null; $("#sheetCancel").hidden = false;
  $("#sheetTitle").textContent = title; sf.innerHTML = bodyHTML; $("#sheetLeft").innerHTML = leftHTML;
  $("#sheetSave").textContent = saveLabel; $("#sheetSave").hidden = !onSave; $("#sheetSave").disabled = !canWrite;
  sheetSave = onSave; $("#scrim").hidden = false; $("#sheet").hidden = false;
}
const setSaveEnabled = ok => { $("#sheetSave").disabled = !canWrite || !ok; };
async function releasePassport(pid){ if (S.passports.some(x => x.id === pid)) await write(() => db.doc("passports/" + pid).update({status:"waiting", accountId:"", emailId:"", personId:""})); }
function closeSheet(){ $("#scrim").hidden = true; $("#sheet").hidden = true; sheetSave = null; }
const F = {
  text:(id,label,val="",o={}) => `<div class="field"><label for="${id}">${label}</label><input id="${id}" name="${id}" value="${esc(val)}" ${o.ltr ? 'class="ltr" dir="ltr"' : ""} ${o.req ? "required" : ""}>${o.hint ? `<span class="hint">${o.hint}</span>` : ""}</div>`,
  date:(id,label,val="",hint="") => `<div class="field"><label for="${id}">${label}</label><input type="date" id="${id}" name="${id}" value="${esc(val)}" dir="ltr">${hint ? `<span class="hint">${hint}</span>` : ""}</div>`,
  sel:(id,label,opts,val="",hint="") => `<div class="field"><label for="${id}">${label}</label><select id="${id}" name="${id}">${opts.map(([v,t,dis]) => `<option value="${esc(v)}" ${String(v) === String(val) ? "selected" : ""} ${dis ? "disabled" : ""}>${esc(t)}</option>`).join("")}</select>${hint ? `<span class="hint">${hint}</span>` : ""}</div>`,
  area:(id,label,val="",o={}) => `<div class="field"><label for="${id}">${label}</label><textarea id="${id}" name="${id}" ${o.ltr ? 'class="ltr" dir="ltr"' : ""} ${o.ph ? `placeholder="${esc(o.ph)}"` : ""}>${esc(val)}</textarea>${o.hint ? `<span class="hint">${o.hint}</span>` : ""}</div>`
};
const formData = () => Object.fromEntries(new FormData($("#sheetForm")).entries());
function armDelete(onDelete){ const b = $("#delBtn"); if (!b) return; b.onclick = async () => { if (b.dataset.armed){ if (await onDelete() !== false) closeSheet(); } else { b.dataset.armed = "1"; b.textContent = "مطمئنید؟ دوباره بزنید"; } }; }

function phoneOptions(pc, keep = ""){
  return simsAll().map(m => { const on = phoneOn(pc, m.code), tot = phoneAccounts(m.code).length;
    const mine = on.kind === "acc" && on.acc.simId === keep && keep;
    const tail = mine ? "شماره همین اکانت" : on.kind === "free" ? `آزاد · ${faN(tot)} اکانت در کل` : on.kind === "acc" ? `قبلاً برای ${S.emails.get(on.acc.emailId)?.address || on.acc.emailId} استفاده شده` : `VFS گفته تکراری است`;
    return [m.code, `${localPhone(m.number)} — ${tail}`, !(on.kind === "free" || mine)]; })
    .sort((a,b) => a[2] - b[2]);
}
// "register in this country": pick an email — an existing account with room goes straight to adding travellers; a free email walks through phone + result
function newAccount(pc, emailPre = ""){
  const info = emailsAll().map(e => { const id = accId(pc, e.code), a = S.accounts.get(id), st = accStatus(a), occ = occupants(id).length;
    const has = st === "active" || st === "check";
    return {e, id, a, st, has, free: has ? CAP - occ : 0, occ}; });
  const label = x => x.has ? (x.free > 0 ? `${x.e.address} — اکانت دارد · ${faN(x.free)} جای خالی` : `${x.e.address} — اکانت پر است (${faN(CAP)}/${faN(CAP)})`) : `${x.e.address} — آزاد · اکانت جدید`;
  const order = x => x.has ? (x.free > 0 ? 0 : 2) : 1;
  const opts = info.slice().sort((a,b) => order(a) - order(b)).map(x => [x.e.code, label(x), x.has && x.free <= 0]);
  const firstEm = emailPre || opts.find(o => !o[2])?.[0] || "";
  if (!firstEm) { toast(`همه اکانت‌های ${pName(pc)} پرند و ایمیل آزادی نمانده؛ ایمیل جدید اضافه کنید`); return; }
  const ph = phoneOptions(pc), firstPh = ph.find(o => !o[2])?.[0] || "";
  openSheet(`ثبت در VFS ${pName(pc)}`,
    `<div class="step"><i>۱</i><div>${F.sel("em","ایمیل",opts,firstEm)}<button class="btn sm" type="button" id="cpEm">کپی ایمیل</button></div></div>
    <div id="exBox" class="sc-cred" hidden></div>
    <div id="newSteps">
    <div class="step"><i>۲</i><div><div class="field"><label for="ph">شماره</label><div class="field-row"><select id="ph" name="ph">${ph.map(([v,t,d]) => `<option value="${esc(v)}" ${v === firstPh ? "selected" : ""} ${d ? "disabled" : ""}>${esc(t)}</option>`).join("")}</select></div></div>
      <div class="btn-row"><button class="btn sm" type="button" id="cpPh">کپی شماره</button><button class="btn sm ghost-red" type="button" id="phTaken">VFS گفت این شماره تکراری است</button></div></div></div>
    <div class="step"><i>۳</i><div>${F.sel("res","نتیجه در سایت VFS",[["created","اکانت را ساختم"],["existed","ایمیل از قبل اکانت داشت و وارد شدم"]],"created")}</div></div>
    </div>`,
    async () => { const d = formData(), x = info.find(i => i.e.code === d.em);
      if (x?.has) { setTimeout(() => addPeople(x.id), 60); return true; }
      if (!d.em || !d.ph) { toast("ایمیل و شماره را انتخاب کنید"); return false; }
      const ok = await saveAccount(pc, d.em, {status:"active", lastVerified:today(), simId:d.ph, createdAt:today()});
      if (!ok) return false;
      await setReg(pc, d.ph, "registered", d.em);
      await addLog(d.res === "existed" ? "login_ok" : "created", pc, {emailId:d.em, simId:d.ph});
      toast(`اکانت ${pName(pc)} ثبت شد`); setTimeout(() => addPeople(accId(pc, d.em)), 60); return true; }, "", "ثبت اکانت");
  const sync = () => { const x = info.find(i => i.e.code === $("#em").value), ex = !!x?.has;
    $("#newSteps").hidden = ex; $("#exBox").hidden = !ex;
    if (ex) { const sim = x.a?.simId ? S.sims.get(x.a.simId) : null;
      $("#exBox").innerHTML = `<div><span class="lbl">وضعیت</span><span class="st st-${x.st}">${ST_LABEL[x.st]}</span> <span style="font-size:12.5px;font-weight:700">${faN(x.occ)}/${faN(CAP)}</span></div>${sim ? credLine("شماره", prettyPhone(sim.number), localPhone(sim.number)) : ""}`; }
    $("#sheetSave").textContent = ex ? "ادامه: انتخاب مسافر" : "ثبت اکانت"; };
  $("#em").onchange = sync; sync();
  $("#cpEm").onclick = e => { const x = S.emails.get($("#em").value); if (x) copyText(x.address, e.currentTarget); };
  $("#cpPh").onclick = e => { const x = S.sims.get($("#ph").value); if (x) copyText(localPhone(x.number), e.currentTarget); };
  $("#phTaken").onclick = async () => { const sim = $("#ph").value; if (!sim) return;
    if (await setReg(pc, sim, "registered")) { await addLog("phone_taken", pc, {simId:sim, emailId:$("#em").value});
      const sel = $("#ph"), opt = sel.querySelector(`option[value="${CSS.escape(sim)}"]`); if (opt){ opt.disabled = true; opt.textContent = opt.textContent.replace(/—.*$/, "— VFS گفته تکراری است"); }
      const next = [...sel.options].find(o => !o.disabled); if (next) { sel.value = next.value; toast("شماره بعدی انتخاب شد"); } else { sel.value = ""; toast("شماره آزاد دیگری نمانده"); } } };
}
function openAccount(id){
  const [pc, em] = id.split("__"), a = S.accounts.get(id), e = S.emails.get(em); if (!e) return;
  const occ = occupants(id), st = accStatus(a), sim = a?.simId ? S.sims.get(a.simId) : null;
  const last = a?.lastVerified ? rel(a.lastVerified) : "ثبت نشده";
  openSheet(`${pName(pc)} · اکانت VFS`,
    `<div class="sc-cred">
      ${credLine("ایمیل", e.address)}
      ${sim ? credLine("شماره", prettyPhone(sim.number), localPhone(sim.number)) : `<div><span class="lbl">شماره</span><span style="color:var(--muted)">ثبت نشده</span></div>`}
      <div><span class="lbl">وضعیت</span><span class="st st-${st}">${ST_LABEL[st]}</span></div>
      <div><span class="lbl">آخرین ورود</span><b style="font-size:13px">${esc(last)}</b>${a?.lastVerified === today() ? "" : `<button class="copy" type="button" data-act="ok" data-id="${esc(id)}">همین الان وارد شدم</button>`}</div>
    </div>
    <div class="up-lbl" style="justify-content:space-between"><span>مسافران این اکانت</span>${capPill(occ.length)}</div>
    ${occ.length ? `<div class="plist">${occ.map(p => `<div class="prow"><span><b>${esc(p.name)}</b> <span class="pill ${p.status === "booked" ? "ok" : "sun"}">${esc(P_STATUS[p.status])}</span>${p.apptDate ? ` <span class="mono" style="font-size:12px">${esc(p.apptDate)}</span>` : ""}</span><span class="p3-act"><button class="btn sm" type="button" data-act="person" data-id="${esc(p.id)}">ویرایش</button><button class="btn sm ic-btn" type="button" data-act="pfinish" data-id="${esc(p.id)}" title="اتمام کار" aria-label="اتمام کار ${esc(p.name)}">✓</button><button class="btn sm ic-btn del" type="button" data-act="pdel" data-id="${esc(p.id)}" title="حذف" aria-label="حذف ${esc(p.name)}">×</button></span></div>`).join("")}</div>` : `<div class="empty" style="padding:10px">هنوز مسافری روی این اکانت نیست.</div>`}
    ${occ.length < CAP && (st === "active" || st === "check") ? `<button class="btn primary" type="button" data-act="addpeople" data-id="${esc(id)}" style="align-self:flex-start">+ افزودن مسافر</button>` : ""}
    `,
    null,
    `<button class="btn sm ghost-red" type="button" data-act="accdel" data-id="${esc(id)}">حذف اکانت</button>`);
}
function addPeople(id, pre = []){
  const [pc, em] = id.split("__"), free = CAP - occupants(id).length;
  if (free <= 0) { toast("این اکانت پر است"); return; }
  const a = S.accounts.get(id), sim = a?.simId ? S.sims.get(a.simId) : null;
  const byAt = (x, y) => (x.at || "").localeCompare(y.at || "");
  const here = S.passports.filter(x => x.portal === pc && x.status === "waiting").sort(byAt);
  const other = S.passports.filter(x => x.portal !== pc && x.status === "waiting").sort(byAt);
  const sel = new Set(pre.filter(pid => S.passports.some(x => x.id === pid && x.status === "waiting")).slice(0, free));
  // travellers without a passport scan: typed passport details, one form row each
  const manual = () => [...document.querySelectorAll(".mrow")].map(r => { const v = k => r.querySelector(`[data-m="${k}"]`).value.trim();
    return {firstName:v("firstName").toUpperCase(), lastName:v("lastName").toUpperCase(), passportNo:v("passportNo").toUpperCase(), nationality:v("nationality").toUpperCase(), expiry:v("expiry")}; })
    .filter(x => x.firstName || x.lastName);
  const mrowHTML = () => `<div class="mrow"><div class="mrow-h"><b>مسافر بدون پاسپورت</b><button type="button" class="fx" data-m-rm aria-label="حذف">×</button></div>
    <div class="two"><div class="field"><label>اسم</label><input data-m="firstName" dir="ltr" class="ltr" placeholder="ALI"></div><div class="field"><label>فامیل</label><input data-m="lastName" dir="ltr" class="ltr" placeholder="AHMADI"></div></div>
    <div class="two"><div class="field"><label>شماره پاسپورت</label><input data-m="passportNo" dir="ltr" class="ltr" placeholder="X12345678"></div><div class="field"><label>ملیت</label><input data-m="nationality" dir="ltr" class="ltr" placeholder="IRAN"></div></div>
    <div class="two"><div class="field"><label>تاریخ انقضای پاسپورت</label><input data-m="expiry" type="date" dir="ltr"></div><span></span></div></div>`;
  const card = x => {
    const on = sel.has(x.id), full = !on && sel.size + manual().length >= free, isImg = (x.contentType || "").startsWith("image/");
    return `<button type="button" class="pk ${on ? "on" : ""}" data-pk="${esc(x.id)}" ${full ? "disabled" : ""} aria-pressed="${on}">
      <span class="fthumb">${isImg ? `<img src="${esc(blobUrl(x.assetId))}" alt="" loading="lazy">` : "PDF"}</span>
      <span class="pk-t"><b>${x.name ? esc(x.name) : "اسم وارد نشده"}</b><small>${x.portal !== pc ? `${flagEl(x.portal, "sm")} ${esc(pName(x.portal))} · ` : ""}${x.passportNo ? `<span class="mono">${esc(x.passportNo)}</span>` : ""}${x.groupId ? ` <span class="gtag">گروه</span>` : ""}</small></span>
      <span class="pk-c">${on ? "✓" : ""}</span></button>`;
  };
  const drawCards = () => {
    const n = sel.size + manual().length;
    $("#apCount").textContent = `${faN(n)} از ${faN(free)} جای خالی انتخاب شده`;
    $("#apHere").innerHTML = here.length ? here.map(card).join("") : `<div class="empty" style="padding:12px">پاسپورتی برای ${esc(pName(pc))} در انتظار ثبت نیست.</div>`;
    const o = $("#apOther"); if (o) o.innerHTML = other.map(card).join("");
    const btn = $("#sheetSave"); btn.textContent = n ? `اضافه کردن ${faN(n)} مسافر` : "اضافه کن"; setSaveEnabled(n > 0 && n <= free);
    document.querySelectorAll("[data-pk]").forEach(b => b.onclick = () => {
      const pid = b.dataset.pk, x = S.passports.find(p => p.id === pid);
      if (sel.has(pid)) sel.delete(pid);
      else { const grp = x?.groupId ? S.passports.filter(p => p.groupId === x.groupId && p.status === "waiting").map(p => p.id) : [pid];
        if (sel.size + manual().length + grp.filter(g => !sel.has(g)).length > free) { toast(`فقط ${faN(free)} جا دارد`); return; }
        grp.forEach(g => sel.add(g)); }
      drawCards(); });
  };
  openSheet(`مسافر · ${pName(pc)}`,
    `<div class="sc-cred">${credLine("ایمیل", S.emails.get(em)?.address || em)}${sim ? credLine("شماره", prettyPhone(sim.number), localPhone(sim.number)) : ""}</div>
    <div class="ap-add">
      <label class="dropz ap-drop" for="apFiles">${UP_ICON}<div><b>آپلود پاسپورت</b><br><span>عکس یا PDF پاسپورت · بعد از آپلود همین‌جا انتخاب می‌شود</span></div><input id="apFiles" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"></label>
      <button type="button" class="ap-manual" id="apManual"><span class="ap-plus">+</span><div><b>مسافر بدون پاسپورت</b><br><span>اسم، فامیل، شماره، ملیت و انقضای پاسپورت را دستی وارد کنید</span></div></button>
    </div>
    <div id="mRows" class="mrows"></div>
    <div class="up-lbl" style="justify-content:space-between"><span>${flagEl(pc, "sm")} در انتظار ثبت · ${esc(pName(pc))}</span><span class="up-count" id="apCount"></span></div>
    <div class="pks" id="apHere"></div>
    ${other.length ? `<details class="more"><summary>پاسپورت‌های کشورهای دیگر (${faN(other.length)})</summary><div class="pks" id="apOther"></div></details>` : ""}
    <div class="two">${F.sel("status","وضعیت",[["waitlist","Waitlist"],["booked","وقت گرفته شد"]],"waitlist")}${F.date("apptDate","تاریخ وقت","")}</div>`,
    async () => { const d = formData(), names = manual();
      const pps = [...sel].map(pid => S.passports.find(x => x.id === pid)).filter(Boolean);
      if (!names.length && !pps.length) { toast("حداقل یک نفر انتخاب کنید"); return false; }
      if (names.length + pps.length > free) { toast(`فقط ${faN(free)} جا دارد`); return false; }
      const all = [...pps.map(x => ({name:x.name || "بی‌نام", pp:x})), ...names.map(n => ({name:[n.firstName, n.lastName].filter(Boolean).join(" "), pp:null, m:n}))];
      for (const x of all){ const ref = db.collection("people").doc();
        if (!await write(() => ref.set({name:x.name, portal:pc, emailId:em, accountId:id, status:d.status, apptDate:d.apptDate, note:"", passportId:x.pp?.id || "", addedAt:today(), ...(x.m || {})}))) return false;
        if (x.pp) await write(() => db.doc("passports/" + x.pp.id).update({status:"assigned", portal:pc, accountId:id, emailId:em, personId:ref.id})); }
      await saveAccount(pc, em, {status:"active", lastVerified:today()});
      await addLog(d.status === "booked" ? "booked" : "added", pc, {emailId:em, simId:a?.simId || "", note:all.map(x => x.name).join("، ")});
      toast(`${faN(all.length)} مسافر اضافه شد`); return true; }, "", "اضافه کن");
  const addRow = () => {
    if (sel.size + document.querySelectorAll(".mrow").length >= free) { toast(`فقط ${faN(free)} جا دارد`); return; }
    $("#mRows").insertAdjacentHTML("beforeend", mrowHTML()); $("#mRows .mrow:last-child [data-m=firstName]").focus(); drawCards(); };
  $("#apManual").onclick = addRow;
  $("#mRows").addEventListener("click", e => { if (e.target.closest("[data-m-rm]")) { e.target.closest(".mrow").remove(); drawCards(); } });
  $("#mRows").addEventListener("input", drawCards);
  $("#apFiles").onchange = e => { if (e.target.files.length) uploadPassports(pc, e.target.files, pids => addPeople(id, [...sel, ...pids])); };
  drawCards();
}
function editPerson(pid){
  const p = S.people.find(x => x.id === pid); if (!p) return;
  openSheet(`مسافر: ${p.name}`,
    `${F.text("name","اسم",p.name,{req:true})}
    ${(() => { const a = S.accounts.get(p.accountId), m = a?.simId ? S.sims.get(a.simId) : null, e = S.emails.get(p.emailId);
      return `<div class="sc-cred"><div><span class="lbl">کشور</span>${flagEl(p.portal, "sm")}<b style="font-size:13px">${esc(pName(p.portal))}</b></div>${credLine("ایمیل", e?.address || p.emailId)}${m ? credLine("شماره", prettyPhone(m.number), localPhone(m.number)) : `<div><span class="lbl">شماره</span><span style="color:var(--muted)">ثبت نشده</span></div>`}</div>`; })()}
    <div class="two">${F.sel("status","وضعیت",P_OPTS,p.status)}${F.date("apptDate","تاریخ وقت",p.apptDate || "")}</div>${F.area("note","یادداشت",p.note || "")}`,
    async () => { const d = formData();
      const doneAt = d.status === "removed" ? (p.status === "removed" ? p.doneAt || today() : today()) : "";
      const ok = await write(() => db.doc("people/" + pid).update({name:d.name.trim(), status:d.status, apptDate:d.apptDate, note:d.note.trim(), doneAt}));
      if (ok && d.status !== p.status) await addLog(d.status === "booked" ? "booked" : "status", p.portal, {emailId:p.emailId, note:`${d.name.trim()}: ${P_STATUS[d.status]}${d.apptDate ? " · " + d.apptDate : ""}`});
      if (ok) toast("ذخیره شد"); return ok; },
    `<button class="btn sm ghost-red" type="button" data-act="pdel" data-id="${esc(pid)}">حذف مسافر</button>`);
}

// ---------- protected delete (phones & emails): red centred modal + typed confirmation ----------
function confirmHardDelete(kind, code){
  const isSim = kind === "sim", rec = isSim ? S.sims.get(code) : S.emails.get(code); if (!rec) return;
  const shown = isSim ? prettyPhone(rec.number) : rec.address;
  const need = isSim ? localPhone(rec.number).slice(-4) : rec.address.split("@")[0];
  const accs = [...S.accounts.values()].filter(a => (isSim ? a.simId === code : a.emailId === code) && accStatus(a) !== "unused");
  const ppl = S.people.filter(p => p.status !== "removed" && accs.some(a => a.id === p.accountId));
  $("#dzTitle").textContent = isSim ? "حذف کامل شماره از پنل" : "حذف کامل ایمیل از پنل";
  $("#dzBody").innerHTML = `<div class="dz-what">${esc(shown)}</div>
    <div>این ${isSim ? "شماره" : "ایمیل"} برای همیشه از پنل پاک می‌شود و برگشت ندارد.</div>
    ${accs.length ? `<div><b>به این‌ها وصل است:</b><ul>${accs.map(a => `<li>VFS ${esc(pName(a.portal))} · ${esc(isSim ? (S.emails.get(a.emailId)?.address || a.emailId) : (a.simId ? localPhone(S.sims.get(a.simId)?.number) : "بدون شماره"))} · ${faN(occupants(a.id).length)} مسافر</li>`).join("")}</ul></div>` : ""}
    ${ppl.length ? `<div style="color:var(--red-ink);font-weight:800">${faN(ppl.length)} مسافر در اکانت‌های این ${isSim ? "شماره" : "ایمیل"} هستند.</div>` : ""}
    <div style="color:var(--muted)">اگر فقط نمی‌خواهید دیگر استفاده شود، به جای حذف وضعیتش را «فعلاً استفاده نشه» کنید، یا آن را با یک شماره آزاد جابجا کنید.</div>`;
  $("#dzLbl").textContent = isSim ? `برای تأیید، ۴ رقم آخر شماره را بنویسید (${need.replace(/\d/g, "•")})` : `برای تأیید، قسمت قبل از @ را بنویسید`;
  const inp = $("#dzInput"), go = $("#dzGo"); inp.value = ""; go.disabled = true;
  inp.oninput = () => { go.disabled = inp.value.trim().toLowerCase() !== need.toLowerCase(); };
  const close = () => { $("#dz").hidden = true; };
  $("#dzCancel").onclick = close;
  $("#dz").onclick = e => { if (e.target.id === "dz") close(); };
  go.onclick = async () => {
    if (inp.value.trim().toLowerCase() !== need.toLowerCase()) return;
    go.disabled = true; go.textContent = "در حال حذف…";
    const ok = await write(() => db.doc((isSim ? "sims/" : "emails/") + code).delete("CONFIRMED:" + code));
    go.textContent = "حذف کامل";
    if (ok) { await addLog("deleted", accs[0]?.portal || "", {note:`${isSim ? "شماره" : "ایمیل"} حذف شد: ${shown}`}); close(); closeSheet(); toast(`${isSim ? "شماره" : "ایمیل"} حذف شد`); }
  };
  $("#dz").hidden = false; setTimeout(() => inp.focus(), 50);
}
function editSim(id){
  const m = S.sims.get(id), code = m ? m.code : nextCode("SIM", S.sims.keys());
  openSheet(m ? `ویرایش ${code}` : "شماره جدید",
    `${F.text("number","شماره",m ? localPhone(m.number) : "",{ltr:true,req:true})}<div class="two">${F.text("operator","اپراتور",m?.operator || "")}${F.date("expiry","انقضا",m?.expiry || "")}</div>
    ${F.sel("status","وضعیت",[["active","فعال"],["inactive","فعلاً استفاده نشه"]],m?.status === "inactive" ? "inactive" : "active")}${F.text("note","یادداشت",m?.note || "")}`,
    async () => { const d = formData(), number = normPhone(d.number);
      const bad = simProblem(number, code); if (bad) { toast(bad); return false; }
      const ok = await write(() => db.doc("sims/" + code).set({...(m || {}), code, number, operator:d.operator || guessOperator(number), expiry:d.expiry, status:d.status, note:d.note, createdAt:m?.createdAt || today()})); if (ok) toast("ذخیره شد"); return ok; },
    m ? `<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn sm" type="button" data-act="swapsim" data-id="${esc(code)}">جابجایی با شماره آزاد</button><button class="btn sm del-hard" type="button" data-act="harddel" data-kind="sim" data-id="${esc(code)}">حذف کامل از پنل</button></div>` : "");
  liveCheck("number", v => v.trim() ? simProblem(normPhone(v), code) : "شماره را وارد کنید");
}
function editEmail(id){
  const e = S.emails.get(id), code = e ? e.code : nextCode("EM", S.emails.keys());
  openSheet(e ? `ویرایش ${code}` : "ایمیل جدید",
    `${F.text("address","آدرس ایمیل",e?.address || "",{ltr:true,req:true})}${F.sel("status","وضعیت",[["active","فعال"],["inactive","فعلاً استفاده نشه"]],e?.status === "inactive" ? "inactive" : "active")}${F.text("note","یادداشت",e?.note || "")}`,
    async () => { const d = formData(); const bad = emailProblem(d.address, code); if (bad) { toast(bad); return false; }
      const ok = await write(() => db.doc("emails/" + code).set({...(e || {}), code, address:d.address.trim().toLowerCase(), status:d.status, note:d.note, createdAt:e?.createdAt || today()})); if (ok) toast("ذخیره شد"); return ok; },
    e ? `<button class="btn sm del-hard" type="button" data-act="harddel" data-kind="email" data-id="${esc(code)}">حذف کامل از پنل</button>` : "");
  liveCheck("address", v => v.trim() ? emailProblem(v, code) : "ایمیل را وارد کنید");
}
function swapSim(code){
  const old = S.sims.get(code); if (!old) return;
  const accs = [...S.accounts.values()].filter(a => a.simId === code && accStatus(a) !== "unused" && accStatus(a) !== "none");
  const pcs = [...new Set(accs.map(a => a.portal))];
  const opts = simsAll().filter(x => x.code !== code && pcs.every(pc => phoneOn(pc, x.code).kind === "free"));
  const accHTML = accs.length ? accs.map(a => `<div class="swap-acc">${flagEl(a.portal, "sm")}<b>${esc(pName(a.portal))}</b><span class="mono">${esc(S.emails.get(a.emailId)?.address || a.emailId)}</span><small>${faN(occupants(a.id).length)} مسافر</small></div>`).join("") : `<div style="color:var(--muted)">این شماره به هیچ اکانتی وصل نیست؛ فقط «فعلاً استفاده نشه» می‌شود.</div>`;
  const optHTML = opts.length ? opts.map((x, i) => `<label class="swap-opt"><input type="radio" name="to" value="${esc(x.code)}" ${i ? "" : "checked"}><span class="mono">${esc(prettyPhone(x.number))}</span><small>${esc(x.code)}${x.operator ? " · " + esc(x.operator) : ""}</small></label>`).join("") : `<div style="color:var(--red-ink);font-weight:700">شماره آزادی برای ${esc(pcs.map(pName).join("، ") || "این کار")} نمانده. اول یک شماره جدید اضافه کنید.</div>`;
  openSheet(`جابجایی ${prettyPhone(old.number)}`,
    `<div class="task-lbl">اکانت‌هایی که منتقل می‌شوند</div><div class="swap-list">${accHTML}</div>
     <div class="task-lbl" style="margin-top:14px">شماره جدید</div><div class="swap-list">${optHTML}</div>
     <div class="swap-note">شماره قدیمی «فعلاً استفاده نشه» می‌شود و سابقه‌اش می‌ماند. شماره را در پروفایل VFS هم عوض کنید.</div>`,
    opts.length || !accs.length ? async () => {
      const to = accs.length ? formData().to : ""; if (accs.length && !to) { toast("یک شماره انتخاب کنید"); return false; }
      for (const a of accs){
        if (!await saveAccount(a.portal, a.emailId, {simId:to})) return false;
        await setReg(a.portal, to, "registered", a.emailId);
        await addLog("sim_swap", a.portal, {emailId:a.emailId, simId:to, note:`از ${localPhone(old.number)} به ${localPhone(S.sims.get(to).number)}`});
      }
      const ok = await write(() => db.doc("sims/" + code).set({...old, status:"inactive"}));
      if (ok) toast(accs.length ? "جابجا شد" : "کنار گذاشته شد"); return ok;
    } : null, "", accs.length ? "جابجا کن" : "فعلاً استفاده نشه");
}
function simProblem(number, selfCode){
  if (!/^\+\d{9,15}$/.test(number)) return "شماره درست نیست";
  const dup = [...S.sims.values()].find(x => x.number === number && x.code !== selfCode);
  return dup ? `این شماره قبلاً با کد ${dup.code} ثبت شده` : "";
}
function emailProblem(addr, selfCode){
  const a = addr.trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(a)) return "ایمیل درست نیست";
  const dup = [...S.emails.values()].find(x => x.address === a && x.code !== selfCode);
  return dup ? `این ایمیل قبلاً با کد ${dup.code} ثبت شده` : "";
}
function liveCheck(fieldId, fn){
  const inp = $("#" + fieldId); if (!inp) return;
  const msg = document.createElement("span"); msg.className = "err"; msg.setAttribute("role", "alert"); inp.after(msg);
  const run = () => { const p = fn(inp.value); msg.textContent = p; setSaveEnabled(!p); };
  inp.addEventListener("input", run); run();
  if (!inp.value) msg.textContent = "";
}

// ---------- passports ----------
let purged = false;
async function purgeOld(){
  if (purged || !canWrite || !db) return; purged = true;
  if (!S.portals.has("US") && !lsGet("m_us")) { lsSet("m_us", "1"); write(() => db.doc("portals/US").set({code:"US", order:S.portals.size})); }
  const old = S.passports.filter(x => x.uploadedAt && ago(x.uploadedAt) >= KEEP_DAYS);
  for (const x of old){
    if (assets) { try { await assets.delete(x.assetId); } catch {} }
    await write(() => db.doc("passports/" + x.id).delete());
  }
  if (old.length) toast(`${faN(old.length)} پاسپورت قدیمی‌تر از ${faN(KEEP_DAYS)} روز پاک شد`);
}
const EXT = {jpg:"image/jpeg", jpeg:"image/jpeg", png:"image/png", webp:"image/webp", pdf:"application/pdf", gif:"image/gif"};
const typeOf = f => f.type && Object.values(EXT).includes(f.type) ? f.type : EXT[(f.name.split(".").pop() || "").toLowerCase()] || "";
const upErr = e => ({too_large:"بیش از ۲۰ مگابایت است", unsupported_type:"فرمت قبول نیست (JPG، PNG، WEBP یا PDF)", quota_or_state:"فضای ذخیره پر است", rate_limited:"خیلی سریع؛ کمی بعد دوباره"}[e?.code] || "آپلود نشد");
const PLANE_SVG = `<svg viewBox="0 0 240 300" aria-hidden="true"><defs>
<linearGradient id="pf" x1="0" x2="1"><stop offset="0" stop-color="#dfe9ff"/><stop offset=".45" stop-color="#fff"/><stop offset="1" stop-color="#b9cdf7"/></linearGradient>
<linearGradient id="pw" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f8ff"/><stop offset="1" stop-color="#8fa9e8"/></linearGradient>
<linearGradient id="pe" x1="0" x2="1"><stop offset="0" stop-color="#6f8fdc"/><stop offset=".5" stop-color="#e8efff"/><stop offset="1" stop-color="#6f8fdc"/></linearGradient></defs>
<path d="M104 104L6 176l3 16 95-32zM136 104l98 72-3 16-95-32z" fill="url(#pw)"/>
<rect x="40" y="146" width="11" height="30" rx="5" fill="url(#pe)"/><rect x="72" y="124" width="11" height="30" rx="5" fill="url(#pe)"/>
<rect x="189" y="146" width="11" height="30" rx="5" fill="url(#pe)"/><rect x="157" y="124" width="11" height="30" rx="5" fill="url(#pe)"/>
<path d="M112 240L70 272l2 13 42-16zM128 240l42 32-2 13-42-16z" fill="url(#pw)"/>
<path d="M120 8c11 0 16 22 16 52v168c0 30-7 52-16 62-9-10-16-32-16-62V60c0-30 5-52 16-52z" fill="url(#pf)"/>
<path d="M112 250c2 20 5 32 8 40 3-8 6-20 8-40z" fill="#F7AD19"/>
<path d="M112 34c3-6 13-6 16 0l-2 7c-4-2-8-2-12 0z" fill="#27406f"/>
<path d="M120 60v180" stroke="#c9d7f5" stroke-width="1" stroke-dasharray="3 5"/></svg>`;
const UP_ICON = `<svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>`;
function uploadPassports(pcPre, initialFiles, onDone){
  if (!assets){ toast("آپلود فایل در این نما در دسترس نیست"); return; }
  const ps = portalsList();
  let pc = pcPre || (tab === "countries" && country && S.portals.has(country) ? country : "") || ps[0]?.code || "", files = [], phase = "pick", fresh = new Set(), grouped = false;
  const size = b => b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;
  const row = (x, i) => {
    const d = x.pid ? S.passports.find(p => p.id === x.pid) : null;
    const thumb = x.url ? `<img src="${x.url}" alt="">` : "PDF";
    let title = esc(x.f.name), sub = size(x.f.size), right = phase === "pick" ? `<button type="button" class="fx" data-up-rm="${i}" aria-label="حذف ${esc(x.f.name)}">×</button>` : "";
    if (x.state === "up") { sub = "در حال آپلود…"; right = `<span class="fstate spin" aria-label="در حال آپلود"></span>`; }
    if (x.state === "read") { sub = "آپلود شد · در حال خواندن اطلاعات…"; right = `<span class="fstate spin" aria-label="در حال خواندن"></span>`; }
    if (x.state === "err") { sub = `<span style="color:var(--red-ink)">${esc(x.msg)}</span>`; right = `<span class="fstate err">!</span>`; }
    if (x.state === "done") {
      right = `<span class="fstate ok">✓</span>`;
      if (d?.name) { title = esc(d.name); sub = `<div class="facts">${d.nationality ? `<span>${esc(d.nationality)}</span>` : ""}${d.passportNo ? `<span class="mono">${esc(d.passportNo)}</span>` : ""}${d.expiry ? `<span>انقضا <span class="mono">${esc(d.expiry)}</span></span>` : ""}</div>`; }
      else sub = "آپلود شد · اطلاعات را از «ویرایش» روی کارت وارد کنید";
    }
    return `<div class="frow ${fresh.has(x) ? "new" : ""}"><div class="fthumb">${thumb}</div><div class="finfo"><b>${title}</b><small>${sub}</small></div>${right}</div>`;
  };
  const F5 = [["firstName","اسم"],["lastName","فامیل"],["passportNo","شماره پاسپورت"],["nationality","ملیت"],["expiry","تاریخ انقضا"]];
  const reviewCard = (x, i) => {
    const v = x.vals || {}, ok = !x.err && (v.firstName || v.lastName || v.passportNo);
    const inp = ([k, lbl]) => `<label>${lbl}<input data-rv="${i}:${k}" ${k === "expiry" ? 'type="date" dir="ltr"' : ""} ${k === "passportNo" ? 'dir="ltr"' : ""} value="${esc(v[k] || "")}" class="${v[k] ? "" : "empty"}${k === "passportNo" ? " ltr" : ""}"></label>`;
    return `<div class="rv ${ok ? "" : "miss"}"><a class="rv-img" href="${x.url || blobUrl(x.assetId || "")}" target="_blank" rel="noopener">${x.url ? `<img src="${x.url}" alt="">` : "PDF"}</a>
      <div class="rv-f"><div class="rv-st">${x.reading ? `<span>در حال خواندن…</span><span class="fstate spin"></span>` : ok ? `<span class="ok">✓ خوانده شد · بررسی کنید</span>` : `<span class="bad">⚠ ${esc(x.err || "خوانده نشد")} · دستی وارد کنید</span>`}
        ${sampler && imgLimits && !x.reading ? `<button type="button" class="btn sm" data-rv-retry="${i}">خواندن دوباره</button>` : ""}</div>
        <div class="rv-g">${F5.slice(0,2).map(inp).join("")}</div><div class="rv-g">${F5.slice(2,4).map(inp).join("")}</div><div class="rv-g">${inp(F5[4])}<span></span></div></div></div>`;
  };
  const draw = () => {
    if (phase === "review") {
      const good = files.filter(x => x.pid);
      $("#upBody").innerHTML = `<div class="up-step"><div class="up-lbl"><i>۳</i>اطلاعات را بررسی کنید <span class="up-count">${faN(good.length)} پاسپورت · ${esc(pName(pc))}</span></div>
        <div class="flist2">${good.map(x => reviewCard(x, files.indexOf(x))).join("")}</div></div>
        ${files.some(x => x.state === "err") ? `<div class="flist2">${files.filter(x => x.state === "err").map(row).join("")}</div>` : ""}`;
      const btn = $("#sheetSave"); btn.textContent = onDone ? "ذخیره و برگشت به انتخاب مسافر" : "ذخیره و رفتن به «در انتظار ثبت»"; btn.disabled = files.some(x => x.reading);
      $("#sheetCancel").hidden = true;
      document.querySelectorAll("[data-rv]").forEach(el => el.oninput = () => { const [i, k] = el.dataset.rv.split(":"); (files[+i].vals ||= {})[k] = el.value.trim(); el.classList.toggle("empty", !el.value.trim()); });
      document.querySelectorAll("[data-rv-retry]").forEach(b => b.onclick = async () => { const x = files[+b.dataset.rvRetry]; x.reading = true; x.err = ""; draw();
        const r = await readNames([{id:x.pid, file:x.f}]); x.reading = false; x.err = r?.errors?.[x.pid] || ""; pull(x); draw(); });
      return;
    }
    const busy = phase !== "pick";
    $("#upBody").innerHTML = `
      <div class="up-step"><div class="up-lbl"><i>۱</i>برای کدام کشور؟</div>
        <div class="cchips">${ps.map(p => `<button type="button" class="cchip ${p.code === pc ? "on" : ""}" style="--cc:${colorOf(p.code)}" data-up-pc="${esc(p.code)}" ${busy ? "disabled" : ""} aria-pressed="${p.code === pc}">${flagEl(p.code, "sm")} ${esc(pName(p.code))}</button>`).join("")}</div></div>
      <div class="up-step"><div class="up-lbl"><i>۲</i>فایل پاسپورت‌ها${files.length ? ` <span class="up-count">${faN(files.length)} فایل</span>` : ""}</div>
        ${busy ? "" : `<label class="dropz ${files.length ? "has" : ""}" id="drop" for="files">${UP_ICON}<div><b>${files.length ? "فایل دیگری هم دارید؟ اینجا رها کنید" : "پاسپورت‌ها را اینجا رها کنید"}</b><br><span>${files.length ? "یا بزنید و انتخاب کنید" : "یا بزنید و از گوشی یا کامپیوتر انتخاب کنید · JPG، PNG یا PDF · چندتایی"}</span></div><input id="files" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"></label>`}
        ${files.length ? `<div class="flist2">${files.map(row).join("")}</div>` : ""}</div>
      ${files.length > 1 && !busy ? `<label class="sw">این‌ها با هم در یک اکانت باشند<input type="checkbox" id="grp" ${grouped ? "checked" : ""}></label>` : ""}
      <div class="up-step" style="opacity:.55"><div class="up-lbl"><i>۳</i>بررسی اطلاعات خوانده‌شده</div></div>`;
    fresh.clear();
    const btn = $("#sheetSave");
    if (phase === "pick") { btn.textContent = files.length ? `آپلود ${faN(files.length)} پاسپورت` : "آپلود"; setSaveEnabled(files.length > 0); }
    else if (phase === "busy") { btn.textContent = files.some(x => x.state === "read") ? "در حال خواندن اطلاعات…" : "در حال آپلود…"; btn.disabled = true; }
    else { btn.textContent = "تمام"; btn.disabled = false; }
    $("#sheetCancel").hidden = phase !== "pick";
    bind();
  };
  const addFiles = list => {
    let bad = 0;
    for (const f of list){ if (!typeOf(f)) { bad++; continue; } if (files.some(x => x.f.name === f.name && x.f.size === f.size)) continue;
      const x = {f, state:"ready", url: typeOf(f).startsWith("image/") ? URL.createObjectURL(f) : ""}; files.push(x); fresh.add(x); }
    if (bad) toast(`${faN(bad)} فایل قبول نشد؛ فقط JPG، PNG، WEBP یا PDF`);
    draw();
  };
  const bind = () => {
    document.querySelectorAll("[data-up-pc]").forEach(b => b.onclick = () => { pc = b.dataset.upPc; draw(); });
    document.querySelectorAll("[data-up-rm]").forEach(b => b.onclick = () => { const x = files.splice(+b.dataset.upRm, 1)[0]; if (x?.url) URL.revokeObjectURL(x.url); draw(); });
    const inp = $("#files"); if (inp) inp.onchange = e => { addFiles(e.target.files); };
    const g = $("#grp"); if (g) g.onchange = () => { grouped = g.checked; };
  };
  const pull = x => { const d = S.passports.find(p => p.id === x.pid); if (d) x.vals = {firstName:d.firstName || "", lastName:d.lastName || "", passportNo:d.passportNo || "", nationality:d.nationality || "", expiry:d.expiry || ""}; };
  openSheet("آپلود پاسپورت", `<div id="upBody" style="display:flex;flex-direction:column;gap:18px"></div>`,
    async () => {
      if (phase === "review") {
        for (const x of files.filter(f => f.pid)){
          const v = x.vals || {}, f = (v.firstName || "").toUpperCase(), l = (v.lastName || "").toUpperCase();
          const patch = {firstName:f, lastName:l, name:[f, l].filter(Boolean).join(" "), passportNo:(v.passportNo || "").toUpperCase(), nationality:(v.nationality || "").toUpperCase(), expiry:v.expiry || ""};
          const d = S.passports.find(p => p.id === x.pid);
          if (!d || ["firstName","lastName","passportNo","nationality","expiry"].some(k => (d[k] || "") !== patch[k])) await write(() => db.doc("passports/" + x.pid).update(patch));
        }
        files.forEach(x => x.url && URL.revokeObjectURL(x.url));
        toast("ذخیره شد");
        if (onDone) { const pids = files.filter(f => f.pid).map(f => f.pid); setTimeout(() => onDone(pids), 60); return true; }
        tab = "pass"; lsSet("tab4", tab); render(); return true;
      }
      if (!files.length || !pc) return false;
      phase = "busy"; draw();
      const okItems = [], gid = grouped && files.length > 1 ? "g" + Date.now().toString(36) : "";
      for (const x of files){ x.state = "up"; draw();
        try { const r = await assets.upload(x.f, {type: typeOf(x.f)});
          const ref = db.collection("passports").doc();
          if (await write(() => ref.set({assetId:r.id, contentType:r.contentType, fileName:x.f.name, portal:pc, firstName:"", lastName:"", name:"", passportNo:"", nationality:"", expiry:"", status:"waiting", groupId:gid, accountId:"", emailId:"", personId:"", uploadedAt:today(), at:new Date().toISOString()}))) { x.pid = ref.id; x.state = sampler && imgLimits ? "read" : "done"; okItems.push(x); }
          else { x.state = "err"; x.msg = "ذخیره نشد"; }
        } catch (e) { x.state = "err"; x.msg = upErr(e); }
        draw(); }
      if (okItems.length) await addLog("upload", pc, {note:`${faN(okItems.length)} پاسپورت`});
      if (sampler && imgLimits && okItems.length){
        const readable = okItems.filter(x => imgLimits.mediaTypes.includes(typeOf(x.f)) && x.f.size <= imgLimits.maxInputBytes);
        okItems.filter(x => !readable.includes(x)).forEach(x => x.err = "این فایل خوانده نمی‌شود");
        if (readable.length) { const r = await readNames(readable.map(x => ({id:x.pid, file:x.f}))); readable.forEach(x => x.err = r?.errors?.[x.pid] || ""); }
      } else okItems.forEach(x => x.err = "خواندن خودکار فعال نیست");
      okItems.forEach(x => { x.state = "done"; pull(x); });
      phase = okItems.length ? "review" : "pick"; draw();
      return false;
    }, "", "آپلود");
  draw();
  if (initialFiles) addFiles(initialFiles);
  const body = $("#sheetForm");
  body.ondragover = e => { if (phase !== "pick") return; e.preventDefault(); $("#drop")?.classList.add("over"); };
  body.ondragleave = e => { if (!body.contains(e.relatedTarget)) $("#drop")?.classList.remove("over"); };
  body.ondrop = e => { if (phase !== "pick") return; e.preventDefault(); $("#drop")?.classList.remove("over"); addFiles(e.dataTransfer.files); };
}
async function readNames(items, st){
  if (st) st.textContent = "در حال خواندن اطلاعات پاسپورت‌ها…";
  try { const r = await API("read", {ids:items.map(i => i.id)}); await refresh(); return {errors:r.errors || {}}; }
  catch (e) { const errors = {}; items.forEach(i => errors[i.id] = e.message || "خواندن انجام نشد"); return {errors}; }
}
function editPassport(pid){
  const x = S.passports.find(p => p.id === pid); if (!x) return;
  const isImg = (x.contentType || "").startsWith("image/");
  openSheet(`پاسپورت${x.name ? ": " + x.name : ""}`,
    `${isImg ? `<a href="${esc(blobUrl(x.assetId))}" target="_blank" rel="noopener"><img src="${esc(blobUrl(x.assetId))}" alt="پاسپورت" style="width:100%;border-radius:12px"></a>` : `<a class="btn" href="${esc(blobUrl(x.assetId))}" target="_blank" rel="noopener">باز کردن PDF</a>`}
    <div class="two">${F.text("firstName","اسم",x.firstName ?? x.name ?? "",{req:true})}${F.text("lastName","فامیل",x.lastName || "")}</div>
    <div class="two">${F.text("passportNo","شماره پاسپورت",x.passportNo || "",{ltr:true})}${F.text("nationality","ملیت",x.nationality || "")}</div>
    <div class="two">${F.date("expiry","تاریخ انقضای پاسپورت",x.expiry || "")}${x.status === "waiting" ? F.sel("portal","کشور",portalsList().map(p => [p.code, pName(p.code)]),x.portal) : `<div class="field"><label>کشور</label><div class="note">${esc(pName(x.portal))}</div></div>`}</div>
    <div class="note">این پاسپورت و اطلاعاتش ${rel(addDaysISO(x.uploadedAt, KEEP_DAYS))} (${esc(addDaysISO(x.uploadedAt, KEEP_DAYS))}) خودکار پاک می‌شود. اسم مسافر در اکانت می‌ماند.</div>
    ${x.status === "assigned" ? `<div class="note">در اکانت <span class="mono">${esc(S.emails.get(x.emailId)?.address || x.emailId)}</span>. برای جدا کردن، مسافر را از تب مسافران ویرایش کنید.</div>` : ""}
    ${x.groupId && x.status === "waiting" ? `<label class="sw">در گروه با بقیه<input type="checkbox" name="inGroup" checked></label>` : ""}
    ${sampler && imgLimits ? `<button class="btn sm" type="button" id="reread">خواندن دوباره</button>` : ""}`,
    async () => { const d = formData();
      const f = d.firstName.trim(), l = d.lastName.trim();
      const patch = {firstName:f, lastName:l, name:[f, l].filter(Boolean).join(" "), passportNo:d.passportNo.trim().toUpperCase(), nationality:d.nationality.trim(), expiry:d.expiry}; if (d.portal) patch.portal = d.portal;
      if (x.groupId && x.status === "waiting" && !d.inGroup) patch.groupId = "";
      const ok = await write(() => db.doc("passports/" + pid).update(patch));
      if (ok && x.personId && patch.name !== x.name) await write(() => db.doc("people/" + x.personId).update({name:patch.name}));
      if (ok) toast("ذخیره شد"); return ok; },
    x.status === "waiting" ? `<button class="btn sm ghost-red" type="button" id="delBtn">حذف پاسپورت</button>` : "");
  if (x.status === "waiting") armDelete(async () => { const ok = await write(() => db.doc("passports/" + pid).delete()); if (ok && assets) { try { await assets.delete(x.assetId); } catch {} } return ok; });
  const rr = $("#reread"); if (rr) rr.onclick = async () => { rr.disabled = true; rr.textContent = "در حال خواندن…";
    try { await readNames([{id:pid}]); closeSheet(); }
    catch { toast("فایل برای خواندن باز نشد"); rr.disabled = false; rr.textContent = "خواندن دوباره"; } };
}
function assignPassports(){
  const items = [...picked].map(id => S.passports.find(x => x.id === id && x.status === "waiting")).filter(Boolean);
  if (!items.length) return;
  if (new Set(items.map(x => x.portal)).size > 1) { toast("پاسپورت‌های یک کشور را با هم انتخاب کنید"); return; }
  const noName = items.filter(x => !x.name.trim()); if (noName.length) { toast(`${faN(noName.length)} پاسپورت اسم ندارد؛ اول اسمش را وارد کنید`); return; }
  const pc = items[0].portal;
  const accs = emailsAll().map(e => ({e, id:accId(pc, e.code), a:S.accounts.get(accId(pc, e.code))})).filter(x => ["active","check"].includes(accStatus(x.a)))
    .map(x => ({...x, free:CAP - occupants(x.id).length, sim:x.a.simId ? S.sims.get(x.a.simId) : null})).filter(x => x.free > 0).sort((a,b) => b.free - a.free);
  const fit = accs.filter(x => x.free >= items.length);
  if (!accs.length) { toast(`روی ${pName(pc)} اکانت با جای خالی نیست؛ اول اکانت بسازید`); return; }
  if (!fit.length) { toast(`هیچ اکانتی ${faN(items.length)} جای خالی ندارد؛ تعداد کمتری انتخاب کنید (بیشترین: ${faN(accs[0].free)})`); return; }
  openSheet(`اضافه به اکانت · ${pName(pc)}`,
    `<div class="note">${items.map(x => esc(x.name)).join("، ")}</div>
    ${F.sel("acc","کدام اکانت؟",accs.map(x => [x.id, `${x.e.address}${x.sim ? " · " + localPhone(x.sim.number) : ""} — ${faN(x.free)} جای خالی${accStatus(x.a) === "check" ? " (باید چک شود)" : ""}`, x.free < items.length]),fit[0].id)}
    <div class="two">${F.sel("status","وضعیت",[["waitlist","Waitlist"],["booked","وقت گرفته شد"]],"waitlist")}${F.date("apptDate","تاریخ وقت","")}</div>`,
    async () => { const d = formData(), em = d.acc.split("__")[1], a = S.accounts.get(d.acc);
      for (const x of items){ const ref = db.collection("people").doc();
        if (!await write(() => ref.set({name:x.name, portal:pc, emailId:em, accountId:d.acc, status:d.status, apptDate:d.apptDate, note:"", passportId:x.id, addedAt:today()}))) return false;
        await write(() => db.doc("passports/" + x.id).update({status:"assigned", accountId:d.acc, emailId:em, personId:ref.id})); }
      await saveAccount(pc, em, {status:"active", lastVerified:today()});
      await addLog(d.status === "booked" ? "booked" : "added", pc, {emailId:em, simId:a?.simId || "", note:items.map(x => x.name).join("، ")});
      picked.clear(); toast(`${faN(items.length)} نفر به اکانت اضافه شد`); return true; }, "", "اضافه کن");
}

function addPortal(){
  const have = new Set(S.portals.keys()), opts = CATALOG.filter(c => !have.has(c[0])).map(([c,n]) => [c, `${n} (VFS ${c})`]);
  openSheet("افزودن کشور", F.sel("pc","کشور",opts,opts[0]?.[0] || ""),
    async () => { const d = formData(); if (!d.pc) return false; const ok = await write(() => db.doc("portals/" + d.pc).set({code:d.pc, order:S.portals.size})); if (ok) toast("اضافه شد"); return ok; });
}
function bulkAdd(){
  openSheet("افزودن گروهی", `${F.area("bulk","شماره‌ها و ایمیل‌ها (هر خط یکی)","",{ltr:true,hint:"تکراری‌ها خودکار رد می‌شوند."})}<div class="note" id="bulkPrev">هنوز چیزی وارد نشده.</div>`,
    async () => { const {phones, emails} = parseBulk($("#bulk").value); if (!phones.length && !emails.length) { toast("چیزی پیدا نشد"); return false; }
      const sIds = [...S.sims.keys()], eIds = [...S.emails.keys()];
      for (const p of phones){ const code = nextCode("SIM", sIds); sIds.push(code); if (!await write(() => db.doc("sims/" + code).set({code, number:p, operator:guessOperator(p), expiry:"", status:"active", note:"", createdAt:today()}))) return false; }
      for (const a of emails){ const code = nextCode("EM", eIds); eIds.push(code); if (!await write(() => db.doc("emails/" + code).set({code, address:a, status:"active", note:"", createdAt:today()}))) return false; }
      toast(`${faN(phones.length)} شماره و ${faN(emails.length)} ایمیل اضافه شد`); return true; }, "", "افزودن");
  $("#bulk").addEventListener("input", () => { const r = parseBulk($("#bulk").value); $("#bulkPrev").innerHTML = `${faN(r.phones.length)} شماره · ${faN(r.emails.length)} ایمیل جدید${r.dupes ? `<br><span class="err">تکراری، ثبت نمی‌شود: <span class="mono">${r.dupList.map(esc).join("، ")}</span></span>` : ""}${r.junk.length ? `<br>شناخته نشد: <span class="mono">${r.junk.map(esc).join("، ")}</span>` : ""}`; });
}
function parseBulk(text){
  const known = new Set([...[...S.sims.values()].map(m => m.number), ...[...S.emails.values()].map(e => e.address)]);
  const phones = [], emails = [], junk = [], dupList = [];
  for (const raw of text.split(/[\n,;]+/).map(x => x.trim()).filter(Boolean)){
    const em = raw.match(/[^\s<>]+@[^\s<>]+\.[a-z]{2,}/i);
    if (em){ const a = em[0].toLowerCase(); known.has(a) ? dupList.push(a) : (known.add(a), emails.push(a)); continue; }
    const p = normPhone(raw); if (/^\+\d{9,15}$/.test(p)){ known.has(p) ? dupList.push(localPhone(p)) : (known.add(p), phones.push(p)); continue; }
    junk.push(raw);
  }
  return {phones, emails, dupes:dupList.length, dupList, junk};
}
function exportCSV(){ location.href = "?api=export"; }
function copyText(t, btn){
  const done = () => { const o = btn.textContent; btn.textContent = "کپی شد ✓"; setTimeout(() => btn.textContent = o, 1200); };
  try { navigator.clipboard.writeText(t).then(done, () => toast(t)); } catch { toast(t); }
}

// ---------- events ----------
$("#tabs").addEventListener("click", e => { const b = e.target.closest(".tab"); if (!b) return; tab = b.dataset.tab; if (tab === "countries") { country = ""; lsSet("country3", ""); } lsSet("tab4", tab); render(); window.scrollTo({top:0}); });
document.addEventListener("click", e => {
  const c = e.target.closest("[data-copy]"); if (c) { copyText(c.dataset.copy, c); return; }
  const b = e.target.closest("[data-act]"); if (!b || b.disabled) return;
  const a = b.dataset.act, id = b.dataset.id;
  if (a === "open"){ country = id; lsSet("country3", id); render(); window.scrollTo({top:0}); return; }
  if (a === "back"){ country = ""; lsSet("country3", ""); render(); return; }
  if (a === "export") return exportCSV();
  if (a === "theme"){ document.documentElement.dataset.theme = isDark() ? "light" : "dark"; try { localStorage.setItem("theme", document.documentElement.dataset.theme); } catch {} syncTheme(); return; }
  if (a === "readappt") return apptReadSheet();
  if (a === "resopen") return openRes(b.dataset.kind, id);
  if (a === "accdel"){ const acc = S.accounts.get(id); if (!acc || !canWrite) return; const occ = occupants(id); closeSheet();
    return undoable(async () => {
      for (const p of occ) await write(() => db.doc("people/" + p.id).update({status:"removed", doneAt:today(), note:[p.note, "اکانت حذف شد"].filter(Boolean).join(" · ")}));
      if (acc.simId) await setReg(acc.portal, acc.simId, "unknown");
      if (await write(() => db.doc("accounts/" + id).delete())) { await addLog("edit", acc.portal, {emailId:acc.emailId, simId:acc.simId || "", note:"اکانت حذف شد"});
        toast(`اکانت ${pName(acc.portal)} حذف شد${occ.length ? ` · ${faN(occ.length)} مسافرش «اتمام کار» شد` : ""}`); }
    }); }
  if (a === "pfinish" || a === "pdel"){ const p = S.people.find(x => x.id === id); if (!p || !canWrite) return;
    closeSheet();
    return undoable(async () => {
      if (a === "pfinish"){ if (await write(() => db.doc("people/" + id).update({status:"removed", doneAt:today()}))) { await addLog("status", p.portal, {emailId:p.emailId, note:`${p.name}: اتمام کار`}); toast(`${p.name}: اتمام کار · تا ${faN(DONE_DAYS)} روز در «مسافران» می‌ماند`); } }
      else if (await write(() => db.doc("people/" + id).delete())) { await addLog("status", p.portal, {emailId:p.emailId, note:`${p.name}: حذف شد`}); toast(`${p.name} حذف شد`); }
    }); }
  if (a === "flow"){ if (id === "up") return uploadPassports(""); document.querySelector(`.tab[data-tab="${id}"]`)?.click(); window.scrollTo(0, 0); return; }
  if (a === "pcountry"){ peopleCountry = id; render(); return; }
  if (a === "wlshow"){ wlShow = id; render(); return; }
  if (a === "book"){ if (canWrite) bookSheet([id]); return; }
  if (a === "bookall"){ if (canWrite) bookSheet(S.people.filter(p => p.accountId === id && p.status === "waitlist").map(p => p.id)); return; }
  if (a === "pstatus2"){ peopleStatus = id; render(); return; }
  if (a === "swapsim"){ if (!canWrite) { toast("این صفحه برای شما فقط خواندنی است"); return; } swapSim(id); return; }
  if (a === "harddel"){ if (!canWrite) { toast("این صفحه برای شما فقط خواندنی است"); return; } confirmHardDelete(b.dataset.kind, id); return; }
  if (a === "pfilter"){ passFilter = id; picked.clear(); render(); return; }
  if (a === "pstatus"){ passStatus = id; render(); return; }
  if (a === "gopass"){ tab = "pass"; passFilter = id; passStatus = "waiting"; lsSet("tab4", tab); render(); window.scrollTo({top:0}); return; }
  if (a === "clearpick"){ picked.clear(); render(); return; }
  if (!canWrite && a !== "acc") { toast("این صفحه برای شما فقط خواندنی است"); return; }
  if (a.startsWith("q_")){ b.disabled = true; (a === "q_skip" ? queueAct(a, id) : undoable(() => queueAct(a, id))).finally(() => { b.disabled = false; }); return; }
  if (["ok","gone","phonetaken","phonefree"].includes(a)){ b.disabled = true; const inSheet = !!b.closest("#sheet"); quick(a, id).finally(() => { b.disabled = false; if (a === "ok" && inSheet) openAccount(id); }); return; }
  ({upload:() => uploadPassports(id || ""), editpass:() => editPassport(id), assign:assignPassports, newacc:() => newAccount(id, b.dataset.email || ""), acc:() => openAccount(id), addpeople:() => addPeople(id), person:() => editPerson(id), sim:() => editSim(id), email:() => editEmail(id), addportal:addPortal, bulk:bulkAdd}[a] || (() => {}))();
});
document.addEventListener("change", e => { if (e.target.id === "qfiles" && e.target.files.length) { uploadPassports("", e.target.files); } });
document.addEventListener("dragover", e => { const q = $("#qdrop"); if (!q || !$("#sheet").hidden) return; e.preventDefault(); q.classList.add("over"); });
document.addEventListener("dragleave", e => { if (!e.relatedTarget) $("#qdrop")?.classList.remove("over"); });
document.addEventListener("drop", e => { const q = $("#qdrop"); if (!q || !$("#sheet").hidden) return; e.preventDefault(); q.classList.remove("over"); if (e.dataTransfer.files.length) uploadPassports("", e.dataTransfer.files); });
document.addEventListener("change", e => { const c = e.target.closest("[data-pick]"); if (!c) return; c.checked ? picked.add(c.dataset.pick) : picked.delete(c.dataset.pick); const y = window.scrollY; render(); window.scrollTo({top:y}); });
document.addEventListener("input", e => { if (e.target.id === "passQ"){ passQ = e.target.value; const pos = e.target.selectionStart; render(); const q = $("#passQ"); if (q){ q.focus(); q.setSelectionRange(pos, pos); } return; } if (e.target.id === "peopleQ"){ peopleQ = e.target.value; const pos = e.target.selectionStart; render(); const q = $("#peopleQ"); if (q){ q.focus(); q.setSelectionRange(pos, pos); } } });
document.addEventListener("change", e => { if (e.target.id === "logPortal"){ logPortal = e.target.value; render(); } });
$("#sheetClose").onclick = closeSheet; $("#sheetCancel").onclick = closeSheet; $("#scrim").onclick = closeSheet;
document.addEventListener("keydown", e => { if (e.key !== "Escape") return; if (!$("#dz").hidden) { $("#dz").hidden = true; return; } if (!$("#sheet").hidden) closeSheet(); });
$("#sheetForm").addEventListener("submit", async e => { e.preventDefault(); if (!sheetSave) return; const btn = $("#sheetSave"); btn.disabled = true; const ok = await sheetSave(); btn.disabled = false; if (ok) closeSheet(); });
function showBanner(t){ const b = $("#banner"); b.textContent = t; b.hidden = false; }

render();
(async () => {
  db = makeDb(); assets = makeAssets();
  try { await refresh(); } catch (e) { showBanner("اتصال به سرور برقرار نشد. صفحه را دوباره باز کنید."); }
  if (SERVER_AI) { sampler = {}; imgLimits = {maxCount:4, maxInputBytes:20 * 1024 * 1024, mediaTypes:["image/jpeg","image/png","image/webp","image/gif","application/pdf"]}; }
  purged = true;
  const sub = (key, q, fn) => q.onSnapshot(snap => { fn(snap); loaded[key] = 1; render(); });
  const toMap = (m, snap, k = "code") => { m.clear(); for (const d of snap.docs) m.set(d.id, {...d.data(), [k]: d.id}); };
  sub("sims", db.collection("sims"), s => toMap(S.sims, s));
  sub("emails", db.collection("emails"), s => toMap(S.emails, s));
  sub("portals", db.collection("portals"), s => toMap(S.portals, s));
  sub("accounts", db.collection("accounts"), s => toMap(S.accounts, s, "id"));
  sub("regs", db.collection("regs"), s => toMap(S.regs, s, "id"));
  sub("people", db.collection("people"), s => { S.people = s.docs.map(d => { const x = {...d.data(), id:d.id}; if (x.status === "done") x.status = "removed"; return x; }); });
  sub("passports", db.collection("passports"), s => { S.passports = s.docs.map(d => ({...d.data(), id:d.id})); });
  sub("logs", db.collection("logs").orderBy("at","desc").limit(1000), s => { S.logs = s.docs.map(d => ({...d.data(), id:d.id})); });
  setInterval(() => { if (!document.hidden && $("#sheet").hidden) refresh().catch(() => {}); }, 20000);
  document.addEventListener("visibilitychange", () => { if (!document.hidden && $("#sheet").hidden) refresh().catch(() => {}); });
})();
})();
</script>

</body>
</html>
