<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$st = db()->prepare('SELECT 1 FROM mou_asuransi WHERE id = ?');
$st->execute([$id]);

if ($st->fetchColumn()) {
    $_SESSION['edit_id'] = $id;      // id disimpan di sesi, bukan di URL
    header('Location: form.php');
} else {
    flash('Data tidak ditemukan.');
    header('Location: index.php');
}
