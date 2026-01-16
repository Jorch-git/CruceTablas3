<?php
/**
 * Script para agregar una Clave Primaria (PK) a una tabla que no la tiene
 * 
 * Uso: 
 * - Ejecutar desde navegador: add_primary_key.php?table=nombre_tabla
 * - O modificar la variable $tableName abajo y ejecutar desde línea de comandos
 */

require_once 'config.php';
require_once 'model/Database.php';

// Nombre de la tabla a modificar
$tableName = $_GET['table'] ?? 'nc_estadistica';

// Conectar a la base de datos
$db = new Database();
$conn = $db->connect();

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

echo "<h2>Agregar Clave Primaria a la tabla: $tableName</h2>";

// Verificar si la tabla existe
$checkTable = "SHOW TABLES LIKE '$tableName'";
$result = $conn->query($checkTable);

if ($result->num_rows === 0) {
    die("Error: La tabla '$tableName' no existe.");
}

// Verificar si ya tiene PK
$checkPK = "SHOW COLUMNS FROM `$tableName` WHERE `Key` = 'PRI'";
$pkResult = $conn->query($checkPK);

if ($pkResult->num_rows > 0) {
    echo "<p style='color: green;'>✓ La tabla '$tableName' ya tiene una Clave Primaria.</p>";
    $pkRow = $pkResult->fetch_assoc();
    echo "<p>Clave Primaria actual: <strong>" . $pkRow['Field'] . "</strong></p>";
    exit;
}

// Obtener todas las columnas para ver qué tipo de datos tiene
$columns = "SHOW COLUMNS FROM `$tableName`";
$colsResult = $conn->query($columns);

echo "<h3>Columnas actuales:</h3><ul>";
$hasIdColumn = false;
$idColumnName = null;
while ($col = $colsResult->fetch_assoc()) {
    echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
    // Buscar si ya existe una columna 'id' o similar
    if (strtolower($col['Field']) === 'id' || strtolower($col['Field']) === 'id_' . strtolower($tableName)) {
        $hasIdColumn = true;
        $idColumnName = $col['Field'];
    }
}
echo "</ul>";

// Opción 1: Si ya existe una columna 'id', convertirla en PK
if ($hasIdColumn) {
    echo "<h3>Opción 1: Convertir columna existente '$idColumnName' en PK</h3>";
    
    // Verificar si tiene valores NULL o duplicados
    $checkNulls = "SELECT COUNT(*) as nulls FROM `$tableName` WHERE `$idColumnName` IS NULL";
    $nullResult = $conn->query($checkNulls);
    $nulls = $nullResult->fetch_assoc()['nulls'];
    
    $checkDups = "SELECT COUNT(*) as dups FROM (SELECT `$idColumnName`, COUNT(*) as cnt FROM `$tableName` GROUP BY `$idColumnName` HAVING cnt > 1) as t";
    $dupResult = $conn->query($checkDups);
    $dups = $dupResult->fetch_assoc()['dups'];
    
    if ($nulls > 0) {
        echo "<p style='color: orange;'>⚠ Advertencia: La columna tiene $nulls valores NULL. Se deben llenar antes de hacerla PK.</p>";
    }
    
    if ($dups > 0) {
        echo "<p style='color: red;'>✗ Error: La columna tiene valores duplicados. No se puede convertir en PK.</p>";
    } else if ($nulls === 0) {
        echo "<p style='color: green;'>✓ La columna puede convertirse en PK.</p>";
        echo "<p><strong>SQL a ejecutar:</strong></p>";
        echo "<pre>ALTER TABLE `$tableName` MODIFY COLUMN `$idColumnName` INT AUTO_INCREMENT PRIMARY KEY;</pre>";
        
        if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
            $sql = "ALTER TABLE `$tableName` MODIFY COLUMN `$idColumnName` INT AUTO_INCREMENT PRIMARY KEY";
            if ($conn->query($sql)) {
                echo "<p style='color: green; font-weight: bold;'>✓ PK agregada exitosamente!</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            }
        } else {
            echo "<p><a href='?table=$tableName&execute=yes' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ejecutar SQL</a></p>";
        }
    }
}

// Opción 2: Crear una nueva columna ID como PK
echo "<h3>Opción 2: Crear nueva columna 'id' como PK autoincremental</h3>";
echo "<p>Esta opción agregará una nueva columna 'id' al inicio de la tabla.</p>";
echo "<p><strong>SQL a ejecutar:</strong></p>";
echo "<pre>ALTER TABLE `$tableName` ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST;</pre>";

if (isset($_GET['execute']) && $_GET['execute'] === 'newid') {
    $sql = "ALTER TABLE `$tableName` ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST";
    if ($conn->query($sql)) {
        echo "<p style='color: green; font-weight: bold;'>✓ Columna 'id' agregada como PK exitosamente!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    }
} else {
    echo "<p><a href='?table=$tableName&execute=newid' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Crear nueva columna ID</a></p>";
}

// Opción 3: Crear PK compuesta (si hay columnas únicas)
echo "<h3>Opción 3: Crear PK compuesta con columnas existentes</h3>";
echo "<p>Si tu tabla tiene columnas que juntas forman un identificador único (ej: entidad_ok + municipio_ok), puedes crear una PK compuesta.</p>";
echo "<p><strong>Ejemplo SQL:</strong></p>";
echo "<pre>ALTER TABLE `$tableName` ADD PRIMARY KEY (`columna1`, `columna2`);</pre>";
echo "<p><em>Nota: Debes modificar este SQL manualmente según tus necesidades.</em></p>";

$conn->close();
?>

