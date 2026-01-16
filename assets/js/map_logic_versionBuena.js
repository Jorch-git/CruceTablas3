// --- INICIALIZACIÓN DEL MAPA ---
const map = L.map('map').setView([23.6345, -102.5528], 5);

// *** Paneles simplificados ***
// Panel para Polígonos (DEBAJO)
map.createPane('paneForShapes');
map.getPane('paneForShapes').style.zIndex = 390;

// Panel para Círculos Clickeables (ENCIMA DE TODO)
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
    })
};
layers.carto.addTo(map);

function changeLayer(type) {
    Object.values(layers).forEach(layer => map.removeLayer(layer));
    layers[type].addTo(map);
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

// *** Capas simplificadas ***
let shapesLayer = L.layerGroup({ pane: 'paneForShapes' }).addTo(map);  // Polígonos
let markersLayer = L.layerGroup({ pane: 'paneForMarkers' }).addTo(map); // Círculos
let datosParaExportar = [];

// Paleta de colores (tus 3 colores)
const colorPalette = [
    '#a57f2c',
    '#98989A',
    '#13322e'
];


/**
 * 1. Se ejecuta al cargar la página
 */
document.addEventListener('DOMContentLoaded', inicializarControles);

/**
 * 2. Configurar los listeners y poblar los checkboxes
 */
function inicializarControles() {
    popularCheckboxes(CRITERIA_LIST);
    btnActualizar.addEventListener('click', actualizarMapa);
    btnExportar.addEventListener('click', exportarAExcel);
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
    // Limpiar capas
    markersLayer.clearLayers();
    shapesLayer.clearLayers();
    
    // *** NUEVO: Limpiar la leyenda y prepararla ***
    const legendDiv = document.getElementById('map-legend');
    legendDiv.innerHTML = '<ol id="legend-list"></ol>'; // Crear la lista ordenada
    const legendList = document.getElementById('legend-list');

    // --- 1. Leer todos los filtros ---
    const entidadSeleccionada = selectEntidad.value;
    const criteriosChecked = Array.from(divCriterios.querySelectorAll('input[type="checkbox"]:checked'))
                                        .map(cb => cb.value);
    const filtroConteo = document.getElementById('filtro-conteo-minimo').value;

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

            let popupContent = `<b>${item[keyMunicipio]}, ${item[keyEntidad]}</b>`;
            popupContent += `<hr><b>Criterios ('Sí') Cumplidos: ${count}</b><br>`;
            popupContent += metCriteria.join('<br>');

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer); 
            
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

    // --- 7. TERCER BARRIDO: Dibujar SHAPES y poblar LEYENDA ---
    
    // *** NUEVO: Contador para los identificadores de la leyenda ***
    let identifierIndex = 1;
    let lastColorIndex = -1;

    entidadesConMunicipios.forEach((municipiosSet, entidad) => {
        
        const nombreArchivo = entidad.replace(/ /g, '_').toUpperCase();
        const url = `assets/js/entidades/${nombreArchivo}.json`;
        
        let colorIndex;
        do {
            colorIndex = Math.floor(Math.random() * colorPalette.length); 
        } while (colorIndex === lastColorIndex); 
        
        lastColorIndex = colorIndex; 
        const color = colorPalette[colorIndex];

        // Estilo para municipios FILTRADOS (resaltados, SIN borde interno)
        const highlightStyle = {
            color: color,
            weight: 0,
            opacity: 0,
            fillColor: color,
            fillOpacity: 0.7, // Relleno SÓLIDO
            pane: 'paneForShapes'
        };
        
        // Estilo para municipios NO filtrados (INVISIBLES)
        const defaultStyle = {
            weight: 0,
            opacity: 0,
            fillOpacity: 0,
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

        // Cargar el archivo GeoJSON
        fetch(url)
            .then(response => {
                if (!response.ok) { throw new Error(`Error HTTP ${response.status} - No se encontró: ${url}`); }
                return response.text(); 
            })
            .then(text => {
                if (text.trim() === "") { throw new Error(`JSON vacío: ${url}`); }

                let geojson;
                try {
                    geojson = JSON.parse(text); 
                } catch (e) {
                    console.error(`El archivo ${url} no es un JSON válido.`, e);
                    throw new Error(`El archivo ${url} contiene JSON inválido.`);
                }

                // Dibuja los municipios (solo los filtrados serán visibles)
                const shapeLayer = L.geoJson(geojson, { 
                    style: styleFunction,
                    pane: 'paneForShapes'
                });
                
                // ==========================================================
                // *** MODIFICACIÓN: Poblar la leyenda y añadir ID al mapa ***
                // ==========================================================

                // 1. Obtener el ID actual
                const currentId = identifierIndex++;

                // 2. Crear el contenido para la leyenda
                const municipiosArray = Array.from(municipiosSet);
                const li = document.createElement('li');
                // Restauramos el contenido original del tooltip aquí
                li.innerHTML = `<b>${currentId}. ${entidad}</b><br><small>${municipiosArray.join(', ')}</small>`;
                
                // 3. Añadirlo a la lista en el HTML
                legendList.appendChild(li);

                // 4. Añadir el número (identificador) al centro del polígono
                // Usamos la clase CSS 'map-identifier-label'
                const center = shapeLayer.getBounds().getCenter();
                L.tooltip({
                        permanent: true,
                        direction: 'center',
                        className: 'map-identifier-label', // Clase para el estilo CSS
                        interactive: false // ¡Muy importante para los clics!
                    })
                    .setContent(String(currentId)) // Contenido es solo el número
                    .setLatLng(center)
                    .addTo(map); // Añadirlo directamente al mapa

                // 5. Añadir el polígono (municipios) a su capa
                shapeLayer.addTo(shapesLayer);
            })
            .catch(error => {
                console.warn(error); 
            });
    });
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