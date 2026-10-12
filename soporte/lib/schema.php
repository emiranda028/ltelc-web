<?php
declare(strict_types=1);

/** Crea las tablas si no existen (MySQL o SQLite). */
function install_schema(): void
{
    $sqlite = (cfg('db')['driver'] ?? 'mysql') === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $fk = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $tables = [
        "users (
            id $pk,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            pass_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'agente',
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )",
        "contacts (
            id $pk,
            name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL UNIQUE,
            organization VARCHAR(160) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        )",
        "tickets (
            id $pk,
            subject VARCHAR(200) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'nuevo',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            system VARCHAR(80) NOT NULL DEFAULT '',
            channel VARCHAR(20) NOT NULL DEFAULT 'web',
            contact_id $fk NOT NULL,
            assignee_id $fk NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            due_at DATETIME NULL,
            first_response_at DATETIME NULL,
            closed_at DATETIME NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
        )",
        "messages (
            id $pk,
            ticket_id $fk NOT NULL,
            author_type VARCHAR(10) NOT NULL,
            author_id $fk NULL,
            body TEXT NOT NULL,
            internal TINYINT NOT NULL DEFAULT 0,
            via VARCHAR(10) NOT NULL DEFAULT 'web',
            email_msgid VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        )",
        "attachments (
            id $pk,
            message_id $fk NOT NULL,
            filename VARCHAR(200) NOT NULL,
            stored VARCHAR(120) NOT NULL,
            mime VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
            size INT NOT NULL DEFAULT 0,
            FOREIGN KEY (message_id) REFERENCES messages(id)
        )",
        "login_tokens (
            id $pk,
            contact_id $fk NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT NOT NULL DEFAULT 0
        )",
        "notify_queue (
            id $pk,
            to_email VARCHAR(190) NOT NULL,
            subject VARCHAR(250) NOT NULL,
            body_text TEXT NOT NULL,
            body_html TEXT NOT NULL,
            send_after DATETIME NOT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )",
        "rate_limits (
            id $pk,
            ip VARCHAR(64) NOT NULL,
            action VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL
        )",
    ];
    foreach ($tables as $t) db()->exec('CREATE TABLE IF NOT EXISTS ' . $t . $tail);

    $idx = [
        'idx_tickets_status ON tickets (status)',
        'idx_tickets_contact ON tickets (contact_id)',
        'idx_messages_ticket ON messages (ticket_id)',
        'idx_messages_msgid ON messages (email_msgid)',
        'idx_queue_pending ON notify_queue (sent_at, send_after)',
        'idx_rate ON rate_limits (ip, action, created_at)',
    ];
    foreach ($idx as $i) {
        try { db()->exec(($sqlite ? 'CREATE INDEX IF NOT EXISTS ' : 'CREATE INDEX ') . $i); } catch (PDOException $e) { /* ya existe */ }
    }
}

function schema_ready(): bool
{
    try { q('SELECT 1 FROM users LIMIT 1'); return true; } catch (PDOException $e) { return false; }
}
