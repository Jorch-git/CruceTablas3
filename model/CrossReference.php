<?php
require_once 'model/Database.php';

class CrossReference {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /**
     * Obtiene todas las tablas de la base de datos
     */
    public function getAllTables() {
        $tables = [];
        $sql = "SHOW TABLES";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    /**
     * Obtiene los valores únicos de la columna 'categoria' de una tabla
     * @deprecated Usar getUniqueColumnValues en su lugar
     */
    public function getUniqueCategories($tableName) {
        return $this->getUniqueColumnValues($tableName, 'categoria');
    }
    
    /**
     * Obtiene los valores únicos de cualquier columna de una tabla
     * @param string $tableName Nombre de la tabla
     * @param string $columnName Nombre de la columna
     * @return array Array con los valores únicos
     */
    public function getUniqueColumnValues($tableName, $columnName) {
        $values = [];
        // Sanitizar nombre de tabla y columna (prevención básica de SQL Injection)
        $safeTableName = $this->conn->real_escape_string($tableName);
        $safeColumnName = $this->conn->real_escape_string($columnName);
        
        // Verificar si la columna existe
        $checkColSql = "SHOW COLUMNS FROM `$safeTableName` LIKE '$safeColumnName'";
        $colResult = $this->conn->query($checkColSql);
        
        if ($colResult && $colResult->num_rows > 0) {
            $sql = "SELECT DISTINCT `$safeColumnName` FROM `$safeTableName` WHERE `$safeColumnName` IS NOT NULL AND `$safeColumnName` != ''";
            $result = $this->conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $values[] = $row[$columnName];
                }
            }
        }
        return $values;
    }

    /**
     * Obtiene las columnas de una tabla excluyendo entidad_ok y municipio_ok
     * @param string $tableName Nombre de la tabla
     * @return array Array con los nombres de las columnas
     */
    public function getTableColumns($tableName) {
        $columns = [];
        $safeTableName = $this->conn->real_escape_string($tableName);
        
        $sql = "SHOW COLUMNS FROM `$safeTableName`";
        $result = $this->conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columnName = $row['Field'];
                // Excluir entidad_ok y municipio_ok
                if ($columnName !== 'entidad_ok' && $columnName !== 'municipio_ok') {
                    $columns[] = $columnName;
                }
            }
        }
        
        return $columns;
    }

