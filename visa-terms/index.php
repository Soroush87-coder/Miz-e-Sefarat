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

const TERMS_VERSION = '2026-10-v3';
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
    'fa_t' => 'تصمیم ویزا',
    'fa'   => 'تصمیم صدور یا رد ویزا با مرجع صادرکننده است. شرکت صدور ویزا یا زمان تصمیم‌گیری مرجع را تضمین نمی‌کند.',
  ],
  'b' => [
    'en_t' => 'How our services are provided',
    'en'   => 'For your convenience, document review and follow-up of your application are carried out online. If you need an in-person meeting, please arrange it with our team in advance.',
    'ar_t' => 'طريقة تقديم الخدمات',
    'ar'   => 'تيسيراً عليكم، تتم مراجعة المستندات ومتابعة الطلب عبر الإنترنت. إذا احتجتم إلى اجتماع حضوري، يرجى التنسيق مع فريقنا مسبقاً.',
    'fa_t' => 'نحوه ارائه خدمات',
    'fa'   => 'برای راحتی شما، بررسی مدارک و پیگیری درخواست به‌صورت آنلاین انجام می‌شود. در صورت نیاز به جلسه حضوری، لطفاً از قبل با تیم ما هماهنگ کنید.',
  ],
  'c' => [
    'en_t' => 'Service fees',
    'en'   => 'The fees for our services were communicated to you before you completed this form. By accepting these terms, you confirm that you were informed of these fees and that you agree to them.',
    'ar_t' => 'رسوم الخدمات',
    'ar'   => 'تم إبلاغكم برسوم خدماتنا قبل تعبئة هذا النموذج. وبموافقتكم على هذه الشروط، تؤكدون أنكم أُبلغتم بهذه الرسوم وأنكم توافقون عليها.',
    'fa_t' => 'هزینه خدمات',
    'fa'   => 'هزینه خدمات ما پیش از تکمیل این فرم به شما اعلام شده است. با پذیرش این شرایط، تأیید می‌کنید که از این هزینه‌ها مطلع شده‌اید و آن‌ها را می‌پذیرید.',
  ],
  'd' => [
    'en_t' => 'Visa refusal and refunds',
    'en'   => 'A visa refusal does not, by itself, entitle the customer to a refund of service fees for services that were correctly performed as agreed. The customer\'s rights regarding services not performed, or any failure by the company, remain protected.',
    'ar_t' => 'رفض التأشيرة واسترداد المبالغ',
    'ar'   => 'رفض التأشيرة بحد ذاته لا يوجب استرداد رسوم الخدمات التي نُفِّذت بشكل صحيح وفق الاتفاق. وتبقى حقوق العميل محفوظة فيما يتعلق بالخدمات غير المنفَّذة أو أي تقصير من جانب الشركة.',
    'fa_t' => 'ریجکتی و بازپرداخت',
    'fa'   => 'رد ویزا به‌تنهایی موجب بازپرداخت کارمزد خدماتی که مطابق توافق به‌درستی انجام شده‌اند نمی‌شود. حقوق مشتری درباره خدمات انجام‌نشده یا قصور شرکت محفوظ است.',
  ],
];
$DECLARATION = [
  'en' => 'I have read all of the terms above, the details I have entered are correct, and I accept these terms.',
  'ar' => 'لقد قرأت جميع الشروط أعلاه، والبيانات التي أدخلتها صحيحة، وأوافق على هذه الشروط.',
  'fa' => 'همه شرایط بالا را خوانده‌ام، اطلاعاتی که وارد کرده‌ام درست است و این شرایط را می‌پذیرم.',
];
$LANG_NAMES = ['en' => 'English', 'ar' => 'Arabic', 'fa' => 'Persian'];

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
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$NONCE'; style-src 'nonce-$NONCE' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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

