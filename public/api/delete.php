<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php'; require_installation(); $user=require_login(true); verify_csrf(); $d=request_json(); $id=(int)($d['id']??0); $stmt=db()->prepare('DELETE FROM movies WHERE id=? AND group_id=?'); $stmt->execute([$id,(int)$user['group_id']]); if($stmt->rowCount()===0)json_response(['error'=>'A film nem található ezen a listán.'],404); json_response(['ok'=>true]);
