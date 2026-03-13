-- ============================================================
-- Script SQL pour la création de la base de données
-- Basé sur le MCD fourni
-- ============================================================

-- Suppression des tables si elles existent (pour éviter les conflits)
DROP TABLE IF EXISTS ASSO5;
DROP TABLE IF EXISTS CONDUIRE;
DROP TABLE IF EXISTS ASSO4;
DROP TABLE IF EXISTS ASSO2;
DROP TABLE IF EXISTS passengers;
DROP TABLE IF EXISTS journeys;
DROP TABLE IF EXISTS conductors;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS users;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE users (
                       users_id INT PRIMARY KEY AUTO_INCREMENT,
                       user_name VARCHAR(100) NOT NULL,
                       user_password VARCHAR(255) NOT NULL, -- Stockage de hash de mot de passe
                       user_email VARCHAR(150) NOT NULL UNIQUE
);

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE employees (
                           employees_id INT PRIMARY KEY AUTO_INCREMENT,
                           name VARCHAR(100) NOT NULL,
                           users_id INT NOT NULL,
                           CONSTRAINT fk_employees_users FOREIGN KEY (users_id)
                               REFERENCES users(users_id) ON DELETE CASCADE
);

-- ============================================================
-- Table: conductors
-- ============================================================
CREATE TABLE conductors (
                            conductors_id INT PRIMARY KEY AUTO_INCREMENT,
                            place_available INT NOT NULL CHECK (place_available >= 0),
                            employees_id INT NOT NULL UNIQUE, -- Relation 1,1 avec employees
                            CONSTRAINT fk_conductors_employees FOREIGN KEY (employees_id)
                                REFERENCES employees(employees_id) ON DELETE CASCADE
);

-- ============================================================
-- Table: passengers
-- ============================================================
CREATE TABLE passengers (
                            passengers_id INT PRIMARY KEY AUTO_INCREMENT,
                            employees_id INT NOT NULL UNIQUE, -- Relation 1,1 avec employees
                            CONSTRAINT fk_passengers_employees FOREIGN KEY (employees_id)
                                REFERENCES employees(employees_id) ON DELETE CASCADE
);

-- ============================================================
-- Table: journeys
-- ============================================================
CREATE TABLE journeys (
                          journeys_id INT PRIMARY KEY AUTO_INCREMENT,
                          travel_time TIME NOT NULL,
                          start VARCHAR(255) NOT NULL, -- Point de départ
                          final VARCHAR(255) NOT NULL, -- Point d'arrivée
                          start_of_hours DATETIME NOT NULL,
                          end_of_hours DATETIME NOT NULL
);

-- ============================================================
-- Table d'association: CONDUIRE (conductors - journeys)
-- Relation many-to-many
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
-- Table d'association: ASSO2 (employees - ???)
-- D'après le MCD, cette table semble relier employees à une autre entité
-- Note: Le MCD ne montre pas clairement la deuxième entité liée par ASSO2
-- ============================================================
CREATE TABLE ASSO2 (
                       employees_id INT NOT NULL,
    -- Ajouter ici l'autre clé étrangère manquante
    -- Exemple: autre_entite_id INT NOT NULL,
                       PRIMARY KEY (employees_id), -- ou (employees_id, autre_entite_id)
                       CONSTRAINT fk_asso2_employees FOREIGN KEY (employees_id)
                           REFERENCES employees(employees_id) ON DELETE CASCADE
    -- Ajouter la contrainte pour l'autre clé étrangère
);

-- ============================================================
-- Table d'association: ASSO4 (passengers - journeys)
-- Relation many-to-many
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
-- Note: Cette table semble manquer d'informations dans le MCD
-- ============================================================
CREATE TABLE ASSO5 (
    -- À compléter selon les entités liées
                       id INT PRIMARY KEY AUTO_INCREMENT -- Optionnel
    -- Ajouter les clés étrangères nécessaires
);

-- ============================================================
-- Insertion de données exemple
-- ============================================================

-- Insertion d'utilisateurs
INSERT INTO users (user_name, user_password, user_email) VALUES
                                                             ('john_doe', 'hashed_password_123', 'john@example.com'),
                                                             ('jane_smith', 'hashed_password_456', 'jane@example.com'),
                                                             ('bob_wilson', 'hashed_password_789', 'bob@example.com');

-- Insertion d'employés (liés aux utilisateurs)
INSERT INTO employees (name, users_id) VALUES
                                           ('John Doe', 1),
                                           ('Jane Smith', 2),
                                           ('Bob Wilson', 3);

-- Insertion de conducteurs (employés qui sont conducteurs)
INSERT INTO conductors (place_available, employees_id) VALUES
                                                           (4, 1), -- John a 4 places disponibles
                                                           (6, 2); -- Jane a 6 places disponibles

-- Insertion de passagers (employés qui sont passagers)
INSERT INTO passengers (employees_id) VALUES
    (3); -- Bob est passager

-- Insertion de trajets
INSERT INTO journeys (travel_time, start, final, start_of_hours, end_of_hours) VALUES
                                                                                   ('00:30:00', 'Paris', 'Lyon', '2024-01-15 08:00:00', '2024-01-15 08:30:00'),
                                                                                   ('01:15:00', 'Lyon', 'Marseille', '2024-01-16 09:00:00', '2024-01-16 10:15:00');

-- Association conducteurs-trajets (CONDUIRE)
INSERT INTO CONDUIRE (conductors_id, journeys_id) VALUES
                                                      (1, 1), -- John conduit le trajet 1
                                                      (2, 2); -- Jane conduit le trajet 2

-- Association passagers-trajets (ASSO4)
INSERT INTO ASSO4 (passengers_id, journeys_id) VALUES
                                                   (1, 1), -- Bob est passager sur le trajet 1
                                                   (1, 2); -- Bob est passager sur le trajet 2

-- ============================================================
-- Création de vues utiles
-- ============================================================

-- Vue pour voir tous les trajets avec leurs conducteurs
CREATE VIEW view_trajets_conducteurs AS
SELECT
    j.journeys_id,
    j.start,
    j.final,
    j.travel_time,
    j.start_of_hours,
    j.end_of_hours,
    e.name AS conducteur_nom,
    c.place_available
FROM journeys j
         JOIN CONDUIRE cd ON j.journeys_id = cd.journeys_id
         JOIN conductors c ON cd.conductors_id = c.conductors_id
         JOIN employees e ON c.employees_id = e.employees_id;

-- Vue pour voir tous les trajets avec leurs passagers
CREATE VIEW view_trajets_passagers AS
SELECT
    j.journeys_id,
    j.start,
    j.final,
    e.name AS passager_nom
FROM journeys j
         JOIN ASSO4 a ON j.journeys_id = a.journeys_id
         JOIN passengers p ON a.passengers_id = p.passengers_id
         JOIN employees e ON p.employees_id = e.employees_id;

-- ============================================================
-- Index pour optimiser les performances
-- ============================================================

CREATE INDEX idx_users_email ON users(user_email);
CREATE INDEX idx_journeys_start ON journeys(start);
CREATE INDEX idx_journeys_final ON journeys(final);
CREATE INDEX idx_journeys_datetime ON journeys(start_of_hours, end_of_hours);