<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$m=db()->query("SELECT *, 'https://image.tmdb.org/t/p/w342'||poster_path AS poster_url FROM movies WHERE status='watchlist' ORDER BY RANDOM() LIMIT 1")->fetch();
if(!$m) json_response(['error'=>'Nincs film a Megnézendő listán.'],404); json_response(['movie'=>$m]);
