#!/bin/bash
set -e

echo "Building Context Generator Docker image..."
docker build -t context-generator .

rm -rf .build/ctx

echo "Extracting build artifacts..."
CONTAINER_ID=$(docker create context-generator)
docker cp $CONTAINER_ID:/.output/ctx ./.build/ctx
docker cp $CONTAINER_ID:/app/.build/phar/ctx.phar ./.build/phar/ctx.phar
docker rm $CONTAINER_ID

echo "Build complete! Artifacts available in ./output directory:"
ls -lh ./.build/
chmod +x ./.build/ctx
chmod +x ./.build/phar/ctx.phar

echo "You can run the executable with:"
echo "./.build/ctx"