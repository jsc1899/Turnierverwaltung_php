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

function get_setting(string $key, string $default = ''): string {
    $row = db_fetch('SELECT value FROM settings WHERE `key` = ?', [$key]);
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void {
    db_execute('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?',
        [$key, $value, $value]);
}

function init_db(): void {
    $pdo = get_db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tournament (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            name                TEXT NOT NULL,
            event_date          VARCHAR(100) DEFAULT '',
            max_competitions    INT DEFAULT 1,
            ausschreibung       VARCHAR(5000) DEFAULT '',
            registrations_open  TINYINT(1) DEFAULT 1,
            is_public           TINYINT(1) DEFAULT 1,
            is_done             TINYINT(1) DEFAULT 0,
            banner_image        VARCHAR(500) DEFAULT '',
            organizer           VARCHAR(255) DEFAULT '',
            sport               VARCHAR(100) DEFAULT '',
            info_url            VARCHAR(500) DEFAULT '',
            show_skill          TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS competition (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            name          TEXT NOT NULL,
            group_size    INT DEFAULT 4,
            advance_count INT DEFAULT 1,
            third_place   TINYINT(1) DEFAULT 0,
            phase         VARCHAR(50) DEFAULT 'setup',
            mode          VARCHAR(50) DEFAULT 'groups_ko',
            max_players   INT DEFAULT 0,
            registrations_open TINYINT(1) DEFAULT 1,
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS player (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            name      TEXT NOT NULL,
            firstname VARCHAR(255) DEFAULT '',
            club      VARCHAR(255) DEFAULT '',
            gender    VARCHAR(20) DEFAULT '',
            skill     DECIMAL(8,1) DEFAULT 0,
            pass_nr   VARCHAR(100) DEFAULT '',
            email     VARCHAR(255) DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS player_skill (
            player_id  INT NOT NULL,
            sport      VARCHAR(100) NOT NULL DEFAULT '',
            skill      DECIMAL(8,1) NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (player_id, sport),
            FOREIGN KEY (player_id) REFERENCES player(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS competition_player (
            competition_id INT NOT NULL,
            player_id      INT NOT NULL,
            skill          DECIMAL(8,1) DEFAULT 0,
            created_at     VARCHAR(50) DEFAULT '',
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
            club          VARCHAR(255) DEFAULT '',
            gender        VARCHAR(20) DEFAULT '',
            pass_nr       VARCHAR(100) DEFAULT '',
            skill         DECIMAL(8,1) DEFAULT 0,
            email         VARCHAR(255) DEFAULT '',
            status        VARCHAR(20) DEFAULT 'pending',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_competition (
            registration_id INT NOT NULL,
            competition_id  INT NOT NULL,
            status          VARCHAR(20) DEFAULT 'pending',
            PRIMARY KEY (registration_id, competition_id),
            FOREIGN KEY (registration_id) REFERENCES registration(id)  ON DELETE CASCADE,
            FOREIGN KEY (competition_id)  REFERENCES competition(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_change_request (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            request_type    TEXT NOT NULL,
            new_competitions VARCHAR(2000) DEFAULT '',
            status          VARCHAR(20) DEFAULT 'pending',
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registration_id) REFERENCES registration(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registration_change_competition (
            change_request_id INT NOT NULL,
            competition_id    INT NOT NULL,
            action            VARCHAR(20) DEFAULT 'add',
            status            VARCHAR(20) DEFAULT 'pending',
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
            role          VARCHAR(20) DEFAULT 'viewer',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS rate_limit (
            ip         VARCHAR(45) NOT NULL,
            action     VARCHAR(50) NOT NULL,
            attempts   INT DEFAULT 1,
            window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ip, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `double` (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            player1_id    INT NOT NULL,
            player2_id    INT NOT NULL,
            name          VARCHAR(500) DEFAULT '',
            skill         DECIMAL(8,1) DEFAULT 0,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tournament_id) REFERENCES tournament(id) ON DELETE CASCADE,
            FOREIGN KEY (player1_id)    REFERENCES player(id)    ON DELETE CASCADE,
            FOREIGN KEY (player2_id)    REFERENCES player(id)    ON DELETE CASCADE,
            UNIQUE KEY uq_double_pair (tournament_id, player1_id, player2_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS competition_double (
            competition_id INT NOT NULL,
            double_id      INT NOT NULL,
            skill          DECIMAL(8,1) DEFAULT 0,
            created_at     VARCHAR(50) DEFAULT '',
            PRIMARY KEY (competition_id, double_id),
            FOREIGN KEY (competition_id) REFERENCES competition(id) ON DELETE CASCADE,
            FOREIGN KEY (double_id)      REFERENCES `double`(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS group_double (
            group_id  INT NOT NULL,
            double_id INT NOT NULL,
            PRIMARY KEY (group_id, double_id),
            FOREIGN KEY (group_id)  REFERENCES grp(id)     ON DELETE CASCADE,
            FOREIGN KEY (double_id) REFERENCES `double`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `team` (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(500) NOT NULL DEFAULT '',
            skill      DECIMAL(8,1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `team_player` (
            team_id   INT NOT NULL,
            player_id INT NOT NULL,
            PRIMARY KEY (team_id, player_id),
            FOREIGN KEY (team_id)   REFERENCES `team`(id)  ON DELETE CASCADE,
            FOREIGN KEY (player_id) REFERENCES player(id)  ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `competition_team` (
            competition_id INT NOT NULL,
            team_id        INT NOT NULL,
            skill          DECIMAL(8,1) DEFAULT 0,
            created_at     VARCHAR(50) DEFAULT '',
            PRIMARY KEY (competition_id, team_id),
            FOREIGN KEY (competition_id) REFERENCES competition(id) ON DELETE CASCADE,
            FOREIGN KEY (team_id)        REFERENCES `team`(id)      ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `group_team` (
            group_id INT NOT NULL,
            team_id  INT NOT NULL,
            PRIMARY KEY (group_id, team_id),
            FOREIGN KEY (group_id) REFERENCES grp(id)    ON DELETE CASCADE,
            FOREIGN KEY (team_id)  REFERENCES `team`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `team_match_duel` (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            match_id    INT NOT NULL,
            duel_order  INT NOT NULL DEFAULT 0,
            player1_id  INT NULL DEFAULT NULL,
            player2_id  INT NULL DEFAULT NULL,
            score1      INT NULL DEFAULT NULL,
            score2      INT NULL DEFAULT NULL,
            played      TINYINT(1) DEFAULT 0,
            FOREIGN KEY (match_id)   REFERENCES `match`(id)  ON DELETE CASCADE,
            FOREIGN KEY (player1_id) REFERENCES player(id)   ON DELETE SET NULL,
            FOREIGN KEY (player2_id) REFERENCES player(id)   ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `match_set` (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            match_id  INT NOT NULL,
            set_order INT NOT NULL DEFAULT 0,
            score1    INT NULL DEFAULT NULL,
            score2    INT NULL DEFAULT NULL,
            FOREIGN KEY (match_id) REFERENCES `match`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Migrations für bestehende Datenbanken (try-catch: Spalte existiert ggf. schon)
    $migrations = [
        "ALTER TABLE tournament ADD COLUMN show_skill TINYINT(1) DEFAULT 0",
        "ALTER TABLE player          MODIFY COLUMN skill DECIMAL(8,1) DEFAULT 0",
        "ALTER TABLE player_skill    MODIFY COLUMN skill DECIMAL(8,1) NOT NULL DEFAULT 0",
        "ALTER TABLE competition_player MODIFY COLUMN skill DECIMAL(8,1) DEFAULT 0",
        "ALTER TABLE registration    MODIFY COLUMN skill DECIMAL(8,1) DEFAULT 0",
        "ALTER TABLE `match` ADD COLUMN bracket VARCHAR(3) NULL DEFAULT NULL",
        "ALTER TABLE competition ADD COLUMN show_seeding TINYINT(1) DEFAULT 1",
        "ALTER TABLE competition ADD COLUMN seeding_order VARCHAR(4) DEFAULT 'desc'",
        "ALTER TABLE competition ADD COLUMN is_doubles TINYINT(1) DEFAULT 0",
        "ALTER TABLE `match` ADD COLUMN double1_id INT NULL DEFAULT NULL",
        "ALTER TABLE `match` ADD COLUMN double2_id INT NULL DEFAULT NULL",
        "ALTER TABLE `double` MODIFY COLUMN tournament_id INT NULL DEFAULT NULL",
        "ALTER TABLE registration_competition ADD COLUMN partner_name VARCHAR(255) DEFAULT ''",
        "ALTER TABLE registration_change_competition ADD COLUMN partner_name VARCHAR(255) DEFAULT ''",
        "ALTER TABLE `match` ADD COLUMN team1_id INT NULL DEFAULT NULL",
        "ALTER TABLE `match` ADD COLUMN team2_id INT NULL DEFAULT NULL",
        "ALTER TABLE competition ADD COLUMN is_team TINYINT(1) DEFAULT 0",
        "ALTER TABLE competition ADD COLUMN show_skill TINYINT(1) DEFAULT 0",
        "ALTER TABLE competition ADD COLUMN team_size INT DEFAULT 0",
        "ALTER TABLE tournament ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
        "ALTER TABLE competition ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
        "ALTER TABLE `match` ADD COLUMN tiebreak_winner TINYINT NOT NULL DEFAULT 0",
        "ALTER TABLE `player` ADD COLUMN ratingscentral_id VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE `player` ADD COLUMN oetv_nr VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE `user` ADD COLUMN last_login DATETIME NULL DEFAULT NULL",
        "ALTER TABLE `player` ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE `double` ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE `team`   ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE `group_player` ADD COLUMN tiebreak_order INT NULL DEFAULT NULL",
        "ALTER TABLE `group_double` ADD COLUMN tiebreak_order INT NULL DEFAULT NULL",
        "ALTER TABLE `group_team`   ADD COLUMN tiebreak_order INT NULL DEFAULT NULL",
        "ALTER TABLE `team_match_duel` ADD COLUMN duel_label VARCHAR(32) NULL DEFAULT NULL",
        "ALTER TABLE competition ADD COLUMN score_mode VARCHAR(10) NOT NULL DEFAULT 'match'",
        "ALTER TABLE `match` ADD COLUMN round_no INT NULL DEFAULT NULL",
        "ALTER TABLE competition ADD COLUMN show_byes TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE competition ADD COLUMN num_courts INT NOT NULL DEFAULT 0",
        "ALTER TABLE `match` ADD COLUMN court_no INT NULL DEFAULT NULL",
        "ALTER TABLE grp ADD COLUMN courts VARCHAR(255) NOT NULL DEFAULT ''",
        "ALTER TABLE competition ADD COLUMN team_result_mode VARCHAR(10) NOT NULL DEFAULT 'wins'",
        "ALTER TABLE competition ADD COLUMN cross_config VARCHAR(64) NOT NULL DEFAULT ''",
        "ALTER TABLE `match` ADD COLUMN place_lo INT NULL DEFAULT NULL",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (\PDOException $e) { /* Spalte/Typ bereits korrekt */ }
    }

    // Migration: double-Unique-Key auf (player1_id, player2_id) reduzieren.
    // Reihenfolge: tournament_id-FK droppen → alten Key droppen → neuen Key anlegen.
    $has_old_uk = (bool)$pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='double'
         AND INDEX_NAME='uq_double_pair' AND COLUMN_NAME='tournament_id'"
    )->fetch();
    if ($has_old_uk) {
        $create_sql = $pdo->query("SHOW CREATE TABLE `double`")->fetchColumn(1);
        if (preg_match('/CONSTRAINT\s+`([^`]+)`\s+FOREIGN KEY\s+\(`tournament_id`\)/', $create_sql, $fk_m)) {
            try { $pdo->exec("ALTER TABLE `double` DROP FOREIGN KEY `{$fk_m[1]}`"); } catch (\PDOException $e) {}
        }
        try { $pdo->exec("ALTER TABLE `double` DROP KEY `uq_double_pair`"); } catch (\PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `double` ADD UNIQUE KEY `uq_double_pair` (`player1_id`, `player2_id`)"); } catch (\PDOException $e) {}
    }

    // Settings-Tabelle
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `key` VARCHAR(100) NOT NULL,
        `value` TEXT NOT NULL,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (\PDOException $e) {}

    // Admin-Rolle sicherstellen
    db_execute("UPDATE user SET role = 'admin' WHERE email = ?", [ADMIN_EMAIL]);

}
