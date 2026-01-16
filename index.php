<?php
// Iniciar la sesión en el punto de entrada principal
session_start();

// Cargar la configuración
require_once 'config.php';

// --- ENRUTADOR BÁSICO ---

// 1. Obtener la ruta de la URL
// Si no se especifica ruta, por defecto va al login
$route = $_GET['route'] ?? 'auth/index';
$route_parts = explode('/', $route);

// 2. Determinar el Controlador y la Acción
// Ej: 'main/app' -> Controlador: 'MainController', Acción: 'app'
// Ej: 'auth/login' -> Controlador: 'AuthController', Acción: 'login'
$controller_name = ucfirst(strtolower($route_parts[0])) . 'Controller';
$action_name = $route_parts[1] ?? 'index'; // Acción por defecto es 'index'

// 3. Cargar el archivo del controlador
$controller_file = 'controller/' . $controller_name . '.php';

if (file_exists($controller_file)) {
    require_once $controller_file;

    // 4. Crear una instancia del controlador
    if (class_exists($controller_name)) {
        $controller = new $controller_name();

        // 5. Llamar a la acción (método)
        if (method_exists($controller, $action_name)) {
            $controller->$action_name();
        } else {
            die("Error 404: La acción '$action_name' no existe en el controlador '$controller_name'.");
        }
    } else {
        die("Error 404: La clase controladora '$controller_name' no existe.");
    }
} else {
    die("Error 404: El archivo controlador '$controller_file' no se encuentra.");
}