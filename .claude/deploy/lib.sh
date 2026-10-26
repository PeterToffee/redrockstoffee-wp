#!/bin/bash
# Shared settings and helpers for the deploy scripts (1-compare, 2-preflight, 3-deploy, 4-verify, rollback).
# Adapted from TheUnframer-WP's own deploy pipeline (same mechanism, different site/target).
# If a path or the site address ever changes, edit the CONFIG block below - nothing else.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT" || exit 1

# ---------- CONFIG ----------
# Pete, 2026-10-26: the deployed unit here is a PLUGIN (rrt-label-configurator),
# not a theme - LOCAL_TARGET/REMOTE_TARGET (not LOCAL_THEME/REMOTE_THEME, TheUnframer's
# naming) to avoid implying a theme-folder convention this repo doesn't use.
LOCAL_TARGET="wp-content/plugins/rrt-label-configurator"          # plugin folder in this repo
REMOTE_TARGET="html/wp-content/plugins/rrt-label-configurator"    # same folder on GoDaddy (SFTP path, relative to home)
# "html/" prefix assumed to match TheUnframer's own GoDaddy account-path convention -
# NOT yet confirmed against this specific account. First real 1-compare.sh run will
# reveal immediately if this is wrong (connection succeeds but "only N files downloaded").
SITE="https://redrockstoffee.com"                    # live site
LOCAL_SITE="http://localhost:8081"                   # local XAMPP site (confirmed this port all session)
PHP="C:/xampp/php/php.exe"                           # local PHP (for syntax checks)
EDGE="C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"   # headless browser for the JS smoke test
# Files we compare and deploy as "code":
CODE_REGEX='\.(php|js|css|json|html)$'
# Images/audio/fonts (checked over HTTP; NEW ones are added to the deploy set):
ASSET_REGEX='\.(png|jpe?g|gif|svg|webp|mp3|mp4|woff2?|ttf|ico)$'
# Never compared or deployed (backups, Mac junk):
EXCLUDE_REGEX='(\.bak\.|/\._|(^|/)\._)'
# ---------- END CONFIG ----------

DEPLOY_DIR="$ROOT/.claude/deploy"
TMP="$ROOT/.deploy-tmp"                 # scratch area (git-ignored)
HEADDIR="$TMP/head/$LOCAL_TARGET"       # exact copy of what is committed (HEAD)
LIVEDIR="$TMP/live"                     # copy of the live code files
REPORT="$TMP/report"                    # diffs and summaries
PROBES="$DEPLOY_DIR/probes.txt"

# Windows-style path (C:/...) for programs like sftp that cannot read Git Bash's /c/... paths.
winpath() { cygpath -m "$1"; }

# Run SFTP commands read from stdin against the live server (the only connection method that works here).
sftp_run() { "$ROOT/.claude/godaddy-sftp.sh"; }

# md5 that ignores Windows-vs-Unix line endings (used to decide "same content").
norm_md5() { tr -d '\r' < "$1" | md5sum | cut -d' ' -f1; }
# md5 of the exact bytes (used to prove an upload arrived unchanged).
exact_md5() { md5sum < "$1" | cut -d' ' -f1; }

# Simple pass/warn/fail counters used by the preflight and verify scripts.
PASS=0; WARN=0; FAIL=0
pass() { PASS=$((PASS+1)); echo "  [PASS] $*"; }
warn() { WARN=$((WARN+1)); echo "  [WARN] $*"; }
fail() { FAIL=$((FAIL+1)); echo "  [FAIL] $*"; }
summary() { echo; echo "RESULT: $PASS passed, $WARN warnings, $FAIL failed"; [ "$FAIL" -eq 0 ]; }

# Unpack exactly what is committed at HEAD (so unfinished local edits can never be uploaded by accident).
export_head() {
  rm -rf "$TMP/head"; mkdir -p "$TMP/head"
  git archive HEAD "$LOCAL_TARGET" | tar -x -C "$TMP/head"
}

