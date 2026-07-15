<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); $user = require_login(true);
$status = (string) ($_GET['status'] ?? 'all');
$sql = "SELECT m.*, u.display_name AS added_by_name,
        ROUND(AVG(r.rating),1) AS avg_rating,
        MAX(CASE WHEN r.user_id = :uid1 THEN r.rating END) AS my_rating,
        MAX(CASE WHEN r.user_id = :uid2 THEN r.note END) AS my_note,
        COUNT(r.rating) AS rating_count
        FROM movies m JOIN users u ON u.id=m.added_by LEFT JOIN ratings r ON r.movie_id=m.id";
$params = [':uid1' => (int)$user['id'], ':uid2' => (int)$user['id']];
if (in_array($status, ['watchlist','watched','favorite'], true)) { $sql .= ' WHERE m.status=:status'; $params[':status']=$status; }
$sql .= ' GROUP BY m.id ORDER BY CASE m.status WHEN "favorite" THEN 1 WHEN "watchlist" THEN 2 ELSE 3 END, m.added_at DESC';
$stmt=db()->prepare($sql); $stmt->execute($params); $movies=$stmt->fetchAll();
foreach($movies as &$m){$m['poster_url']=poster_url($m['poster_path'] ?: null);}
json_response(['movies'=>$movies]);
