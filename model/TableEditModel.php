<?php
require_once 'model/Database.php';

class TableEditModel {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /**
     * Revisa las credenciales (copia de la lógica de Auth)
     */
    public function checkAuth($username, $password) {
        // Usa las constantes de tu config.php
        return ($username === APP_USER && $password === APP_PASS);
    }

    /**
     * Obtiene todas las filas de una tabla
     */
    public function getTableData($tableName) {
        $safeTable = $this->conn->real_escape_string($tableName);
        $sql = "SELECT * FROM `$safeTable`";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene el esquema de la tabla (columnas y clave primaria)
     */
    public function getTableSchema($tableName) {
        $safeTable = $this->conn->real_escape_string($tableName);
        $columns = [];
        $primaryKey = null;

        $sql = "SHOW COLUMNS FROM `$safeTable`";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
            if ($row['Key'] === 'PRI') {
                $primaryKey = $row['Field'];
            }
        }
        return ['columns' => $columns, 'primary_key' => $primaryKey];
    }

    /**
     * Aplica un conjunto de cambios (INSERT, UPDATE, DELETE)
     * usando transacciones para el 'rollback'
     */
    public function applyChanges($tableName, $changeset) {
        $safeTable = $this->conn->real_escape_string($tableName);
        $schema = $this->getTableSchema($tableName);
        $pk = $schema['primary_key'];

        if (empty($pk)) {
            return ['success' => false, 'error' => 'La tabla no tiene Clave Primaria (PK) y no puede ser editada.'];
        }

        // ¡Inicia Transacción! (Punto de Rollback)
        $this->conn->begin_transaction();

        try {
            foreach ($changeset as $change) {
                $type = $change['type'];
                $data = $change['data'];

                // Sanitizar todos los valores
                $cleanData = [];
                foreach ($data as $key => $value) {
                    $cleanData[$this->conn->real_escape_string($key)] = $this->conn->real_escape_string($value);
                }

                if ($type === 'delete') {
                    $pkValue = $cleanData[$pk];
                    $sql = "DELETE FROM `$safeTable` WHERE `$pk` = '$pkValue'";
                    
                } else if ($type === 'update') {
                    $pkValue = $cleanData[$pk];
                    $updates = [];
                    foreach ($cleanData as $key => $value) {
                        if ($key !== $pk) {
                            $updates[] = "`$key` = '$value'";
                        }
                    }
                    $sql = "UPDATE `$safeTable` SET " . implode(', ', $updates) . " WHERE `$pk` = '$pkValue'";

                } else if ($type === 'insert') {
                    // Quitar la PK si está vacía (para autoincrement)
                    if (isset($cleanData[$pk]) && empty($cleanData[$pk])) {
                        unset($cleanData[$pk]);
                    }
                    $keys = array_keys($cleanData);
                    $values = array_values($cleanData);
                    $sql = "INSERT INTO `$safeTable` (`" . implode('`, `', $keys) . "`) VALUES ('" . implode("', '", $values) . "')";
                }

                // Ejecutar la consulta
                if (!$this->conn->query($sql)) {
                    // ¡Si algo falla, lanza una excepción!
                    throw new Exception("Error en la consulta: " . $this->conn->error . " (SQL: $sql)");
                }
            }

            // Si todo salió bien, confirmar cambios
            $this->conn->commit();
            return ['success' => true];

        } catch (Exception $e) {
            // ¡Si algo falló, hacer ROLLBACK!
            $this->conn->rollback();
            return ['success' => false, 'error' => 'Falló la transacción. No se guardó ningún cambio. Error: ' . $e->getMessage()];
        }
    }

