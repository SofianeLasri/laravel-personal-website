#!/bin/bash

# Backup service entrypoint script
# Sets up cron jobs and initializes the backup service

set -euo pipefail

LOG_FILE="/var/log/backup/backup.log"
CRON_SCHEDULE="${BACKUP_SCHEDULE:-0 2 * * *}"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ENTRYPOINT: $1" | tee -a "$LOG_FILE"
}

# Ensure log directory exists and is writable
mkdir -p /var/log/backup
touch "$LOG_FILE"

log_message "Backup service starting..."
log_message "Cron schedule: $CRON_SCHEDULE"

# Validate environment variables
if [ -z "${DB_HOST:-}" ]; then
    log_message "WARNING: DB_HOST not set, using default 'app'"
fi

if [ -n "${BACKUP_FTP_HOST:-}" ]; then
    log_message "FTP backup enabled for host: ${BACKUP_FTP_HOST}"
else
    log_message "FTP backup disabled (no BACKUP_FTP_HOST set)"
fi

log_message "Retention period: ${BACKUP_RETENTION_DAYS:-30} days"

# Cron jobs are pre-installed in /etc/cron.d/backup
log_message "Cron jobs configured:"
if [ -f /etc/cron.d/backup ]; then
    grep -v '^#' /etc/cron.d/backup | grep -v '^$' | while read -r line; do
        log_message "  $line"
    done
else
    log_message "  WARNING: No cron jobs found in /etc/cron.d/backup"
fi

# Initial health check
touch /var/log/backup/healthcheck

# Test backup scripts permissions
SCRIPTS_DIR="/app/scripts"
for script in "$SCRIPTS_DIR"/*.sh; do
    if [ -f "$script" ]; then
        if [ ! -x "$script" ]; then
            log_message "Making script executable: $(basename "$script")"
            chmod +x "$script"
        fi
    fi
done

# Run initial backup if requested
if [ "${RUN_INITIAL_BACKUP:-false}" = "true" ]; then
    log_message "Running initial backup..."
    if /app/scripts/run-backup.sh; then
        log_message "Initial backup completed successfully"
    else
        log_message "WARNING: Initial backup failed"
    fi
fi

log_message "Backup service initialized successfully"
log_message "Starting cron daemon..."

# Execute the main command (usually crond)
exec "$@"