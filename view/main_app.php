<?php
// Helpers para 'recordar' el formulario
$base_table = $form_data['base_table'] ?? null;
$verify_checked = $form_data['verify_tables'] ?? [];
$categories_checked = $form_data['categories_to_split'] ?? [];
$fuse_tables_data = $form_data['fuse_table_name'] ?? [];
$fuse_cols_data = $form_data['fuse_table_cols'] ?? [];
?>

<div class="container app-container">
    <header class="app-header">
        <h1>Herramienta de Cruce de Datos</h1>
        <a href="index.php?route=auth/logout" class="btn btn-logout">Cerrar Sesión (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
    </header>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="index.php?route=main/selectBaseTable" method="POST" class="form-card" id="form-base-table">
        <h3>Paso 1: Seleccionar Tabla Base</h3>
        <p>Elige la tabla principal. Esto recargará la página para mostrar las categorías disponibles de esa tabla.</p>
        <div class="form-group">
            <label for="base_table">Tabla Base:</label>
            <select name="base_table" id="base_table" onchange="document.getElementById('form-base-table').submit();">
                <option value="">-- Seleccione --</option>
                <?php foreach ($all_tables as $table): ?>
                    <option value="<?php echo htmlspecialchars($table); ?>" <?php echo ($table === $base_table) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($table); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($base_table): // Solo mostrar el resto del formulario si ya se seleccionó una tabla base ?>
    
    <div class="form-card edit-card">
        <strong>Tabla Base Seleccionada: <?php echo htmlspecialchars($base_table); ?></strong>
        <a href="index.php?route=tableEdit/index&table=<?php echo htmlspecialchars($base_table); ?>" class="btn btn-edit">✏️ Editar esta tabla</a>
    </div>


    <form action="index.php?route=main/process" method="POST" class="form-card">
        <input type="hidden" name="base_table" value="<?php echo htmlspecialchars($base_table); ?>">

        <h3>Paso 2: Opciones de Cruce (Opcional)</h3>

        <fieldset>
            <legend>A. Tablas de Verificación (Relación Sí/No)</legend>
            <p>Marque las tablas que desea cruzar. Se creará una columna 'en_tabla' con 'Sí' o 'No' si existe la relación por `entidad_ok` y `municipio_ok`.</p>
            <div class="checkbox-grid">
                <?php foreach ($all_tables as $table): ?>
                    <?php if ($table === $base_table) continue; // No compararse consigo misma ?>
                    <div class="checkbox-item">
                        <input type="checkbox" 
                               name="verify_tables[]" 
                               value="<?php echo htmlspecialchars($table); ?>" 
                               id="verify_<?php echo htmlspecialchars($table); ?>"
                               <?php echo in_array($table, $verify_checked) ? 'checked' : ''; ?>>
                        <label for="verify_<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- Botón para mostrar opciones adicionales -->
        <div class="additional-options-toggle">
            <button type="button" id="btn-show-additional-options" class="btn btn-secondary">
                ➕ Opciones Adicionales
            </button>
        </div>

        <!-- Sección B: Ocultada inicialmente -->
        <fieldset id="section-b-fusion" style="display: none;">
            <legend>B. Fusión de Columnas (Merge)</legend>
            <p>Seleccione una tabla del punto A y elija las columnas que desea fusionar (máximo 3 columnas por tabla). Puede agregar hasta 5 tablas para fusionar.</p>
            <div id="fuse-tables-container">
                <p class="info-message" id="fuse-no-tables-message">⚠️ Primero debe seleccionar al menos una tabla en el punto A para poder fusionar columnas.</p>
                <?php for ($i = 0; $i < 5; $i++): // Creamos 5 "slots" para fusionar ?>
                <div class="fuse-group" data-slot-index="<?php echo $i; ?>" style="display: <?php echo $i === 0 ? 'block' : 'none'; ?>;">
                    <div class="fuse-table-selector">
                        <label>Tabla <?php echo ($i + 1); ?>:</label>
                        <select name="fuse_table_name[]" class="fuse-table-select" data-slot="<?php echo $i; ?>">
                            <option value="">-- Seleccione Tabla --</option>
                        </select>
                    </div>
                    <div class="fuse-columns-selector" id="fuse-columns-<?php echo $i; ?>" style="display: none;">
                        <label>Seleccione columnas (máximo 3):</label>
                        <div class="columns-checkbox-group" id="columns-checkbox-<?php echo $i; ?>">
                            <p class="loading-message">Cargando columnas...</p>
                        </div>
                        <small class="columns-selected-count" id="count-<?php echo $i; ?>" style="display: none;">0 de 3 columnas seleccionadas</small>
                    </div>
                </div>
                <?php endfor; ?>
                <div class="add-table-container" id="add-table-container" style="display: none;">
                    <button type="button" id="btn-add-table" class="btn btn-secondary">
                        ➕ Agregar otra tabla
                    </button>
                </div>
            </div>
        </fieldset>
        
        <fieldset>
            <legend>C. Separar por Columna (Opcional)</legend>
            <?php if (empty($base_table)): ?>
                <p>Primero debe seleccionar una tabla base.</p>
            <?php else: ?>
                <p>Seleccione una columna de la tabla base y elija los valores que desea convertir en columnas 'Sí'/'No'.</p>
                <div class="form-group">
                    <label for="split_column">Columna a separar:</label>
                    <select name="split_column" id="split_column" class="split-column-select">
                        <option value="">-- Seleccione Columna --</option>
                        <?php foreach (($available_columns ?? []) as $col): ?>
                            <option value="<?php echo htmlspecialchars($col); ?>" 
                                    <?php echo ($col === ($form_data['split_column'] ?? '')) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($col); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="split-values-container" style="display: none;">
                    <label>Valores a convertir en columnas 'Sí'/'No':</label>
                    <div class="checkbox-grid" id="split-values-checkboxes">
                        <p class="loading-message">Cargando valores...</p>
                    </div>
                </div>
            <?php endif; ?>
        </fieldset>

        <button type="submit" class="btn btn-primary">📊 Generar Cruce</button>
    </form>
    
    
    <?php if (isset($results) && !empty($results['data'])): ?>
    <div class="results-card">

        <div class="results-header">
            <h3>Resultados del Cruce (<?php echo count($results['data']); ?> filas)</h3>
            <div class="results-header-buttons">
                <button type="button" id="btn-consolidate" class="btn btn-consolidate">🔗 Consolidar Filas Duplicadas</button>
                <a href="index.php?route=map/index" class="btn btn-map">🌍 Generar Mapa con estos Datos</a>
            </div>
        </div>
        
        <!-- Controles de paginación -->
        <div class="pagination-controls">
            <div class="pagination-options">
                <label>Filas por página:</label>
                <select id="rows-per-page" class="pagination-select">
                    <option value="20">20</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="all">Todas</option>
                </select>
            </div>
            <div class="pagination-info">
                <span id="pagination-info">Mostrando 1-50 de <?php echo count($results['data']); ?></span>
            </div>
            <div class="pagination-buttons">
                <button type="button" id="btn-prev" class="btn btn-pagination" disabled>◀ Anterior</button>
                <button type="button" id="btn-next" class="btn btn-pagination">Siguiente ▶</button>
            </div>
        </div>
        
        <div class="table-container" id="results-table-container">
            <table id="results-table">
                <thead>
                    <tr>
                        <?php foreach ($results['headers'] as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="results-table-body">
                    <?php foreach ($results['data'] as $row): ?>
                    <tr>
                        <?php foreach ($results['headers'] as $header): ?>
                            <?php
                            $value = $row[$header] ?? '';
                            $class = '';
                            if (strtoupper($value) === 'SÍ') {
                                $class = 'cell-si'; // Clase CSS para 'Sí'
                            } elseif (strtoupper($value) === 'NO') {
                                $class = 'cell-no'; // Clase CSS para 'No'
                            }
                            ?>
                            <td class="<?php echo $class; ?>"><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Script para paginación -->
        <script>
        (function() {
            const tableBody = document.getElementById('results-table-body');
            const rowsPerPageSelect = document.getElementById('rows-per-page');
            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const paginationInfo = document.getElementById('pagination-info');
            
            const allRows = Array.from(tableBody.querySelectorAll('tr'));
            const totalRows = allRows.length;
            let currentPage = 1;
            let rowsPerPage = 50;
            
            // Función para actualizar la tabla
            function updateTable() {
                // Ocultar todas las filas
                allRows.forEach(row => {
                    row.style.display = 'none';
                });
                
                // Calcular índices
                const startIndex = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
                const endIndex = rowsPerPage === 'all' ? totalRows : startIndex + rowsPerPage;
                
                // Mostrar filas de la página actual
                for (let i = startIndex; i < endIndex && i < totalRows; i++) {
                    allRows[i].style.display = '';
                }
                
                // Actualizar información de paginación
                if (rowsPerPage === 'all') {
                    paginationInfo.textContent = `Mostrando todas las ${totalRows} filas`;
                } else {
                    const start = startIndex + 1;
                    const end = Math.min(endIndex, totalRows);
                    paginationInfo.textContent = `Mostrando ${start}-${end} de ${totalRows}`;
                }
                
                // Actualizar botones
                btnPrev.disabled = (currentPage === 1);
                const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);
                btnNext.disabled = (currentPage >= totalPages);
            }
            
            // Event listeners
            rowsPerPageSelect.addEventListener('change', function() {
                rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
                currentPage = 1;
                updateTable();
            });
            
            btnPrev.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    updateTable();
                }
            });
            
            btnNext.addEventListener('click', function() {
                const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    updateTable();
                }
            });
            
            // Inicializar
            updateTable();
        })();
        
        // Script para consolidar filas duplicadas
        (function() {
            const btnConsolidate = document.getElementById('btn-consolidate');
            const resultsHeader = document.querySelector('.results-header h3');
            const paginationInfo = document.getElementById('pagination-info');
            
            if (btnConsolidate) {
                btnConsolidate.addEventListener('click', function() {
                    if (!confirm('¿Está seguro de que desea consolidar las filas duplicadas? Esto agrupará las filas con la misma entidad_ok y municipio_ok.')) {
                        return;
                    }
                    
                    btnConsolidate.disabled = true;
                    btnConsolidate.textContent = 'Consolidando...';
                    
                    fetch('index.php?route=main/consolidateRows', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error: ' + data.error);
                            btnConsolidate.disabled = false;
                            btnConsolidate.textContent = '🔗 Consolidar Filas Duplicadas';
                            return;
                        }
                        
                        if (data.success) {
                            // Actualizar la tabla con los datos consolidados
                            const tableBody = document.getElementById('results-table-body');
                            const tableHead = document.querySelector('#results-table thead tr');
                            
                            // Actualizar cabeceras
                            tableHead.innerHTML = '';
                            data.headers.forEach(function(header) {
                                const th = document.createElement('th');
                                th.textContent = header;
                                tableHead.appendChild(th);
                            });
                            
                            // Actualizar filas
                            tableBody.innerHTML = '';
                            data.data.forEach(function(row) {
                                const tr = document.createElement('tr');
                                data.headers.forEach(function(header) {
                                    const td = document.createElement('td');
                                    const value = row[header] ?? '';
                                    td.textContent = value;
                                    
                                    // Aplicar clases CSS
                                    const upperValue = value.toString().toUpperCase();
                                    if (upperValue === 'SÍ' || upperValue === 'SI') {
                                        td.className = 'cell-si';
                                    } else if (upperValue === 'NO') {
                                        td.className = 'cell-no';
                                    }
                                    
                                    tr.appendChild(td);
                                });
                                tableBody.appendChild(tr);
                            });
                            
                            // Actualizar información
                            resultsHeader.textContent = 'Resultados del Cruce (' + data.consolidated_count + ' filas)';
                            paginationInfo.textContent = 'Mostrando 1-' + Math.min(50, data.consolidated_count) + ' de ' + data.consolidated_count;
                            
                            // Recargar paginación
                            const allRows = Array.from(tableBody.querySelectorAll('tr'));
                            const totalRows = allRows.length;
                            let currentPage = 1;
                            let rowsPerPage = 50;
                            
                            function updateTable() {
                                allRows.forEach(row => row.style.display = 'none');
                                const startIndex = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
                                const endIndex = rowsPerPage === 'all' ? totalRows : startIndex + rowsPerPage;
                                for (let i = startIndex; i < endIndex && i < totalRows; i++) {
                                    allRows[i].style.display = '';
                                }
                                if (rowsPerPage === 'all') {
                                    paginationInfo.textContent = 'Mostrando todas las ' + totalRows + ' filas';
                                } else {
                                    const start = startIndex + 1;
                                    const end = Math.min(endIndex, totalRows);
                                    paginationInfo.textContent = 'Mostrando ' + start + '-' + end + ' de ' + totalRows;
                                }
                                document.getElementById('btn-prev').disabled = (currentPage === 1);
                                const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);
                                document.getElementById('btn-next').disabled = (currentPage >= totalPages);
                            }
                            
                            updateTable();
                            
                            alert('Consolidación completada. Se redujeron de ' + data.original_count + ' a ' + data.consolidated_count + ' filas.');
                            btnConsolidate.disabled = false;
                            btnConsolidate.textContent = '🔗 Consolidar Filas Duplicadas';
                        }
                    })
                    .catch(error => {
                        alert('Error al consolidar: ' + error);
                        btnConsolidate.disabled = false;
                        btnConsolidate.textContent = '🔗 Consolidar Filas Duplicadas';
                    });
                });
            }
        })();
        </script>
    </div>
    <?php elseif (isset($results)): ?>
        <div class="form-card">
             <h3>Resultados del Cruce</h3>
             <p>No se encontraron datos para la tabla base seleccionada.</p>
        </div>
    <?php endif; ?>
    

    <?php endif; // Fin del if ($base_table) ?>
    
