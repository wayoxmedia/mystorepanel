#!/bin/bash

# Rutas
PHP="/opt/php82/bin/php"
COMPOSER="/usr/local/bin/composer"

# Logs
LOG_DIR="./logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/composer-$(date '+%Y-%m').log"

# Validación de argumentos
if [ -z "$1" ]; then
    echo "❌ Por favor, especifica un comando para Composer (install, update, require, etc)." | tee -a "$LOG_FILE"
    echo "Uso: $0 <comando> [argumentos...]" | tee -a "$LOG_FILE"
    exit 1
fi

# Comando principal
COMMAND="$1"
shift

# Construcción del comando
if [ "$COMMAND" == "install" ]; then
    CMD="$PHP $COMPOSER install --no-dev --optimize-autoloader --no-interaction $*"
else
    CMD="$PHP $COMPOSER $COMMAND $*"
fi

# Imprimir y loggear encabezado
{
    echo ""
    echo "======================================="
    echo "🕒 $(date '+%Y-%m-%d %H:%M:%S')"
    echo "📦 Ejecutando: $CMD"
    echo "---------------------------------------"
} | tee -a "$LOG_FILE"

# Ejecutar y loggear la salida
$CMD 2>&1 | tee -a "$LOG_FILE"
