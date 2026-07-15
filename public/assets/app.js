'use strict';

const $ = (s) => document.querySelector(s);
const searchInput = $('#movieSearch');
const searchResults = $('#searchResults');
const spinner = $('#searchSpinner');
const grid = $('#movieGrid');
const emptyState = $('#emptyState');
const movieCount = $('#movieCount');
const localFilter = $('#localFilter');
const toast = $('#toast');
let searchTimer = null;
let searchAbort = null;
let currentStatus = 'all';
let movies = [];

function escapeHtml(value = '') {
  return String(value).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
}

function notify(message, type = 'ok') {
  toast.textContent = message;
  toast.className = `toast show ${type}`;
  setTimeout(() => toast.className = 'toast', 2800);
}

async function api(url, options = {}) {
  const headers = {'Accept': 'application/json', ...(options.headers || {})};
  if (options.body) {
    headers['Content-Type'] = 'application/json';
    headers['X-CSRF-Token'] = window.APP.csrf;
  }
  const response = await fetch(url, {...options, headers});
  const data = await response.json().catch(() => ({error: 'Érvénytelen szerverválasz.'}));
  if (!response.ok) throw new Error(data.error || `Hiba: ${response.status}`);
  return data;
}

function year(date) { return date ? date.slice(0, 4) : '–'; }
function poster(url, title) {
  return url
    ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(title)} posztere" loading="lazy">`
    : `<div class="poster-placeholder">🎞️</div>`;
}

searchInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  const q = searchInput.value.trim();
  if (q.length < 2) { searchResults.innerHTML = ''; return; }
  searchTimer = setTimeout(() => searchMovies(q), 350);
});

searchInput.addEventListener('keydown', e => {
  if (e.key === 'Escape') searchResults.innerHTML = '';
});

document.addEventListener('click', e => {
  if (!e.target.closest('.search-panel')) searchResults.innerHTML = '';
});

async function searchMovies(q) {
  if (searchAbort) searchAbort.abort();
  searchAbort = new AbortController();
  spinner.classList.remove('hidden');
  try {
    const response = await fetch(`/api/search.php?q=${encodeURIComponent(q)}`, {signal: searchAbort.signal});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Keresési hiba.');
    renderSearch(data.results || []);
  } catch (e) {
    if (e.name !== 'AbortError') notify(e.message, 'error');
  } finally {
    spinner.classList.add('hidden');
  }
}

function renderSearch(results) {
  if (!results.length) {
    searchResults.innerHTML = '<div class="no-result">Nincs találat.</div>';
    return;
  }
  searchResults.innerHTML = results.map(m => `
    <article class="search-item">
      <div class="search-poster">${poster(m.poster_url, m.title)}</div>
      <div class="search-info">
        <strong>${escapeHtml(m.title)}</strong>
        <span>${escapeHtml(m.original_title && m.original_title !== m.title ? m.original_title : '')}</span>
        <small>${year(m.release_date)} · TMDb ${m.vote_average || '–'}</small>
      </div>
      <button class="primary compact" data-add="${m.tmdb_id}">+ Hozzáadás</button>
    </article>`).join('');

  searchResults.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      await api('/api/add.php', {method: 'POST', body: JSON.stringify({tmdb_id: Number(btn.dataset.add)})});
      notify('Film hozzáadva a Megnézendő listához.');
      searchResults.innerHTML = '';
      searchInput.value = '';
      await loadMovies();
    } catch (e) { notify(e.message, 'error'); btn.disabled = false; }
  }));
}

async function loadMovies() {
  try {
    const data = await api(`/api/movies.php?status=${encodeURIComponent(currentStatus)}`);
    movies = data.movies || [];
    renderMovies();
  } catch (e) { notify(e.message, 'error'); }
}

