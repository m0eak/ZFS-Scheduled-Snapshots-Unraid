#!/bin/bash

# Cron file path
CRON_FILE="/etc/cron.d/zfs-scheduled-snapshots"
# Script to execute
SCRIPT_PATH="/usr/local/emhttp/plugins/zfs.scheduled.snapshots/scripts/runner.php"

function install_cron() {
    echo "# ZFS Scheduled Snapshots - Check every 5 minutes" > "$CRON_FILE"
    # Run every 5 minutes: */5 * * * *
    echo "*/5 * * * * root /usr/bin/php $SCRIPT_PATH > /dev/null 2>&1" >> "$CRON_FILE"
    # Ensure correct permissions
    chmod 644 "$CRON_FILE"
    # Update cron daemon
    update_cron
    echo "Cron job installed."
}

function remove_cron() {
    if [ -f "$CRON_FILE" ]; then
        rm -f "$CRON_FILE"
        update_cron
        echo "Cron job removed."
    fi
}

# Main logic
case "$1" in
    'install')
        install_cron
        ;;
    'remove')
        remove_cron
        ;;
    *)
        echo "Usage: $0 {install|remove}"
        exit 1
        ;;
esac
