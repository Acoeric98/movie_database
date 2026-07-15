'use strict';

const $ = (s) => document.querySelector(s);
const searchInput = $('#movieSearch');
const searchResults = $('#searchResults');
const spinner = $('#searchSpinner');
const grid = $('#movieGrid');
const emptyState = $('#emptyState');
const movieCount = $('#movieCount');
const localFilter = $('#localFilter');
const sortMovies = $('#sortMovies');
const popularGrid = $('#popularGrid');
const toast = $('#toast');
const detailsModal = $('#detailsModal');
const detailsBackdrop = $('#detailsBackdrop');
const detailsClose = $('#detailsClose');
const detailsBody = $('#detailsBody');
let searchTimer = null;
let searchAbort = null;
let currentStatus = 'all';
let popularType = 'movie';
let movies = [];

function escapeHtml(value = '') { return String(value).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch])); }
function notify(message, type = 'ok') { toast.textContent = message; toast.className = `toast show ${type}`; setTimeout(() => toast.className = 'toast', 2800); }
async function api(url, options = {}) { const headers = {'Accept': 'application/json', ...(options.headers || {})}; if (options.body) { headers['Content-Type'] = 'application/json'; headers['X-CSRF-Token'] = window.APP.csrf; } const response = await fetch(url, {...options, headers}); const data = await response.json().catch(() => ({error: 'Érvénytelen szerverválasz.'})); if (!response.ok) throw new Error(data.error || `Hiba: ${response.status}`); return data; }
function year(date) { return date ? date.slice(0, 4) : '–'; }
function mediaLabel(type) { return type === 'tv' ? 'Sorozat' : 'Film'; }
function poster(url, title) { return url ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(title)} posztere" loading="lazy">` : '<div class="poster-placeholder">🎞️</div>'; }
function displayTitles(m) { const original = m.original_title && m.original_title !== m.title ? `<span>Eredeti: ${escapeHtml(m.original_title)}</span>` : ''; return `<strong>${escapeHtml(m.title)}</strong>${original}`; }
function largePosterUrl(url) { return url ? url.replace('/t/p/w185', '/t/p/w500').replace('/t/p/w342', '/t/p/w500') : ''; }

searchInput.addEventListener('input', () => { clearTimeout(searchTimer); const q = searchInput.value.trim(); if (q.length < 2) { searchResults.innerHTML = ''; return; } searchTimer = setTimeout(() => searchMovies(q), 350); });
searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') searchResults.innerHTML = ''; });
document.addEventListener('click', e => { if (!e.target.closest('.search-panel')) searchResults.innerHTML = ''; });

