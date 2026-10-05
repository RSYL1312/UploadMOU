(() => {
  const input = document.getElementById('q');
  const limit = document.getElementById('limit');
  const form  = document.getElementById('form-cari');
  const box   = document.getElementById('hasil');
  let timer, ctrl;

  // Urutan aktif dibaca dari tabel yang sedang tampil
  const urlBaru = () => {
    const t = box.querySelector('.tbl');
    const sort = t ? t.dataset.sort : 'akhir';
    const dir  = t ? t.dataset.dir : 'asc';
    return 'index.php?q=' + encodeURIComponent(input.value.trim()) +
           '&limit=' + limit.value + '&sort=' + sort + '&dir=' + dir;
  };

  function muat(url) {
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    box.style.opacity = '.5';
    const sep = url.includes('?') ? '&' : '?';
    fetch(url + sep + 'ajax=1', { signal: ctrl.signal })
      .then(r => r.text())
      .then(html => { box.innerHTML = html; box.style.opacity = '1'; })
      .catch(err => { if (err.name !== 'AbortError') box.style.opacity = '1'; });
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => muat(urlBaru()), 300);
  });
  limit.addEventListener('change', () => muat(urlBaru()));
  form.addEventListener('submit', e => e.preventDefault());

  // Klik pagination atau judul kolom (sortir) tanpa reload halaman
  box.addEventListener('click', e => {
    const a = e.target.closest('.pg a, th a.sort');
    if (a) { e.preventDefault(); muat(a.href); }
  });

  document.addEventListener('submit', e => {
    const f = e.target.closest('form[data-confirm]');
    if (f && !confirm(f.dataset.confirm)) e.preventDefault();
  });
})();