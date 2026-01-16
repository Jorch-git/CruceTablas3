<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App de Cruce de Datos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    
    <?php // --- INICIO DE MODIFICACIÓN --- ?>
    <?php // Cargar Leaflet solo si estamos en la página del mapa ?>
    <?php if (isset($isMapPage) && $isMapPage): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <?php // --- FIN DE MODIFICACIÓN --- ?>
    
</head>
<body>