</div>

<script>
// Script para manejar opciones adicionales y cargar columnas dinámicamente
(function() {
    const MAX_COLUMNS_PER_TABLE = 3;
    const MAX_TABLES = 5;
    
    // Datos guardados de PHP (columnas pre-seleccionadas)
    const savedColumns = <?php echo json_encode($fuse_cols_data ?? []); ?>;
    const savedTableNames = <?php echo json_encode($fuse_tables_data ?? []); ?>;
    
    // Referencias a elementos
    const btnShowAdditional = document.getElementById('btn-show-additional-options');
    const sectionB = document.getElementById('section-b-fusion');
    const fuseNoTablesMessage = document.getElementById('fuse-no-tables-message');
    
    // Función para obtener las tablas seleccionadas en el punto A
    function getSelectedTablesFromA() {
        const checkboxes = document.querySelectorAll('input[name="verify_tables[]"]:checked');
        const selectedTables = [];
        checkboxes.forEach(function(checkbox) {
            selectedTables.push(checkbox.value);
        });
        return selectedTables;
    }
    
    // Función para obtener las tablas ya seleccionadas en los slots de fusión
    function getSelectedFuseTables() {
        const selectedFuseTables = [];
        document.querySelectorAll('.fuse-table-select').forEach(function(select) {
            if (select.value && select.closest('.fuse-group').style.display !== 'none') {
                selectedFuseTables.push(select.value);
            }
        });
        return selectedFuseTables;
    }
    
    // Función para contar cuántos slots están visibles
    function getVisibleSlotsCount() {
        let count = 0;
        document.querySelectorAll('.fuse-group').forEach(function(group) {
            if (group.style.display !== 'none') {
                count++;
            }
        });
        return count;
    }
    
    // Función para actualizar los dropdowns de tablas en la sección B
    function updateFuseTableDropdowns() {
        const selectedTables = getSelectedTablesFromA();
        const fuseGroups = document.querySelectorAll('.fuse-group');
        const selectedFuseTables = getSelectedFuseTables();
        const addTableContainer = document.getElementById('add-table-container');
        const visibleSlots = getVisibleSlotsCount();
        
        if (selectedTables.length === 0) {
            fuseNoTablesMessage.style.display = 'block';
            fuseGroups.forEach(function(group) {
                group.style.display = 'none';
            });
            if (addTableContainer) addTableContainer.style.display = 'none';
            return;
        }
        
        fuseNoTablesMessage.style.display = 'none';
        
        // Mostrar/ocultar botón de agregar tabla
        if (addTableContainer) {
            if (visibleSlots < 5 && selectedTables.length > visibleSlots) {
                addTableContainer.style.display = 'block';
            } else {
                addTableContainer.style.display = 'none';
            }
        }
        
        // Actualizar cada dropdown visible
        fuseGroups.forEach(function(group, index) {
            const select = group.querySelector('.fuse-table-select');
            if (!select) return;
            
            // Solo actualizar si el grupo está visible
            if (group.style.display === 'none' && index > 0) return;
            
            const currentValue = select.value;
            
            // Limpiar opciones existentes excepto la primera
            select.innerHTML = '<option value="">-- Seleccione Tabla --</option>';
            
            // Agregar las tablas seleccionadas en A, excluyendo las ya seleccionadas en otros slots
            selectedTables.forEach(function(table) {
                // Excluir si ya está seleccionada en otro slot (excepto el actual)
                if (selectedFuseTables.includes(table) && table !== currentValue) {
                    return;
                }
                
                const option = document.createElement('option');
                option.value = table;
                option.textContent = table;
                
                // Marcar como seleccionada si es la actual o estaba guardada
                if (currentValue === table || savedTableNames[index] === table) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            });
            
            // Si había una tabla guardada, cargar sus columnas
            if (savedTableNames[index] && savedTableNames[index] === currentValue) {
                loadTableColumns(savedTableNames[index], index, savedColumns[index] || []);
            }
        });
    }
    
    // Función para cargar columnas de una tabla
    function loadTableColumns(tableName, slotIndex, preselectedColumns = []) {
        if (!tableName) {
            document.getElementById('fuse-columns-' + slotIndex).style.display = 'none';
            return;
        }
        
        const columnsContainer = document.getElementById('columns-checkbox-' + slotIndex);
        const countElement = document.getElementById('count-' + slotIndex);
        const columnsSelector = document.getElementById('fuse-columns-' + slotIndex);
        
        columnsContainer.innerHTML = '<p class="loading-message">Cargando columnas...</p>';
        columnsSelector.style.display = 'block';
        countElement.style.display = 'none';
        
        // Hacer petición AJAX
        fetch('index.php?route=main/getTableColumns&table=' + encodeURIComponent(tableName))
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    columnsContainer.innerHTML = '<p class="error-message">Error: ' + data.error + '</p>';
                    return;
                }
                
                if (data.columns.length === 0) {
                    columnsContainer.innerHTML = '<p class="info-message">Esta tabla no tiene columnas adicionales disponibles.</p>';
                    return;
                }
                
                // Crear checkboxes para cada columna
                let html = '<div class="checkbox-grid">';
                data.columns.forEach(function(column) {
                    const checkboxId = 'fuse-col-' + slotIndex + '-' + column.replace(/[^a-zA-Z0-9]/g, '_');
                    const isChecked = preselectedColumns.includes(column);
                    html += '<div class="checkbox-item">';
                    html += '<input type="checkbox" ';
                    html += 'name="fuse_table_cols[' + slotIndex + '][]" ';
                    html += 'value="' + escapeHtml(column) + '" ';
                    html += 'id="' + checkboxId + '" ';
                    html += 'class="fuse-column-checkbox" ';
                    html += 'data-slot="' + slotIndex + '" ';
                    html += (isChecked ? 'checked ' : '');
                    html += 'onchange="updateColumnCount(' + slotIndex + ')">';
                    html += '<label for="' + checkboxId + '">' + escapeHtml(column) + '</label>';
                    html += '</div>';
                });
                html += '</div>';
                
                columnsContainer.innerHTML = html;
                updateColumnCount(slotIndex);
            })
            .catch(error => {
                columnsContainer.innerHTML = '<p class="error-message">Error al cargar columnas: ' + error + '</p>';
            });
    }
    
    // Función para actualizar el contador de columnas seleccionadas
    window.updateColumnCount = function(slotIndex) {
        const checkboxes = document.querySelectorAll('.fuse-column-checkbox[data-slot="' + slotIndex + '"]:checked');
        const count = checkboxes.length;
        const countElement = document.getElementById('count-' + slotIndex);
        
        if (count > 0) {
            countElement.style.display = 'block';
            countElement.textContent = count + ' de ' + MAX_COLUMNS_PER_TABLE + ' columnas seleccionadas';
            
            if (count > MAX_COLUMNS_PER_TABLE) {
                countElement.style.color = 'red';
                countElement.textContent = '⚠️ Máximo ' + MAX_COLUMNS_PER_TABLE + ' columnas permitidas. Desmarque algunas.';
                // Desmarcar las que exceden el límite
                Array.from(checkboxes).slice(MAX_COLUMNS_PER_TABLE).forEach(cb => {
                    cb.checked = false;
                });
                updateColumnCount(slotIndex); // Recursivo para actualizar el contador
            } else {
                countElement.style.color = '';
            }
        } else {
            countElement.style.display = 'none';
        }
        
        // Deshabilitar checkboxes si ya se alcanzó el límite
        const allCheckboxes = document.querySelectorAll('.fuse-column-checkbox[data-slot="' + slotIndex + '"]');
        allCheckboxes.forEach(cb => {
            if (!cb.checked && count >= MAX_COLUMNS_PER_TABLE) {
                cb.disabled = true;
            } else {
                cb.disabled = false;
            }
        });
    };
    
    // Función helper para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Función para delegar eventos (para elementos creados dinámicamente)
    function setupFuseTableSelectListeners() {
        document.querySelectorAll('.fuse-table-select').forEach(function(select) {
            const slotIndex = select.getAttribute('data-slot');
            
            // Remover listeners anteriores si existen
            const newSelect = select.cloneNode(true);
            select.parentNode.replaceChild(newSelect, select);
            
            // Agregar listener para cambios
            newSelect.addEventListener('change', function() {
                loadTableColumns(this.value, slotIndex, []);
                // Actualizar todos los dropdowns para excluir la tabla seleccionada
                updateFuseTableDropdowns();
            });
        });
    }
    
    // Función para agregar una nueva tabla
    function addNewTableSlot() {
        const fuseGroups = document.querySelectorAll('.fuse-group');
        const visibleSlots = getVisibleSlotsCount();
        
        if (visibleSlots >= 5) {
            alert('Máximo 5 tablas permitidas');
            return;
        }
        
        // Encontrar el primer slot oculto y mostrarlo
        for (let i = 0; i < fuseGroups.length; i++) {
            if (fuseGroups[i].style.display === 'none') {
                fuseGroups[i].style.display = 'block';
                updateFuseTableDropdowns();
                setupFuseTableSelectListeners();
                break;
            }
        }
    }
    
    // Mostrar/ocultar sección B al hacer clic en el botón
    if (btnShowAdditional) {
        btnShowAdditional.addEventListener('click', function() {
            if (sectionB.style.display === 'none') {
                sectionB.style.display = 'block';
                this.textContent = '➖ Ocultar Opciones Adicionales';
                updateFuseTableDropdowns();
                setupFuseTableSelectListeners();
            } else {
                sectionB.style.display = 'none';
                this.textContent = '➕ Opciones Adicionales';
            }
        });
    }
    
    // Escuchar cambios en los checkboxes del punto A
    document.querySelectorAll('input[name="verify_tables[]"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // Si la sección B está visible, actualizar los dropdowns
            if (sectionB && sectionB.style.display !== 'none') {
                updateFuseTableDropdowns();
                setupFuseTableSelectListeners();
            }
        });
    });
    
    // Botón para agregar otra tabla
    const btnAddTable = document.getElementById('btn-add-table');
    if (btnAddTable) {
        btnAddTable.addEventListener('click', function() {
            addNewTableSlot();
        });
    }
    
    // Si hay datos guardados, mostrar la sección B automáticamente
    if (savedTableNames.length > 0 && savedTableNames.some(t => t !== '')) {
        if (btnShowAdditional && sectionB) {
            sectionB.style.display = 'block';
            btnShowAdditional.textContent = '➖ Ocultar Opciones Adicionales';
            // Esperar un momento para que el DOM esté listo
            setTimeout(function() {
                // Mostrar los slots que tenían datos guardados
                savedTableNames.forEach(function(tableName, index) {
                    if (tableName) {
                        const fuseGroup = document.querySelector('.fuse-group[data-slot-index="' + index + '"]');
                        if (fuseGroup) {
                            fuseGroup.style.display = 'block';
                        }
                    }
                });
                updateFuseTableDropdowns();
                setupFuseTableSelectListeners();
            }, 100);
        }
    } else {
        // Inicializar con solo el primer slot visible
        updateFuseTableDropdowns();
        setupFuseTableSelectListeners();
    }
})();

