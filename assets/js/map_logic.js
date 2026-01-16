// --- INICIALIZACIÓN DEL MAPA ---
const map = L.map('map').setView([23.6345, -102.5528], 5);

// *** 1. CREACIÓN DE PANELES (NUEVO ORDEN) ***
// Panel para Contorno (FONDO)
map.createPane('paneForContorno');
map.getPane('paneForContorno').style.zIndex = 380;

// Panel para Municipios Resaltados (MEDIO)
map.createPane('paneForMunicipalities');
map.getPane('paneForMunicipalities').style.zIndex = 390;

// Panel para las Etiquetas Numéricas (SOBRE LOS POLÍGONOS)
map.createPane('paneForNumericLabels');
map.getPane('paneForNumericLabels').style.zIndex = 660; // Encima de polígonos, debajo de círculos

// Panel para Círculos Clickeables (ENCIMA)
map.createPane('paneForMarkers');
map.getPane('paneForMarkers').style.zIndex = 675;


// Capas base
const layers = {
    carto: L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: 'CartoDB'
    }),
    esri: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Esri'
    }),
    osmfr: L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
        attribution: 'OpenStreetMap France'
    }),
    // Mapa limpio sin nombres de localidades (CartoDB Positron)
    positron: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: 'CartoDB Positron'
    })
};
layers.carto.addTo(map);

// Variable para rastrear el tipo de mapa actual
let currentMapType = 'carto';
let isCleanMap = false;

function changeLayer(type) {
    Object.values(layers).forEach(layer => map.removeLayer(layer));
    layers[type].addTo(map);
    currentMapType = type;
}

// Función para normalizar nombres (quitar acentos)
function normalizeString(str) {
    if (!str) return "";
    return str
        .toUpperCase() // Convertir a mayúsculas
        .normalize("NFD") // Descomponer acentos (ej. "é" -> "e" + "´")
        .replace(/[\u0300-\u036f]/g, ""); // Eliminar los caracteres de acento
}

// --- LÓGICA DE FILTROS Y MAPA ---

// Variables globales
const selectEntidad = document.getElementById('filtro-entidad');
const divCriterios = document.getElementById('filtro-criterios');
const btnActualizar = document.getElementById('btn-actualizar');
const btnExportar = document.getElementById('btn-exportar');
const chkColorearPorConteo = document.getElementById('filtro-colorear-conteo');
const btnToggleLegend = document.getElementById('btn-toggle-legend');
const btnToggleCleanMap = document.getElementById('btn-toggle-clean-map');
const btnMaximizeMap = document.getElementById('btn-maximize-map');
const btnRestoreMap = document.getElementById('btn-restore-map');
const btnToggleSolidBackground = document.getElementById('btn-toggle-solid-background');
const btnShowAllPopups = document.getElementById('btn-show-all-popups');
const mapLegend = document.getElementById('map-legend');
const mapContainer = document.getElementById('map-container');
const mapElement = document.getElementById('map');

// Variables para almacenar el estado original del mapa
let originalMapHeight = null;
let originalMapContainerStyle = null;
let isMapMaximized = false;

// Variable para el modo de fondo sólido
let isSolidBackgroundMode = false;

// Variable para almacenar todos los marcadores y controlar popups
let allMarkers = [];
let areAllPopupsOpen = false;
let allTooltips = []; // Almacenar tooltips permanentes

// Lista de todas las entidades de México (para modo sólido)
const ALL_ENTITIES = [
    'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas',
    'Chihuahua', 'Ciudad de México', 'Coahuila de Zaragoza', 'Colima', 'Durango',
    'Guanajuato', 'Guerrero', 'Hidalgo', 'Jalisco', 'México', 'Michoacán de Ocampo',
    'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo',
    'San Luis Potosí', 'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala',
    'Veracruz de Ignacio de la Llave', 'Yucatán', 'Zacatecas'
];


