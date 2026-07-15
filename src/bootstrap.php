<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

const APP_ROOT = __DIR__ . '/..';
const DATA_DIR = APP_ROOT . '/data';
const CONFIG_FILE = DATA_DIR . '/config.php';

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_config(): array
{
    if (!is_file(CONFIG_FILE)) {
        return [];
    }
    $config = require CONFIG_FILE;
    return is_array($config) ? $config : [];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0770, true) && !is_dir(DATA_DIR)) {
        throw new RuntimeException('A data könyvtár nem hozható létre.');
    }

    $pdo = new PDO('sqlite:' . DATA_DIR . '/movies.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    return $pdo;
}

function require_installation(): void
{
    if (!is_file(CONFIG_FILE) || !is_file(DATA_DIR . '/movies.sqlite')) {
        header('Location: /install.php');
        exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, display_name FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(bool $json = false): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }
    if ($json) {
        json_response(['error' => 'Nincs bejelentkezve.'], 401);
    }
    header('Location: /login.php');
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        json_response(['error' => 'Érvénytelen biztonsági token. Frissítsd az oldalt.'], 419);
    }
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function tmdb_request(string $path, array $query = []): array
{
    $config = load_config();
    $token = trim((string) ($config['tmdb_token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Nincs beállítva TMDb token.');
    }

    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_USERAGENT => 'MovieNight/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('A TMDb nem érhető el: ' . $error);
    }
    $decoded = json_decode($body, true);
    if ($status >= 400 || !is_array($decoded)) {
        throw new RuntimeException('TMDb hiba (' . $status . '). Ellenőrizd a tokent.');
    }
    return $decoded;
}

function poster_url(?string $path, string $size = 'w342'): ?string
{
    return $path ? 'https://image.tmdb.org/t/p/' . $size . $path : null;
}
