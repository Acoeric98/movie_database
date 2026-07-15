<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_installation();
if (current_user()) { header('Location: /'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $username=mb_strtolower(trim((string)($_POST['username']??'')));
    $display=trim((string)($_POST['display_name']??''));
    $password=(string)($_POST['password']??'');
    $mode=(string)($_POST['mode']??'solo');
    $invite=strtoupper(trim((string)($_POST['invite_code']??'')));
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/',$username)) $error='A felhasználónév 3–30 karakter legyen, és csak betűt, számot, pontot, kötőjelet vagy aláhúzást tartalmazzon.';
    elseif (mb_strlen($display)<2 || mb_strlen($display)>40) $error='A megjelenített név 2–40 karakter legyen.';
    elseif (strlen($password)<6) $error='A jelszó legalább 6 karakter legyen.';
    elseif (!in_array($mode,['solo','couple','join'],true)) $error='Érvénytelen használati mód.';
    if ($error==='') {
        $pdo=db();
        try {
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('INSERT INTO users(username,display_name,password_hash) VALUES(?,?,?)');
            $stmt->execute([$username,$display,password_hash($password,PASSWORD_DEFAULT)]);
            $uid=(int)$pdo->lastInsertId();
            if ($mode==='join') {
                $stmt=$pdo->prepare("SELECT g.id FROM groups g WHERE g.invite_code=? AND g.mode='couple' AND (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id=g.id)<2");
                $stmt->execute([$invite]);
                $gid=(int)($stmt->fetchColumn()?:0);
                if ($gid<1) throw new RuntimeException('A meghívókód hibás, lejárt, vagy a páros lista már megtelt.');
                $pdo->prepare("INSERT INTO group_members(group_id,user_id,role) VALUES(?,?,'member')")->execute([$gid,$uid]);
            } else {
                $groupName=$mode==='solo' ? $display.' listája' : $display.' közös listája';
                $code=$mode==='couple' ? random_invite_code($pdo) : null;
                $stmt=$pdo->prepare('INSERT INTO groups(name,mode,invite_code,created_by) VALUES(?,?,?,?)');
                $stmt->execute([$groupName,$mode,$code,$uid]);
                $gid=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO group_members(group_id,user_id,role) VALUES(?,?,'owner')")->execute([$gid,$uid]);
            }
            $pdo->commit();
            session_regenerate_id(true); $_SESSION['user_id']=$uid;
            header('Location: /'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error=str_contains($e->getMessage(),'UNIQUE') ? 'Ez a felhasználónév már foglalt.' : $e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Regisztráció – Movie Night</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="auth-page"><main class="auth-card wide"><div class="logo">🎬</div><h1>Regisztráció</h1><p class="muted">Saját vagy közös filmlista létrehozása</p>
<?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="post" id="registerForm"><label>Megjelenített név<input name="display_name" value="<?=htmlspecialchars((string)($_POST['display_name']??''))?>" required maxlength="40"></label><label>Felhasználónév<input name="username" value="<?=htmlspecialchars((string)($_POST['username']??''))?>" required maxlength="30" autocomplete="username"></label><label>Jelszó<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
<fieldset class="mode-picker"><legend>Hogyan használod?</legend>
<label class="mode-card"><input type="radio" name="mode" value="solo" <?=($_POST['mode']??'solo')==='solo'?'checked':''?>><span><strong>Egyedül</strong><small>Saját, külön filmlista</small></span></label>
<label class="mode-card"><input type="radio" name="mode" value="couple" <?=($_POST['mode']??'')==='couple'?'checked':''?>><span><strong>Párban</strong><small>Új közös lista és meghívókód</small></span></label>
<label class="mode-card"><input type="radio" name="mode" value="join" <?=($_POST['mode']??'')==='join'?'checked':''?>><span><strong>Csatlakozás</strong><small>Meglévő pár meghívókódjával</small></span></label>
</fieldset>
<label id="inviteField" class="hidden">Meghívókód<input name="invite_code" value="<?=htmlspecialchars((string)($_POST['invite_code']??''))?>" maxlength="12" placeholder="Például: A1B2C3D4"></label>
<button class="primary" type="submit">Fiók létrehozása</button></form><p class="auth-switch">Már van fiókod? <a href="/login.php">Belépés</a></p></main>
<script>const f=document.getElementById('inviteField');function sync(){const v=document.querySelector('input[name=mode]:checked').value;f.classList.toggle('hidden',v!=='join');f.querySelector('input').required=v==='join';}document.querySelectorAll('input[name=mode]').forEach(x=>x.addEventListener('change',sync));sync();</script></body></html>
