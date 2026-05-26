-- ============================================================
--  Prode Vale 4 — Mundial USA 2026  |  setup.sql  v2
--  Ejecutar en phpMyAdmin: base de datos "vale4" (nueva o recreada)
-- ============================================================

DROP DATABASE IF EXISTS mundial2026;
CREATE DATABASE mundial2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mundial2026;

-- ── Usuarios ─────────────────────────────────────────────────
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    nombre     VARCHAR(80)  NOT NULL,
    apellido   VARCHAR(80)  NOT NULL,
    dni        VARCHAR(20)  NOT NULL UNIQUE,
    celular    VARCHAR(30)  NOT NULL,
    email      VARCHAR(120) NOT NULL,
    is_admin   TINYINT(1)   DEFAULT 0,
    token      VARCHAR(64)  DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Partidos de grupos ────────────────────────────────────────
CREATE TABLE matches (
    id         INT PRIMARY KEY,
    grp        CHAR(1)     NOT NULL,
    team1      VARCHAR(50) NOT NULL,
    team2      VARCHAR(50) NOT NULL,
    match_date DATE        NOT NULL,
    match_time TIME        DEFAULT NULL   -- hora Argentina UTC-3
);

-- ── Resultados de grupos ──────────────────────────────────────
CREATE TABLE results (
    match_id   INT PRIMARY KEY,
    s1         INT  NOT NULL,
    s2         INT  NOT NULL,
    scorers    TEXT DEFAULT '[]',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Picks de grupos ───────────────────────────────────────────
CREATE TABLE picks (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL,
    match_id   INT         NOT NULL,
    s1         INT         NOT NULL,
    s2         INT         NOT NULL,
    scorers    TEXT        DEFAULT '[]',
    updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pick (username, match_id)
);

-- ── Jugadores por selección ───────────────────────────────────
CREATE TABLE players (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    team VARCHAR(50)  NOT NULL,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_player (team, name),
    INDEX idx_team (team)
);

-- ── Partidos eliminatorios ────────────────────────────────────
-- ronda: 'R32','R16','QF','SF','3RD','FIN'
-- slot: posición fija en el bracket (0-15 para R32, 0-7 R16, etc.)
-- pen1/pen2: goles en penales (solo para mostrar ganador, no puntúan)
CREATE TABLE knockout_matches (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ronda      VARCHAR(4)  NOT NULL,
    slot       INT         NOT NULL,
    team1      VARCHAR(50) DEFAULT NULL,
    team2      VARCHAR(50) DEFAULT NULL,
    match_date DATE        DEFAULT NULL,
    match_time TIME        DEFAULT NULL,
    s1         INT         DEFAULT NULL,  -- goles 90'+30'
    s2         INT         DEFAULT NULL,
    pen1       INT         DEFAULT NULL,  -- penales (solo clasif)
    pen2       INT         DEFAULT NULL,
    scorers1   TEXT        DEFAULT '[]',  -- goleadores team1 (90')
    scorers2   TEXT        DEFAULT '[]',  -- goleadores team2 (90')
    winner     VARCHAR(50) DEFAULT NULL,
    generated  TINYINT(1)  DEFAULT 0,     -- 1 = generado automáticamente
    UNIQUE KEY uq_slot (ronda, slot)
);

-- ── Picks eliminatorios ───────────────────────────────────────
CREATE TABLE knockout_picks (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL,
    km_id      INT         NOT NULL,
    s1         INT         NOT NULL,
    s2         INT         NOT NULL,
    scorers    TEXT        DEFAULT '[]',  -- máx 3, solo 90'
    updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kpick (username, km_id)
);

-- ── Carga inicial de partidos de grupos ───────────────────────
INSERT INTO matches (id,grp,team1,team2,match_date) VALUES
(1,'A','México','Sudáfrica','2026-06-11'),
(2,'A','Corea del Sur','Chequia','2026-06-11'),
(3,'A','Chequia','Sudáfrica','2026-06-18'),
(4,'A','México','Corea del Sur','2026-06-19'),
(5,'A','Corea del Sur','Sudáfrica','2026-06-25'),
(6,'A','Chequia','México','2026-06-25'),
(7,'B','Canadá','Bosnia-Herz.','2026-06-12'),
(8,'B','Qatar','Suiza','2026-06-13'),
(9,'B','Suiza','Bosnia-Herz.','2026-06-18'),
(10,'B','Canadá','Qatar','2026-06-18'),
(11,'B','Suiza','Canadá','2026-06-24'),
(12,'B','Bosnia-Herz.','Qatar','2026-06-24'),
(13,'C','Brasil','Marruecos','2026-06-13'),
(14,'C','Haití','Escocia','2026-06-14'),
(15,'C','Brasil','Haití','2026-06-19'),
(16,'C','Escocia','Marruecos','2026-06-19'),
(17,'C','Escocia','Brasil','2026-06-24'),
(18,'C','Marruecos','Haití','2026-06-24'),
(19,'D','USA','Paraguay','2026-06-12'),
(20,'D','Australia','Turquía','2026-06-13'),
(21,'D','USA','Australia','2026-06-19'),
(22,'D','Turquía','Paraguay','2026-06-20'),
(23,'D','Turquía','USA','2026-06-26'),
(24,'D','Paraguay','Australia','2026-06-26'),
(25,'E','Alemania','Curazao','2026-06-14'),
(26,'E','C. de Marfil','Ecuador','2026-06-14'),
(27,'E','Alemania','C. de Marfil','2026-06-20'),
(28,'E','Ecuador','Curazao','2026-06-20'),
(29,'E','Curazao','C. de Marfil','2026-06-25'),
(30,'E','Ecuador','Alemania','2026-06-25'),
(31,'F','P. Bajos','Japón','2026-06-14'),
(32,'F','Suecia','Túnez','2026-06-14'),
(33,'F','P. Bajos','Suecia','2026-06-20'),
(34,'F','Túnez','Japón','2026-06-20'),
(35,'F','Japón','Suecia','2026-06-26'),
(36,'F','Túnez','P. Bajos','2026-06-26'),
(37,'G','Bélgica','Egipto','2026-06-15'),
(38,'G','Irán','Nueva Zelanda','2026-06-15'),
(39,'G','Bélgica','Irán','2026-06-21'),
(40,'G','Nueva Zelanda','Egipto','2026-06-21'),
(41,'G','Egipto','Irán','2026-06-26'),
(42,'G','Bélgica','Nueva Zelanda','2026-06-26'),
(43,'H','España','Cabo Verde','2026-06-15'),
(44,'H','Arabia Saudita','Uruguay','2026-06-15'),
(45,'H','España','Arabia Saudita','2026-06-21'),
(46,'H','Uruguay','Cabo Verde','2026-06-21'),
(47,'H','Cabo Verde','Arabia Saudita','2026-06-27'),
(48,'H','Uruguay','España','2026-06-27'),
(49,'I','Francia','Senegal','2026-06-16'),
(50,'I','Irak','Noruega','2026-06-16'),
(51,'I','Francia','Irak','2026-06-22'),
(52,'I','Noruega','Senegal','2026-06-22'),
(53,'I','Senegal','Irak','2026-06-26'),
(54,'I','Noruega','Francia','2026-06-26'),
(55,'J','Argentina','Argelia','2026-06-16'),
(56,'J','Austria','Jordania','2026-06-17'),
(57,'J','Argentina','Austria','2026-06-22'),
(58,'J','Jordania','Argelia','2026-06-22'),
(59,'J','Argelia','Austria','2026-06-27'),
(60,'J','Jordania','Argentina','2026-06-27'),
(61,'K','Portugal','RD Congo','2026-06-17'),
(62,'K','Uzbekistán','Colombia','2026-06-17'),
(63,'K','Portugal','Uzbekistán','2026-06-22'),
(64,'K','Colombia','RD Congo','2026-06-22'),
(65,'K','RD Congo','Uzbekistán','2026-06-27'),
(66,'K','Colombia','Portugal','2026-06-27'),
(67,'L','Inglaterra','Croacia','2026-06-17'),
(68,'L','Ghana','Panamá','2026-06-17'),
(69,'L','Inglaterra','Ghana','2026-06-23'),
(70,'L','Panamá','Croacia','2026-06-23'),
(71,'L','Croacia','Ghana','2026-06-27'),
(72,'L','Panamá','Inglaterra','2026-06-27');
