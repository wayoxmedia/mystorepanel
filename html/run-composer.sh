#!/bin/bash
## WARNING
## This script is intended to be run from the command line and in PROD env.
## It is not intended to be run local.
## It is not intended to be run in a Docker container.
# Routes
PHP="/opt/php82/bin/php"
COMPOSER="/usr/local/bin/composer"

# Logs
LOG_DIR="./logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/composer-$(date '+%Y-%m').log"

# Args Validation
if [ -z "$1" ]; then
    echo "❌ Por favor, especifica un comando para Composer (install, update, require, etc)." | tee -a "$LOG_FILE"
    echo "Uso: $0 <comando> [argumentos...]" | tee -a "$LOG_FILE"
    exit 1
fi

# Main Command
COMMAND="$1"
shift

# Build command
if [ "$COMMAND" == "install" ]; then
    CMD="$PHP $COMPOSER install --no-dev --optimize-autoloader --no-interaction $*"
else
    CMD="$PHP $COMPOSER $COMMAND $*"
fi

# Print & log header
{
    echo ""
    echo "======================================="
    echo "🕒 $(date '+%Y-%m-%d %H:%M:%S')"
    echo "📦 Ejecutando: $CMD"
    echo "---------------------------------------"
} | tee -a "$LOG_FILE"

# Run & log output
$CMD 2>&1 | tee -a "$LOG_FILE"
