<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) json_response(['results' => []]);
try {
    $data = tmdb_request('search/multi', ['query' => $q, 'language' => 'hu-HU', 'region' => 'HU', 'include_adult' => 'false', 'page' => 1]);
    $results = [];
    foreach ($data['results'] ?? [] as $m) {
        $type = (string)($m['media_type'] ?? '');
        if (!in_array($type, ['movie', 'tv'], true)) continue;
        $title = (string)($m['title'] ?? $m['name'] ?? $m['original_title'] ?? $m['original_name'] ?? 'Ismeretlen');
        $original = (string)($m['original_title'] ?? $m['original_name'] ?? '');
        $release = (string)($m['release_date'] ?? $m['first_air_date'] ?? '');
        $collection = null;
        if ($type === 'movie') {
            try {
                $details = tmdb_request('movie/' . (int)$m['id'], ['language' => 'hu-HU']);
                if (!empty($details['belongs_to_collection']['id'])) {
                    $collection = [
                        'id' => (int)$details['belongs_to_collection']['id'],
                        'name' => (string)($details['belongs_to_collection']['name'] ?? 'Filmsorozat'),
                    ];
                }
            } catch (Throwable) {}
        }
        $results[] = [
            'tmdb_id' => (int) $m['id'],
            'media_type' => $type,
            'title' => $title,
            'original_title' => $original,
            'release_date' => $release,
            'poster_path' => $m['poster_path'] ?? null,
            'poster_url' => poster_url($m['poster_path'] ?? null, 'w185'),
            'overview' => (string) ($m['overview'] ?? ''),
            'vote_average' => round((float) ($m['vote_average'] ?? 0), 1),
            'collection' => $collection,
        ];
        if (count($results) >= 8) break;
    }
    json_response(['results' => $results]);
} catch (Throwable $e) { json_response(['error' => $e->getMessage()], 502); }
