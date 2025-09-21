#!/bin/bash

# FTP upload script
# Uploads database backup files to FTP server

set -euo pipefail

BACKUP_DIR="/app/backups"
LOG_FILE="/var/log/backup/backup.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - FTP UPLOAD: $1" | tee -a "$LOG_FILE"
}

# Configuration from environment variables
FTP_HOST="${BACKUP_FTP_HOST:-}"
FTP_USERNAME="${BACKUP_FTP_USERNAME:-}"
FTP_PASSWORD="${BACKUP_FTP_PASSWORD:-}"
FTP_PATH="${BACKUP_FTP_PATH:-/backups}"
FTP_PORT="${BACKUP_FTP_PORT:-21}"
FTP_TIMEOUT="${BACKUP_FTP_TIMEOUT:-30}"

log_message "FTP Configuration:"
log_message "  Host: $FTP_HOST"
log_message "  Port: $FTP_PORT"
log_message "  Username: $FTP_USERNAME"
log_message "  Path: $FTP_PATH"
log_message "  Timeout: ${FTP_TIMEOUT}s"

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

# Test FTP connection with proper authentication
log_message "Testing FTP connection..."
TEST_SCRIPT=$(mktemp)
chmod 600 "$TEST_SCRIPT"
cat > "$TEST_SCRIPT" << EOF
open $FTP_HOST $FTP_PORT
user $FTP_USERNAME $FTP_PASSWORD
binary
passive
pwd
quit
EOF

log_message "Attempting connection to $FTP_HOST:$FTP_PORT with user $FTP_USERNAME"
if ! ftp -n < "$TEST_SCRIPT" >/tmp/ftp_test.log 2>&1; then
    log_message "FTP connection test failed. Output:"
    cat /tmp/ftp_test.log | while read -r line; do
        log_message "FTP TEST: $line"
    done
    rm -f "$TEST_SCRIPT" /tmp/ftp_test.log
    handle_error "Cannot connect to FTP server"
fi

# Check for successful login in output
if ! grep -q "230\|logged in\|Login successful" /tmp/ftp_test.log; then
    log_message "FTP authentication failed. Output:"
    cat /tmp/ftp_test.log | while read -r line; do
        log_message "FTP AUTH: $line"
    done
    rm -f "$TEST_SCRIPT" /tmp/ftp_test.log
    handle_error "FTP authentication failed"
fi

log_message "FTP connection and authentication successful"
rm -f "$TEST_SCRIPT" /tmp/ftp_test.log

# Function to upload file via curl (fallback method)
upload_with_curl() {
    local file="$1"
    local filename=$(basename "$file")
    local remote_url="ftp://$FTP_HOST:$FTP_PORT$FTP_PATH/$BACKUP_NAME/$filename"

    log_message "Attempting upload with curl: $filename"
    if curl --connect-timeout "$FTP_TIMEOUT" \
           --ftp-pasv \
           --user "$FTP_USERNAME:$FTP_PASSWORD" \
           --upload-file "$file" \
           "$remote_url" \
           --silent \
           --show-error 2>/tmp/curl_error.log; then
        log_message "Curl upload successful: $filename"
        return 0
    else
        log_message "Curl upload failed: $filename"
        if [ -f /tmp/curl_error.log ]; then
            cat /tmp/curl_error.log | while read -r line; do
                log_message "CURL ERROR: $line"
            done
        fi
        return 1
    fi
}

# Create FTP script with secure permissions
FTP_SCRIPT=$(mktemp)
chmod 600 "$FTP_SCRIPT"
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
log_message "DEBUG: FTP script contents:"
cat "$FTP_SCRIPT" | while read -r line; do
    log_message "SCRIPT: $line"
done

START_TIME=$(date +%s)

log_message "Executing FTP upload..."
if ftp -n < "$FTP_SCRIPT" >/tmp/ftp_output.log 2>&1; then
    # Check for success indicators in the output
    if grep -q "Transfer complete\|226\|200\|sent\|bytes" /tmp/ftp_output.log; then
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
        log_message "FTP upload completed but no success indicators found"
        log_message "Full FTP output:"
        cat /tmp/ftp_output.log | while read -r line; do
            log_message "FTP: $line"
        done
        handle_error "FTP upload may have failed - no success indicators"
    fi
else
    log_message "FTP command failed with exit code $?"
    log_message "Full FTP output:"
    cat /tmp/ftp_output.log | while read -r line; do
        log_message "FTP: $line"
    done

    # Try fallback with curl
    log_message "Attempting fallback upload with curl..."

    # First, create the directory via curl
    if curl --connect-timeout "$FTP_TIMEOUT" \
           --ftp-pasv \
           --user "$FTP_USERNAME:$FTP_PASSWORD" \
           "ftp://$FTP_HOST:$FTP_PORT$FTP_PATH/" \
           --quote "MKD $BACKUP_NAME" \
           --silent \
           --show-error 2>/tmp/curl_mkdir.log; then
        log_message "Directory created successfully with curl"
    else
        log_message "Directory creation failed (may already exist)"
        if [ -f /tmp/curl_mkdir.log ]; then
            cat /tmp/curl_mkdir.log | while read -r line; do
                log_message "CURL MKDIR: $line"
            done
        fi
    fi

    # Upload files with curl
    curl_success=true
    for file in "$LATEST_BACKUP"/*; do
        if [ -f "$file" ]; then
            if ! upload_with_curl "$file"; then
                curl_success=false
                break
            fi
        fi
    done

    if [ "$curl_success" = "true" ]; then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        log_message "Curl fallback upload completed successfully in ${DURATION}s"

        # Calculate total uploaded size
        TOTAL_SIZE=$(du -sh "$LATEST_BACKUP" | cut -f1)
        log_message "Total backup size uploaded: $TOTAL_SIZE"

        # Create upload summary
        cat > "$LATEST_BACKUP/upload_summary.txt" << EOF
FTP Upload Summary (Curl Fallback)
==================================
Upload Date: $(date '+%Y-%m-%d %H:%M:%S %Z')
FTP Server: $FTP_HOST:$FTP_PORT
Remote Path: $FTP_PATH/$BACKUP_NAME
Total Size: $TOTAL_SIZE
Duration: ${DURATION}s
Method: Curl Fallback
Status: SUCCESS
EOF
    else
        handle_error "Both FTP and curl upload methods failed"
    fi
fi

# Verify upload by listing remote directory
log_message "Verifying upload..."
VERIFY_SCRIPT=$(mktemp)
chmod 600 "$VERIFY_SCRIPT"
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