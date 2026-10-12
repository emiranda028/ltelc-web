<?php
declare(strict_types=1);

/*
 * Mails entrantes sin depender de la extensión imap de PHP (que desde PHP 8.4 no viene incluida):
 * un cliente IMAP mínimo sobre SSL y un lector MIME propio.
 */

final class LtImap
{
    private $s;
    private int $n = 0;

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->s = @stream_socket_client("ssl://$host:$port", $errno, $err, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->s) throw new RuntimeException("IMAP: no se pudo conectar a $host:$port ($err)");
        stream_set_timeout($this->s, 30);
        fgets($this->s);   // saludo
        $this->cmd('LOGIN ' . $this->quote($user) . ' ' . $this->quote($pass));
    }

    private function quote(string $s): string { return '"' . addcslashes($s, '"\\') . '"'; }

    /** Envía un comando y devuelve las líneas sin etiquetar (con literales {n} ya incorporados). */
    public function cmd(string $c): array
    {
        $tag = 'A' . (++$this->n);
        fwrite($this->s, "$tag $c\r\n");
        $out = [];
        while (true) {
            $line = fgets($this->s);
            if ($line === false) throw new RuntimeException('IMAP: se cortó la conexión');
            while (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {   // literal: leer exactamente n bytes
                $need = (int)$m[1]; $buf = '';
                while (strlen($buf) < $need) { $chunk = fread($this->s, $need - strlen($buf)); if ($chunk === false || $chunk === '') throw new RuntimeException('IMAP: literal incompleto'); $buf .= $chunk; }
                $line .= $buf . fgets($this->s);
            }
            if (str_starts_with($line, "$tag ")) {
                if (!preg_match("/^$tag OK/", $line)) throw new RuntimeException('IMAP: ' . trim(substr($line, strlen($tag) + 1)));
                return $out;
            }
            $out[] = $line;
        }
    }

    public function select(string $box): void { $this->cmd('SELECT ' . $this->quote($box)); }
    public function ensure(string $box): void { try { $this->cmd('CREATE ' . $this->quote($box)); } catch (RuntimeException $e) { /* ya existe */ } }

    /** @return int[] UIDs de la carpeta seleccionada */
    public function uids(): array
    {
        foreach ($this->cmd('UID SEARCH ALL') as $l) if (str_starts_with($l, '* SEARCH')) return array_map('intval', preg_split('/\s+/', trim(substr($l, 8)), -1, PREG_SPLIT_NO_EMPTY));
        return [];
    }

    public function fetch(int $uid): ?string
    {
        foreach ($this->cmd("UID FETCH $uid (BODY.PEEK[])") as $l) {
            if (preg_match('/BODY\[\] \{(\d+)\}\r\n/', $l, $m, PREG_OFFSET_CAPTURE)) return substr($l, $m[0][1] + strlen($m[0][0]), (int)$m[1][0]);
        }
        return null;
    }

    /** Mueve el mensaje a otra carpeta (copia + borrado, compatible con cualquier servidor). */
    public function move(int $uid, string $box): void
    {
        $this->cmd("UID COPY $uid " . $this->quote($box));
        $this->cmd("UID STORE $uid +FLAGS.SILENT (\\Deleted)");
    }

    public function close(): void
    {
        try { $this->cmd('EXPUNGE'); $this->cmd('LOGOUT'); } catch (RuntimeException $e) {}
        fclose($this->s);
    }
}

