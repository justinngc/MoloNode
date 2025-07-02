FROM php:8.1-fpm

# Install procps package (includes top, free, ps, etc.)
RUN apt-get update && \
    apt-get install -y procps jq cron nano && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

