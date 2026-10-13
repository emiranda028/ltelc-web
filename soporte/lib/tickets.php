<?php
declare(strict_types=1);

const LT_STATUSES = [
    'nuevo' => 'Nuevo',
    'abierto' => 'En curso',
    'esperando' => 'Esperando al cliente',
    'resuelto' => 'Resuelto',
    'cerrado' => 'Cerrado',
];
const LT_PRIORITIES = [
    'urgente' => ['Urgente', 'El sistema no funciona o hay operación detenida'],
    'alta' => ['Alta', 'Afecta el trabajo diario, pero hay una alternativa'],
    'normal' => ['Normal', 'Consulta, error menor o pedido de ajuste'],
    'baja' => ['Baja', 'Sugerencia o mejora para más adelante'],
];
const LT_OPEN = ['nuevo', 'abierto', 'esperando'];
const LT_ALLOWED_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'mp4'];

function ticket_number(int $id): string { return 'LT-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function ticket_link(array $t): string { return base_url('ticket.php?t=' . $t['token']); }
function status_label(string $s): string { return LT_STATUSES[$s] ?? $s; }
function priority_label(string $p): string { return LT_PRIORITIES[$p][0] ?? $p; }
function systems(): array { return cfg('systems', ['Otro']); }

/* ---------------- horario de atención y plazos ---------------- */
function in_business_hours(?DateTimeImmutable $t = null): bool
{
    $t = $t ?? new DateTimeImmutable();
    $h = cfg('hours');
    $hour = (int)$t->format('G') + ((int)$t->format('i')) / 60;
    return in_array((int)$t->format('N'), $h['days'], true) && $hour >= $h['start'] && $hour < $h['end'];
}
/** Próximo momento dentro del horario de atención (o el mismo, si ya lo está). */
function next_business_start(?DateTimeImmutable $t = null): DateTimeImmutable
{
    $t = $t ?? new DateTimeImmutable();
    if (in_business_hours($t)) return $t;
    $h = cfg('hours');
    for ($i = 0; $i < 14; $i++) {
        $day = $t->modify("+$i day")->setTime((int)$h['start'], 0);
        if (in_array((int)$day->format('N'), $h['days'], true) && $day > $t) return $day;
    }
    return $t;
}
/** Suma horas hábiles a una fecha. */
function add_business_hours(DateTimeImmutable $start, float $hours): DateTimeImmutable
{
    $h = cfg('hours');
    $t = next_business_start($start);
    $left = $hours * 60;
    while ($left > 0) {
        $endOfDay = $t->setTime((int)$h['end'], 0);
        $avail = ($endOfDay->getTimestamp() - $t->getTimestamp()) / 60;
        if ($left <= $avail) return $t->modify('+' . (int)round($left) . ' minutes');
        $left -= $avail;
        $t = next_business_start($endOfDay->modify('+1 minute'));
    }
    return $t;
}
function due_for(string $priority, ?DateTimeImmutable $from = null): string
{
    $hours = cfg('sla_hours')[$priority] ?? 9;
    return add_business_hours($from ?? new DateTimeImmutable(), (float)$hours)->format('Y-m-d H:i:s');
}
function hours_note(): string
{
    $h = cfg('hours');
    return sprintf('Horario de atención: lunes a viernes de %d a %d h. Lo que llega fuera de ese horario lo respondemos el siguiente día hábil.', $h['start'], $h['end']);
}

/* ---------------- contactos ---------------- */
function find_or_create_contact(string $email, string $name = '', string $org = ''): array
{
    $email = mb_strtolower(trim($email));
    $c = q1('SELECT * FROM contacts WHERE email = ?', [$email]);
    if ($c) {
        if (($name && !$c['name']) || ($org && !$c['organization'])) {
            q('UPDATE contacts SET name = ?, organization = ? WHERE id = ?', [$c['name'] ?: $name, $c['organization'] ?: $org, $c['id']]);
            $c = q1('SELECT * FROM contacts WHERE id = ?', [$c['id']]);
        }
        return $c;
    }
    q('INSERT INTO contacts (name, email, organization, created_at) VALUES (?, ?, ?, ?)', [$name, $email, $org, now()]);
    return q1('SELECT * FROM contacts WHERE id = ?', [(int)db()->lastInsertId()]);
}

/* ---------------- adjuntos ---------------- */
function upload_dir(): string
{
    $d = cfg('upload_dir', LT_CFG_DIR . '/soporte-uploads');   // junto a la configuración, fuera de public_html
    if (!is_dir($d)) mkdir($d, 0750, true);
    return $d;
}
/** Valida y guarda archivos de un formulario ($_FILES['adjuntos']). Devuelve [lista, error]. */
function collect_uploads(?array $files): array
{
    $out = [];
    if (!$files || !isset($files['name'])) return [$out, null];
    $max = (int)cfg('max_upload_mb', 10) * 1024 * 1024;
    $n = is_array($files['name']) ? count($files['name']) : 0;
    if ($n > 6) return [[], 'Podés adjuntar hasta 6 archivos.'];
    for ($i = 0; $i < $n; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) return [[], 'No se pudo subir "' . $files['name'][$i] . '".'];
        $name = basename((string)$files['name'][$i]);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, LT_ALLOWED_EXT, true)) return [[], "El tipo de archivo .$ext no está permitido."];
        if ($files['size'][$i] > $max) return [[], '"' . $name . '" supera los ' . cfg('max_upload_mb', 10) . ' MB.'];
        $out[] = ['name' => $name, 'tmp' => $files['tmp_name'][$i], 'size' => (int)$files['size'][$i], 'mime' => (string)($files['type'][$i] ?: 'application/octet-stream')];
    }
    return [$out, null];
}
/** Guarda adjuntos de un mensaje. Cada uno: name, size, mime y 'tmp' (subido) o 'data' (bytes de un mail). */
function store_attachments(int $messageId, array $files): void
{
    foreach ($files as $f) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, LT_ALLOWED_EXT, true)) continue;
        $stored = random_token(16) . '.' . $ext;
        $dest = upload_dir() . '/' . $stored;
        $ok = isset($f['data']) ? file_put_contents($dest, $f['data']) !== false
            : (is_uploaded_file($f['tmp']) ? move_uploaded_file($f['tmp'], $dest) : copy($f['tmp'], $dest));
        if ($ok) q('INSERT INTO attachments (message_id, filename, stored, mime, size) VALUES (?, ?, ?, ?, ?)', [$messageId, mb_substr($f['name'], 0, 190), $stored, $f['mime'], $f['size']]);
    }
}

