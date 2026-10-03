<?php
/*
 * Travel Market — Visa Service Terms (single-file wizard)
 * SOUQ AL SAFAR TOURISM L.L.C — Dubai Licence No. 1063865
 *
 * Upload this file as index.php to a folder on a PHP host with HTTPS.
 * Fill in the settings below (server side only — never shown to the browser).
 * Setup guide: see README-fa.md next to this file in the repository.
 */

// ===================== SETTINGS — only edit this block =====================
$CONFIG = [
  // Claude API key used to read the Emirates ID photo (console.anthropic.com → API Keys)
  'anthropic_api_key' => '',
  'model'             => 'claude-opus-5-5',

  // SMTP account that sends the PDF copy to the company
  'smtp_host'      => '',            // e.g. smtp.hostinger.com
  'smtp_port'      => 465,           // 465 (ssl) or 587 (tls)
  'smtp_secure'    => 'ssl',         // 'ssl' or 'tls'
  'smtp_user'      => '',            // usually the full mailbox address
  'smtp_pass'      => '',
  'mail_from'      => '',            // sender address (normally same as smtp_user)
  'company_email'  => '',            // where signed agreements are sent; several: 'a@x.com, b@y.com'

  // Private folder for temporary files. Leave empty = automatic, outside public_html.
  'private_dir'    => '',
  // How long (minutes) a PDF / session stays on the server before automatic deletion
  'keep_minutes'   => 120,
];
// ===========================================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Dubai');

const TERMS_VERSION = '2026-10-v1';
const LIB_TCPDF = '6.10.1';
const LIB_MAILER = 'v7.0.2';

$COMPANY = [
  'legal'   => 'SOUQ AL SAFAR TOURISM L.L.C',
  'arabic'  => 'سوق السفر للسياحة ش.ذ.م.م',
  'licence' => '1063865',
  'brand'   => 'Travel Market',
];

$TERMS = [
  'a' => [
    'en_t' => 'Visa decision',
    'en'   => 'The decision to issue or refuse a visa rests with the issuing authority. The company does not guarantee that a visa will be issued, or when the authority will make its decision.',
    'ar_t' => 'قرار التأشيرة',
    'ar'   => 'قرار إصدار التأشيرة أو رفضها يعود إلى الجهة المُصدِرة. لا تضمن الشركة إصدار التأشيرة ولا موعد اتخاذ الجهة لقرارها.',
  ],
  'b' => [
    'en_t' => 'How our services are provided',
    'en'   => 'For your convenience, document review and follow-up of your application are carried out online. If you need an in-person meeting, please arrange it with our team in advance.',
    'ar_t' => 'طريقة تقديم الخدمات',
    'ar'   => 'تيسيراً عليكم، تتم مراجعة المستندات ومتابعة الطلب عبر الإنترنت. إذا احتجتم إلى اجتماع حضوري، يرجى التنسيق مع فريقنا مسبقاً.',
  ],
  'c' => [
    'en_t' => 'Fees',
    'en'   => 'Amounts paid to the company are for the company\'s own services. Embassy fees are paid by the customer directly.',
    'ar_t' => 'الرسوم',
    'ar'   => 'المبالغ المدفوعة للشركة هي مقابل خدمات الشركة نفسها. أما رسوم السفارة فيدفعها العميل مباشرةً.',
  ],
  'd' => [
    'en_t' => 'Visa refusal and refunds',
    'en'   => 'A visa refusal does not, by itself, entitle the customer to a refund of service fees for services that were correctly performed as agreed. The customer\'s rights regarding services not performed, or any failure by the company, remain protected.',
    'ar_t' => 'رفض التأشيرة واسترداد المبالغ',
    'ar'   => 'رفض التأشيرة بحد ذاته لا يوجب استرداد رسوم الخدمات التي نُفِّذت بشكل صحيح وفق الاتفاق. وتبقى حقوق العميل محفوظة فيما يتعلق بالخدمات غير المنفَّذة أو أي تقصير من جانب الشركة.',
  ],
];
$TERMS_NOTE = [
  'en' => 'The service fee and the description of your order are agreed with you separately. This form only records your acceptance of the general service terms.',
  'ar' => 'يتم الاتفاق معكم بشكل منفصل على رسوم الخدمة ووصف طلبكم. يقتصر هذا النموذج على تسجيل موافقتكم على الشروط العامة للخدمات.',
];
$DECLARATION = [
  'en' => 'I have read the full agreement, my details above are correct, and I accept these terms. The signature I drew is my signature on this agreement.',
  'ar' => 'لقد قرأت الاتفاقية كاملةً، وبياناتي أعلاه صحيحة، وأوافق على هذه الشروط. التوقيع الذي رسمته هو توقيعي على هذه الاتفاقية.',
];

// Library files fetched once from GitHub (pinned version, SHA-256 checked).
$LIBS = [
  'tcpdf' => ['base' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/' . LIB_TCPDF . '/', 'files' => [
    'tcpdf.php' => '7dba861e596450737c0c63e006b07e1995f0be4754755c32efe54f6712fbebe7',
    'tcpdf_autoconfig.php' => '09223073deececec07da66c8023d5a4abde122ddeb5f7d54459e3f48fe8860aa',
    'include/tcpdf_font_data.php' => 'f638dabefa06b48d221be90448e8dec07303bc4fa333913ebe174eb25780153c',
    'include/tcpdf_fonts.php' => '33e7f8647b295584c85461d35658afdeea5d5da5fbae2aa24b16cbc1798bbb76',
    'include/tcpdf_colors.php' => 'fc0aae08f6bcf5b2c9500178b8dc91c3e3ac126a580fb510ad13c3163442e6e9',
    'include/tcpdf_images.php' => 'dd615410895b6055727223d7198e1cd485aa3fcb6d1195799c29ec4726e38085',
    'include/tcpdf_static.php' => 'dff66b73df6a2e32b3cc2525a20df991fa7d0883ade5dc2e6dc65b9cbbdc8e0f',
    'fonts/helvetica.php' => 'd00f610d79e7b3d62831f5b280c3d67fba6dd27e3d8e731c5e08c9997274515c',
    'fonts/helveticab.php' => 'da221bac61a548428591037e865f42adde25330fd6767870b233716ddd0bd795',
    'fonts/helveticai.php' => 'd4ed58a6edf5d848c4c32d361d41228496933d735e79d0e725523e4968cff74f',
    'fonts/dejavusans.php' => '2311c1906b07b3e86e1444e1e2315e086f3efbb89f117f97ff81a8b44ea61d2b',
    'fonts/dejavusans.z' => '0b911bc136718959ad0c6e175537dcd3f4c91d1765ddb2c36427af3b50f4a7e1',
    'fonts/dejavusans.ctg.z' => '6d983c257d60508cb24c69e3fa716ff7bebaa9c5e3fd724dab1ba7908f10b87d',
    'fonts/dejavusansb.php' => 'b2d8a30e2f7823ecab8c612cab6c664f3c4b9dbf31e7729bd1620d90a1a4933a',
    'fonts/dejavusansb.z' => '18c349b1f422d5b3764a8706949af3306f01baa5b74b8660260a7fa140b2a974',
    'fonts/dejavusansb.ctg.z' => 'a640299ef8dab84c25c558c71c8608625c9f762edd3cf305a4716d13a5b68de9',
    'fonts/dejavusansi.php' => '7a38b312bde0866543483f23d6f711597406343d641c8dbe9cf94561b0dd60f2',
    'fonts/dejavusansi.z' => 'bb51fc0e36775b48b88798b58cfeef076214a0086daaac05792a712a1ec2c524',
    'fonts/dejavusansi.ctg.z' => '301d861615d72ee46a2b9c295453c72ffe00f0dfc0a32964fae6b5e2c8e3c8e2',
    'fonts/aealarabiya.php' => 'd5b6d64b8f22993b6aa7e69ed3537f8846fd99efed945707e0364b9397075c1c',
    'fonts/aealarabiya.z' => '999e1b6fb591d2518342df92068ffee076d75c36c5af911608ea112d15bf71e8',
    'fonts/aealarabiya.ctg.z' => 'f803cd152facef180b514623937fd423831c48da02b01f1417323410cc98598b',
  ]],
  'phpmailer' => ['base' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/' . LIB_MAILER . '/', 'files' => [
    'src/Exception.php' => '22ab858ae438d98f58f41f38ad2191d1b0d59570aebea0463a7948cfae1021b7',
    'src/PHPMailer.php' => '01038c4104554fe7fdb958eb4653883c5cf93af76a3c8797794001c35c1bf553',
    'src/SMTP.php' => 'f7c8210e4047871f9f0b0639d7387d5574738fb9984f744d58d85378da77c68e',
  ]],
];

