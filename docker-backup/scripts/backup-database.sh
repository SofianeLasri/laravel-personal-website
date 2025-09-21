#!/bin/bash

# Database backup script
# Creates compressed MySQL dump and uploads to FTP

set -euo pipefail

# Configuration from environment variables
DB_HOST="${DB_HOST:-app}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-laravel}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

BACKUP_DIR="/app/backups"
LOG_FILE="/var/log/backup/backup.log"

# Create timestamp for backup
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
BACKUP_PATH="$BACKUP_DIR/$TIMESTAMP"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - DATABASE BACKUP: $1" | tee -a "$LOG_FILE"
}

# Function to handle errors
handle_error() {
    log_message "ERROR: $1"
    exit 1
}

# Create backup directory
mkdir -p "$BACKUP_PATH" || handle_error "Failed to create backup directory"

log_message "Starting database backup..."
log_message "Backup path: $BACKUP_PATH"

# Wait for database to be ready
log_message "Waiting for database connection..."
for i in {1..60}; do
    if mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; then
        log_message "Database connection established"
        break
    fi
    if [ $i -eq 60 ]; then
        handle_error "Database connection timeout after 60 attempts"
    fi
    sleep 2
done

# Create database dump
DUMP_FILE="$BACKUP_PATH/${DB_DATABASE}_dump.sql"
log_message "Creating database dump..."

mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --password="$DB_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --lock-tables=false \
    --add-drop-database \
    --databases "$DB_DATABASE" \
    > "$DUMP_FILE" || handle_error "Failed to create database dump"

# Compress the dump
log_message "Compressing database dump..."
gzip "$DUMP_FILE" || handle_error "Failed to compress database dump"

# Verify compressed file
COMPRESSED_FILE="$DUMP_FILE.gz"
if [ ! -f "$COMPRESSED_FILE" ]; then
    handle_error "Compressed dump file not found"
fi

# Verify file integrity
if ! gzip -t "$COMPRESSED_FILE"; then
    handle_error "Compressed dump file is corrupted"
fi

# Get file size for logging
FILE_SIZE=$(du -h "$COMPRESSED_FILE" | cut -f1)
log_message "Database backup completed successfully"
log_message "Compressed dump size: $FILE_SIZE"
log_message "File: $COMPRESSED_FILE"

# Create metadata file
cat > "$BACKUP_PATH/metadata.txt" << EOF
Database Backup Metadata
========================
Timestamp: $TIMESTAMP
Database: $DB_DATABASE
Host: $DB_HOST:$DB_PORT
Compressed Size: $FILE_SIZE
Backup File: $(basename "$COMPRESSED_FILE")
Created: $(date '+%Y-%m-%d %H:%M:%S %Z')
EOF

log_message "Database backup metadata created"
echo "$BACKUP_PATH" > /tmp/backup_path.txt