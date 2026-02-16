#!/bin/bash

# Script to execute
SCRIPT_PATH="/usr/local/emhttp/plugins/zfs.scheduled.snapshots/scripts/runner.php"

function install_cron() {
    # Use user-level crontab instead of /etc/cron.d/
    # Get existing crontab and remove any existing zfs.scheduled.snapshots entries
    crontab -l 2>/dev/null | grep -v "zfs.scheduled.snapshots" > /tmp/root_cron_new 2>/dev/null || touch /tmp/root_cron_new
    
    # Add new cron job (run every 5 minutes)
    echo "*/5 * * * * /usr/bin/php $SCRIPT_PATH > /dev/null 2>&1" >> /tmp/root_cron_new
    
    # Install new crontab
    crontab /tmp/root_cron_new
    rm -f /tmp/root_cron_new
    
    echo "Cron job installed."
}

function remove_cron() {
    # Remove zfs.scheduled.snapshots entry from crontab
    crontab -l 2>/dev/null | grep -v "zfs.scheduled.snapshots" > /tmp/root_cron_new 2>/dev/null || touch /tmp/root_cron_new
    crontab /tmp/root_cron_new
    rm -f /tmp/root_cron_new
    
    # Also remove old system-level cron file if it exists
    rm -f /etc/cron.d/zfs-scheduled-snapshots 2>/dev/null
    
    echo "Cron job removed."
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
