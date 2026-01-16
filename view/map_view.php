<div class="container app-container">
    <header class="app-header">
        <h1>Mapa Interactivo</h1>
        <div>
            <a href="index.php?route=main/index" class="btn">Volver al Cruce</a>
            <a href="index.php?route=auth/logout" class="btn btn-logout">Cerrar Sesión (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
        </div>
    </header>

    <div class="filters">
        <div>
            <label for="tipo-mapa-display"><strong>1. Tipo de Mapa (Tabla Base):</strong></label>
            <input type="text" id="tipo-mapa-display" value="<?php echo htmlspecialchars($base_table_name); ?>" disabled style="background:#eee;">
        </div>

        <strong>2. Filtros de Visualización:</strong>
        <div style="margin-top: 10px;">
            <label for="filtro-entidad">3. Filtrar por Entidad:</label>
            <select id="filtro-entidad">
                <option value="Todas">-- Mostrar Todas las Entidades --</option>
                <?php foreach ($entidades as $entidad): ?>
                <option value="<?php echo htmlspecialchars($entidad); ?>"><?php echo htmlspecialchars($entidad); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="margin-top: 10px;">
            <label for="filtro-conteo-minimo">4. Filtrar por conteo:</label>
            <select id="filtro-conteo-minimo">
                <option value="min_1">Mostrar todos (Al menos 1)</option>
                <option value="min_2">Al menos 2 criterios</option>
                <option value="min_3">Al menos 3 criterios</option>
                <option value="min_4">Al menos 4 criterios</option>
                <option value="top_4">Top 4 Tiers (Max, Max-1, Max-2, Max-3)</option>
                <option value="top_3">Top 3 Tiers (Max, Max-1, Max-2)</option>
                <option value="top_2">Top 2 Tiers (Max, Max-1)</option>
                <option value="max_only">Solo el Máximo</option>
            </select>
        </div>
        
        <div style="margin-top: 10px; padding: 5px; background: #f9f9f9; border-radius: 4px;">
            <input type="checkbox" id="filtro-colorear-conteo" checked>
            <label for="filtro-colorear-conteo"><strong>Colorear fondo de estado por # de coincidencias (1, 2, 3+)</strong></label>
            <small style="display: block; margin-left: 20px;">(Si se desactiva, todos los estados con coincidencias usarán un solo color)</small>
        </div>
        <div style="margin-top: 15px;">
            <label>5. Criterios (Columnas del cruce anterior):</label>
            <div id="filtro-criterios">
                </div>
        </div>
        
        <div>
            <button id="btn-actualizar" class="btn btn-primary">Actualizar/Visualizar Mapa</button>
            <button id="btn-exportar" class="btn">Descargar a Excel (CSV)</button>
        </div>
    </div>

    <div class="map-controls-section">
        <strong>Controles del Mapa:</strong>
        <div class="map-controls-buttons">
            <button id="btn-toggle-legend" class="btn btn-map-control" title="Mostrar/Ocultar Leyenda">
                📋 Mostrar/Ocultar Leyenda
            </button>
            <button id="btn-toggle-clean-map" class="btn btn-map-control" title="Cambiar a Mapa Limpio">
                🗺️ Mapa Limpio (Sin Nombres)
            </button>
            <button id="btn-maximize-map" class="btn btn-map-control" title="Maximizar Mapa">
                ⛶ Maximizar Mapa
            </button>
            <button id="btn-restore-map" class="btn btn-map-control" style="display: none;" title="Restaurar Mapa">
                ⛶ Restaurar Mapa
            </button>
            <button id="btn-toggle-solid-background" class="btn btn-map-control" title="Alternar Fondo Sólido por Entidad">
                🎨 Fondo Sólido por Entidad
            </button>
            <button id="btn-show-all-popups" class="btn btn-map-control" title="Mostrar/Ocultar Todos los Popups">
                📌 Mostrar Todos los Popups
            </button>
        </div>
    </div>

    <div id="map-container">
        <div id="map"></div>
        <div id="map-legend"></div> 
    </div>

    <div id="table-container">
        </div>

</div>

<script>
    // Pasamos los datos del controlador (PHP) a variables globales de JS
    
    // 1. Datos principales del cruce, enriquecidos con coordenadas
    const ALL_MAP_DATA = <?php echo $map_data_json; ?>;
    
    // 2. Lista de columnas que se consideran "criterios" (para los checkboxes)
    const CRITERIA_LIST = <?php echo $criteria_list_json; ?>;

    // 3. Datos de conteo de duplicados (para colorear el fondo del estado)
    //    Esta variable es leída por map_logic.js
    const DUPLICATE_INFO = <?php echo $duplicate_info_json; ?>;
</script>


<script src="assets/js/map_logic.js"></script>