<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php'; require_installation(); $user=require_login(true); $stmt=db()->prepare("SELECT *,'https://image.tmdb.org/t/p/w342'||poster_path AS poster_url FROM movies WHERE group_id=? AND status='watchlist' ORDER BY RANDOM() LIMIT 1");$stmt->execute([(int)$user['group_id']]);$m=$stmt->fetch();if(!$m)json_response(['error'=>'Nincs film a Megnézendő listán.'],404);json_response(['movie'=>$m]);
