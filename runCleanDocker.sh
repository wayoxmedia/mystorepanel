#!/bin/bash

echo "🧼 Docker Maintenance Script"
echo "============================"
echo "1️⃣  Clean everything (containers, images, volumes, networks)"
echo "2️⃣  Clean and build (no cache)"
echo "3️⃣  Clean, build and up (no cache + run containers)"
echo "4️⃣  Build and up (without cleaning)"
echo "5️⃣  Exit"
echo ""

read -p "❓ What do you want to do? [1-5] " option

case $option in
  1)
    echo ""
    echo "⚠️  WARNING: This will remove ALL Docker containers, images, volumes, and networks."
    read -p "❓ Are you sure you want to continue? [y/N] " confirm1
    if [[ "$confirm1" =~ ^[Yy]$ ]]; then
      docker system prune -a --volumes -f
      echo "✅ Docker environment cleaned."
    else
      echo "❌ Operation canceled."
    fi
    ;;
  2)
    echo ""
    echo "⚠️  This will clean Docker and rebuild the containers from scratch."
    read -p "❓ Proceed? [y/N] " confirm2
    if [[ "$confirm2" =~ ^[Yy]$ ]]; then
      docker system prune -a --volumes -f
      docker compose --env-file .env build --no-cache
      echo "✅ Build complete."
    else
      echo "❌ Operation canceled."
    fi
    ;;
  3)
    echo ""
    echo "⚠️  This will clean, rebuild, and start the containers."
    read -p "❓ Proceed? [y/N] " confirm3
    if [[ "$confirm3" =~ ^[Yy]$ ]]; then
      docker system prune -a --volumes -f
      docker compose --env-file .env build --no-cache
      docker compose --env-file .env up -d
      echo "✅ Containers are up and running."
      docker ps
    else
      echo "❌ Operation canceled."
    fi
    ;;
  4)
    echo ""
    echo "⚠️  This will build (with cache) and start the containers."
    read -p "❓ Proceed? [y/N] " confirm4
    if [[ "$confirm4" =~ ^[Yy]$ ]]; then
      docker compose --env-file .env build
      docker compose --env-file .env up -d
      echo "✅ Containers are up and running."
    else
      echo "❌ Operation canceled."
    fi
    ;;
  5)
    echo "👋 Bye!"
    exit 0
    ;;
  *)
    echo "❌ Invalid option."
    ;;
esac
