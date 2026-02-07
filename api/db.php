<?php
declare(strict_types=1);

// Shared DB for API + dashboard
$config = require __DIR__ . '/config.php';

function db_config(array $config): array {
    return [
        'driver' => $config['db_driver'] ?? 'sqlite',
        'sqlite_path' => $config['db_sqlite_path'] ?? (__DIR__ . '/data/marge.sqlite'),
        'host' => $config['db_host'] ?? 'localhost',
        'name' => $config['db_name'] ?? 'marge',
        'user' => $config['db_user'] ?? 'root',
        'pass' => $config['db_pass'] ?? '',
        'charset' => $config['db_charset'] ?? 'utf8mb4',
    ];
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = db_config(require __DIR__ . '/config.php');
    if ($cfg['driver'] === 'mysql') {
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $dsn = 'sqlite:' . $cfg['sqlite_path'];
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    db_init($pdo, $cfg['driver']);
    return $pdo;
}

function db_init(PDO $pdo, string $driver): void {
    if ($driver === 'mysql') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(30) NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            service VARCHAR(255) DEFAULT NULL,
            requested_date VARCHAR(50) DEFAULT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "Nouveau",
            priority VARCHAR(50) NOT NULL DEFAULT "Normal",
            notes TEXT DEFAULT NULL,
            source VARCHAR(50) NOT NULL DEFAULT "site",
            archived_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS devis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            reference VARCHAR(50) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT "XOF",
            status VARCHAR(30) NOT NULL DEFAULT "Brouillon",
            description TEXT DEFAULT NULL,
            client_name VARCHAR(255) DEFAULT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            client_phone VARCHAR(50) DEFAULT NULL,
            client_company VARCHAR(255) DEFAULT NULL,
            client_address VARCHAR(255) DEFAULT NULL,
            client_city VARCHAR(100) DEFAULT NULL,
            client_country VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            archived_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS devis_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            devis_id INT NOT NULL,
            label VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            pass_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT "admin",
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user VARCHAR(100) NOT NULL,
            role VARCHAR(30) NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity VARCHAR(50) NOT NULL,
            entity_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            service TEXT,
            requested_date TEXT,
            subject TEXT,
            message TEXT,
            details TEXT,
            status TEXT NOT NULL DEFAULT "Nouveau",
            priority TEXT NOT NULL DEFAULT "Normal",
            notes TEXT,
            source TEXT NOT NULL DEFAULT "site",
            archived_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS devis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_id INTEGER NOT NULL,
            reference TEXT NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT "XOF",
            status TEXT NOT NULL DEFAULT "Brouillon",
            description TEXT,
            client_name TEXT,
            client_email TEXT,
            client_phone TEXT,
            client_company TEXT,
            client_address TEXT,
            client_city TEXT,
            client_country TEXT,
            notes TEXT,
            archived_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS devis_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            devis_id INTEGER NOT NULL,
            label TEXT NOT NULL,
            quantity REAL NOT NULL DEFAULT 1,
            unit_price REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            pass_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "admin",
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user TEXT NOT NULL,
            role TEXT NOT NULL,
            action TEXT NOT NULL,
            entity TEXT NOT NULL,
            entity_id INTEGER,
            details TEXT,
            ip TEXT,
            created_at TEXT NOT NULL
        );');

        // Ensure columns exist for older sqlite db
        $columns = [
            'description TEXT',
            'client_name TEXT',
            'client_email TEXT',
            'client_phone TEXT',
            'client_company TEXT',
            'client_address TEXT',
            'client_city TEXT',
            'client_country TEXT',
            'notes TEXT',
            'archived_at TEXT'
        ];
        foreach ($columns as $col) {
            try { $pdo->exec('ALTER TABLE devis ADD COLUMN ' . $col); } catch (Throwable $e) { }
        }
        try { $pdo->exec('ALTER TABLE leads ADD COLUMN archived_at TEXT'); } catch (Throwable $e) { }
    }
}

function audit_log(string $action, string $entity, ?int $entityId = null, array $details = [], string $user = '', string $role = ''): void {
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user, role, action, entity, entity_id, details, ip, created_at)
        VALUES (:user, :role, :action, :entity, :entity_id, :details, :ip, :created_at)');
    $stmt->execute([
        ':user' => $user,
        ':role' => $role,
        ':action' => $action,
        ':entity' => $entity,
        ':entity_id' => $entityId,
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ':ip' => $ip,
        ':created_at' => $now,
    ]);
}

function insert_lead(array $data): void {
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO leads (type, name, email, phone, service, requested_date, subject, message, details, status, priority, notes, source, created_at, updated_at)
        VALUES (:type, :name, :email, :phone, :service, :requested_date, :subject, :message, :details, :status, :priority, :notes, :source, :created_at, :updated_at)');

    $stmt->execute([
        ':type' => $data['type'],
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':phone' => $data['phone'] ?? null,
        ':service' => $data['service'] ?? null,
        ':requested_date' => $data['requested_date'] ?? null,
        ':subject' => $data['subject'] ?? null,
        ':message' => $data['message'] ?? null,
        ':details' => $data['details'] ?? null,
        ':status' => $data['status'] ?? 'Nouveau',
        ':priority' => $data['priority'] ?? 'Normal',
        ':notes' => $data['notes'] ?? null,
        ':source' => $data['source'] ?? 'site',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}
