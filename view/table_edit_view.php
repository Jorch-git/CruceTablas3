<link href="https://unpkg.com/tabulator-tables@5.6.1/dist/css/tabulator_modern.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@5.6.1/dist/js/tabulator.min.js"></script>

<div class="container app-container">
    <header class="app-header">
        <h1>Editor de Tabla: <span style="color:#3498db;"><?php echo htmlspecialchars($table_name); ?></span></h1>
        <div>
            <a href="index.php?route=main/index" class="btn">Volver al Cruce</a>
            <a href="index.php?route=auth/logout" class="btn btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <div class="form-card">
        <h3>Instrucciones</h3>
        <ul>
            <li>Haz doble clic en una celda para **Editarla**. Presiona Enter para confirmar.</li>
            <li>Usa los botones de abajo para **Agregar** o **Eliminar** filas.</li>
            <li>Los cambios no son permanentes hasta que presiones **Guardar Cambios**.</li>
            <li>**Restaurar** descartará todos los cambios locales que no hayas guardado.</li>
            <li>La columna de Clave Primaria (PK) **(<?php echo htmlspecialchars($primary_key); ?>)** no se puede editar en filas existentes.</li>
        </ul>

        <div class="table-controls">
            <button id="add-row" class="btn btn-primary">➕ Agregar Fila</button>
            <button id="delete-row" class="btn btn-logout">➖ Eliminar Fila Seleccionada</button>
            <button id="restore-data" class="btn">Restaurar Datos Originales</button>
            <button id="save-changes" class="btn btn-map">💾 Guardar Cambios</button>
        </div>
        <div id="status-message"></div>
        
        <div id="editable-table" style="margin-top:20px;"></div>
    </div>
</div>

<div id="auth-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content form-card">
        <h3>Confirmar Cambios</h3>
        <p>Por seguridad, ingresa tus credenciales para guardar los cambios en la base de datos.</p>
        <div id="auth-error" class="error-message" style="display:none;"></div>
        <div class="form-group">
            <label for="auth-user">Usuario:</label>
            <input type="text" id="auth-user" value="<?php echo htmlspecialchars($_SESSION['username']); // Pre-rellena el usuario ?>">
        </div>
        <div class="form-group">
            <label for="auth-pass">Contraseña:</label>
            <input type="password" id="auth-pass" autocomplete="off">
        </div>
        <button id="auth-confirm" class="btn btn-primary">Confirmar y Guardar</button>
        <button id="auth-cancel" class="btn">Cancelar</button>
    </div>
</div>

<script>
    const TABLE_NAME = "<?php echo htmlspecialchars($table_name); ?>";
    const PRIMARY_KEY = "<?php echo htmlspecialchars($primary_key); ?>";
    
    // Crear la definición de columnas para Tabulator desde el esquema de PHP
    const TABLE_COLUMNS = [
        {formatter:"rowSelection", titleFormatter:"rowSelection", hozAlign:"center", headerSort:false, cellClick:function(e, cell){
            cell.getRow().toggleSelect(); // Permite seleccionar filas
        }},
        <?php foreach ($columns as $col): ?>
        {
            title: "<?php echo htmlspecialchars($col); ?>",
            field: "<?php echo htmlspecialchars($col); ?>",
            editor: "input", // Hacer la celda editable
            headerFilter: "input", // Añadir un filtro en la cabecera
            // Hacer la PK no editable si la fila ya existe (no es nueva)
            editable: function(cell) {
                var data = cell.getRow().getData();
                // La PK solo es editable si la fila es nueva (aún no tiene PK)
                var isNewRow = data[PRIMARY_KEY] === null || data[PRIMARY_KEY] === undefined || data[PRIMARY_KEY] === "";
                return "<?php echo htmlspecialchars($col); ?>" === PRIMARY_KEY ? isNewRow : true;
            }
        },
        <?php endforeach; ?>
    ];
</script>

<script src="assets/js/table_edit.js"></script>