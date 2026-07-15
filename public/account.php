<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php'; require_installation(); $user=require_login();
$pdo=db(); $success=''; $error='';
$stmt=$pdo->prepare('SELECT u.display_name,u.username,g.*,gm.role,(SELECT COUNT(*) FROM group_members WHERE group_id=g.id) member_count FROM users u JOIN group_members gm ON gm.user_id=u.id JOIN groups g ON g.id=gm.group_id WHERE u.id=?');
$stmt->execute([(int)$user['id']]); $info=$stmt->fetch();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals(csrf_token(),(string)($_POST['csrf']??''))) $error='Érvénytelen biztonsági token.';
    else {
        $action=(string)($_POST['action']??'');
        try {
            if ($action==='enable_couple' && $info['role']==='owner' && (int)$info['member_count']===1) {
                $code=random_invite_code($pdo); $pdo->prepare("UPDATE groups SET mode='couple',invite_code=? WHERE id=?")->execute([$code,(int)$info['id']]); $success='Páros mód bekapcsolva.';
            } elseif ($action==='new_code' && $info['role']==='owner' && $info['mode']==='couple') {
                $code=random_invite_code($pdo); $pdo->prepare('UPDATE groups SET invite_code=? WHERE id=?')->execute([$code,(int)$info['id']]); $success='Új meghívókód elkészült.';
            } else throw new RuntimeException('Ez a művelet most nem végezhető el.');
            $stmt->execute([(int)$user['id']]); $info=$stmt->fetch();
        } catch(Throwable $e){$error=$e->getMessage();}
    }
}
$members=$pdo->prepare('SELECT u.display_name,u.username,gm.role FROM group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=? ORDER BY gm.joined_at'); $members->execute([(int)$info['id']]);
?>
<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fiók – Movie Night</title><link rel="stylesheet" href="/assets/style.css"></head><body><header class="topbar"><div><h1>⚙️ Fiók és lista</h1><span class="muted"><?=htmlspecialchars($info['display_name'])?></span></div><a class="ghost" href="/">Vissza</a></header><main class="container narrow">
<?php if($success):?><div class="alert success"><?=htmlspecialchars($success)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="settings-card"><h2><?=htmlspecialchars($info['name'])?></h2><p><strong>Mód:</strong> <?=$info['mode']==='couple'?'Páros':'Egyéni'?></p><h3>Tagok</h3><div class="member-list"><?php foreach($members as $m):?><div><span><?=htmlspecialchars($m['display_name'])?> <small>@<?=htmlspecialchars($m['username'])?></small></span><b><?=$m['role']==='owner'?'Tulajdonos':'Tag'?></b></div><?php endforeach;?></div>
<?php if($info['mode']==='couple'):?><div class="invite-box"><span>Meghívókód</span><strong><?=htmlspecialchars((string)$info['invite_code'])?></strong><small>A másik fél ezt adja meg regisztrációkor. Legfeljebb 2 tag lehet.</small></div><?php if($info['role']==='owner'):?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="new_code"><button class="ghost" type="submit">Új kód generálása</button></form><?php endif;?>
<?php elseif($info['role']==='owner'):?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="enable_couple"><button class="primary" type="submit">Átváltás páros módra</button></form><?php endif;?></section></main></body></html>
