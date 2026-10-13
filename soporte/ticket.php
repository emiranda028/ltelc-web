<?php
require __DIR__ . '/lib/bootstrap.php';

$token = (string)($_GET['t'] ?? '');
$t = $token !== '' ? q1('SELECT * FROM tickets WHERE token = ?', [$token]) : null;
if (!$t) { http_response_code(404); page_head('Reclamo no encontrado'); echo '<div class="card"><h1>No encontramos ese reclamo</h1><p class="lead">Revisá el enlace del mail o pedí uno nuevo desde <a href="' . h(base_url('mis-reclamos.php')) . '">Mis reclamos</a>.</p></div>'; page_foot(); exit; }
$contact = q1('SELECT * FROM contacts WHERE id = ?', [$t['contact_id']]);
$num = ticket_number((int)$t['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['accion'] ?? 'responder';
    if ($action === 'resuelto') {
        set_status($t, 'resuelto');
        add_message($t, 'system', null, 'El cliente marcó el reclamo como resuelto.', true, 'web', [], null, false);
        flash('Marcamos el reclamo como resuelto. ¡Gracias! Si vuelve a pasar, respondé acá y se reabre.');
        redirect('ticket.php?t=' . $t['token']);
    }
    $body = trim((string)($_POST['mensaje'] ?? ''));
    [$files, $upErr] = collect_uploads($_FILES['adjuntos'] ?? null);
    if ($upErr) flash($upErr, 'error');
    elseif ($body === '' && !$files) flash('Escribí un mensaje o adjuntá un archivo.', 'error');
    elseif (rate_limited('respuesta', 30)) flash('Enviaste muchos mensajes seguidos. Probá de nuevo en unos minutos.', 'error');
    else { add_message($t, 'contact', (int)$contact['id'], $body ?: '(adjunto)', false, 'web', $files); flash('Enviamos tu mensaje al equipo.'); }
    redirect('ticket.php?t=' . $t['token']);
}

$msgs = ticket_messages((int)$t['id'], false);
page_head("Reclamo $num");
$open = in_array($t['status'], LT_OPEN, true);
?>
<div class="tk">
  <section>
    <div class="tk-head">
      <h1><?= h($t['subject']) ?></h1>
      <div class="meta"><b><?= h($num) ?></b> <?= status_chip($t['status']) ?> <?= priority_chip($t['priority']) ?> <?= $t['sistema'] ? '· ' . h($t['sistema']) : '' ?> · creado <?= h(fmt_date($t['created_at'])) ?></div>
    </div>
    <?php render_thread($msgs, base_url('descarga.php?t=' . $t['token']), false); ?>
    <form class="card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label for="mensaje"><?= $open ? 'Sumar información o responder' : 'Volver a abrir con un mensaje' ?></label><textarea id="mensaje" name="mensaje" placeholder="Escribí tu mensaje"></textarea></div>
      <div class="reply-tools">
        <input type="file" name="adjuntos[]" multiple aria-label="Adjuntar archivos">
        <button class="btn" type="submit" name="accion" value="responder">Enviar</button>
      </div>
    </form>
  </section>
  <aside class="side">
    <div class="card">
      <h3>Estado</h3>
      <p style="margin:0 0 6px"><?= status_chip($t['status']) ?></p>
      <p class="muted" style="margin:0;font-size:.9rem"><?= $t['first_response_at'] ? 'Primera respuesta: ' . h(fmt_date($t['first_response_at'])) : 'Lo estamos revisando. ' . h(hours_note()) ?></p>
      <?php if ($open): ?>
      <form method="post" style="margin-top:14px"><?= csrf_field() ?><button class="btn line small" name="accion" value="resuelto">Ya está resuelto</button></form>
      <?php endif; ?>
    </div>
    <div class="card">
      <h3>Tus datos</h3>
      <dl class="kv"><dt>Nombre</dt><dd><?= h($contact['name'] ?: '—') ?></dd><dt>Mail</dt><dd><?= h($contact['email']) ?></dd><dt>Organización</dt><dd><?= h($contact['organization'] ?: '—') ?></dd></dl>
      <p style="margin:14px 0 0"><a href="<?= h(base_url('mis-reclamos.php')) ?>">Ver todos mis reclamos</a></p>
    </div>
  </aside>
</div>
<?php page_foot();
