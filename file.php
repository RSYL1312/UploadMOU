<?php
require __DIR__ . '/config.php';

// Ambil file_pdf DAN nama_asuransi (dua-duanya diperlukan untuk penamaan)
$st = db()->prepare('SELECT file_pdf, nama_asuransi FROM mou_asuransi WHERE id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$row  = $st->fetch();
$name = $row['file_pdf'] ?? '';
$path = $name ? UPLOAD_DIR . $name : '';

if (!$name || !preg_match('/^[a-f0-9]{32}\.pdf$/', $name) || !is_file($path)) {
    http_response_code(404); exit('File tidak ditemukan.');
}

// Nama unduhan: "MOU - Nama Asuransi.pdf" (karakter berbahaya dibuang)
$bersih = trim(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $row['nama_asuransi']));
$unduh  = 'MOU - ' . ($bersih !== '' ? $bersih : 'Asuransi') . '.pdf';
$ascii  = preg_replace('/[^A-Za-z0-9 ._-]/', '_', $unduh);   // cadangan untuk browser lama

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($unduh));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);