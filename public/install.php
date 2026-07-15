<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (is_file(CONFIG_FILE) && is_file(DATA_DIR . '/movies.sqlite')) {
    header('Location: /login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = trim((string) ($_POST['tmdb_token'] ?? ''));
        $name1 = trim((string) ($_POST['name1'] ?? 'Szabi'));
        $user1 = trim((string) ($_POST['user1'] ?? 'szabi'));
        $pass1 = (string) ($_POST['pass1'] ?? '');
        $name2 = trim((string) ($_POST['name2'] ?? 'Barbi'));
        $user2 = trim((string) ($_POST['user2'] ?? 'barbi'));
        $pass2 = (string) ($_POST['pass2'] ?? '');

        if ($token === '' || $user1 === '' || $user2 === '' || strlen($pass1) < 6 || strlen($pass2) < 6) {
            throw new RuntimeException('Minden mező kötelező, a jelszó legalább 6 karakter legyen.');
        }
        if (strcasecmp($user1, $user2) === 0) {
            throw new RuntimeException('A két felhasználónév nem lehet azonos.');
        }

        if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0770, true) && !is_dir(DATA_DIR)) {
            throw new RuntimeException('A data könyvtár nem írható.');
        }

        $configContent = "<?php\nreturn " . var_export([
            'tmdb_token' => $token,
            'app_name' => 'Movie Night',
        ], true) . ";\n";
        if (file_put_contents(CONFIG_FILE, $configContent, LOCK_EX) === false) {
            throw new RuntimeException('A konfiguráció nem menthető. Ellenőrizd a jogosultságokat.');
        }
        chmod(CONFIG_FILE, 0640);

        $pdo = db();
        $pdo->exec((string) file_get_contents(__DIR__ . '/../src/schema.sql'));
        $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$user1, $name1 ?: $user1, password_hash($pass1, PASSWORD_DEFAULT)]);
        $stmt->execute([$user2, $name2 ?: $user2, password_hash($pass2, PASSWORD_DEFAULT)]);

        header('Location: /login.php?installed=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        @unlink(CONFIG_FILE);
        @unlink(DATA_DIR . '/movies.sqlite');
    }
}
?>
<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Telepítés</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="auth-page"><main class="auth-card wide"><h1>Movie Night telepítése</h1><p class="muted">Egyszer kell kitölteni. A TMDb API Read Access Tokent használd.</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="form-grid">
<label class="full">TMDb Read Access Token<input type="password" name="tmdb_token" required autocomplete="off"></label>
<fieldset><legend>Első felhasználó</legend><label>Megjelenő név<input name="name1" value="Szabi" required></label><label>Felhasználónév<input name="user1" value="szabi" required></label><label>Jelszó<input type="password" name="pass1" minlength="6" required></label></fieldset>
<fieldset><legend>Második felhasználó</legend><label>Megjelenő név<input name="name2" value="Barbi" required></label><label>Felhasználónév<input name="user2" value="barbi" required></label><label>Jelszó<input type="password" name="pass2" minlength="6" required></label></fieldset>
<button class="primary full" type="submit">Telepítés</button></form></main></body></html>
