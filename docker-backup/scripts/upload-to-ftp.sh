#!/bin/bash

# FTP upload script
# Uploads database backup files to FTP server

set -euo pipefail

# Configuration from environment variables
FTP_HOST="${BACKUP_FTP_HOST:-}"
FTP_USERNAME="${BACKUP_FTP_USERNAME:-}"
FTP_PASSWORD="${BACKUP_FTP_PASSWORD:-}"
FTP_PATH="${BACKUP_FTP_PATH:-/backups}"
FTP_PORT="${BACKUP_FTP_PORT:-21}"

BACKUP_DIR="/app/backups"
LOG_FILE="/var/log/backup/backup.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - FTP UPLOAD: $1" | tee -a "$LOG_FILE"
}

# Function to handle errors
handle_error() {
    log_message "ERROR: $1"
    exit 1
}

# Validate FTP configuration
if [ -z "$FTP_HOST" ] || [ -z "$FTP_USERNAME" ] || [ -z "$FTP_PASSWORD" ]; then
    handle_error "FTP configuration incomplete. Please set BACKUP_FTP_HOST, BACKUP_FTP_USERNAME, and BACKUP_FTP_PASSWORD"
fi

# Get latest backup directory
if [ -f /tmp/backup_path.txt ]; then
    LATEST_BACKUP=$(cat /tmp/backup_path.txt)
else
    LATEST_BACKUP=$(find "$BACKUP_DIR" -maxdepth 1 -type d -name "*_*" | sort | tail -1)
fi

if [ -z "$LATEST_BACKUP" ] || [ ! -d "$LATEST_BACKUP" ]; then
    handle_error "No backup directory found to upload"
fi

BACKUP_NAME=$(basename "$LATEST_BACKUP")
log_message "Starting FTP upload for backup: $BACKUP_NAME"
log_message "FTP Host: $FTP_HOST:$FTP_PORT"
log_message "Remote path: $FTP_PATH"

# Test FTP connection
log_message "Testing FTP connection..."
if ! echo "quit" | ftp -n "$FTP_HOST" "$FTP_PORT" >/dev/null 2>&1; then
    handle_error "Cannot connect to FTP server"
fi

# Create FTP script
FTP_SCRIPT="/tmp/ftp_upload.txt"
cat > "$FTP_SCRIPT" << EOF
open $FTP_HOST $FTP_PORT
user $FTP_USERNAME $FTP_PASSWORD
binary
passive
cd $FTP_PATH
mkdir $BACKUP_NAME
cd $BACKUP_NAME
EOF

# Add database backup upload commands
for file in "$LATEST_BACKUP"/*; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        echo "put \"$file\" \"$filename\"" >> "$FTP_SCRIPT"
        log_message "Queued file: $filename"
    fi
done

echo "quit" >> "$FTP_SCRIPT"

# Execute FTP upload
log_message "Uploading backup to FTP server..."
START_TIME=$(date +%s)

if ftp -n < "$FTP_SCRIPT" 2>&1 | tee /tmp/ftp_output.log | grep -q "Transfer complete\|226\|200"; then
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    log_message "FTP upload completed successfully in ${DURATION}s"

    # Calculate total uploaded size
    TOTAL_SIZE=$(du -sh "$LATEST_BACKUP" | cut -f1)
    log_message "Total backup size uploaded: $TOTAL_SIZE"

    # Create upload summary
    cat > "$LATEST_BACKUP/upload_summary.txt" << EOF
FTP Upload Summary
==================
Upload Date: $(date '+%Y-%m-%d %H:%M:%S %Z')
FTP Server: $FTP_HOST:$FTP_PORT
Remote Path: $FTP_PATH/$BACKUP_NAME
Total Size: $TOTAL_SIZE
Duration: ${DURATION}s
Status: SUCCESS
EOF

else
    log_message "FTP upload output:"
    cat /tmp/ftp_output.log | tail -10 | while read -r line; do
        log_message "FTP: $line"
    done
    handle_error "FTP upload failed"
fi

# Verify upload by listing remote directory
log_message "Verifying upload..."
VERIFY_SCRIPT="/tmp/ftp_verify.txt"
cat > "$VERIFY_SCRIPT" << EOF
open $FTP_HOST $FTP_PORT
user $FTP_USERNAME $FTP_PASSWORD
cd $FTP_PATH
ls $BACKUP_NAME
quit
EOF

if ftp -n < "$VERIFY_SCRIPT" 2>&1 | grep -q "$BACKUP_NAME"; then
    log_message "Upload verification successful"
else
    log_message "WARNING: Upload verification failed - backup may be incomplete"
fi

# Cleanup temporary files
rm -f "$FTP_SCRIPT" "$VERIFY_SCRIPT" /tmp/ftp_output.log

log_message "FTP upload process completed"