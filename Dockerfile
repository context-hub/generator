ARG COMPOSER_VERSION="2.8.4"

FROM ghcr.io/context-hub/docker-ctx-binary/bin-builder:latest AS builder

# Define build arguments for target platform
ARG TARGET_OS="linux"
ARG TARGET_ARCH="x86_64"
ARG VERSION="latest"

WORKDIR /app

# Copy source code
COPY . .
RUN rm -f composer.lock
RUN rm -rf vendor

RUN composer install --no-dev --ignore-platform-reqs

# Create build directories
RUN mkdir -p .build/phar .build/bin

# Build PHAR file
RUN /usr/local/bin/box compile
RUN /usr/local/bin/box info .build/phar/ctx.phar --ansi

RUN mkdir -p ./buildroot/bin
RUN cp /build-tools/build/bin/micro.sfx ./buildroot/bin
# Combine micro.sfx with the PHAR to create the final binary
RUN /build-tools/static-php-cli/bin/spc micro:combine .build/phar/ctx.phar --output=.build/bin/ctx
RUN chmod +x .build/bin/ctx

# Copy to output with appropriate naming including version
RUN mkdir -p /.output
RUN cp .build/bin/ctx /.output/ctx

# Set default entrypoint (without version in name)
ENTRYPOINT ["/.output/ctx"]