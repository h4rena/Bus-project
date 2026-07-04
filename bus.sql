CREATE TABLE points(
    id_point SERIAL PRIMARY KEY,
    nom_point VARCHAR(100),
    longitude NUMERIC(10,6),
    latitude NUMERIC(10,6)
);

CREATE TABLE lignes (
    id_ligne SERIAL PRIMARY KEY,
    nom_ligne VARCHAR(100)
);

CREATE TABLE ligne_points (
    id_ligne INT REFERENCES lignes(id_ligne) ON DELETE CASCADE,
    id_point INT REFERENCES points(id_point),
    ordre INT NOT NULL,
    PRIMARY KEY (id_ligne, ordre)
);

-- 1. Types de point (arrêt_bus, parking, etc.)
CREATE TABLE type_point (
    id_type SERIAL PRIMARY KEY,
    nom_type VARCHAR(50) NOT NULL
);
INSERT INTO type_point (nom_type) VALUES ('arrêt_bus');

-- 2. Ajouter id_type à points existante
ALTER TABLE points ADD COLUMN id_type INT REFERENCES type_point(id_type);

-- 3. Ajouter couleur aux lignes (pour différencier 119, 192A, 154)
ALTER TABLE lignes ADD COLUMN couleur VARCHAR(7) DEFAULT '#e6194b';
ALTER TABLE lignes ADD COLUMN trajet_geo JSONB;


DELETE FROM ligne_points;
DELETE FROM lignes;
DELETE FROM points;