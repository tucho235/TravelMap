/**
 * Trip Map Editor - Leaflet Integration
 * 
 * Gestiona el mapa interactivo para dibujar rutas de viaje
 */

(function () {
    'use strict';

    // Variables globales
    let map;
    let drawnItems;
    let drawControl;
    let pointMarkers = L.layerGroup();
    let routesData = [];

    // Colores por tipo de transporte
    const transportColors = {
        'plane': '#FF4444',
        'car': '#4444FF',
        'train': '#FF8800',
        'ship': '#00AAAA',
        'walk': '#44FF44'
    };

    /**
     * Inicializa el mapa
     */
    function initMap() {
        // Crear el mapa centrado en el mundo
        map = L.map('map').setView([20, 0], 2);

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Grupo para las rutas dibujadas
        drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // Grupo para los marcadores de puntos
        map.addLayer(pointMarkers);

        // Configurar controles de dibujo
        initDrawControls();

        // Cargar rutas existentes
        loadExistingRoutes();

        // Cargar puntos existentes
        loadExistingPoints();

        // Ajustar vista si hay contenido
        adjustMapView();
    }

    /**
     * Inicializa los controles de dibujo
     */
    function initDrawControls() {
        drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                polyline: {
                    shapeOptions: {
                        color: '#4444FF',
                        weight: 4,
                        opacity: 0.8
                    },
                    showLength: true,
                    metric: true,
                    feet: false
                },
                polygon: false,
                circle: false,
                circlemarker: false,
                marker: false,
                rectangle: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

        map.addControl(drawControl);

        // Evento al crear una nueva ruta
        map.on(L.Draw.Event.CREATED, handleRouteCreated);

        // Evento al editar rutas
        map.on(L.Draw.Event.EDITED, handleRoutesEdited);

        // Evento al eliminar rutas
        map.on(L.Draw.Event.DELETED, handleRoutesDeleted);
    }

    /**
     * Maneja la creación de una nueva ruta
     */
    function handleRouteCreated(e) {
        const layer = e.layer;

        // Pedir tipo de transporte
        const transportType = promptTransportType();

        if (!transportType) {
            return; // Usuario canceló
        }

        // Asignar color según el transporte
        const color = transportColors[transportType];
        layer.setStyle({
            color: color,
            weight: 4,
            opacity: 0.8
        });

        // Agregar la capa al grupo
        drawnItems.addLayer(layer);

        // Guardar metadata en la capa
        layer.transportType = transportType;
        layer.color = color;

        // Actualizar datos
        updateRoutesData();

        console.log('Ruta creada:', transportType, layer.toGeoJSON());
    }

    /**
     * Maneja la edición de rutas
     */
    function handleRoutesEdited(e) {
        updateRoutesData();
        console.log('Rutas editadas');
    }

    /**
     * Maneja la eliminación de rutas
     */
    function handleRoutesDeleted(e) {
        updateRoutesData();
        console.log('Rutas eliminadas');
    }

    /**
     * Pide al usuario el tipo de transporte
     */
    function promptTransportType() {
        const options = Object.keys(transportTypes);
        let message = 'Selecciona el tipo de transporte:\n\n';

        options.forEach((key, index) => {
            message += `${index + 1}. ${transportTypes[key]}\n`;
        });

        message += '\nIngresa el número (1-' + options.length + '):';

        const input = prompt(message);

        if (!input) {
            return null;
        }

        const index = parseInt(input) - 1;

        if (index >= 0 && index < options.length) {
            return options[index];
        }

        alert('Opción no válida. Seleccionando Auto por defecto.');
        return 'car';
    }

    /**
     * Actualiza el array de datos de rutas
     */
    function updateRoutesData() {
        routesData = [];

        drawnItems.eachLayer(function (layer) {
            const geojson = layer.toGeoJSON();

            routesData.push({
                transport_type: layer.transportType || 'car',
                color: layer.color || transportColors['car'],
                geojson: geojson
            });
        });

        // Actualizar input hidden
        document.getElementById('routes_data').value = JSON.stringify(routesData);

        console.log('Rutas actualizadas:', routesData.length);
    }

    /**
     * Carga las rutas existentes desde el servidor
     */
    function loadExistingRoutes() {
        if (!existingRoutes || existingRoutes.length === 0) {
            console.log('No hay rutas existentes');
            return;
        }

        existingRoutes.forEach(function (route) {
            const geojson = route.geojson;
            const layer = L.geoJSON(geojson, {
                style: {
                    color: route.color,
                    weight: 4,
                    opacity: 0.8
                }
            });

            // Agregar metadata
            layer.eachLayer(function (l) {
                l.transportType = route.transport_type;
                l.color = route.color;
                drawnItems.addLayer(l);
            });
        });

        // Actualizar datos
        updateRoutesData();

        console.log('Rutas cargadas:', existingRoutes.length);
    }

    /**
     * Carga los puntos de interés existentes
     */
    function loadExistingPoints() {
        if (!existingPoints || existingPoints.length === 0) {
            console.log('No hay puntos existentes');
            return;
        }

        existingPoints.forEach(function (point) {
            // Icono personalizado
            const icon = L.divIcon({
                className: 'custom-poi-marker',
                html: `<div style="background-color: #FF4444; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-weight: bold;">📍</div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            const marker = L.marker([point.latitude, point.longitude], {
                icon: icon,
                title: point.title
            });

            marker.bindPopup(`
                <strong>${point.title}</strong><br>
                <small class="text-muted">${point.type}</small><br>
                <small>Lat: ${point.latitude}, Lng: ${point.longitude}</small>
            `);

            pointMarkers.addLayer(marker);
        });

        console.log('Puntos cargados:', existingPoints.length);
    }

    /**
     * Ajusta la vista del mapa para mostrar todo el contenido
     */
    function adjustMapView() {
        const allLayers = [];

        // Agregar rutas
        drawnItems.eachLayer(function (layer) {
            allLayers.push(layer);
        });

        // Agregar puntos
        pointMarkers.eachLayer(function (layer) {
            allLayers.push(layer);
        });

        if (allLayers.length > 0) {
            const group = new L.featureGroup(allLayers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    /**
     * Limpia todas las rutas
     */
    function clearAllRoutes() {
        if (!confirm('¿Estás seguro de que quieres eliminar todas las rutas?')) {
            return;
        }

        drawnItems.clearLayers();
        updateRoutesData();
    }

    /**
     * Maneja el envío del formulario
     */
    function handleFormSubmit(e) {
        // Actualizar datos antes de enviar
        updateRoutesData();

        if (routesData.length === 0) {
            if (!confirm('No has dibujado ninguna ruta. ¿Deseas continuar y eliminar todas las rutas existentes?')) {
                e.preventDefault();
                return false;
            }
        }

        return true;
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Inicializar mapa
        initMap();

        // Evento para limpiar todas las rutas
        $('#clearAllRoutes').on('click', clearAllRoutes);

        // Evento para el formulario
        $('#routesForm').on('submit', handleFormSubmit);

        console.log('Trip Map Editor inicializado');
    });

})();
