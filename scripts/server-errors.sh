#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
LOG_DIR="${LOG_DIR:-$APP_DIR/storage/logs}"
LINES="${LINES:-120}"
MODE="${1:-errors}"

usage() {
    cat <<'EOF'
Usage:
  bash scripts/server-errors.sh [errors|laravel|follow|web|list]

Options via environment:
  LINES=200        Number of lines to show. Default: 120.
  APP_DIR=/path    Laravel project directory. Default: repo root.
  LOG_FILE=/path   Specific Laravel log file to read.

Examples:
  bash scripts/server-errors.sh
  LINES=300 bash scripts/server-errors.sh laravel
  bash scripts/server-errors.sh follow
  sudo LINES=100 bash scripts/server-errors.sh web
EOF
}

latest_laravel_log() {
    if [[ -n "${LOG_FILE:-}" ]]; then
        printf '%s\n' "$LOG_FILE"
        return
    fi

    if [[ -f "$LOG_DIR/laravel.log" ]]; then
        printf '%s\n' "$LOG_DIR/laravel.log"
        return
    fi

    find "$LOG_DIR" -type f -name '*.log' -print0 2>/dev/null \
        | xargs -0 ls -t 2>/dev/null \
        | head -n 1
}

require_log_file() {
    local log_file
    log_file="$(latest_laravel_log || true)"

    if [[ -z "$log_file" || ! -f "$log_file" ]]; then
        echo "Laravel log tidak ditemukan di: $LOG_DIR" >&2
        exit 1
    fi

    printf '%s\n' "$log_file"
}

show_laravel_errors() {
    local log_file
    log_file="$(require_log_file)"

    echo "== Laravel errors: $log_file =="
    grep -nE 'production\.ERROR|local\.ERROR|staging\.ERROR|ERROR:|CRITICAL|Exception|Stack trace' "$log_file" \
        | tail -n "$LINES" \
        || true
}

show_laravel_tail() {
    local log_file
    log_file="$(require_log_file)"

    echo "== Laravel log: $log_file =="
    tail -n "$LINES" "$log_file"
}

follow_laravel_log() {
    local log_file
    log_file="$(require_log_file)"

    echo "== Following Laravel log: $log_file =="
    tail -n "$LINES" -f "$log_file"
}

list_laravel_logs() {
    echo "== Laravel logs in $LOG_DIR =="
    find "$LOG_DIR" -type f -name '*.log' -print0 2>/dev/null \
        | xargs -0 ls -lhAt 2>/dev/null \
        || true
}

show_web_logs() {
    local found=0
    local candidates=(
        /var/log/nginx/error.log
        /var/log/apache2/error.log
        /var/log/httpd/error_log
        /var/log/php-fpm/error.log
        /var/log/php*/error.log
        /var/log/php*-fpm.log
    )

    for log_file in "${candidates[@]}"; do
        for expanded in $log_file; do
            if [[ -f "$expanded" && -r "$expanded" ]]; then
                found=1
                echo "== Web/PHP log: $expanded =="
                tail -n "$LINES" "$expanded"
                echo
            fi
        done
    done

    if [[ "$found" -eq 0 ]]; then
        echo "Log web/PHP umum tidak ditemukan atau tidak bisa dibaca."
        echo "Coba jalankan dengan sudo:"
        echo "  sudo LINES=$LINES bash scripts/server-errors.sh web"
    fi
}

case "$MODE" in
    errors)
        show_laravel_errors
        ;;
    laravel)
        show_laravel_tail
        ;;
    follow)
        follow_laravel_log
        ;;
    web)
        show_web_logs
        ;;
    list)
        list_laravel_logs
        ;;
    help|--help|-h)
        usage
        ;;
    *)
        echo "Mode tidak dikenal: $MODE" >&2
        usage >&2
        exit 1
        ;;
esac