// Script para manejar la sección C (Separar por Columna)
(function() {
    const splitColumnSelect = document.getElementById('split_column');
    const splitValuesContainer = document.getElementById('split-values-container');
    const splitValuesCheckboxes = document.getElementById('split-values-checkboxes');
    const baseTable = '<?php echo htmlspecialchars($base_table ?? ''); ?>';
    const savedSplitColumn = '<?php echo htmlspecialchars($form_data['split_column'] ?? ''); ?>';
    const savedCategories = <?php echo json_encode($categories_checked ?? []); ?>;
    
    // Función para cargar valores únicos de una columna
    function loadColumnValues(columnName) {
        if (!columnName || !baseTable) {
            splitValuesContainer.style.display = 'none';
            return;
        }
        
        splitValuesCheckboxes.innerHTML = '<p class="loading-message">Cargando valores...</p>';
        splitValuesContainer.style.display = 'block';
        
        // Hacer petición AJAX
        fetch('index.php?route=main/getColumnValues&table=' + encodeURIComponent(baseTable) + '&column=' + encodeURIComponent(columnName))
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    splitValuesCheckboxes.innerHTML = '<p class="error-message">Error: ' + data.error + '</p>';
                    return;
                }
                
                if (data.values.length === 0) {
                    splitValuesCheckboxes.innerHTML = '<p class="info-message">Esta columna no tiene valores disponibles.</p>';
                    return;
                }
                
                // Crear checkboxes para cada valor
                let html = '';
                data.values.forEach(function(value) {
                    const checkboxId = 'split-val-' + value.toString().replace(/[^a-zA-Z0-9]/g, '_');
                    const isChecked = savedCategories.includes(value);
                    html += '<div class="checkbox-item">';
                    html += '<input type="checkbox" ';
                    html += 'name="categories_to_split[]" ';
                    html += 'value="' + escapeHtml(value) + '" ';
                    html += 'id="' + checkboxId + '" ';
                    html += (isChecked ? 'checked ' : '');
                    html += '>';
                    html += '<label for="' + checkboxId + '">' + escapeHtml(value) + '</label>';
                    html += '</div>';
                });
                
                splitValuesCheckboxes.innerHTML = html;
            })
            .catch(error => {
                splitValuesCheckboxes.innerHTML = '<p class="error-message">Error al cargar valores: ' + error + '</p>';
            });
    }
    
    // Función helper para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Event listener para el selector de columna
    if (splitColumnSelect) {
        // Cargar valores si ya hay una columna seleccionada
        if (savedSplitColumn) {
            loadColumnValues(savedSplitColumn);
        }
        
        splitColumnSelect.addEventListener('change', function() {
            loadColumnValues(this.value);
        });
    }
})();
</script>

