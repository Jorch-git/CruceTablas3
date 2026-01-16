<?php
require_once 'model/Database.php';

class MapModel {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /**
     * Carga la tabla de coordenadas y la prepara como un mapa de búsqueda.
     */
    private function getCoordinatesLookup() {
        $lookupMap = [];
        // Asumimos que la tabla se llama 'coordenadas'
        $sql = "SELECT entidad_ok, municipio_ok, latitud, longitud FROM coordenadas";
        $result = $this->conn->query($sql);

        if (!$result || $result->num_rows === 0) {
            // Si la tabla no existe o falla, devuelve un mapa vacío
            return [];
        }

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['entidad_ok']) && !empty($row['municipio_ok']) && !empty($row['latitud']) && !empty($row['longitud'])) {
                // Crear la clave_limpia
                $clave = strtoupper(trim($row['entidad_ok'])) . '|' . strtoupper(trim($row['municipio_ok']));
                
                // Evitar duplicados, solo guardar el primero que encuentre
                if (!isset($lookupMap[$clave])) {
                     $lookupMap[$clave] = [
                        'latitud' => (float)$row['latitud'],
                        'longitud' => (float)$row['longitud']
                    ];
                }
            }
        }
        return $lookupMap;
    }

    /**
     * Toma los datos de resultados (de la sesión) y les añade latitud/longitud
     * desde la tabla 'coordenadas'.
     */
    public function enrichDataWithCoordinates($resultData) {
        $coordinatesMap = $this->getCoordinatesLookup();
        if (empty($coordinatesMap)) {
            // No se pudo cargar la tabla de coordenadas
            return ['data' => [], 'coordenadas_cargadas' => false];
        }

        $enrichedData = [];
        foreach ($resultData as $row) {
            // Re-crear la clave_limpia para esta fila de resultados
            if (isset($row['entidad_ok']) && isset($row['municipio_ok'])) {
                $clave = strtoupper(trim($row['entidad_ok'])) . '|' . strtoupper(trim($row['municipio_ok']));
                
                // Buscar en el mapa de coordenadas
                if (isset($coordinatesMap[$clave])) {
                    // ¡Éxito! Añadir lat/lng a la fila
                    $row['latitud'] = $coordinatesMap[$clave]['latitud'];
                    $row['longitud'] = $coordinatesMap[$clave]['longitud'];
                    $enrichedData[] = $row;
                }
                // Si no se encuentra la clave en coordenadas, la fila simplemente se omite del mapa.
            }
        }
        
        return ['data' => $enrichedData, 'coordenadas_cargadas' => true];
    }

    /**
     * Filtra las cabeceras para obtener solo las que son "criterios"
     * Solo incluye las columnas de verificación (en_*) que son las del cruce
     * Excluye: columnas de la tabla origen, columnas fusionadas, y otras columnas base
     */
    public function getCriteriaFromHeaders($headers) {
        $criteria = [];
        // Columnas base que NO son criterios de "Sí/No"
        $ignore_cols = [
            'entidad_ok', 'municipio_ok', 'clave_limpia', 'latitud', 'longitud', 
            'categoria', 'id', // Añade cualquier otra columna base que no sea un criterio
            'entidad', 'municipio' // Nombres originales si es que existen
        ];

        foreach ($headers as $header) {
            $isIgnored = false;
            
            // Ignorar columnas base
            foreach ($ignore_cols as $ignore) {
                if (strtolower($header) === strtolower($ignore)) {
                    $isIgnored = true;
                    break;
                }
            }
            
            // Ignorar columnas fusionadas (tienen formato: columna__tabla)
            if (strpos($header, '__') !== false) {
                $isIgnored = true;
            }
            
            // Ignorar columnas de categorías separadas (no empiezan con 'en_')
            // Solo incluir columnas de verificación que empiezan con 'en_'
            if (!$isIgnored && strpos(strtolower($header), 'en_') === 0) {
                // Esta es una columna de verificación del cruce
                $criteria[] = $header;
            }
        }
        return $criteria;
    }
}