<?php
class Auth {
    
    /**
     * Intenta loguear al usuario
     */
    public function login($username, $password) {
        if ($username === APP_USER && $password === APP_PASS) {
            // Si es correcto, se guarda en la sesión
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout() {
        session_unset();
        session_destroy();
    }

    /**
     * Verifica si el usuario está actualmente logueado
     */
    public function isLoggedIn() {
        return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }

    /**
     * (Seguridad) Redirige al login si no está logueado
     */
    public function checkAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php?route=auth/index');
            exit;
        }
    }
}