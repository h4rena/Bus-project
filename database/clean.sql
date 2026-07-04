-- Nettoyage complet

-- 1. Supprimer les lignes et leurs points
DELETE FROM ligne_points;
DELETE FROM lignes;

-- 2. Supprimer tous les points (y compris arrêts bus)
DELETE FROM points;

-- 3. Nettoyer les types et réinitialiser
DELETE FROM type_point;
ALTER SEQUENCE type_point_id_type_seq RESTART WITH 1;

-- 4. Un seul type : arrêt_bus (id_type = 1)
INSERT INTO type_point (nom_type) VALUES ('arrêt_bus');

-- 5. Réinitialiser les séquences des autres tables (optionnel)
ALTER SEQUENCE points_id_point_seq RESTART WITH 1;
ALTER SEQUENCE lignes_id_ligne_seq RESTART WITH 1;