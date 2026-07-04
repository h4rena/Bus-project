<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <title>SIG-WEBMAPPING</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <div id="map"></div>

    <button id="btn-line-mode"><img src="assets/images/bus-stop.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Ligne</button>

    <div id="sidebar">
        <div id="sidebar-header">
            <h3><img src="assets/images/shuttle-bus.png" alt="" style="width:20px;height:20px;vertical-align:middle"> Lignes de bus</h3>
            <button id="btn-close-sidebar">✕</button>
        </div>
        <div id="bus-list"></div>
    </div>
    <button id="btn-show-sidebar"><img src="assets/images/menu.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Lignes</button>

    <div id="line-panel">
        <span>Cliquez sur les points dans l'ordre</span>
        <span id="line-count"><img src="assets/images/pin.png" alt="" style="width:16px;height:16px;vertical-align:middle"> 0</span>
        <button id="btn-valider-ligne"><img src="assets/images/check.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Valider</button>
        <button id="btn-annuler-ligne"><img src="assets/images/cancel.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Annuler</button>
    </div>

    <script>
        const map = L.map('map').setView([-18.986021, 47.532735], 15);

        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap', maxZoom: 19,
        }).addTo(map);

        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri', maxZoom: 20,
        });

        L.control.layers({ Carte: osm, Satellite: satellite }).addTo(map);

        const markers = {};
        const LINE_COLORS = ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6'];
        let lineMode = false;
        let selectedPoints = [];
        let previewLine = null;
        let linePolylines = [];
        let activeRoute = null;
        let pointTypes = [];
        let currentPointPopup = null;
        let activeBusLine = null;
        let busPolylineLayer = null;
        let sidebarVisible = true;

        function loadTypes() {
            fetch('get_types.php')
                .then(res => res.json())
                .then(data => { pointTypes = data; });
        }

        function loadPoints() {
            fetch('get_points.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;
                    data.points.forEach(p => addMarker(p));
                    showAllMarkers();
                });
        }

        function addMarker(point) {
            const lat = parseFloat(point.latitude);
            const lng = parseFloat(point.longitude);
            const nom = point.nom_point || 'Sans nom';
            const isBusStop = point.id_type == 1;

            let marker;
            if (isBusStop) {
                marker = L.marker([lat, lng], {
                    icon: L.icon({
                        iconUrl: 'assets/images/bus-32.png',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                        popupAnchor: [0, -16],
                    }),
                });
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }

            marker.bindPopup(
                `<b>${nom}</b><br>${point.nom_type || 'Point'}<br>Lat: ${lat}<br>Lng: ${lng}`
            );

            marker.on('click', () => onMarkerClick(point.id_point, marker));
            markers[point.id_point] = marker;
        }

        function onMarkerClick(id, marker) {
            if (!lineMode) return;

            const idx = selectedPoints.indexOf(id);
            if (idx !== -1) {
                selectedPoints.splice(idx, 1);
                marker.setIcon(L.Icon.Default.prototype);
            } else {
                selectedPoints.push(id);
                marker.setIcon(L.divIcon({
                    className: 'selected-marker',
                    html: '<div style="background:#FFD700;width:24px;height:24px;border-radius:50%;border:3px solid #FF8C00"></div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
                }));
            }

            updatePreview();
            document.getElementById('line-count').innerHTML = '<img src="assets/images/pin.png" alt="" style="width:16px;height:16px;vertical-align:middle"> ' + selectedPoints.length;
        }

        function updatePreview() {
            if (previewLine) { map.removeLayer(previewLine); previewLine = null; }
            if (selectedPoints.length < 2) return;

            const latlngs = selectedPoints.map(id => {
                const m = markers[id];
                return m ? m.getLatLng() : null;
            }).filter(Boolean);

            if (latlngs.length < 2) return;

            previewLine = L.polyline(latlngs, {
                color: '#2196F3', dashArray: '10, 10', weight: 3,
            }).addTo(map);
        }

        function loadLignes() {
            // Juste pour alimenter la sidebar via renderBusList()
            renderBusList();
        }

        function showAllMarkers() {
            Object.values(markers).forEach(m => map.addLayer(m));
        }

        function renderBusList() {
            fetch('get_lignes.php')
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('bus-list');
                    container.innerHTML = '';
                    data.forEach(l => {
                        const card = document.createElement('div');
                        card.className = 'bus-card';
                        card.dataset.id = l.id_ligne;

                        const header = document.createElement('div');
                        header.className = 'bus-header';
                        header.innerHTML = `
                            <span class="bus-dot" style="background:${l.couleur || 'gray'}"></span>
                            <strong>Ligne ${l.nom_ligne}</strong>
                            <span class="bus-count">${l.points.length} arrêts</span>
                        `;

                        const stopsList = document.createElement('div');
                        stopsList.className = 'bus-stops';
                        stopsList.style.display = 'none';
                        stopsList.innerHTML = l.points
                            .sort((a, b) => a.ordre - b.ordre)
                            .map((p, i) => `<div class="stop-item" data-point-id="${p.id_point}">
                                <span class="stop-idx">${i + 1}.</span> <img src="assets/images/bus-stop.png" alt="" style="width:14px;height:14px;vertical-align:middle"> ${p.nom_point || 'Sans nom'}
                            </div>`)
                            .join('');

                        header.onclick = () => toggleBusLine(l, card, stopsList);

                        card.appendChild(header);
                        card.appendChild(stopsList);
                        container.appendChild(card);
                    });
                });
        }

        function toggleBusLine(ligne, card, stopsList) {
            const isSame = activeBusLine === ligne.id_ligne;

            // Reset toutes les cartes
            document.querySelectorAll('.bus-card').forEach(c => {
                c.querySelector('.bus-stops').style.display = 'none';
                c.classList.remove('active');
            });

            // Cacher la polyline de bus active
            if (busPolylineLayer) { map.removeLayer(busPolylineLayer); busPolylineLayer = null; }
            if (activeRoute) { map.removeControl(activeRoute); activeRoute = null; }

            // Réafficher tous les marqueurs
            showAllMarkers();

            if (isSame) {
                activeBusLine = null;
                return;
            }

            activeBusLine = ligne.id_ligne;
            card.classList.add('active');
            stopsList.style.display = 'block';

            // Masquer les marqueurs qui ne sont pas dans cette ligne
            const linePointIds = new Set(ligne.points.map(p => p.id_point));
            Object.keys(markers).forEach(id => {
                if (!linePointIds.has(parseInt(id))) {
                    map.removeLayer(markers[id]);
                }
            });

            // Afficher la polyline (trajet OSRM si dispo)
            let latlngs;
            if (ligne.trajet_geo && ligne.trajet_geo.length > 0) {
                latlngs = ligne.trajet_geo;
            } else {
                latlngs = ligne.points
                    .sort((a, b) => a.ordre - b.ordre)
                    .map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
            }

            busPolylineLayer = L.polyline(latlngs, {
                color: ligne.couleur || '#e6194b',
                weight: 4, opacity: 0.85,
            }).addTo(map);

            busPolylineLayer.bindPopup(`<b>${ligne.nom_ligne || 'Sans nom'}</b>`);

            // Zoom sur la ligne
            const bounds = L.latLngBounds(latlngs);
            map.fitBounds(bounds, { padding: [60, 60] });

            // Gestion clic sur arrêts dans la liste
            stopsList.querySelectorAll('.stop-item').forEach(el => {
                el.onclick = (e) => {
                    e.stopPropagation();
                    const id = parseInt(el.dataset.pointId);
                    const marker = markers[id];
                    if (marker) {
                        map.setView(marker.getLatLng(), 17);
                        marker.openPopup();
                    }
                };
            });
        }

        map.on('click', function (e) {
            if (lineMode) return;
            if (activeRoute) { map.removeControl(activeRoute); activeRoute = null; }

            const { lat, lng } = e.latlng;

            const popupContent = document.createElement('div');
            popupContent.innerHTML = `
                <label>Nom :</label>
                <input id="popup-nom" type="text" placeholder="Nom du point" />
                <label>Type :</label>
                <select id="popup-type">
                    ${pointTypes.map(t =>
                        `<option value="${t.id_type}">${t.nom_type}</option>`
                    ).join('')}
                </select>
                <br>
                <button id="popup-btn-save"><img src="assets/images/check.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Ajouter</button>
            `;

            const popup = L.popup({ closeButton: false, className: 'point-form-popup' })
                .setLatLng(e.latlng)
                .setContent(popupContent)
                .openOn(map);

            setTimeout(() => {
                document.getElementById('popup-btn-save').onclick = function () {
                    const nom = document.getElementById('popup-nom').value.trim();
                    const id_type = parseInt(document.getElementById('popup-type').value) || null;

                    fetch('save_point.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            latitude: lat.toFixed(6),
                            longitude: lng.toFixed(6),
                            nom_point: nom,
                            id_type: id_type,
                        }),
                    })
                        .then(res => res.json())
                        .then(data => {
                            map.closePopup();
                            if (data.success) {
                                addMarker(data.point);
                            } else {
                                alert('Erreur : ' + data.error);
                            }
                        });
                };
            }, 100);
        });

        document.getElementById('btn-line-mode').onclick = function () {
            lineMode = !lineMode;
            this.classList.toggle('active', lineMode);
            this.innerHTML = lineMode
                ? '<img src="assets/images/check.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Ligne active'
                : '<img src="assets/images/bus-stop.png" alt="" style="width:16px;height:16px;vertical-align:middle"> Ligne';
            document.getElementById('line-panel').classList.toggle('visible', lineMode);
            if (!lineMode) resetLineSelection();
        };

        const BUS_COLORS = { '119': '#e6194b', '104': '#3cb44b', '117': '#4363d8' };

        document.getElementById('btn-valider-ligne').onclick = function () {
            if (activeRoute) { map.removeControl(activeRoute); activeRoute = null; }
            if (selectedPoints.length < 2) {
                alert('Sélectionnez au moins 2 points');
                return;
            }

            const nom = prompt('Nom de la ligne (ex: 119, 192A, 154) :', '');
            if (nom === null) return;

            const couleur = BUS_COLORS[nom] || '#e6194b';

            fetch('save_ligne.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nom_ligne: nom,
                    couleur: couleur,
                    points: selectedPoints,
                }),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        resetLineSelection();
                        document.getElementById('btn-line-mode').click();
                        renderBusList();
                    } else {
                        alert('Erreur : ' + data.error);
                    }
                })
                .catch(err => alert('Erreur réseau : ' + err.message));
        };

        document.getElementById('btn-annuler-ligne').onclick = resetLineSelection;

        document.getElementById('btn-close-sidebar').onclick = function () {
            document.getElementById('sidebar').classList.add('hidden');
        };
        document.getElementById('btn-show-sidebar').onclick = function () {
            document.getElementById('sidebar').classList.remove('hidden');
        };

        function resetLineSelection() {
            selectedPoints.forEach(id => {
                const m = markers[id];
                if (m) m.setIcon(L.Icon.Default.prototype);
            });
            selectedPoints = [];
            document.getElementById('line-count').innerHTML = '<img src="assets/images/pin.png" alt="" style="width:16px;height:16px;vertical-align:middle"> 0';
            if (previewLine) { map.removeLayer(previewLine); previewLine = null; }
        }

        loadTypes();
        loadPoints();
        loadLignes();
    </script>
</body>
</html>
