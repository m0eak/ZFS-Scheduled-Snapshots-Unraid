#!/bin/bash

# Configuration
PLUGIN_NAME="zfs.scheduled.snapshots"
CRON_FILE="/etc/cron.d/zfs-scheduled-snapshots"
RUNNER_SCRIPT="/usr/local/emhttp/plugins/$PLUGIN_NAME/scripts/runner.php"
LOG_TAG="ZfsScheduledSnapshots"

echo "========================================================"
echo "      ZFS Scheduled Snapshots - Debug Check             "
echo "========================================================"
echo "Date: $(date)"
echo ""

# 1. Check ZFS Datasets Configuration
echo "[1] Checking ZFS Datasets Configuration..."
echo "----------------------------------------"
if ! command -v zfs &> /dev/null; then
    echo "ERROR: 'zfs' command not found. Is ZFS installed?"
else
    # Get all datasets with com.sun:auto-snapshot property
    datasets=$(zfs get -H -o name,value,source com.sun:auto-snapshot | grep -v "\-$")
    
    if [ -z "$datasets" ]; then
        echo "No datasets found with 'com.sun:auto-snapshot' property set."
        echo "To enable snapshots for a dataset, run:"
        echo "  zfs set com.sun:auto-snapshot=true <pool/dataset>"
    else
        printf "%-40s %-15s %-15s %-10s\n" "Dataset" "Auto-Snapshot" "Frequency" "Keep"
        printf "%-40s %-15s %-15s %-10s\n" "-------" "-------------" "---------" "----"
        
        while read -r line; do
            dataset=$(echo "$line" | awk '{print $1}')
            enabled=$(echo "$line" | awk '{print $2}')
            
            if [ "$enabled" == "true" ]; then
                freq=$(zfs get -H -o value com.sun:auto-snapshot:frequency "$dataset" 2>/dev/null)
                if [ "$freq" == "-" ]; then freq="daily (default)"; fi
                
                keep=$(zfs get -H -o value com.sun:auto-snapshot:keep "$dataset" 2>/dev/null)
                if [ "$keep" == "-" ]; then keep="31 (default)"; fi
                
                printf "%-40s %-15s %-15s %-10s\n" "$dataset" "$enabled" "$freq" "$keep"
            fi
        done <<< "$datasets"
    fi
fi
echo ""

# 2. Check Cron Job
echo "[2] Checking Cron Job..."
echo "----------------------"
if [ -f "$CRON_FILE" ]; then
    echo "Cron file exists at: $CRON_FILE"
    echo "Content:"
    cat "$CRON_FILE"
else
    echo "ERROR: Cron file NOT found at $CRON_FILE"
    echo "Try reinstalling the plugin or running the setup script manually."
fi
echo ""

# 3. Check Runner Script
echo "[3] Checking Runner Script..."
echo "-------------------------"
if [ -f "$RUNNER_SCRIPT" ]; then
    echo "Runner script exists at: $RUNNER_SCRIPT"
    if [ -x "$RUNNER_SCRIPT" ]; then
        echo "Runner script is executable."
    else
        echo "WARNING: Runner script is NOT executable. Attempting to fix..."
        chmod +x "$RUNNER_SCRIPT"
        if [ -x "$RUNNER_SCRIPT" ]; then
            echo "Fixed: Runner script is now executable."
        else
            echo "ERROR: Failed to make runner script executable."
        fi
    fi
    
    # Check PHP syntax
    if command -v php &> /dev/null; then
        php -l "$RUNNER_SCRIPT" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo "PHP syntax check: OK"
        else
            echo "PHP syntax check: FAILED"
            php -l "$RUNNER_SCRIPT"
        fi
    else
        echo "WARNING: 'php' command not found. Cannot check syntax."
    fi

else
    echo "ERROR: Runner script NOT found at $RUNNER_SCRIPT"
fi
echo ""

# 4. Check Logs
echo "[4] Checking Recent Logs..."
echo "-----------------------"
if [ -f "/var/log/syslog" ]; then
    echo "Searching for '$LOG_TAG' in /var/log/syslog (last 10 entries)..."
    grep "$LOG_TAG" /var/log/syslog | tail -n 10
else
    echo "WARNING: /var/log/syslog not found. Cannot check logs."
fi
echo ""

# 5. Summary
echo "[5] Summary"
echo "-----------"
echo "To manually trigger a run, execute:"
echo "  php $RUNNER_SCRIPT"
echo ""
echo "========================================================"
