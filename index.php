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
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        #map { height: 100%; width: 100%; }

        #btn-line-mode {
            position: absolute; top: 80px; right: 10px; z-index: 1000;
            padding: 8px 14px; background: white;
            border: 2px solid rgba(0,0,0,0.2); border-radius: 4px;
            cursor: pointer; font-weight: bold; font-size: 14px;
        }
        #btn-line-mode.active {
            background: #4CAF50; color: white; border-color: #388E3C;
        }

        #line-panel {
            display: none; position: absolute; bottom: 30px;
            left: 50%; transform: translateX(-50%); z-index: 1000;
            background: white; padding: 10px 18px; border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3); font-size: 14px;
            text-align: center; gap: 12px; align-items: center;
        }
        #line-panel.visible { display: flex; }
        #line-panel button {
            padding: 6px 14px; border: none; border-radius: 4px;
            cursor: pointer; font-weight: bold;
        }
        #btn-valider-ligne { background: #4CAF50; color: white; }
        #btn-annuler-ligne { background: #f44336; color: white; }
        #line-count { font-weight: bold; margin: 0 8px; }

        #filter-panel {
            position: absolute; top: 80px; left: 10px; z-index: 1000;
            background: white; padding: 8px 12px; border-radius: 4px;
            border: 2px solid rgba(0,0,0,0.2); font-size: 14px;
        }
        #filter-panel select {
            padding: 4px; border-radius: 3px; border: 1px solid #ccc;
        }

        #btn-itineraire {
            position: absolute; top: 130px; right: 10px; z-index: 1000;
            padding: 8px 14px; background: white;
            border: 2px solid rgba(0,0,0,0.2); border-radius: 4px;
            cursor: pointer; font-weight: bold; font-size: 14px;
        }
        #btn-itineraire.active { background: #2196F3; color: white; border-color: #1565C0; }

        #itineraire-result {
            display: none; position: absolute; bottom: 30px;
            left: 50%; transform: translateX(-50%); z-index: 1000;
            background: white; padding: 12px 18px; border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3); font-size: 14px;
            max-width: 320px;
        }
        #itineraire-result.visible { display: block; }
        #itineraire-result button.close {
            float: right; border: none; background: none; cursor: pointer; font-weight: bold;
        }

        .leaflet-popup-content input,
        .leaflet-popup-content select {
            width: 100%; padding: 4px; margin: 4px 0;
            border: 1px solid #ccc; border-radius: 3px;
        }
        .leaflet-popup-content button {
            padding: 6px 16px; background: #4CAF50; color: white;
            border: none; border-radius: 4px; cursor: pointer;
            font-weight: bold; margin-top: 6px;
        }
    </style>
