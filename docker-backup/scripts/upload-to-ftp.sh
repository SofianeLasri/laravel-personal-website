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
FTP_PATH="${BACKUP_FTP_PATH:-/}"
FTP_PORT="${BACKUP_FTP_PORT:-21}"
FTP_TIMEOUT="${BACKUP_FTP_TIMEOUT:-30}"

# Remove leading slash if present and ensure path is clean
FTP_PATH=$(echo "$FTP_PATH" | sed 's|^/*||' | sed 's|/*$||')
if [ -n "$FTP_PATH" ]; then
    FTP_PATH="/$FTP_PATH"
else
    FTP_PATH=""
fi


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

# Test FTP connection using curl
log_message "Testing FTP connection..."

if curl --connect-timeout "$FTP_TIMEOUT" \
       --ftp-pasv \
       --user "$FTP_USERNAME:$FTP_PASSWORD" \
       "ftp://$FTP_HOST:$FTP_PORT/" \
       --list-only \
       --silent \
       --show-error >/tmp/curl_test.log 2>&1; then
    log_message "FTP connection successful"
else
    log_message "FTP connection failed:"
    cat /tmp/curl_test.log | while read -r line; do
        log_message "ERROR: $line"
    done
    rm -f /tmp/curl_test.log
    handle_error "Cannot connect to FTP server"
fi

rm -f /tmp/curl_test.log

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

# Function to upload file directly to FTP path
upload_with_curl_direct() {
    local file="$1"
    local remote_filename="$2"
    local remote_url="ftp://$FTP_HOST:$FTP_PORT$FTP_PATH/$remote_filename"

    if curl --connect-timeout "$FTP_TIMEOUT" \
           --ftp-pasv \
           --user "$FTP_USERNAME:$FTP_PASSWORD" \
           --upload-file "$file" \
           "$remote_url" \
           --silent \
           --show-error 2>/tmp/curl_error.log; then
        return 0
    else
        if [ -f /tmp/curl_error.log ]; then
            cat /tmp/curl_error.log | while read -r line; do
                log_message "Upload error: $line"
            done
        fi
        return 1
    fi
}

# Upload backup using curl
log_message "Uploading backup files..."
START_TIME=$(date +%s)

upload_success=true
uploaded_files=0

for file in "$LATEST_BACKUP"/*; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        # Add timestamp prefix to make files unique
        remote_filename="${BACKUP_NAME}_${filename}"
        log_message "Uploading: $remote_filename"

        if upload_with_curl_direct "$file" "$remote_filename"; then
            uploaded_files=$((uploaded_files + 1))
        else
            upload_success=false
            log_message "Upload failed: $remote_filename"
            break
        fi
    fi
done

if [ "$upload_success" = "true" ] && [ "$uploaded_files" -gt 0 ]; then
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    log_message "Upload completed successfully in ${DURATION}s"
    log_message "Total files uploaded: $uploaded_files"

    # Calculate total uploaded size
    TOTAL_SIZE=$(du -sh "$LATEST_BACKUP" | cut -f1)
    log_message "Total backup size uploaded: $TOTAL_SIZE"

    # Create upload summary
    cat > "$LATEST_BACKUP/upload_summary.txt" << EOF
FTP Upload Summary
==================
Upload Date: $(date '+%Y-%m-%d %H:%M:%S %Z')
FTP Server: $FTP_HOST:$FTP_PORT
Remote Path: $FTP_PATH
Remote File Prefix: $BACKUP_NAME
Total Size: $TOTAL_SIZE
Files Uploaded: $uploaded_files
Duration: ${DURATION}s
Method: Curl (Direct Upload)
Status: SUCCESS
EOF
else
    handle_error "Upload failed - $uploaded_files files uploaded successfully"
fi

# Verify upload by listing remote directory
log_message "Verifying upload..."
if curl --connect-timeout "$FTP_TIMEOUT" \
       --ftp-pasv \
       --user "$FTP_USERNAME:$FTP_PASSWORD" \
       "ftp://$FTP_HOST:$FTP_PORT$FTP_PATH/" \
       --list-only \
       --silent \
       --show-error >/tmp/verify_upload.log 2>&1; then

    # Check if our backup files are in the listing
    backup_files_found=0
    for file in "$LATEST_BACKUP"/*; do
        if [ -f "$file" ]; then
            filename=$(basename "$file")
            remote_filename="${BACKUP_NAME}_${filename}"
            if grep -q "$remote_filename" /tmp/verify_upload.log; then
                backup_files_found=$((backup_files_found + 1))
            fi
        fi
    done

    if [ "$backup_files_found" -eq "$uploaded_files" ]; then
        log_message "All files verified on server ($backup_files_found/$uploaded_files)"
    else
        log_message "WARNING: Only $backup_files_found/$uploaded_files files verified on server"
    fi
else
    log_message "WARNING: Could not verify upload"
fi

# Cleanup temporary files
rm -f /tmp/curl_mkdir.log /tmp/curl_error.log /tmp/verify_upload.log

log_message "FTP upload process completed"