async function searchMovies(q) {
  if (searchAbort) searchAbort.abort();
  searchAbort = new AbortController(); spinner.classList.remove('hidden');
  try { const response = await fetch(`/api/search.php?q=${encodeURIComponent(q)}`, {signal: searchAbort.signal}); const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Keresési hiba.'); renderSearch(data.results || []); }
  catch (e) { if (e.name !== 'AbortError') notify(e.message, 'error'); }
  finally { spinner.classList.add('hidden'); }
}

function renderSearch(results) {
  if (!results.length) { searchResults.innerHTML = '<div class="no-result">Nincs találat.</div>'; return; }
  searchResults.innerHTML = results.map(m => `
    <article class="search-item">
      <div class="search-poster">${poster(m.poster_url, m.title)}</div>
      <div class="search-info"><strong>${escapeHtml(m.title)}</strong><span>${escapeHtml(m.original_title && m.original_title !== m.title ? m.original_title : '')}</span><small>${mediaLabel(m.media_type)} · ${year(m.release_date)} · TMDb ${m.vote_average || '–'}</small></div>
      <div class="search-actions"><button class="primary compact" data-add="${m.tmdb_id}" data-type="${m.media_type}">+ Hozzáadás</button>${m.collection ? `<button class="ghost compact" data-collection="${m.collection.id}">Részek</button>` : ''}</div>
    </article>`).join('');
  searchResults.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', async () => addTitle(btn, Number(btn.dataset.add), btn.dataset.type || 'movie')));
  searchResults.querySelectorAll('[data-collection]').forEach(btn => btn.addEventListener('click', async () => { btn.disabled = true; try { await openCollectionPicker(Number(btn.dataset.collection)); } catch (e) { notify(e.message, 'error'); } finally { btn.disabled = false; } }));
}

async function addTitle(btn, tmdbId, mediaType) {
  btn.disabled = true;
  try { await api('/api/add.php', {method: 'POST', body: JSON.stringify({tmdb_id: tmdbId, media_type: mediaType})}); notify('Cím hozzáadva a Megnézendő listához.'); searchResults.innerHTML = ''; searchInput.value = ''; await loadMovies(); }
  catch (e) { notify(e.message, 'error'); btn.disabled = false; }
}

async function openCollectionPicker(collectionId) {
  const data = await api(`/api/collection.php?id=${collectionId}`);
  if (!data.parts?.length) { notify('Ehhez a filmsorozathoz nincs epizódlista.', 'error'); return; }
  const list = data.parts.map((p, i) => `${i + 1}. ${p.title} (${year(p.release_date)})`).join('\n');
  const all = confirm(`${data.name}\n\nÖsszes epizód hozzáadása?\n\nOK = összes epizód\nMégse = csak kiválasztott részek`);
  let ids = data.parts.map(p => p.tmdb_id);
  if (!all) { const chosen = prompt(`Írd be a hozzáadandó részek sorszámát vesszővel elválasztva:\n\n${list}`); if (!chosen) return; const indexes = chosen.split(',').map(v => Number(v.trim()) - 1).filter(i => i >= 0 && i < data.parts.length); ids = [...new Set(indexes)].map(i => data.parts[i].tmdb_id); if (!ids.length) { notify('Nem választottál érvényes részt.', 'error'); return; } }
  const res = await api('/api/add.php', {method: 'POST', body: JSON.stringify({tmdb_ids: ids, media_type: 'movie'})});
  notify(`${res.added || 0} rész hozzáadva a listához.`); searchResults.innerHTML = ''; searchInput.value = ''; await loadMovies();
}

async function loadPopular() {
  if (!popularGrid) return;
  popularGrid.innerHTML = '<div class="muted">Ajánlók betöltése…</div>';
  try {
    const data = await api(`/api/popular.php?type=${popularType}`);
    popularGrid.innerHTML = (data.results || []).map(m => `<article class="popular-card"><button class="popular-poster poster-button" data-popular-details="${m.tmdb_id}" aria-label="${escapeHtml(m.title)} borítójának nagyítása és részletei">${poster(m.poster_url, m.title)}</button><div class="popular-title">${displayTitles(m)}</div><small>${mediaLabel(m.media_type)} · ${year(m.release_date)} · TMDb ${m.vote_average || '–'}</small><p class="popular-overview">${escapeHtml(m.overview || 'Nincs elérhető leírás.')}</p><div class="popular-actions"><button class="ghost compact" data-popular-details="${m.tmdb_id}">Részletek</button><button class="primary compact" data-popular-add="${m.tmdb_id}" data-type="${m.media_type}">+ Lista</button></div></article>`).join('');
    popularGrid.querySelectorAll('[data-popular-add]').forEach(btn => btn.addEventListener('click', async () => addTitle(btn, Number(btn.dataset.popularAdd), btn.dataset.type)));
    popularGrid.querySelectorAll('[data-popular-details]').forEach(btn => btn.addEventListener('click', () => openDetails((data.results || []).find(m => Number(m.tmdb_id) === Number(btn.dataset.popularDetails)))));
  } catch (e) { popularGrid.innerHTML = `<div class="no-result">${escapeHtml(e.message)}</div>`; }
}


function openDetails(m) {
  if (!m || !detailsModal) return;
  const posterLarge = largePosterUrl(m.poster_url);
  detailsBody.innerHTML = `<div class="details-poster">${posterLarge ? `<img src="${escapeHtml(posterLarge)}" alt="${escapeHtml(m.title)} nagyított borítója">` : '<div class="poster-placeholder">🎞️</div>'}</div><div class="details-info"><div class="popular-title details-title">${displayTitles(m)}</div><div class="meta">${mediaLabel(m.media_type)} · ${year(m.release_date)} · TMDb ${m.vote_average || '–'}</div><p>${escapeHtml(m.overview || 'Nincs elérhető leírás.')}</p><button class="primary" data-modal-add="${m.tmdb_id}" data-type="${m.media_type}">+ Hozzáadás a listához</button></div>`;
  detailsModal.classList.remove('hidden');
  detailsModal.setAttribute('aria-hidden', 'false');
  detailsBody.querySelector('[data-modal-add]').addEventListener('click', async e => addTitle(e.currentTarget, Number(e.currentTarget.dataset.modalAdd), e.currentTarget.dataset.type));
}
function closeDetails() { if (!detailsModal) return; detailsModal.classList.add('hidden'); detailsModal.setAttribute('aria-hidden', 'true'); }
if (detailsClose) detailsClose.addEventListener('click', closeDetails);
if (detailsBackdrop) detailsBackdrop.addEventListener('click', closeDetails);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetails(); });

document.querySelectorAll('[data-popular-type]').forEach(btn => btn.addEventListener('click', async () => { document.querySelectorAll('[data-popular-type]').forEach(b => b.classList.remove('active')); btn.classList.add('active'); popularType = btn.dataset.popularType; await loadPopular(); }));

