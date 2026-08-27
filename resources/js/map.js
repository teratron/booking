import { LngLatBounds, Map as MapLibreMap, NavigationControl, Popup } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

/**
 * Converts one pins-endpoint response into GeoJSON features. The two
 * response shapes are mutually exclusive — `clusters` (server-aggregated,
 * zoomed-out) or `pins` (individual markers) — never both. A cluster
 * feature's `point_count`/`point_count_abbreviated` match the property
 * names MapLibre's own supercluster used to set, so the 'clusters'/
 * 'cluster-count' layers below render server-built aggregates unchanged.
 */
function featuresFromPinsResponse(payload) {
    if (payload.clusters) {
        return payload.clusters.map((cluster) => ({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [cluster.lng, cluster.lat] },
            properties: { point_count: cluster.count, point_count_abbreviated: String(cluster.count) },
        }));
    }

    return payload.pins.map((pin) => ({
        type: 'Feature',
        geometry: { type: 'Point', coordinates: [pin.lng, pin.lat] },
        properties: { id: pin.id, tierBorderColour: pin.tier_border_colour },
    }));
}

function boundsFromPins(pins) {
    return pins.reduce((bbox, pin) => bbox.extend([pin.lng, pin.lat]), new LngLatBounds());
}

// Registered once Livewire's bundled Alpine boots (see resources/js/app.js —
// a separate `alpinejs` instance is deliberately never started in this
// project). `catalogMap(config)` is the factory every `<x-public.map>`
// instance's `x-data` calls.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogMap', (config) => ({
        map: null,
        popup: null,
        // Whether the server's own last response was truncated at its pin
        // cap — read by the template to show a "zoom in to see more"
        // affordance instead of leaving a partial, uncommunicated result
        // set on screen.
        truncated: false,

        init() {
            this.map = new MapLibreMap({
                container: this.$refs.container,
                style: config.styleUrl,
                center: [config.centerLng, config.centerLat],
                zoom: config.zoom,
            });

            this.map.addControl(new NavigationControl(), 'top-right');

            this.map.on('load', () => {
                // Not clustered client-side any more: the backend now
                // decides cluster-vs-pin mode from the request's own zoom
                // (see fetchPins) and ships pre-aggregated centroids below
                // the threshold, so a country-wide viewport never pays the
                // cost of serialising one feature per object. The 'clusters'
                // and 'unclustered-pin' layers below still key off a
                // `point_count` property, only it is now set by us on the
                // server-clustered features rather than by MapLibre's own
                // supercluster.
                this.map.addSource('catalog-pins', {
                    type: 'geojson',
                    data: { type: 'FeatureCollection', features: [] },
                });

                this.map.addLayer({
                    id: 'clusters',
                    type: 'circle',
                    source: 'catalog-pins',
                    filter: ['has', 'point_count'],
                    paint: {
                        'circle-color': config.brandColour,
                        'circle-radius': ['step', ['get', 'point_count'], 16, 10, 22, 50, 28],
                        'circle-opacity': 0.85,
                    },
                });

                this.map.addLayer({
                    id: 'cluster-count',
                    type: 'symbol',
                    source: 'catalog-pins',
                    filter: ['has', 'point_count'],
                    layout: { 'text-field': '{point_count_abbreviated}', 'text-size': 12 },
                    paint: { 'text-color': '#ffffff' },
                });

                this.map.addLayer({
                    id: 'unclustered-pin',
                    type: 'circle',
                    source: 'catalog-pins',
                    filter: ['!', ['has', 'point_count']],
                    paint: {
                        'circle-color': ['coalesce', ['get', 'tierBorderColour'], config.brandColour],
                        'circle-radius': 8,
                        'circle-stroke-width': 2,
                        'circle-stroke-color': '#ffffff',
                    },
                });

                this.map.on('click', 'clusters', (event) => this.expandCluster(event));
                this.map.on('click', 'unclustered-pin', (event) => this.openPinCard(event));
                this.map.on('mouseenter', 'unclustered-pin', () => {
                    this.map.getCanvas().style.cursor = 'pointer';
                });
                this.map.on('mouseleave', 'unclustered-pin', () => {
                    this.map.getCanvas().style.cursor = '';
                });

                this.fetchPins();
            });

            this.map.on('moveend', () => this.fetchPins());

            window.addEventListener('catalog-filters-changed', (event) => {
                this.filters = event.detail ?? {};
                this.fetchPins();
            });

            this.filters = config.initialFilters ?? {};
        },

        expandCluster(event) {
            // These are server-built grid centroids, not a supercluster
            // instance, so there is no per-cluster "expansion zoom" to ask
            // for as before -- step in by a fixed amount instead and let
            // the next fetchPins() (fired by the resulting 'moveend') ask
            // the server to re-aggregate at the new zoom.
            const feature = event.features[0];

            this.map.easeTo({
                center: feature.geometry.coordinates,
                zoom: this.map.getZoom() + 3,
            });
        },

        openPinCard(event) {
            const feature = event.features[0];
            const objectId = feature.properties.id;

            fetch(config.pinCardUrlTemplate.replace('__OBJECT__', objectId))
                .then((response) => response.text())
                .then((html) => {
                    this.popup?.remove();
                    this.popup = new Popup({ closeButton: true, maxWidth: '280px' })
                        .setLngLat(feature.geometry.coordinates)
                        .setHTML(html)
                        .addTo(this.map);
                });
        },

        fetchPins() {
            const bounds = this.map.getBounds();
            // this.filters comes from the catalog Livewire component's own
            // 'catalog-filters-changed' event, which carries a real `null`
            // for "no filter selected" -- URLSearchParams stringifies that
            // to the four-character string "null", which the backend would
            // then match as a real (if defensively-guarded) value. Dropped
            // here so the default, filterless map render never sends one.
            const activeFilters = Object.fromEntries(
                Object.entries(this.filters).filter(
                    ([, value]) => value !== null && value !== undefined && value !== '',
                ),
            );
            const params = new URLSearchParams({
                sw_lat: bounds.getSouth(),
                sw_lng: bounds.getWest(),
                ne_lat: bounds.getNorth(),
                ne_lng: bounds.getEast(),
                zoom: this.map.getZoom(),
                ...activeFilters,
            });

            fetch(`${config.pinsUrl}?${params.toString()}`)
                .then((response) => response.json())
                .then((payload) => this.applyPinsResponse(payload));
        },

        applyPinsResponse(payload) {
            const source = this.map.getSource('catalog-pins');
            if (!source) return;

            this.truncated = payload.truncated === true;
            source.setData({ type: 'FeatureCollection', features: featuresFromPinsResponse(payload) });
            this.fitInitialBoundsOnce(payload.pins);
        },

        // Only meaningful in pin mode (`payload.pins` is absent in cluster
        // mode) -- runs once per page load, the first time the visitor's
        // own starting viewport actually has individual pins to frame.
        fitInitialBoundsOnce(pins) {
            if (this.hasFitInitialBounds || !pins?.length) return;

            this.hasFitInitialBounds = true;
            this.map.fitBounds(boundsFromPins(pins), { padding: 48, maxZoom: 12 });
        },
    }));
});
