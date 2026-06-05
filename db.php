<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(string $sql, array $params = []): ?array {
    return db_query($sql, $params)->fetch() ?: null;
}

function db_fetchall(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

function db_insert(string $sql, array $params = []): string {
    db_query($sql, $params);
    return get_db()->lastInsertId();
}

function db_execute(string $sql, array $params = []): int {
    return db_query($sql, $params)->rowCount();
}

function init_db(): void {
    $pdo = get_db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tournament (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            name                TEXT NOT NULL,
            event_date          TEXT DEFAULT '',
            max_competitions    INT DEFAULT 1,
            ausschreibung       TEXT DEFAULT '',
            registrations_open  TINYINT(1) DEFAULT 1,
            is_public           TINYINT(1) DEFAULT 1,
            is_done             TINYINT(1) DEFAULT 0,
            banner_image        TEXT DEFAULT '',
            organizer           TEXT DEFAULT '',
            sport               TEXT DEFAULT '',
            info_url            TEXT DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS competition (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            name          TEXT NOT NULL,
            group_size    INT DEFAULT 4,
            advance_count INT DEFAULT 1,
            third_place   TINYINT(1) DEFAULT 0,
            phase         TEXT DEFAULT 'setup',
            mode          TEXT DEFAULT 'groups_ko',
            max_players   INT DEFAULT 0,
            registrations_open TINYINT(1) DEFAULT 1,
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS player (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            name      TEXT NOT NULL,
            firstname TEXT DEFAULT '',
            club      TEXT DEFAULT '',
            gender    TEXT DEFAULT '',
            skill     INT DEFAULT 0,
            pass_nr   TEXT DEFAULT '',
            email     TEXT DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS player_skill (
            player_id  INT NOT NULL,
            sport      TEXT NOT NULL DEFAULT '',
            skill      INT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (player_id, sport(50)),
            FOREIGN KEY (player_id) REFERENCES player(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS competition_player (
            competition_id INT NOT NULL,
            player_id      INT NOT NULL,
            skill          INT DEFAULT 0,
            created_at     TEXT DEFAULT '',
            PRIMARY KEY (competition_id, player_id),
            FOREIGN KEY (competition_id) REFERENCES competition(id) ON DELETE CASCADE,
            FOREIGN KEY (player_id)      REFERENCES player(id)      ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS grp (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NOT NULL,
            name           TEXT NOT NULL,
            FOREIGN KEY (competition_id) REFERENCES competition(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS group_player (
            group_id  INT NOT NULL,
            player_id INT NOT NULL,
            PRIMARY KEY (group_id, player_id),
            FOREIGN KEY (group_id)  REFERENCES grp(id)    ON DELETE CASCADE,
            FOREIGN KEY (player_id) REFERENCES player(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `match` (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NOT NULL,
            group_id       INT DEFAULT NULL,
            ko_round       INT DEFAULT NULL,
            ko_position    INT DEFAULT NULL,
            player1_id     INT DEFAULT NULL,
            player2_id     INT DEFAULT NULL,
            score1         INT DEFAULT NULL,
            score2         INT DEFAULT NULL,
            played         TINYINT(1) DEFAULT 0,
            match_order    INT DEFAULT 0,
            FOREIGN KEY (competition_id) REFERENCES competition(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id)       REFERENCES grp(id)         ON DELETE SET NULL,
            FOREIGN KEY (player1_id)     REFERENCES player(id)      ON DELETE SET NULL,
            FOREIGN KEY (player2_id)     REFERENCES player(id)      ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            lastname      TEXT NOT NULL,
            firstname     TEXT NOT NULL,
            club          TEXT DEFAULT '',
            gender        TEXT DEFAULT '',
            pass_nr       TEXT DEFAULT '',
            skill         INT DEFAULT 0,
            email         TEXT DEFAULT '',
            status        TEXT DEFAULT 'pending',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_competition (
            registration_id INT NOT NULL,
            competition_id  INT NOT NULL,
            status          TEXT DEFAULT 'pending',
            PRIMARY KEY (registration_id, competition_id),
            FOREIGN KEY (registration_id) REFERENCES registration(id)  ON DELETE CASCADE,
            FOREIGN KEY (competition_id)  REFERENCES competition(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_change_request (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            request_type    TEXT NOT NULL,
            new_competitions TEXT DEFAULT '',
            status          TEXT DEFAULT 'pending',
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registration_id) REFERENCES registration(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_change_competition (
            change_request_id INT NOT NULL,
            competition_id    INT NOT NULL,
            action            TEXT DEFAULT 'add',
            status            TEXT DEFAULT 'pending',
            PRIMARY KEY (change_request_id, competition_id),
            FOREIGN KEY (change_request_id) REFERENCES registration_change_request(id) ON DELETE CASCADE,
            FOREIGN KEY (competition_id)    REFERENCES competition(id)                  ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS user (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      TEXT NOT NULL,
            email         VARCHAR(255) NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            confirmed     TINYINT(1) DEFAULT 0,
            role          TEXT DEFAULT 'viewer',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS rate_limit (
            ip         VARCHAR(45) NOT NULL,
            action     VARCHAR(50) NOT NULL,
            attempts   INT DEFAULT 1,
            window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ip, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Admin-Rolle sicherstellen
    db_execute("UPDATE user SET role = 'admin' WHERE email = ?", [ADMIN_EMAIL]);
}
