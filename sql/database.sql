-- Sports Event Calendar – Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- Tables
CREATE TABLE users
(
    id            int(10)      NOT NULL AUTO_INCREMENT,
    name          varchar(255) NOT NULL,
    email         varchar(160) NOT NULL,
    password_hash text         NOT NULL,
    is_active     boolean   NOT NULL DEFAULT 1,
    created_at    timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE roles
(
    id    int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name  varchar(60)      NOT NULL,
    label varchar(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE permissions
(
    id          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name        varchar(255)     NOT NULL,
    label       varchar(255) NOT NULL,
    description text         DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE sports
(
    id            int(11)      NOT NULL AUTO_INCREMENT,
    name          varchar(255) NOT NULL,
    is_team_sport boolean      NOT NULL,
    PRIMARY KEY (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE venues
(
    id            int(11)      NOT NULL AUTO_INCREMENT,
    name          varchar(255) NOT NULL,
    address_line1 varchar(160) NOT NULL,
    address_line2 varchar(160) DEFAULT NULL,
    city          varchar(80)  NOT NULL,
    postal_code   varchar(30)  NOT NULL,
    country       varchar(80)  NOT NULL,
    is_indoor     tinyint(1)   DEFAULT NULL,
    time_zone     varchar(70)  DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE teams
(
    id         int(11)      NOT NULL AUTO_INCREMENT,
    name       varchar(255) NOT NULL,
    short_name varchar(100) NOT NULL,
    city       varchar(80) DEFAULT NULL,
    country    varchar(80) DEFAULT NULL,
    logo_url   text        DEFAULT NULL,
    sport_id   int(11)      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_teams_sport_id (sport_id),
    CONSTRAINT fk_teams_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id) ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE competitions
(
    id         int(11) NOT NULL AUTO_INCREMENT,
    type       varchar(20)  DEFAULT NULL,
    name       varchar(255) DEFAULT NULL,
    created_by int(10)      DEFAULT NULL,
    sport_id   int(11)      DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_competitions_sport_id (sport_id),
    KEY idx_competitions_created_by (created_by),
    CONSTRAINT fk_competitions_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id) ON DELETE RESTRICT,
    CONSTRAINT fk_competitions_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE api_tokens
(
    id           int(10) NOT NULL AUTO_INCREMENT,
    token_hash   text    NOT NULL,
    generated_by int(10) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user (generated_by),
    CONSTRAINT fk_api_tokens_user
        FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE user_roles
(
    user_id int(10)          NOT NULL,
    role_id int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    KEY role_id (role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE role_permissions
(
    role_id       int(10) UNSIGNED NOT NULL,
    permission_id int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY permission_id (permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE competition_teams
(
    competition_id int(11) NOT NULL,
    team_id        int(11) NOT NULL,
    PRIMARY KEY (competition_id, team_id),
    KEY idx_competition_teams_team_id (team_id),
    CONSTRAINT fk_competition_teams_competition
        FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_competition_teams_team
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE events
(
    id             int(10)      NOT NULL AUTO_INCREMENT,
    title          varchar(255) NOT NULL,
    banner_path    text                  DEFAULT NULL,
    description    text                  DEFAULT NULL,
    start_at       timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_at         timestamp    NULL     DEFAULT NULL,
    status         varchar(30)           DEFAULT NULL,
    competition_id int(11)               DEFAULT NULL,
    venue_id       int(11)               DEFAULT NULL,
    sport_id       int(11)               DEFAULT NULL,
    created_by     int(10)               DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_events_competition_id (competition_id),
    KEY idx_events_venue_id (venue_id),
    KEY idx_events_sport_id (sport_id),
    KEY idx_events_created_by (created_by),
    CONSTRAINT fk_events_competition
        FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE event_teams
(
    event_id int(11) NOT NULL,
    team_id  int(11) NOT NULL,
    side     varchar(100) DEFAULT NULL,
    score    varchar(100) DEFAULT NULL,
    result   varchar(100) DEFAULT NULL,
    PRIMARY KEY (event_id, team_id),
    KEY idx_event_teams_team_id (team_id),
    CONSTRAINT fk_event_teams_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_teams_team
        FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- Seed data
INSERT INTO users (id, name, email, password_hash, is_active, created_at)
VALUES (1, 'Nuwan Danushka', 'nuwandanushkaat@gmail.com',
        '$2y$12$pdCtZZ3mTtGxDGFtjlNgt.bgaPB5kVymbgTRYhOmBvskh4JgiCgyq', 1, '2025-10-30 21:05:57'),
       (2, 'Admin', 'admin@gmail.com', '$2y$12$AcvZcCR4NkfoRvs.3SxyDeDVUZPKCiVPeAR6Pmdx.6up6qgksolau', 1,
        '2025-10-31 12:06:03');

INSERT INTO roles (id, name, label)
VALUES (1, 'admin', 'Administrator'),
       (2, 'editor', 'Content Editor'),
       (3, 'viewer', 'Read only');

INSERT INTO permissions (id, name, label, description)
VALUES (1, 'event_create', 'Create event', NULL),
       (2, 'event_update', 'Update event', NULL),
       (3, 'event_delete', 'Delete event', NULL);

INSERT INTO role_permissions (role_id, permission_id)
VALUES (1, 1),
       (1, 2),
       (1, 3),
       (2, 1),
       (2, 2);

INSERT INTO user_roles (user_id, role_id)
VALUES (1, 1),
       (2, 1);

INSERT INTO api_tokens (id, token_hash, generated_by)
VALUES (1, '06e95f980593e7484aa605725a72c5c8ef7b29124720699e27a7661a02717417', 1);

INSERT INTO sports (id, name, is_team_sport)
VALUES (1, 'Football', 1),
       (2, 'Basketball', 1),
       (3, 'Ice Hockey', 1),
       (4, 'Volleyball', 1);

INSERT INTO teams (id, name, short_name, city, country, logo_url, sport_id)
VALUES
    (1, 'Rapid Vienna', 'RAP', 'Vienna', 'Austria', NULL, 1),
    (2, 'Austria Vienna', 'AUS', 'Vienna', 'Austria', NULL, 1),
    (3, 'Red Bull Salzburg', 'RBS', 'Salzburg', 'Austria', NULL, 1),
    (4, 'Sturm Graz', 'STG', 'Graz', 'Austria', NULL, 1),
    (5, 'Vienna Basket', 'VBC', 'Vienna', 'Austria', NULL, 2),
    (6, 'Gunners Oberwart', 'GOW', 'Oberwart', 'Austria', NULL, 2),
    (7, 'Kapfenberg Bulls', 'KPB', 'Kapfenberg', 'Austria', NULL, 2),
    (8, 'Graz 99ers', 'G99', 'Graz', 'Austria', NULL, 3),
    (9, 'Linz Steel', 'LNS', 'Linz', 'Austria', NULL, 3),
    (10, 'Austrian Volley Vienna', 'AVV', 'Vienna', 'Austria', NULL, 4);

INSERT INTO venues (id, name, address_line1, address_line2, city, postal_code, country, is_indoor, time_zone)
VALUES (1, 'Ernst Happel Stadion', 'Meiereistrasse 7', NULL, 'Vienna', '1020', 'Austria', 0, 'Europe/Vienna'),
       (2, 'Allianz Stadion', 'Keisslergasse 6', NULL, 'Vienna', '1140', 'Austria', 0, 'Europe/Vienna'),
       (3, 'Red Bull Arena', 'Stadionstrasse 2', NULL, 'Salzburg', '5071', 'Austria', 0, 'Europe/Vienna'),
       (4, 'Merkur Arena', 'Stadionplatz 1', NULL, 'Graz', '8041', 'Austria', 0, 'Europe/Vienna'),
       (5, 'Tivoli Stadium', 'Olympiastrasse 10', NULL, 'Innsbruck', '6020', 'Austria', 0, 'Europe/Vienna'),
       (6, 'Linz Stadium', 'Stadionstrasse 1', NULL, 'Linz', '4020', 'Austria', 0, 'Europe/Vienna'),
       (7, 'Sporthalle Vienna', 'Bernhardtstrasse 1', NULL, 'Vienna', '1220', 'Austria', 1, 'Europe/Vienna'),
       (8, 'Salzburg Arena', 'Am Messezentrum 1', NULL, 'Salzburg', '5020', 'Austria', 1, 'Europe/Vienna'),
       (9, 'Raiffeisen Sportpark', 'Harterstrasse 136', NULL, 'Graz', '8053', 'Austria', 1, 'Europe/Vienna'),
       (10, 'Klagenfurt Stadium', 'Suedring 1', NULL, 'Klagenfurt', '9020', 'Austria', 0, 'Europe/Vienna');

INSERT INTO competitions (id, type, name, created_by, sport_id)
VALUES
    (1, 'league', 'Austrian Bundesliga 2025', 1, 1),
    (2, 'cup', 'Vienna City Cup 2025', 1, 1),
    (3, 'supercup', 'Austria Super Cup 2025', 1, 1),
    (4, 'league', 'Austrian Basketball League 2025', 1, 2),
    (5, 'cup', 'Basketball Cup 2025', 1, 2),
    (6, 'league', 'Ice Hockey League 2025', 1, 3),
    (7, 'cup', 'Ice Hockey Cup 2025', 1, 3),
    (8, 'league', 'Volleyball League 2025', 1, 4),
    (9, 'cup', 'Volleyball Cup 2025', 1, 4),
    (10, 'supercup', 'Volleyball Super Cup 2025', 1, 4);

INSERT INTO competition_teams (competition_id, team_id)
VALUES
    (1, 1),
    (1, 2),
    (1, 3),
    (1, 4),
    (2, 1),
    (2, 2),
    (4, 5),
    (4, 7),
    (6, 8),
    (10, 10);

INSERT INTO events
(id, title, banner_path, description, start_at, end_at, status, competition_id, venue_id, sport_id, created_by)
VALUES
    (1, 'Rapid Vienna vs Austria Vienna', NULL, NULL, '2025-11-10 18:00:00', '2025-11-10 20:00:00', 'confirmed', 1, 2, 1, 1),
    (2, 'Red Bull Salzburg vs Rapid Vienna', NULL, NULL, '2025-11-17 17:30:00', '2025-11-17 19:30:00', 'confirmed', 1, 3, 1, 1),
    (3, 'Austria Vienna vs Sturm Graz', NULL, NULL, '2025-11-24 18:00:00', '2025-11-24 20:00:00', 'confirmed', 1, 1, 1, 1),
    (4, 'Vienna Basket vs Kapfenberg Bulls', NULL, NULL, '2025-11-12 18:30:00', '2025-11-12 20:30:00', 'confirmed', 4, 7, 2, 1),
    (5, 'Gunners Oberwart vs Vienna Basket', NULL, NULL, '2025-11-19 19:00:00', '2025-11-19 21:00:00', 'confirmed', 5, 9, 2, 1),
    (6, 'Graz 99ers Season Opener', NULL, NULL, '2025-11-21 19:00:00', '2025-11-21 21:30:00', 'confirmed', 6, 4, 3, 1),
    (7, 'Ice Hockey Cup Round 1', NULL, NULL, '2025-11-15 12:00:00', '2025-11-15 14:00:00', 'scheduled', 7, 6, 3, 1),
    (8, 'Volleyball League Matchday 1', NULL, NULL, '2025-11-08 16:00:00', '2025-11-08 18:00:00', 'scheduled', 8, 7, 4, 1),
    (9, 'Volleyball Cup Quarterfinal', NULL, NULL, '2025-11-09 18:00:00', '2025-11-09 20:00:00', 'scheduled', 9, 9, 4, 1),
    (10, 'Volleyball Super Cup Final', NULL, NULL, '2025-11-30 10:00:00', '2025-11-30 12:00:00', 'scheduled', 10, 1, 4, 1);

INSERT INTO event_teams (event_id, team_id, side, score, result)
VALUES (1, 1, 'home', NULL, NULL),
       (1, 2, 'away', NULL, NULL),
       (2, 3, 'home', NULL, NULL),
       (2, 1, 'away', NULL, NULL),
       (3, 2, 'home', NULL, NULL),
       (3, 4, 'away', NULL, NULL),
       (4, 5, 'home', NULL, NULL),
       (4, 7, 'away', NULL, NULL),
       (5, 6, 'home', NULL, NULL),
       (5, 5, 'away', NULL, NULL);

COMMIT;
