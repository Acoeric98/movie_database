<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true); verify_csrf(); $d=request_json(); $id=(int)($d['id']??0);
$stmt=db()->prepare('DELETE FROM movies WHERE id=?'); $stmt->execute([$id]); json_response(['ok'=>true]);
