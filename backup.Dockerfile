FROM alpine:3.19

# Install required packages
RUN apk update && apk add --no-cache \
    bash \
    curl \
    mysql-client \
    gzip \
    busybox-extras \
    tzdata \
    supervisor

# Create backup user and directories
RUN addgroup -g 1000 backup && \
    adduser -D -s /bin/bash -u 1000 -G backup backup && \
    mkdir -p /app/backups /app/scripts /var/log/backup && \
    chown -R backup:backup /app/backups /app/scripts /var/log/backup

# Copy backup scripts
COPY docker-backup/scripts/ /app/scripts/
RUN chmod +x /app/scripts/*.sh && \
    chown -R backup:backup /app/scripts

# Copy and install supervisor configuration
COPY docker-backup/supervisord.conf /etc/supervisor/conf.d/backup.conf

# Set timezone
RUN cp /usr/share/zoneinfo/Europe/Paris /etc/localtime && \
    echo "Europe/Paris" > /etc/timezone

# Setup cron for backup user

# Copy entrypoint
COPY docker-backup/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /app

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD [ -f /var/log/backup/healthcheck ] && [ $(($(date +%s) - $(stat -c %Y /var/log/backup/healthcheck 2>/dev/null || echo 0))) -lt 7200 ] || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n"]