function norm_name($s) {
  $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
  if (mb_strlen($s) < 3 || mb_strlen($s) > 100) return '';
  if (!preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'\-]*$/u", $s)) return '';
  return $s;
}

function public_state() {
  $s = $_SESSION;
  return [
    'sub' => isset($s['sub']) ? ['id' => $s['sub']['id'], 'email' => $s['sub']['email'], 'file_ok' => is_file($s['sub']['file'])] : null,
  ];
}

// ---------------------------------------------------------------- PDF
function build_pdf($snap, $dest) {
  global $P, $COMPANY, $TERMS, $DECLARATION, $LANG_NAMES;
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
  $en = function ($html) use ($pdf) { $pdf->setRTL(false); $pdf->SetFont('dejavusans', '', 9.5); $pdf->writeHTML($html, true, false, true, false, ''); };
  $rtl = function ($html, $font, $size) use ($pdf) { $pdf->setRTL(true); $pdf->SetFont($font, '', $size); $pdf->writeHTML($html, true, false, true, false, ''); $pdf->setRTL(false); };

  $en('<table cellpadding="2"><tr><td width="60%"><b style="font-size:13pt;color:#1e1b3a">' . h($COMPANY['legal']) . '</b><br>'
    . 'Trading as ' . h($COMPANY['brand']) . '<br>Dubai Licence No. ' . h($COMPANY['licence']) . '</td>'
    . '<td width="40%" align="right"><span style="font-size:8pt;color:#555">Tracking No.</span><br><b style="font-size:12pt">' . h($snap['id']) . '</b><br>'
    . '<span style="font-size:8pt">' . h($snap['time_label']) . '</span></td></tr></table>'
    . '<hr><h2 style="color:#5b4bf0;font-size:14pt">Visa Service Terms Agreement</h2>');
  $rtl('<span style="font-size:13pt"><b>' . h($COMPANY['arabic']) . '</b></span><br>اتفاقية شروط خدمات التأشيرة<br>رخصة دبي رقم ' . h($COMPANY['licence']), 'aealarabiya', 12);

  $rows = [['Full name', 'الاسم الكامل', $c['name']], ['Mobile', 'رقم الهاتف المتحرك', $c['phone']], ['Email', 'البريد الإلكتروني', $c['email']],
           ['Form language', 'لغة النموذج', $LANG_NAMES[$snap['lang']]]];
  $t = '<h3 style="font-size:11pt">Customer details</h3><table border="0.3" cellpadding="4">';
  foreach ($rows as $r)
    $t .= '<tr><td width="34%" style="background-color:#f3f1ff">' . h($r[0]) . '<br><span style="font-size:8.5pt">' . h($r[1]) . '</span></td><td width="66%"><b>' . h($r[2]) . '</b></td></tr>';
  $en($t . '</table><p style="font-size:7.5pt;color:#555">Name, mobile and email were entered by the customer and were not verified.</p>');

  $x = '<h3 style="font-size:11pt">Terms accepted</h3>';
  $i = 0;
  foreach ($TERMS as $k => $term) { $i++; $x .= '<p><b>' . $i . '. ' . h($term['en_t']) . '</b><br>' . h($term['en']) . '</p>'; }
  $en($x);
  $x = '<h3>الشروط المقبولة</h3>'; $i = 0;
  foreach ($TERMS as $term) { $i++; $x .= '<p><b>' . $i . '. ' . h($term['ar_t']) . '</b><br>' . h($term['ar']) . '</p>'; }
  $rtl($x, 'aealarabiya', 12);
  $x = '<h3>شرایط پذیرفته‌شده</h3>'; $i = 0;
  foreach ($TERMS as $term) { $i++; $x .= '<p><b>' . $i . '. ' . h($term['fa_t']) . '</b><br>' . h($term['fa']) . '</p>'; }
  $rtl($x, 'dejavusans', 9.5);

  if ($pdf->GetY() > 220) $pdf->AddPage();
  $en('<h3 style="font-size:11pt">Declaration</h3><p>' . h($DECLARATION['en']) . '</p>');
  $rtl('<p>' . h($DECLARATION['ar']) . '</p>', 'aealarabiya', 12);
  $rtl('<p>' . h($DECLARATION['fa']) . '</p>', 'dejavusans', 9.5);
  $en('<table border="0.3" cellpadding="6"><tr><td style="background-color:#f8f7ff">Accepted electronically by the customer:<br><b>' . h($c['name']) . '</b><br>'
    . 'The customer ticked the declaration above, accepting all of the terms, before submitting the form.<br>'
    . 'Submitted: ' . h($snap['time_label']) . '<br><span style="font-size:7.5pt;color:#555">IP address: ' . h($snap['ip'])
    . ' · Terms version ' . h($snap['terms_version']) . ' · SHA-256 of terms: ' . h(substr($snap['terms_hash'], 0, 32)) . '…</span></td></tr></table>');
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
    $m->Subject = 'Accepted visa service terms ' . $sub['id'] . ' — ' . $c['name'];
    $m->Body = "A customer accepted the visa service terms.\n\nTracking No.: {$sub['id']}\nSubmitted: {$sub['snap']['time_label']}\n"
      . "Name: {$c['name']}\nMobile: {$c['phone']}\nEmail: {$c['email']}\n\n"
      . "The agreement is attached. Keep this email: the file is deleted from the website automatically after a short time.";
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

  if ($a === 'submit') {
    if ($locked) out(['ok' => true, 'state' => public_state()]); // repeated click / refresh: no new agreement
    $d = [
      'name'  => norm_name($in['name'] ?? ''),
      'phone' => preg_match('/^\+?[0-9][0-9 \-()]{6,19}$/', trim($in['phone'] ?? '')) ? trim($in['phone']) : '',
      'email' => filter_var(trim($in['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
    ];
    if (mb_strlen($d['email']) > 120) $d['email'] = '';
    $bad = array_keys(array_filter($d, function ($v) { return $v === ''; }));
    if (($in['final'] ?? '') !== '1') $bad[] = 'final';
    if ($bad) fail('invalid', 422, ['fields' => $bad]);
    if (!rate_ok('sub-ip-' . client_ip(), 20, 3600)) fail('rate', 429);
    $lang = isset($LANG_NAMES[$in['lang'] ?? '']) ? $in['lang'] : 'en';

    $now = new DateTime('now', new DateTimeZone('Asia/Dubai'));
    $id = 'TM-' . $now->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $snap = [
      'id' => $id,
      'time_label' => $now->format('d M Y, H:i:s') . ' (Dubai time, GST)',
      'customer' => $d,
      'lang' => $lang,
      'terms_version' => TERMS_VERSION,
      'terms_hash' => hash('sha256', json_encode([$TERMS, $DECLARATION], JSON_UNESCAPED_UNICODE)),
      'ip' => client_ip(),
    ];
    $file = "$P/files/" . bin2hex(random_bytes(16)) . '.pdf';
    try { $okPdf = build_pdf($snap, $file); } catch (Throwable $e) { error_log('tm_visa: pdf failed ' . $e->getMessage()); $okPdf = false; }
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Vazirmatn:wght@400;500;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
<style nonce="<?= $NONCE ?>">
:root{--ink:#1e1b3a;--muted:#6b6880;--teal:#6d5dfc;--teal-d:#5b4bf0;--line:#e7e4f5;--err:#c0262d;--ok:#067647;--soft:#f5f3ff;--grad:linear-gradient(135deg,#6d5dfc 0%,#8b5cf6 55%,#c56cf0 100%)}
*{box-sizing:border-box}
html{background:#f7f3ff}
body{margin:0;min-height:100vh;color:var(--ink);font:17px/1.6 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,sans-serif;-webkit-text-size-adjust:100%;overflow-x:hidden;
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
h1,h2{text-align:center;letter-spacing:-.6px;line-height:1.25}h1{font-size:30px;margin:0 0 12px}h2{font-size:26px;margin:0 0 14px}p{margin:0 0 14px}
@media (min-width:700px){h1{font-size:36px}h2{font-size:30px}}
.muted{color:var(--muted);font-size:15px}
.btn{display:block;width:100%;border:0;border-radius:999px;padding:17px 22px;font:inherit;font-size:17px;font-weight:700;background:var(--grad);color:#fff;margin-top:14px;cursor:pointer;box-shadow:0 12px 28px rgba(109,93,252,.35);transition:transform .15s ease,box-shadow .15s ease}
.btn:hover{transform:translateY(-1px);box-shadow:0 16px 34px rgba(109,93,252,.42)}.btn:active{transform:translateY(1px)}
.btn[disabled]{opacity:.38;box-shadow:none;transform:none}
.btn.alt{background:#fff;color:var(--teal);border:1.5px solid var(--line);box-shadow:0 6px 16px rgba(30,27,58,.05)}
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
.link{background:none;border:0;color:var(--teal);font:inherit;font-size:15px;font-weight:600;text-decoration:none;padding:10px 0;cursor:pointer}
.mt{margin-top:14px}.bad{border-color:var(--err)!important}.th{margin:0 0 8px;font-size:20px;text-align:center}.sh{font-size:16px}a.btn{text-align:center;text-decoration:none}
.ctr{text-align:center}
.big{font-size:28px;font-weight:800;letter-spacing:1px;text-align:center;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.langs{position:absolute;top:0;inset-inline-end:0;display:flex;gap:2px;padding:3px;background:rgba(255,255,255,.75);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.95);border-radius:999px;box-shadow:0 4px 14px rgba(109,93,252,.12)}
.langs button{border:0;background:none;border-radius:999px;padding:6px 11px;font:inherit;font-size:13px;font-weight:600;color:var(--ink);cursor:pointer}
.langs button[aria-pressed=true]{background:var(--grad);color:#fff}
@media (max-width:480px){header{padding-top:52px}}
.intro{margin-bottom:26px}
.term{position:relative;background:#fff;border:1.5px solid var(--line);border-radius:24px;padding:20px 20px 16px;margin-top:16px}
.term.ok{border-color:#c9b8fb;background:#fdfcff}
.term.bad{border-color:var(--err)}
.term{display:flex;gap:14px;align-items:flex-start}.term h3{margin:2px 0 6px;font-size:18px}
.num{flex:none;width:32px;height:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:#fff;background:var(--grad)}
.term p{margin:0}
.term .chk{margin-top:14px;border:0;background:var(--soft);padding:12px 14px;border-radius:16px}
.sec{margin:34px 0 4px;text-align:center}
.sec span{display:inline-block;font-size:30px;margin-bottom:4px}
.chk.bad{border-color:var(--err)}
.big{margin:8px 0 18px}
.done{text-align:center;font-size:56px;margin:6px 0 10px}
html[lang=fa] body,html[lang=fa] input,html[lang=fa] button{font-family:Vazirmatn,Tahoma,sans-serif}
html[lang=ar] body,html[lang=ar] input,html[lang=ar] button{font-family:"IBM Plex Sans Arabic","Geeza Pro",Tahoma,sans-serif}
.langs button[data-lang=fa]{font-family:Vazirmatn,Tahoma,sans-serif}.langs button[data-lang=ar]{font-family:"IBM Plex Sans Arabic",Tahoma,sans-serif}.langs button[data-lang=en]{font-family:Inter,sans-serif}
#legal{font-family:inherit}
/* desktop: everything on one screen */
@media (min-width:1024px) and (min-height:600px){
 html,body{height:100%;overflow:hidden}
 body{font-size:16px}
 .wrap{max-width:1360px;height:100vh;display:flex;flex-direction:column;padding:14px 28px 18px}
 header{display:flex;align-items:center;justify-content:space-between;text-align:start;padding:0 6px 12px}
 .brand{display:flex;align-items:baseline;gap:14px}.brand small{margin:0}
 .logo{font-size:22px}.logo svg{width:32px;height:32px}
 .langs{position:static;order:2}
 .card{flex:1;min-height:0;padding:clamp(18px,2.6vh,30px) clamp(22px,2.4vw,36px);border-radius:26px;overflow:auto}
 .layout{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(330px,1fr);gap:clamp(20px,2.2vw,34px);height:100%}
 .col-main{display:flex;flex-direction:column;min-height:0}
 .intro{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:clamp(10px,1.6vh,18px)}
 .intro h1{font-size:clamp(24px,3.4vh,32px);margin:0;text-align:start}
 .intro p{text-align:start;font-size:14px;margin:0}.intro .co{text-align:end}.intro p.muted:last-child{flex-basis:100%}
 .co .sep br{display:none}.co .sep::after{content:" · "}
 .terms{display:flex;flex-direction:column;gap:clamp(8px,1.4vh,14px);flex:1;min-height:0}
 .term{flex:1 1 0;margin:0;align-items:center;padding:clamp(10px,1.7vh,18px) clamp(16px,1.6vw,22px);border-radius:20px}
 .term h3{font-size:clamp(15px,2vh,17.5px);margin:0 0 3px}
 .term p{font-size:clamp(13.5px,1.95vh,16px);line-height:1.55}
 .num{width:34px;height:34px;font-size:15px;border-radius:12px}
 .col-side{display:flex;flex-direction:column;justify-content:space-between;gap:12px;background:var(--soft);border-radius:22px;padding:clamp(16px,2.4vh,26px) clamp(18px,1.8vw,26px);min-height:0}
 .sec{margin:0;text-align:start}.sec span{display:none}.sec h2{font-size:clamp(20px,2.8vh,26px);margin:0 0 4px;text-align:start}.sec p{font-size:14px;margin:0}
 label.f{margin:clamp(6px,1.3vh,14px) 6px 5px;font-size:13.5px}
 input[type=text],input[type=email],input[type=tel]{padding:clamp(9px,1.5vh,14px) 18px;font-size:15.5px}
 .hint{margin-top:8px}
 .tail .chk{margin-top:0;padding:clamp(10px,1.6vh,16px) 16px;font-size:14.5px}
 .tail .btn{margin-top:clamp(10px,1.6vh,16px);padding:clamp(12px,1.9vh,17px) 20px;font-size:16.5px}
 .tail .msg{padding:10px 12px;font-size:13.5px;margin-top:8px}
 .card .done{font-size:48px}
}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<div class="wrap">
  <header>
    <div class="langs" role="group" aria-label="Language">
      <button type="button" data-lang="en">English</button><button type="button" data-lang="ar">العربية</button><button type="button" data-lang="fa">فارسی</button>
    </div>
    <div class="brand"><span class="logo"><svg viewBox="0 0 32 32" aria-hidden="true"><defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7c6cf5"/><stop offset="1" stop-color="#5046e5"/></linearGradient></defs><rect x="3" y="3" width="26" height="26" rx="8" fill="none" stroke="url(#lg)" stroke-width="3"/><path d="M10.5 16.5l4 4 7-8" fill="none" stroke="url(#lg)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>Travel Market</span><small id="legal">SOUQ AL SAFAR TOURISM L.L.C</small></div>
  </header>
  <main class="card" id="view"></main>
</div>
<script nonce="<?= $NONCE ?>">
const BOOT = <?= json_encode($BOOT, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const T = {
en:{title:'Visa service terms',intro:'Please read the terms below. At the end, enter your details, confirm and submit.',
 accept:'I have read and accept this term.',sec:'Your details',sec_intro:'Before finishing, please enter your full name, mobile number and email.',
 name:'Full name',phone:'Mobile number',email:'Email',contact_note:'Your details are recorded as you type them; they are not verified.',
 submit:'Accept and create agreement',wait:'Creating your agreement…',
 missing:'Please fill in the highlighted fields and tick the confirmation box.',
 e_rate:'Too many attempts. Please wait a while and try again.',e_net:'Connection problem. Please check your internet and try again.',
 e_generic:'Something went wrong. Please try again.',e_pdf:'The agreement could not be created. Please try again.',e_setup:'This page is temporarily unavailable.',
 f_title:'Your agreement is ready',f_track:'Tracking number',f_dl:'Download agreement (PDF)',
 f_mail_ok:'A copy was handed to our mail server for delivery to Travel Market.',
 f_mail_fail:'We could not send the copy to Travel Market yet. Your PDF is still available above.',f_retry:'Try sending again',
 f_mail_wait:'Sending a copy to Travel Market…',f_keep:'Please download and keep your copy. It will be deleted from this website within {keep} minutes.',
 f_gone:'The file has been deleted from this website. Please contact Travel Market with your tracking number.',
 licence:'Dubai Licence No.'},
ar:{title:'شروط خدمات التأشيرة',intro:'يرجى قراءة الشروط أدناه. في النهاية، أدخل بياناتك وأكّد موافقتك وأرسل النموذج.',
 accept:'قرأت هذا البند وأوافق عليه.',sec:'بياناتك',sec_intro:'قبل الإنهاء، يرجى إدخال اسمك الكامل ورقم هاتفك المتحرك وبريدك الإلكتروني.',
 name:'الاسم الكامل',phone:'رقم الهاتف المتحرك',email:'البريد الإلكتروني',contact_note:'تُسجَّل بياناتك كما أدخلتها، دون التحقق منها.',
 submit:'موافقة وإنشاء الاتفاقية',wait:'جارٍ إنشاء الاتفاقية…',
 missing:'يرجى تعبئة الحقول المحددة ووضع علامة في مربع التأكيد.',
 e_rate:'محاولات كثيرة. يرجى الانتظار قليلاً ثم المحاولة مجدداً.',e_net:'مشكلة في الاتصال. يرجى التحقق من الإنترنت والمحاولة مجدداً.',
 e_generic:'حدث خطأ. يرجى المحاولة مرة أخرى.',e_pdf:'تعذر إنشاء الاتفاقية. يرجى المحاولة مرة أخرى.',e_setup:'هذه الصفحة غير متاحة مؤقتاً.',
 f_title:'اتفاقيتك جاهزة',f_track:'رقم المتابعة',f_dl:'تنزيل الاتفاقية (PDF)',
 f_mail_ok:'تم تسليم نسخة إلى خادم البريد لدينا لإرسالها إلى Travel Market.',
 f_mail_fail:'لم نتمكن بعد من إرسال النسخة إلى Travel Market. ملف PDF لا يزال متاحاً أعلاه.',f_retry:'إعادة محاولة الإرسال',
 f_mail_wait:'جارٍ إرسال نسخة إلى Travel Market…',f_keep:'يرجى تنزيل نسختك والاحتفاظ بها. ستُحذف من هذا الموقع خلال {keep} دقيقة.',
 f_gone:'تم حذف الملف من هذا الموقع. يرجى التواصل مع Travel Market مع ذكر رقم المتابعة.',
 licence:'رخصة دبي رقم'},
fa:{title:'شرایط خدمات ویزا',intro:'لطفاً شرایط زیر را بخوانید. در پایان، مشخصات خود را وارد کنید، تیک تأیید را بزنید و فرم را ارسال کنید.',
 accept:'این بند را خواندم و می‌پذیرم.',sec:'مشخصات شما',sec_intro:'پیش از پایان، لطفاً نام کامل، شماره موبایل و ایمیل خود را وارد کنید.',
 name:'نام کامل',phone:'شماره موبایل',email:'ایمیل',contact_note:'مشخصات شما همان‌طور که وارد می‌کنید ثبت می‌شود و تأیید نمی‌شود.',
 submit:'پذیرش و ساخت توافق‌نامه',wait:'در حال ساخت توافق‌نامه…',
 missing:'لطفاً فیلدهای مشخص‌شده را پر کنید و تیک تأیید را بزنید.',
 e_rate:'تعداد تلاش‌ها زیاد است. لطفاً کمی صبر کنید و دوباره امتحان کنید.',e_net:'مشکل در اتصال. لطفاً اینترنت را بررسی و دوباره امتحان کنید.',
 e_generic:'خطایی رخ داد. لطفاً دوباره امتحان کنید.',e_pdf:'توافق‌نامه ساخته نشد. لطفاً دوباره امتحان کنید.',e_setup:'این صفحه موقتاً در دسترس نیست.',
 f_title:'توافق‌نامه شما آماده است',f_track:'شماره پیگیری',f_dl:'دانلود توافق‌نامه (PDF)',
 f_mail_ok:'یک نسخه برای ارسال به Travel Market به سرور ایمیل ما تحویل داده شد.',
 f_mail_fail:'هنوز نتوانستیم نسخه را برای Travel Market بفرستیم. فایل PDF همچنان بالاتر در دسترس است.',f_retry:'تلاش دوباره برای ارسال',
 f_mail_wait:'در حال ارسال نسخه به Travel Market…',f_keep:'لطفاً نسخه خود را دانلود و نگهداری کنید. این فایل تا {keep} دقیقه دیگر از این سایت پاک می‌شود.',
 f_gone:'فایل از این سایت پاک شده است. لطفاً با شماره پیگیری با Travel Market تماس بگیرید.',
 licence:'شماره لایسنس دبی'}
};
const KEYS = ['a','b','c','d'];
let lang = 'en';
try { const l = localStorage.getItem('tm_lang'); if (T[l]) lang = l; } catch (e) {}
let S = BOOT.state, busy = false, msg = null, bad = [];
const F = {name:'', phone:'', email:'', final:false};
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const t = (k, v = {}) => (T[lang][k] || T.en[k] || k).replace(/\{(\w+)\}/g, (_, x) => v[x] ?? BOOT[x] ?? '');
const termText = (k, f) => BOOT.terms[k][lang + f] || BOOT.terms[k]['en' + f];

async function api(a, data) {
  let r;
  try { r = await fetch('?a=' + a, {method:'POST', headers:{'X-CSRF':BOOT.csrf}, body:new URLSearchParams(data || {}), credentials:'same-origin'}); }
  catch (e) { return {ok:false, error:'net'}; }
  try { const j = await r.json(); if (j.state) S = j.state; return j; } catch (e) { return {ok:false, error:'generic'}; }
}
const errText = e => T[lang]['e_' + e] ? t('e_' + e) : t('e_generic');

function render() {
  document.documentElement.lang = lang;
  document.documentElement.dir = lang === 'en' ? 'ltr' : 'rtl';
  document.querySelectorAll('.langs button').forEach(b => b.setAttribute('aria-pressed', b.dataset.lang === lang));
  $('#legal').textContent = lang === 'en' ? BOOT.company.legal : BOOT.company.arabic;
  const v = $('#view');
  const M = msg ? `<div class="msg ${msg.type}" role="alert">${esc(msg.text)}</div>` : '';
  if (S.sub) {
    const s = S.sub, e = s.email;
    const mail = e === 'accepted' ? `<div class="msg ok">${t('f_mail_ok')}</div>`
      : e === 'pending' ? `<div class="msg info">${t('f_mail_wait')}</div>`
      : `<div class="msg err">${t('f_mail_fail')}</div><button class="btn alt" id="retry" type="button" ${busy ? 'disabled' : ''}>${t('f_retry')}</button>`;
    v.innerHTML = `<div class="done" aria-hidden="true">🎉</div><h1>${t('f_title')}</h1><p class="muted ctr">${t('f_track')}</p><p class="big" dir="ltr">${esc(s.id)}</p>
      ${s.file_ok ? `<a class="btn" href="?a=pdf&t=${encodeURIComponent(BOOT.csrf)}">${t('f_dl')}</a><p class="muted mt ctr">${t('f_keep')}</p>` : `<div class="msg err">${t('f_gone')}</div>`}
      ${mail}${M}`;
    const rb = $('#retry');
    if (rb) rb.onclick = async () => { busy = true; render(); const r = await api('email'); busy = false; msg = r.ok ? null : {type:'err', text: errText(r.error)}; render(); };
    return;
  }
  const terms = KEYS.map((k, i) => `<section class="term"><span class="num">${i + 1}</span>
      <div><h3>${esc(termText(k, '_t'))}</h3><p>${esc(termText(k, ''))}</p></div></section>`).join('');
  const fld = (k, type, extra) => `<label class="f" for="f_${k}">${t(k)}</label><input id="f_${k}" name="${k}" type="${type}" value="${esc(F[k])}" ${extra}${bad.includes(k) ? ' class="bad"' : ''}>`;
  v.innerHTML = `<div class="layout"><div class="col-main"><div class="intro"><h1>${t('title')}</h1>
      <p class="ctr co"><b>${esc(BOOT.company.legal)}</b><br><span class="muted">${esc(BOOT.company.arabic)}<span class="sep"><br></span>${t('licence')} ${esc(BOOT.company.licence)}</span></p>
      <p class="ctr muted">${t('intro')}</p></div>
    <div class="terms">${terms}</div></div>
    <aside class="col-side"><div class="sec"><span aria-hidden="true">📝</span><h2>${t('sec')}</h2><p class="muted">${t('sec_intro')}</p></div>
    <div class="fields">${fld('name', 'text', 'autocomplete="name"')}
    ${fld('phone', 'tel', 'autocomplete="tel" dir="ltr" placeholder="+971 5x xxx xxxx"')}
    ${fld('email', 'email', 'autocomplete="email" dir="ltr"')}
    <p class="hint mt">${t('contact_note')}</p></div>
    <div class="tail"><label class="chk${bad.includes('final') ? ' bad' : ''}"><input type="checkbox" id="fin" ${F.final ? 'checked' : ''}><span>${esc(BOOT.decl[lang])}</span></label>
    ${busy ? `<div class="msg info">${t('wait')}</div>` : ''}${M}
    <button class="btn" id="sub" type="button" ${busy ? 'disabled' : ''}>${t('submit')}</button></div></aside></div>`;
  const clear = (key, el) => { bad = bad.filter(x => x !== key); el.classList.remove('bad'); if (!bad.length && msg && msg.type === 'err') { msg = null; const m = $('#view .msg.err'); if (m) m.remove(); } };
  v.querySelectorAll('input[name]').forEach(i => i.oninput = () => { F[i.name] = i.value; if (i.value.trim()) clear(i.name, i); });
  $('#fin').onchange = e => { F.final = e.target.checked; if (F.final) clear('final', e.target.closest('.chk')); };
  $('#sub').onclick = submit;
}

async function submit() {
  document.querySelectorAll('#view input[name]').forEach(i => F[i.name] = i.value.trim());
  bad = [];
  ['name', 'phone', 'email'].forEach(k => { if (!F[k]) bad.push(k); });
  if (!F.final) bad.push('final');
  if (bad.length) { msg = {type:'err', text: t('missing')}; render(); scrollToFirstBad(); return; }
  busy = true; msg = null; render();
  const data = {name: F.name, phone: F.phone, email: F.email, final: '1', lang};
  const r = await api('submit', data);
  busy = false;
  if (r.ok && S.sub) { msg = null; render(); window.scrollTo(0, 0); return; }
  bad = r.fields || [];
  msg = {type:'err', text: r.error === 'invalid' ? t('missing') : errText(r.error)};
  render(); scrollToFirstBad();
}
function scrollToFirstBad() { const el = document.querySelector('#view .bad') || document.querySelector('#view .msg.err'); if (el) el.scrollIntoView({block:'center', behavior:'smooth'}); }
document.querySelectorAll('.langs button').forEach(b => b.onclick = () => {
  document.querySelectorAll('#view input[name]').forEach(i => F[i.name] = i.value);
  lang = b.dataset.lang; try { localStorage.setItem('tm_lang', lang); } catch (e) {} msg = null; render();
});
render();
</script>
</body>
</html>
