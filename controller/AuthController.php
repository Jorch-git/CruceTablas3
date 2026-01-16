<?php
require_once 'model/Auth.php';

class AuthController {
    
    private $auth;

    public function __construct() {
        $this->auth = new Auth();
    }

    /**
     * Muestra la página de login
     */
    public function index() {
        // Si ya está logueado, lo mandamos a la app principal
        if ($this->auth->isLoggedIn()) {
            header('Location: index.php?route=main/index');
            exit;
        }
        // Cargar la vista de login
        require_once 'view/login.php';
    }

    /**
     * Procesa el formulario de login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($this->auth->login($username, $password)) {
                // Éxito: redirigir a la app principal
                header('Location: index.php?route=main/index');
            } else {
                // Error: volver a mostrar el login con un mensaje
                $error = "Usuario o contraseña incorrectos.";
                require_once 'view/login.php';
            }
        } else {
            // Si intentan acceder por GET, redirigir al index
            header('Location: index.php?route=auth/index');
        }
    }

    /**
     * Cierra la sesión
     */
    public function logout() {
        $this->auth->logout();
        header('Location: index.php?route=auth/index');
    }
}