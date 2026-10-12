<?php
require __DIR__ . '/lib/bootstrap.php';

$c = consume_login_token((string)($_GET['k'] ?? ''));
if (!$c) {
    page_head('Enlace vencido');
    echo '<section class="card" style="max-width:560px;margin:20px auto"><h1>El enlace <b>venció</b></h1><p class="lead">Los enlaces de acceso duran 30 minutos y sirven una sola vez. Pedí uno nuevo y entrás en un minuto.</p><a class="btn" href="' . h(base_url('mis-reclamos.php')) . '">Pedir un enlace nuevo</a></section>';
    page_foot();
    exit;
}
session_regenerate_id(true);
$_SESSION['contact_id'] = (int)$c['id'];
redirect('mis-reclamos.php');
