<?php
require __DIR__ . '/lib/bootstrap.php';
if (!schema_ready()) redirect('instalar.php');

$err = null;
$v = ['nombre' => '', 'email' => '', 'organizacion' => '', 'sistema' => '', 'prioridad' => 'normal', 'asunto' => '', 'detalle' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    [$files, $upErr] = collect_uploads($_FILES['adjuntos'] ?? null);
    if (!empty($_POST['web'])) { $err = 'No pudimos procesar el envío.'; }                       // trampa anti-bots
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Revisá el mail: necesitamos uno válido para responderte.';
    elseif ($v['nombre'] === '' || $v['asunto'] === '' || mb_strlen($v['detalle']) < 10) $err = 'Completá tu nombre, un asunto y una descripción del problema.';
    elseif ($upErr) $err = $upErr;
    elseif (rate_limited('nuevo', 8)) $err = 'Recibimos varios reclamos seguidos desde tu conexión. Esperá unos minutos o escribinos a ' . cfg('mail_from') . '.';
    else {
        $sys = in_array($v['sistema'], systems(), true) ? $v['sistema'] : '';
        $contact = find_or_create_contact($v['email'], $v['nombre'], $v['organizacion']);
        $t = create_ticket($contact, $v['asunto'], $v['detalle'], $v['prioridad'], $sys, 'web', $files);
        $_SESSION['contact_id'] = (int)$contact['id'];
        flash('Listo: registramos tu reclamo ' . ticket_number((int)$t['id']) . '. Te mandamos la confirmación a ' . $contact['email'] . '.');
        redirect('ticket.php?t=' . $t['token']);
    }
}

page_head('Nuevo reclamo');
$hrs = cfg('hours');
?>
<div class="hero">
  <section class="card">
    <h1>Contanos qué <b>está pasando</b></h1>
    <p class="lead">Cargá tu reclamo o consulta y te damos un número de seguimiento. Todo queda registrado en un solo lugar y te avisamos por mail cada vez que haya una respuesta.</p>
    <?php if ($err): ?><div class="flash flash-error" role="alert"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>No completar <input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>
      <div class="grid2">
        <div class="field"><label for="nombre">Nombre y apellido</label><input type="text" id="nombre" name="nombre" autocomplete="name" required value="<?= h($v['nombre']) ?>"></div>
        <div class="field"><label for="email">Mail</label><input type="email" id="email" name="email" autocomplete="email" required value="<?= h($v['email']) ?>"></div>
      </div>
      <div class="grid2">
        <div class="field"><label for="organizacion">Organización</label><input type="text" id="organizacion" name="organizacion" autocomplete="organization" value="<?= h($v['organizacion']) ?>"></div>
        <div class="field"><label for="sistema">Sistema</label>
          <select id="sistema" name="sistema"><option value="">Elegí el sistema</option>
            <?php foreach (systems() as $s): ?><option<?= $v['sistema'] === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <fieldset class="field" style="border:0;padding:0;margin:0 0 16px"><legend style="font-size:.88rem;font-weight:600;margin-bottom:6px">¿Qué tan urgente es?</legend>
        <div class="prio">
          <?php foreach (LT_PRIORITIES as $k => [$lab, $desc]): ?>
            <label><input type="radio" name="prioridad" value="<?= h($k) ?>"<?= $v['prioridad'] === $k ? ' checked' : '' ?>><span><b><?= h($lab) ?></b><span><?= h($desc) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="field"><label for="asunto">Asunto</label><input type="text" id="asunto" name="asunto" maxlength="200" required value="<?= h($v['asunto']) ?>" placeholder="Ej.: No puedo exportar el reporte mensual"></div>
      <div class="field"><label for="detalle">Descripción</label><textarea id="detalle" name="detalle" required placeholder="Qué estabas haciendo, qué esperabas que pase y qué pasó. Si aparece un mensaje de error, copialo acá."><?= h($v['detalle']) ?></textarea></div>
      <div class="field"><label for="adjuntos">Capturas o archivos <span class="hint">(opcional, hasta 6 de <?= (int)cfg('max_upload_mb', 10) ?> MB)</span></label><input type="file" id="adjuntos" name="adjuntos[]" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.heic,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.mp4"></div>
      <button class="btn" type="submit">Enviar reclamo</button>
    </form>
  </section>
  <aside class="hero-side">
    <div class="side-box">
      <h2>Cómo funciona</h2>
      <ol class="steps">
        <li>Recibís un número de reclamo y la confirmación por mail.</li>
        <li>El equipo lo toma según la urgencia y te responde por mail.</li>
        <li>Seguís la conversación desde el enlace o respondiendo el mail.</li>
      </ol>
    </div>
    <div class="side-box">
      <h2>Horario de atención</h2>
      <p>Lunes a viernes de <?= (int)$hrs['start'] ?> a <?= (int)$hrs['end'] ?> h. Los reclamos urgentes se avisan al equipo en el momento.</p>
      <p>También podés escribir a <a href="mailto:<?= h(cfg('mail_from')) ?>"><?= h(cfg('mail_from')) ?></a>: cada mail abre un reclamo.</p>
    </div>
    <div class="card">
      <h2 style="font-size:1.05rem">¿Ya cargaste un reclamo?</h2>
      <p class="muted" style="margin:6px 0 14px;font-size:.94rem">Te mandamos un enlace al mail para ver todos tus reclamos y su estado.</p>
      <a class="btn line" href="<?= h(base_url('mis-reclamos.php')) ?>">Ver mis reclamos</a>
    </div>
  </aside>
</div>
<?php page_foot();
