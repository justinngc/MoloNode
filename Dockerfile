FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Install required packages
RUN apt-get update && apt-get install -y \
    apt-utils \
    transmission-daemon \
    jq \
    php php-fpm php-cli php-mbstring php-xml php-curl php-zip \
    nginx \
    cron \        
    procps \
    curl unzip git supervisor nano \
    debian-keyring debian-archive-keyring gnupg && \
    mkdir -p /etc/apt/keyrings && \
    curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key | \
        gpg --dearmor -o /etc/apt/keyrings/caddy-stable-archive-keyring.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/caddy-stable-archive-keyring.gpg] https://dl.cloudsmith.io/public/caddy/stable/deb/debian any-version main" \
        > /etc/apt/sources.list.d/caddy-stable.list && \
    apt-get update && apt-get install -y caddy

# Create needed directories
RUN mkdir -p /var/www/html/files

# Set working directory
WORKDIR /var/www/html

# ✅ Copy application and scripts (including .sh files)
COPY app/ /var/www/html/

# ✅ Make scripts executable (AFTER they are copied)
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod +x /var/www/html/requests/measure-bandwidth.sh && \
    chmod +x /var/www/html/requests/yabs.sh

# Copy nginx and init files
COPY nginx/default.conf /etc/nginx/sites-enabled/default
COPY scripts/init.sh /init.sh

# Make init executable
RUN chmod +x /init.sh

# Launch init
CMD ["/init.sh"]
