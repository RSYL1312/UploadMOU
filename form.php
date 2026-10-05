<?php
require dirname(__FILE__) . '/config.php';

// id tidak dari URL: GET dari sesi (diset edit.php), POST dari field tersembunyi
$id = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (int)ambil($_POST, 'id', 0) : (int)ambil($_SESSION, 'edit_id', 0);
$data = array('nama_asuransi' => '', 'tanggal_mulai' => '', 'tanggal_akhir' => '', 'file_asli' => '', 'file_pdf' => '');
if ($id) {
    $st = db()->prepare('SELECT * FROM mou_asuransi WHERE id = ?');
    $st->execute(array($id));
    $row = $st->fetch();
    if (!$row) { http_status(404); exit('Data tidak ditemukan.'); }
    $data = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama  = trim((string)ambil($_POST, 'nama_asuransi', ''));
    $mulai = (string)ambil($_POST, 'tanggal_mulai', '');
    $akhir = (string)ambil($_POST, 'tanggal_akhir', '');
    $data = array_merge($data, array('nama_asuransi' => $nama, 'tanggal_mulai' => $mulai, 'tanggal_akhir' => $akhir));
    $ada_file = isset($_FILES['file_pdf']) && $_FILES['file_pdf']['error'] !== UPLOAD_ERR_NO_FILE;
    $baru = null;

    try {
        if ($nama === '' || panjang($nama) > 150) throw new UserError('Nama asuransi wajib diisi (maks. 150 karakter).');
        if (!valid_date($mulai) || !valid_date($akhir)) throw new UserError('Format tanggal tidak valid.');
        if ($akhir < $mulai) throw new UserError('Tanggal akhir tidak boleh sebelum tanggal mulai.');
        if (!$id && !$ada_file) throw new UserError('File PDF wajib diupload.');

        if ($ada_file) $baru = simpan_pdf($_FILES['file_pdf']);
        $asli = $ada_file ? potong(basename($_FILES['file_pdf']['name']), 255) : '';

        if ($id) {
            $lama = $data['file_pdf'];
            if ($baru) {
                $sql = 'UPDATE mou_asuransi SET nama_asuransi=?, tanggal_mulai=?, tanggal_akhir=?, file_pdf=?, file_asli=? WHERE id=?';
                $par = array($nama, $mulai, $akhir, $baru, $asli, $id);
            } else {
                $sql = 'UPDATE mou_asuransi SET nama_asuransi=?, tanggal_mulai=?, tanggal_akhir=? WHERE id=?';
                $par = array($nama, $mulai, $akhir, $id);
            }
            db()->prepare($sql)->execute($par);
            if ($baru) hapus_pdf($lama);
            flash('Data berhasil diperbarui.');
        } else {
            db()->prepare('INSERT INTO mou_asuransi (nama_asuransi, tanggal_mulai, tanggal_akhir, file_pdf, file_asli) VALUES (?,?,?,?,?)')
              ->execute(array($nama, $mulai, $akhir, $baru, $asli));
            flash('Data berhasil ditambahkan.');
        }
        unset($_SESSION['edit_id']);
        header('Location: index.php'); exit;
    } catch (UserError $ex) {
        $error = $ex->getMessage();
        if ($baru) hapus_pdf($baru);
    } catch (Exception $ex) {
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