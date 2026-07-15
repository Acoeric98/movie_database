<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_installation();
$user = require_login();
$config = load_config();
?>
<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($config['app_name'] ?? 'Movie Night') ?></title><link rel="stylesheet" href="/assets/style.css"></head>
<body><header class="topbar"><div><h1>🎬 Movie Night</h1><span class="muted">Sziasztok, <?= htmlspecialchars($user['display_name']) ?>!</span></div><a class="ghost" href="/logout.php">Kilépés</a></header>
<main class="container">
<section class="search-panel"><h2>Mit nézzünk meg?</h2><div class="search-wrap"><input id="movieSearch" type="search" placeholder="Kezdd el írni a film címét magyarul vagy angolul…" autocomplete="off"><span id="searchSpinner" class="spinner hidden"></span></div><div id="searchResults" class="search-results"></div></section>
<nav class="tabs"><button data-status="all" class="active">Összes</button><button data-status="watchlist">Megnézendő</button><button data-status="watched">Megnézve</button><button data-status="favorite">Kedvencek</button><button id="randomBtn" class="random">🎲 Mit nézzünk ma?</button></nav>
<section class="toolbar"><input id="localFilter" type="search" placeholder="Keresés a mentett filmek között…"><span id="movieCount"></span></section>
<section id="movieGrid" class="movie-grid"></section>
<div id="emptyState" class="empty hidden"><div>🍿</div><h3>Még nincs itt film</h3><p>Keress rá fent, és add hozzá a listához.</p></div>
</main>
<div id="toast" class="toast"></div>
<script>window.APP={csrf:<?= json_encode(csrf_token()) ?>,userId:<?= (int)$user['id'] ?>};</script><script src="/assets/app.js"></script></body></html>
