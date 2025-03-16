#!/bin/bash
set -eu

# Initialize working directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "📦 Building Context Generator executable..."

# Step 1: Clean up previous builds and prepare directories
mkdir -p .build/phar .build/bin

# Step 2: Download box tool for PHAR creation if not exists
if [ ! -x .build/bin/box ]; then
    echo "📥 Downloading box PHAR builder..."
    wget -O .build/bin/box "https://github.com/box-project/box/releases/download/4.6.6/box.phar"
    chmod +x .build/bin/box
fi

# Step 3: Download phpmicro static-php-cli tool if not exists
if [ ! -x .build/bin/spc ]; then
    echo "📥 Downloading static-php-cli..."
    wget -O .build/bin/spc "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64"
    chmod +x .build/bin/spc
fi

# Step 4: Prepare environment for PHP micro
echo "🔍 Preparing PHP micro dependencies..."
.build/bin/spc dump-extensions ./ --format=text

# Step 5: Download required PHP extensions
echo "🔍 Downloading PHP micro dependencies..."
.build/bin/spc download micro \
    --for-extensions=ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter \
    --with-php=8.3 \
    --prefer-pre-built

# Step 6: Install UPX for compression
.build/bin/spc install-pkg upx

# Step 7: Verify environment is ready
.build/bin/spc doctor --auto-fix

# Step 8: Build the self-executable binary with required extensions
echo "🔨 Building self-executable binary..."
.build/bin/spc build ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter \
    --build-micro \
    --with-upx-pack \
    --debug

# Step 9: Build PHAR file
echo "🔨 Building PHAR file..."
.build/bin/box compile -v

# Step 10: Combine micro.sfx with the PHAR to create the final binary
echo "🔄 Creating final executable..."
.build/bin/spc micro:combine .build/phar/ctx.phar --output=.build/bin/ctx

# Make the binary executable
chmod +x .build/bin/ctx

echo "✅ Build complete!"
echo "📁 Executable available at: .build/bin/ctx"
echo "📁 PHAR available at: .build/phar/ctx.phar"