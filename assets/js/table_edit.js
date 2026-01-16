document.addEventListener("DOMContentLoaded", function() {
    if (typeof Tabulator === "undefined") {
        console.error("Tabulator no está cargado. Asegúrate de incluir la librería.");
        return;
    }
    if (!PRIMARY_KEY) {
        document.getElementById("status-message").innerHTML = 
            '<div class="error-message">Error: La tabla no tiene Clave Primaria (PK) y no puede ser editada.</div>';
        return;
    }

    // --- INICIO DE LA MODIFICACIÓN (DEBUGGING) ---
    console.log(`--- Editor de Tabla INICIALIZADO ---
    > Clave Primaria (PK) detectada: ${PRIMARY_KEY}
    > Sigue las trazas en esta consola.`);
    // --- FIN DE LA MODIFICACIÓN (DEBUGGING) ---

    // --- Variables ---
    const changeset = { inserts: [], updates: [], deletes: [] };
    
    // --- Elementos del DOM ---
    const btnAdd = document.getElementById("add-row");
    const btnDelete = document.getElementById("delete-row");
    const btnRestore = document.getElementById("restore-data");
    const btnSave = document.getElementById("save-changes");
    const statusMsg = document.getElementById("status-message");

    // --- Modal de Autenticación ---
    const modal = document.getElementById("auth-modal");
    const btnAuthConfirm = document.getElementById("auth-confirm");
    const btnAuthCancel = document.getElementById("auth-cancel");
    const authError = document.getElementById("auth-error");
    const authPass = document.getElementById("auth-pass");
    const authUser = document.getElementById("auth-user");

    // --- Inicialización de Tabulator ---
    const table = new Tabulator("#editable-table", {
        layout: "fitDataFill", 
        columns: TABLE_COLUMNS,
        movableColumns: true,
        dataLoaded: function(data) {
            console.log(`%cDATOS CARGADOS: ${data.length} filas desde la BD.`, "color: green; font-weight: bold;");
        },
        
        // --- INICIO DE LA MODIFICACIÓN (DEBUGGING) ---
        cellEdited: function(cell) {
            console.group("Evento: cellEdited (Celda Editada)"); // Agrupa los logs

            const rowData = cell.getRow().getData();
            console.log("Datos de la fila que cambió:", rowData);
            
            // Usamos !! (doble negación) para una comprobación flexible (no 0, no null, no "")
            const isExistingRow = !!rowData[PRIMARY_KEY];
            console.log(`¿Es fila existente? (tiene PK): ${isExistingRow}`);

            if (isExistingRow) {
                console.log("... Tipo de cambio: UPDATE (Actualización)");
                
                const pkValue = rowData[PRIMARY_KEY];
                // Usar '==' (doble igual) para comparar flexiblemente (ej. 10 == "10")
                const index = changeset.updates.findIndex(item => item[PRIMARY_KEY] == pkValue);
                console.log(`... Buscando PK [${pkValue}] en 'changeset.updates'. Encontrado en índice: ${index}`);

                if (index > -1) {
                    changeset.updates.splice(index, 1);
                    console.log("... Fila YA ESTABA en 'updates'. Se elimina para re-añadir la versión más reciente.");
                }
                
                changeset.updates.push(rowData);
                console.log("... Fila (re)añadida a 'changeset.updates'.");
                statusMsg.innerHTML = '<span style="color: #e67e22;">Cambios (actualización) detectados. Presiona "Guardar".</span>';

            } else {
                console.log("... Tipo de cambio: INSERT (Nueva Fila)");
                if (!changeset.inserts.includes(rowData)) {
                    changeset.inserts.push(rowData);
                     console.log("... Fila añadida a 'changeset.inserts'.");
                } else {
                     console.log("... Fila ya estaba en 'changeset.inserts' (solo se actualizó su data).");
                }
                statusMsg.innerHTML = '<span style="color: #e67e22;">Cambios (nueva fila) detectados. Presiona "Guardar".</span>';
            }

            // Mostramos el estado actual del 'changeset' CADA VEZ que editas
            console.log("%cEstado actual del Changeset:", "font-weight: bold;", JSON.parse(JSON.stringify(changeset)));
            console.groupEnd(); // Cierra el grupo de logs
        }
        // --- FIN DE LA MODIFICACIÓN (DEBUGGING) ---
    });

    // --- Cargar Datos Iniciales ---
    function loadTableData() {
        statusMsg.innerHTML = "Cargando datos...";
        changeset.inserts = [];
        changeset.updates = [];
        changeset.deletes = [];
        
        fetch(`index.php?route=tableEdit/getData&table=${TABLE_NAME}`)
            .then(res => res.json())
            .then(response => {
                if (response.error) {
                    statusMsg.innerHTML = `<div class="error-message">${response.error}</div>`;
                    return;
                }
                table.setData(response.data);
                statusMsg.innerHTML = '<span style="color: #27ae60;">Datos cargados.</span>';
            })
            .catch(err => {
                 statusMsg.innerHTML = `<div class="error-message">Error de red: ${err.message}</div>`;
            });
    }

    // --- Event Listeners (con logging) ---
    btnAdd.addEventListener("click", () => {
        console.group("Evento: btnAdd (Añadir Fila)");
        table.addRow({}, true); // Añade la fila vacía al inicio
        console.log("... Fila nueva vacía añadida a la tabla (visual).");
        console.log("... NOTA: No se añade a 'changeset.inserts' hasta que la edites.");
        statusMsg.innerHTML = '<span style="color: #9b59b6;">Nueva fila añadida (temporal). Edítala para registrar el cambio.</span>';
        console.groupEnd();
    });

    btnDelete.addEventListener("click", () => {
        console.group("Evento: btnDelete (Eliminar Fila)");
        const selectedRows = table.getSelectedRows();
        if (selectedRows.length === 0) {
            console.warn("No hay filas seleccionadas.");
            console.groupEnd();
            alert("Por favor, selecciona una o más filas para eliminar.");
            return;
        }

        selectedRows.forEach(row => {
            const rowData = row.getData();
            const isExistingRow = !!rowData[PRIMARY_KEY];
            console.log("Procesando fila:", rowData, "¿Es existente?", isExistingRow);

            if (isExistingRow) {
                if (!changeset.deletes.find(item => item[PRIMARY_KEY] == rowData[PRIMARY_KEY])) {
                     changeset.deletes.push(rowData);
                     console.log("... Fila añadida a 'changeset.deletes'.");
                }
                const updateIndex = changeset.updates.findIndex(item => item[PRIMARY_KEY] == rowData[PRIMARY_KEY]);
                if (updateIndex > -1) {
                    changeset.updates.splice(updateIndex, 1);
                    console.log("... Fila eliminada de 'changeset.updates' (porque fue borrada).");
                }
            } else {
                const insertIndex = changeset.inserts.indexOf(rowData);
                if (insertIndex > -1) {
                    changeset.inserts.splice(insertIndex, 1);
                    console.log("... Fila nueva (que nunca se guardó) eliminada de 'changeset.inserts'.");
                }
            }
            row.delete();
        });
        
        console.log("%cEstado actual del Changeset:", "font-weight: bold;", JSON.parse(JSON.stringify(changeset)));
        statusMsg.innerHTML = '<span style="color: #e74c3c;">Fila(s) marcada(s) para eliminar.</span>';
        console.groupEnd();
    });

    btnRestore.addEventListener("click", () => {
        if (confirm("¿Estás seguro? Esto descartará TODOS los cambios no guardados y recargará los datos originales.")) {
            loadTableData();
        }
    });

    btnSave.addEventListener("click", () => {
        console.group("Evento: btnSave (Guardar Cambios)");

        // --- INICIO DE LA MODIFICACIÓN (DEBUGGING) ---
        // ESTE ES EL LOG MÁS IMPORTANTE
        console.log("%cRevisando el 'changeset' ANTES de la validación:", "color: blue; font-weight: bold;", JSON.parse(JSON.stringify(changeset)));
        // Usamos JSON.parse/stringify para "descongelar" el objeto y ver el valor real
        // --- FIN DE LA MODIFICACIÓN (DEBUGGING) ---

        if (changeset.inserts.length === 0 && changeset.updates.length === 0 && changeset.deletes.length === 0) {
            console.error("VALIDACIÓN FALLIDA: El 'changeset' está vacío.");
            alert("No hay cambios que guardar.");
            console.groupEnd();
            return;
        }
        
        console.log("%cVALIDACIÓN OK: Cambios encontrados. Abriendo modal.", "color: green;");
        
        authError.style.display = 'none';
        authPass.value = '';
        modal.style.display = 'flex';
        authPass.focus();
        console.groupEnd();
    });

    // --- Lógica del Modal (sin logs extra) ---
    btnAuthCancel.addEventListener("click", () => {
        modal.style.display = 'none';
    });

    btnAuthConfirm.addEventListener("click", () => {
        btnAuthConfirm.disabled = true;
        btnAuthConfirm.textContent = "Guardando...";

        const finalChangeset = [
            ...changeset.deletes.map(data => ({ type: 'delete', data })),
            ...changeset.updates.map(data => ({ type: 'update', data })),
            ...changeset.inserts.map(data => ({ type: 'insert', data }))
        ];

        const formData = new FormData();
        formData.append('table_name', TABLE_NAME);
        formData.append('changeset', JSON.stringify(finalChangeset));
        formData.append('username', authUser.value);
        formData.append('password', authPass.value);

        fetch(`index.php?route=tableEdit/saveChanges`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                statusMsg.innerHTML = '<span style="color: #27ae60;">¡Cambios guardados exitosamente!</span>';
                modal.style.display = 'none';
                loadTableData(); // Recargar los datos desde la BD (esto limpia el changeset)
            } else {
                authError.textContent = response.error;
                authError.style.display = 'block';
            }
        })
        .catch(err => {
            authError.textContent = `Error de red: ${err.message}`;
            authError.style.display = 'block';
        })
        .finally(() => {
            btnAuthConfirm.disabled = false;
            btnAuthConfirm.textContent = "Confirmar y Guardar";
        });
    });

    // Carga inicial de datos
    loadTableData();
});