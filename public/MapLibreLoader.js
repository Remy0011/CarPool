(() => {
    const journeys = Array.isArray(window.CARPOOL_JOURNEYS) ? window.CARPOOL_JOURNEYS : [];
    const mapElement = document.getElementById('map');

    if (!mapElement) {
        return;
    }

    const statusNode = document.getElementById('map-status');
    const selectNode = document.getElementById('journey-select');
    const playButton = document.getElementById('route-play');
    const resetButton = document.getElementById('route-reset');
    const progressBar = document.getElementById('journey-progress-bar');
    const progressLabel = document.getElementById('journey-progress-label');
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

    const state = {
        map: null,
        routeCoordinates: [],
        animationFrame: null,
        playing: false,
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
            playButton.textContent = 'Lancer';
        }
    }

    function updateCar(progress) {
        pointGeoJson.geometry.coordinates = interpolateCoordinate(state.routeCoordinates, progress);
        state.map.getSource('car-point').setData(pointGeoJson);
        setProgress(progress);
    }

    function resetRoutePosition() {
        stopAnimation();
        updateCar(0);
        setStatus('Voiture replacee au point de depart.');
    }

    function startAnimation() {
        if (!state.routeCoordinates.length || state.playing) {
            return;
        }

        state.playing = true;
        if (playButton) {
            playButton.textContent = 'En cours...';
        }

        const durationMs = 8000;
        const startTime = performance.now();

        const step = (timestamp) => {
            const progress = Math.min((timestamp - startTime) / durationMs, 1);
            updateCar(progress);

            if (progress < 1 && state.playing) {
                state.animationFrame = requestAnimationFrame(step);
                return;
            }

            stopAnimation();
            setStatus('Trajet termine. La voiture est arrivee a destination.');
        };

        state.animationFrame = requestAnimationFrame(step);
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

        stopAnimation();
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
                state.map.addLayer({
                    id: 'car-point-layer',
                    type: 'circle',
                    source: 'car-point',
                    paint: {
                        'circle-radius': 10,
                        'circle-color': '#ff5f2e',
                        'circle-stroke-width': 4,
                        'circle-stroke-color': '#ffffff',
                    },
                });

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
                setStatus('Animation mise en pause.');
                return;
            }
            startAnimation();
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', resetRoutePosition);
    }

    initMap();
})();
