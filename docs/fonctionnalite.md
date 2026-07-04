# Fonctionnalités

## 1. Liste des bus et affichage d'itinéraire

**Principe** : Afficher la liste des lignes de bus dans une sidebar, et permettre de visualiser leur tracé sur la carte.

**Fonctionnement** :
- Un bouton 📋 **Lignes** (en haut à gauche) ouvre/ferme la sidebar.
- La sidebar liste toutes les lignes de bus avec :
  - Un point coloré (couleur de la ligne)
  - Le nom de la ligne
  - Le nombre d'arrêts
- Au clic sur une ligne :
  - La liste de ses arrêts s'affiche (nom + ordre)
  - Une **polyline** colorée est tracée sur la carte le long du trajet de la ligne (via OSRM si disponible, sinon en ligne droite entre les arrêts)
  - Seuls les marqueurs de cette ligne restent visibles
  - La vue se centre sur l'étendue de la ligne
- Au clic sur un arrêt dans la liste :
  - La carte zoome sur le marqueur correspondant
  - La popup d'information s'ouvre
- Un second clic sur la même ligne masque le tracé et réaffiche tous les marqueurs.

**Fichiers impliqués** :
- `index.php` — `renderBusList()` (affichage sidebar), `toggleBusLine()` (affichage/masquage tracé)
- `get_lignes.php` — requête SQL renvoyant lignes + points + noms
- `assets/style.css` — styles de la sidebar et des cartes

---

## 2. Recherche d'itinéraire (find_route)

**Principe** : L'utilisateur clique un point de départ et un point d'arrivée sur la carte ; le système trouve la ligne de bus qui relie les deux arrêts les plus proches.

**Fonctionnement** :
- Un bouton 🧭 **Itinéraire** (en haut à droite) active le mode recherche.
- L'utilisateur clique sur la carte :
  1. **Premier clic** : pose un marqueur 🟢 **Départ**
  2. **Second clic** : pose un marqueur 🔴 **Arrivée**
- Une requête AJAX POST est envoyée à `find_route.php` avec les coordonnées.
- **Côté serveur** (`find_route.php`) :
  1. Calcule l'arrêt de bus (`id_type = 1`) le plus proche du départ et de l'arrivée via la **formule de Haversine** (distance en mètres).
  2. Recherche une ligne qui dessert ces deux arrêts dans le bon ordre (`ligne_points`).
  3. Retourne la ligne, son trajet OSRM, et les informations des deux arrêts.
- **Affichage du résultat** :
  - Un panneau en bas de l'écran indique : *"Prenez la ligne [nom], montez à [arrêt départ], descendez à [arrêt arrivée]"*
  - Les deux arrêts sont marqués avec l'icône bus
  - La polyline de la ligne est tracée avec sa couleur
  - La vue se centre sur le trajet
- Si **aucune ligne directe** ne relie les deux arrêts, un message approprié est affiché.

**Fichiers impliqués** :
- `index.php` — `handleItineraireClick()` (pose des marqueurs), `afficherResultatItineraire()` (affichage résultat)
- `find_route.php` — logique métier (Haversine, requêtes lignes)
- `connexion.php` — connexion PDO à la base PostgreSQL