/* ---------------- tickets y mensajes ---------------- */
function create_ticket(array $contact, string $subject, string $body, string $priority, string $system, string $channel, array $files = [], ?string $msgid = null): array
{
    if (!isset(LT_PRIORITIES[$priority])) $priority = 'normal';
    $ts = now();
    q('INSERT INTO tickets (subject, status, priority, sistema, channel, contact_id, token, created_at, updated_at, due_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [mb_substr($subject, 0, 200), 'nuevo', $priority, $system, $channel, $contact['id'], random_token(20), $ts, $ts, due_for($priority)]);
    $t = q1('SELECT * FROM tickets WHERE id = ?', [(int)db()->lastInsertId()]);
    add_message($t, 'contact', (int)$contact['id'], $body, false, $channel, $files, $msgid, false);

    // confirmación al cliente
    $num = ticket_number((int)$t['id']);
    [$tx, $ht] = email_template(
        "Recibimos tu reclamo $num",
        ['Hola' . ($contact['name'] ? ' ' . $contact['name'] : '') . ', registramos tu pedido "' . $t['subject'] . '" con prioridad ' . mb_strtolower(priority_label($priority)) . '.',
         'Podés seguirlo, sumar información o adjuntos desde el enlace de abajo, o respondiendo este mail sin cambiar el asunto.'],
        ['Ver mi reclamo', ticket_link($t)],
        hours_note()
    );
    send_mail($contact['email'], "[$num] Recibimos tu reclamo: {$t['subject']}", $tx, $ht, ['auto' => true, 'in_reply_to' => $msgid]);

    notify_team($t, "Nuevo reclamo $num · " . priority_label($priority), [
        ($contact['organization'] ?: $contact['email']) . ' · ' . ($system ?: 'Sin sistema indicado'),
        $t['subject'], mb_substr($body, 0, 600)], $priority === 'urgente');
    return $t;
}

function add_message(array $t, string $authorType, ?int $authorId, string $body, bool $internal, string $via, array $files = [], ?string $msgid = null, bool $notify = true): int
{
    q('INSERT INTO messages (ticket_id, author_type, author_id, body, internal, via, email_msgid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [$t['id'], $authorType, $authorId, $body, $internal ? 1 : 0, $via, $msgid, now()]);
    $mid = (int)db()->lastInsertId();
    if ($files) store_attachments($mid, $files);

    $num = ticket_number((int)$t['id']);
    if ($authorType === 'contact') {
        $newStatus = in_array($t['status'], ['esperando', 'resuelto', 'cerrado'], true) ? 'abierto' : $t['status'];
        q('UPDATE tickets SET updated_at = ?, status = ?, closed_at = NULL WHERE id = ?', [now(), $newStatus, $t['id']]);
        if ($notify) {
            $c = q1('SELECT * FROM contacts WHERE id = ?', [$t['contact_id']]);
            notify_team($t, "Respuesta del cliente en $num", [($c['name'] ?: $c['email']) . ' escribió:', mb_substr($body, 0, 800)], $t['priority'] === 'urgente', $t['assignee_id'] ? (int)$t['assignee_id'] : null);
        }
    } elseif ($authorType === 'staff' && !$internal) {
        q('UPDATE tickets SET updated_at = ?, first_response_at = COALESCE(first_response_at, ?) WHERE id = ?', [now(), now(), $t['id']]);
        $c = q1('SELECT * FROM contacts WHERE id = ?', [$t['contact_id']]);
        $u = q1('SELECT name FROM users WHERE id = ?', [$authorId]);
        [$tx, $ht] = email_template(
            "Respuesta a tu reclamo $num",
            [$body, '— ' . ($u['name'] ?? 'Equipo LTELC') . ', LTELC'],
            ['Ver el reclamo completo', ticket_link($t)],
            'Para responder, contestá este mail sin cambiar el asunto o usá el enlace.'
        );
        send_mail($c['email'], "Re: [$num] {$t['subject']}", $tx, $ht);
    } else {
        q('UPDATE tickets SET updated_at = ? WHERE id = ?', [now(), $t['id']]);
    }
    return $mid;
}

function set_status(array $t, string $status): void
{
    if (!isset(LT_STATUSES[$status])) return;
    $closed = in_array($status, ['resuelto', 'cerrado'], true) ? now() : null;
    q('UPDATE tickets SET status = ?, closed_at = ?, updated_at = ? WHERE id = ?', [$status, $closed, now(), $t['id']]);
}

function ticket_messages(int $ticketId, bool $includeInternal): array
{
    $msgs = q('SELECT m.*, u.name AS staff_name, c.name AS contact_name, c.email AS contact_email FROM messages m
               LEFT JOIN users u ON (m.author_type = \'staff\' AND u.id = m.author_id)
               LEFT JOIN contacts c ON (m.author_type = \'contact\' AND c.id = m.author_id)
               WHERE m.ticket_id = ?' . ($includeInternal ? '' : ' AND m.internal = 0') . ' ORDER BY m.id', [$ticketId])->fetchAll();
    foreach ($msgs as &$m) $m['files'] = q('SELECT id, filename, size FROM attachments WHERE message_id = ?', [$m['id']])->fetchAll();
    return $msgs;
}

/* ---------------- avisos al equipo (respetando el horario) ---------------- */
function notify_team(array $t, string $subject, array $lines, bool $urgent, ?int $onlyUserId = null): void
{
    $users = $onlyUserId
        ? q('SELECT * FROM users WHERE id = ? AND active = 1', [$onlyUserId])->fetchAll()
        : q('SELECT * FROM users WHERE active = 1')->fetchAll();
    [$tx, $ht] = email_template($subject, $lines, ['Abrir en el panel', base_url('panel/ticket.php?id=' . $t['id'])],
        'Respondé al cliente desde el panel: si contestás este aviso, la respuesta no le llega.');
    $when = $urgent ? new DateTimeImmutable() : next_business_start();
    // equipo + casillas que reciben copia de todo (config notify_copy)
    $to = array_map(fn($u) => mb_strtolower($u['email']), $users);
    foreach ((array)cfg('notify_copy', ['agencialtelc@gmail.com']) as $copy) $to[] = mb_strtolower(trim($copy));
    foreach (array_unique(array_filter($to)) as $email) {
        q('INSERT INTO notify_queue (to_email, subject, body_text, body_html, send_after, created_at) VALUES (?, ?, ?, ?, ?, ?)',
          [$email, $subject, $tx, $ht, $when->format('Y-m-d H:i:s'), now()]);
    }
    if ($urgent || in_business_hours()) process_queue(20);
}
/** Envía los avisos pendientes cuya hora ya llegó. */
function process_queue(int $limit = 50): int
{
    $rows = q('SELECT * FROM notify_queue WHERE sent_at IS NULL AND send_after <= ? ORDER BY id LIMIT ' . (int)$limit, [now()])->fetchAll();
    $n = 0;
    foreach ($rows as $r) {
        if (send_mail($r['to_email'], $r['subject'], $r['body_text'], $r['body_html'])) {
            q('UPDATE notify_queue SET sent_at = ? WHERE id = ?', [now(), $r['id']]);
            $n++;
        }
    }
    return $n;
}

/* ---------------- mails entrantes ---------------- */
/** Quita el texto citado de una respuesta por mail. */
function strip_quoted(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $out = [];
    foreach (explode("\n", $text) as $line) {
        $l = trim($line);
        if (preg_match('/^(El|On) .{4,200}(escribió|wrote):$/iu', $l)) break;
        if (preg_match('/^-{2,}\s*(Mensaje original|Original Message)/iu', $l)) break;
        if (preg_match('/^(De|From):\s.+@/iu', $l) && count($out) > 0) break;
        if (str_starts_with($l, '>')) continue;
        $out[] = rtrim($line);
    }
    return trim(implode("\n", $out));
}

/**
 * Procesa un mail recibido: si el asunto trae [LT-00012] lo suma a ese ticket;
 * si no, abre un ticket nuevo. Devuelve el id del ticket o null si se ignoró.
 */
function ingest_email(string $fromEmail, string $fromName, string $subject, string $text, array $attachments = [], ?string $msgid = null, bool $autoSubmitted = false): ?int
{
    $fromEmail = mb_strtolower(trim($fromEmail));
    if ($autoSubmitted || $fromEmail === mb_strtolower((string)cfg('mail_from')) || preg_match('/^(mailer-daemon|postmaster|no-?reply)@/i', $fromEmail)) return null;
    if ($msgid && q1('SELECT id FROM messages WHERE email_msgid = ?', [$msgid])) return null;   // ya procesado
    $subject = trim($subject) ?: '(sin asunto)';
    $body = strip_quoted($text);
    if ($body === '' && !$attachments) $body = '(mensaje sin texto)';

    if (preg_match('/\[LT-(\d{1,9})\]/', $subject, $m)) {
        $t = q1('SELECT * FROM tickets WHERE id = ?', [(int)$m[1]]);
        if ($t) {
            $c = q1('SELECT * FROM contacts WHERE id = ?', [$t['contact_id']]);
            $staffUser = q1('SELECT * FROM users WHERE email = ? AND active = 1', [$fromEmail]);
            if ($c && $c['email'] === $fromEmail) { add_message($t, 'contact', (int)$c['id'], $body, false, 'email', $attachments, $msgid); return (int)$t['id']; }
            if ($staffUser) { add_message($t, 'staff', (int)$staffUser['id'], $body, false, 'email', $attachments, $msgid); return (int)$t['id']; }
            // otra persona de la misma organización: queda como nota interna para revisar
            add_message($t, 'contact', null, "Mensaje de $fromName <$fromEmail>:\n\n$body", true, 'email', $attachments, $msgid);
            return (int)$t['id'];
        }
    }
    $contact = find_or_create_contact($fromEmail, $fromName);
    $clean = trim(preg_replace('/^((re|rv|fw|fwd)\s*:\s*)+/i', '', $subject));
    $t = create_ticket($contact, $clean ?: '(sin asunto)', $body, 'normal', '', 'email', $attachments, $msgid);
    return (int)$t['id'];
}

/* ---------------- acceso de clientes por enlace ---------------- */
function send_login_link(string $email): void
{
    $c = q1('SELECT * FROM contacts WHERE email = ?', [mb_strtolower(trim($email))]);
    if (!$c) return;   // no revelamos si el mail existe
    $token = random_token(24);
    q('INSERT INTO login_tokens (contact_id, token_hash, expires_at) VALUES (?, ?, ?)', [$c['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 1800)]);
    [$tx, $ht] = email_template('Tu acceso a Soporte LTELC', ['Usá este enlace para ver todos tus reclamos. Vence en 30 minutos y sirve una sola vez.'], ['Entrar a mis reclamos', base_url('acceso.php?k=' . $token)], 'Si no lo pediste, podés ignorar este mail.');
    send_mail($c['email'], 'Tu acceso a Soporte LTELC', $tx, $ht, ['auto' => true]);
}
function consume_login_token(string $token): ?array
{
    $row = q1('SELECT * FROM login_tokens WHERE token_hash = ? AND used = 0 AND expires_at > ?', [hash('sha256', $token), now()]);
    if (!$row) return null;
    q('UPDATE login_tokens SET used = 1 WHERE id = ?', [$row['id']]);
    return q1('SELECT * FROM contacts WHERE id = ?', [$row['contact_id']]);
}

function human_size(int $b): string
{
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}
function fmt_date(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return date('d/m', $t) . (date('Y', $t) !== date('Y') ? date('/Y', $t) : '') . ' ' . date('H:i', $t);
}