// ------------------------------------------------------------------ paths
function private_dir() {
  global $CONFIG;
  if ($CONFIG['private_dir'] !== '') return rtrim($CONFIG['private_dir'], '/');
  $pos = strpos(__DIR__, '/public_html');
  $base = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__);
  return $base . '/travel-market-private';
}
$P = private_dir();
$KEEP = max(15, (int)$CONFIG['keep_minutes']) * 60;

function ensure_dirs($P) {
  foreach (['', '/sessions', '/files', '/rl', '/lib', '/tmp'] as $d) {
    if (!is_dir($P . $d) && !@mkdir($P . $d, 0700, true)) return false;
  }
  @file_put_contents("$P/.htaccess", "Require all denied\nDeny from all\n");
  @file_put_contents("$P/index.html", '');
  return is_writable("$P/files");
}

function inside_docroot($P) {
  $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
  $rp = realpath($P);
  if (!$root || !$rp) return false;
  return $rp === $root || strpos($rp . '/', rtrim($root, '/') . '/') === 0;
}

// ---------------------------------------------------------------- cleanup
function cleanup($P, $KEEP) {
  $now = time();
  foreach (glob("$P/files/*") ?: [] as $f) if (is_file($f) && $now - filemtime($f) > $KEEP) @unlink($f);
  foreach (glob("$P/sessions/sess_*") ?: [] as $f) if ($now - filemtime($f) > $KEEP) @unlink($f);
  foreach (glob("$P/rl/*") ?: [] as $f) if ($now - filemtime($f) > 7200) @unlink($f);
  foreach (glob("$P/tmp/*") ?: [] as $f) if (is_file($f) && $now - filemtime($f) > 3600) @unlink($f);
}

if (PHP_SAPI === 'cli') {
  // Optional cron job:  php /path/to/index.php cleanup
  if (($argv[1] ?? '') === 'cleanup') { cleanup($P, $KEEP); echo "ok\n"; }
  exit;
}

// ------------------------------------------------------- library install
function libs_ready($P, $LIBS) {
  foreach ($LIBS as $name => $lib) foreach ($lib['files'] as $rel => $hash)
    if (!is_file("$P/lib/$name/$rel")) return false;
  return true;
}
function install_libs($P, $LIBS) {
  if (libs_ready($P, $LIBS)) return true;
  if (!function_exists('curl_init')) return false;
  @set_time_limit(180);
  $lock = fopen("$P/lib/.lock", 'c');
  if (!$lock || !flock($lock, LOCK_EX)) return false;
  $ok = true;
  foreach ($LIBS as $name => $lib) foreach ($lib['files'] as $rel => $hash) {
    $dest = "$P/lib/$name/$rel";
    if (is_file($dest)) continue;
    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0700, true);
    $ch = curl_init($lib['base'] . $rel);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => true]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !is_string($data) || hash('sha256', $data) !== $hash) { $ok = false; break 2; }
    file_put_contents("$dest.part", $data);
    rename("$dest.part", $dest);
  }
  flock($lock, LOCK_UN);
  fclose($lock);
  return $ok && libs_ready($P, $LIBS);
}

// ------------------------------------------------------------ setup check
function is_https() {
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}
function company_recipients($CONFIG) {
  return array_values(array_filter(array_map('trim', explode(',', (string)$CONFIG['company_email'])), 'strlen'));
}
function setup_problems($P, $CONFIG, $LIBS) {
  $p = [];
  if (PHP_VERSION_ID < 70400) $p[] = 'نسخه PHP باید 7.4 یا بالاتر باشد.';
  foreach (['curl', 'mbstring', 'openssl', 'gd', 'fileinfo'] as $ext)
    if (!extension_loaded($ext)) $p[] = "افزونه PHP «$ext» روی هاست فعال نیست.";
  if (!is_https()) $p[] = 'سایت باید با HTTPS باز شود (SSL را در پنل هاست فعال کنید).';
  if (!ensure_dirs($P)) $p[] = 'پوشه خصوصی ساخته نشد یا قابل نوشتن نیست: ' . $P;
  elseif (inside_docroot($P)) $p[] = 'پوشه خصوصی داخل مسیر عمومی سایت است. در تنظیمات private_dir مسیری بیرون از public_html بدهید.';
  elseif (!install_libs($P, $LIBS)) $p[] = 'کتابخانه‌های PDF و ایمیل دانلود نشدند (اتصال هاست به GitHub). صفحه را دوباره باز کنید یا راهنما را ببینید.';
  if (!preg_match('/^sk-ant-/', $CONFIG['anthropic_api_key'])) $p[] = 'کلید anthropic_api_key تنظیم نشده است.';
  foreach (['smtp_host', 'smtp_user', 'smtp_pass'] as $k) if ($CONFIG[$k] === '') $p[] = "تنظیم $k خالی است.";
  if (!filter_var($CONFIG['mail_from'], FILTER_VALIDATE_EMAIL)) $p[] = 'تنظیم mail_from یک ایمیل معتبر نیست.';
  $rcpt = company_recipients($CONFIG);
  if (!$rcpt || count($rcpt) !== count(array_filter($rcpt, function ($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); })))
    $p[] = 'تنظیم company_email ایمیل معتبر ندارد (چند ایمیل را با کاما جدا کنید).';
  return $p;
}

// ------------------------------------------------------------ security headers
$NONCE = base64_encode(random_bytes(16));
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(self), geolocation=(), microphone=()');
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$NONCE'; style-src 'nonce-$NONCE'; img-src 'self' data: blob:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');

if (!is_https() && !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) && strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') !== 0) {
  header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
  exit;
}
if (is_https()) header('Strict-Transport-Security: max-age=31536000');

$PROBLEMS = setup_problems($P, $CONFIG, $LIBS);
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0)
  $PROBLEMS = array_values(array_filter($PROBLEMS, function ($x) { return strpos($x, 'HTTPS') === false; }));

if ($PROBLEMS) {
  http_response_code(503);
  if (isset($_GET['a'])) { header('Content-Type: application/json'); echo '{"ok":false,"error":"setup"}'; exit; }
  echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup incomplete</title>'
    . '<style nonce="' . $NONCE . '">body{font-family:system-ui,sans-serif;background:#f5f8fb;color:#13294b;padding:24px;max-width:640px;margin:auto}li{margin:8px 0}</style>'
    . '<h2>This page is not available yet.</h2><p dir="rtl" lang="fa"><b>راه‌اندازی کامل نیست.</b> موارد زیر را در بالای فایل index.php تنظیم کنید:</p><ul dir="rtl" lang="fa">';
  foreach ($PROBLEMS as $x) echo '<li>' . htmlspecialchars($x, ENT_QUOTES, 'UTF-8') . '</li>';
  echo '</ul>';
  exit;
}
if (mt_rand(1, 10) === 1) cleanup($P, $KEEP);

// ---------------------------------------------------------------- session
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.lazy_write', '0');
ini_set('session.gc_maxlifetime', (string)$KEEP);
session_save_path("$P/sessions");
session_name('tm_visa');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['created']) || time() - $_SESSION['created'] > 6 * 3600) {
  $_SESSION = ['created' => time(), 'csrf' => bin2hex(random_bytes(32))];
  session_regenerate_id(true);
}

// ---------------------------------------------------------------- helpers
function out($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
  exit;
}
function fail($err, $code = 400, $extra = []) { out(['ok' => false, 'error' => $err] + $extra, $code); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function rate_ok($key, $max, $window) {
  global $P;
  $f = "$P/rl/" . hash('sha256', $key);
  $fp = fopen($f, 'c+');
  if (!$fp) return false;
  flock($fp, LOCK_EX);
  $list = json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
  $now = time();
  $list = array_values(array_filter($list, function ($t) use ($now, $window) { return $t > $now - $window; }));
  $ok = count($list) < $max;
  if ($ok) $list[] = $now;
  ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($list));
  flock($fp, LOCK_UN); fclose($fp);
  return $ok;
}

