#!/bin/bash
# ─── Gateway Deploy Script ───────────────────────────────────────────────────
# USAGE: ./deploy-gateway.sh [path-to-new-index.js]
#
# This script PREVENTS deploying a broken gateway by:
# 1. Validating JavaScript syntax (node --check)
# 2. Verifying REQUIRED event listeners exist in the code
# 3. Creating a timestamped backup before replacing
# 4. Restarting supervisor and verifying it stays running
# 5. Rolling back automatically if startup fails
#
# NEVER edit index.js directly on the server. Always use this script.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

GATEWAY_DIR="/var/www/integrasi-wa.jodyaryono.id"
GATEWAY_FILE="$GATEWAY_DIR/index.js"
BACKUP_DIR="$GATEWAY_DIR/backups"
SUPERVISOR_NAME="integrasi-wa"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Required event listeners that MUST exist in the gateway code
REQUIRED_PATTERNS=(
    "client.on('group_join'"
    "client.on('group_leave'"
    "client.on('group_membership_request'"
    "client.on('message'"
    "client.on('group_update'"
    "forwardToWebhook"
)

error() { echo -e "${RED}✗ ERROR: $1${NC}" >&2; }
success() { echo -e "${GREEN}✓ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }

# ─── Validate source file ───────────────────────────────────────────────────
validate_file() {
    local file="$1"
    local errors=0

    echo "━━━ Validating: $file ━━━"

    # 1. Check file exists and is not empty
    if [ ! -f "$file" ]; then
        error "File not found: $file"
        return 1
    fi
    if [ ! -s "$file" ]; then
        error "File is empty: $file"
        return 1
    fi
    success "File exists ($(wc -l < "$file") lines, $(du -h "$file" | cut -f1))"

    # 2. JavaScript syntax check
    echo "  Checking JavaScript syntax..."
    if node --check "$file" 2>/tmp/gateway-syntax-err; then
        success "JavaScript syntax OK"
    else
        error "JavaScript syntax error:"
        cat /tmp/gateway-syntax-err >&2
        return 1
    fi

    # 3. Check required patterns
    echo "  Checking required event listeners..."
    for pattern in "${REQUIRED_PATTERNS[@]}"; do
        if grep -q "$pattern" "$file"; then
            success "  Found: $pattern"
        else
            error "  MISSING: $pattern"
            errors=$((errors + 1))
        fi
    done

    if [ $errors -gt 0 ]; then
        error "$errors required pattern(s) missing! Deploy BLOCKED."
        error "The gateway MUST have all group event listeners (group_join, group_leave, group_membership_request)."
        error "These were lost before and caused onboarding to stop working."
        return 1
    fi

    success "All $((${#REQUIRED_PATTERNS[@]})) required patterns found"
    return 0
}

# ─── Main ────────────────────────────────────────────────────────────────────

SOURCE_FILE="${1:-}"

if [ -z "$SOURCE_FILE" ]; then
    echo "Usage: $0 <path-to-new-index.js>"
    echo ""
    echo "This script safely deploys a new gateway index.js with validation."
    echo "It checks syntax, verifies required event listeners, backs up, and"
    echo "rolls back automatically if the new version crashes."
    echo ""
    echo "To validate the CURRENT deployed file:"
    echo "  $0 --check"
    exit 1
fi

# Check-only mode
if [ "$SOURCE_FILE" = "--check" ]; then
    echo "━━━ Checking currently deployed gateway ━━━"
    validate_file "$GATEWAY_FILE"
    exit $?
fi

# Validate source
if ! validate_file "$SOURCE_FILE"; then
    error "Validation failed. Fix the issues above before deploying."
    exit 1
fi

echo ""
echo "━━━ Deploying ━━━"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Timestamped backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/index.js.$TIMESTAMP"
cp "$GATEWAY_FILE" "$BACKUP_FILE"
success "Backup: $BACKUP_FILE"

# Clean old backups (keep last 20)
ls -t "$BACKUP_DIR"/index.js.* 2>/dev/null | tail -n +21 | xargs -r rm -f
success "Old backups cleaned (keeping last 20)"

# Deploy
cp "$SOURCE_FILE" "$GATEWAY_FILE"
success "Deployed new index.js"

# Restart supervisor
echo "  Restarting $SUPERVISOR_NAME..."
supervisorctl restart "$SUPERVISOR_NAME"
sleep 3

# Verify it's running
STATUS=$(supervisorctl status "$SUPERVISOR_NAME" | awk '{print $2}')
if [ "$STATUS" = "RUNNING" ]; then
    success "Gateway is RUNNING"

    # Wait a bit more and check again (catch delayed crashes)
    sleep 5
    STATUS=$(supervisorctl status "$SUPERVISOR_NAME" | awk '{print $2}')
    if [ "$STATUS" = "RUNNING" ]; then
        success "Gateway stable after 8 seconds"
        echo ""
        echo -e "${GREEN}━━━ Deploy successful! ━━━${NC}"
    else
        warn "Gateway crashed after initial start!"
        echo "  Rolling back to $BACKUP_FILE..."
        cp "$BACKUP_FILE" "$GATEWAY_FILE"
        supervisorctl restart "$SUPERVISOR_NAME"
        sleep 3
        error "ROLLED BACK to previous version due to crash"
        exit 1
    fi
else
    warn "Gateway is NOT running (status: $STATUS)"
    echo "  Rolling back to $BACKUP_FILE..."
    cp "$BACKUP_FILE" "$GATEWAY_FILE"
    supervisorctl restart "$SUPERVISOR_NAME"
    sleep 3
    STATUS=$(supervisorctl status "$SUPERVISOR_NAME" | awk '{print $2}')
    if [ "$STATUS" = "RUNNING" ]; then
        error "ROLLED BACK successfully. Previous version is running."
    else
        error "ROLLED BACK but gateway still not running! Manual intervention needed."
    fi
    exit 1
fi
