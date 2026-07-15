<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$type = (string)($_GET['type'] ?? 'movie');
if (!in_array($type, ['movie', 'tv'], true)) $type = 'movie';
try {
    $endpoint = $type === 'tv' ? 'tv/popular' : 'movie/popular';
    $data = tmdb_request($endpoint, ['language' => 'hu-HU', 'region' => 'HU', 'page' => 1]);
    $results = array_slice(array_map(static function (array $m) use ($type): array {
        return [
            'tmdb_id' => (int)$m['id'],
            'media_type' => $type,
            'title' => (string)($m['title'] ?? $m['name'] ?? $m['original_title'] ?? $m['original_name'] ?? 'Ismeretlen'),
            'original_title' => (string)($m['original_title'] ?? $m['original_name'] ?? ''),
            'release_date' => (string)($m['release_date'] ?? $m['first_air_date'] ?? ''),
            'poster_url' => poster_url($m['poster_path'] ?? null, 'w185'),
            'overview' => (string)($m['overview'] ?? ''),
            'vote_average' => round((float)($m['vote_average'] ?? 0), 1),
        ];
    }, $data['results'] ?? []), 0, 10);
    json_response(['results' => $results]);
} catch (Throwable $e) { json_response(['error' => $e->getMessage()], 502); }