function luhn_ok($digits) {
  $sum = 0; $alt = false;
  for ($i = strlen($digits) - 1; $i >= 0; $i--) {
    $n = (int)$digits[$i];
    if ($alt) { $n *= 2; if ($n > 9) $n -= 9; }
    $sum += $n; $alt = !$alt;
  }
  return $sum % 10 === 0;
}
function norm_eid($s) {
  $d = preg_replace('/\D/', '', (string)$s);
  if (strlen($d) !== 15 || substr($d, 0, 3) !== '784' || !luhn_ok($d)) return '';
  return substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7, 7) . '-' . substr($d, 14, 1);
}
function norm_date($s) {
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim((string)$s), $m)) return '';
  if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $m[1] < 2000 || $m[1] > 2100) return '';
  return $m[0];
}
function norm_name($s) {
  $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
  if (mb_strlen($s) < 3 || mb_strlen($s) > 100) return '';
  if (!preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'\-]*$/u", $s)) return '';
  return $s;
}

function public_state() {
  $s = $_SESSION;
  $scan = $s['scan'] ?? null;
  return [
    'scan'    => $scan ? ['ok' => !empty($scan['ok']), 'needs_back' => !empty($scan['needs_back']), 'fields' => $scan['fields']] : null,
    'details' => $s['details'] ?? null,
    'terms'   => $s['terms'] ?? [],
    'sub'     => isset($s['sub']) ? ['id' => $s['sub']['id'], 'email' => $s['sub']['email'], 'file_ok' => is_file($s['sub']['file'])] : null,
  ];
}

// ------------------------------------------------------------- OCR (Claude)
function claude_read_card($imgBytes, $mime, $side) {
  global $CONFIG;
  $schema = [
    'type' => 'object', 'additionalProperties' => false,
    'required' => ['is_emirates_id', 'side', 'readable', 'full_name', 'id_number', 'expiry_date'],
    'properties' => [
      'is_emirates_id' => ['type' => 'boolean'],
      'side'           => ['type' => 'string', 'enum' => ['front', 'back', 'unknown']],
      'readable'       => ['type' => 'boolean'],
      'full_name'      => ['type' => 'string'],
      'id_number'      => ['type' => 'string'],
      'expiry_date'    => ['type' => 'string'],
    ],
  ];
  $system = "You read UAE Emirates ID cards to pre-fill a form. Work only from what is visibly printed in the image or PDF.\n"
    . "- If a PDF shows both sides of the card, read the fields from both sides.\n"
    . "- is_emirates_id: true only if the image or PDF shows a UAE Emirates ID card (front or back).\n"
    . "- side: which side is shown. The back usually has a machine-readable zone (MRZ).\n"
    . "- readable: false if the card is blurred, cut off, covered by glare, or too small to read.\n"
    . "- full_name: the holder's name in English exactly as printed (or from the MRZ on the back, with '<' replaced by spaces).\n"
    . "- id_number: the 15-digit ID number as 784-YYYY-NNNNNNN-N.\n"
    . "- expiry_date: the card expiry date as YYYY-MM-DD.\n"
    . "Never guess or complete partially visible characters: if any part of a field is not clearly legible or not present on this side, return an empty string for that field.";
  $body = [
    'model' => $CONFIG['model'],
    'max_tokens' => 4000,
    'fallbacks' => 'default',
    'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
    'system' => $system,
    'messages' => [[
      'role' => 'user',
      'content' => [
        [
          'type' => $mime === 'application/pdf' ? 'document' : 'image',
          'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($imgBytes)],
        ],
        ['type' => 'text', 'text' => "The customer says this is the $side of their Emirates ID. Extract the fields."],
      ],
    ]],
  ];
  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
      'content-type: application/json',
      'x-api-key: ' . $CONFIG['anthropic_api_key'],
      'anthropic-version: 2023-06-01',
      'anthropic-beta: server-side-fallback-2026-07-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
  ]);
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $body = null;
  if ($code !== 200 || !is_string($res)) { error_log("tm_visa: OCR HTTP $code"); return null; }
  $j = json_decode($res, true);
  if (($j['stop_reason'] ?? '') !== 'end_turn') { error_log('tm_visa: OCR stop ' . ($j['stop_reason'] ?? '?')); return null; }
  foreach ($j['content'] ?? [] as $b) if (($b['type'] ?? '') === 'text') {
    $r = json_decode($b['text'], true);
    if (is_array($r) && isset($r['is_emirates_id'])) return $r;
  }
  return null;
}

// ---------------------------------------------------------------- PDF
function build_pdf($snap, $sigBytes, $dest) {
  global $P, $COMPANY, $TERMS, $TERMS_NOTE, $DECLARATION;
  if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    define('K_PATH_MAIN', "$P/lib/tcpdf/");
    define('K_PATH_URL', '');
    define('K_PATH_FONTS', "$P/lib/tcpdf/fonts/");
    define('K_PATH_CACHE', "$P/tmp/");
    define('K_PATH_IMAGES', '');
    define('K_BLANK_IMAGE', '_blank.png');
    define('K_TCPDF_CALLS_IN_HTML', false);
    define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
    define('K_ALLOWED_TCPDF_TAGS', '');
  }
  require_once "$P/lib/tcpdf/tcpdf.php";
  $c = $snap['customer'];
  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
  $pdf->SetCreator($COMPANY['brand']);
  $pdf->SetAuthor($COMPANY['legal']);
  $pdf->SetTitle('Visa Service Terms Agreement ' . $snap['id']);
  $pdf->setPrintHeader(false);
  $pdf->setFooterFont(['dejavusans', '', 7]);
  $pdf->SetMargins(16, 14, 16);
  $pdf->SetAutoPageBreak(true, 16);
  $pdf->AddPage();
  $pdf->SetFont('dejavusans', '', 9.5);

  $html = '<table cellpadding="2"><tr><td width="60%"><b style="font-size:13pt;color:#13294b">' . h($COMPANY['legal']) . '</b><br>'
    . 'Trading as ' . h($COMPANY['brand']) . '<br>Dubai Licence No. ' . h($COMPANY['licence']) . '</td>'
    . '<td width="40%" align="right"><span style="font-size:8pt;color:#555">Tracking No.</span><br><b style="font-size:12pt">' . h($snap['id']) . '</b><br>'
    . '<span style="font-size:8pt">' . h($snap['time_label']) . '</span></td></tr></table>'
    . '<hr><h2 style="color:#0b7d73;font-size:14pt">Visa Service Terms Agreement</h2>';
  $pdf->writeHTML($html, true, false, true, false, '');
  $pdf->setRTL(true);
  $pdf->SetFont('aealarabiya', '', 12);
  $pdf->writeHTML('<span style="font-size:13pt"><b>' . h($COMPANY['arabic']) . '</b></span><br>اتفاقية شروط خدمات التأشيرة<br>رخصة دبي رقم ' . h($COMPANY['licence']), true, false, true, false, '');
  $pdf->setRTL(false);
  $pdf->SetFont('dejavusans', '', 9.5);

  $rows = [
    ['Full name', 'الاسم الكامل', $c['name'], 'name'],
    ['Emirates ID number', 'رقم الهوية الإماراتية', $c['eid'], 'eid'],
    ['Emirates ID expiry', 'تاريخ انتهاء الهوية', $c['expiry'], 'expiry'],
    ['Mobile', 'رقم الهاتف المتحرك', $c['phone'], null],
    ['Email', 'البريد الإلكتروني', $c['email'], null],
  ];
  $t = '<h3 style="font-size:11pt">Customer details</h3><table border="0.3" cellpadding="4">';
  foreach ($rows as $r) {
    $note = '';
    if ($r[3] && $snap['scanned'][$r[3]] !== $r[2])
      $note = '<br><span style="font-size:7.5pt;color:#666">Read from card: ' . h($snap['scanned'][$r[3]] !== '' ? $snap['scanned'][$r[3]] : '(not read)') . ' — corrected by customer</span>';
    $t .= '<tr><td width="34%" style="background-color:#eef4f8">' . h($r[0]) . '<br><span style="font-size:8.5pt">' . h($r[1]) . '</span></td><td width="66%"><b>' . h($r[2]) . '</b>' . $note . '</td></tr>';
  }
  $t .= '</table><p style="font-size:7.5pt;color:#555">Name, ID number and expiry were read from a photo of the customer\'s Emirates ID and reviewed by the customer. This is not an official or government identity verification. Mobile and email were entered by the customer and were not verified. The card image is not stored or included in this document.</p>';
  $pdf->writeHTML($t, true, false, true, false, '');

  $en = '<h3 style="font-size:11pt">Terms accepted</h3>';
  $i = 0;
  foreach ($TERMS as $k => $term) {
    $i++;
    $en .= '<p><b>' . $i . '. ' . h($term['en_t']) . '</b><br>' . h($term['en']) . '<br><span style="font-size:7.5pt;color:#0b7d73">✓ Accepted separately — ' . h($snap['terms_at'][$k]) . '</span></p>';
  }
  $en .= '<p style="font-size:9pt"><i>' . h($TERMS_NOTE['en']) . '</i></p>';
  $pdf->writeHTML($en, true, false, true, false, '');

  $pdf->setRTL(true);
  $pdf->SetFont('aealarabiya', '', 12);
  $ar = '<h3>الشروط المقبولة</h3>';
  $i = 0;
  foreach ($TERMS as $k => $term) { $i++; $ar .= '<p><b>' . $i . '. ' . h($term['ar_t']) . '</b><br>' . h($term['ar']) . '</p>'; }
  $ar .= '<p>' . h($TERMS_NOTE['ar']) . '</p>';
  $pdf->writeHTML($ar, true, false, true, false, '');
  $pdf->setRTL(false);
  $pdf->SetFont('dejavusans', '', 9.5);

  if ($pdf->GetY() > 215) $pdf->AddPage();
  $pdf->writeHTML('<h3 style="font-size:11pt">Declaration and signature</h3><p>' . h($DECLARATION['en']) . '</p>', true, false, true, false, '');
  $pdf->setRTL(true);
  $pdf->SetFont('aealarabiya', '', 12);
  $pdf->writeHTML('<h3>الإقرار والتوقيع</h3><p>' . h($DECLARATION['ar']) . '</p>', true, false, true, false, '');
  $pdf->setRTL(false);
  $pdf->SetFont('dejavusans', '', 9.5);
  $y = $pdf->GetY() + 2;
  $pdf->Rect(16, $y, 80, 34);
  $pdf->Image('@' . $sigBytes, 17, $y + 1, 78, 32, 'JPG', '', '', false, 300, '', false, false, 0, 'CM');
  $pdf->SetXY(100, $y);
  $pdf->writeHTMLCell(94, 34, 100, $y, '<b>' . h($c['name']) . '</b><br>Signed electronically on the customer\'s device.<br>Submitted: ' . h($snap['time_label'])
    . '<br><span style="font-size:7.5pt;color:#555">IP address: ' . h($snap['ip']) . '<br>Terms version ' . h($snap['terms_version']) . '<br>SHA-256 of terms: ' . h(substr($snap['terms_hash'], 0, 32)) . '…</span>', 0, 1);
  $pdf->Output($dest, 'F');
  return is_file($dest);
}

