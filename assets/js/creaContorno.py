import geopandas as gpd
from shapely.geometry import Polygon, MultiPolygon, MultiLineString
from pathlib import Path

def crear_contorno_estado(input_path, output_path):
    """
    Lee un archivo GeoJSON de municipios, los une para formar un estado
    y guarda un nuevo GeoJSON solo con los bordes exteriores e interiores.
    """
    try:
        # 1. Cargar el archivo GeoJSON
        gdf = gpd.read_file(input_path)
    except Exception as e:
        print(f"❌ Error leyendo el archivo: {input_path}")
        print(f"Detalle: {e}")
        return

    # 2. Unir (disolver) todos los polígonos en una sola geometría
    # Esto "derrite" todas las líneas internas de los municipios
    try:
        estado_unido = gdf.unary_union
    except Exception as e:
        print(f"❌ Error al unir las geometrías. Verifica que el JSON sea válido.")
        print(f"Detalle: {e}")
        return

    # 3. Extraer todas las líneas de borde (exterior e interiores/islas)
    bordes = []

    # La geometría unida puede ser un Polígono (si no tiene islas)
    # o un MultiPolígono (si tiene islas, como Baja California Sur)
    
    if estado_unido.geom_type == 'Polygon':
        # Añadir el borde exterior
        bordes.append(estado_unido.exterior)
        # Añadir todos los bordes de las "islas" o "agujeros"
        for interior in estado_unido.interiors:
            bordes.append(interior)
            
    elif estado_unido.geom_type == 'MultiPolygon':
        # Si es un MultiPolígono, iterar sobre cada polígono que lo compone
        for poly in estado_unido.geoms:
            bordes.append(poly.exterior)
            # Añadir los agujeros de cada polígono
            for interior in poly.interiors:
                bordes.append(interior)

    # 4. Crear un nuevo GeoDataFrame con los bordes
    # Usamos MultiLineString para guardar todas las líneas en una sola fila
    bordes_multilinea = MultiLineString(bordes)
    
    # Crear el GeoDataFrame final
    output_gdf = gpd.GeoDataFrame(geometry=[bordes_multilinea], crs=gdf.crs)

    # 5. Guardar el nuevo archivo GeoJSON
    try:
        output_gdf.to_file(output_path, driver="GeoJSON")
        print(f"✅ ¡Éxito! Contorno guardado en: {output_path}")
    except Exception as e:
        print(f"❌ Error al guardar el archivo: {output_path}")
        print(f"Detalle: {e}")

# --- Configuración para ejecutar el script ---
if __name__ == "__main__":
    
    # Define la carpeta de entrada y salida
    # (Asume que este script está en la misma carpeta que 'assets')
    carpeta_base = Path("")
    
    # Nombre del archivo de entrada
    nombre_entidad = "AGUASCALIENTES.json"
    
    # Nombre del archivo de salida
    nombre_salida = "CONTORNO_AGUASCALIENTES.json"

    # Rutas completas
    ruta_input = carpeta_base / nombre_entidad
    ruta_output = carpeta_base / nombre_salida

    # Ejecutar la función
    crear_contorno_estado(ruta_input, ruta_output)

    # --- Ejemplo para AGUASCALIENTES ---
    # Descomenta las siguientes líneas para procesar otro estado
    
    # ruta_input_ags = carpeta_base / "AGUASCALIENTES.json"
    # ruta_output_ags = carpeta_base / "CONTORNO_AGUASCALIENTES.json"
    # crear_contorno_estado(ruta_input_ags, ruta_output_ags)