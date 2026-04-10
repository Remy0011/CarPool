-- ============================================================
-- Script SQL pour la creation de la base de donnees
-- Base sur le MCD fourni
-- ============================================================

-- Suppression des tables si elles existent (pour eviter les conflits)
DROP TABLE IF EXISTS ASSO5;
DROP TABLE IF EXISTS CONDUIRE;
DROP TABLE IF EXISTS ASSO4;
DROP TABLE IF EXISTS ASSO2;
DROP TABLE IF EXISTS passengers;
DROP TABLE IF EXISTS journeys;
DROP TABLE IF EXISTS conductors;
DROP TABLE IF EXISTS users;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE users (
                       users_id INT PRIMARY KEY AUTO_INCREMENT,
                       user_name VARCHAR(100) NOT NULL,
                       user_password VARCHAR(255) NOT NULL,
                       user_email VARCHAR(150) NOT NULL UNIQUE
);

-- ============================================================
-- Table: conductors
-- ============================================================
CREATE TABLE conductors (
                            conductors_id INT PRIMARY KEY AUTO_INCREMENT,
                            place_available INT NOT NULL CHECK (place_available >= 0),
                            users_id INT NOT NULL UNIQUE,
                            CONSTRAINT fk_conductors_users FOREIGN KEY (users_id)
                                REFERENCES users(users_id) ON DELETE CASCADE
);

-- ============================================================
-- Table: passengers
-- ============================================================
CREATE TABLE passengers (
                            passengers_id INT PRIMARY KEY AUTO_INCREMENT,
                            users_id INT NOT NULL UNIQUE,
                            CONSTRAINT fk_passengers_users FOREIGN KEY (users_id)
                                REFERENCES users(users_id) ON DELETE CASCADE
);

-- ============================================================
-- Table: journeys
-- ============================================================
CREATE TABLE journeys (
                          journeys_id INT PRIMARY KEY AUTO_INCREMENT,
                          travel_time TIME NOT NULL,
                          start VARCHAR(255) NOT NULL,
                          final VARCHAR(255) NOT NULL,
                          start_of_hours DATETIME NOT NULL,
                          end_of_hours DATETIME NOT NULL
);

-- ============================================================
-- Table d'association: CONDUIRE (conductors - journeys)
-- ============================================================
CREATE TABLE CONDUIRE (
                          conductors_id INT NOT NULL,
                          journeys_id INT NOT NULL,
                          PRIMARY KEY (conductors_id, journeys_id),
                          CONSTRAINT fk_conduire_conductors FOREIGN KEY (conductors_id)
                              REFERENCES conductors(conductors_id) ON DELETE CASCADE,
                          CONSTRAINT fk_conduire_journeys FOREIGN KEY (journeys_id)
                              REFERENCES journeys(journeys_id) ON DELETE CASCADE
);

-- ============================================================
-- Table d'association: ASSO2 (users - ???)
-- ============================================================
CREATE TABLE ASSO2 (
                       users_id INT NOT NULL,
                       PRIMARY KEY (users_id),
                       CONSTRAINT fk_asso2_users FOREIGN KEY (users_id)
                           REFERENCES users(users_id) ON DELETE CASCADE
);

-- ============================================================
-- Table d'association: ASSO4 (passengers - journeys)
-- ============================================================
CREATE TABLE ASSO4 (
                       passengers_id INT NOT NULL,
                       journeys_id INT NOT NULL,
                       PRIMARY KEY (passengers_id, journeys_id),
                       CONSTRAINT fk_asso4_passengers FOREIGN KEY (passengers_id)
                           REFERENCES passengers(passengers_id) ON DELETE CASCADE,
                       CONSTRAINT fk_asso4_journeys FOREIGN KEY (journeys_id)
                           REFERENCES journeys(journeys_id) ON DELETE CASCADE
);

-- ============================================================
-- Table d'association: ASSO5 (?? - ??)
-- ============================================================
CREATE TABLE ASSO5 (
                       id INT PRIMARY KEY AUTO_INCREMENT
);

-- ============================================================
-- Insertion de donnees exemple
-- ============================================================

-- All seeded users have password: test123
INSERT INTO users (user_name, user_password, user_email) VALUES
                                                             ('john_doe', '$2y$12$2KmCeD1eXWxKDCV9G78kYOvTkBM8DQHxD9Z6ryWH.af0rwNI0g3pq', 'john@example.com'),
                                                             ('jane_smith', '$2y$12$tAKfxjKaNxIqlmgZoaBGjubdzrxTe6u6v8Dy7IGeEzmlnNNxnuAOC', 'jane@example.com'),
                                                             ('bob_wilson', '$2y$12$l6VLvZXcC6pz2/1NYP1SduhCS78e38eIxAzIATTh13LzdeJnmtA5e', 'bob@example.com');

INSERT INTO conductors (place_available, users_id) VALUES
                                                        (4, 1),
                                                        (6, 2);

INSERT INTO passengers (users_id) VALUES
    (3);

INSERT INTO journeys (travel_time, start, final, start_of_hours, end_of_hours) VALUES
                                                                                   ('00:30:00', 'Paris', 'Lyon', '2024-01-15 08:00:00', '2024-01-15 08:30:00'),
                                                                                   ('01:15:00', 'Lyon', 'Marseille', '2024-01-16 09:00:00', '2024-01-16 10:15:00');

INSERT INTO CONDUIRE (conductors_id, journeys_id) VALUES
                                                      (1, 1),
                                                      (2, 2);

INSERT INTO ASSO4 (passengers_id, journeys_id) VALUES
                                                   (1, 1),
                                                   (1, 2);

-- ============================================================
-- Creation de vues utiles
-- ============================================================

CREATE VIEW view_trajets_conducteurs AS
SELECT
    j.journeys_id,
    j.start,
    j.final,
    j.travel_time,
    j.start_of_hours,
    j.end_of_hours,
    u.user_name AS conducteur_nom,
    c.place_available
FROM journeys j
         JOIN CONDUIRE cd ON j.journeys_id = cd.journeys_id
         JOIN conductors c ON cd.conductors_id = c.conductors_id
         JOIN users u ON c.users_id = u.users_id;

CREATE VIEW view_trajets_passagers AS
SELECT
    j.journeys_id,
    j.start,
    j.final,
    u.user_name AS passager_nom
FROM journeys j
         JOIN ASSO4 a ON j.journeys_id = a.journeys_id
         JOIN passengers p ON a.passengers_id = p.passengers_id
         JOIN users u ON p.users_id = u.users_id;

-- ============================================================
-- Index pour optimiser les performances
-- ============================================================

CREATE INDEX idx_users_email ON users(user_email);
CREATE INDEX idx_journeys_start ON journeys(start);
CREATE INDEX idx_journeys_final ON journeys(final);
CREATE INDEX idx_journeys_datetime ON journeys(start_of_hours, end_of_hours);
