<?php
// Kompatibel dengan PHP 5.4 ke atas

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_mou');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', dirname(__FILE__) . '/uploads/');
define('MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('PER_PAGE', 10);

/** Kesalahan input yang aman ditampilkan ke pengguna (bukan error database) */
class UserError extends Exception {}

// ---------- Fungsi cadangan untuk PHP lama ----------
if (!function_exists('hash_equals')) {
    function hash_equals($a, $b) {
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) return false;
        $r = 0;
        for ($i = 0; $i < strlen($a); $i++) $r |= ord($a[$i]) ^ ord($b[$i]);
        return $r === 0;
    }
}

function acak_hex($n) {
    if (function_exists('random_bytes')) return bin2hex(random_bytes($n));
    if (function_exists('openssl_random_pseudo_bytes')) {
        $kuat = false;
        $b = openssl_random_pseudo_bytes($n, $kuat);
        if ($b !== false && $kuat) return bin2hex($b);
    }
    $s = '';   // cadangan terakhir bila openssl tidak aktif
    while (strlen($s) < $n * 2) $s .= sha1(uniqid(mt_rand(), true) . microtime() . mt_rand());
    return substr($s, 0, $n * 2);
}

function http_status($code) {
    $t = array(404 => 'Not Found', 405 => 'Method Not Allowed', 419 => 'Page Expired');
    header('HTTP/1.1 ' . $code . ' ' . (isset($t[$code]) ? $t[$code] : 'Error'));
}

function ambil($arr, $key, $default = null) {
    return (isset($arr[$key]) && !is_array($arr[$key])) ? $arr[$key] : $default;
}

function panjang($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function potong($s, $n) { return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n); }

// ---------- Fungsi utama ----------
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                  PDO::ATTR_EMULATE_PREPARES => false,
                  PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4')
        );
    }
    return $pdo;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tgl($d) { return date('d-m-Y', strtotime($d)); }

function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = acak_hex(32);
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check() {
    $t = (string)ambil($_POST, 'csrf', '');
    $s = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
    if ($s === '' || !hash_equals($s, $t)) {
        http_status(419); exit('Token tidak valid. Muat ulang halaman.');
    }
}

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);
    return $m;
}

function valid_date($d) {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

/** Validasi ketat & simpan PDF dengan nama acak. Mengembalikan nama file tersimpan. */
function simpan_pdf(array $f) {
    if ($f['error'] !== UPLOAD_ERR_OK) throw new UserError('Upload file gagal.');
    if ($f['size'] > MAX_SIZE) throw new UserError('Ukuran file maksimal 5 MB.');

    $fi   = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    // Standar PDF: penanda "%PDF-" boleh berada dalam 1024 byte pertama
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 1024);
    $mime_ok = in_array($mime, array('application/pdf', 'application/x-pdf', 'application/octet-stream'), true);

    if (!$mime_ok || $ext !== 'pdf' || strpos($head, '%PDF-') === false) {
        throw new UserError('File harus berformat PDF yang valid.');
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new UserError('Folder uploads tidak bisa dibuat. Buat manual folder "uploads".');
    }
    if (!is_file(UPLOAD_DIR . '.htaccess')) {
        file_put_contents(UPLOAD_DIR . '.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    $name = acak_hex(16) . '.pdf';
    if (!@move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
        throw new UserError('Gagal menyimpan file.');
    }
    return $name;
}

function hapus_pdf($name) {
    if ($name && preg_match('/^[a-f0-9]{32}\.pdf$/', $name) && is_file(UPLOAD_DIR . $name)) {
        unlink(UPLOAD_DIR . $name);
    }
}

function head($title) { ?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> - MOU Asuransi</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f4f6f9;margin:0;color:#222}
.wrap{max-width:1000px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
h1{margin:0 0 16px;font-size:22px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee;font-size:14px}
th{background:#fafafa}.tbl{overflow-x:auto}
td{transition:background .15s}
tr:hover td{background:#eef4ff}
tr:hover td:first-child{box-shadow:inset 3px 0 0 #2563eb}
.btn{display:inline-block;padding:8px 14px;border-radius:6px;border:0;background:#2563eb;color:#fff;text-decoration:none;font-size:14px;cursor:pointer}
.btn.g{background:#6b7280}.btn.r{background:#dc2626}.btn.s{padding:5px 10px;font-size:13px}
input[type=text],input[type=date],input[type=file],input[type=search]{width:100%;padding:9px;border:1px solid #ccc;border-radius:6px;font-size:14px}
label{display:block;margin:14px 0 5px;font-weight:600;font-size:14px}
.bar{display:flex;gap:8px;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap}
.bar form{display:flex;gap:6px}.alert{padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:14px}
.ok{background:#dcfce7;color:#166534}.err{background:#fee2e2;color:#991b1b}
.b{display:inline-block;white-space:nowrap;padding:3px 10px;border-radius:12px;font-size:12px;color:#fff}
td:nth-child(5){white-space:nowrap}
.b1{background:#16a34a}.b2{background:#f59e0b}.b3{background:#dc2626}
.pg{margin-top:14px;display:flex;gap:6px;flex-wrap:wrap}.pg a{padding:6px 11px;background:#e5e7eb;border-radius:6px;text-decoration:none;color:#222;font-size:13px}.pg a.on{background:#2563eb;color:#fff}
.act{display:flex;gap:5px}.act form{margin:0}
</style></head><body><div class="wrap"><div class="card">
<?php }
function foot() { echo '</div></div></body></html>'; }