<?php
require dirname(__FILE__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_status(405); exit; }
csrf_check();
$id = (int)ambil($_POST, 'id', 0);

$st = db()->prepare('SELECT file_pdf FROM mou_asuransi WHERE id = ?');
$st->execute(array($id));
$file = $st->fetchColumn();
if ($file) {
    db()->prepare('DELETE FROM mou_asuransi WHERE id = ?')->execute(array($id));
    hapus_pdf($file);
    flash('Data berhasil dihapus.');
}
header('Location: index.php');