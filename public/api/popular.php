<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$type = (string)($_GET['type'] ?? 'movie');
if (!in_array($type, ['movie', 'tv', 'random'], true)) $type = 'movie';

function popular_item(array $m, string $type): array {
    $id = (int)$m['id'];
    $fallback = [];
    if (empty($m['overview'])) {
        try { $fallback = tmdb_request($type . '/' . $id, ['language' => 'en-US']); } catch (Throwable) { $fallback = []; }
    }
    $title = (string)($m['title'] ?? $m['name'] ?? '');
    $englishTitle = (string)($fallback['title'] ?? $fallback['name'] ?? '');
    $original = (string)($m['original_title'] ?? $m['original_name'] ?? $fallback['original_title'] ?? $fallback['original_name'] ?? '');
    if ($title === '' || ($title === $original && $englishTitle !== '' && $englishTitle !== $original)) {
        $title = $englishTitle;
    }
    if ($title === '') $title = $original !== '' ? $original : 'Ismeretlen';

    return [
        'tmdb_id' => $id,
        'media_type' => $type,
        'title' => $title,
        'original_title' => $original,
        'release_date' => (string)($m['release_date'] ?? $m['first_air_date'] ?? $fallback['release_date'] ?? $fallback['first_air_date'] ?? ''),
        'poster_url' => poster_url($m['poster_path'] ?? $fallback['poster_path'] ?? null, 'w185'),
        'overview' => (string)($m['overview'] ?? $fallback['overview'] ?? ''),
        'vote_average' => round((float)($m['vote_average'] ?? $fallback['vote_average'] ?? 0), 1),
    ];
}

function popular_source(string $type, int $page): array {
    $endpoint = $type === 'tv' ? 'tv/popular' : 'movie/popular';
    return tmdb_request($endpoint, ['language' => 'hu-HU', 'region' => 'HU', 'page' => $page]);
}

try {
    if ($type === 'random') {
        $results = [];
        foreach (['movie', 'tv'] as $mediaType) {
            $data = popular_source($mediaType, random_int(1, 20));
            foreach (array_slice($data['results'] ?? [], 0, 8) as $item) {
                $results[] = popular_item($item, $mediaType);
            }
        }
        shuffle($results);
        $results = array_slice($results, 0, 10);
    } else {
        $data = popular_source($type, 1);
        $results = array_slice(array_map(static fn (array $m): array => popular_item($m, $type), $data['results'] ?? []), 0, 10);
    }
    json_response(['results' => $results]);
} catch (Throwable $e) { json_response(['error' => $e->getMessage()], 502); }
