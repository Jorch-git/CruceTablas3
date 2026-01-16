// --- INICIALIZACIÓN DEL MAPA ---
// (Esto asume que Leaflet ya está cargado por el header.php)
const map = L.map('map').setView([23.6345, -102.5528], 5);

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

// --- LÓGICA DE FILTROS Y MAPA ---

// Variables globales
const selectEntidad = document.getElementById('filtro-entidad');
const divCriterios = document.getElementById('filtro-criterios');
const btnActualizar = document.getElementById('btn-actualizar');
const btnExportar = document.getElementById('btn-exportar');

let markersLayer = L.layerGroup().addTo(map);
let datosParaExportar = [];

// Los datos (ALL_MAP_DATA) y criterios (CRITERIA_LIST) son cargados
// automáticamente desde el <script> inyectado por map_view.php

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
    markersLayer.clearLayers();

    // --- 1. Leer todos los filtros ---
    const entidadSeleccionada = selectEntidad.value;
    const criteriosChecked = Array.from(divCriterios.querySelectorAll('input[type="checkbox"]:checked'))
                                        .map(cb => cb.value);
    const filtroConteo = document.getElementById('filtro-conteo-minimo').value;

    if (criteriosChecked.length === 0) {
        alert("Por favor, selecciona al menos un criterio para visualizar.");
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


    // --- 5. SEGUNDO BARRIDO: Dibujar marcadores Y TABLA ---
    let pinsDibujados = 0;
    let tableHtml = '<table><thead><tr><th>Entidad</th><th>Municipio</th><th>Conteo</th><th>Criterios Cumplidos</th></tr></thead><tbody>';
    datosParaExportar = [];

    datosFiltrados.forEach(data => {
        const { item, count, metCriteria } = data;

        if (count >= conteoMinimo) {
            const lat = item.latitud;
            const lng = item.longitud;
            pinsDibujados++;
            
            const pinStyle = getPinStyle(count); 

            const marker = L.circleMarker([lat, lng], {
                radius: pinStyle.radius,
                fillColor: pinStyle.fillColor,
                fillOpacity: pinStyle.fillOpacity,
                color: '#000',
                weight: 1,
                opacity: 1
            });
            
            // ==================================
            // === MODIFICACIÓN AÑADIDA AQUÍ ===
            // 1. Define el texto para la etiqueta
            const tooltipContent = `${item[keyMunicipio]}, ${item[keyEntidad]}`;
            
            // 2. Asigna la etiqueta permanente (siempre visible)
            marker.bindTooltip(tooltipContent, {
                permanent: true,     // <-- LA CLAVE: Hace que sea siempre visible
                direction: 'top',    // La coloca encima del punto
                // Ajusta la posición para que no tape el círculo (usa el radio del pin)
                offset: [0, -pinStyle.radius - 2], 
                className: 'permanent-tooltip' // Clase CSS para estilizarla
            }).openTooltip();
            // ==================================

            // Generar contenido del popup (esto se queda igual, para el CLIC)
            let popupContent = `<b>${item[keyMunicipio]}, ${item[keyEntidad]}</b>`;
            popupContent += `<hr><b>Criterios ('Sí') Cumplidos: ${count}</b><br>`;
            popupContent += metCriteria.join('<br>');

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer);

            // --- B. AÑADIR FILA A LA TABLA HTML ---
            const criteriaListString = metCriteria.join('; ');
            tableHtml += `<tr>
                            <td>${item[keyEntidad]}</td>
                            <td>${item[keyMunicipio]}</td>
                            <td>${count}</td>
                            <td>${criteriaListString}</td>
                        </tr>`;
            
            // --- C. AÑADIR DATOS AL ARRAY DE EXPORTACIÓN ---
            datosParaExportar.push({
                entidad: item[keyEntidad],
                municipio: item[keyMunicipio],
                conteo: count,
                criterios: criteriaListString
            });
        }
    });
    
    // --- 6. Finalizar y mostrar ---
    tableHtml += '</tbody></table>';
    document.getElementById('table-container').innerHTML = tableHtml;

    if (pinsDibujados === 0) {
        alert("No se encontraron resultados que cumplan con los filtros seleccionados.");
        document.getElementById('table-container').innerHTML = '<p style="padding:10px;">No se encontraron resultados.</p>';
    }
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
    // Hacemos el radio dinámico
    let newRadius = baseRadius * Math.pow(1.20, count - 1);
    
    return {
        fillColor: color,
        fillOpacity: 0.9,
        radius: newRadius
    };
}