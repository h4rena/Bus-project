# SIG-Webmapping — Application de cartographie interactive

Application PHP/PostgreSQL permettant de stocker des points cliqués sur une carte, de créer des lignes (polylignes) et de visualiser des lignes de bus avec itinéraire routier réel (OSRM).

## Prérequis

- **PHP 8.2+** avec l'extension `pdo_pgsql` (utiliser celui de XAMPP)
- **PostgreSQL** avec la base `webmapping_db`
- Navigateur moderne

## Installation

### 1. Base de données

```bash
# Créer la base (si pas déjà faite)
createdb -U postgres webmapping_db

# Exécuter le schéma
psql -h localhost -U postgres -d webmapping_db -f webmapping.sql
```

### 2. Lancer l'application

```bash
/opt/lampp/bin/php -S 127.0.0.1:8080 -t "/chemin/vers/le/dossier/php"
```

Ouvrir `http://127.0.0.1:8080` dans le navigateur.

## Fonctionnalités

### 🗺️ Carte
- Fond OSM par défaut + bascule vers satellite (Esri)
- Centrée sur Antananarivo (ITU), zoom 19

### 📍 Points
- **Clic sur la carte** → popup avec nom + type (arrêt_bus, parking, etc.)
- Arrêts de bus → icône spécifique (`assets/images/bus-32.png`)
- Sauvegarde automatique en base PostgreSQL

### 📏 Lignes (polylignes)
- **Mode ligne** (bouton ✏️) : cliquer sur les marqueurs dans l'ordre
- Prévisualisation en pointillés bleus
- **Validation** → sauvegarde + calcul **OSRM** automatique (itinéraire routier réel)
- Les lignes suivent les vraies routes, pas des segments droits

### 🚌 Lignes de bus
- Filtre intégré (coin haut gauche) : 119, 104, 117
- Affiche uniquement les arrêts de la ligne sélectionnée + son tracé
- Couleurs distinctes par ligne

### 🧭 Itinéraire (Routing)
- **Clic sur une ligne** → itinéraire OSRM détaillé avec distance/temps estimé
- Utilise le service public `router.project-osrm.org`

## Structure du projet

```
php/
├── index.php           # Page principale (carte Leaflet + UI)
├── connexion.php       # Connexion PDO à PostgreSQL
├── save_point.php      # API POST : créer un point
├── get_points.php      # API GET : lister les points
├── save_ligne.php      # API POST : créer une ligne + appel OSRM
├── get_lignes.php      # API GET : lister les lignes
├── get_types.php       # API GET : lister les types de point
├── assets/images/      # Icônes personnalisées (bus-32.png)
├── database/
│   └── insert-bus.sql  # Insertion des arrêts (119, 104, 117)
└── README.md
```

## Base de données

### Tables

| Table | Rôle |
|-------|------|
| `points` | Points géolocalisés (latitude, longitude, type) |
| `type_point` | Types de points (arrêt_bus, parking, entrée, autre) |
| `lignes` | Lignes de bus ou autres polylignes (nom, couleur, trajet_geo) |
| `ligne_points` | Association ordonnée points ↔ lignes |

### Types de point

| id_type | nom_type |
|---------|----------|
| 1 | arrêt_bus |
| 6 | parking |
| 7 | entrée |
| 8 | autre |

### Insérer les données de bus

```bash
PGPASSWORD=3958 psql -h localhost -U postgres -d webmapping_db \
  -f database/insert-bus.sql
```

## API

### `GET /get_types.php`
```json
[{"id_type":1,"nom_type":"arrêt_bus"}, ...]
```

### `GET /get_points.php`
```json
{"success":true,"points":[
  {"id_point":1,"nom_point":"Gare","latitude":"-18.986","longitude":"47.532","id_type":1,"nom_type":"arrêt_bus"}
]}
```

### `POST /save_point.php`
```json
// Requête
{"latitude":-18.986,"longitude":47.532,"nom_point":"Gare","id_type":1}
// Réponse
{"success":true,"id":1,"point":{"id_point":1,"nom_point":"Gare","longitude":"47.532","latitude":"-18.986","id_type":1}}
```

### `GET /get_lignes.php`
```json
[{"id_ligne":1,"nom_ligne":"119","couleur":"#e6194b","trajet_geo":[[-18.98,47.53],...],"points":[...]}]
```

### `POST /save_ligne.php`
```json
// Requête
{"nom_ligne":"119","couleur":"#e6194b","points":[1,3,5]}
// Réponse (trajet_geo = résultat OSRM)
{"success":true,"id_ligne":1,"trajet_geo":[[...],...]}
```

## Technologies

- **[Leaflet](https://leafletjs.com/)** — Cartographie interactive
- **[Leaflet Routing Machine](https://www.lleaflet.com/)** — Calcul d'itinéraire OSRM
- **[OSRM](https://project-osrm.org/)** — Moteur de routage open-source
- **[PostgreSQL](https://www.postgresql.org/)** — Base de données
- **PHP 8** (PDO) — Backend API
- **[OpenStreetMap](https://www.openstreetmap.org/)** & **[Esri](https://www.esri.com/)** — Tuiles cartographiques