// ---------------------------------------------------------------- email
function send_company_email(&$sub) {
  global $P, $CONFIG, $COMPANY;
  require_once "$P/lib/phpmailer/src/Exception.php";
  require_once "$P/lib/phpmailer/src/PHPMailer.php";
  require_once "$P/lib/phpmailer/src/SMTP.php";
  $m = new PHPMailer\PHPMailer\PHPMailer(true);
  try {
    $m->isSMTP();
    $m->Host = $CONFIG['smtp_host'];
    $m->Port = (int)$CONFIG['smtp_port'];
    $m->SMTPAuth = true;
    $m->Username = $CONFIG['smtp_user'];
    $m->Password = $CONFIG['smtp_pass'];
    $m->SMTPSecure = $CONFIG['smtp_secure'] === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $m->Timeout = 20;
    $m->CharSet = 'UTF-8';
    $m->setFrom($CONFIG['mail_from'], $COMPANY['brand'] . ' Agreements');
    foreach (company_recipients($CONFIG) as $to) $m->addAddress($to);
    $c = $sub['snap']['customer'];
    $m->Subject = 'Signed visa service terms ' . $sub['id'] . ' — ' . $c['name'];
    $m->Body = "A customer signed the visa service terms.\n\nTracking No.: {$sub['id']}\nSubmitted: {$sub['snap']['time_label']}\n"
      . "Name: {$c['name']}\nEmirates ID: {$c['eid']}\nMobile: {$c['phone']}\nEmail: {$c['email']}\n\n"
      . "The signed agreement is attached. Keep this email: the file is deleted from the website automatically after a short time.";
    $m->addAttachment($sub['file'], 'TravelMarket-Agreement-' . $sub['id'] . '.pdf', 'base64', 'application/pdf');
    $m->send();
    return true;
  } catch (Throwable $e) {
    error_log('tm_visa: email failed (' . get_class($e) . ')');
    return false;
  }
}
function try_email(&$sub) {
  if ($sub['email'] === 'accepted' || !is_file($sub['file'])) return;
  $lock = fopen($sub['file'] . '.lock', 'c');
  if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
  $sub['email_attempts']++;
  $sub['email'] = send_company_email($sub) ? 'accepted' : 'failed';
  flock($lock, LOCK_UN); fclose($lock);
}

