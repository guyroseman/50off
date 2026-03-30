#!/bin/bash
# 50OFF — Cron Setup Script
# Run this once to install the scraper cron jobs.
# Usage: bash scraper/cron_setup.sh /absolute/path/to/50off

SITE_PATH="${1:-$(dirname "$(cd "$(dirname "$0")" && pwd)")}"
PHP_BIN=$(command -v php || echo "/usr/bin/php")
LOG="/tmp/50off_scraper.log"

echo "Installing 50OFF cron jobs..."
echo "  Site path : $SITE_PATH"
echo "  PHP binary: $PHP_BIN"
echo "  Log file  : $LOG"

# Build cron entries — stagger retailers across 4-hour windows to avoid overlap
CRON_ALL="0 */4 * * * $PHP_BIN $SITE_PATH/scraper/run.php all >> $LOG 2>&1"

# Remove any existing 50off cron lines, then add fresh ones
( crontab -l 2>/dev/null | grep -v '50off'; echo "$CRON_ALL" ) | crontab -

echo ""
echo "Cron installed. Current crontab:"
crontab -l | grep 50off
echo ""
echo "The scraper will run every 4 hours (all 3 retailers)."
echo "Manual run: $PHP_BIN $SITE_PATH/scraper/run.php all"
echo "Tail logs:  tail -f $LOG"
