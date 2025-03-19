FROM php:8.3-cli-alpine AS builder

# Install required packages
RUN apk add --no-cache \
    wget \
    git \
    unzip \
    upx \
    bash

# Set working directory
WORKDIR /app

# Copy source code
COPY . .

# Create build directories
RUN mkdir -p .build/phar .build/bin

# Download box tool for PHAR creation
RUN wget -O .build/bin/box "https://github.com/box-project/box/releases/download/4.6.6/box.phar"
RUN chmod +x .build/bin/box

# Download static-php-cli tool
RUN wget -O .build/bin/spc "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64"
RUN chmod +x .build/bin/spc

# Prepare environment for PHP micro
RUN .build/bin/spc dump-extensions ./ --format=text

# Download required PHP extensions
RUN .build/bin/spc download micro \
    --for-extensions=ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter \
    --with-php=8.3 \
    --prefer-pre-built

# Install UPX for compression
RUN .build/bin/spc install-pkg upx

# Verify environment is ready
RUN .build/bin/spc doctor --auto-fix

# Build the self-executable binary with required extensions
RUN .build/bin/spc build ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter \
    --build-micro \
    --with-upx-pack

# Build PHAR file
RUN .build/bin/box compile -v

# Combine micro.sfx with the PHAR to create the final binary
RUN .build/bin/spc micro:combine .build/phar/ctx.phar --output=.build/bin/ctx
RUN chmod +x .build/bin/ctx

RUN cp .build/bin/ctx /.output

ENTRYPOINT ["/.output/ctx"]