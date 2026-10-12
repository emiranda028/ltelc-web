<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
if (!schema_ready()) redirect('instalar.php');
if (staff()) redirect('panel/');

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $u = q1('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    if (rate_limited('login', 10)) $err = 'Demasiados intentos. Esperá unos minutos.';
    elseif (!$u || !password_verify((string)($_POST['clave'] ?? ''), $u['pass_hash'])) $err = 'Mail o contraseña incorrectos.';
    else {
        session_regenerate_id(true);
        $_SESSION['staff_id'] = (int)$u['id'];
        redirect('panel/');
    }
}
page_head('Ingresar', 'panel'); ?>
<section class="card" style="max-width:440px;margin:30px auto">
  <h1>Panel de <b>soporte</b></h1>
  <p class="lead">Acceso para el equipo LTELC.</p>
  <?php if ($err): ?><div class="flash flash-error" role="alert"><?= h($err) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="email">Mail</label><input type="email" id="email" name="email" autocomplete="username" required></div>
    <div class="field"><label for="clave">Contraseña</label><input type="password" id="clave" name="clave" autocomplete="current-password" required></div>
    <button class="btn" type="submit">Ingresar</button>
  </form>
</section>
<?php page_foot();
