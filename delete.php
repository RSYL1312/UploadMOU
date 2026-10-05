<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();
$id = (int)($_POST['id'] ?? 0);

$st = db()->prepare('SELECT file_pdf FROM mou_asuransi WHERE id = ?');
$st->execute([$id]);
if ($file = $st->fetchColumn()) {
    db()->prepare('DELETE FROM mou_asuransi WHERE id = ?')->execute([$id]);
    hapus_pdf($file);
    flash('Data berhasil dihapus.');
}
header('Location: index.php');
