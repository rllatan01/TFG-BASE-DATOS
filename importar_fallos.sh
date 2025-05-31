#!/bin/bash
# Script para importar archivos fallos_parte_XX.sql en orden

DB_USER="tu_usuario"
DB_PASS="tu_contraseña"
DB_NAME="coches"

for file in fallos_parte_*.sql; do
    echo "Importando $file..."
    mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$file"
    if [ $? -ne 0 ]; then
        echo "Error al importar $file"
        exit 1
    fi
done

echo "Importación completa."
