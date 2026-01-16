<?php
require_once 'model/Auth.php';
require_once 'model/MapModel.php'; // Nuestro nuevo modelo

class MapController {

    private $auth;
    private $mapModel;

    public function __construct() {
        $this->auth = new Auth();
        $this->auth->checkAuth(); // Proteger este controlador
        
        $this->mapModel = new MapModel();
    }

    /**
     * Muestra la página del mapa
     */
    public function index() {
        // 1. Verificar si hay datos en la sesión
        if (empty($_SESSION['last_run_data'])) {
            $_SESSION['error'] = "No hay datos de cruce para mostrar en el mapa. Por favor, genera un cruce primero.";
            header('Location: index.php?route=main/index');
            exit;
        }

        // 2. Obtener datos de la sesión
        $resultData = $_SESSION['last_run_data'];
        $resultHeaders = $_SESSION['last_run_headers'];
        $baseTableName = $_SESSION['base_table_name'] ?? 'Resultados';

        // 3. Enriquecer los datos con coordenadas (usando el nuevo modelo)
        $mapDataResult = $this->mapModel->enrichDataWithCoordinates($resultData);
        $enrichedData = $mapDataResult['data'];
        
        if ($mapDataResult['coordenadas_cargadas'] === false) {
            // Error: No se pudo cargar la tabla de coordenadas
            $_SESSION['error'] = "Error: No se pudo cargar la tabla 'coordenadas' de la base de datos. El mapa no puede generarse.";
            header('Location: index.php?route=main/index');
            exit;
        }

        // 4. Obtener la lista de criterios (basado en las cabeceras del cruce)
        // ¡Esta es la parte clave que cumple tu requisito!
        $criteriaList = $this->mapModel->getCriteriaFromHeaders($resultHeaders);
        
        // 5. Obtener lista de entidades (del resultado enriquecido)
        $entidades = [];
        if (!empty($enrichedData)) {
            $entidades = array_unique(array_column($enrichedData, 'entidad_ok'));
            sort($entidades);
        }

        // 6. Preparar datos para la vista
        $data = [
            'base_table_name' => $baseTableName,
            'map_data_json' => json_encode($enrichedData), // Convertir a JSON para JS
            'criteria_list_json' => json_encode($criteriaList), // Convertir a JSON para JS
            'entidades' => $entidades,
            
            // =================================================================
            // *** INICIO DE LA CORRECCIÓN ***
            // =================================================================
            // 6b. (NUEVO) Pasar los datos de conteo/duplicados a la vista del mapa
            'duplicate_info_json' => json_encode($_SESSION['duplicate_info'] ?? [])
            // =================================================================
            // *** FIN DE LA CORRECCIÓN ***
        ];

        // 7. Cargar la vista del mapa
        $this->loadView('map_view', $data);
    }

    /**
     * Helper para cargar vistas (incluyendo header y footer)
     */
    private function loadView($viewName, $data = []) {
        extract($data);
        
        // Pasar una variable a 'header' para que sepa que es la página del mapa
        $isMapPage = true; 
        require_once 'view/_parts/header.php';
        require_once 'view/' . $viewName . '.php';
        require_once 'view/_parts/footer.php';
    }
}