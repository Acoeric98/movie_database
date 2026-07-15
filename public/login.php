<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_installation();
if (current_user()) { header('Location: /'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE');
    $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
    $user = $stmt->fetch();
    if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: /'); exit;
    }
    $error = 'Hibás felhasználónév vagy jelszó.';
}
?>
<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Belépés – Movie Night</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="auth-page"><main class="auth-card"><div class="logo">🎬</div><h1>Movie Night</h1><p class="muted">Közös filmlista</p>
<?php if (isset($_GET['installed'])): ?><div class="alert success">Telepítés kész. Jelentkezz be!</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post"><label>Felhasználónév<input name="username" autocomplete="username" required autofocus></label><label>Jelszó<input type="password" name="password" autocomplete="current-password" required></label><button class="primary" type="submit">Belépés</button></form></main></body></html>
