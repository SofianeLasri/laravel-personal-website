#!/bin/bash

# Backup scheduler script
# Replaces cron with a simple loop-based scheduler for containers

set -uo pipefail  # Removed -e temporarily for debugging

LOG_FILE="/var/log/backup/backup.log"
SCRIPTS_DIR="/app/scripts"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"
touch "$LOG_FILE"

# Configuration from environment variables
BACKUP_SCHEDULE="${BACKUP_SCHEDULE:-0 2 * * *}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Function to log messages
log_message() {
    local message="$(date '+%Y-%m-%d %H:%M:%S') - SCHEDULER: $1"
    echo "$message" | tee -a "$LOG_FILE" 2>/dev/null || echo "$message"
}

# Function to parse cron schedule (simplified)
parse_cron_schedule() {
    local schedule="$1"
    log_message "DEBUG: Parsing schedule: '$schedule'"

    # Extract hour and minute from "minute hour * * *" format
    BACKUP_MINUTE=$(echo "$schedule" | cut -d' ' -f1 | tr -d '"' | tr -d "'")
    BACKUP_HOUR=$(echo "$schedule" | cut -d' ' -f2 | tr -d '"' | tr -d "'")

    log_message "DEBUG: Extracted MINUTE=$BACKUP_MINUTE, HOUR=$BACKUP_HOUR"

    # Validation
    if [[ ! "$BACKUP_MINUTE" =~ ^[0-9]+$ ]] || [[ ! "$BACKUP_HOUR" =~ ^[0-9]+$ ]]; then
        log_message "ERROR: Invalid cron schedule format: '$schedule'"
        log_message "ERROR: MINUTE='$BACKUP_MINUTE', HOUR='$BACKUP_HOUR'"
        return 1
    fi

    # Ensure values are in valid range
    if [ "$BACKUP_MINUTE" -gt 59 ] || [ "$BACKUP_HOUR" -gt 23 ]; then
        log_message "ERROR: Invalid time values - MINUTE=$BACKUP_MINUTE, HOUR=$BACKUP_HOUR"
        return 1
    fi

    log_message "INFO: Successfully parsed schedule: ${BACKUP_HOUR}:$(printf '%02d' "$BACKUP_MINUTE")"
}

# Parse the backup schedule
log_message "Starting backup scheduler initialization..."
if ! parse_cron_schedule "$BACKUP_SCHEDULE"; then
    log_message "FATAL: Failed to parse backup schedule, exiting"
    exit 1
fi

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
    if [ $((10#$current_minute % 5)) -eq 0 ] && [ "$last_healthcheck_minute" != "$current_minute" ]; then
        if touch /var/log/backup/healthcheck 2>/dev/null; then
            last_healthcheck_minute="$current_minute"
        else
            log_message "WARNING: Failed to update health check file"
        fi
    fi

    # Sleep for 30 seconds before next check
    sleep 30
done