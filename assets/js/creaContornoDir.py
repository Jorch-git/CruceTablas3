import geopandas as gpd
from shapely.geometry import Polygon, MultiPolygon, MultiLineString
from pathlib import Path
import sys

def crear_contorno_estado(input_path, output_path):
    """
    Lee un archivo GeoJSON de municipios, los une para formar un estado
    y guarda un nuevo GeoJSON solo con los bordes exteriores e interiores.
    """
    try:
        # 1. Cargar el archivo GeoJSON
        gdf = gpd.read_file(input_path)
        
        # Verificar que no esté vacío
        if gdf.empty:
            print(f"⚠️  Omitiendo (archivo vacío): {input_path.name}")
            return

        # 2. Unir (disolver) todos los polígonos en una sola geometría
        estado_unido = gdf.unary_union

        # 3. Extraer todas las líneas de borde (exterior e interiores/islas)
        bordes = []

        if estado_unido.geom_type == 'Polygon':
            bordes.append(estado_unido.exterior)
            for interior in estado_unido.interiors:
                bordes.append(interior)
                
        elif estado_unido.geom_type == 'MultiPolygon':
            for poly in estado_unido.geoms:
                bordes.append(poly.exterior)
                for interior in poly.interiors:
                    bordes.append(interior)

        # 4. Crear un nuevo GeoDataFrame con los bordes
        bordes_multilinea = MultiLineString(bordes)
        output_gdf = gpd.GeoDataFrame(geometry=[bordes_multilinea], crs=gdf.crs)

        # 5. Guardar el nuevo archivo GeoJSON
        output_gdf.to_file(output_path, driver="GeoJSON")
        print(f"✅ Procesado: {input_path.name} -> {output_path.name}")

    except Exception as e:
        print(f"❌ Error procesando {input_path.name}: {e}")

def main(directory_path):
    """
    Función principal para procesar todos los archivos JSON en un directorio.
    """
    input_dir = Path(directory_path)

    if not input_dir.exists():
        print(f"Error: El directorio no existe: {directory_path}")
        return
    if not input_dir.is_dir():
        print(f"Error: La ruta no es un directorio: {directory_path}")
        return

    print(f"--- 🗺️  Iniciando procesamiento de contornos en: {input_dir} ---")

    file_count = 0
    # Usar .glob('*.json') para obtener solo archivos JSON en el directorio actual
    for file_path in input_dir.glob('*.json'):
        
        # Omitir archivos que ya son contornos
        if file_path.name.startswith('CONTORNO_'):
            continue

        # Crear el nombre del archivo de salida
        output_name = f"CONTORNO_{file_path.name}"
        output_path = file_path.with_name(output_name) # Pone el archivo en el mismo dir
        
        # Procesar el archivo
        crear_contorno_estado(file_path, output_path)
        file_count += 1
        
    print(f"--- ✨ Proceso completado. {file_count} archivos procesados. ---")

# --- Punto de entrada del script ---
if __name__ == "__main__":
    
    # 1. Verificar si se pasó un argumento (la ruta del directorio)
    if len(sys.argv) < 2:
        print("❌ Error: Debes proporcionar la ruta al directorio de entidades.")
        print("Uso: python crear_contornos.py ruta/a/tu/carpeta")
        print("Ejemplo: python crear_contornos.py assets/js/entidades")
    else:
        # 2. Tomar la ruta desde el argumento de la línea de comandos
        directorio = sys.argv[1]
        main(directorio)