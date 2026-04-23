(() => {
    const journeys = Array.isArray(window.CARPOOL_JOURNEYS) ? window.CARPOOL_JOURNEYS : [];
    const mapElement = document.getElementById('map');

    if (!mapElement) {
        return;
    }

    const statusNode = document.getElementById('map-status');
    const selectNode = document.getElementById('journey-select');
    const playButton = document.getElementById('route-play');
    const geoStartButton = document.getElementById('geo-start');
    const geoStopButton = document.getElementById('geo-stop');
    const resetButton = document.getElementById('route-reset');
    const progressBar = document.getElementById('journey-progress-bar');
    const progressLabel = document.getElementById('journey-progress-label');
    const modeLabel = document.getElementById('map-mode-label');
    const sidebarFields = {
        start: document.getElementById('journey-start'),
        final: document.getElementById('journey-final'),
        conductor: document.getElementById('journey-conductor'),
        startTime: document.getElementById('journey-start-time'),
        duration: document.getElementById('journey-duration'),
        endTime: document.getElementById('journey-end-time'),
    };

    const routeGeoJson = {
        type: 'Feature',
        geometry: {
            type: 'LineString',
            coordinates: [],
        },
    };

    const pointGeoJson = {
        type: 'Feature',
        geometry: {
            type: 'Point',
            coordinates: [2.3522, 48.8566],
        },
    };

    const liveTrackGeoJson = {
        type: 'Feature',
        geometry: {
            type: 'LineString',
            coordinates: [],
        },
    };

    const state = {
        map: null,
        routeCoordinates: [],
        animationFrame: null,
        playing: false,
        watchId: null,
        mode: 'simulation',
        currentJourney: null,
        carMarker: null,
        lastBearing: 0,
    };

    async function ensureMapLibreAssets() {
        if (window.maplibregl) {
            return window.maplibregl;
        }

        if (!document.getElementById('maplibre-css')) {
            const css = document.createElement('link');
            css.id = 'maplibre-css';
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css';
            document.head.appendChild(css);
        }

        await new Promise((resolve, reject) => {
            const existing = document.getElementById('maplibre-script');
            if (existing) {
                if (window.maplibregl) {
                    resolve();
                    return;
                }
                existing.addEventListener('load', resolve, {once: true});
                existing.addEventListener('error', reject, {once: true});
                return;
            }

            const script = document.createElement('script');
            script.id = 'maplibre-script';
            script.src = 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return window.maplibregl;
    }

    function setStatus(message, isError = false) {
        if (!statusNode) {
            return;
        }
        statusNode.textContent = message;
        statusNode.classList.toggle('is-error', isError);
    }

    function setProgress(progress) {
        const safeProgress = Math.max(0, Math.min(1, progress));
        const value = `${Math.round(safeProgress * 100)}%`;
        if (progressBar) {
            progressBar.style.width = value;
        }
        if (progressLabel) {
            progressLabel.textContent = value;
        }
    }

    function setMode(mode) {
        state.mode = mode;
        if (modeLabel) {
            modeLabel.textContent = mode === 'geolocation' ? 'Geolocalisation' : 'Simulation';
        }
        if (playButton) {
            playButton.textContent = state.playing ? 'Pause simulation' : 'Lancer la simulation';
        }
        if (geoStartButton) {
            geoStartButton.disabled = mode === 'geolocation';
        }
    }

    function buildCarMarkerElement() {
        const wrapper = document.createElement('div');
        wrapper.className = 'carpool-car-marker';
        wrapper.style.width = '64px';
        wrapper.style.height = '64px';
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.justifyContent = 'center';
        wrapper.style.transformOrigin = '50% 50%';
        wrapper.style.filter = 'drop-shadow(0 10px 18px rgba(21, 32, 54, 0.28))';

        const image = document.createElement('img');
        image.src = '/img/CarPool%20d%C3%A9tour%C3%A9.png';
        image.alt = 'Voiture CarPool';
        image.style.width = '64px';
        image.style.height = '64px';
        image.style.objectFit = 'contain';
        image.style.pointerEvents = 'none';
        image.draggable = false;

        wrapper.appendChild(image);

        return wrapper;
    }

    function formatDateTime(value) {
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function updateSidebar(journey) {
        if (!journey) {
            return;
        }

        sidebarFields.start.textContent = journey.start;
        sidebarFields.final.textContent = journey.final;
        sidebarFields.conductor.textContent = journey.conductor_name;
        sidebarFields.startTime.textContent = formatDateTime(journey.start_of_hours);
        sidebarFields.duration.textContent = `${journey.travel_time} h`;
        sidebarFields.endTime.textContent = formatDateTime(journey.end_of_hours);
    }

    function interpolateCoordinate(line, progress) {
        if (line.length === 0) {
            return [2.3522, 48.8566];
        }

        if (line.length === 1) {
            return line[0];
        }

        const scaled = Math.max(0, Math.min(1, progress)) * (line.length - 1);
        const startIndex = Math.floor(scaled);
        const endIndex = Math.min(line.length - 1, startIndex + 1);
        const localProgress = scaled - startIndex;
        const [startLng, startLat] = line[startIndex];
        const [endLng, endLat] = line[endIndex];

        return [
            startLng + (endLng - startLng) * localProgress,
            startLat + (endLat - startLat) * localProgress,
        ];
    }

    function stopAnimation() {
        if (state.animationFrame) {
            cancelAnimationFrame(state.animationFrame);
            state.animationFrame = null;
        }
        state.playing = false;
        if (playButton) {
            playButton.textContent = 'Lancer la simulation';
        }
    }

    function updateLiveTrack(position) {
        if (!state.map || !state.map.getSource('live-track')) {
            return;
        }

        const existing = liveTrackGeoJson.geometry.coordinates;
        const previous = existing[existing.length - 1];
        const nextPoint = [Number(position[0]), Number(position[1])];

        if (!previous || previous[0] !== nextPoint[0] || previous[1] !== nextPoint[1]) {
            existing.push(nextPoint);
        }

        state.map.getSource('live-track').setData(liveTrackGeoJson);
    }

    function setCarPosition(coordinates, progress = 0, shouldFollow = true) {
        const previousCoordinates = pointGeoJson.geometry.coordinates;
        pointGeoJson.geometry.coordinates = coordinates;
        state.map.getSource('car-point').setData(pointGeoJson);

        if (state.carMarker) {
            state.lastBearing = computeBearing(previousCoordinates, coordinates);
            state.carMarker.setLngLat(coordinates);
            state.carMarker.setRotation(state.lastBearing);
        }

        setProgress(progress);

        if (shouldFollow) {
            state.map.easeTo({
                center: coordinates,
                duration: 900,
                essential: true,
            });
        }
    }

    function clearLiveTrack() {
        liveTrackGeoJson.geometry.coordinates = [];
        if (state.map && state.map.getSource('live-track')) {
            state.map.getSource('live-track').setData(liveTrackGeoJson);
        }
    }

    function computeBearing(from, to) {
        const deltaLng = to[0] - from[0];
        const deltaLat = to[1] - from[1];

        if (deltaLng === 0 && deltaLat === 0) {
            return state.lastBearing;
        }

        return Math.atan2(deltaLng, deltaLat) * 180 / Math.PI;
    }

    function resetRoutePosition() {
        stopGeolocationTracking(false);
        stopAnimation();
        clearLiveTrack();

        if (!state.routeCoordinates.length) {
            return;
        }

        setMode('simulation');
        setCarPosition(state.routeCoordinates[0], 0, false);
        fitToRoute();
        setStatus('Voiture replacee au point de depart.');
    }

    function startAnimation() {
        if (!state.routeCoordinates.length || state.playing) {
            return;
        }

        stopGeolocationTracking(false);
        setMode('simulation');
        state.playing = true;
        if (playButton) {
            playButton.textContent = 'Pause simulation';
        }

        const durationMs = 8000;
        const startTime = performance.now();

        const step = (timestamp) => {
            const progress = Math.min((timestamp - startTime) / durationMs, 1);
            const coordinates = interpolateCoordinate(state.routeCoordinates, progress);
            setCarPosition(coordinates, progress, true);

            if (progress < 1 && state.playing) {
                state.animationFrame = requestAnimationFrame(step);
                return;
            }

            stopAnimation();
            setStatus('Trajet termine. La voiture est arrivee a destination.');
        };

        state.animationFrame = requestAnimationFrame(step);
    }

    function estimateProgressFromPosition(position) {
        if (!state.routeCoordinates.length) {
            return 0;
        }

        let closestIndex = 0;
        let closestDistance = Number.POSITIVE_INFINITY;

        state.routeCoordinates.forEach((coordinate, index) => {
            const deltaLng = coordinate[0] - position[0];
            const deltaLat = coordinate[1] - position[1];
            const distance = (deltaLng * deltaLng) + (deltaLat * deltaLat);

            if (distance < closestDistance) {
                closestDistance = distance;
                closestIndex = index;
            }
        });

        return state.routeCoordinates.length > 1 ? closestIndex / (state.routeCoordinates.length - 1) : 0;
    }

    function stopGeolocationTracking(withMessage = true) {
        if (state.watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(state.watchId);
            state.watchId = null;
        }

        if (state.mode === 'geolocation') {
            setMode('simulation');
            if (withMessage) {
                setStatus('Suivi GPS arrete. Vous pouvez relancer la simulation.');
            }
        }
    }

    function startGeolocationTracking() {
        if (!navigator.geolocation) {
            setStatus('La geolocalisation du navigateur n est pas disponible. Utilisez la simulation.', true);
            return;
        }

        if (!state.map || !state.currentJourney) {
            setStatus('Choisissez d abord un trajet pour activer le suivi.', true);
            return;
        }

        stopGeolocationTracking(false);
        stopAnimation();
        clearLiveTrack();
        setMode('geolocation');
        setStatus('Demande d acces a la geolocalisation en cours...');

        state.watchId = navigator.geolocation.watchPosition(
            (position) => {
                const coordinates = [
                    position.coords.longitude,
                    position.coords.latitude,
                ];
                const progress = estimateProgressFromPosition(coordinates);
                updateLiveTrack(coordinates);
                setCarPosition(coordinates, progress, true);
                setStatus(`Position GPS recue. Precision approx. ${Math.round(position.coords.accuracy)} m.`);
            },
            (error) => {
                stopGeolocationTracking(false);

                let message = 'Impossible de recuperer votre position.';
                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Geolocalisation refusee. Lancez la simulation pour illustrer le trajet.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Position indisponible pour le moment. La simulation reste disponible.';
                } else if (error.code === error.TIMEOUT) {
                    message = 'Le GPS met trop de temps a repondre. Essayez la simulation.';
                }

                setStatus(message, true);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 1000,
                timeout: 10000,
            }
        );
    }

    function fitToRoute() {
        if (!state.routeCoordinates.length) {
            return;
        }

        const bounds = state.routeCoordinates.reduce(
            (acc, coord) => acc.extend(coord),
            new window.maplibregl.LngLatBounds(state.routeCoordinates[0], state.routeCoordinates[0])
        );

        state.map.fitBounds(bounds, {
            padding: 60,
            duration: 1200,
        });
    }

    async function geocodePlace(place) {
        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('format', 'json');
        url.searchParams.set('limit', '1');
        url.searchParams.set('q', place);

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Geocoding indisponible');
        }

        const results = await response.json();
        if (!Array.isArray(results) || results.length === 0) {
            throw new Error(`Lieu introuvable : ${place}`);
        }

        return [Number(results[0].lon), Number(results[0].lat)];
    }

    async function fetchRoute(start, end) {
        const url = new URL(`https://router.project-osrm.org/route/v1/driving/${start[0]},${start[1]};${end[0]},${end[1]}`);
        url.searchParams.set('overview', 'full');
        url.searchParams.set('geometries', 'geojson');

        const response = await fetch(url.toString());
        if (!response.ok) {
            throw new Error('Calcul d itineraire indisponible');
        }

        const data = await response.json();
        const coordinates = data?.routes?.[0]?.geometry?.coordinates;
        if (!Array.isArray(coordinates) || coordinates.length < 2) {
            throw new Error('Itineraire non disponible');
        }

        return coordinates;
    }

    function fallbackCoordinates(place) {
        const lookup = {
            paris: [2.3522, 48.8566],
            lyon: [4.8357, 45.7640],
            marseille: [5.3698, 43.2965],
            nancy: [6.1844, 48.6921],
            metz: [6.1757, 49.1193],
            lille: [3.0573, 50.6292],
            strasbourg: [7.7521, 48.5734],
            toulouse: [1.4442, 43.6047],
            bordeaux: [-0.5792, 44.8378],
        };
        const key = String(place || '').trim().toLowerCase();
        return lookup[key] || [2.3522, 48.8566];
    }

    async function loadJourney(journeyId) {
        const journey = journeys.find((item) => Number(item.id) === Number(journeyId));
        if (!journey || !state.map) {
            return;
        }

        state.currentJourney = journey;
        stopGeolocationTracking(false);
        stopAnimation();
        clearLiveTrack();
        updateSidebar(journey);
        setStatus(`Chargement de l itineraire ${journey.start} -> ${journey.final}...`);
        setProgress(0);

        try {
            const [startCoordinates, endCoordinates] = await Promise.all([
                geocodePlace(journey.start),
                geocodePlace(journey.final),
            ]);
            state.routeCoordinates = await fetchRoute(startCoordinates, endCoordinates);
            setStatus('Itineraire charge. Appuyez sur Lancer pour demarrer la voiture.');
        } catch (error) {
            state.routeCoordinates = [
                fallbackCoordinates(journey.start),
                fallbackCoordinates(journey.final),
            ];
            setStatus(error instanceof Error ? error.message : 'Impossible de charger la route.', true);
        }

        routeGeoJson.geometry.coordinates = state.routeCoordinates;
        pointGeoJson.geometry.coordinates = state.routeCoordinates[0];
        state.map.getSource('route-line').setData(routeGeoJson);
        state.map.getSource('car-point').setData(pointGeoJson);
        state.map.getSource('live-track').setData(liveTrackGeoJson);
        if (state.carMarker) {
            state.lastBearing = 0;
            state.carMarker.setLngLat(pointGeoJson.geometry.coordinates);
            state.carMarker.setRotation(0);
        }
        setMode('simulation');
        fitToRoute();
    }

    async function initMap() {
        if (!journeys.length) {
            setStatus('Ajoutez d abord un trajet dans Reservation pour activer la carte.', true);
            return;
        }

        try {
            const maplibregl = await ensureMapLibreAssets();
            state.map = new maplibregl.Map({
                container: 'map',
                style: {
                    version: 8,
                    sources: {
                        osm: {
                            type: 'raster',
                            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                            tileSize: 256,
                            attribution: '&copy; OpenStreetMap contributors',
                        },
                    },
                    layers: [
                        {
                            id: 'osm',
                            type: 'raster',
                            source: 'osm',
                        },
                    ],
                },
                center: [2.3522, 48.8566],
                zoom: 5.5,
            });

            state.map.addControl(new maplibregl.NavigationControl(), 'top-right');

            state.map.on('load', async () => {
                state.map.addSource('route-line', {
                    type: 'geojson',
                    data: routeGeoJson,
                });
                state.map.addLayer({
                    id: 'route-line-layer',
                    type: 'line',
                    source: 'route-line',
                    layout: {
                        'line-cap': 'round',
                        'line-join': 'round',
                    },
                    paint: {
                        'line-color': '#1f79ff',
                        'line-width': 7,
                        'line-opacity': 0.92,
                    },
                });

                state.map.addSource('car-point', {
                    type: 'geojson',
                    data: pointGeoJson,
                });

                state.map.addSource('live-track', {
                    type: 'geojson',
                    data: liveTrackGeoJson,
                });
                state.map.addLayer({
                    id: 'live-track-layer',
                    type: 'line',
                    source: 'live-track',
                    layout: {
                        'line-cap': 'round',
                        'line-join': 'round',
                    },
                    paint: {
                        'line-color': '#ff8a00',
                        'line-width': 5,
                        'line-opacity': 0.85,
                        'line-dasharray': [1.2, 1.2],
                    },
                });

                state.carMarker = new maplibregl.Marker({
                    element: buildCarMarkerElement(),
                    anchor: 'center',
                    rotationAlignment: 'map',
                    pitchAlignment: 'map',
                })
                    .setLngLat(pointGeoJson.geometry.coordinates)
                    .addTo(state.map);

                await loadJourney(journeys[0].id);
            });
        } catch (error) {
            setStatus('Impossible de charger la librairie cartographique.', true);
        }
    }

    if (selectNode) {
        selectNode.addEventListener('change', (event) => {
            loadJourney(event.target.value);
        });
    }

    if (playButton) {
        playButton.addEventListener('click', () => {
            if (state.playing) {
                stopAnimation();
                setStatus('Simulation mise en pause.');
                return;
            }
            startAnimation();
        });
    }

    if (geoStartButton) {
        geoStartButton.addEventListener('click', startGeolocationTracking);
    }

    if (geoStopButton) {
        geoStopButton.addEventListener('click', () => {
            stopGeolocationTracking(true);
            stopAnimation();
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', resetRoutePosition);
    }

    initMap();
})();
