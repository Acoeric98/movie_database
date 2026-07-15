<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) json_response(['results' => []]);
try {
    $data = tmdb_request('search/movie', ['query' => $q, 'language' => 'hu-HU', 'region' => 'HU', 'include_adult' => 'false', 'page' => 1]);
    $results = array_slice(array_map(static function (array $m): array {
        return [
            'tmdb_id' => (int) $m['id'],
            'title' => (string) ($m['title'] ?? $m['original_title'] ?? 'Ismeretlen'),
            'original_title' => (string) ($m['original_title'] ?? ''),
            'release_date' => (string) ($m['release_date'] ?? ''),
            'poster_path' => $m['poster_path'] ?? null,
            'poster_url' => poster_url($m['poster_path'] ?? null, 'w185'),
            'overview' => (string) ($m['overview'] ?? ''),
            'vote_average' => round((float) ($m['vote_average'] ?? 0), 1),
        ];
    }, $data['results'] ?? []), 0, 8);
    json_response(['results' => $results]);
} catch (Throwable $e) { json_response(['error' => $e->getMessage()], 502); }
