<?php
require __DIR__ . '/config.php';

// id tidak lagi dari URL: GET dari sesi (diset edit.php), POST dari field tersembunyi
$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int)($_POST['id'] ?? 0) : (int)($_SESSION['edit_id'] ?? 0);
$data = ['nama_asuransi' => '', 'tanggal_mulai' => '', 'tanggal_akhir' => '', 'file_asli' => ''];
if ($id) {
    $st = db()->prepare('SELECT * FROM mou_asuransi WHERE id = ?');
    $st->execute([$id]);
    $data = $st->fetch() ?: null;
    if (!$data) { http_response_code(404); exit('Data tidak ditemukan.'); }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama  = trim((string)($_POST['nama_asuransi'] ?? ''));
    $mulai = (string)($_POST['tanggal_mulai'] ?? '');
    $akhir = (string)($_POST['tanggal_akhir'] ?? '');
    $data = array_merge($data, ['nama_asuransi' => $nama, 'tanggal_mulai' => $mulai, 'tanggal_akhir' => $akhir]);
    $ada_file = isset($_FILES['file_pdf']) && $_FILES['file_pdf']['error'] !== UPLOAD_ERR_NO_FILE;
    $baru = null;

    try {
        if ($nama === '' || mb_strlen($nama) > 150) throw new RuntimeException('Nama asuransi wajib diisi (maks. 150 karakter).');
        if (!valid_date($mulai) || !valid_date($akhir)) throw new RuntimeException('Format tanggal tidak valid.');
        if ($akhir < $mulai) throw new RuntimeException('Tanggal akhir tidak boleh sebelum tanggal mulai.');
        if (!$id && !$ada_file) throw new RuntimeException('File PDF wajib diupload.');

        if ($ada_file) $baru = simpan_pdf($_FILES['file_pdf']);

        if ($id) {
            $lama = $data['file_pdf'] ?? db()->query('SELECT file_pdf FROM mou_asuransi WHERE id = ' . $id)->fetchColumn();
            if ($baru) {
                $sql = 'UPDATE mou_asuransi SET nama_asuransi=?, tanggal_mulai=?, tanggal_akhir=?, file_pdf=?, file_asli=? WHERE id=?';
                $par = [$nama, $mulai, $akhir, $baru, mb_substr(basename($_FILES['file_pdf']['name']), 0, 255), $id];
            } else {
                $sql = 'UPDATE mou_asuransi SET nama_asuransi=?, tanggal_mulai=?, tanggal_akhir=? WHERE id=?';
                $par = [$nama, $mulai, $akhir, $id];
            }
            db()->prepare($sql)->execute($par);
            if ($baru) hapus_pdf($lama);
            flash('Data berhasil diperbarui.');
        } else {
            db()->prepare('INSERT INTO mou_asuransi (nama_asuransi, tanggal_mulai, tanggal_akhir, file_pdf, file_asli) VALUES (?,?,?,?,?)')
              ->execute([$nama, $mulai, $akhir, $baru, mb_substr(basename($_FILES['file_pdf']['name']), 0, 255)]);
            flash('Data berhasil ditambahkan.');
        }
        unset($_SESSION['edit_id']);
        header('Location: index.php'); exit;
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
        if ($baru) hapus_pdf($baru);
    } catch (Throwable $ex) {
        error_log($ex->getMessage());
        if ($baru) hapus_pdf($baru);
        $error = 'Terjadi kesalahan pada server.';
    }
}

head($id ? 'Edit MOU' : 'Tambah MOU');
?>
<h1><?= $id ? 'Edit' : 'Tambah' ?> MOU Asuransi</h1>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <label>Nama Asuransi</label>
  <input type="text" name="nama_asuransi" maxlength="150" required value="<?= e($data['nama_asuransi']) ?>">
  <label>Tanggal Mulai MOU</label>
  <input type="date" name="tanggal_mulai" required value="<?= e($data['tanggal_mulai']) ?>">
  <label>Tanggal Akhir MOU</label>
  <input type="date" name="tanggal_akhir" required value="<?= e($data['tanggal_akhir']) ?>">
  <label>File MOU (PDF, maks. 5 MB)</label>
  <input type="file" name="file_pdf" accept="application/pdf,.pdf" <?= $id ? '' : 'required' ?>>
  <?php if ($id && $data['file_asli']): ?><small>File saat ini: <?= e($data['file_asli']) ?> (kosongkan jika tidak diganti)</small><?php endif; ?>
  <p><button class="btn">Simpan</button> <a class="btn g" href="index.php">Batal</a></p>
</form>
<?php foot();