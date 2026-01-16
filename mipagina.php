<?php
$servername = "localhost"; // O "127.0.0.1"
$username = "user_web";
$password = "clave";
$dbname = "presentacionmapas";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}
echo "¡Conectado exitosamente a la base de datos!";

// ... aquí iría tu código para hacer consultas ...

$conn->close();
?>