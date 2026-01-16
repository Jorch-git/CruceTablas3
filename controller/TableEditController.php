<?php
require_once 'model/Auth.php';
require_once 'model/TableEditModel.php'; // Modelo nuevo

class TableEditController {

    private $auth;
    private $editModel;

    public function __construct() {
        $this->auth = new Auth();
        $this->auth->checkAuth(); // Proteger este controlador
        $this->editModel = new TableEditModel();
    }

    /**
     * Muestra la página del editor
     */
    public function index() {
        $table = $_GET['table'] ?? null;
        if (empty($table)) {
            $_SESSION['error'] = "No se especificó ninguna tabla para editar.";
            header('Location: index.php?route=main/index');
            exit;
        }

        // Obtener el esquema (columnas y clave primaria)
        $schema = $this->editModel->getTableSchema($table);
        
        if (empty($schema['primary_key'])) {
            // En lugar de rechazar, mostrar opciones para agregar PK
            $data = [
                'table_name' => $table,
                'columns' => $schema['columns'],
                'primary_key' => null,
                'has_pk' => false
            ];
            
            $this->loadView('table_edit_no_pk', $data);
            return;
        }

        $data = [
            'table_name' => $table,
            'columns' => $schema['columns'],
            'primary_key' => $schema['primary_key']
        ];
        
        $this->loadView('table_edit_view', $data);
    }

    /**
     * (AJAX) Obtiene los datos de la tabla
     */
    public function getData() {
        header('Content-Type: application/json');
        $table = $_GET['table'] ?? null;
        if (empty($table)) {
            echo json_encode(['error' => 'No se especificó la tabla.']);
            exit;
        }
        $data = $this->editModel->getTableData($table);
        echo json_encode(['data' => $data]);
    }

    /**
     * (AJAX) Guarda los cambios
     */
    public function saveChanges() {
        header('Content-Type: application/json');

        // 1. Re-autenticar al usuario
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;

        if (!$this->editModel->checkAuth($username, $password)) {
            echo json_encode(['success' => false, 'error' => '¡Autenticación fallida! Usuario o contraseña incorrectos.']);
            exit;
        }

        // 2. Obtener los datos a guardar
        $table = $_POST['table_name'] ?? null;
        $changeset = json_decode($_POST['changeset'] ?? '[]', true);

        if (empty($table) || empty($changeset)) {
            echo json_encode(['success' => false, 'error' => 'No se enviaron cambios o falta el nombre de la tabla.']);
            exit;
        }
        
        // 3. Aplicar cambios (con lógica de transacción/rollback)
        $result = $this->editModel->applyChanges($table, $changeset);

        if ($result['success']) {
            echo json_encode(['success' => true]);
        } else {
            // El 'rollback' fue automático gracias a la transacción
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }

    /**
     * Agrega una Clave Primaria a una tabla
     */
    public function addPrimaryKey() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $tableName = $_POST['table'] ?? null;
        $type = $_POST['type'] ?? null;
        $columnName = $_POST['column'] ?? null;

        if (empty($tableName)) {
            echo json_encode(['success' => false, 'error' => 'No se especificó la tabla']);
            exit;
        }

        $result = $this->editModel->addPrimaryKey($tableName, $type, $columnName);
        echo json_encode($result);
    }

    /**
     * Helper para cargar vistas (incluyendo header y footer)
     */
    private function loadView($viewName, $data = []) {
        extract($data);
        require_once 'view/_parts/header.php';
        require_once 'view/' . $viewName . '.php';
        require_once 'view/_parts/footer.php';
    }
}