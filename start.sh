#!/bin/bash

echo "🔪 Matando procesos en puertos 8001 y 3535..."
sudo kill -9 $(sudo lsof -t -i:8001,3535) 2>/dev/null

echo "🧹 Limpiando Redis cache..."
redis-cli FLUSHDB 2>/dev/null

echo "🚀 Iniciando Backend (puerto 8000)..."
cd backend && nohup php artisan serve --host=0.0.0.0 --port=8000 > /dev/null 2>&1 &

echo "🚀 Iniciando Frontend (puerto 3535)..."
cd frontend && nohup npm run dev > /dev/null 2>&1 &

sleep 3

echo ""
echo "✅ Servicios iniciados:"
echo "   Backend:  http://localhost:8000"
echo "   Frontend: http://localhost:3535"
