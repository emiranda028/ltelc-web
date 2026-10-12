<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
$me = require_staff();

$id = (int)($_GET['id'] ?? 0);
$t = q1('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) { http_response_code(404); page_head('No encontrado', 'panel'); echo '<div class="card"><h1>Ese ticket no existe</h1></div>'; page_foot(); exit; }
$contact = q1('SELECT * FROM contacts WHERE id = ?', [$t['contact_id']]);
$num = ticket_number((int)$t['id']);
$users = q('SELECT id, name FROM users WHERE active = 1 ORDER BY name')->fetchAll();
$back = 'panel/ticket.php?id=' . $t['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['accion'] ?? '';
    if ($action === 'datos') {
        $st = isset(LT_STATUSES[$_POST['estado'] ?? '']) ? $_POST['estado'] : $t['status'];
        $pr = isset(LT_PRIORITIES[$_POST['prioridad'] ?? '']) ? $_POST['prioridad'] : $t['priority'];
        $as = (int)($_POST['asignado'] ?? 0);
        $as = $as && q1('SELECT id FROM users WHERE id = ? AND active = 1', [$as]) ? $as : null;
        $sy = in_array($_POST['sistema'] ?? '', systems(), true) ? $_POST['sistema'] : '';
        $changes = [];
        if ($st !== $t['status']) { set_status($t, $st); $changes[] = 'estado → ' . status_label($st); }
        if ($pr !== $t['priority']) {
            q('UPDATE tickets SET priority = ?, due_at = ? WHERE id = ?', [$pr, $t['first_response_at'] ? $t['due_at'] : due_for($pr, new DateTimeImmutable($t['created_at'])), $t['id']]);
            $changes[] = 'prioridad → ' . priority_label($pr);
        }
        if ($as !== ($t['assignee_id'] ? (int)$t['assignee_id'] : null)) {
            q('UPDATE tickets SET assignee_id = ? WHERE id = ?', [$as, $t['id']]);
            $who = $as ? q1('SELECT name FROM users WHERE id = ?', [$as])['name'] : 'nadie';
            $changes[] = 'asignado → ' . $who;
            if ($as && $as !== (int)$me['id']) notify_team($t, "Te asignaron $num", [$me['name'] . ' te asignó el reclamo "' . $t['subject'] . '".'], $pr === 'urgente', $as);
        }
        if ($sy !== (string)$t['system']) { q('UPDATE tickets SET system = ? WHERE id = ?', [$sy, $t['id']]); $changes[] = 'sistema → ' . ($sy ?: '—'); }
        if ($changes) {
            q('INSERT INTO messages (ticket_id, author_type, author_id, body, internal, via, created_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
              [$t['id'], 'system', $me['id'], $me['name'] . ': ' . implode(' · ', $changes), 'panel', now()]);
            q('UPDATE tickets SET updated_at = ? WHERE id = ?', [now(), $t['id']]);
            flash('Cambios guardados.');
        }
        redirect($back);
    }
    if ($action === 'responder' || $action === 'nota') {
        $body = trim((string)($_POST['mensaje'] ?? ''));
        [$files, $upErr] = collect_uploads($_FILES['adjuntos'] ?? null);
        if ($upErr) { flash($upErr, 'error'); redirect($back); }
        if ($body === '') { flash('Escribí el mensaje.', 'error'); redirect($back); }
        $internal = $action === 'nota';
        add_message($t, 'staff', (int)$me['id'], $body, $internal, 'panel', $files);
        if (!$internal) {
            $next = $_POST['despues'] ?? 'esperando';
            if (isset(LT_STATUSES[$next])) set_status($t, $next);
            if (!$t['assignee_id']) q('UPDATE tickets SET assignee_id = ? WHERE id = ?', [$me['id'], $t['id']]);
        } elseif ($t['status'] === 'nuevo') {
            set_status($t, 'abierto');
        }
        flash($internal ? 'Nota interna guardada (el cliente no la ve).' : 'Respuesta enviada al cliente por mail.');
        redirect($back);
    }
    redirect($back);
}

$msgs = ticket_messages((int)$t['id'], true);
$others = q('SELECT id, subject, status FROM tickets WHERE contact_id = ? AND id <> ? ORDER BY id DESC LIMIT 6', [$t['contact_id'], $t['id']])->fetchAll();
page_head("$num · {$t['subject']}", 'panel'); ?>
<p style="margin:0 0 12px"><a href="<?= h(base_url('panel/')) ?>">← Bandeja</a></p>
<div class="tk">
  <section>
    <div class="tk-head">
      <h1><?= h($t['subject']) ?></h1>
      <div class="meta"><b><?= h($num) ?></b> <?= status_chip($t['status']) ?> <?= priority_chip($t['priority']) ?> <?= sla_badge($t) ?> · <?= $t['channel'] === 'email' ? 'llegó por mail' : 'cargado en la web' ?> · <?= h(fmt_date($t['created_at'])) ?></div>
    </div>
    <?php render_thread($msgs, base_url('descarga.php'), true); ?>
    <form class="card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label for="mensaje">Respuesta</label><textarea id="mensaje" name="mensaje" placeholder="Lo que escribas acá le llega al cliente por mail. Usá «Nota interna» para comentarios del equipo."></textarea></div>
      <div class="reply-tools">
        <input type="file" name="adjuntos[]" multiple aria-label="Adjuntar archivos">
        <label class="check">Después de responder: <select name="despues" style="width:auto;padding:6px 10px">
          <option value="esperando">Esperando al cliente</option><option value="abierto">Sigue en curso</option><option value="resuelto">Resuelto</option></select></label>
      </div>
      <div class="reply-tools" style="margin-top:12px;justify-content:flex-end">
        <button class="btn line" type="submit" name="accion" value="nota">Nota interna</button>
        <button class="btn" type="submit" name="accion" value="responder">Responder al cliente</button>
      </div>
    </form>
  </section>
  <aside class="side">
    <form class="card" method="post"><?= csrf_field() ?>
      <h3>Gestión</h3>
      <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><?php foreach (LT_STATUSES as $k2 => $l): ?><option value="<?= $k2 ?>"<?= $t['status'] === $k2 ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="prioridad">Prioridad</label><select id="prioridad" name="prioridad"><?php foreach (LT_PRIORITIES as $k2 => [$l]): ?><option value="<?= $k2 ?>"<?= $t['priority'] === $k2 ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="asignado">Asignado</label><select id="asignado" name="asignado"><option value="0">Sin asignar</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)$t['assignee_id'] === (int)$u['id'] ? ' selected' : '' ?>><?= h($u['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="sistema">Sistema</label><select id="sistema" name="sistema"><option value="">—</option><?php foreach (systems() as $sy): ?><option<?= $t['system'] === $sy ? ' selected' : '' ?>><?= h($sy) ?></option><?php endforeach; ?></select></div>
      <button class="btn small" name="accion" value="datos">Guardar</button>
    </form>
    <div class="card">
      <h3>Cliente</h3>
      <dl class="kv"><dt>Nombre</dt><dd><?= h($contact['name'] ?: '—') ?></dd><dt>Mail</dt><dd><a href="mailto:<?= h($contact['email']) ?>"><?= h($contact['email']) ?></a></dd><dt>Organización</dt><dd><?= h($contact['organization'] ?: '—') ?></dd></dl>
      <?php if ($others): ?><h3 style="margin-top:16px">Otros reclamos</h3><ul style="margin:0;padding-left:18px;font-size:.9rem">
        <?php foreach ($others as $o): ?><li><a href="?id=<?= $o['id'] ?>"><?= h(ticket_number((int)$o['id'])) ?></a> <?= h($o['subject']) ?> <small>(<?= h(status_label($o['status'])) ?>)</small></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card">
      <h3>Plazos</h3>
      <dl class="kv"><dt>Creado</dt><dd><?= h(fmt_date($t['created_at'])) ?></dd><dt>Responder antes de</dt><dd><?= h(fmt_date($t['due_at'])) ?></dd><dt>1ª respuesta</dt><dd><?= h(fmt_date($t['first_response_at'])) ?></dd><dt>Cerrado</dt><dd><?= h(fmt_date($t['closed_at'])) ?></dd></dl>
      <p style="margin:14px 0 0;font-size:.86rem"><a href="<?= h(ticket_link($t)) ?>" target="_blank" rel="noopener">Ver como el cliente</a></p>
    </div>
  </aside>
</div>
<?php page_foot();
