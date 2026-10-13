<?php
declare(strict_types=1);

define('LT_ROOT', dirname(__DIR__));

// La configuración (claves) vive FUERA de public_html para que el deploy desde GitHub no la toque.
// Se busca soporte-config.php en la carpeta personal del hosting (/home/usuario/) y en las carpetas
// superiores a este sistema; también sirve config.php junto al sistema o la variable LT_SOPORTE_CONFIG.
$cfgFile = getenv('LT_SOPORTE_CONFIG') ?: '';
$cands = [];
$home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : '');
if ($home) $cands[] = rtrim($home, '/') . '/soporte-config.php';
if (preg_match('~^(/home/[^/]+)/~', LT_ROOT, $m)) $cands[] = $m[1] . '/soporte-config.php';
for ($d = dirname(LT_ROOT); $d !== dirname($d); $d = dirname($d)) $cands[] = $d . '/soporte-config.php';
$cands[] = LT_ROOT . '/config.php';
foreach ($cands as $cand) {
    if ($cfgFile === '' && @is_file($cand)) $cfgFile = $cand;
}
define('LT_CFG_DIR', $cfgFile !== '' ? dirname($cfgFile) : dirname(LT_ROOT, 2));
if ($cfgFile === '' || !is_file($cfgFile)) {
    http_response_code(500);
    exit('Soporte LTELC: falta la configuración (crear soporte-config.php en la carpeta personal del hosting, a partir de config.sample.php).');
}
$GLOBALS['LT_CFG'] = require $cfgFile;
date_default_timezone_set(cfg('timezone', 'America/Argentina/Buenos_Aires'));

function cfg(string $key, $default = null)
{
    return $GLOBALS['LT_CFG'][$key] ?? $default;
}

/* ---------------- base de datos ---------------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $d = cfg('db');
    if (($d['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $d['path']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass']);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q1(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}
function now(): string { return date('Y-m-d H:i:s'); }

/* ---------------- utilidades ---------------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path = ''): string { return rtrim(cfg('base_url'), '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . (preg_match('~^https?://~', $path) ? $path : base_url($path))); exit; }
function random_token(int $bytes = 24): string { return bin2hex(random_bytes($bytes)); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }
function is_cli(): bool { return PHP_SAPI === 'cli'; }

/* ---------------- sesión y seguridad ---------------- */
if (!is_cli() && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with((string)cfg('base_url'), 'https://');
    session_name('ltsop');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = random_token(16);
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('La sesión expiró. Volvé atrás y recargá la página.');
    }
}
function flash(?string $msg = null, string $kind = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = [$kind, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
/** Limita acciones por IP: máximo $max en la última hora. */
function rate_limited(string $action, int $max): bool
{
    $since = date('Y-m-d H:i:s', time() - 3600);
    $n = (int)q('SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > ?', [client_ip(), $action, $since])->fetchColumn();
    if ($n >= $max) return true;
    q('INSERT INTO rate_limits (ip, action, created_at) VALUES (?, ?, ?)', [client_ip(), $action, now()]);
    return false;
}

/* ---------------- usuarios del equipo ---------------- */
function staff(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = empty($_SESSION['staff_id']) ? null : q1('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['staff_id']]);
    return $u;
}
function require_staff(): array
{
    $u = staff();
    if (!$u) redirect('panel/login.php');
    return $u;
}
/** Contacto (cliente) con sesión iniciada por enlace mágico. */
function contact(): ?array
{
    return empty($_SESSION['contact_id']) ? null : q1('SELECT * FROM contacts WHERE id = ?', [$_SESSION['contact_id']]);
}

require __DIR__ . '/schema.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/tickets.php';
require __DIR__ . '/view.php';
require __DIR__ . '/mail_in.php';
