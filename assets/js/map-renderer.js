/**
 * map-renderer.js
 * Shared MapLibre GL rendering helpers: route layers, flight arcs, and map markers.
 * Depends on map-config.js (window.MapConfig must be loaded first).
 * Consumed by both public_map.js and trip_single.js.
 */

window.MapRenderer = (function () {
    'use strict';

    /**
     * Creates the DOM element for a cluster marker.
     * @param {number} count - Number of points in the cluster.
     * @returns {HTMLElement}
     */
    function createClusterMarkerEl(count) {
        const el = document.createElement('div');
        el.className = 'marker-cluster-custom';
        el.innerHTML = `<span>${count}</span>`;
        el.style.cssText = [
            'background:#1e293b', 'border:2px solid white', 'border-radius:50%',
            'width:40px', 'height:40px', 'display:flex', 'align-items:center',
            'justify-content:center', 'box-shadow:0 2px 6px rgba(0,0,0,.2)',
            'font-weight:600', 'color:white', 'font-size:13px', 'cursor:pointer'
        ].join(';');
        return el;
    }

    /**
     * Creates the DOM element for a single point marker.
     * Uses MapConfig.pointTypeConfig for icons and colors.
     * @param {{ type: string, tripColor: string }} point
     * @returns {HTMLElement}
     */
    function createPointMarkerEl(point) {
        const cfg      = MapConfig.pointTypeConfig;
        const typeCfg  = cfg[point.type] || cfg['visit'];
        const iconStyle = typeCfg.darkText ? 'color:#000;stroke:#000;' : '';

        const el = document.createElement('div');
        el.className = 'custom-point-marker';
        el.innerHTML = `
            <div class="point-marker-inner point-type-${point.type}"
                 style="background-color:${typeCfg.color};border-color:${point.tripColor || '#3388ff'};">
                <span class="point-icon" style="${iconStyle}">${typeCfg.icon}</span>
            </div>
        `;
        return el;
    }

    /**
     * Adds a route to the map as a MapLibre GL line layer.
     *
     * If the route is a simple A-to-B plane route (exactly 2 coordinates), it should
     * be rendered as a deck.gl arc instead of a line. In that case this function does
     * NOT add any layer and returns a flight-data entry object for the caller to
     * accumulate and pass to renderFlightArcs().
     *
     * @param {maplibregl.Map} map
     * @param {object} route  - { id, transport_type, geojson }
     * @param {string} sourceId - Unique source ID (e.g. "route-1-42")
     * @param {string} layerId  - Unique layer ID (e.g. "route-layer-1-42")
     * @param {object} [opts]
     * @param {boolean} [opts.isFuture=false]   - Future trips get grey dashed style.
     * @param {string}  [opts.visibility='visible'] - MapLibre layer visibility.
     * @returns {{ source, target, color }|null}
     *   Returns a flight-data entry if this is a plane arc, null if a layer was added.
     */
    function addRouteLayer(map, route, sourceId, layerId, opts) {
        opts = opts || {};
        var isFuture   = opts.isFuture   || false;
        var visibility = opts.visibility  || 'visible';

        var geojson = MapConfig.normalizeGeojson(route.geojson);
        if (!geojson || !geojson.geometry) return null;

        var transportType = route.transport_type || 'car';
        var cfg           = MapConfig.transportConfig[transportType] || MapConfig.transportConfig['car'];
        var coords        = geojson.geometry.coordinates || [];

        // Simple A-to-B plane → caller will render as deck.gl arc
        if (transportType === 'plane' && coords.length === 2) {
            var arcColor = isFuture
                ? [107, 107, 107, 150]
                : MapConfig.hexToRgba(cfg.color, 180);
            return { source: coords[0], target: coords[1], color: arcColor };
        }

        // Add as MapLibre line layer
        if (!map.getSource(sourceId)) {
            map.addSource(sourceId, { type: 'geojson', data: geojson });
        }

        if (!map.getLayer(layerId)) {
            var color      = isFuture ? '#6B6B6B' : cfg.color;
            var lineWidth  = isFuture ? 3 : 4;
            var paint      = {
                'line-color':   color,
                'line-width':   lineWidth,
                'line-opacity': 0.7
            };

            if (isFuture) {
                paint['line-dasharray'] = [2, 6];
            } else if (cfg.dashArray) {
                paint['line-dasharray'] = cfg.dashArray;
            }

            map.addLayer({
                id:     layerId,
                type:   'line',
                source: sourceId,
                layout: {
                    'line-join': 'round',
                    'line-cap':  'round',
                    'visibility': visibility
                },
                paint: paint,
                metadata: { transportType: transportType }
            });
        }

        return null; // layer was added, not an arc
    }

    /**
     * Creates or updates the deck.gl ArcLayer overlay for flight arcs.
     *
     * @param {maplibregl.Map} map
     * @param {Array}  flightData      - Array of { source, target, color } entries.
     * @param {object} [existingOverlay] - Previous MapboxOverlay to update instead of creating a new one.
     * @returns {object} The deck.gl MapboxOverlay (new or updated).
     */
    function renderFlightArcs(map, flightData, existingOverlay) {
        if (typeof deck === 'undefined') {
            console.warn('map-renderer: deck.gl not loaded, skipping flight arcs');
            return existingOverlay || null;
        }

        var arcLayer = new deck.ArcLayer({
            id:                  'flight-arcs',
            data:                flightData,
            getSourcePosition:   function (d) { return d.source; },
            getTargetPosition:   function (d) { return d.target; },
            getSourceColor:      function (d) { return d.color; },
            getTargetColor:      function (d) { return d.color; },
            getWidth:            2,
            getHeight:           0.3,
            greatCircle:         true,
            pickable:            true,
            numSegments:         25,
            updateTriggers:      { getSourcePosition: flightData.length, getTargetPosition: flightData.length }
        });

        if (existingOverlay) {
            existingOverlay.setProps({ layers: [arcLayer] });
            return existingOverlay;
        }

        var overlay = new deck.MapboxOverlay({ interleaved: true, layers: [arcLayer] });
        map.addControl(overlay);
        return overlay;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    return {
        createClusterMarkerEl: createClusterMarkerEl,
        createPointMarkerEl:   createPointMarkerEl,
        addRouteLayer:         addRouteLayer,
        renderFlightArcs:      renderFlightArcs
    };
}());
