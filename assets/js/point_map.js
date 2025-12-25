/**
 * Point Map - Mapa interactivo para seleccionar coordenadas de puntos
 * 
 * Permite seleccionar coordenadas haciendo clic en el mapa o arrastrando el marcador
 */

(function () {
    'use strict';

    let map;
    let marker;
    const defaultLat = 0;
    const defaultLng = 0;
    const defaultZoom = 2;

    /**
     * Inicializa el mapa
     */
    function initMap() {
        // Determinar coordenadas iniciales
        let lat = defaultLat;
        let lng = defaultLng;
        let zoom = defaultZoom;

        if (typeof initialLat !== 'undefined' && initialLat !== null && 
            typeof initialLng !== 'undefined' && initialLng !== null) {
            lat = parseFloat(initialLat);
            lng = parseFloat(initialLng);
            zoom = 13; // Zoom más cercano si ya hay coordenadas
        }

        // Crear el mapa
        map = L.map('pointMap').setView([lat, lng], zoom);

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Si hay coordenadas iniciales, crear marcador
        if (lat !== defaultLat || lng !== defaultLng) {
            addMarker(lat, lng);
        }

        // Evento de clic en el mapa
        map.on('click', onMapClick);

        console.log('Point Map inicializado');
    }

    /**
     * Maneja el clic en el mapa
     */
    function onMapClick(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        // Si ya existe un marcador, removerlo
        if (marker) {
            map.removeLayer(marker);
        }

        // Agregar nuevo marcador
        addMarker(lat, lng);

        // Actualizar campos del formulario
        updateFormFields(lat, lng);
    }

    /**
     * Agrega un marcador arrastrable al mapa
     */
    function addMarker(lat, lng) {
        // Icono personalizado
        const icon = L.divIcon({
            className: 'custom-poi-marker',
            html: `<div style="background-color: #FF4444; color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.4); font-size: 20px; cursor: move;">📍</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        });

        marker = L.marker([lat, lng], {
            icon: icon,
            draggable: true
        }).addTo(map);

        // Popup con coordenadas
        marker.bindPopup(`
            <strong>Ubicación seleccionada</strong><br>
            <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
        `).openPopup();

        // Evento al arrastrar el marcador
        marker.on('dragend', onMarkerDrag);
    }

    /**
     * Maneja el arrastre del marcador
     */
    function onMarkerDrag(e) {
        const position = marker.getLatLng();
        const lat = position.lat;
        const lng = position.lng;

        // Actualizar popup
        marker.setPopupContent(`
            <strong>Ubicación seleccionada</strong><br>
            <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
        `);

        // Actualizar campos del formulario
        updateFormFields(lat, lng);

        console.log('Marcador arrastrado:', lat, lng);
    }

    /**
     * Actualiza los campos de latitud y longitud del formulario
     */
    function updateFormFields(lat, lng) {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        if (latInput && lngInput) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);

            // Remover clases de error si existen
            latInput.classList.remove('is-invalid');
            lngInput.classList.remove('is-invalid');

            // Agregar efecto visual de actualización
            latInput.classList.add('bg-success-subtle');
            lngInput.classList.add('bg-success-subtle');

            setTimeout(() => {
                latInput.classList.remove('bg-success-subtle');
                lngInput.classList.remove('bg-success-subtle');
            }, 500);
        }

        console.log('Coordenadas actualizadas:', lat, lng);
    }

    /**
     * Sincroniza el mapa cuando se cambian manualmente las coordenadas
     */
    function syncMapFromInputs() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        if (!latInput || !lngInput) {
            return;
        }

        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        // Validar que sean números válidos
        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        // Validar rangos
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return;
        }

        // Remover marcador anterior
        if (marker) {
            map.removeLayer(marker);
        }

        // Agregar nuevo marcador
        addMarker(lat, lng);

        // Centrar el mapa
        map.setView([lat, lng], 13);

        console.log('Mapa sincronizado desde inputs:', lat, lng);
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Inicializar mapa
        initMap();

        // Sincronizar mapa cuando cambian los inputs de coordenadas
        $('#latitude, #longitude').on('blur', syncMapFromInputs);

        // También sincronizar al presionar Enter
        $('#latitude, #longitude').on('keypress', function (e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                syncMapFromInputs();
            }
        });

        console.log('Point Map listeners configurados');
    });

})();
