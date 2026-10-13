<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
$me = require_staff();

$open = "('" . implode("','", LT_OPEN) . "')";
$tabs = [
    'abiertos'   => ['Abiertos', "t.status IN $open", []],
    'mios'       => ['Míos', "t.status IN $open AND t.assignee_id = ?", [$me['id']]],
    'sin'        => ['Sin asignar', "t.status IN $open AND t.assignee_id IS NULL", []],
    'urgentes'   => ['Urgentes', "t.status IN $open AND t.priority IN ('urgente','alta')", []],
    'esperando'  => ['Esperando cliente', "t.status = 'esperando'", []],
    'resueltos'  => ['Resueltos', "t.status IN ('resuelto','cerrado')", []],
    'todos'      => ['Todos', '1=1', []],
];
$tab = isset($tabs[$_GET['v'] ?? '']) ? $_GET['v'] : 'abiertos';
[$_, $where, $params] = $tabs[$tab];
$s = trim((string)($_GET['s'] ?? ''));
if ($s !== '') {
    $n = preg_match('/^(?:LT-?)?0*(\d+)$/i', $s, $m) ? (int)$m[1] : 0;
    $where .= ' AND (t.id = ? OR t.subject LIKE ? OR c.email LIKE ? OR c.name LIKE ? OR c.organization LIKE ?)';
    $like = '%' . $s . '%';
    array_push($params, $n, $like, $like, $like, $like);
}
$order = "CASE t.priority WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, t.due_at";
if (in_array($tab, ['resueltos', 'todos'], true)) $order = 't.updated_at DESC';
$rows = q("SELECT t.*, c.name AS cname, c.email AS cemail, c.organization AS corg, u.name AS uname
           FROM tickets t JOIN contacts c ON c.id = t.contact_id LEFT JOIN users u ON u.id = t.assignee_id
           WHERE $where ORDER BY $order LIMIT 300", $params)->fetchAll();

// resumen
$k = [
    'abiertos' => (int)q("SELECT COUNT(*) FROM tickets WHERE status IN $open")->fetchColumn(),
    'sin'      => (int)q("SELECT COUNT(*) FROM tickets WHERE status IN $open AND assignee_id IS NULL")->fetchColumn(),
    'vencidos' => (int)q("SELECT COUNT(*) FROM tickets WHERE status IN $open AND first_response_at IS NULL AND due_at < ?", [now()])->fetchColumn(),
    'urgentes' => (int)q("SELECT COUNT(*) FROM tickets WHERE status IN $open AND priority = 'urgente'")->fetchColumn(),
    'resueltos7' => (int)q("SELECT COUNT(*) FROM tickets WHERE status IN ('resuelto','cerrado') AND closed_at > ?", [date('Y-m-d H:i:s', time() - 7 * 86400)])->fetchColumn(),
];
page_head('Tickets', 'panel'); ?>
<h1>Bandeja de <b>reclamos</b></h1>
<div class="kpis">
  <a class="kpi" href="?v=abiertos"><b><?= $k['abiertos'] ?></b><span>Abiertos</span></a>
  <a class="kpi" href="?v=sin"><b><?= $k['sin'] ?></b><span>Sin asignar</span></a>
  <a class="kpi<?= $k['vencidos'] ? ' alert' : '' ?>" href="?v=abiertos"><b><?= $k['vencidos'] ?></b><span>Sin respuesta y vencidos</span></a>
  <a class="kpi<?= $k['urgentes'] ? ' alert' : '' ?>" href="?v=urgentes"><b><?= $k['urgentes'] ?></b><span>Urgentes</span></a>
  <a class="kpi" href="?v=resueltos"><b><?= $k['resueltos7'] ?></b><span>Resueltos en 7 días</span></a>
</div>
<div class="toolbar">
  <nav class="tabs" aria-label="Vistas">
    <?php foreach ($tabs as $key => [$label]): ?><a href="?v=<?= $key ?>"<?= $key === $tab ? ' class="on" aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?>
  </nav>
  <form class="search" method="get"><input type="hidden" name="v" value="<?= h($tab) ?>"><input type="search" name="s" value="<?= h($s) ?>" placeholder="N°, asunto, cliente u organización" aria-label="Buscar"><button class="btn small line">Buscar</button></form>
</div>
<div class="table-wrap">
<?php if (!$rows): ?><div class="empty">No hay reclamos en esta vista.</div>
<?php else: ?>
  <table class="list"><thead><tr><th>N°</th><th>Asunto</th><th>Cliente</th><th>Sistema</th><th>Prioridad</th><th>Estado</th><th>Asignado</th><th>Plazo</th><th>Actividad</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $link = base_url('panel/ticket.php?id=' . $r['id']); ?>
    <tr class="row" onclick="location.href='<?= h($link) ?>'">
      <td class="num"><?= h(ticket_number((int)$r['id'])) ?><?= $r['channel'] === 'email' ? ' <small title="Llegó por mail">✉</small>' : '' ?></td>
      <td><a class="subj" href="<?= h($link) ?>"><?= h($r['subject']) ?></a></td>
      <td><?= h($r['cname'] ?: $r['cemail']) ?><?= $r['corg'] ? '<br><small>' . h($r['corg']) . '</small>' : '' ?></td>
      <td><?= h($r['sistema'] ?: '—') ?></td>
      <td><?= priority_chip($r['priority']) ?></td>
      <td><?= status_chip($r['status']) ?></td>
      <td><?= h($r['uname'] ?: '—') ?></td>
      <td><?= sla_badge($r) ?: '<span class="sla ok">—</span>' ?></td>
      <td class="num"><?= h(fmt_date($r['updated_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
<?php endif; ?>
</div>
<?php page_foot();
