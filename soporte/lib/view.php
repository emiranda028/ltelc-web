<?php
declare(strict_types=1);

function page_head(string $title, string $area = 'public'): void
{
    $u = $area === 'panel' ? staff() : null;
    $css = base_url('assets/soporte.css') . '?v=4';
    echo '<!DOCTYPE html><html lang="es-AR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · Soporte LTELC</title><meta name="robots" content="noindex">';
    echo '<link rel="icon" href="' . h(base_url('../favicon.svg')) . '" type="image/svg+xml">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">';
    echo '<link rel="stylesheet" href="' . h($css) . '"></head><body class="area-' . h($area) . '">';
    echo '<header class="top"><div class="wrap top-in">';
    echo '<a class="brand" href="' . h(base_url($area === 'panel' ? 'panel/' : '')) . '">' . logo_svg() . '<b>LTELC</b><span>' . ($area === 'panel' ? 'Panel de soporte' : 'Soporte') . '</span></a>';
    echo '<nav class="top-nav">';
    if ($area === 'panel' && $u) {
        echo '<a href="' . h(base_url('panel/')) . '">Tickets</a>';
        if ($u['role'] === 'admin') echo '<a href="' . h(base_url('panel/usuarios.php')) . '">Equipo</a>';
        echo '<span class="who">' . h($u['name']) . '</span><a class="btn-sm" href="' . h(base_url('panel/salir.php')) . '">Salir</a>';
    } elseif ($area === 'public') {
        echo '<a href="' . h(base_url('')) . '">Nuevo reclamo</a><a href="' . h(base_url('mis-reclamos.php')) . '">Mis reclamos</a><a class="btn-sm" href="https://ltelc.tech/">ltelc.tech</a>';
    }
    echo '</nav></div></header><main class="wrap main">';
    $f = flash();
    if ($f) echo '<div class="flash flash-' . h($f[0]) . '" role="status">' . h($f[1]) . '</div>';
}

function page_foot(): void
{
    echo '</main><footer class="foot"><div class="wrap">LTELC · Lo Tengo en la Cabeza · <a href="mailto:' . h(cfg('mail_from')) . '">' . h(cfg('mail_from')) . '</a></div></footer></body></html>';
}

function logo_svg(): string
{
    return '<svg viewBox="0 0 32 32" aria-hidden="true" width="30" height="30"><rect width="32" height="32" rx="9" fill="#060E1D"/><circle cx="16" cy="13" r="7.5" fill="none" stroke="#0A5BFF" stroke-width="2"/><path d="M9 25h14" stroke="#EAF0FA" stroke-width="2.2" stroke-linecap="round"/><circle cx="16" cy="13" r="2.2" fill="#FFB547"/></svg>';
}

function status_chip(string $s): string { return '<span class="chip st-' . h($s) . '">' . h(status_label($s)) . '</span>'; }
function priority_chip(string $p): string { return '<span class="chip pr-' . h($p) . '">' . h(priority_label($p)) . '</span>'; }

/** Hilo de mensajes. $staffView muestra notas internas y nombres del equipo. */
function render_thread(array $msgs, string $dlBase, bool $staffView): void
{
    echo '<ol class="thread">';
    foreach ($msgs as $m) {
        $cls = $m['internal'] ? 'note' : ($m['author_type'] === 'staff' ? 'staff' : ($m['author_type'] === 'system' ? 'system' : 'client'));
        $who = $m['author_type'] === 'staff' ? ($m['staff_name'] ?: 'Equipo LTELC') . ' · LTELC'
             : ($m['author_type'] === 'contact' ? ($m['contact_name'] ?: ($m['contact_email'] ?: 'Cliente')) : 'Sistema');
        if ($m['internal']) $who = $m['author_type'] === 'system' ? 'Registro' : ('Nota interna · ' . ($m['staff_name'] ?: ($m['contact_name'] ?: 'equipo')));
        $via = ['email' => 'por mail', 'web' => 'desde la web', 'panel' => 'desde el panel'][$m['via']] ?? '';
        echo '<li class="msg msg-' . $cls . '"><div class="msg-head"><b>' . h($who) . '</b><span>' . h(fmt_date($m['created_at'])) . ($staffView && $via ? ' · ' . h($via) : '') . '</span></div>';
        echo '<div class="msg-body">' . nl2br(h($m['body'])) . '</div>';
        if ($m['files']) {
            echo '<ul class="files">';
            foreach ($m['files'] as $f) echo '<li><a href="' . h($dlBase . (str_contains($dlBase, '?') ? '&' : '?') . 'id=' . $f['id']) . '">' . h($f['filename']) . '</a> <small>' . h(human_size((int)$f['size'])) . '</small></li>';
            echo '</ul>';
        }
        echo '</li>';
    }
    echo '</ol>';
}

function sla_badge(array $t): string
{
    if (!in_array($t['status'], LT_OPEN, true) || !$t['due_at'] || $t['first_response_at']) return '';
    $left = strtotime($t['due_at']) - time();
    if ($left < 0) return '<span class="sla late">Vencido</span>';
    if ($left < 7200) return '<span class="sla soon">Vence ' . h(date('H:i', strtotime($t['due_at']))) . '</span>';
    return '<span class="sla ok">Vence ' . h(fmt_date($t['due_at'])) . '</span>';
}
