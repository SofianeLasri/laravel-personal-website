#!/bin/bash

# Cleanup script for old database backups
# Removes local and remote backups older than specified retention period

set -euo pipefail

# Configuration from environment variables
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
FTP_HOST="${BACKUP_FTP_HOST:-}"
FTP_USERNAME="${BACKUP_FTP_USERNAME:-}"
FTP_PASSWORD="${BACKUP_FTP_PASSWORD:-}"
FTP_PATH="${BACKUP_FTP_PATH:-/backups}"
FTP_PORT="${BACKUP_FTP_PORT:-21}"

BACKUP_DIR="/app/backups"
LOG_FILE="/var/log/backup/backup.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - CLEANUP: $1" | tee -a "$LOG_FILE"
}

# Function to handle errors
handle_error() {
    log_message "ERROR: $1"
    exit 1
}

log_message "Starting cleanup process..."
log_message "Retention period: $RETENTION_DAYS days"

# Cleanup local backups
log_message "Cleaning up local backups older than $RETENTION_DAYS days..."

LOCAL_CLEANED=0
if [ -d "$BACKUP_DIR" ]; then
    while IFS= read -r -d '' backup_dir; do
        backup_name=$(basename "$backup_dir")

        # Extract date from backup name (format: YYYY-MM-DD_HH-MM-SS)
        if [[ $backup_name =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$ ]]; then
            backup_date=$(echo "$backup_name" | cut -d'_' -f1)
            backup_timestamp=$(date -d "$backup_date" +%s 2>/dev/null || echo "0")
            cutoff_timestamp=$(date -d "$RETENTION_DAYS days ago" +%s)

            if [ "$backup_timestamp" -lt "$cutoff_timestamp" ] && [ "$backup_timestamp" -gt "0" ]; then
                backup_size=$(du -sh "$backup_dir" 2>/dev/null | cut -f1 || echo "unknown")
                log_message "Removing local backup: $backup_name (size: $backup_size)"

                if rm -rf "$backup_dir"; then
                    LOCAL_CLEANED=$((LOCAL_CLEANED + 1))
                    log_message "Successfully removed local backup: $backup_name"
                else
                    log_message "WARNING: Failed to remove local backup: $backup_name"
                fi
            fi
        fi
    done < <(find "$BACKUP_DIR" -maxdepth 1 -type d -name "*_*" -print0 2>/dev/null)
fi

log_message "Local cleanup completed. Removed $LOCAL_CLEANED backups"

# Cleanup remote backups (FTP)
if [ -n "$FTP_HOST" ] && [ -n "$FTP_USERNAME" ] && [ -n "$FTP_PASSWORD" ]; then
    log_message "Cleaning up remote backups older than $RETENTION_DAYS days..."

    # Test FTP connection
    if ! echo "quit" | ftp -n "$FTP_HOST" "$FTP_PORT" >/dev/null 2>&1; then
        log_message "WARNING: Cannot connect to FTP server for cleanup"
    else
        # Get list of remote backup directories
        FTP_LIST_SCRIPT=$(mktemp)
        chmod 600 "$FTP_LIST_SCRIPT"
        cat > "$FTP_LIST_SCRIPT" << EOF
open $FTP_HOST $FTP_PORT
user $FTP_USERNAME $FTP_PASSWORD
cd $FTP_PATH
ls
quit
EOF

        REMOTE_BACKUPS="/tmp/remote_backups.txt"
        if ftp -n < "$FTP_LIST_SCRIPT" > "$REMOTE_BACKUPS" 2>/dev/null; then
            REMOTE_CLEANED=0

            # Parse backup directories from FTP listing
            while read -r line; do
                # Extract directory names (assuming standard FTP ls format)
                if echo "$line" | grep -q "^d"; then
                    backup_name=$(echo "$line" | awk '{print $NF}')

                    # Check if it matches our backup naming convention
                    if [[ $backup_name =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$ ]]; then
                        backup_date=$(echo "$backup_name" | cut -d'_' -f1)
                        backup_timestamp=$(date -d "$backup_date" +%s 2>/dev/null || echo "0")
                        cutoff_timestamp=$(date -d "$RETENTION_DAYS days ago" +%s)

                        if [ "$backup_timestamp" -lt "$cutoff_timestamp" ] && [ "$backup_timestamp" -gt "0" ]; then
                            log_message "Removing remote backup: $backup_name"

                            # Create FTP script to remove directory
                            FTP_REMOVE_SCRIPT=$(mktemp)
                            chmod 600 "$FTP_REMOVE_SCRIPT"
                            cat > "$FTP_REMOVE_SCRIPT" << EOF
open $FTP_HOST $FTP_PORT
user $FTP_USERNAME $FTP_PASSWORD
cd $FTP_PATH
cd $backup_name
mdelete *
cd ..
rmdir $backup_name
quit
EOF

                            if ftp -n < "$FTP_REMOVE_SCRIPT" >/dev/null 2>&1; then
                                REMOTE_CLEANED=$((REMOTE_CLEANED + 1))
                                log_message "Successfully removed remote backup: $backup_name"
                            else
                                log_message "WARNING: Failed to remove remote backup: $backup_name"
                            fi

                            rm -f "$FTP_REMOVE_SCRIPT"
                        fi
                    fi
                fi
            done < "$REMOTE_BACKUPS"

            log_message "Remote cleanup completed. Removed $REMOTE_CLEANED backups"
        else
            log_message "WARNING: Failed to list remote backups for cleanup"
        fi

        # Cleanup temporary files
        rm -f "$FTP_LIST_SCRIPT" "$REMOTE_BACKUPS"
    fi
else
    log_message "Skipping remote cleanup - FTP configuration not provided"
fi

# Generate cleanup report
TOTAL_CLEANED=$((LOCAL_CLEANED + ${REMOTE_CLEANED:-0}))
log_message "Cleanup process completed"
log_message "Total backups cleaned: $TOTAL_CLEANED (Local: $LOCAL_CLEANED, Remote: ${REMOTE_CLEANED:-0})"

# Update health check
touch /var/log/backup/healthcheck