<?php
require dirname(__FILE__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_status(405); exit; }
csrf_check();

$id = (int)ambil($_POST, 'id', 0);
$st = db()->prepare('SELECT 1 FROM mou_asuransi WHERE id = ?');
$st->execute(array($id));

if ($st->fetchColumn()) {
    $_SESSION['edit_id'] = $id;      // id disimpan di sesi, bukan di URL
    header('Location: form.php');
} else {
    flash('Data tidak ditemukan.');
    header('Location: index.php');
}