</head>
<body>
    <div id="map"></div>

    <button id="btn-line-mode">✏️ Ligne</button>

    <div id="filter-panel">
        <label>Filtrer : </label>
        <select id="filter-ligne">
            <option value="">Tous les points</option>
            <option value="119">Ligne 119</option>
            <option value="104">Ligne 104</option>
            <option value="117">Ligne 117</option>
        </select>
    </div>

    <div id="line-panel">
        <span>Cliquez sur les points dans l'ordre</span>
        <span id="line-count">📌 0</span>
        <button id="btn-valider-ligne">✅ Valider</button>
        <button id="btn-annuler-ligne">❌ Annuler</button>
    </div>

    <button id="btn-itineraire">🧭 Itinéraire</button>
    <div id="itineraire-result"></div>

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
                }).addTo(map);
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
            document.getElementById('line-count').textContent = '📌 ' + selectedPoints.length;
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
            fetch('get_lignes.php')
                .then(res => res.json())
                .then(data => {
                    linePolylines.forEach(p => map.removeLayer(p));
                    linePolylines = [];

                    data.forEach(l => {
                        const color = l.couleur || LINE_COLORS[l.id_ligne % LINE_COLORS.length];
                        let latlngs;

                        if (l.trajet_geo && l.trajet_geo.length > 0) {
                            latlngs = l.trajet_geo;
                        } else {
                            latlngs = l.points
                                .sort((a, b) => a.ordre - b.ordre)
                                .map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
                        }

                        const polyline = L.polyline(latlngs, {
                            color: color, weight: 4, opacity: 0.8,
                        }).addTo(map);

                        polyline.bindPopup(`<b>${l.nom_ligne || 'Sans nom'}</b>`);

                        polyline.on('click', function () {
                            if (l.points.length < 2) return;
                            if (activeRoute) map.removeControl(activeRoute);

                            const first = l.points[0];
                            const last = l.points[l.points.length - 1];

                            activeRoute = L.Routing.control({
                                waypoints: [
                                    L.latLng(parseFloat(first.latitude), parseFloat(first.longitude)),
                                    L.latLng(parseFloat(last.latitude), parseFloat(last.longitude)),
                                ],
                                router: L.Routing.osrmv1({
                                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                                }),
                                lineOptions: { styles: [{ color: '#ff0000', opacity: 0.7, weight: 5 }] },
                                show: true, addWaypoints: false,
                                fitSelectedRoutes: true, showAlternatives: false,
                            }).addTo(map);
                        });

                        linePolylines.push(polyline);
                    });

                    updateFilter();
                });
        }

        function updateFilter() {
            const selected = document.getElementById('filter-ligne').value;

            Object.keys(markers).forEach(id => {
                const marker = markers[id];
                if (!selected) {
                    map.addLayer(marker);
                    return;
                }
                map.removeLayer(marker);
            });

            linePolylines.forEach(poly => map.removeLayer(poly));

            fetch('get_lignes.php')
                .then(res => res.json())
                .then(data => {
                    data.forEach(l => {
                        if (selected && l.nom_ligne !== selected) return;

                        const color = l.couleur || LINE_COLORS[l.id_ligne % LINE_COLORS.length];
                        let latlngs;

                        if (l.trajet_geo && l.trajet_geo.length > 0) {
                            latlngs = l.trajet_geo;
                        } else {
                            latlngs = l.points
                                .sort((a, b) => a.ordre - b.ordre)
                                .map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
                        }

                        const polyline = L.polyline(latlngs, {
                            color: color, weight: 4, opacity: 0.8,
                        }).addTo(map);

                        polyline.bindPopup(`<b>${l.nom_ligne || 'Sans nom'}</b>`);
                        linePolylines.push(polyline);

                        l.points.forEach(p => {
                            const marker = markers[p.id_point];
                            if (marker) map.addLayer(marker);
                        });
                    });
                });
        }

        document.getElementById('filter-ligne').onchange = updateFilter;

        // ===== Itinéraire (départ -> arrivée -> bus le plus proche) =====
        let itineraireMode = false;
        let itineraireClicks = [];
        let itineraireMarkers = [];
        let itineraireLayers = [];

        function clearItineraire() {
            itineraireMarkers.forEach(m => map.removeLayer(m));
            itineraireLayers.forEach(l => map.removeLayer(l));
            itineraireMarkers = [];
            itineraireLayers = [];
            itineraireClicks = [];
            document.getElementById('itineraire-result').classList.remove('visible');
        }

        document.getElementById('btn-itineraire').onclick = function () {
            itineraireMode = !itineraireMode;
            this.classList.toggle('active', itineraireMode);
            clearItineraire();
            if (itineraireMode && lineMode) document.getElementById('btn-line-mode').click();
        };

        function handleItineraireClick(latlng) {
            const label = itineraireClicks.length === 0 ? '🟢 Départ' : '🔴 Arrivée';
            const color = itineraireClicks.length === 0 ? '#4CAF50' : '#f44336';
            const marker = L.circleMarker(latlng, {
                radius: 8, color: color, fillColor: color, fillOpacity: 1,
            }).addTo(map).bindTooltip(label, { permanent: true, direction: 'top' });
            itineraireMarkers.push(marker);
            itineraireClicks.push(latlng);

            if (itineraireClicks.length === 2) {
                fetch('find_route.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        depart: { lat: itineraireClicks[0].lat, lng: itineraireClicks[0].lng },
                        arrivee: { lat: itineraireClicks[1].lat, lng: itineraireClicks[1].lng },
                    }),
                })
                    .then(res => res.json())
                    .then(afficherResultatItineraire)
                    .catch(err => alert('Erreur réseau : ' + err.message));
            }
        }

        function afficherResultatItineraire(data) {
            const box = document.getElementById('itineraire-result');
            box.classList.add('visible');

            if (!data.success) {
                box.innerHTML = `<button class="close" onclick="clearItineraire()">✖</button>Erreur : ${data.error}`;
                return;
            }
            if (!data.trouve) {
                box.innerHTML = `<button class="close" onclick="clearItineraire()">✖</button>${data.message}<br>Arrêt départ : <b>${data.arret_depart.nom_point}</b><br>Arrêt arrivée : <b>${data.arret_arrivee.nom_point}</b>`;
                return;
            }

            const l = data.ligne;
            box.innerHTML = `
                <button class="close" onclick="clearItineraire()">✖</button>
                🚌 Prenez la ligne <b style="color:${l.couleur}">${l.nom_ligne}</b><br>
                Montez à : <b>${data.arret_depart.nom_point}</b><br>
                Descendez à : <b>${data.arret_arrivee.nom_point}</b>
            `;

            // Marqueurs des arrêts trouvés
            [data.arret_depart, data.arret_arrivee].forEach(a => {
                const m = L.marker([parseFloat(a.latitude), parseFloat(a.longitude)], {
                    icon: L.icon({
                        iconUrl: 'assets/images/bus-32.png', iconSize: [32, 32],
                        iconAnchor: [16, 16], popupAnchor: [0, -16],
                    }),
                }).addTo(map).bindPopup(`<b>${a.nom_point}</b>`);
                itineraireLayers.push(m);
            });

            // Trace de la ligne concernée en surbrillance
            if (l.trajet_geo && l.trajet_geo.length > 0) {
                const poly = L.polyline(l.trajet_geo, {
                    color: l.couleur, weight: 6, opacity: 0.9,
                }).addTo(map);
                itineraireLayers.push(poly);
                map.fitBounds(poly.getBounds(), { padding: [40, 40] });
            }
        }

        map.on('click', function (e) {
            if (itineraireMode) { handleItineraireClick(e.latlng); return; }
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
                <button id="popup-btn-save">✅ Ajouter</button>
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
            this.textContent = lineMode ? '✅ Ligne active' : '✏️ Ligne';
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
                        loadLignes();
                        // Mettre à jour le filtre avec un petit délai pour OSRM
                        setTimeout(updateFilter, 2000);
                    } else {
                        alert('Erreur : ' + data.error);
                    }
                })
                .catch(err => alert('Erreur réseau : ' + err.message));
        };

        document.getElementById('btn-annuler-ligne').onclick = resetLineSelection;

        function resetLineSelection() {
            selectedPoints.forEach(id => {
                const m = markers[id];
                if (m) m.setIcon(L.Icon.Default.prototype);
            });
            selectedPoints = [];
            document.getElementById('line-count').textContent = '📌 0';
            if (previewLine) { map.removeLayer(previewLine); previewLine = null; }
        }

        loadTypes();
        loadPoints();
        loadLignes();
    </script>
</body>
</html>