// *** ASIGNACIÓN DE CAPAS A SUS PANELES ***
let contornoLayer = L.layerGroup({ pane: 'paneForContorno' }).addTo(map);      // Capa de contornos (FONDO)
let municipalitiesLayer = L.layerGroup({ pane: 'paneForMunicipalities' }).addTo(map); // Capa de municipios (MEDIO)
let markersLayer = L.layerGroup({ pane: 'paneForMarkers' }).addTo(map);      // Capa de círculos (ENCIMA)
let identifierLabelsLayer = L.layerGroup({ pane: 'paneForNumericLabels' }).addTo(map); // Capa para los números (EN MEDIO)
let datosParaExportar = [];

// Paleta de colores para los NIVELES de duplicados
const colorConfig = {
    colorCount1: '#a57f2c',     // Color para 1 coincidencia
    colorCount2: '#98989A',     // Color para 2 coincidencias
    colorCount3Plus: '#13322e', // Color para 3 o más
    colorDefault: '#E0E0E0'     // Color si no hay coincidencias en el cruce
};


/**
 * 1. Se ejecuta al cargar la página
 */
document.addEventListener('DOMContentLoaded', inicializarControles);

/**
 * 2. Configurar los listeners y poblar los checkboxes
 */
function inicializarControles() {
    popularCheckboxes(CRITERIA_LIST);
    
    if (btnActualizar) {
        btnActualizar.addEventListener('click', actualizarMapa);
    }
    
    if (btnExportar) {
        btnExportar.addEventListener('click', exportarAExcel);
    }
    
    // Configurar botón para mostrar/ocultar leyenda
    if (btnToggleLegend) {
        btnToggleLegend.addEventListener('click', toggleLegend);
        // Asegurar que la leyenda esté visible por defecto
        if (mapLegend) {
            mapLegend.style.display = 'block';
        }
    }
    
    // Configurar botón para cambiar a mapa limpio
    if (btnToggleCleanMap) {
        btnToggleCleanMap.addEventListener('click', toggleCleanMap);
    }
    
    // Configurar botón para maximizar mapa
    if (btnMaximizeMap) {
        btnMaximizeMap.addEventListener('click', maximizeMap);
    }
    
    // Configurar botón para restaurar mapa
    if (btnRestoreMap) {
        btnRestoreMap.addEventListener('click', restoreMap);
    }
    
    // Configurar botón para alternar fondo sólido
    if (btnToggleSolidBackground) {
        btnToggleSolidBackground.addEventListener('click', toggleSolidBackground);
    }
    
    // Configurar botón para mostrar/ocultar todos los popups
    if (btnShowAllPopups) {
        btnShowAllPopups.addEventListener('click', toggleAllPopups);
    }
}

/**
 * Función para mostrar/ocultar la leyenda
 */
function toggleLegend() {
    if (mapLegend) {
        const isVisible = mapLegend.style.display !== 'none';
        mapLegend.style.display = isVisible ? 'none' : 'block';
        btnToggleLegend.textContent = isVisible ? '📋 Mostrar Leyenda' : '📋 Ocultar Leyenda';
    }
}

/**
 * Función para cambiar entre mapa normal y mapa limpio
 */
function toggleCleanMap() {
    isCleanMap = !isCleanMap;
    
    if (isCleanMap) {
        // Cambiar a mapa limpio (Positron)
        changeLayer('positron');
        btnToggleCleanMap.textContent = '🗺️ Mapa Normal (Con Nombres)';
        btnToggleCleanMap.title = 'Volver a Mapa Normal';
    } else {
        // Volver al mapa normal (CartoDB Voyager)
        changeLayer('carto');
        btnToggleCleanMap.textContent = '🗺️ Mapa Limpio (Sin Nombres)';
        btnToggleCleanMap.title = 'Cambiar a Mapa Limpio';
    }
}

/**
 * Función para maximizar el mapa
 */
