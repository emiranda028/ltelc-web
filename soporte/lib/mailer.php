<?php
declare(strict_types=1);

/**
 * Envía un mail en texto + HTML. Usa SMTP si está configurado; si no, mail().
 * Devuelve true si el servidor lo aceptó.
 */
function send_mail(string $to, string $subject, string $text, string $html, array $opts = []): bool
{
    $fromEmail = cfg('mail_from');
    $fromName = cfg('mail_from_name', 'Soporte LTELC');
    $domain = substr(strrchr($fromEmail, '@') ?: '@ltelc.tech', 1);
    $msgId = $opts['message_id'] ?? ('<' . random_token(12) . '@' . $domain . '>');
    $boundary = 'lt' . random_token(10);

    $headers = [
        'From' => mime_header($fromName) . " <$fromEmail>",
        'Reply-To' => $opts['reply_to'] ?? $fromEmail,
        'Message-ID' => $msgId,
        'Date' => date('r'),
        'MIME-Version' => '1.0',
        'Content-Type' => "multipart/alternative; boundary=\"$boundary\"",
        'X-Mailer' => 'LTELC Soporte',
    ];
    if (!empty($opts['in_reply_to'])) { $ref = '<' . trim($opts['in_reply_to'], '<> ') . '>'; $headers['In-Reply-To'] = $ref; $headers['References'] = $ref; }
    if (!empty($opts['auto'])) $headers['Auto-Submitted'] = 'auto-replied';

    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text))
          . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html))
          . "--$boundary--\r\n";

    $smtp = cfg('smtp');
    try {
        if (!empty($smtp['host'])) return smtp_send($smtp, $fromEmail, $to, mime_header($subject), $headers, $body);
        $hdr = '';
        foreach ($headers as $k => $v) $hdr .= "$k: $v\r\n";
        return mail($to, mime_header($subject), $body, $hdr, '-f' . $fromEmail);
    } catch (Throwable $e) {
        error_log('LTELC soporte, mail a ' . $to . ' falló: ' . $e->getMessage());
        return false;
    }
}

function mime_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Cliente SMTP mínimo (SSL 465 o STARTTLS 587) con AUTH LOGIN. */
function smtp_send(array $c, string $from, string $to, string $subject, array $headers, string $body): bool
{
    $secure = $c['secure'] ?? 'ssl';
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . ($c['port'] ?? 465);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $c['verify_peer'] ?? true, 'verify_peer_name' => $c['verify_peer'] ?? true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP: no se pudo conectar ($errstr)");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function (string $line, array $ok) use ($fp, $read): string {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $ok, true)) throw new RuntimeException('SMTP: ' . trim($resp));
        return $resp;
    };
    $host = gethostname() ?: 'localhost';
    $cmd('', [220]);
    $cmd("EHLO $host", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP: STARTTLS falló');
        $cmd("EHLO $host", [250]);
    }
    if (!empty($c['user'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $msg = "To: $to\r\nSubject: $subject\r\n";
    foreach ($headers as $k => $v) $msg .= "$k: $v\r\n";
    $msg .= "\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', $msg);   // dot-stuffing
    fwrite($fp, $msg . "\r\n.\r\n");
    $cmd('', [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}

/** Plantilla de mail con la marca. $paragraphs: texto plano por párrafo; $cta: [texto, url]. */
function email_template(string $title, array $paragraphs, ?array $cta = null, string $footnote = ''): array
{
    $text = $title . "\n\n" . implode("\n\n", $paragraphs) . ($cta ? "\n\n{$cta[0]}: {$cta[1]}" : '') . ($footnote ? "\n\n$footnote" : '')
          . "\n\n— Soporte LTELC · " . cfg('mail_from');
    $p = '';
    foreach ($paragraphs as $para) $p .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#1C2B33">' . nl2br(h($para)) . '</p>';
    $btn = $cta ? '<p style="margin:22px 0"><a href="' . h($cta[1]) . '" style="background:#0A5BFF;color:#fff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;font-size:15px;display:inline-block">' . h($cta[0]) . '</a></p>' : '';
    $foot = $footnote ? '<p style="margin:18px 0 0;font-size:13px;color:#5F6B73">' . nl2br(h($footnote)) . '</p>' : '';
    $html = '<!doctype html><html><body style="margin:0;background:#F1F4F7;font-family:Segoe UI,Helvetica,Arial,sans-serif">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F4F7;padding:28px 12px"><tr><td align="center">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:18px;overflow:hidden">'
          . '<tr><td style="background:#060E1D;padding:20px 28px;color:#EAF0FA;font-weight:800;letter-spacing:.06em;font-size:18px">LTELC <span style="font-weight:500;letter-spacing:0;font-size:13px;color:#93A3BC">· Soporte</span></td></tr>'
          . '<tr><td style="padding:28px">'
          . '<h1 style="margin:0 0 16px;font-size:21px;line-height:1.25;color:#1C2B33">' . h($title) . '</h1>' . $p . $btn . $foot
          . '</td></tr><tr><td style="padding:16px 28px;background:#F7F9FB;font-size:12px;color:#5F6B73">LTELC · Lo Tengo en la Cabeza · ltelc.tech</td></tr>'
          . '</table></td></tr></table></body></html>';
    return [$text, $html];
}
