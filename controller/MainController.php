<?php
require_once 'model/Auth.php';
require_once 'model/CrossReference.php';

class MainController {

    private $auth;
    private $crossRefModel;

    public function __construct() {
        $this->auth = new Auth();
        // Proteger todo este controlador: si no está logueado, se va al login.
        $this->auth->checkAuth();
        
        $this->crossRefModel = new CrossReference();
    }

    /**
     * Muestra el formulario principal de la aplicación
     * Esta es la acción por defecto (index)
     */
    public function index() {
        // Cargar datos para los selectores del formulario
        $baseTableSelected = $_SESSION['form_data']['base_table'] ?? null;
        $splitColumnSelected = $_SESSION['form_data']['split_column'] ?? null;
        
        $data = [
            'all_tables' => $this->crossRefModel->getAllTables(),
            'base_table_selected' => $baseTableSelected,
            'categories' => [],
            'form_data' => $_SESSION['form_data'] ?? [],
            'available_columns' => []
        ];

        // Si ya se seleccionó una tabla base, obtener sus columnas disponibles (excluyendo entidad_ok y municipio_ok)
        if (!empty($baseTableSelected)) {
            $data['available_columns'] = $this->crossRefModel->getTableColumns($baseTableSelected);
            
            // Si hay una columna seleccionada para separar, obtener sus valores únicos
            if (!empty($splitColumnSelected)) {
                $data['categories'] = $this->crossRefModel->getUniqueColumnValues($baseTableSelected, $splitColumnSelected);
            }
        }
        
        // Cargar la vista principal de la app
        $this->loadView('main_app', $data);
    }

