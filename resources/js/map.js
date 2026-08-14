import { LngLatBounds, Map as MapLibreMap, NavigationControl, Popup } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

// Registered once Livewire's bundled Alpine boots (see resources/js/app.js —
// a separate `alpinejs` instance is deliberately never started in this
// project). `catalogMap(config)` is the factory every `<x-public.map>`
// instance's `x-data` calls.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogMap', (config) => ({
        map: null,
        popup: null,

        init() {
            this.map = new MapLibreMap({
                container: this.$refs.container,
                style: config.styleUrl,
                center: [config.centerLng, config.centerLat],
                zoom: config.zoom,
            });

            this.map.addControl(new NavigationControl(), 'top-right');

            this.map.on('load', () => {
                this.map.addSource('catalog-pins', {
                    type: 'geojson',
                    data: { type: 'FeatureCollection', features: [] },
                    cluster: true,
                    clusterRadius: 50,
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
            const feature = event.features[0];
            const clusterId = feature.properties.cluster_id;
            const source = this.map.getSource('catalog-pins');

            source.getClusterExpansionZoom(clusterId, (error, zoom) => {
                if (error) return;

                this.map.easeTo({ center: feature.geometry.coordinates, zoom });
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
            const params = new URLSearchParams({
                sw_lat: bounds.getSouth(),
                sw_lng: bounds.getWest(),
                ne_lat: bounds.getNorth(),
                ne_lng: bounds.getEast(),
                ...this.filters,
            });

            fetch(`${config.pinsUrl}?${params.toString()}`)
                .then((response) => response.json())
                .then((payload) => {
                    const source = this.map.getSource('catalog-pins');
                    if (!source) return;

                    source.setData({
                        type: 'FeatureCollection',
                        features: payload.pins.map((pin) => ({
                            type: 'Feature',
                            geometry: { type: 'Point', coordinates: [pin.lng, pin.lat] },
                            properties: { id: pin.id, tierBorderColour: pin.tier_border_colour },
                        })),
                    });

                    if (!this.hasFitInitialBounds && payload.pins.length > 0) {
                        this.hasFitInitialBounds = true;
                        const pinBounds = payload.pins.reduce(
                            (bbox, pin) => bbox.extend([pin.lng, pin.lat]),
                            new LngLatBounds(),
                        );
                        this.map.fitBounds(pinBounds, { padding: 48, maxZoom: 12 });
                    }
                });
        },
    }));
});
