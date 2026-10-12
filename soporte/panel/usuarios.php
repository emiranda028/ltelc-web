<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
$me = require_staff();
if ($me['role'] !== 'admin') { http_response_code(403); page_head('Sin permiso', 'panel'); echo '<div class="card"><h1>Solo administradores</h1></div>'; page_foot(); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['accion'] ?? '';
    if ($action === 'crear') {
        $name = trim((string)($_POST['nombre'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['clave'] ?? '');
        $role = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'agente';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Completá nombre y un mail válido.', 'error');
        elseif (mb_strlen($pass) < 10) flash('La contraseña tiene que tener al menos 10 caracteres.', 'error');
        elseif (q1('SELECT id FROM users WHERE email = ?', [$email])) flash('Ya existe un usuario con ese mail.', 'error');
        else {
            q('INSERT INTO users (name, email, pass_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, now()]);
            flash("Creamos el usuario de $name.");
        }
    } else {
        $u = q1('SELECT * FROM users WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($u && $action === 'activo' && (int)$u['id'] !== (int)$me['id']) {
            q('UPDATE users SET active = 1 - active WHERE id = ?', [$u['id']]);
            flash($u['active'] ? "Desactivamos a {$u['name']}." : "Reactivamos a {$u['name']}.");
        } elseif ($u && $action === 'clave') {
            $pass = (string)($_POST['clave'] ?? '');
            if (mb_strlen($pass) < 10) flash('La contraseña tiene que tener al menos 10 caracteres.', 'error');
            else { q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]); flash("Cambiamos la contraseña de {$u['name']}."); }
        }
    }
    redirect('panel/usuarios.php');
}

$users = q('SELECT u.*, (SELECT COUNT(*) FROM tickets t WHERE t.assignee_id = u.id AND t.status IN (\'nuevo\',\'abierto\',\'esperando\')) AS abiertos FROM users u ORDER BY u.active DESC, u.name')->fetchAll();
page_head('Equipo', 'panel'); ?>
<h1>Equipo de <b>soporte</b></h1>
<p class="lead">Las personas activas reciben los avisos de reclamos nuevos por mail (en horario de atención; los urgentes, siempre).</p>
<div class="table-wrap" style="margin-bottom:20px">
  <table class="list" style="min-width:720px"><thead><tr><th>Nombre</th><th>Mail</th><th>Rol</th><th>Abiertos</th><th>Estado</th><th>Nueva contraseña</th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
    <tr><td><b><?= h($u['name']) ?></b></td><td><?= h($u['email']) ?></td><td><?= $u['role'] === 'admin' ? 'Administrador' : 'Agente' ?></td><td class="num"><?= (int)$u['abiertos'] ?></td>
      <td><?php if ((int)$u['id'] === (int)$me['id']): ?><small>Vos</small><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn small line" name="accion" value="activo"><?= $u['active'] ? 'Desactivar' : 'Reactivar' ?></button></form><?php endif; ?></td>
      <td><form method="post" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="password" name="clave" placeholder="Mín. 10 caracteres" autocomplete="new-password" style="padding:7px 10px" aria-label="Nueva contraseña"><button class="btn small line" name="accion" value="clave">Cambiar</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<form class="card" method="post" style="max-width:720px"><?= csrf_field() ?>
  <h2 style="margin-bottom:14px">Sumar a alguien</h2>
  <div class="grid2">
    <div class="field"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" required></div>
    <div class="field"><label for="email">Mail</label><input type="email" id="email" name="email" required></div>
  </div>
  <div class="grid2">
    <div class="field"><label for="clave">Contraseña inicial</label><input type="password" id="clave" name="clave" autocomplete="new-password" required></div>
    <div class="field"><label for="rol">Rol</label><select id="rol" name="rol"><option value="agente">Agente</option><option value="admin">Administrador</option></select></div>
  </div>
  <button class="btn" name="accion" value="crear">Crear usuario</button>
</form>
<?php page_foot();