function maximizeMap() {
    if (!mapContainer || !mapElement) return;
    
    // Guardar el estado original si es la primera vez
    if (!isMapMaximized) {
        originalMapHeight = mapElement.style.height || window.getComputedStyle(mapElement).height;
        originalMapContainerStyle = {
            position: mapContainer.style.position || '',
            top: mapContainer.style.top || '',
            left: mapContainer.style.left || '',
            right: mapContainer.style.right || '',
            bottom: mapContainer.style.bottom || '',
            zIndex: mapContainer.style.zIndex || '',
            width: mapContainer.style.width || '',
            height: mapContainer.style.height || ''
        };
    }
    
    // Maximizar el mapa
    mapContainer.style.position = 'fixed';
    mapContainer.style.top = '0';
    mapContainer.style.left = '0';
    mapContainer.style.right = '0';
    mapContainer.style.bottom = '0';
    mapContainer.style.zIndex = '9999';
    mapContainer.style.width = '100%';
    mapContainer.style.height = '100vh';
    
    mapElement.style.height = '100vh';
    
    // Ocultar botón maximizar y mostrar botón restaurar
    if (btnMaximizeMap) btnMaximizeMap.style.display = 'none';
    if (btnRestoreMap) btnRestoreMap.style.display = 'inline-block';
    
    isMapMaximized = true;
    
    // Ajustar el tamaño del mapa de Leaflet
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 100);
}

/**
 * Función para restaurar el mapa a su tamaño original
 */
function restoreMap() {
    if (!mapContainer || !mapElement || !isMapMaximized) return;
    
    // Restaurar estilos originales
    if (originalMapContainerStyle) {
        mapContainer.style.position = originalMapContainerStyle.position;
        mapContainer.style.top = originalMapContainerStyle.top;
        mapContainer.style.left = originalMapContainerStyle.left;
        mapContainer.style.right = originalMapContainerStyle.right;
        mapContainer.style.bottom = originalMapContainerStyle.bottom;
        mapContainer.style.zIndex = originalMapContainerStyle.zIndex;
        mapContainer.style.width = originalMapContainerStyle.width;
        mapContainer.style.height = originalMapContainerStyle.height;
    } else {
        // Si no hay estilos guardados, usar valores por defecto
        mapContainer.style.position = '';
        mapContainer.style.top = '';
        mapContainer.style.left = '';
        mapContainer.style.right = '';
        mapContainer.style.bottom = '';
        mapContainer.style.zIndex = '';
        mapContainer.style.width = '';
        mapContainer.style.height = '';
    }
    
    // Restaurar altura del mapa
    if (originalMapHeight) {
        mapElement.style.height = originalMapHeight;
    } else {
        mapElement.style.height = '1000px'; // Valor por defecto del CSS
    }
    
    // Mostrar botón maximizar y ocultar botón restaurar
    if (btnMaximizeMap) btnMaximizeMap.style.display = 'inline-block';
    if (btnRestoreMap) btnRestoreMap.style.display = 'none';
    
    isMapMaximized = false;
    
    // Ajustar el tamaño del mapa de Leaflet
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 100);
}

/**
 * Función para alternar el modo de fondo sólido por entidad
 */
function toggleSolidBackground() {
    isSolidBackgroundMode = !isSolidBackgroundMode;
    
    if (btnToggleSolidBackground) {
        if (isSolidBackgroundMode) {
            btnToggleSolidBackground.textContent = '🎨 Fondo Normal';
            btnToggleSolidBackground.title = 'Volver a Fondo Normal';
        } else {
            btnToggleSolidBackground.textContent = '🎨 Fondo Sólido por Entidad';
            btnToggleSolidBackground.title = 'Alternar Fondo Sólido por Entidad';
        }
    }
    
    // Si hay datos cargados, actualizar el mapa
    if (typeof actualizarMapa === 'function') {
        actualizarMapa();
    }
}

/**
 * Función para mostrar/ocultar todos los popups de los pines
 */