/* ---------------- lector MIME ---------------- */
function mime_split(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    if (str_starts_with($raw, "\n")) return [[], substr($raw, 1)];   // parte sin encabezados
    $p = strpos($raw, "\n\n");
    $head = $p === false ? $raw : substr($raw, 0, $p);
    $body = $p === false ? '' : substr($raw, $p + 2);
    $headers = [];
    foreach (explode("\n", preg_replace("/\n[ \t]+/", ' ', $head)) as $l) {
        if (!str_contains($l, ':')) continue;
        [$k, $v] = explode(':', $l, 2);
        $headers[strtolower(trim($k))][] = trim($v);
    }
    return [$headers, $body];
}
function mime_h(array $headers, string $k): string { return $headers[$k][0] ?? ''; }
function mime_decode_header(string $s): string
{
    if (!str_contains($s, '=?')) return $s;
    $s = preg_replace('/\?=\s+=\?/', '?==?', $s);   // palabras codificadas contiguas
    $d = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return $d === false ? $s : $d;
}
/** Parámetro de un header (charset, boundary, filename, name), incluido el formato RFC 2231. */
function mime_param(string $header, string $name): string
{
    if (preg_match('/' . $name . '\*=([^\'";]*)\'[^\']*\'([^;]+)/i', $header, $m)) return to_utf8(rawurldecode(trim($m[2])), $m[1] ?: 'UTF-8');
    if (preg_match('/' . $name . '\*0\*?=/i', $header)) {   // partido en varias piezas
        preg_match_all('/' . $name . '\*(\d+)(\*?)="?([^";]*)"?/i', $header, $mm, PREG_SET_ORDER);
        $v = ''; $cs = 'UTF-8';
        foreach ($mm as $m) { $piece = $m[3]; if ($m[2] === '*') { if ($m[1] === '0' && preg_match("/^([^']*)'[^']*'(.*)$/", $piece, $x)) { $cs = $x[1] ?: $cs; $piece = $x[2]; } $piece = rawurldecode($piece); } $v .= $piece; }
        return to_utf8($v, $cs);
    }
    if (preg_match('/(?:^|;)\s*' . $name . '\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/i', $header, $m)) return mime_decode_header(stripslashes($m[1]));
    if (preg_match('/(?:^|;)\s*' . $name . '\s*=\s*([^;\s]+)/i', $header, $m)) return mime_decode_header($m[1]);
    return '';
}
function to_utf8(string $s, string $charset): string
{
    $charset = strtoupper(trim($charset)) ?: 'UTF-8';
    if ($charset === 'UTF-8' || $charset === 'US-ASCII') return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    $r = @iconv($charset, 'UTF-8//IGNORE', $s);
    return $r === false ? mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1') : $r;
}
function html_to_text(string $html): string
{
    $html = preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is', '', $html);
    $html = preg_replace('~<(br|/p|/div|/tr|/li|/h\d)[^>]*>~i', "\n", $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}

/** Recorre las partes de un mail y junta texto y adjuntos. */
function mime_walk(array $headers, string $body, array &$acc, int $depth = 0): void
{
    $ct = mime_h($headers, 'content-type') ?: 'text/plain';
    $type = strtolower(trim(explode(';', $ct)[0]));
    if ($depth < 8 && str_starts_with($type, 'multipart/') && ($b = mime_param($ct, 'boundary')) !== '') {
        $parts = explode("\n--" . $b, "\n" . $body);
        array_shift($parts);   // preámbulo
        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) break;   // cierre
            [$ph, $pb] = mime_split(preg_replace('/^[ \t]*\n/', '', $part, 1));
            mime_walk($ph, $pb, $acc, $depth + 1);
        }
        return;
    }
    $enc = strtolower(mime_h($headers, 'content-transfer-encoding'));
    if ($enc === 'base64') $body = (string)base64_decode(preg_replace('/\s+/', '', $body));
    elseif ($enc === 'quoted-printable') $body = quoted_printable_decode($body);
    $cd = mime_h($headers, 'content-disposition');
    $fname = mime_param($cd, 'filename') ?: mime_param($ct, 'name');
    $isAttach = $fname !== '' || stripos($cd, 'attachment') === 0;
    if (!$isAttach && $type === 'text/plain') { $acc['text'] .= ($acc['text'] ? "\n" : '') . to_utf8($body, mime_param($ct, 'charset')); return; }
    if (!$isAttach && $type === 'text/html') { $acc['html'] .= to_utf8($body, mime_param($ct, 'charset')); return; }
    if ($type === 'message/rfc822' && !$isAttach) { [$ih, $ib] = mime_split($body); mime_walk($ih, $ib, $acc, $depth + 1); return; }
    // imágenes incrustadas chicas (logos de firmas) no se guardan
    if (!$isAttach && mime_h($headers, 'content-id') !== '' && strlen($body) < 20000) return;
    if ($isAttach || str_starts_with($type, 'image/')) {
        $name = basename(str_replace('\\', '/', $fname ?: ('adjunto.' . (explode('/', $type)[1] ?? 'bin'))));
        $acc['files'][] = ['name' => $name, 'data' => $body, 'size' => strlen($body), 'mime' => $type];
    }
}

/** Convierte un mail crudo en los datos que necesita ingest_email(). */
function parse_email(string $raw): array
{
    [$h, $b] = mime_split($raw);
    $acc = ['text' => '', 'html' => '', 'files' => []];
    mime_walk($h, $b, $acc);
    $from = mime_decode_header(mime_h($h, 'from'));
    $email = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : (preg_match('/[^\s<>"]+@[^\s<>"]+/', $from, $m) ? $m[0] : '');
    $name = trim(preg_replace('/<[^>]*>/', '', $from), " \"'\t");
    if ($name === $email) $name = '';
    $replyTo = mime_h($h, 'reply-to');
    $auto = mime_h($h, 'auto-submitted');
    $max = (int)cfg('max_upload_mb', 10) * 1024 * 1024;
    return [
        'from' => mb_strtolower(trim($email)),
        'name' => $name,
        'reply_to' => preg_match('/[^\s<>"]+@[^\s<>"]+/', $replyTo, $m) ? $m[0] : '',
        'subject' => mime_decode_header(mime_h($h, 'subject')),
        'text' => $acc['text'] !== '' ? $acc['text'] : html_to_text($acc['html']),
        'files' => array_values(array_filter($acc['files'], fn($f) => $f['size'] > 0 && $f['size'] <= $max)),
        'msgid' => trim(mime_h($h, 'message-id'), " <>") ?: null,
        'auto' => ($auto !== '' && strtolower($auto) !== 'no') || mime_h($h, 'x-autoreply') !== '' || mime_h($h, 'x-autorespond') !== ''
                  || in_array(strtolower(mime_h($h, 'precedence')), ['bulk', 'junk', 'auto_reply', 'list'], true),
    ];
}

/** Lee la casilla de soporte, convierte cada mail en ticket o respuesta y lo archiva. */
function fetch_mailbox(int $limit = 40): array
{
    $c = cfg('imap');
    if (!$c || empty($c['host'])) return ['ok' => false, 'msg' => 'IMAP sin configurar'];
    $im = new LtImap($c['host'], (int)($c['port'] ?? 993), $c['user'], $c['pass']);
    $done = $c['done_folder'] ?? 'Procesados';
    $im->ensure($done);
    $im->select($c['folder'] ?? 'INBOX');
    $stats = ['ok' => true, 'leidos' => 0, 'tickets' => 0, 'ignorados' => 0];
    foreach (array_slice($im->uids(), 0, $limit) as $uid) {
        $raw = $im->fetch($uid);
        if ($raw === null) continue;
        $stats['leidos']++;
        $e = parse_email($raw);
        $id = $e['from'] ? ingest_email($e['from'], $e['name'], $e['subject'], $e['text'], $e['files'], $e['msgid'], $e['auto']) : null;
        $id ? $stats['tickets']++ : $stats['ignorados']++;
        $im->move($uid, $done);
    }
    $im->close();
    return $stats;
}
