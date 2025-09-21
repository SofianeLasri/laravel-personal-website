#!/bin/bash

# Backup scheduler script
# Replaces cron with a simple loop-based scheduler for containers

set -euo pipefail

LOG_FILE="/var/log/backup/backup.log"
SCRIPTS_DIR="/app/scripts"

# Configuration from environment variables
BACKUP_SCHEDULE="${BACKUP_SCHEDULE:-0 2 * * *}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - SCHEDULER: $1" | tee -a "$LOG_FILE"
}

# Function to parse cron schedule (simplified)
parse_cron_schedule() {
    local schedule="$1"
    # Extract hour and minute from "minute hour * * *" format
    BACKUP_MINUTE=$(echo "$schedule" | cut -d' ' -f1)
    BACKUP_HOUR=$(echo "$schedule" | cut -d' ' -f2)
}

# Parse the backup schedule
parse_cron_schedule "$BACKUP_SCHEDULE"

log_message "Backup scheduler starting..."
log_message "Backup scheduled at: ${BACKUP_HOUR}:${BACKUP_MINUTE} daily"
log_message "Cleanup scheduled: Sunday at 03:00"
log_message "Health check: every 5 minutes"

# Keep track of last execution to avoid duplicates
last_backup_day=""
last_cleanup_week=""
last_healthcheck_minute=""

# Main scheduling loop
while true; do
    current_minute=$(date '+%M')
    current_hour=$(date '+%H')
    current_day=$(date '+%Y-%m-%d')
    current_weekday=$(date '+%u')  # 1=Monday, 7=Sunday
    current_week=$(date '+%Y-W%U')

    # Backup check (daily at configured time)
    if [ "$current_hour" = "$(printf "%02d" "$BACKUP_HOUR")" ] &&
       [ "$current_minute" = "$(printf "%02d" "$BACKUP_MINUTE")" ] &&
       [ "$last_backup_day" != "$current_day" ]; then

        log_message "Starting scheduled backup..."
        last_backup_day="$current_day"

        if "$SCRIPTS_DIR/run-backup.sh"; then
            log_message "Scheduled backup completed successfully"
        else
            log_message "ERROR: Scheduled backup failed"
        fi
    fi

    # Cleanup check (weekly on Sunday at 3 AM)
    if [ "$current_weekday" = "7" ] &&
       [ "$current_hour" = "03" ] &&
       [ "$current_minute" = "00" ] &&
       [ "$last_cleanup_week" != "$current_week" ]; then

        log_message "Starting scheduled cleanup..."
        last_cleanup_week="$current_week"

        if "$SCRIPTS_DIR/cleanup-old-backups.sh"; then
            log_message "Scheduled cleanup completed successfully"
        else
            log_message "ERROR: Scheduled cleanup failed"
        fi
    fi

    # Health check update (every 5 minutes)
    minute_mod_5=$((10#$current_minute % 5))
    if [ "$minute_mod_5" -eq 0 ] && [ "$last_healthcheck_minute" != "$current_minute" ]; then
        touch /var/log/backup/healthcheck
        last_healthcheck_minute="$current_minute"
    fi

    # Sleep for 30 seconds before next check
    sleep 30
done