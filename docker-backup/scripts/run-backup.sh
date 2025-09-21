#!/bin/bash

# Main backup orchestration script
# Coordinates database backup, FTP upload, and cleanup

set -euo pipefail

LOG_FILE="/var/log/backup/backup.log"
SCRIPTS_DIR="/app/scripts"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - BACKUP: $1" | tee -a "$LOG_FILE"
}

# Function to handle errors
handle_error() {
    log_message "ERROR: $1"
    log_message "Backup process failed!"
    exit 1
}

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

log_message "========================================="
log_message "Starting backup process"
log_message "========================================="

# Check if required scripts exist
REQUIRED_SCRIPTS=(
    "backup-database.sh"
    "upload-to-ftp.sh"
    "cleanup-old-backups.sh"
)

for script in "${REQUIRED_SCRIPTS[@]}"; do
    if [ ! -f "$SCRIPTS_DIR/$script" ]; then
        handle_error "Required script not found: $script"
    fi
    if [ ! -x "$SCRIPTS_DIR/$script" ]; then
        handle_error "Script not executable: $script"
    fi
done

# Record backup start time
START_TIME=$(date +%s)
log_message "Backup started at $(date '+%Y-%m-%d %H:%M:%S')"

# Step 1: Database backup
log_message "Step 1: Database backup"
if ! "$SCRIPTS_DIR/backup-database.sh"; then
    handle_error "Database backup failed"
fi
log_message "Database backup completed successfully"

# Step 2: FTP upload (only if FTP is configured)
if [ -n "${BACKUP_FTP_HOST:-}" ]; then
    log_message "Step 2: FTP upload"
    if ! "$SCRIPTS_DIR/upload-to-ftp.sh"; then
        handle_error "FTP upload failed"
    fi
    log_message "FTP upload completed successfully"
else
    log_message "Step 2: Skipping FTP upload (not configured)"
fi

# Step 3: Cleanup old backups
log_message "Step 3: Cleanup old backups"
if ! "$SCRIPTS_DIR/cleanup-old-backups.sh"; then
    log_message "WARNING: Cleanup process encountered errors"
else
    log_message "Cleanup completed successfully"
fi

# Calculate total backup time
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
MINUTES=$((DURATION / 60))
SECONDS=$((DURATION % 60))

log_message "========================================="
log_message "Backup process completed successfully"
log_message "Total duration: ${MINUTES}m ${SECONDS}s"
log_message "========================================="

# Update health check
touch /var/log/backup/healthcheck

# Optional: Send notification (can be extended later)
if [ -n "${BACKUP_WEBHOOK_URL:-}" ]; then
    log_message "Sending notification webhook"
    curl -X POST "$BACKUP_WEBHOOK_URL" \
        -H "Content-Type: application/json" \
        -d "{\"message\":\"Database backup completed successfully in ${MINUTES}m ${SECONDS}s\"}" \
        >/dev/null 2>&1 || log_message "WARNING: Failed to send webhook notification"
fi