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
    if (!is_file(CONFIG_FILE)) return [];
    $config = require CONFIG_FILE;
    return is_array($config) ? $config : [];
}

function random_invite_code(PDO $pdo): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $stmt = $pdo->prepare('SELECT 1 FROM groups WHERE invite_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}


function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($rows as $row) if (($row['name'] ?? '') === $column) return true;
    return false;
}

function migrate_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        mode TEXT NOT NULL DEFAULT 'solo' CHECK(mode IN ('solo','couple')),
        invite_code TEXT UNIQUE,
        created_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        group_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL UNIQUE,
        role TEXT NOT NULL DEFAULT 'member' CHECK(role IN ('owner','member')),
        joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(group_id,user_id),
        FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $groupCount = (int)$pdo->query('SELECT COUNT(*) FROM groups')->fetchColumn();

    if ($userCount > 0 && $groupCount === 0) {
        $pdo->beginTransaction();
        try {
            $firstUser = (int)$pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
            $code = random_invite_code($pdo);
            $stmt = $pdo->prepare("INSERT INTO groups(name,mode,invite_code,created_by) VALUES('Közös filmlista','couple',?,?)");
            $stmt->execute([$code, $firstUser]);
            $groupId = (int)$pdo->lastInsertId();
            $users = $pdo->query('SELECT id FROM users ORDER BY id')->fetchAll();
            $member = $pdo->prepare('INSERT OR IGNORE INTO group_members(group_id,user_id,role) VALUES(?,?,?)');
            foreach ($users as $i => $u) $member->execute([$groupId, (int)$u['id'], $i === 0 ? 'owner' : 'member']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if (table_has_column($pdo, 'movies', 'group_id') === false) {
        $defaultGroup = (int)$pdo->query('SELECT id FROM groups ORDER BY id LIMIT 1')->fetchColumn();
        if ($defaultGroup > 0) {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->beginTransaction();
            try {
                $pdo->exec("CREATE TABLE movies_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    group_id INTEGER NOT NULL,
                    tmdb_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    original_title TEXT,
                    release_date TEXT,
                    poster_path TEXT,
                    overview TEXT,
                    status TEXT NOT NULL DEFAULT 'watchlist' CHECK(status IN ('watchlist','watched','favorite')),
                    added_by INTEGER NOT NULL,
                    added_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    watched_at TEXT,
                    UNIQUE(group_id,tmdb_id),
                    FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
                    FOREIGN KEY(added_by) REFERENCES users(id)
                )");
                $stmt = $pdo->prepare("INSERT INTO movies_new(id,group_id,tmdb_id,title,original_title,release_date,poster_path,overview,status,added_by,added_at,watched_at)
                    SELECT id,?,tmdb_id,title,original_title,release_date,poster_path,overview,status,added_by,added_at,watched_at FROM movies");
                $stmt->execute([$defaultGroup]);
                $pdo->exec('DROP TABLE movies');
                $pdo->exec('ALTER TABLE movies_new RENAME TO movies');
                $pdo->commit();
                $pdo->exec('PRAGMA foreign_keys = ON');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pdo->exec('PRAGMA foreign_keys = ON');
                throw $e;
            }
        }
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_movies_group_status ON movies(group_id,status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_movies_added_at ON movies(added_at)');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0770, true) && !is_dir(DATA_DIR)) throw new RuntimeException('A data könyvtár nem hozható létre.');
    $pdo = new PDO('sqlite:' . DATA_DIR . '/movies.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    if (is_file(DATA_DIR . '/movies.sqlite') && table_exists($pdo, 'users')) migrate_schema($pdo);
    return $pdo;
}

function require_installation(): void
{
    if (!is_file(CONFIG_FILE) || !is_file(DATA_DIR . '/movies.sqlite')) { header('Location: /install.php'); exit; }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT u.id,u.username,u.display_name,g.id AS group_id,g.name AS group_name,g.mode AS group_mode,g.invite_code,gm.role
        FROM users u JOIN group_members gm ON gm.user_id=u.id JOIN groups g ON g.id=gm.group_id WHERE u.id=?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(bool $json = false): array
{
    $user = current_user();
    if ($user) return $user;
    if ($json) json_response(['error' => 'Nincs bejelentkezve.'], 401);
    header('Location: /login.php'); exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) json_response(['error' => 'Érvénytelen biztonsági token. Frissítsd az oldalt.'], 419);
}
function request_json(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}
function tmdb_request(string $path, array $query = []): array
{
    $token = trim((string)(load_config()['tmdb_token'] ?? ''));
    if ($token === '') throw new RuntimeException('Nincs beállítva TMDb token.');
    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_USERAGENT=>'MovieNight/2.0']);
    $body = curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch); curl_close($ch);
    if ($body === false || $error !== '') throw new RuntimeException('A TMDb nem érhető el: '.$error);
    $decoded=json_decode($body,true);
    if ($status>=400 || !is_array($decoded)) throw new RuntimeException('TMDb hiba ('.$status.'). Ellenőrizd a tokent.');
    return $decoded;
}
function poster_url(?string $path, string $size='w342'): ?string { return $path ? 'https://image.tmdb.org/t/p/'.$size.$path : null; }
