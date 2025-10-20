#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
DIST_DIR="$ROOT_DIR/dist"
PKG_DIR="$ROOT_DIR" # repo root when used standalone

mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/moofeeds.zip"

# Create a temporary packaging directory to ensure only module files are zipped
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

# Copy module folder preserving structure
rsync -a --delete \
  "$ROOT_DIR/" "$TMP/moofeeds/" \
  --exclude ".git" \
  --exclude ".github/workflows" \
  --exclude "scripts" \
  --exclude "dist" \
  --exclude "*.zip"

# Ensure empty var dirs are present
mkdir -p "$TMP/moofeeds/var/cache" "$TMP/moofeeds/var/lock"

# Build zip
cd "$TMP"
zip -r9 "$DIST_DIR/moofeeds.zip" moofeeds

echo "Built: $DIST_DIR/moofeeds.zip"