function renderMovies() {
  const q = localFilter.value.trim().toLocaleLowerCase('hu-HU');
  const filtered = movies.filter(m => (`${m.title} ${m.original_title || ''}`).toLocaleLowerCase('hu-HU').includes(q));
  movieCount.textContent = `${filtered.length} film`;
  emptyState.classList.toggle('hidden', filtered.length > 0);
  grid.innerHTML = filtered.map(m => `
    <article class="movie-card" data-id="${m.id}">
      <div class="card-poster">${poster(m.poster_url, m.title)}<span class="status-badge ${m.status}">${statusText(m.status)}</span></div>
      <div class="card-body">
        <h3>${escapeHtml(m.title)}</h3>
        <div class="meta">${year(m.release_date)}${m.original_title && m.original_title !== m.title ? ` · ${escapeHtml(m.original_title)}` : ''}</div>
        <p class="overview">${escapeHtml(m.overview || 'Nincs magyar leírás.')}</p>
        <div class="added">Hozzáadta: ${escapeHtml(m.added_by_name)}</div>
        <label>Állapot<select data-status-select>
          <option value="watchlist" ${m.status === 'watchlist' ? 'selected' : ''}>Megnézendő</option>
          <option value="watched" ${m.status === 'watched' ? 'selected' : ''}>Megnézve</option>
          <option value="favorite" ${m.status === 'favorite' ? 'selected' : ''}>Kedvenc</option>
        </select></label>
        <div class="rating-row"><label>Saját pontszám<input data-rating type="number" min="1" max="10" placeholder="1–10" value="${m.my_rating || ''}"></label><span class="average">Közös átlag: <b>${m.avg_rating || '–'}</b></span></div>
        <label>Megjegyzésem<textarea data-note maxlength="1000" placeholder="Pl. hétvégén nézzük meg…">${escapeHtml(m.my_note || '')}</textarea></label>
        <div class="card-actions"><button class="primary compact" data-save>Mentés</button><button class="danger compact" data-delete>Törlés</button></div>
      </div>
    </article>`).join('');
  bindCards();
}

function statusText(status) {
  return ({watchlist:'Megnézendő', watched:'Megnézve', favorite:'Kedvenc'})[status] || status;
}

function bindCards() {
  grid.querySelectorAll('.movie-card').forEach(card => {
    const id = Number(card.dataset.id);
    card.querySelector('[data-status-select]').addEventListener('change', async e => {
      try { await updateMovie(id, {status: e.target.value}); notify('Állapot frissítve.'); await loadMovies(); }
      catch (err) { notify(err.message, 'error'); }
    });
    card.querySelector('[data-save]').addEventListener('click', async () => {
      const rating = card.querySelector('[data-rating]').value;
      const note = card.querySelector('[data-note]').value;
      try { await updateMovie(id, {rating: rating === '' ? null : Number(rating), note}); notify('Értékelés elmentve.'); await loadMovies(); }
      catch (err) { notify(err.message, 'error'); }
    });
    card.querySelector('[data-delete]').addEventListener('click', async () => {
      if (!confirm('Biztosan törlitek ezt a filmet a közös listáról?')) return;
      try { await api('/api/delete.php', {method:'POST', body:JSON.stringify({id})}); notify('Film törölve.'); await loadMovies(); }
      catch (err) { notify(err.message, 'error'); }
    });
  });
}

function updateMovie(id, data) {
  return api('/api/update.php', {method:'POST', body:JSON.stringify({id, ...data})});
}

document.querySelectorAll('.tabs [data-status]').forEach(btn => btn.addEventListener('click', async () => {
  document.querySelectorAll('.tabs [data-status]').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentStatus = btn.dataset.status;
  await loadMovies();
}));

localFilter.addEventListener('input', renderMovies);

$('#randomBtn').addEventListener('click', async () => {
  try {
    const {movie} = await api('/api/random.php');
    alert(`🎲 A mai film:\n\n${movie.title} (${year(movie.release_date)})`);
  } catch (e) { notify(e.message, 'error'); }
});

loadMovies();