function toggleAllPopups() {
    if (allMarkers.length === 0) {
        alert("No hay pines en el mapa. Por favor, actualiza el mapa primero.");
        return;
    }
    
    areAllPopupsOpen = !areAllPopupsOpen;
    
    if (areAllPopupsOpen) {
        // Cerrar cualquier popup abierto
        map.closePopup();
        
        // Crear tooltips permanentes para todos los marcadores
        allMarkers.forEach(marker => {
            const popup = marker.getPopup();
            if (popup) {
                const popupContent = popup.getContent();
                const latlng = marker.getLatLng();
                
                // Crear tooltip permanente con el contenido del popup
                const tooltip = L.tooltip({
                    permanent: true,
                    direction: 'top',
                    className: 'permanent-popup-tooltip',
                    interactive: false,
                    offset: [0, -10]
                })
                .setContent(popupContent)
                .setLatLng(latlng)
                .addTo(map);
                
                allTooltips.push(tooltip);
            }
        });
        
        if (btnShowAllPopups) {
            btnShowAllPopups.textContent = '📌 Ocultar Todos los Popups';
            btnShowAllPopups.title = 'Ocultar Todos los Popups';
        }
    } else {
        // Remover todos los tooltips permanentes
        allTooltips.forEach(tooltip => {
            map.removeLayer(tooltip);
        });
        allTooltips = [];
        
        // Cerrar cualquier popup abierto
        map.closePopup();
        
        if (btnShowAllPopups) {
            btnShowAllPopups.textContent = '📌 Mostrar Todos los Popups';
            btnShowAllPopups.title = 'Mostrar Todos los Popups';
        }
    }
}
  
/**
 * 3. Repuebla los checkboxes de Criterios
 */
function popularCheckboxes(criteriaArray) {
    divCriterios.innerHTML = '';
    
    criteriaArray.forEach(criterio => {
        const label = document.createElement('label');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = criterio;
        checkbox.id = `check-${criterio}`;
        
        label.appendChild(checkbox);
        const labelText = criterio.replace(/_/g, ' '); 
        label.appendChild(document.createTextNode(labelText));
        divCriterios.appendChild(label);
    });
}

/**
 * 4. Función principal: Dibuja el mapa Y LA TABLA
 */