async function loadMovies() { try { const data = await api(`/api/movies.php?status=${encodeURIComponent(currentStatus)}`); movies = data.movies || []; renderMovies(); } catch (e) { notify(e.message, 'error'); } }
function renderMovies() {
  const q = localFilter.value.trim().toLocaleLowerCase('hu-HU');
  const filtered = movies
    .filter(m => (`${m.title} ${m.original_title || ''}`).toLocaleLowerCase('hu-HU').includes(q))
    .sort(movieSorter(sortMovies?.value || 'added_desc'));
  movieCount.textContent = `${filtered.length} cím`; emptyState.classList.toggle('hidden', filtered.length > 0);
  grid.innerHTML = filtered.map(m => `<article class="movie-card" data-id="${m.id}"><div class="card-poster">${poster(m.poster_url, m.title)}<span class="status-badge ${m.status}">${statusText(m.status)}</span></div><div class="card-body"><h3>${escapeHtml(m.title)}</h3><div class="meta">${mediaLabel(m.media_type)} · ${year(m.release_date)}${m.original_title && m.original_title !== m.title ? ` · ${escapeHtml(m.original_title)}` : ''}</div><p class="overview">${escapeHtml(m.overview || 'Nincs elérhető leírás.')}</p><div class="added">Hozzáadta: ${escapeHtml(m.added_by_name)}</div><label>Állapot<select data-status-select><option value="watchlist" ${m.status === 'watchlist' ? 'selected' : ''}>Megnézendő</option><option value="watched" ${m.status === 'watched' ? 'selected' : ''}>Megnézve</option><option value="favorite" ${m.status === 'favorite' ? 'selected' : ''}>Kedvenc</option></select></label><div class="rating-row"><label>Saját pontszám<input data-rating type="number" min="1" max="10" placeholder="1–10" value="${m.my_rating || ''}"></label><span class="average">Közös átlag: <b>${m.avg_rating || '–'}</b></span></div><label>Megjegyzésem<textarea data-note maxlength="1000" placeholder="Pl. hétvégén nézzük meg…">${escapeHtml(m.my_note || '')}</textarea></label><div class="card-actions"><button class="primary compact" data-save>Mentés</button><button class="danger compact" data-delete>Törlés</button></div></div></article>`).join('');
  bindCards();
}
function movieSorter(mode) {
  const textCompare = (a, b) => a.title.localeCompare(b.title, 'hu-HU', {sensitivity: 'base'});
  return (a, b) => {
    if (mode === 'title_asc') return textCompare(a, b);
    if (mode === 'year_desc') return (Number(year(b.release_date)) || 0) - (Number(year(a.release_date)) || 0) || textCompare(a, b);
    if (mode === 'rating_desc') return (Number(b.avg_rating) || 0) - (Number(a.avg_rating) || 0) || textCompare(a, b);
    return Number(b.id) - Number(a.id);
  };
}
function statusText(status) { return ({watchlist:'Megnézendő', watched:'Megnézve', favorite:'Kedvenc'})[status] || status; }
function bindCards() { grid.querySelectorAll('.movie-card').forEach(card => { const id = Number(card.dataset.id); card.querySelector('[data-status-select]').addEventListener('change', async e => { try { await updateMovie(id, {status: e.target.value}); notify('Állapot frissítve.'); await loadMovies(); } catch (err) { notify(err.message, 'error'); } }); card.querySelector('[data-save]').addEventListener('click', async () => { const rating = card.querySelector('[data-rating]').value; const note = card.querySelector('[data-note]').value; try { await updateMovie(id, {rating: rating === '' ? null : Number(rating), note}); notify('Értékelés elmentve.'); await loadMovies(); } catch (err) { notify(err.message, 'error'); } }); card.querySelector('[data-delete]').addEventListener('click', async () => { if (!confirm('Biztosan törlitek ezt a címet a közös listáról?')) return; try { await api('/api/delete.php', {method:'POST', body:JSON.stringify({id})}); notify('Cím törölve.'); await loadMovies(); } catch (err) { notify(err.message, 'error'); } }); }); }
function updateMovie(id, data) { return api('/api/update.php', {method:'POST', body:JSON.stringify({id, ...data})}); }
document.querySelectorAll('.tabs [data-status]').forEach(btn => btn.addEventListener('click', async () => { document.querySelectorAll('.tabs [data-status]').forEach(b => b.classList.remove('active')); btn.classList.add('active'); currentStatus = btn.dataset.status; await loadMovies(); }));
localFilter.addEventListener('input', renderMovies);
if (sortMovies) sortMovies.addEventListener('change', renderMovies);
$('#randomBtn').addEventListener('click', async () => { try { const {movie} = await api('/api/random.php'); alert(`🎲 A mai cím:\n\n${movie.title} (${year(movie.release_date)})`); } catch (e) { notify(e.message, 'error'); } });
loadMovies(); loadPopular();
