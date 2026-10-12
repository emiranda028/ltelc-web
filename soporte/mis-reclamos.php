<?php
require __DIR__ . '/lib/bootstrap.php';

if (isset($_GET['salir'])) { unset($_SESSION['contact_id']); redirect('mis-reclamos.php'); }

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !rate_limited('acceso', 6)) send_login_link($email);
    $sent = true;   // misma respuesta siempre: no revelamos qué mails existen
}

$c = contact();
page_head('Mis reclamos');
if (!$c): ?>
  <section class="card" style="max-width:560px;margin:20px auto">
    <h1>Mis <b>reclamos</b></h1>
    <?php if ($sent): ?>
      <p class="lead">Si ese mail tiene reclamos registrados, te llega un enlace de acceso en unos minutos. Revisá también la carpeta de spam.</p>
    <?php else: ?>
      <p class="lead">Ingresá el mail con el que cargaste tus reclamos y te mandamos un enlace para entrar. No hace falta contraseña.</p>
      <form method="post"><?= csrf_field() ?>
        <div class="field"><label for="email">Mail</label><input type="email" id="email" name="email" autocomplete="email" required></div>
        <button class="btn" type="submit">Enviarme el enlace</button>
      </form>
    <?php endif; ?>
  </section>
<?php else:
  $rows = q('SELECT * FROM tickets WHERE contact_id = ? ORDER BY updated_at DESC', [$c['id']])->fetchAll(); ?>
  <div class="toolbar"><div><h1>Mis <b>reclamos</b></h1><p class="muted" style="margin:6px 0 0"><?= h($c['email']) ?> · <a href="?salir=1">Salir</a></p></div>
    <a class="btn" href="<?= h(base_url('')) ?>">Nuevo reclamo</a></div>
  <div class="table-wrap">
  <?php if (!$rows): ?><div class="empty">Todavía no tenés reclamos cargados.</div>
  <?php else: ?>
    <table class="list"><thead><tr><th>N°</th><th>Asunto</th><th>Sistema</th><th>Prioridad</th><th>Estado</th><th>Última actividad</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $link = base_url('ticket.php?t=' . $r['token']); ?>
      <tr class="row" onclick="location.href='<?= h($link) ?>'"><td class="num"><?= h(ticket_number((int)$r['id'])) ?></td><td><a class="subj" href="<?= h($link) ?>"><?= h($r['subject']) ?></a></td><td><?= h($r['system'] ?: '—') ?></td><td><?= priority_chip($r['priority']) ?></td><td><?= status_chip($r['status']) ?></td><td class="num"><?= h(fmt_date($r['updated_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  </div>
<?php endif;
page_foot();