function actualizarMapa() {
    // Limpiar todas las capas
    markersLayer.clearLayers();
    municipalitiesLayer.clearLayers();
    contornoLayer.clearLayers(); 
    identifierLabelsLayer.clearLayers(); // <-- Limpiar la capa de números
    
    // Resetear estado de popups
    allMarkers = [];
    areAllPopupsOpen = false;
    // Remover todos los tooltips si existen
    allTooltips.forEach(tooltip => {
        map.removeLayer(tooltip);
    });
    allTooltips = [];
    if (btnShowAllPopups) {
        btnShowAllPopups.textContent = '📌 Mostrar Todos los Popups';
        btnShowAllPopups.title = 'Mostrar Todos los Popups';
    }

    // --- LÍNEAS DE PRUEBA Y CORRECCIÓN ---
    console.log("--- Iniciando actualizarMapa() ---");
    const legendDiv = document.getElementById('map-legend');
    
    // Verificación de seguridad
    if (!legendDiv) {
        console.error("¡ERROR! No se encontró el elemento <div id='map-legend'>. Asegúrate de que esté en tu HTML.");
        alert("Error de script: No se encontró el contenedor <div id='map-legend'>. Revisa el HTML.");
        return; // Detiene la ejecución
    }
    
    console.log("Elemento #map-legend encontrado:", legendDiv);
    // --- FIN DE LÍNEAS DE PRUEBA ---
    
    // Limpiar la leyenda y prepararla
    legendDiv.innerHTML = '<ol id="legend-list"></ol>'; // Crear la lista ordenada
    const legendList = document.getElementById('legend-list');

    // --- 1. Leer todos los filtros ---
    const entidadSeleccionada = selectEntidad.value;
    const criteriosChecked = Array.from(divCriterios.querySelectorAll('input[type="checkbox"]:checked'))
                                        .map(cb => cb.value);
    const filtroConteo = document.getElementById('filtro-conteo-minimo').value;
    const isColoreadoPorConteo = chkColorearPorConteo.checked;


    if (criteriosChecked.length === 0) {
        alert("Por favor, selecciona al menos un criterio para visualizar.");
        legendDiv.innerHTML = ''; // Ocultar leyenda si no hay nada
        return;
    }
    if (!ALL_MAP_DATA || ALL_MAP_DATA.length === 0) {
        alert("No hay datos para mostrar (posiblemente ninguno tenía coordenadas).");
        return;
    }

    // --- 2. Claves de acceso ---
    const keyEntidad = 'entidad_ok';
    const keyMunicipio = 'municipio_ok';

    // =================================================================
    // *** 4. Pre-procesar los duplicados (YA ESTÁ DENTRO) ***
    // =================================================================
    const maxRepetitionMap = new Map();
    
    // Filtrar los nombres de las tablas de cruce seleccionadas
    const checkedTableNames = criteriosChecked
        .map(c => c.startsWith('en_') ? c.substring(3) : null)
        .filter(c => c !== null); // Quitar cualquier criterio que no sea 'en_...'

    // DUPLICATE_INFO es la variable global creada en map_view.php
    if (typeof DUPLICATE_INFO !== 'undefined' && Array.isArray(DUPLICATE_INFO)) {
        
        // Filtramos la data de duplicados para incluir SOLO las tablas seleccionadas
        const filteredDuplicates = DUPLICATE_INFO.filter(item => 
            checkedTableNames.includes(item.tabla)
        );

        // Ahora calculamos el max, pero solo de la data filtrada
        filteredDuplicates.forEach(item => {
            const entidadNorm = normalizeString(item.entidad);
            const currentMax = maxRepetitionMap.get(entidadNorm) || 0;
            
            if (item.repeticiones > currentMax) {
                maxRepetitionMap.set(entidadNorm, item.repeticiones);
            }
        });
    }
    console.log("Tablas de cruce activas:", checkedTableNames);
    console.log("Mapa de máximas repeticiones (dinámico):", maxRepetitionMap);
    // =================================================================


    // --- 3. PRIMER BARRIDO: Calcular conteos y encontrar el máximo ---
    let maxCriterios = 0;
    const datosFiltrados = []; 

    ALL_MAP_DATA.forEach(item => {
        if (entidadSeleccionada !== 'Todas' && item[keyEntidad] !== entidadSeleccionada) {
            return;
        }

        let criteriosCumplidos = 0;
        let metCriteriaNames = [];
        
        criteriosChecked.forEach(criterio => {
            if (item[criterio]) {
                let valor = item[criterio].toString().trim().toLowerCase();
                if (valor === 'si' || valor === 'sí') {
                    criteriosCumplidos++;
                    metCriteriaNames.push(criterio.replace(/_/g, ' '));
                }
            }
        });

        datosFiltrados.push({ 
            item: item, 
            count: criteriosCumplidos,
            metCriteria: metCriteriaNames
        });

        if (criteriosCumplidos > maxCriterios) {
            maxCriterios = criteriosCumplidos;
        }
    });

    // --- 4. Determinar el "piso" de criterios a mostrar ---
    let conteoMinimo = 1; 
    if (filtroConteo.startsWith('min_')) {
        conteoMinimo = parseInt(filtroConteo.split('_')[1], 10);
    } else if (filtroConteo.startsWith('top_')) {
        const topTiers = parseInt(filtroConteo.split('_')[1], 10);
        conteoMinimo = maxCriterios - (topTiers - 1); 
    } else if (filtroConteo === 'max_only') {
        conteoMinimo = maxCriterios;
    }
    if (conteoMinimo <= 0) conteoMinimo = 1;

    // --- 5. SEGUNDO BARRIDO: Dibujar PINES, Llenar TABLA, EXPORT y agrupar ENTIDADES ---
    let pinsDibujados = 0;
    let tableHtml = '<table><thead><tr><th>Entidad</th><th>Municipio</th><th>Conteo</th><th>Criterios Cumplidos</th></tr></thead><tbody>';
    datosParaExportar = [];
    
    const entidadesConMunicipios = new Map();

    datosFiltrados.forEach(data => {
        const { item, count, metCriteria } = data;

        if (count >= conteoMinimo) {
            pinsDibujados++;
            
            // --- A. DIBUJAR PIN (CÍRCULO) EN MAPA ---
            const lat = item.latitud;
            const lng = item.longitud;
            const pinStyle = getPinStyle(count); 

            const marker = L.circleMarker([lat, lng], {
                radius: pinStyle.radius,
                fillColor: pinStyle.fillColor,
                fillOpacity: pinStyle.fillOpacity,
                color: '#000',
                weight: 1,
                opacity: 1,
                pane: 'paneForMarkers' // Al panel superior
            });

            // Popup solo con el nombre del municipio
            let popupContent = `<b>${item[keyMunicipio]}</b>`;

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer);
            
            // Guardar referencia al marcador para control de popups
            allMarkers.push(marker); 
            
            // --- B. AGREGAR A GRUPO DE ENTIDADES ---
            const entidad = item[keyEntidad];
            const municipio = item[keyMunicipio]; 
            
            if (!entidadesConMunicipios.has(entidad)) {
                entidadesConMunicipios.set(entidad, new Set());
            }
            entidadesConMunicipios.get(entidad).add(municipio); 

            // --- C. AÑADIR FILA A LA TABLA HTML ---
            const criteriaListString = metCriteria.join('; ');
            tableHtml += `<tr>
                            <td>${item[keyEntidad]}</td>
                            <td>${item[keyMunicipio]}</td>
                            <td>${count}</td>
                            <td>${criteriaListString}</td>
                        </tr>`;
            
            // --- D. AÑADIR DATOS AL ARRAY DE EXPORTACIÓN ---
            datosParaExportar.push({
                entidad: item[keyEntidad],
                municipio: item[keyMunicipio],
                conteo: count,
                criterios: criteriaListString
            });
        }
    });
    
    // --- 6. Finalizar y mostrar TABLA ---
    tableHtml += '</tbody></table>';
    document.getElementById('table-container').innerHTML = tableHtml;

    if (pinsDibujados === 0) {
        alert("No se encontraron resultados que cumplan con los filtros seleccionados.");
        document.getElementById('table-container').innerHTML = '<p style="padding:10px;">No se encontraron resultados.</p>';
        legendDiv.innerHTML = ''; // Ocultar leyenda
        return;
    }

    // --- 7. TERCER BARRIDO: Dibujar SHAPES (Municipios y Contornos) ---
    let identifierIndex = 1;

    // Si está en modo sólido, determinar qué entidades tienen pines
    const entidadesConPines = new Set();
    if (isSolidBackgroundMode) {
        datosFiltrados.forEach(data => {
            if (data.count >= conteoMinimo) {
                entidadesConPines.add(data.item[keyEntidad]);
            }
        });
    }

    // Si está en modo sólido, cargar TODAS las entidades
    const entidadesACargar = isSolidBackgroundMode ? 
        (() => {
            // Obtener todas las entidades únicas de ALL_MAP_DATA
            const todasEntidades = new Set();
            if (ALL_MAP_DATA && ALL_MAP_DATA.length > 0) {
                ALL_MAP_DATA.forEach(item => {
                    if (item[keyEntidad]) {
                        todasEntidades.add(item[keyEntidad]);
                    }
                });
            }
            // Agregar también las de ALL_ENTITIES para asegurar que todas estén
            ALL_ENTITIES.forEach(ent => todasEntidades.add(ent));
            return Array.from(todasEntidades);
        })() :
        Array.from(entidadesConMunicipios.keys());

    // Función para cargar contorno de una entidad
    function cargarContornoEntidad(entidad, tienePines) {
        const nombreArchivo = entidad.replace(/ /g, '_').toUpperCase();
        const url_contorno = `assets/js/entidades/CONTORNO_${nombreArchivo}.json`;
        
        // Color: gris si no tiene pines, dorado/marrón si tiene pines
        const colorFondo = tienePines ? '#a57f2c' : '#98989A'; // Dorado si tiene pines, gris si no
        
        const contornoStyle = {
            color: '#98989A', // Contorno blanco para contraste
            weight: 2,
            opacity: 1.0,
            fillColor: colorFondo,
            fillOpacity: 1.0, // Opacidad completa (sólido)
            interactive: false,
            pane: 'paneForContorno'
        };
        
        fetch(url_contorno)
            .then(response => {
                if (!response.ok) {
                    return; // Silenciosamente ignorar si no existe
                }
                return response.text();
            })
            .then(text => {
                if (!text || text.trim() === "") {
                    return;
                }
                
                let geojson_contorno;
                try {
                    geojson_contorno = JSON.parse(text);
                } catch (e) {
                    console.warn(`Error parseando contorno de ${entidad}:`, e);
                    return;
                }
                
                L.geoJson(geojson_contorno, { style: contornoStyle }).addTo(contornoLayer);
            })
            .catch(error => {
                // Silenciosamente ignorar errores
            });
    }

    // Si está en modo sólido, cargar todos los contornos
    if (isSolidBackgroundMode) {
        entidadesACargar.forEach(entidad => {
            const tienePines = entidadesConPines.has(entidad);
            cargarContornoEntidad(entidad, tienePines);
        });
    }

    // Cargar municipios y contornos normales (solo si NO está en modo sólido)
    if (!isSolidBackgroundMode) {
        entidadesConMunicipios.forEach((municipiosSet, entidad) => {
        
        const nombreArchivo = entidad.replace(/ /g, '_').toUpperCase();
        const url_municipios = `assets/js/entidades/${nombreArchivo}.json`;
        const url_contorno = `assets/js/entidades/CONTORNO_${nombreArchivo}.json`;
        
        // Asignar color basado en la lógica de conteo
        const entidadNorm = normalizeString(entidad);
        const maxCount = maxRepetitionMap.get(entidadNorm) || 0; // 0 si no hay coincidencias
        
        let color; // Color para el municipio (relleno)
        let contornoFillColor; // Color para el estado (fondo)
        
        if (isColoreadoPorConteo) {
            // --- LÓGICA "ACTIVA" (Colorear por 1, 2, 3+) ---
            if (maxCount === 1) {
                color = colorConfig.colorCount1;
            } else if (maxCount === 2) {
                color = colorConfig.colorCount2;
            } else if (maxCount >= 3) {
                color = colorConfig.colorCount3Plus;
            } else {
                color = colorConfig.colorDefault; 
            }
        } else {
            // --- LÓGICA "INACTIVA" (Estándar de 1) ---
            if (maxCount >= 1) {
                color = colorConfig.colorCount1; // Un solo color si hay CUALQUIER coincidencia
            } else {
                color = colorConfig.colorDefault; // Color por defecto si no hay ninguna
            }
        }
        contornoFillColor = color; // El color del contorno es el que acabamos de calcular


        // Estilo para municipios FILTRADOS (resaltados, con borde gris)
        const highlightStyle = {
            color: '#000000', // Contorno negro
            weight: 3,
            opacity: 1.0,
            fillColor: color, // Color del municipio
            fillOpacity: 0.9, // Relleno SÓLIDO
            pane: 'paneForMunicipalities'
        };
        
        // Estilo para municipios NO filtrados (con borde gris)
        const defaultStyle = {
            color: '#3B3B3B', // Contorno negro  '#3B3B3B'
            weight: 1.5,
            opacity: 1.0,
            fillColor: '#3B3B3B',   // '#3B3B3B'
            fillOpacity: 1, // Relleno difuminado
            interactive: false
        };

        // Función de estilo dinámica
        function styleFunction(feature) {
            const nomgeo = feature.properties.NOMGEO;
            const normalizedNomgeo = normalizeString(nomgeo);
            
            if (municipiosSet.has(normalizedNomgeo)) {
                return highlightStyle;
            } else {
                return defaultStyle;
            }
        }

        // --- Carga de MUNICIPIOS (Capa media) ---
        fetch(url_municipios)
            .then(response => {
                if (!response.ok) { throw new Error(`Error HTTP ${response.status} - No se encontró: ${url_municipios}`); }
                return response.text(); 
            })
            .then(text => {
                if (text.trim() === "") { throw new Error(`JSON vacío: ${url_municipios}`); }

                let geojson;
                try {
                    geojson = JSON.parse(text); 
                } catch (e) {
                    console.error(`El archivo ${url_municipios} no es un JSON válido.`, e);
                    throw new Error(`El archivo ${url_municipios} contiene JSON inválido.`);
                }

                const shapeLayer = L.geoJson(geojson, { 
                    style: styleFunction,
                    pane: 'paneForMunicipalities'
                });
                
                const currentId = identifierIndex++;
                const municipiosArray = Array.from(municipiosSet);
                const li = document.createElement('li');
                
                // ==========================================================
                // *** INICIO DE LA CORRECCIÓN (Problema 1: Doble número) ***
                // ==========================================================
                // Quitamos el `${currentId}.` de aquí
                li.innerHTML = `<b>${entidad}</b><br><small>${municipiosArray.join(', ')}</small>`;
                // ==========================================================
                // *** FIN DE LA CORRECCIÓN ***
                // ==========================================================

                legendList.appendChild(li);

                const center = shapeLayer.getBounds().getCenter();
                L.tooltip({
                        permanent: true,
                        direction: 'center',
                        className: 'map-identifier-label',
                        interactive: false,
                        pane: 'paneForNumericLabels' // Asignar al panel correcto
                    })
                    .setContent(String(currentId))
                    .setLatLng(center)
                    .addTo(identifierLabelsLayer); // <-- Añadir a la capa de etiquetas

                // 5. Añadir el polígono (municipios) a su capa
                shapeLayer.addTo(municipalitiesLayer);
            })
            .catch(error => {
                console.warn(error); 
            });

        // --- Carga del CONTORNO (Capa de fondo) ---
        
        // Estilo para el contorno (usa el color que calculamos)
        const contornoStyle = {
            color: '#444', 
            weight: 2.9,
            opacity: 0.7,
            fillColor: contornoFillColor, // *** Color dinámico basado en duplicados ***
            fillOpacity: 0.05, // CASI invisible
            interactive: false,
            pane: 'paneForContorno' 
        };
        
        console.log(`Buscando contorno: ${url_contorno}`);

        fetch(url_contorno)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Aviso: No se encontró el archivo de contorno: ${url_contorno}`);
                }
                return response.text();
            })
            .then(text => {
                if (text.trim() === "") {
                    throw new Error(`Error: El archivo de contorno JSON está vacío: ${url_contorno}`);
                }
                
                let geojson_contorno;
                try {
                    geojson_contorno = JSON.parse(text);
                } catch (e) {
                    console.error(`El archivo ${url_contorno} no es un JSON válido.`, e);
                    throw new Error(`El archivo ${url_contorno} contiene JSON inválido.`);
                }
                
                console.log(`Cargando contorno para: ${entidad} con color ${contornoFillColor}`);
                L.geoJson(geojson_contorno, { style: contornoStyle }).addTo(contornoLayer);
            })
            .catch(error => {
                console.warn(error.message);
            });
        });
    } // Fin del if (!isSolidBackgroundMode)
}
    
/**
 * 5. FUNCIÓN: Exportar a Excel (CSV)
 */
function exportarAExcel() {
    if (datosParaExportar.length === 0) {
        alert("No hay datos para exportar. Por favor, haz clic en 'Actualizar/Visualizar Mapa' primero.");
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Entidad,Municipio,Conteo,Criterios\n";

    datosParaExportar.forEach(row => {
        let rowString = `"${row.entidad}","${row.municipio}","${row.conteo}","${row.criterios}"`;
        csvContent += rowString + "\n";
    });

    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "exporte_mapa.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * 6. Función para definir el estilo del pin
 */
function getPinStyle(count) {
    let color = '#808080'; // Gris por defecto
    let baseRadius = 6; 

    switch (count) {
        case 1: color = '#9d2449'; break; 
        case 2: color = '#2E7D32'; break; // Verde obscuro
        case 3: color = '#03A9F4'; break; // Azul claro
        case 4: color = '#0D47A1'; break; // Azul obscuro
        case 5: color = '#E65100'; break; // Naranja obscuro
        case 6: color = '#B71C1C'; break; // Rojo obscuro
        case 7: color = '#800080'; break; // Morado
        default:
            if (count >= 8) {
                color = '#EE82EE'; // Violeta
            }
            break;
    }
    let newRadius = baseRadius * Math.pow(1.20, count - 1);
    
    return {
        fillColor: color,
        fillOpacity: 0.9,
        radius: newRadius
    };
}