<?php if (isset($duplicateInfo)): // Solo se ejecuta si la variable existe ?>
<script>
    (function() {
        // Convertir los datos PHP a un objeto JavaScript
        const duplicates = <?php echo json_encode($duplicateInfo); ?>;
        
        console.log("--- 🕵️ REPORTE DE DUPLICADOS EN TABLAS DE CRUCE ---");
        
        if (duplicates.length > 0) {
            console.warn(`Se encontraron ${duplicates.length} grupos de duplicados:`);

            // Agrupar por tabla para una mejor lectura en la consola
            const groupedByTable = {};
            duplicates.forEach(item => {
                if (!groupedByTable[item.tabla]) {
                    groupedByTable[item.tabla] = [];
                }
                groupedByTable[item.tabla].push(item);
            });

            // Imprimir en la consola usando console.table() para que se vea bien
            for (const tableName in groupedByTable) {
                console.log(`\nTabla: %c${tableName}`, 'font-weight: bold; color: #a57f2c;');
                console.table(groupedByTable[tableName], ["entidad", "municipio", "repeticiones"]);
            }
            
        } else {
            console.log("✅ No se encontraron duplicados en las tablas de verificación seleccionadas.");
        }
        
        console.log("--- FIN DEL REPORTE ---");
    })();
</script>
<?php endif; ?>