    /**
     * Acción para "pre-cargar" el formulario cuando se elige la tabla base.
     * Esto es necesario para obtener las categorías dinámicamente.
     */
    public function selectBaseTable() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Guardar la selección en la sesión para que el form la recuerde
            $_SESSION['form_data'] = [
                'base_table' => $_POST['base_table'] ?? null
            ];
        }
        // Redirigir de nuevo al formulario principal (index),
        // que ahora detectará la tabla base en la sesión.
        header('Location: index.php?route=main/index');
    }

    /**
     * Procesa el formulario completo y muestra los resultados
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?route=main/index');
            exit;
        }

        // 1. Recolectar datos del formulario
        $baseTable = $_POST['base_table'] ?? null;
        $verifyTables = $_POST['verify_tables'] ?? [];
        $categoriesToSplit = $_POST['categories_to_split'] ?? [];
        
        // Guardar en sesión para "recordar" el formulario
        $_SESSION['form_data'] = $_POST;

        // 2. Procesar las tablas de FUSIÓN (nuevo formato con checkboxes)
        $fuseTables = [];
        if (isset($_POST['fuse_table_name']) && is_array($_POST['fuse_table_name'])) {
            foreach ($_POST['fuse_table_name'] as $index => $tableName) {
                if (!empty($tableName)) {
                    // Obtener las columnas seleccionadas desde los checkboxes
                    $columns = [];
                    if (isset($_POST['fuse_table_cols'][$index]) && is_array($_POST['fuse_table_cols'][$index])) {
                        $columns = $_POST['fuse_table_cols'][$index];
                    }
                    
                    // Validar máximo 3 columnas
                    if (count($columns) > 3) {
                        $columns = array_slice($columns, 0, 3);
                    }
                    
                    if (!empty($columns)) {
                        $fuseTables[] = [
                            'table' => $tableName,
                            'columns' => $columns
                        ];
                    }
                }
            }
        }
        
        // 3. Validar que la tabla base esté seleccionada
        if (empty($baseTable)) {
            $_SESSION['error'] = "Por favor, selecciona una tabla base primero.";
            header('Location: index.php?route=main/index');
            exit;
        }

        // =================================================================
        // *** INICIO DE LA MODIFICACIÓN ***
        // =================================================================
        // 3. Llamar a la función de búsqueda de duplicados (AHORA OBTIENE TODOS LOS CONTEOS)
        $duplicateInfo = $this->crossRefModel->findDuplicatesInVerifyTables($verifyTables);
        // =================================================================
        // *** FIN DE LA MODIFICACIÓN ***

        // 4. Obtener la columna seleccionada para separar
        $splitColumn = $_POST['split_column'] ?? null;
        
        // Debug: verificar que los datos lleguen correctamente
        if ($splitColumn && !empty($categoriesToSplit)) {
            error_log("DEBUG: splitColumn = " . $splitColumn);
            error_log("DEBUG: categoriesToSplit = " . print_r($categoriesToSplit, true));
        }
        
        // 5. Llamar al Modelo para procesar los datos
        $result = $this->crossRefModel->processData($baseTable, $verifyTables, $fuseTables, $categoriesToSplit, $splitColumn);

        // 5. Cargar la vista de la aplicación...
        $splitColumnSelected = $_POST['split_column'] ?? null;
        $categories = [];
        if (!empty($splitColumnSelected)) {
            $categories = $this->crossRefModel->getUniqueColumnValues($baseTable, $splitColumnSelected);
        }
        
        $data = [
            'all_tables' => $this->crossRefModel->getAllTables(),
            'base_table_selected' => $baseTable,
            'categories' => $categories,
            'available_columns' => $this->crossRefModel->getTableColumns($baseTable),
            'form_data' => $_SESSION['form_data'],
            'results' => $result,
            'duplicateInfo' => $duplicateInfo // <-- Esto es para el console.log en main_app.php
        ];

        // --- Guardar en sesión para el MAPA ---
        $_SESSION['last_run_data'] = $result['data'] ?? [];
        $_SESSION['last_run_headers'] = $result['headers'] ?? [];
        $_SESSION['base_table_name'] = $baseTable; 
        
        // =================================================================
        // *** INICIO DE LA CORRECCIÓN ***
        // =================================================================
        // 6. (NUEVO) Guardar el reporte de conteos/duplicados en la sesión para el mapa
        $_SESSION['duplicate_info'] = $duplicateInfo;
        // =================================================================
        // *** FIN DE LA CORRECCIÓN ***

        $this->loadView('main_app', $data);
    }
    

    /**
     * Endpoint AJAX para obtener las columnas de una tabla
     */
    public function getTableColumns() {
        header('Content-Type: application/json');
        
        $tableName = $_GET['table'] ?? null;
        if (empty($tableName)) {
            echo json_encode(['error' => 'No se especificó la tabla.']);
            exit;
        }
        
        $columns = $this->crossRefModel->getTableColumns($tableName);
        echo json_encode(['columns' => $columns]);
    }
    
    /**
     * Endpoint AJAX para obtener los valores únicos de una columna
     */
    public function getColumnValues() {
        header('Content-Type: application/json');
        
        $tableName = $_GET['table'] ?? null;
        $columnName = $_GET['column'] ?? null;
        
        if (empty($tableName) || empty($columnName)) {
            echo json_encode(['error' => 'No se especificó la tabla o la columna.']);
            exit;
        }
        
        $values = $this->crossRefModel->getUniqueColumnValues($tableName, $columnName);
        echo json_encode(['values' => $values]);
    }
    
    /**
     * Endpoint AJAX para consolidar filas duplicadas
     */
    public function consolidateRows() {
        header('Content-Type: application/json');
        
        // Obtener datos de la sesión
        $data = $_SESSION['last_run_data'] ?? [];
        $headers = $_SESSION['last_run_headers'] ?? [];
        
        if (empty($data)) {
            echo json_encode(['error' => 'No hay datos para consolidar.']);
            exit;
        }
        
        $result = $this->crossRefModel->consolidateDuplicateRows($data, $headers);
        
        // Actualizar la sesión con los datos consolidados
        $_SESSION['last_run_data'] = $result['data'];
        $_SESSION['last_run_headers'] = $result['headers'];
        
        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'headers' => $result['headers'],
            'original_count' => count($data),
            'consolidated_count' => count($result['data'])
        ]);
    }

    /**
     * Helper para cargar vistas (incluyendo header y footer)
     */
    private function loadView($viewName, $data = []) {
        // Extrae el array $data en variables individuales (ej. $data['all_tables'] se vuelve $all_tables)
        extract($data);
        
        require_once 'view/_parts/header.php';
        require_once 'view/' . $viewName . '.php';
        require_once 'view/_parts/footer.php';
    }
}