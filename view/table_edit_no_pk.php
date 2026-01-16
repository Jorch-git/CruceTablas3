<?php
// Vista para tablas sin PK - mostrar opciones para agregar PK
?>

<div class="container app-container">
    <header class="app-header">
        <h1>Editor de Tabla: <?php echo htmlspecialchars($table_name); ?></h1>
        <a href="index.php?route=main/index" class="btn btn-logout">← Volver</a>
    </header>

    <div class="form-card">
        <h2>⚠️ Tabla sin Clave Primaria</h2>
        <p>La tabla <strong><?php echo htmlspecialchars($table_name); ?></strong> no tiene una Clave Primaria (PK) definida.</p>
        <p>Para poder editar la tabla de forma segura, necesitas agregar una PK. Tienes las siguientes opciones:</p>

        <div style="margin: 20px 0;">
            <h3>Opción 1: Crear nueva columna 'id' como PK</h3>
            <p>Esta opción agregará una nueva columna 'id' autoincremental al inicio de la tabla.</p>
            <button type="button" id="btn-add-id" class="btn btn-primary">Agregar columna 'id' como PK</button>
        </div>

        <div style="margin: 20px 0;">
            <h3>Opción 2: Usar columna existente como PK</h3>
            <p>Si ya tienes una columna única, puedes convertirla en PK.</p>
            <div class="form-group">
                <label>Seleccionar columna:</label>
                <select id="existing-column" class="form-select">
                    <option value="">-- Seleccione una columna --</option>
                    <?php foreach ($columns as $col): ?>
                        <option value="<?php echo htmlspecialchars($col); ?>"><?php echo htmlspecialchars($col); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" id="btn-use-existing" class="btn btn-secondary" disabled>Usar esta columna como PK</button>
        </div>

        <div style="margin: 20px 0;">
            <h3>Opción 3: Usar script SQL manual</h3>
            <p>Puedes ejecutar el script SQL manualmente usando el archivo <code>add_primary_key.php</code></p>
            <a href="add_primary_key.php?table=<?php echo urlencode($table_name); ?>" class="btn btn-secondary" target="_blank">Abrir script SQL</a>
        </div>

        <div id="status-message" style="margin-top: 20px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnAddId = document.getElementById('btn-add-id');
    const btnUseExisting = document.getElementById('btn-use-existing');
    const selectColumn = document.getElementById('existing-column');
    const statusMsg = document.getElementById('status-message');
    const tableName = '<?php echo htmlspecialchars($table_name); ?>';

    // Habilitar botón cuando se selecciona una columna
    selectColumn.addEventListener('change', function() {
        btnUseExisting.disabled = !this.value;
    });

    // Agregar nueva columna 'id' como PK
    btnAddId.addEventListener('click', function() {
        if (!confirm('¿Está seguro de agregar una nueva columna "id" como Clave Primaria? Esto modificará la estructura de la tabla.')) {
            return;
        }

        btnAddId.disabled = true;
        btnAddId.textContent = 'Procesando...';

        fetch('index.php?route=tableEdit/addPrimaryKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'table=' + encodeURIComponent(tableName) + '&type=new'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusMsg.innerHTML = '<div class="error-message" style="background-color: #d4edda; color: #155724;">✓ ' + data.message + '</div>';
                setTimeout(function() {
                    window.location.href = 'index.php?route=tableEdit/index&table=' + encodeURIComponent(tableName);
                }, 2000);
            } else {
                statusMsg.innerHTML = '<div class="error-message">✗ Error: ' + data.error + '</div>';
                btnAddId.disabled = false;
                btnAddId.textContent = 'Agregar columna \'id\' como PK';
            }
        })
        .catch(error => {
            statusMsg.innerHTML = '<div class="error-message">✗ Error: ' + error + '</div>';
            btnAddId.disabled = false;
            btnAddId.textContent = 'Agregar columna \'id\' como PK';
        });
    });

    // Usar columna existente como PK
    btnUseExisting.addEventListener('click', function() {
        const columnName = selectColumn.value;
        if (!columnName) return;

        if (!confirm('¿Está seguro de convertir la columna "' + columnName + '" en Clave Primaria? Esto modificará la estructura de la tabla.')) {
            return;
        }

        btnUseExisting.disabled = true;
        btnUseExisting.textContent = 'Procesando...';

        fetch('index.php?route=tableEdit/addPrimaryKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'table=' + encodeURIComponent(tableName) + '&type=existing&column=' + encodeURIComponent(columnName)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusMsg.innerHTML = '<div class="error-message" style="background-color: #d4edda; color: #155724;">✓ ' + data.message + '</div>';
                setTimeout(function() {
                    window.location.href = 'index.php?route=tableEdit/index&table=' + encodeURIComponent(tableName);
                }, 2000);
            } else {
                statusMsg.innerHTML = '<div class="error-message">✗ Error: ' + data.error + '</div>';
                btnUseExisting.disabled = false;
                btnUseExisting.textContent = 'Usar esta columna como PK';
            }
        })
        .catch(error => {
            statusMsg.innerHTML = '<div class="error-message">✗ Error: ' + error + '</div>';
            btnUseExisting.disabled = false;
            btnUseExisting.textContent = 'Usar esta columna como PK';
        });
    });
});
</script>

<style>
.form-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-bottom: 10px;
}
</style>

