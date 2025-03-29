ARG COMPOSER_VERSION="2.8.4"

FROM composer:${COMPOSER_VERSION} AS composer
FROM php:8.3-cli-alpine AS builder

# Define build arguments for target platform
ARG TARGET_OS="linux"
ARG TARGET_ARCH="x86_64"
ARG VERSION="latest"

ENV COMPOSER_ALLOW_SUPERUSER=1

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
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --prefer-dist --ignore-platform-reqs

# Create build directories
RUN mkdir -p .build/phar .build/bin

# Download box tool for PHAR creation
RUN wget -O .build/bin/box "https://github.com/box-project/box/releases/download/4.6.6/box.phar"
RUN chmod +x .build/bin/box

# Download static-php-cli tool based on target OS and architecture
RUN wget -O .build/bin/spc "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${TARGET_OS}-${TARGET_ARCH}" \
    && chmod +x .build/bin/spc

# Download required PHP extensions
RUN .build/bin/spc download micro \
    --for-extensions=ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter,curl \
    --with-php=8.3 \
    --prefer-pre-built

# Install UPX for compression
RUN .build/bin/spc install-pkg upx

# Verify environment is ready
RUN .build/bin/spc doctor --auto-fix

# Build the self-executable binary with required extensions
RUN .build/bin/spc build ctype,dom,filter,libxml,mbstring,phar,simplexml,sockets,tokenizer,xml,xmlwriter,curl \
    --build-micro \
    --with-upx-pack

# Build PHAR file
RUN .build/bin/box compile -v

# Combine micro.sfx with the PHAR to create the final binary
RUN .build/bin/spc micro:combine .build/phar/ctx.phar --output=.build/bin/ctx
RUN chmod +x .build/bin/ctx

# Copy to output with appropriate naming including version
RUN mkdir -p /.output
RUN cp .build/bin/ctx /.output/ctx-${TARGET_OS}-${TARGET_ARCH}-${VERSION}

# Set default entrypoint (without version in name)
ENTRYPOINT ["/.output/ctx-${TARGET_OS}-${TARGET_ARCH}-${VERSION}"]