# List of code files in the committed plugin (relative to the plugin folder).
list_code_files() {
  ( cd "$HEADDIR" && find . -type f | sed 's#^\./##' | grep -E "$CODE_REGEX" | grep -Ev "$EXCLUDE_REGEX" | sort )
}
# List of asset files (images/audio/fonts) in the committed plugin.
list_asset_files() {
  ( cd "$HEADDIR" && find . -type f | sed 's#^\./##' | grep -E "$ASSET_REGEX" | grep -Ev "$EXCLUDE_REGEX" | sort )
}

# Pete, 2026-10-26: rewritten for this plugin's actual layout (no style.css/
# functions.php/template-parts here - that's a theme-folder convention this
# repo doesn't use). assets first, inc/ next, the main plugin file last (it's
# the file every other file depends on, and it carries RRT_LABEL_VERSION, the
# cache-busting constant - same role style.css/functions.php played for
# TheUnframer's deploy_rank()).
deploy_rank() {
  case "$1" in
    rrt-label-configurator.php) echo 9 ;;
    inc/*) echo 3 ;;
    *.php) echo 6 ;;
    *.js|*.css|*.json|*.html) echo 2 ;;
    *) echo 1 ;;
  esac
}

# Run every line of probes.txt against a site.   run_probes <base-url> [bust]
#   bust = add a throw-away ?_probe=... so a cached copy is bypassed and we test the server itself.
# Counts PASS/FAIL using the helpers above. Extra probe lines can be passed in a file as the 3rd argument.
run_probes() {
  local base="$1" bust="${2:-}" extra="${3:-}" line left status must mustnot method path url data code body term ok
  body="$(mktemp)"
  while IFS= read -r line; do
    case "$line" in ''|\#*) continue ;; esac
    IFS='|' read -r left status must mustnot <<< "$line"
    method=$(echo "$left" | awk '{print $1}'); path=$(echo "$left" | awk '{print $2}')
    status=$(echo "$status" | tr -d ' '); must=$(echo "$must" | sed 's/^ *//; s/ *$//'); mustnot=$(echo "$mustnot" | sed 's/^ *//; s/ *$//')
    if [ "$method" = "POST" ]; then
      url="${path%%\?*}"; data=""; [ "$url" != "$path" ] && data="${path#*\?}"
      code=$(curl -sk -m 40 -X POST -d "$data" -o "$body" -w '%{http_code}' "$base$url")
    else
      url="$path"; [ -n "$bust" ] && { case "$url" in *\?*) url="$url&_probe=$RANDOM$RANDOM" ;; *) url="$url?_probe=$RANDOM$RANDOM" ;; esac; }
      code=$(curl -sk -m 40 -o "$body" -w '%{http_code}' "$base$url")
    fi
    ok=1; why=""
    [ "$status" != "-" ] && [ "$code" != "$status" ] && { ok=0; why="status $code (wanted $status)"; }
    if [ "$must" != "-" ] && [ -n "$must" ]; then
      IFS=';' read -ra terms <<< "$must"
      for term in "${terms[@]}"; do term=$(echo "$term" | sed 's/^ *//; s/ *$//'); grep -qF -- "$term" "$body" || { ok=0; why="$why missing text: $term;"; }; done
    fi
    if [ "$mustnot" != "-" ] && [ -n "$mustnot" ]; then
      IFS=';' read -ra terms <<< "$mustnot"
      for term in "${terms[@]}"; do term=$(echo "$term" | sed 's/^ *//; s/ *$//'); grep -qF -- "$term" "$body" && { ok=0; why="$why forbidden text present: $term;"; }; done
    fi
    if [ "$ok" -eq 1 ]; then pass "$method $path  ($code)"; else fail "$method $path  -> $why"; fi
  done < <(cat "$PROBES" ${extra:+"$extra"})
  rm -f "$body"
}
