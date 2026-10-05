<?php
require dirname(__FILE__) . '/config.php';
unset($_SESSION['edit_id']);   // kembali ke daftar = keluar dari mode edit

// Kondisi tabel (cari/sortir/baris/halaman) disimpan di sesi, bukan di URL
$ajax = isset($_GET['ajax']);
if (!$ajax && $_GET) { header('Location: index.php'); exit; }   // bersihkan URL
$src = $ajax ? $_GET : (isset($_SESSION['tabel']) ? $_SESSION['tabel'] : array());

$LIMITS = array(10, 25, 50, 100);
// Kunci => kolom database (whitelist, mencegah SQL Injection lewat ORDER BY)
$SORTS = array(
    'nama'   => 'nama_asuransi',
    'mulai'  => 'tanggal_mulai',
    'akhir'  => 'tanggal_akhir',
    'status' => 'tanggal_akhir',   // status dihitung dari tanggal akhir
    'pdf'    => 'file_asli'
);

$q = trim((string)ambil($src, 'q', ''));
$limit = (int)ambil($src, 'limit', PER_PAGE);
if (!in_array($limit, $LIMITS, true)) $limit = PER_PAGE;
$sort = (string)ambil($src, 'sort', 'akhir');
if (!isset($SORTS[$sort])) $sort = 'akhir';
$dir = (ambil($src, 'dir', 'asc') === 'desc') ? 'desc' : 'asc';
$page = max(1, (int)ambil($src, 'page', 1));
$where = $q !== '' ? 'WHERE nama_asuransi LIKE :q' : '';
$like = '%' . addcslashes($q, '%_\\') . '%';

$st = db()->prepare("SELECT COUNT(*) FROM mou_asuransi $where");
if ($q !== '') $st->bindValue(':q', $like);
$st->execute();
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
if ($ajax) $_SESSION['tabel'] = compact('q', 'limit', 'sort', 'dir', 'page');

$order = $SORTS[$sort] . ' ' . strtoupper($dir) . ', id DESC';   // aman: dari whitelist
$st = db()->prepare("SELECT * FROM mou_asuransi $where ORDER BY $order LIMIT :l OFFSET :o");
if ($q !== '') $st->bindValue(':q', $like);
$st->bindValue(':l', $limit, PDO::PARAM_INT);
$st->bindValue(':o', ($page - 1) * $limit, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();
$today = new DateTime('today');

function url_tabel($p, $s, $d, $q, $limit) {
    return '?page=' . $p . '&q=' . urlencode($q) . '&limit=' . $limit . '&sort=' . $s . '&dir=' . $d;
}

function tabel(array $rows, $page, $pages, $q, $limit, $total, $sort, $dir, DateTime $today) {
    $awal = ($page - 1) * $limit;
    $kolom = array('nama' => 'Nama Asuransi', 'mulai' => 'Mulai', 'akhir' => 'Akhir', 'status' => 'Status', 'pdf' => 'PDF'); ?>
<div class="tbl" data-sort="<?= e($sort) ?>" data-dir="<?= e($dir) ?>"><table>
<tr>
  <th>No</th>
<?php foreach ($kolom as $k => $judul):
    $aktif = ($k === $sort);
    $next = ($aktif && $dir === 'asc') ? 'desc' : 'asc'; ?>
  <th><a class="sort" href="<?= e(url_tabel(1, $k, $next, $q, $limit)) ?>"><?= $judul ?> <span class="arr <?= $aktif ? 'on' : '' ?>"><?= $aktif ? ($dir === 'asc' ? '▲' : '▼') : '⇅' ?></span></a></th>
<?php endforeach; ?>
  <th>Aksi</th>
</tr>
<?php if (!$rows): ?><tr><td colspan="7">Data tidak ditemukan.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r):
    $sisa = (int)$today->diff(new DateTime($r['tanggal_akhir']))->format('%r%a');
    if ($sisa < 0)       { $cls = 'b3'; $lbl = 'Berakhir'; }
    elseif ($sisa <= 30) { $cls = 'b2'; $lbl = 'Sisa ' . $sisa . ' hari'; }
    else                 { $cls = 'b1'; $lbl = 'Aktif'; }
?>
<tr>
  <td><?= $awal + $i + 1 ?></td>
  <td><?= e($r['nama_asuransi']) ?></td>
  <td><?= tgl($r['tanggal_mulai']) ?></td>
  <td><?= tgl($r['tanggal_akhir']) ?></td>
  <td><span class="b <?= $cls ?>"><?= e($lbl) ?></span></td>
  <td><a href="file.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener"><?= e($r['file_asli']) ?></a></td>
  <td><div class="act">
    <form method="post" action="edit.php">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn s g">Edit</button>
    </form>
    <form method="post" action="delete.php" data-confirm="Hapus data ini?">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn s r">Hapus</button>
    </form></div></td>
</tr>
<?php endforeach; ?>
</table></div>
<p class="info"><?= $total ? 'Menampilkan ' . ($awal + 1) . '–' . ($awal + count($rows)) . ' dari ' . $total . ' data' : '0 data' ?></p>
<?php if ($pages > 1):
    // Tampilkan: 1 ... (halaman-2 .. halaman+2) ... terakhir
    $tampil = array(1, $pages);
    for ($x = $page - 2; $x <= $page + 2; $x++) { if ($x >= 1 && $x <= $pages) $tampil[] = $x; }
    $tampil = array_unique($tampil); sort($tampil); $prev = 0; ?>
<div class="pg">
<?php if ($page > 1): ?><a href="<?= e(url_tabel($page - 1, $sort, $dir, $q, $limit)) ?>">&laquo;</a><?php endif; ?>
<?php foreach ($tampil as $p): if ($p - $prev > 1) echo '<span class="dots">…</span>'; $prev = $p; ?>
  <a class="<?= $p === $page ? 'on' : '' ?>" href="<?= e(url_tabel($p, $sort, $dir, $q, $limit)) ?>"><?= $p ?></a>
<?php endforeach; ?>
<?php if ($page < $pages): ?><a href="<?= e(url_tabel($page + 1, $sort, $dir, $q, $limit)) ?>">&raquo;</a><?php endif; ?>
</div><?php endif;
}

if ($ajax) { tabel($rows, $page, $pages, $q, $limit, $total, $sort, $dir, $today); exit; }

head('Daftar MOU');
?>
<style>
.info{font-size:13px;color:#666;margin:12px 0 0}.dots{padding:6px 4px;color:#888}.sel{width:auto!important}
th a.sort{color:inherit;text-decoration:none;white-space:nowrap;display:inline-block}
th a.sort:hover{color:#2563eb}.arr{font-size:11px;color:#aaa}.arr.on{color:#2563eb}
</style>
<h1>Data MOU Asuransi</h1>
<?php if ($m = flash()): ?><div class="alert ok"><?= e($m) ?></div><?php endif; ?>
<div class="bar">
  <form method="get" id="form-cari">
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Ketik nama asuransi..." autocomplete="off" autofocus>
    <select id="limit" name="limit" class="sel" style="padding:9px;border:1px solid #ccc;border-radius:6px">
      <?php foreach ($LIMITS as $n): ?><option value="<?= $n ?>" <?= $n === $limit ? 'selected' : '' ?>><?= $n ?> baris</option><?php endforeach; ?>
    </select>
  </form>
  <a class="btn" href="form.php">+ Tambah MOU</a>
</div>
<div id="hasil"><?php tabel($rows, $page, $pages, $q, $limit, $total, $sort, $dir, $today); ?></div>
<script src="search.js"></script>
<?php foot();