    /**
     * Agrega una Clave Primaria a una tabla
     * @param string $tableName Nombre de la tabla
     * @param string $type Tipo: 'new' para nueva columna, 'existing' para columna existente
     * @param string $columnName Nombre de la columna (solo si type es 'existing')
     * @return array Resultado de la operación
     */
    public function addPrimaryKey($tableName, $type, $columnName = null) {
        $safeTable = $this->conn->real_escape_string($tableName);
        
        // Verificar que la tabla existe
        $checkTable = "SHOW TABLES LIKE '$safeTable'";
        $result = $this->conn->query($checkTable);
        if (!$result || $result->num_rows === 0) {
            return ['success' => false, 'error' => "La tabla '$tableName' no existe."];
        }

        // Verificar si ya tiene PK
        $checkPK = "SHOW COLUMNS FROM `$safeTable` WHERE `Key` = 'PRI'";
        $pkResult = $this->conn->query($checkPK);
        if ($pkResult && $pkResult->num_rows > 0) {
            return ['success' => false, 'error' => "La tabla ya tiene una Clave Primaria."];
        }

        $this->conn->begin_transaction();

        try {
            if ($type === 'new') {
                // Crear nueva columna 'id' como PK autoincremental
                $sql = "ALTER TABLE `$safeTable` ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST";
                
                if ($this->conn->query($sql)) {
                    $this->conn->commit();
                    return ['success' => true, 'message' => "Columna 'id' agregada como Clave Primaria exitosamente."];
                } else {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => "Error al agregar PK: " . $this->conn->error];
                }
                
            } else if ($type === 'existing' && $columnName) {
                $safeColumn = $this->conn->real_escape_string($columnName);
                
                // Verificar que la columna existe
                $checkCol = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
                $colResult = $this->conn->query($checkCol);
                if (!$colResult || $colResult->num_rows === 0) {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => "La columna '$columnName' no existe."];
                }

                // Verificar valores NULL
                $checkNulls = "SELECT COUNT(*) as nulls FROM `$safeTable` WHERE `$safeColumn` IS NULL";
                $nullResult = $this->conn->query($checkNulls);
                $nulls = $nullResult->fetch_assoc()['nulls'];
                
                if ($nulls > 0) {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => "La columna tiene $nulls valores NULL. Debes llenarlos antes de hacerla PK."];
                }

                // Verificar duplicados
                $checkDups = "SELECT COUNT(*) as dups FROM (SELECT `$safeColumn`, COUNT(*) as cnt FROM `$safeTable` GROUP BY `$safeColumn` HAVING cnt > 1) as t";
                $dupResult = $this->conn->query($checkDups);
                $dups = $dupResult->fetch_assoc()['dups'];
                
                if ($dups > 0) {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => "La columna tiene valores duplicados. No se puede convertir en PK."];
                }

                // Intentar convertir en PK (primero intentar con AUTO_INCREMENT si es INT)
                $getColType = "SHOW COLUMNS FROM `$safeTable` WHERE Field = '$safeColumn'";
                $typeResult = $this->conn->query($getColType);
                $colInfo = $typeResult->fetch_assoc();
                $colType = strtoupper($colInfo['Type']);
                
                if (strpos($colType, 'INT') !== false) {
                    $sql = "ALTER TABLE `$safeTable` MODIFY COLUMN `$safeColumn` INT AUTO_INCREMENT PRIMARY KEY";
                } else {
                    // Si no es INT, solo agregar PK sin autoincrement
                    $sql = "ALTER TABLE `$safeTable` ADD PRIMARY KEY (`$safeColumn`)";
                }
                
                if ($this->conn->query($sql)) {
                    $this->conn->commit();
                    return ['success' => true, 'message' => "Columna '$columnName' convertida en Clave Primaria exitosamente."];
                } else {
                    // Si falla, intentar solo PK sin modificar tipo
                    $sql2 = "ALTER TABLE `$safeTable` ADD PRIMARY KEY (`$safeColumn`)";
                    if ($this->conn->query($sql2)) {
                        $this->conn->commit();
                        return ['success' => true, 'message' => "Columna '$columnName' convertida en Clave Primaria exitosamente (sin autoincrement)."];
                    } else {
                        $this->conn->rollback();
                        return ['success' => false, 'error' => "Error al convertir en PK: " . $this->conn->error];
                    }
                }
            } else {
                $this->conn->rollback();
                return ['success' => false, 'error' => "Tipo de operación inválido o columna no especificada."];
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => "Error: " . $e->getMessage()];
        }
    }
}