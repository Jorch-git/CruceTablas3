<?php
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    public $conn;

    /**
     * Obtiene la conexión a la base de datos
     */
    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);

        if ($this->conn->connect_error) {
            die("Conexión fallida: " . $this->conn->connect_error);
        }
        
        // Es buena práctica establecer el charset
        $this->conn->set_charset("utf8mb4");

        return $this->conn;
    }
}