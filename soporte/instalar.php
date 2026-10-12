<?php
require __DIR__ . '/lib/bootstrap.php';

$hasUsers = schema_ready() && (int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
if ($hasUsers) { page_head('Instalación'); echo '<div class="card" style="max-width:560px;margin:20px auto"><h1>Ya está <b>instalado</b></h1><p class="lead">El sistema ya tiene usuarios. Entrá al <a href="' . h(base_url('panel/')) . '">panel</a>.</p></div>'; page_foot(); exit; }

$err = null; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = (string)cfg('install_key', '');
    $name = trim((string)($_POST['nombre'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['clave'] ?? '');
    if ($key === '' || !hash_equals($key, (string)($_POST['clave_instalacion'] ?? ''))) $err = 'La clave de instalación no coincide con la de la configuración.';
    elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Completá tu nombre y un mail válido.';
    elseif (mb_strlen($pass) < 10) $err = 'La contraseña tiene que tener al menos 10 caracteres.';
    else {
        install_schema();
        q('INSERT INTO users (name, email, pass_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin', now()]);
        session_regenerate_id(true);
        $_SESSION['staff_id'] = (int)db()->lastInsertId();
        flash('Listo: el sistema está instalado. Sumá al resto del equipo desde «Equipo».');
        redirect('panel/');
    }
}
page_head('Instalación'); ?>
<section class="card" style="max-width:560px;margin:20px auto">
  <h1>Instalar <b>Soporte LTELC</b></h1>
  <p class="lead">Crea las tablas en la base de datos y el primer usuario administrador.</p>
  <?php if ($err): ?><div class="flash flash-error" role="alert"><?= h($err) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="ci">Clave de instalación <span class="hint">(la que figura como install_key en la configuración)</span></label><input type="password" id="ci" name="clave_instalacion" required></div>
    <div class="field"><label for="nombre">Tu nombre</label><input type="text" id="nombre" name="nombre" required value="<?= h($name) ?>"></div>
    <div class="field"><label for="email">Tu mail</label><input type="email" id="email" name="email" required value="<?= h($email) ?>"></div>
    <div class="field"><label for="clave">Contraseña <span class="hint">(mínimo 10 caracteres)</span></label><input type="password" id="clave" name="clave" autocomplete="new-password" required></div>
    <button class="btn" type="submit">Instalar</button>
  </form>
</section>
<?php page_foot();
