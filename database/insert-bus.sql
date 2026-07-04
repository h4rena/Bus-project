-- =========================================================
-- Insertion des arrêts de bus (lignes 119, 104, 117)
-- Type 1 = arrêt_bus (dans type_point)
--
-- Usage : PGPASSWORD=yourpassword psql -h localhost -U postgres -d webmapping_db -f database/insert-bus.sql
-- =========================================================

BEGIN;

-- ==================== LIGNE 119 ====================
WITH d(ordre, nom, lng, lat) AS (
    VALUES
    (1,  'Arrêt 119-1',         47.506433, -18.904916),
    (2,  'Arrêt 119-2',         47.509222, -18.905737),
    (3,  'Arrêt 119-3',         47.508959, -18.908872),
    (4,  'Arrêt 119-4',         47.508257, -18.915851),
    (5,  'Arrêt 119-5',         47.509876, -18.916480),
    (6,  'Arrêt 119-6',         47.513148, -18.916389),
    (7,  'Arrêt 119-7',         47.517702, -18.912935),
    (8,  'Tribunaly',           47.518587, -18.915339),
    (9,  'Anosy',               47.522427, -18.917976),
    (10, 'Ambohijatovo',        47.528392, -18.911733),
    (11, 'Amtsahabe Mascotte',  47.533782, -18.913914),
    (12, 'Amtsahabe Fisa',      47.535170, -18.913604),
    (13, 'Ankoraotra Gazety',   47.537589, -18.913923),
    (14, 'Ampasa',              47.539123, -18.915288),
    (15, 'Ampasanimalo Buffare',47.540764, -18.916439),
    (16, 'Sampanana',           47.542765, -18.917316),
    (17, 'Amboho',              47.543746, -18.917626),
    (18, 'Pavé',                47.545833, -18.917372),
    (19, 'Tsiadana Akondro',    47.547302, -18.916880),
    (20, 'Ankatso',             47.549432, -18.914988)
), inserted AS (
    INSERT INTO points (nom_point, longitude, latitude, id_type)
    SELECT nom, lng, lat, 1 FROM d ORDER BY ordre
    RETURNING id_point
), ids AS (
    SELECT array_agg(id_point ORDER BY id_point) AS arr FROM inserted
), ligne AS (
    INSERT INTO lignes (nom_ligne, couleur) VALUES ('119', '#e6194b') RETURNING id_ligne
)
INSERT INTO ligne_points (id_ligne, id_point, ordre)
SELECT (SELECT id_ligne FROM ligne),
       unnest((SELECT arr FROM ids)),
       generate_series(1, (SELECT array_length(arr, 1) FROM ids));

-- ==================== LIGNE 104 ====================
WITH d(ordre, nom, lng, lat) AS (
    VALUES
    (1,  'Akadimbaoka',         47.525259, -18.944775),
    (2,  'Androndrakely',       47.532747, -18.947696),
    (3,  'Tsena',               47.533741, -18.946461),
    (4,  'FJKM',                47.535357, -18.944050),
    (5,  'Pandrafitra',         47.535999, -18.942932),
    (6,  'Ampamatanana',        47.537363, -18.942026),
    (7,  'Ambohitsoa',          47.540421, -18.939389),
    (8,  'Tany Malalaka',       47.541665, -18.938370),
    (9,  'Garazy',              47.541880, -18.935646),
    (10, 'Sapanany Mahazoariva',47.543221, -18.932686),
    (11, 'Espace RAVO',         47.542702, -18.930190),
    (12, 'Arrêt 104',           47.543329, -18.928121)
), inserted AS (
    INSERT INTO points (nom_point, longitude, latitude, id_type)
    SELECT nom, lng, lat, 1 FROM d ORDER BY ordre
    RETURNING id_point
), ids AS (
    SELECT array_agg(id_point ORDER BY id_point) AS arr FROM inserted
), ligne AS (
    INSERT INTO lignes (nom_ligne, couleur) VALUES ('104', '#3cb44b') RETURNING id_ligne
)
INSERT INTO ligne_points (id_ligne, id_point, ordre)
SELECT (SELECT id_ligne FROM ligne),
       unnest((SELECT arr FROM ids)),
       generate_series(1, (SELECT array_length(arr, 1) FROM ids));

-- ==================== LIGNE 117 ====================
WITH d(ordre, nom, lng, lat) AS (
    VALUES
    (1,  'Ampamatanana',             47.537363, -18.942026),
    (2,  'Ambohitsoa',               47.540421, -18.939389),
    (3,  'Tany Malalaka',            47.541665, -18.938370),
    (4,  'Garazy',                   47.541880, -18.935646),
    (5,  'Sapanany Mahazoariva',     47.543221, -18.932686),
    (6,  'Espace RAVO',              47.542702, -18.930190),
    (7,  'Arrêt 104',                47.543329, -18.928121),
    (8,  'Ambatoroka Fiangonana',    47.541677, -18.924429),
    (9,  'Ambanidia Garazy',         47.540840, -18.922725),
    (10, 'FJKM Ambanidia',           47.538282, -18.919393),
    (11, 'Akazotokana Total',        47.535851, -18.917232),
    (12, 'Antsahabe',                47.533936, -18.915239),
    (13, 'Ambojatovo',               47.530557, -18.914093)
), inserted AS (
    INSERT INTO points (nom_point, longitude, latitude, id_type)
    SELECT nom, lng, lat, 1 FROM d ORDER BY ordre
    RETURNING id_point
), ids AS (
    SELECT array_agg(id_point ORDER BY id_point) AS arr FROM inserted
), ligne AS (
    INSERT INTO lignes (nom_ligne, couleur) VALUES ('117', '#4363d8') RETURNING id_ligne
)
INSERT INTO ligne_points (id_ligne, id_point, ordre)
SELECT (SELECT id_ligne FROM ligne),
       unnest((SELECT arr FROM ids)),
       generate_series(1, (SELECT array_length(arr, 1) FROM ids));

COMMIT;

-- Vérification
SELECT l.nom_ligne, l.couleur, COUNT(lp.id_point) AS nb_arrets
FROM lignes l
JOIN ligne_points lp ON lp.id_ligne = l.id_ligne
GROUP BY l.id_ligne, l.nom_ligne, l.couleur
ORDER BY l.nom_ligne;
