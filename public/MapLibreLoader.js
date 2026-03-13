const map = new maplibregl.Map({
    style: 'https://data.lfmaps.fr/styles/bright',
    center: [6.1844, 48.6937],
    zoom: 12,
    container: 'map',
})

let maplibrePromise = null;
let cssLoaded = false;

/**
 * Charge MapLibre GL et son CSS de manière différée au besoin
 * @returns {Promise} Le module MapLibre GL
 */
async function loadMapLibre() {
    if (!maplibrePromise) {
        maplibrePromise = Promise.all([
            import('maplibre-gl'),
            loadMapLibreCSS()
        ]).then(([module]) => module.default);
    }
    return maplibrePromise;
}

/**
 * Charge le CSS de MapLibre GL dynamiquement
 */
async function loadMapLibreCSS() {
    if (!cssLoaded) {
        await import('maplibre-gl/dist/maplibre-gl.css');
        cssLoaded = true;
    }
}