// ================================================================ actions
$a = $_GET['a'] ?? '';
if ($a !== '') {
  if ($a === 'pdf') {
    if (!hash_equals($_SESSION['csrf'], (string)($_GET['t'] ?? '')) || empty($_SESSION['sub'])) { http_response_code(403); exit('Not available'); }
    $f = $_SESSION['sub']['file'];
    if (!is_file($f)) { http_response_code(410); header('Content-Type: text/plain; charset=utf-8'); exit('This file has been deleted from the website.'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="TravelMarket-Agreement-' . $_SESSION['sub']['id'] . '.pdf"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) fail('csrf', 403);
  $in = $_POST;
  $locked = !empty($_SESSION['sub']);

  if ($a === 'state') out(['ok' => true, 'state' => public_state()]);

  if ($a === 'scan') {
    if ($locked) fail('locked');
    $side = ($in['side'] ?? '') === 'back' ? 'back' : 'front';
    $f = $_FILES['image'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) fail('upload');
    $tmp = $f['tmp_name'];
    $size = filesize($tmp);
    $info = @getimagesize($tmp);
    $fmime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    $isPdf = $fmime === 'application/pdf' && file_get_contents($tmp, false, null, 0, 5) === '%PDF-';
    if ($size < 3000 || $size > 8 * 1024 * 1024) { @unlink($tmp); fail('bad_image'); }
    if (!$isPdf) {
      if (!$info || !isset($types[$info[2]]) || $types[$info[2]] !== $fmime) { @unlink($tmp); fail('bad_image'); }
      if (min($info[0], $info[1]) < 400 || max($info[0], $info[1]) > 8000) { @unlink($tmp); fail('small_image'); }
    }
    if (!rate_ok('ocr-s-' . session_id(), 12, 3600) || !rate_ok('ocr-ip-' . client_ip(), 30, 3600)) { @unlink($tmp); fail('rate', 429); }
    $bytes = file_get_contents($tmp);
    @unlink($tmp); // raw card image is never kept on the server
    $r = claude_read_card($bytes, $fmime, $side);
    $bytes = null;
    if ($r === null) fail('service', 502);
    if (!$r['is_emirates_id']) fail('not_id');
    if (!$r['readable']) fail('unreadable');
    $got = ['name' => norm_name($r['full_name']), 'eid' => norm_eid($r['id_number']), 'expiry' => norm_date($r['expiry_date'])];
    $scan = $_SESSION['scan'] ?? ['fields' => ['name' => '', 'eid' => '', 'expiry' => '']];
    if ($side === 'front') $scan = ['fields' => ['name' => '', 'eid' => '', 'expiry' => '']];
    $fields = $scan['fields'];
    if ($side === 'back' && $fields['eid'] !== '' && $got['eid'] !== '' && $got['eid'] !== $fields['eid']) fail('mismatch');
    if ($side === 'front' && $got['name'] === '' && $got['eid'] === '') fail('unreadable');
    foreach ($got as $k => $v) if ($fields[$k] === '' && $v !== '') $fields[$k] = $v;
    $ok = $fields['name'] !== '' && $fields['eid'] !== '' && $fields['expiry'] !== '';
    $_SESSION['scan'] = ['fields' => $fields, 'ok' => $ok, 'needs_back' => !$ok, 'at' => time()];
    // a new scan replaces card details; contact details are kept
    $keep = $_SESSION['details'] ?? [];
    $_SESSION['details'] = ['phone' => $keep['phone'] ?? '', 'email' => $keep['email'] ?? ''];
    out(['ok' => true, 'state' => public_state()]);
  }

  if ($a === 'details') {
    if ($locked) fail('locked');
    if (empty($_SESSION['scan']['ok'])) fail('need_scan', 409);
    $d = [
      'name'   => norm_name($in['name'] ?? ''),
      'eid'    => norm_eid($in['eid'] ?? ''),
      'expiry' => norm_date($in['expiry'] ?? ''),
      'phone'  => preg_match('/^\+?[0-9][0-9 \-()]{6,19}$/', trim($in['phone'] ?? '')) ? trim($in['phone']) : '',
      'email'  => filter_var(trim($in['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
    ];
    $bad = array_keys(array_filter($d, function ($v) { return $v === ''; }));
    if ($bad) fail('invalid', 422, ['fields' => $bad]);
    if (mb_strlen($d['email']) > 120) fail('invalid', 422, ['fields' => ['email']]);
    $d['confirmed'] = time();
    $_SESSION['details'] = $d;
    out(['ok' => true, 'state' => public_state()]);
  }

  if ($a === 'terms') {
    if ($locked) fail('locked');
    if (empty($_SESSION['details']['confirmed'])) fail('need_details', 409);
    $k = $in['key'] ?? '';
    if (!isset($TERMS[$k])) fail('invalid');
    if (($in['accepted'] ?? '') === '1') $_SESSION['terms'][$k] = time();
    else unset($_SESSION['terms'][$k]);
    out(['ok' => true, 'state' => public_state()]);
  }

  if ($a === 'submit') {
    if ($locked) out(['ok' => true, 'state' => public_state()]); // repeated click / refresh: no new agreement
    if (empty($_SESSION['scan']['ok'])) fail('need_scan', 409);
    if (empty($_SESSION['details']['confirmed'])) fail('need_details', 409);
    foreach ($TERMS as $k => $_) if (empty($_SESSION['terms'][$k])) fail('need_terms', 409);
    if (($in['final'] ?? '') !== '1') fail('need_final', 422);
    if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $in['signature'] ?? '', $m)) fail('need_signature', 422);
    $sig = base64_decode($m[1], true);
    if (!$sig || strlen($sig) > 600000 || !($gi = @getimagesizefromstring($sig)) || $gi[2] !== IMAGETYPE_JPEG) fail('need_signature', 422);
    $im = @imagecreatefromstring($sig);
    if (!$im) fail('need_signature', 422);
    $dark = 0; $w = imagesx($im); $hh = imagesy($im);
    for ($x = 0; $x < $w; $x += 2) for ($y = 0; $y < $hh; $y += 2) { $rgb = imagecolorat($im, $x, $y); if ((($rgb >> 16) & 255) + (($rgb >> 8) & 255) + ($rgb & 255) < 300) $dark++; }
    imagedestroy($im);
    if ($dark < 60) fail('need_signature', 422);
    if (!rate_ok('sub-ip-' . client_ip(), 20, 3600)) fail('rate', 429);

    $now = new DateTime('now', new DateTimeZone('Asia/Dubai'));
    $id = 'TM-' . $now->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $fmt = function ($ts) { $d = new DateTime('@' . $ts); $d->setTimezone(new DateTimeZone('Asia/Dubai')); return $d->format('d M Y, H:i:s') . ' (Dubai time)'; };
    $termsAt = [];
    foreach ($TERMS as $k => $_) $termsAt[$k] = $fmt($_SESSION['terms'][$k]);
    $det = $_SESSION['details'];
    $snap = [
      'id' => $id,
      'time_label' => $now->format('d M Y, H:i:s') . ' (Dubai time, GST)',
      'customer' => ['name' => $det['name'], 'eid' => $det['eid'], 'expiry' => $det['expiry'], 'phone' => $det['phone'], 'email' => $det['email']],
      'scanned' => $_SESSION['scan']['fields'],
      'terms_at' => $termsAt,
      'terms_version' => TERMS_VERSION,
      'terms_hash' => hash('sha256', json_encode([$TERMS, $TERMS_NOTE, $DECLARATION], JSON_UNESCAPED_UNICODE)),
      'ip' => client_ip(),
    ];
    $file = "$P/files/" . bin2hex(random_bytes(16)) . '.pdf';
    try { $okPdf = build_pdf($snap, $sig, $file); } catch (Throwable $e) { error_log('tm_visa: pdf failed ' . $e->getMessage()); $okPdf = false; }
    if (!$okPdf) fail('pdf', 500);
    @chmod($file, 0600);
    $_SESSION['sub'] = ['id' => $id, 'file' => $file, 'snap' => $snap, 'email' => 'pending', 'email_attempts' => 0];
    session_write_close(); session_start(); // persist before the slow SMTP step
    try_email($_SESSION['sub']);
    out(['ok' => true, 'state' => public_state()]);
  }

  if ($a === 'email') {
    if (!$locked) fail('invalid');
    $sub = &$_SESSION['sub'];
    if ($sub['email'] !== 'accepted') {
      if ($sub['email_attempts'] >= 6 || !rate_ok('mail-ip-' . client_ip(), 20, 3600)) fail('rate', 429, ['state' => public_state()]);
      try_email($sub);
    }
    out(['ok' => true, 'state' => public_state()]);
  }
  fail('unknown', 404);
}

// ================================================================ page
$BOOT = [
  'csrf' => $_SESSION['csrf'],
  'company' => $COMPANY,
  'terms' => $TERMS,
  'note' => $TERMS_NOTE,
  'decl' => $DECLARATION,
  'keep' => (int)round($KEEP / 60),
  'state' => public_state(),
];
?><!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Travel Market — Visa Service Terms</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%229%22%20fill%3D%22%235046e5%22/%3E%3Cpath%20d%3D%22M9.5%2016.5l4.5%204.5%208.5-9.5%22%20fill%3D%22none%22%20stroke%3D%22%23fff%22%20stroke-width%3D%223.4%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22/%3E%3C/svg%3E">
<meta name="theme-color" content="#f6f3ff">
<style nonce="<?= $NONCE ?>">
:root{--ink:#1e1b3a;--muted:#6b6880;--teal:#6d5dfc;--teal-d:#5b4bf0;--line:#e7e4f5;--err:#c0262d;--ok:#067647;--soft:#f5f3ff;--grad:linear-gradient(135deg,#6d5dfc 0%,#8b5cf6 55%,#c56cf0 100%)}
*{box-sizing:border-box}
html{background:#f7f3ff}
body{margin:0;min-height:100vh;color:var(--ink);font:17px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Tahoma,"Geeza Pro",sans-serif;-webkit-text-size-adjust:100%;overflow-x:hidden;
 background:linear-gradient(160deg,#f3ecff 0%,#fff4f9 45%,#eef2ff 100%);background-attachment:fixed}
.blob{position:fixed;border-radius:50%;filter:blur(70px);opacity:.75;z-index:0;pointer-events:none;animation:float 22s ease-in-out infinite alternate}
.b1{width:420px;height:420px;background:#c4a1ff;top:-120px;left:-140px}
.b2{width:380px;height:380px;background:#ffb8d6;top:20%;right:-160px;animation-delay:-6s}
.b3{width:360px;height:360px;background:#a9c1ff;bottom:-140px;left:20%;animation-delay:-12s}
@keyframes float{0%{transform:translate(0,0) scale(1)}50%{transform:translate(40px,30px) scale(1.08)}100%{transform:translate(-30px,50px) scale(.95)}}
@media (prefers-reduced-motion:reduce){.blob{animation:none}}
.wrap{position:relative;z-index:1;max-width:640px;margin:0 auto;padding:22px 16px calc(36px + env(safe-area-inset-bottom))}
header{position:relative;text-align:center;padding:16px 0 24px}
.logo{display:inline-flex;align-items:center;gap:12px;font-size:26px;font-weight:800;letter-spacing:-.5px;color:var(--ink)}
.logo svg{width:40px;height:40px;flex:none}
.brand small{display:block;color:var(--muted);font-size:12.5px;margin-top:6px;letter-spacing:.3px}
.lang{position:absolute;top:0;inset-inline-end:0;background:rgba(255,255,255,.7);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.9);border-radius:999px;padding:7px 15px;color:var(--ink);font:inherit;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(109,93,252,.12)}
.card{position:relative;background:rgba(255,255,255,.86);-webkit-backdrop-filter:blur(20px);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.95);border-radius:30px;padding:34px 24px 30px;min-height:62vh;box-shadow:0 30px 80px rgba(109,93,252,.18),0 4px 14px rgba(30,27,58,.05)}
@media (min-width:700px){.wrap{padding-top:40px;max-width:760px}.card{padding:48px 56px 44px;min-height:560px}body{font-size:17.5px}}
.ico{width:76px;height:76px;margin:6px auto 18px;border-radius:24px;display:flex;align-items:center;justify-content:center;font-size:38px;background:linear-gradient(135deg,#efeaff,#ffe9f4);box-shadow:inset 0 0 0 1px rgba(255,255,255,.9),0 10px 24px rgba(139,92,246,.18)}
.bar{height:8px;background:#eeebfa;border-radius:999px;overflow:hidden;margin-bottom:10px}.bar i{display:block;height:100%;background:var(--grad);border-radius:999px;width:0;transition:width .4s ease}
.stepno{font-size:13px;color:var(--muted);text-align:center;margin-bottom:8px;font-weight:600}
h1,h2{text-align:center;letter-spacing:-.6px;line-height:1.25}h1{font-size:30px;margin:0 0 12px}h2{font-size:26px;margin:0 0 14px}p{margin:0 0 14px}
@media (min-width:700px){h1{font-size:36px}h2{font-size:30px}}
.muted{color:var(--muted);font-size:15px}
.btn{display:block;width:100%;border:0;border-radius:999px;padding:17px 22px;font:inherit;font-size:17px;font-weight:700;background:var(--grad);color:#fff;margin-top:14px;cursor:pointer;box-shadow:0 12px 28px rgba(109,93,252,.35);transition:transform .15s ease,box-shadow .15s ease}
.btn:hover{transform:translateY(-1px);box-shadow:0 16px 34px rgba(109,93,252,.42)}.btn:active{transform:translateY(1px)}
.btn[disabled]{opacity:.38;box-shadow:none;transform:none}
.btn.alt{background:#fff;color:var(--teal);border:1.5px solid var(--line);box-shadow:0 6px 16px rgba(30,27,58,.05)}
.nav{display:flex;gap:12px;margin-top:20px}.nav .btn{margin-top:0}
label.f{display:block;font-size:14px;font-weight:700;color:var(--muted);margin:16px 6px 7px}
input[type=text],input[type=email],input[type=tel],input[type=date]{width:100%;padding:16px 22px;border:1.5px solid var(--line);border-radius:999px;font:inherit;font-size:16px;color:var(--ink);background:#fff;outline:none;-webkit-appearance:none;appearance:none;transition:border-color .15s,box-shadow .15s}
input:focus{border-color:#8b5cf6;box-shadow:0 0 0 4px rgba(139,92,246,.15)}
.hint{font-size:12.5px;color:var(--muted);margin:5px 8px 0}
.chk{display:flex;gap:12px;align-items:flex-start;padding:16px 18px;border:1.5px solid var(--line);border-radius:20px;margin-top:16px;font-size:16px;background:#fff;cursor:pointer}
.chk:has(input:checked){border-color:#8b5cf6;background:#faf8ff}
.chk input{width:24px;height:24px;flex:none;margin:1px 0 0;accent-color:#7c5cf6}
.msg{padding:14px 16px;border-radius:18px;font-size:15px;margin-top:14px}.msg.err{background:#fff1f2;color:var(--err)}.msg.ok{background:#ecfdf3;color:var(--ok)}.msg.info{background:var(--soft)}
.hide{display:none!important}
label.btn,label.link{position:relative;text-align:center;display:block}.vh{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}
dl{margin:0;background:var(--soft);border-radius:20px;padding:16px 20px}dt{font-size:12.5px;color:var(--muted)}dd{margin:0 0 10px;font-weight:700;word-break:break-word}
.agree{max-height:320px;overflow:auto;border:1.5px solid var(--line);border-radius:20px;padding:16px 18px;font-size:15px;background:#fff}
.agree h3{font-size:16px;margin:12px 0 4px}
canvas{display:block;width:100%;height:200px;border:2px dashed #cbbcfb;border-radius:20px;background:#fff;touch-action:none}
.link{background:none;border:0;color:var(--teal);font:inherit;font-size:15px;font-weight:600;text-decoration:none;padding:10px 0;cursor:pointer}
.mt{margin-top:14px}.bad{border-color:var(--err)!important}.th{margin:0 0 8px;font-size:20px;text-align:center}.sh{font-size:16px}a.btn{text-align:center;text-decoration:none}
.ctr{text-align:center}
.big{font-size:28px;font-weight:800;letter-spacing:1px;text-align:center;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<div class="wrap">
  <header>
    <button class="lang" id="langBtn" type="button">العربية</button>
    <div class="brand"><span class="logo"><svg viewBox="0 0 32 32" aria-hidden="true"><defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7c6cf5"/><stop offset="1" stop-color="#5046e5"/></linearGradient></defs><rect x="3" y="3" width="26" height="26" rx="8" fill="none" stroke="url(#lg)" stroke-width="3"/><path d="M10.5 16.5l4 4 7-8" fill="none" stroke="url(#lg)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>Travel Market</span><small id="legal">SOUQ AL SAFAR TOURISM L.L.C</small></div>
  </header>
  <div class="card">
    <div class="bar"><i id="barFill"></i></div>
    <div class="stepno" id="stepNo"></div>
    <div class="ico" id="ico" aria-hidden="true"></div>
    <main id="view"></main>
  </div>
</div>
<script nonce="<?= $NONCE ?>">
const BOOT = <?= json_encode($BOOT, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const T = {
en:{lang:'العربية',step:'Step {n} of 6',start:'Start',next:'Next',back:'Back',
 w_title:'Visa service terms',w_intro:'Please review and sign our general visa service terms. It takes about 3 minutes.',
 w_priv:'Before you start: you will take a photo of your Emirates ID (or upload a PDF of it). The file is sent securely to our text-reading service (Anthropic Claude) only to read your name, ID number and expiry date for this agreement. We do not keep the photo or file — it is deleted from our server right after it is read and is not placed in the agreement. Your confirmed details and signed agreement stay on our server for up to {keep} minutes so you can download them, then they are deleted automatically. A copy is sent to Travel Market by email.',
 w_note:'Reading the card only fills in your details. It is not an official or government identity check.',
 s_title:'Scan your Emirates ID',s_front:'Take a clear photo of the FRONT of your card. Place it on a flat surface, fill the frame, and avoid glare.',
 s_back:'We could not read everything from the front. Please take a photo of the BACK of your card.',
 s_cam:'Take photo',s_lib:'Choose photo or PDF',s_wait:'Reading your card…',s_redo:'Scan the front again',
 s_done:'Card read successfully. Please check your details below.',
 e_not_id:'This does not look like an Emirates ID. Please take a new photo of your card.',
 e_unreadable:'The card could not be read clearly. Please take a new, sharper photo without glare.',
 e_bad_image:'Please use a photo or a PDF of your card (max 8 MB).',e_small_image:'The photo is too small. Please take a closer photo.',
 e_mismatch:'The back does not match the front of the card. Please photograph the back of the same card.',
 e_service:'Our card-reading service is not responding right now. Please try again.',
 e_rate:'Too many attempts. Please wait a while and try again.',e_upload:'The photo could not be uploaded. Please try again.',
 e_net:'Connection problem. Please check your internet and try again.',e_generic:'Something went wrong. Please try again.',
 e_pdf:'The agreement could not be created. Please try again.',e_setup:'This page is temporarily unavailable.',
 d_title:'Confirm your details',d_intro:'Check the details read from your card and correct any mistakes. Then add your mobile number and email.',
 d_name:'Full name (as on card)',d_eid:'Emirates ID number',d_exp:'Card expiry date',d_phone:'Mobile number',d_email:'Email',
 d_from:'Read from card: {v}',d_btn:'I have reviewed and confirmed my details',d_bad:'Please check the highlighted fields.',
 t_title:'Service terms',t_part:'Term {n} of 4',t_accept:'I have read and accept this term.',
 r_title:'Review and sign',r_details:'Your details',r_edit:'Edit details',r_agree:'Full agreement',
 r_sign:'Sign below with your finger',r_clear:'Clear signature',r_final:'',r_btn:'Confirm and create agreement',r_need_sig:'Please sign in the box.',
 r_wait:'Creating your agreement…',
 f_title:'Your agreement is ready',f_track:'Tracking number',f_dl:'Download agreement (PDF)',
 f_mail_ok:'A copy was handed to our mail server for delivery to Travel Market.',
 f_mail_fail:'We could not send the copy to Travel Market yet. Your PDF is still available below.',f_retry:'Try sending again',
 f_mail_wait:'Sending a copy to Travel Market…',f_keep:'Please download and keep your copy. It will be deleted from this website within {keep} minutes.',
 f_gone:'The file has been deleted from this website. Please contact Travel Market with your tracking number.',
 contact_note:'Your mobile and email are recorded as you typed them; they are not verified.'},
ar:{lang:'English',step:'الخطوة {n} من 6',start:'ابدأ',next:'التالي',back:'السابق',
 w_title:'شروط خدمات التأشيرة',w_intro:'يرجى مراجعة الشروط العامة لخدمات التأشيرة والتوقيع عليها. يستغرق ذلك نحو 3 دقائق.',
 w_priv:'قبل البدء: ستلتقط صورة لبطاقة الهوية الإماراتية (أو ترفع ملف PDF لها). يُرسل الملف بشكل آمن إلى خدمة قراءة النصوص لدينا (Anthropic Claude) فقط لقراءة الاسم ورقم الهوية وتاريخ الانتهاء لهذه الاتفاقية. لا نحتفظ بالصورة أو الملف، إذ يُحذف من خادمنا فور قراءته ولا يُدرج في الاتفاقية. تبقى بياناتك المؤكدة والاتفاقية الموقعة على خادمنا لمدة أقصاها {keep} دقيقة لتتمكن من تنزيلها، ثم تُحذف تلقائياً. وتُرسل نسخة إلى Travel Market عبر البريد الإلكتروني.',
 w_note:'قراءة البطاقة تُستخدم فقط لتعبئة بياناتك، وليست تحققاً رسمياً أو حكومياً من الهوية.',
 s_title:'مسح الهوية الإماراتية',s_front:'التقط صورة واضحة لـ الوجه الأمامي من البطاقة. ضعها على سطح مستوٍ، واجعلها تملأ الإطار، وتجنّب الانعكاس.',
 s_back:'لم نتمكن من قراءة جميع البيانات من الوجه الأمامي. يرجى تصوير الوجه الخلفي من البطاقة.',
 s_cam:'التقاط صورة',s_lib:'اختيار صورة أو ملف PDF',s_wait:'جارٍ قراءة البطاقة…',s_redo:'مسح الوجه الأمامي من جديد',
 s_done:'تمت قراءة البطاقة بنجاح. يرجى مراجعة بياناتك أدناه.',
 e_not_id:'لا تبدو هذه الصورة لبطاقة هوية إماراتية. يرجى التقاط صورة جديدة للبطاقة.',
 e_unreadable:'تعذرت قراءة البطاقة بوضوح. يرجى التقاط صورة أوضح بدون انعكاس.',
 e_bad_image:'يرجى استخدام صورة أو ملف PDF للبطاقة (بحد أقصى 8 ميغابايت).',e_small_image:'الصورة صغيرة جداً. يرجى التقاط صورة أقرب.',
 e_mismatch:'الوجه الخلفي لا يطابق الوجه الأمامي. يرجى تصوير الوجه الخلفي للبطاقة نفسها.',
 e_service:'خدمة قراءة البطاقة لا تستجيب حالياً. يرجى المحاولة مرة أخرى.',
 e_rate:'محاولات كثيرة. يرجى الانتظار قليلاً ثم المحاولة مجدداً.',e_upload:'تعذر رفع الصورة. يرجى المحاولة مرة أخرى.',
 e_net:'مشكلة في الاتصال. يرجى التحقق من الإنترنت والمحاولة مجدداً.',e_generic:'حدث خطأ. يرجى المحاولة مرة أخرى.',
 e_pdf:'تعذر إنشاء الاتفاقية. يرجى المحاولة مرة أخرى.',e_setup:'هذه الصفحة غير متاحة مؤقتاً.',
 d_title:'تأكيد البيانات',d_intro:'راجع البيانات المقروءة من البطاقة وصحّح أي خطأ، ثم أضف رقم هاتفك المتحرك وبريدك الإلكتروني.',
 d_name:'الاسم الكامل (كما في البطاقة)',d_eid:'رقم الهوية الإماراتية',d_exp:'تاريخ انتهاء البطاقة',d_phone:'رقم الهاتف المتحرك',d_email:'البريد الإلكتروني',
 d_from:'المقروء من البطاقة: {v}',d_btn:'راجعت بياناتي وأكدتها',d_bad:'يرجى التحقق من الحقول المحددة.',
 t_title:'شروط الخدمة',t_part:'البند {n} من 4',t_accept:'قرأت هذا البند وأوافق عليه.',
 r_title:'المراجعة والتوقيع',r_details:'بياناتك',r_edit:'تعديل البيانات',r_agree:'الاتفاقية كاملة',
 r_sign:'وقّع في المربع أدناه بإصبعك',r_clear:'مسح التوقيع',r_final:'',r_btn:'تأكيد وإنشاء الاتفاقية',r_need_sig:'يرجى التوقيع داخل المربع.',
 r_wait:'جارٍ إنشاء الاتفاقية…',
 f_title:'اتفاقيتك جاهزة',f_track:'رقم المتابعة',f_dl:'تنزيل الاتفاقية (PDF)',
 f_mail_ok:'تم تسليم نسخة إلى خادم البريد لدينا لإرسالها إلى Travel Market.',
 f_mail_fail:'لم نتمكن بعد من إرسال النسخة إلى Travel Market. ملف PDF لا يزال متاحاً أدناه.',f_retry:'إعادة محاولة الإرسال',
 f_mail_wait:'جارٍ إرسال نسخة إلى Travel Market…',f_keep:'يرجى تنزيل نسختك والاحتفاظ بها. ستُحذف من هذا الموقع خلال {keep} دقيقة.',
 f_gone:'تم حذف الملف من هذا الموقع. يرجى التواصل مع Travel Market مع ذكر رقم المتابعة.',
 contact_note:'يُسجَّل رقم الهاتف والبريد الإلكتروني كما أدخلتهما، دون التحقق منهما.'}
};
let lang = 'en';
try { if (localStorage.getItem('tm_lang') === 'ar') lang = 'ar'; } catch (e) {}
let S = BOOT.state, step = 1, tstep = 0, busy = false, msg = null;
const form = {name:'',eid:'',expiry:'',phone:'',email:''};
let sigData = null, sigInk = 0, finalChk = false, badFields = [];
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const t = (k, v = {}) => (T[lang][k] || T.en[k] || k).replace(/\{(\w+)\}/g, (_, x) => v[x] ?? BOOT[x] ?? '');
const KEYS = ['a','b','c','d'];

function syncForm() {
  const d = S.details, f = S.scan && S.scan.fields;
  if (d && d.confirmed) Object.assign(form, {name:d.name, eid:d.eid, expiry:d.expiry, phone:d.phone, email:d.email});
  else {
    if (f && !form.name && !form.eid && !form.expiry) Object.assign(form, {name:f.name, eid:f.eid, expiry:f.expiry});
    if (d) { form.phone = form.phone || d.phone || ''; form.email = form.email || d.email || ''; }
  }
}
function maxStep() {
  if (S.sub) return 6;
  if (!S.scan || !S.scan.ok) return 2;
  if (!S.details || !S.details.confirmed) return 3;
  return KEYS.every(k => S.terms[k]) ? 5 : 4;
}
async function api(a, data, isForm) {
  const body = isForm ? data : new URLSearchParams(data || {});
  let r;
  try { r = await fetch('?a=' + a, {method:'POST', headers:{'X-CSRF':BOOT.csrf}, body, credentials:'same-origin'}); }
  catch (e) { return {ok:false, error:'net'}; }
  try { const j = await r.json(); if (j.state) S = j.state; return j; } catch (e) { return {ok:false, error:'generic'}; }
}
function errText(e) { return T[lang]['e_' + e] ? t('e_' + e) : t('e_generic'); }

function render() {
  document.documentElement.lang = lang;
  document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  $('#langBtn').textContent = t('lang');
  $('#legal').textContent = lang === 'ar' ? BOOT.company.arabic : BOOT.company.legal;
  $('#barFill').style.width = (step / 6 * 100) + '%';
  $('#stepNo').textContent = t('step', {n: step});
  $('#ico').textContent = ['👋','🪪','📝','📋','✍️','🎉'][step - 1];
  const v = $('#view');
  const M = msg ? `<div class="msg ${msg.type}" role="alert">${esc(msg.text)}</div>` : '';
  const navBack = `<button class="btn alt" data-go="${step - 1}" type="button">${t('back')}</button>`;
  if (step === 1) {
    v.innerHTML = `<h1>${t('w_title')}</h1><p class="ctr"><b>${esc(BOOT.company.legal)}</b><br><span class="muted">${esc(BOOT.company.arabic)}<br>Dubai Licence No. ${esc(BOOT.company.licence)}</span></p>
      <p class="ctr muted">${t('w_intro')}</p><p class="muted mt ctr">${t('w_note')}</p>
      <button class="btn" data-go="2" type="button">${t('start')}</button>`;
  } else if (step === 2) {
    const sc = S.scan, back = sc && sc.needs_back;
    let body = sc && sc.ok ? `<div class="msg ok">${t('s_done')}</div>` : `<p>${back ? t('s_back') : t('s_front')}</p>`;
    v.innerHTML = `<h2>${t('s_title')}</h2>${body}
      ${busy ? `<div class="msg info">${t('s_wait')}</div>` : `
      <label class="btn${sc && sc.ok ? ' alt' : ''}">${t('s_cam')}<input type="file" id="cam" accept="image/*" capture="environment" class="vh"></label>
      <label class="btn alt">${t('s_lib')}<input type="file" id="lib" accept="image/*,application/pdf" class="vh"></label>
      ${back ? `<label class="link">${t('s_redo')}<input type="file" id="redo" accept="image/*" capture="environment" class="vh"></label>` : ''}`}
      ${M}<p class="muted mt">${t('w_note')}</p>
      <div class="nav">${navBack}<button class="btn" data-go="3" type="button" ${sc && sc.ok && !busy ? '' : 'disabled'}>${t('next')}</button></div>`;
    if (!busy) {
      const on = (sel, side) => { const i = $(sel); if (i) i.onchange = () => { if (i.files[0]) scan(i.files[0], side); }; };
      on('#cam', back ? 'back' : 'front'); on('#lib', back ? 'back' : 'front'); on('#redo', 'front');
    }
  } else if (step === 3) {
    const f = S.scan.fields;
    const fld = (k, type, extra = '') => {
      const from = (k in f) && form[k] !== f[k] ? `<div class="hint">${esc(t('d_from', {v: f[k] || '—'}))}</div>` : '';
      const bad = badFields.includes(k) ? ' class="bad"' : '';
      return `<label class="f" for="f_${k}">${t('d_' + (k === 'expiry' ? 'exp' : k))}</label><input id="f_${k}" name="${k}" type="${type}" value="${esc(form[k])}" ${extra}${bad}>${from}`;
    };
    v.innerHTML = `<h2>${t('d_title')}</h2>${msg && msg.type === 'ok' ? M : ''}<p class="muted">${t('d_intro')}</p>
      ${fld('name','text','autocomplete="name"')}${fld('eid','text','inputmode="numeric" dir="ltr"')}${fld('expiry','date','dir="ltr"')}
      ${fld('phone','tel','autocomplete="tel" dir="ltr" placeholder="+971 5x xxx xxxx"')}${fld('email','email','autocomplete="email" dir="ltr"')}
      <p class="hint mt">${t('contact_note')}</p>${msg && msg.type === 'ok' ? '' : M}
      <button class="btn" id="conf" type="button" ${busy ? 'disabled' : ''}>${t('d_btn')}</button>
      <div class="nav">${navBack}</div>`;
    v.querySelectorAll('input').forEach(i => i.oninput = () => { form[i.name] = i.value; });
    $('#conf').onclick = saveDetails;
  } else if (step === 4) {
    const k = KEYS[tstep], term = BOOT.terms[k];
    v.innerHTML = `<h2>${t('t_title')}</h2><div class="stepno">${t('t_part', {n: tstep + 1})}</div>
      <h3 class="th">${esc(term[lang + '_t'])}</h3><p>${esc(term[lang])}</p>
      ${k === 'c' || tstep === 3 ? `<div class="msg info">${esc(BOOT.note[lang])}</div>` : ''}
      <label class="chk"><input type="checkbox" id="acc" ${S.terms[k] ? 'checked' : ''}><span>${t('t_accept')}</span></label>${M}
      <div class="nav"><button class="btn alt" id="tb" type="button">${t('back')}</button><button class="btn" id="tn" type="button" ${S.terms[k] && !busy ? '' : 'disabled'}>${t('next')}</button></div>`;
    $('#acc').onchange = async e => { busy = true; const r = await api('terms', {key: k, accepted: e.target.checked ? '1' : '0'}); busy = false; msg = r.ok ? null : {type:'err', text: errText(r.error)}; render(); };
    $('#tb').onclick = () => { msg = null; if (tstep > 0) { tstep--; render(); } else go(3); };
    $('#tn').onclick = () => { msg = null; if (tstep < 3) { tstep++; render(); } else go(5); };
  } else if (step === 5) {
    const d = S.details;
    const agree = `<h3>${esc(lang === 'ar' ? BOOT.company.arabic : BOOT.company.legal)}</h3><p class="muted">Dubai Licence No. ${esc(BOOT.company.licence)}</p>`
      + KEYS.map((k, i) => `<h3>${i + 1}. ${esc(BOOT.terms[k][lang + '_t'])}</h3><p>${esc(BOOT.terms[k][lang])}</p>`).join('')
      + `<p><i>${esc(BOOT.note[lang])}</i></p>`;
    v.innerHTML = `<h2>${t('r_title')}</h2><h3 class="sh">${t('r_details')}</h3>
      <dl><dt>${t('d_name')}</dt><dd>${esc(d.name)}</dd><dt>${t('d_eid')}</dt><dd dir="ltr">${esc(d.eid)}</dd><dt>${t('d_exp')}</dt><dd dir="ltr">${esc(d.expiry)}</dd>
      <dt>${t('d_phone')}</dt><dd dir="ltr">${esc(d.phone)}</dd><dt>${t('d_email')}</dt><dd dir="ltr">${esc(d.email)}</dd></dl>
      <button class="link" data-go="3" type="button">${t('r_edit')}</button>
      <h3 class="sh">${t('r_agree')}</h3><div class="agree">${agree}</div>
      <label class="f">${t('r_sign')}</label><canvas id="sig"></canvas><button class="link" id="clr" type="button">${t('r_clear')}</button>
      <label class="chk"><input type="checkbox" id="fin" ${finalChk ? 'checked' : ''}><span>${esc(BOOT.decl[lang])}</span></label>
      ${busy ? `<div class="msg info">${t('r_wait')}</div>` : ''}${M}
      <button class="btn" id="sub" type="button" ${finalChk && !busy ? '' : 'disabled'}>${t('r_btn')}</button>
      <div class="nav"><button class="btn alt" id="rb" type="button">${t('back')}</button></div>`;
    setupSig();
    $('#fin').onchange = e => { finalChk = e.target.checked; $('#sub').disabled = !finalChk || busy; };
    $('#clr').onclick = () => { sigData = null; sigInk = 0; setupSig(); };
    $('#rb').onclick = () => { tstep = 3; go(4); };
    $('#sub').onclick = submit;
  } else if (step === 6) {
    const s = S.sub, e = s.email;
    const mail = e === 'accepted' ? `<div class="msg ok">${t('f_mail_ok')}</div>`
      : e === 'pending' ? `<div class="msg info">${t('f_mail_wait')}</div>`
      : `<div class="msg err">${t('f_mail_fail')}</div><button class="btn alt" id="retry" type="button" ${busy ? 'disabled' : ''}>${t('f_retry')}</button>`;
    v.innerHTML = `<h2>${t('f_title')}</h2><p class="muted">${t('f_track')}</p><p class="big" dir="ltr">${esc(s.id)}</p>
      ${s.file_ok ? `<a class="btn" href="?a=pdf&t=${encodeURIComponent(BOOT.csrf)}">${t('f_dl')}</a><p class="muted mt">${t('f_keep')}</p>` : `<div class="msg err">${t('f_gone')}</div>`}
      ${mail}${M}`;
    const rb = $('#retry');
    if (rb) rb.onclick = async () => { busy = true; render(); const r = await api('email'); busy = false; msg = r.ok ? null : {type:'err', text: errText(r.error)}; render(); };
  }
  v.querySelectorAll('[data-go]').forEach(b => b.onclick = () => go(+b.dataset.go));
}
function go(n) {
  if (busy) return;
  msg = null; badFields = [];
  step = Math.max(1, Math.min(n, maxStep()));
  if (step === 4 && tstep > 3) tstep = 0;
  syncForm(); render(); window.scrollTo(0, 0);
}

async function toJpeg(file) {
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = url; });
    const sc = Math.min(1, 2000 / Math.max(img.naturalWidth, img.naturalHeight));
    const c = document.createElement('canvas');
    c.width = Math.round(img.naturalWidth * sc); c.height = Math.round(img.naturalHeight * sc);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return await new Promise(res => c.toBlob(res, 'image/jpeg', 0.9));
  } finally { URL.revokeObjectURL(url); }
}
async function scan(file, side) {
  busy = true; msg = null; render();
  const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
  let blob = null;
  if (isPdf) blob = file.size <= 8 * 1024 * 1024 ? file : null;
  else { try { blob = await toJpeg(file); } catch (e) { blob = null; } }
  if (!blob) { busy = false; msg = {type:'err', text: t('e_bad_image')}; render(); return; }
  const fd = new FormData(); fd.append('side', side); fd.append('image', blob, isPdf ? 'card.pdf' : 'card.jpg');
  const r = await api('scan', fd, true);
  busy = false;
  if (r.ok) {
    form.name = form.eid = form.expiry = ''; syncForm();
    if (S.scan.ok) { go(3); msg = {type:'ok', text: t('s_done')}; render(); return; }
  } else msg = {type:'err', text: errText(r.error)};
  render();
}
async function saveDetails() {
  document.querySelectorAll('#view input').forEach(i => form[i.name] = i.value.trim());
  busy = true; render();
  const r = await api('details', form);
  busy = false;
  if (r.ok) { tstep = 0; go(4); return; }
  badFields = r.fields || [];
  msg = {type:'err', text: r.error === 'invalid' ? t('d_bad') : errText(r.error)};
  render();
}
function setupSig() {
  const c = $('#sig'); if (!c) return;
  const r = c.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
  c.width = Math.round(r.width * dpr); c.height = Math.round(r.height * dpr);
  const x = c.getContext('2d');
  x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
  if (sigData) { const im = new Image(); im.onload = () => x.drawImage(im, 0, 0, c.width, c.height); im.src = sigData; }
  x.lineWidth = 2.6 * dpr; x.lineCap = x.lineJoin = 'round'; x.strokeStyle = '#13294b';
  let down = false, lx = 0, ly = 0;
  const pos = e => { const b = c.getBoundingClientRect(); return [(e.clientX - b.left) * dpr, (e.clientY - b.top) * dpr]; };
  c.onpointerdown = e => { down = true; c.setPointerCapture(e.pointerId); [lx, ly] = pos(e); e.preventDefault(); };
  c.onpointermove = e => { if (!down) return; const [nx, ny] = pos(e); x.beginPath(); x.moveTo(lx, ly); x.lineTo(nx, ny); x.stroke(); sigInk += Math.hypot(nx - lx, ny - ly); lx = nx; ly = ny; e.preventDefault(); };
  c.onpointerup = c.onpointercancel = () => { if (down) { down = false; sigData = c.toDataURL('image/jpeg', 0.85); } };
}
async function submit() {
  if (sigInk < 60 || !sigData) { msg = {type:'err', text: t('r_need_sig')}; render(); return; }
  busy = true; msg = null; render();
  const r = await api('submit', {final: finalChk ? '1' : '0', signature: sigData});
  busy = false;
  if (r.ok && S.sub) { go(6); return; }
  msg = {type:'err', text: r.error === 'need_signature' ? t('r_need_sig') : errText(r.error)};
  render();
}
$('#langBtn').onclick = () => { lang = lang === 'en' ? 'ar' : 'en'; try { localStorage.setItem('tm_lang', lang); } catch (e) {} render(); };
syncForm();
step = S.sub ? 6 : 1;
render();
</script>
</body>
</html>
