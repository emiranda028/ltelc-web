<?php
/*
 * Tarea programada (cada 5 minutos en Hostinger):
 *   php /home/USUARIO/domains/ltelc.tech/public_html/soporte/cron.php
 * Lee la casilla soporte@ltelc.tech y envía los avisos pendientes al equipo.
 * También puede llamarse por web con la clave: /soporte/cron.php?k=CRON_KEY
 */
require __DIR__ . '/lib/bootstrap.php';

if (!is_cli()) {
    $key = (string)cfg('cron_key', '');
    if ($key === '' || !hash_equals($key, (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('Prohibido'); }
    header('Content-Type: text/plain; charset=UTF-8');
}
if (!schema_ready()) exit("Falta instalar (instalar.php)\n");

$lock = fopen(sys_get_temp_dir() . '/ltelc-soporte-cron.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) exit("Ya hay una ejecución en curso\n");

$out = date('Y-m-d H:i') . ' ';
try {
    $r = fetch_mailbox();
    $out .= $r['ok'] ? "mail: {$r['leidos']} leídos, {$r['tickets']} a tickets, {$r['ignorados']} ignorados" : 'mail: ' . $r['msg'];
} catch (Throwable $e) {
    $out .= 'mail: ERROR ' . $e->getMessage();
}
$out .= ' · avisos enviados: ' . process_queue();
$pend = q('SELECT COUNT(*) FROM notify_queue WHERE sent_at IS NULL AND send_after <= ?', [now()])->fetchColumn();
$later = q('SELECT COUNT(*) FROM notify_queue WHERE sent_at IS NULL AND send_after > ?', [now()])->fetchColumn();
$sent = q('SELECT COUNT(*) FROM notify_queue WHERE sent_at IS NOT NULL')->fetchColumn();
$out .= " · sin poder enviar: $pend · programados para el horario: $later · enviados en total: $sent";
if (!is_cli() && isset($_GET['detalle'])) {
    foreach (q('SELECT to_email, subject, send_after, sent_at FROM notify_queue ORDER BY id DESC LIMIT 10')->fetchAll() as $r)
        $out .= "\n  " . preg_replace('/^(.).*(@.*)$/', '$1***$2', $r['to_email']) . ' | ' . $r['subject'] . ' | ' . ($r['sent_at'] ? 'enviado ' . $r['sent_at'] : 'pendiente desde ' . $r['send_after']);
}

// limpieza: tokens vencidos y registros de límite viejos
q('DELETE FROM login_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
q('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
// los resueltos sin novedades por 7 días pasan a cerrados
q("UPDATE tickets SET status = 'cerrado' WHERE status = 'resuelto' AND updated_at < ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);

echo $out, "\n";
