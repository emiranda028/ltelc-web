<?php
require __DIR__ . '/lib/bootstrap.php';

$a = q1('SELECT a.*, m.ticket_id, m.internal FROM attachments a JOIN messages m ON m.id = a.message_id WHERE a.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$a) { http_response_code(404); exit('Archivo no encontrado.'); }
$t = q1('SELECT * FROM tickets WHERE id = ?', [$a['ticket_id']]);

// acceso: equipo, el enlace del ticket o el cliente dueño con sesión iniciada
$ok = staff() !== null;
if (!$ok && !$a['internal']) {
    $tok = (string)($_GET['t'] ?? '');
    $ok = ($tok !== '' && hash_equals($t['token'], $tok)) || (contact() && (int)contact()['id'] === (int)$t['contact_id']);
}
if (!$ok) { http_response_code(403); exit('No tenés acceso a este archivo.'); }

$path = upload_dir() . '/' . basename($a['stored']);
if (!is_file($path)) { http_response_code(404); exit('El archivo ya no está disponible.'); }
$inline = preg_match('~^(image/(png|jpe?g|gif|webp)|application/pdf)$~', $a['mime']);
header('Content-Type: ' . ($inline ? $a['mime'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($a['filename']));
header('X-Content-Type-Options: nosniff');
readfile($path);
