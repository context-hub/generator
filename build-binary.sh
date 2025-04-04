#!/bin/bash
set -e

echo "Building Context Generator Docker image..."
docker build -t context-generator .

rm -rf .output
mkdir -p .output

echo "Extracting build artifacts..."
CONTAINER_ID=$(docker create context-generator)
docker cp $CONTAINER_ID:/.output/ctx ./.output/ctx
docker rm $CONTAINER_ID

echo "Build complete! Artifacts available in ./output directory:"
ls -lh ./.output/

echo "You can run the executable with:"
echo "./.output/ctx"