/**
     * Busca duplicados (entidad_ok, municipio_ok) en una lista de tablas.
     *
     * @param array $verifyTables Lista de nombres de tablas a verificar.
     * @return array Un array con los reportes de duplicados.
     */
    public function findDuplicatesInVerifyTables($verifyTables) {
        $duplicateReports = []; // Array para guardar los mensajes

        foreach ($verifyTables as $tableName) {
            if (empty($tableName)) {
                continue;
            }

            $safeTableName = $this->conn->real_escape_string($tableName);
            
            // SQL para encontrar duplicados, normalizando los campos
            // (Usamos UPPER y TRIM para ser consistentes con tu lógica de 'clave_limpia')
            $sql = "SELECT 
                        UPPER(TRIM(`entidad_ok`)) as `entidad_norm`, 
                        UPPER(TRIM(`municipio_ok`)) as `municipio_norm`, 
                        COUNT(*) as `count`
                    FROM 
                        `$safeTableName`
                    WHERE 
                        `entidad_ok` IS NOT NULL AND `municipio_ok` IS NOT NULL
                    GROUP BY 
                        `entidad_norm`, `municipio_norm`
                    /* ========================================================
                     * === ¡CORRECCIÓN! Se elimina el 'HAVING' para obtener todos los conteos ===
                     * ========================================================
                    -- HAVING 
                    --    `count` > 1 
                    */
                    ORDER BY 
                        `count` DESC";

            $result = $this->conn->query($sql);

            if ($result && $result->num_rows > 0) {
                // Se encontraron duplicados en esta tabla
                while ($row = $result->fetch_assoc()) {
                    // Crear el objeto de log como lo pediste
                    $logMessage = [
                        'tabla' => $tableName,
                        'entidad' => $row['entidad_norm'],
                        'municipio' => $row['municipio_norm'],
                        'repeticiones' => (int)$row['count'] // Asegurarnos que sea un número
                    ];
                    $duplicateReports[] = $logMessage;
                }
            }
        }

        return $duplicateReports;
    }



    /**
     * Carga los datos de una tabla y les añade la 'clave_limpia'
     */
    private function loadAndNormalizeData($tableName, $columns = ['*']) {
        $data = [];
        $safeTableName = $this->conn->real_escape_string($tableName);
        $safeCols = $columns == ['*'] ? '*' : '`' . implode('`, `', array_map([$this->conn, 'real_escape_string'], $columns)) . '`';
        
        $sql = "SELECT $safeCols FROM `$safeTableName`";
        $result = $this->conn->query($sql);

        if (!$result) {
            echo "Error en SQL: " . $this->conn->error;
            return [];
        }

        while ($row = $result->fetch_assoc()) {
            // Replicar la lógica de 'clave_limpia' de Python
            if (isset($row['entidad_ok']) && isset($row['municipio_ok'])) {
                $row['clave_limpia'] = strtoupper(trim($row['entidad_ok'])) . '|' . strtoupper(trim($row['municipio_ok']));
            }
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Crea un "set" (mapa) de claves únicas para búsquedas rápidas
     * Esto replica el `set(df['clave_limpia'])` de Python
     */
    private function getClaveLookupSet($tableName) {
        $lookupSet = [];
        // Solo necesitamos las columnas para la clave
        $data = $this->loadAndNormalizeData($tableName, ['entidad_ok', 'municipio_ok']);
        foreach ($data as $row) {
            if (isset($row['clave_limpia'])) {
                $lookupSet[$row['clave_limpia']] = true; // Usar la clave del array para búsquedas O(1)
            }
        }
        return $lookupSet;
    }

    /**
     * Procesa la solicitud de cruce de datos completa
     */
    public function processData($baseTable, $verifyTables = [], $fuseTables = [], $categoriesToSplit = [], $splitColumn = null) {
        
        // 1. Cargar datos de la tabla principal
        $mainData = $this->loadAndNormalizeData($baseTable);
        $headers = empty($mainData) ? [] : array_keys($mainData[0]);

        if (empty($mainData)) {
            return ['data' => [], 'headers' => []]; // No hay datos para procesar
        }

        // 2. Lógica de "tablas_de_verificacion" (Sí/No)
        // Replicar `df_principal['clave_limpia'].isin(claves_unicas)`
        foreach ($verifyTables as $table) {
            $lookupSet = $this->getClaveLookupSet($table);
            $newColName = 'en_' . $table;
            $headers[] = $newColName;

            foreach ($mainData as $i => $row) {
                $mainData[$i][$newColName] = 'No'; // Valor por defecto
                if (isset($row['clave_limpia']) && isset($lookupSet[$row['clave_limpia']])) {
                    $mainData[$i][$newColName] = 'Sí';
                }
            }
        }

        // 3. Lógica de "tablas_de_fusion" (Merge)
        // Replicar `pd.merge(..., how='left')`
        foreach ($fuseTables as $tableInfo) {
            $tableName = $tableInfo['table'];
            $columns = $tableInfo['columns'];
            
            // Asegurarnos de traer siempre las columnas clave para el merge
            $colsToFetch = array_unique(array_merge(['entidad_ok', 'municipio_ok'], $columns));
            
            $fusionData = $this->loadAndNormalizeData($tableName, $colsToFetch);
            
            // Crear un mapa de búsqueda para el merge
            $fusionMap = [];
            foreach ($fusionData as $row) {
                if (isset($row['clave_limpia'])) {
                    $fusionMap[$row['clave_limpia']] = $row;
                }
            }

            // Añadir nuevas cabeceras con el nombre de la tabla de origen en el nombre de la columna
            $renamedColumns = [];
            foreach ($columns as $col) {
                $newColName = $col . '__' . $tableName;
                $renamedColumns[$col] = $newColName;
                $headers[] = $newColName;
            }
            $headers = array_unique($headers);

            // Realizar el "left merge"
            foreach ($mainData as $i => $row) {
                $clave = $row['clave_limpia'] ?? null;
                
                if ($clave && isset($fusionMap[$clave])) {
                    // Clave encontrada, fusionar datos con el nuevo nombre de columna
                    foreach ($columns as $col) {
                        $newColName = $renamedColumns[$col];
                        $mainData[$i][$newColName] = $fusionMap[$clave][$col] ?? 'NA'; // Usar NA si la col no vino
                    }
                } else {
                    // Clave no encontrada, rellenar con NA
                    foreach ($columns as $col) {
                        $newColName = $renamedColumns[$col];
                        $mainData[$i][$newColName] = 'NA';
                    }
                }
            }
        }

        // 4. Lógica de "categorias_a_separar" (NUEVA LÓGICA: crear columnas y duplicar filas)
        // Crear columnas con los nombres de los valores y duplicar filas
        if ($splitColumn && !empty($categoriesToSplit)) {
            // Verificar que la columna existe en los datos (no solo en headers)
            $columnExists = false;
            if (!empty($mainData)) {
                $firstRow = $mainData[0];
                $columnExists = isset($firstRow[$splitColumn]);
            }
            
            if ($columnExists) {
                // Crear las nuevas columnas con los nombres de los valores (mantener nombres originales)
                $newColumns = [];
                foreach ($categoriesToSplit as $category) {
                    // Usar el nombre original de la categoría como nombre de columna
                    $newColName = $category; // Mantener el nombre original
                    $newColumns[] = $newColName;
                    // Agregar a headers si no existe
                    if (!in_array($newColName, $headers)) {
                        $headers[] = $newColName;
                    }
                }
                $headers = array_unique($headers);
                
                // Duplicar filas: cada fila original se convierte en múltiples filas (una por cada valor)
                $expandedData = [];
                foreach ($mainData as $row) {
                    // Obtener el valor de la columna a separar
                    $rowValue = isset($row[$splitColumn]) ? trim($row[$splitColumn]) : '';
                    $rowValueUpper = strtoupper($rowValue);
                    
                    // Crear una fila por cada valor seleccionado
                    foreach ($categoriesToSplit as $category) {
                        $newRow = []; // Crear nueva fila vacía
                        
                        // Copiar todas las columnas existentes excepto la que se va a separar y clave_limpia
                        foreach ($row as $key => $value) {
                            if ($key !== $splitColumn && $key !== 'clave_limpia') {
                                $newRow[$key] = $value;
                            }
                        }
                        
                        // Inicializar todas las nuevas columnas con 'No'
                        foreach ($newColumns as $col) {
                            $newRow[$col] = 'No';
                        }
                        
                        // Si el valor de la fila coincide con esta categoría, marcar como 'Sí'
                        $categoryUpper = strtoupper(trim($category));
                        if ($rowValueUpper === $categoryUpper) {
                            $newRow[$category] = 'Sí';
                        }
                        
                        $expandedData[] = $newRow;
                    }
                }
                
                $mainData = $expandedData;
                
                // Eliminar la columna original de splitColumn de los headers
                $headers = array_values(array_diff($headers, [$splitColumn]));
            }
        }
        
        // Limpiar la 'clave_limpia' de la salida final
        $finalData = [];
        $finalHeaders = array_values(array_diff($headers, ['clave_limpia']));
        
        foreach ($mainData as $row) {
            unset($row['clave_limpia']);
            // Asegurar que todas las filas tengan todas las columnas en el orden correcto
            $finalRow = [];
            foreach ($finalHeaders as $header) {
                $finalRow[$header] = $row[$header] ?? 'No'; // Default a 'No' para columnas de separación
            }
            $finalData[] = $finalRow;
        }

        return ['data' => $finalData, 'headers' => $finalHeaders];
    }
    
    /**
     * Consolida filas duplicadas agrupando por todas las columnas excepto las de separación
     * Solo consolida cuando todas las columnas (excepto las de separación) son iguales
     * @param array $data Datos a consolidar
     * @param array $headers Cabeceras de las columnas
     * @return array Datos consolidados
     */
    public function consolidateDuplicateRows($data, $headers) {
        if (empty($data)) {
            return ['data' => [], 'headers' => $headers];
        }
        
        // Identificar columnas que son de separación (columnas que solo tienen 'Sí' o 'No')
        $splitColumns = [];
        foreach ($headers as $header) {
            if ($header === 'entidad_ok' || $header === 'municipio_ok') {
                continue;
            }
            
            // Verificar si esta columna solo tiene valores 'Sí' o 'No'
            $hasOnlySiNo = true;
            foreach ($data as $row) {
                $value = strtoupper(trim($row[$header] ?? ''));
                if ($value !== 'SÍ' && $value !== 'SI' && $value !== 'NO' && $value !== 'NA' && $value !== '') {
                    $hasOnlySiNo = false;
                    break;
                }
            }
            
            if ($hasOnlySiNo) {
                $splitColumns[] = $header;
            }
        }
        
        // Identificar columnas que NO son de separación (todas las demás)
        $nonSplitColumns = array_diff($headers, $splitColumns);
        
        // Función para crear una clave única basada en todas las columnas no-separación
        $createKey = function($row) use ($nonSplitColumns) {
            $keyParts = [];
            foreach ($nonSplitColumns as $col) {
                $value = $row[$col] ?? '';
                // Normalizar el valor para comparación
                $normalized = strtoupper(trim($value));
                $keyParts[] = $col . ':' . $normalized;
            }
            return implode('||', $keyParts);
        };
        
        // Agrupar por todas las columnas no-separación
        $grouped = [];
        foreach ($data as $row) {
            $key = $createKey($row);
            
            if (!isset($grouped[$key])) {
                // Primera fila de este grupo, usar como base
                $grouped[$key] = [];
                
                // Copiar todas las columnas no-separación
                foreach ($nonSplitColumns as $col) {
                    $grouped[$key][$col] = $row[$col] ?? '';
                }
                
                // Inicializar columnas de separación con 'No'
                foreach ($splitColumns as $col) {
                    $grouped[$key][$col] = 'No';
                }
            }
            
            // Merge: si alguna fila tiene 'Sí' en una columna de separación, marcar como 'Sí'
            foreach ($splitColumns as $col) {
                $value = strtoupper(trim($row[$col] ?? ''));
                if ($value === 'SÍ' || $value === 'SI') {
                    $grouped[$key][$col] = 'Sí';
                }
            }
        }
        
        // Convertir el array agrupado de vuelta a array indexado
        $consolidatedData = array_values($grouped);
        
        return ['data' => $consolidatedData, 'headers' => $headers];
    }
}