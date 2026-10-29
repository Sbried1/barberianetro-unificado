#!/bin/bash

echo "Inicializando Docker..."

if ! sudo systemctl start docker; then
    echo "Error: no se pudo iniciar Docker."
    exit 1
fi

echo "Construyendo y levantando contenedores..."

if ! docker compose up --build -d; then
    echo "Error: Docker Compose no pudo iniciar los contenedores."
    exit 1
fi

echo "Todo listo."