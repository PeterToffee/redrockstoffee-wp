#!/bin/bash
# STAGE 4 - VERIFY: prove the deploy worked. Read-only against the live site.
#
#   .claude/deploy/4-verify.sh [deploy-id]                 right after stage 3 (defaults to the latest deploy)
#   .claude/deploy/4-verify.sh [deploy-id] --after-flush   again after Pete presses Flush Cache: tests what VISITORS get
#   .claude/deploy/4-verify.sh [deploy-id] --record        if everything passes: save the new live baseline + write the deploy log
#
# Checks:
#   1. round trip   - every uploaded file is downloaded back and must match the exact bytes we sent
#   2. public files - css/js/images fetched over the web must match (or at least exist)
#   3. live pages   - probes.txt (+ any .claude/deploy/log/<id>/probes-extra.txt) against the live site, bypassing caches
#   4. (--after-flush) the same pages exactly as visitors get them, plus the cache status
# Any FAIL prints the exact rollback command.

source "$(dirname "$0")/lib.sh"
TS=""; AFTER=0; RECORD=0
for a in "$@"; do
  case "$a" in --after-flush) AFTER=1 ;; --record) RECORD=1 ;; [0-9]*) TS="$a" ;; esac
done
[ -z "$TS" ] && TS=$(cat "$TMP/last-deploy-id" 2>/dev/null)
[ -z "$TS" ] && TS=$(ls -1 "$DEPLOY_DIR/log" 2>/dev/null | sort -n | tail -1)
LOG="$DEPLOY_DIR/log/$TS"; MAN="$LOG/manifest.txt"
[ -f "$MAN" ] || { echo "No manifest for deploy id '$TS'."; exit 2; }
V="$TMP/verify/$TS"; mkdir -p "$V"; WV="$(winpath "$V")"
echo "== Stage 4: verify deploy $TS ($(grep -vc '^#' "$MAN") files) =="

# ---- 1. round trip
echo; echo "1. Round trip (download back from live and compare exact bytes)"
grep -v '^#' "$MAN" | while IFS='|' read -r f cat before after backup; do
  mkdir -p "$V/$(dirname "$f")"; echo "get \"$REMOTE_TARGET/$f\" \"$WV/$f\""
done | sftp_run >/dev/null 2>&1
while IFS='|' read -r f cat before after backup; do
  if [ ! -f "$V/$f" ]; then fail "$f is not on the server"
  elif [ "$(exact_md5 "$V/$f")" = "$after" ]; then pass "$f  matches what we sent"
  else fail "$f  DIFFERS from what we sent"; fi
done < <(grep -v '^#' "$MAN")

# ---- 2. public files over HTTP
echo; echo "2. Public css/js/images over the web"
while IFS='|' read -r f cat before after backup; do
  case "$f" in *.css|*.js|assets/*) ;; *) continue ;; esac
  code=$(curl -sk -m 40 -o "$V/http.tmp" -w '%{http_code}' "$SITE/wp-content/plugins/rrt-label-configurator/$f?verify=$TS")
  if [ "$code" != "200" ]; then fail "$f  http $code"
  elif [ "$(exact_md5 "$V/http.tmp")" = "$after" ]; then pass "$f  served correctly"
  else warn "$f  served, but bytes differ from ours (Cloudflare may re-encode/minify)"; fi
done < <(grep -v '^#' "$MAN")
rm -f "$V/http.tmp"

# ---- 3. live pages, bypassing caches
echo; echo "3. Live pages (cache bypassed, tests the server itself)"
run_probes "$SITE" bust "$( [ -f "$LOG/probes-extra.txt" ] && echo "$LOG/probes-extra.txt" )"

# ---- 4. as visitors see it
if [ "$AFTER" -eq 1 ]; then
  echo; echo "4. Live pages exactly as visitors get them (after Flush Cache)"
  run_probes "$SITE" "" "$( [ -f "$LOG/probes-extra.txt" ] && echo "$LOG/probes-extra.txt" )"
  for i in 1 2; do
    echo "     /co-branding/ cache status: $(curl -skI "$SITE/co-branding/" | tr -d '\r' | grep -iE '^(cf-cache-status)' | tr '\n' ' ')"
  done
fi

summary; OK=$?
if [ "$OK" -ne 0 ]; then
  echo "SOMETHING FAILED. Roll back with:  .claude/deploy/rollback.sh $TS --go"
  exit 1
fi

# ---- record
if [ "$RECORD" -eq 1 ]; then
  STATE="$DEPLOY_DIR/live-state.txt"; touch "$STATE"
  while IFS='|' read -r f cat before after backup; do
    case "$f" in *.php|*.js|*.css|*.json|*.html) grep -vF "  $f" "$STATE" > "$STATE.tmp"; mv "$STATE.tmp" "$STATE"; echo "$(norm_md5 "$V/$f")  $f" >> "$STATE" ;; esac
  done < <(grep -v '^#' "$MAN")
  sort -k2 "$STATE" -o "$STATE"
  {
    echo; echo "## $(date '+%Y-%m-%d %H:%M') - deploy $TS (commit $(git rev-parse --short HEAD))"
    grep -v '^#' "$MAN" | cut -d'|' -f1,2 | sed 's/^/- /; s/|/  (/; s/$/)/'
    echo "- verified: round trip + live pages passed ($PASS checks, $WARN warnings). Server backups: *.bak.$TS. Rollback: .claude/deploy/rollback.sh $TS --go"
  } >> "$DEPLOY_DIR/../deploy-log.md"
  echo "Recorded: live baseline updated and entry added to .claude/deploy-log.md."
  echo "Now commit + push to GitHub (every deploy includes git)."
fi
[ "$AFTER" -eq 0 ] && echo "Next: ask Pete to press Flush Cache, then:  .claude/deploy/4-verify.sh $TS --after-flush --record"
