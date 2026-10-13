<?php
/*
 * Configuración de Soporte LTELC.
 * Copiar este archivo como  /home/USUARIO/soporte-config.php  (carpeta personal del hosting,
 * FUERA de public_html: así no se publica ni lo pisa el deploy desde GitHub) y completar las claves.
 */
return [
    'base_url' => 'https://ltelc.tech/soporte',
    'timezone' => 'America/Argentina/Buenos_Aires',

    // Base de datos MySQL creada en hPanel → Bases de datos
    'db' => ['driver' => 'mysql', 'host' => 'localhost', 'name' => 'u000000000_soporte', 'user' => 'u000000000_soporte', 'pass' => 'CAMBIAR'],

    // Casilla soporte@ltelc.tech creada en hPanel → Correos
    'mail_from' => 'soporte@ltelc.tech',
    'mail_from_name' => 'Soporte LTELC',
    // Copia de todos los avisos del equipo (reclamos nuevos, respuestas de clientes, asignaciones)
    'notify_copy' => ['agencialtelc@gmail.com'],
    'smtp' => ['host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl', 'user' => 'soporte@ltelc.tech', 'pass' => 'CAMBIAR'],
    'imap' => ['host' => 'imap.hostinger.com', 'port' => 993, 'user' => 'soporte@ltelc.tech', 'pass' => 'CAMBIAR', 'folder' => 'INBOX', 'done_folder' => 'Procesados'],

    // Claves largas al azar (por ejemplo, 40 letras y números)
    'cron_key' => 'CAMBIAR',
    'install_key' => 'CAMBIAR',

    // Horario de atención: los avisos no urgentes al equipo esperan a este horario
    'hours' => ['days' => [1, 2, 3, 4, 5], 'start' => 9, 'end' => 18],
    // Plazo de primera respuesta, en horas hábiles
    'sla_hours' => ['urgente' => 2, 'alta' => 4, 'normal' => 9, 'baja' => 27],

    'systems' => ['Bitácora', 'Gestión Hotelera', 'Hospital El Cruce', 'Panel ABSA', 'GGA', 'Control Profeed', 'Trabajar en Saladillo', 'Atlas Político Mundial', 'Otro'],

    // Adjuntos: fuera de public_html
    // 'upload_dir' => '/home/USUARIO/soporte-uploads',   // por defecto, junto a este archivo
    'max_upload_mb' => 10,
];
