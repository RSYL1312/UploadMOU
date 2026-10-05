<?php
declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

const DB_HOST = 'localhost';
const DB_NAME = 'db_mou';
const DB_USER = 'root';
const DB_PASS = '';
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_SIZE = 5 * 1024 * 1024; // 5 MB
const PER_PAGE = 10;

function db(): PDO {
    static $pdo = null;
    return $pdo ??= new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tgl(string $d): string { return date('d-m-Y', strtotime($d)); }

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Token tidak valid. Muat ulang halaman.');
    }
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

/** Validasi ketat & simpan PDF dengan nama acak. Mengembalikan nama file tersimpan. */
function simpan_pdf(array $f): string {
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload file gagal.');
    if ($f['size'] > MAX_SIZE) throw new RuntimeException('Ukuran file maksimal 5 MB.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

    // Standar PDF: penanda "%PDF-" boleh berada dalam 1024 byte pertama
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 1024);
    $mime_ok = in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true);

    if (!$mime_ok || $ext !== 'pdf' || strpos($head, '%PDF-') === false) {
        throw new RuntimeException('File harus berformat PDF yang valid.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Folder uploads tidak bisa dibuat. Buat manual folder "uploads".');
    }
    if (!is_file(UPLOAD_DIR . '.htaccess')) {
        file_put_contents(UPLOAD_DIR . '.htaccess', "Require all denied\nphp_flag engine off\n");
    }

    $name = bin2hex(random_bytes(16)) . '.pdf';
    if (!@move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException('Gagal menyimpan file.');
    }
    return $name;
}

function hapus_pdf(?string $name): void {
    if ($name && preg_match('/^[a-f0-9]{32}\.pdf$/', $name) && is_file(UPLOAD_DIR . $name)) {
        unlink(UPLOAD_DIR . $name);
    }
}

function head(string $title): void { ?>
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
.b{display:inline-block;white-space:nowrap;padding:3px 8px;border-radius:12px;font-size:12px;color:#fff}td:nth-child(5){white-space:nowrap}.b1{background:#16a34a}.b2{background:#f59e0b}.b3{background:#dc2626}
.pg{margin-top:14px;display:flex;gap:6px;flex-wrap:wrap}.pg a{padding:6px 11px;background:#e5e7eb;border-radius:6px;text-decoration:none;color:#222;font-size:13px}.pg a.on{background:#2563eb;color:#fff}
.act{display:flex;gap:5px}.act form{margin:0}
</style></head><body><div class="wrap"><div class="card">
<?php }
function foot(): void { echo '</div></div></body></html>'; }
