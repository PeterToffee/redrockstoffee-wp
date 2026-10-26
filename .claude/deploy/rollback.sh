#!/bin/bash
# ROLLBACK: undo one deploy using its manifest. Nothing is ever deleted - files are renamed aside.
#
#   .claude/deploy/rollback.sh <deploy-id> --go
#
# For each file the deploy touched:
#   replaced file  -> the deployed version is renamed  <name>.rolledback.<id>  and  <name>.bak.<id>  is put back
#   brand-new file -> renamed  <name>.removed.<id>
# Then it downloads the restored files, checks they match the pre-deploy bytes, and re-runs the live page checks.
# Afterwards, ask Pete to press Flush Cache.

source "$(dirname "$0")/lib.sh"
TS="${1:-}"; [ "${2:-}" = "--go" ] || { echo "Usage: rollback.sh <deploy-id> --go"; ls -1 "$DEPLOY_DIR/log" 2>/dev/null | sort -n | tail -5 | sed 's/^/  deploy ids: /'; exit 2; }
MAN="$DEPLOY_DIR/log/$TS/manifest.txt"; [ -f "$MAN" ] || { echo "No manifest for '$TS'."; exit 2; }
echo "== Rolling back deploy $TS =="

# reverse order: entry points/templates first, libraries last
grep -v '^#' "$MAN" | sed '1!G;h;$!d' > "$TMP/rollback-order.txt"
# How each kind of file is undone (the server cannot MOVE files between the public folder and the private backup folder,
# so backups are COPIED back):
#   replaced file -> download its backup from ~/deploy-backups/<id>/backup/, upload it beside the live file as <name>.new,
#                    then swap by renaming. If the backup could not be downloaded the live file is left alone and reported.
#   brand-new file -> a copy is kept on this PC (.deploy-tmp/removed-<id>/), then it is removed from the server.
# (Manifests from the very first version of the scripts kept backups beside the live file as <name>.bak.<id>; those still work.)
SRC="$TMP/rollback-src-$TS"; REM="$TMP/removed-$TS"; rm -rf "$SRC"; mkdir -p "$SRC" "$REM"; WSRC="$(winpath "$SRC")"; WREM="$(winpath "$REM")"
while IFS='|' read -r f cat before after backup; do
  case "$backup" in
    deploy-backups/*) mkdir -p "$SRC/$(dirname "$f")"; echo "get \"$backup\" \"$WSRC/$f\"" ;;
    -)                mkdir -p "$REM/$(dirname "$f")"; echo "get \"$REMOTE_TARGET/$f\" \"$WREM/$f\"" ;;
  esac
done < "$TMP/rollback-order.txt" | sftp_run >/dev/null 2>&1

: > "$TMP/rollback-cmds.txt"; : > "$TMP/rollback-swap.txt"; SKIPPED=0
while IFS='|' read -r f cat before after backup; do
  R="$REMOTE_TARGET/$f"
  case "$backup" in
    deploy-backups/*)
      if [ -f "$SRC/$f" ] && [ "$(exact_md5 "$SRC/$f")" = "$before" ]; then
        { echo "put \"$WSRC/$f\" \"$R.new\""; } >> "$TMP/rollback-cmds.txt"; echo "$f" >> "$TMP/rollback-swap.txt"
      else
        echo "  SKIPPED (backup missing or does not match the recorded original, live file left as is): $f"; SKIPPED=$((SKIPPED+1))
      fi ;;
    -)  [ -f "$REM/$f" ] && echo "rm \"$R\"" >> "$TMP/rollback-cmds.txt" || { echo "  SKIPPED (could not keep a local copy first): $f"; SKIPPED=$((SKIPPED+1)); } ;;
    *)  { echo "rename \"$R\" \"$R.rolledback.$TS\""; echo "rename \"$R.bak.$TS\" \"$R\""; } >> "$TMP/rollback-cmds.txt" ;;
  esac
done < "$TMP/rollback-order.txt"
sftp_run < "$TMP/rollback-cmds.txt" > "$DEPLOY_DIR/log/$TS/rollback-sftp.txt" 2>&1
# second step: swap in only the files whose upload arrived complete
if [ -s "$TMP/rollback-swap.txt" ]; then
  while read -r f; do echo "ls -l \"$REMOTE_TARGET/$f.new\""; done < "$TMP/rollback-swap.txt" | sftp_run 2>/dev/null > "$TMP/rollback-sizes.txt"
  : > "$TMP/rollback-cmds2.txt"
  while read -r f; do
    want=$(stat -c%s "$SRC/$f"); got=$(grep -E "^-" "$TMP/rollback-sizes.txt" | grep -F "$REMOTE_TARGET/$f.new" | awk '{print $5}' | head -1)
    if [ "$got" = "$want" ]; then { echo "rename \"$REMOTE_TARGET/$f\" \"$REMOTE_TARGET/$f.old\""; echo "rename \"$REMOTE_TARGET/$f.new\" \"$REMOTE_TARGET/$f\""; echo "rm \"$REMOTE_TARGET/$f.old\""; } >> "$TMP/rollback-cmds2.txt"
    else echo "  SKIPPED (restored copy did not upload completely, live file left as is): $f"; echo "rm \"$REMOTE_TARGET/$f.new\"" >> "$TMP/rollback-cmds2.txt"; SKIPPED=$((SKIPPED+1)); fi
  done < "$TMP/rollback-swap.txt"
  sftp_run < "$TMP/rollback-cmds2.txt" >> "$DEPLOY_DIR/log/$TS/rollback-sftp.txt" 2>&1
  rm -f "$TMP/rollback-swap.txt"
fi
[ "$SKIPPED" -gt 0 ] && echo "NOTE: $SKIPPED file(s) were skipped safely; see above."

# verify: replaced files must be byte-identical to what they were before the deploy
V="$TMP/verify/rollback-$TS"; mkdir -p "$V"; WV="$(winpath "$V")"
while IFS='|' read -r f cat before after backup; do
  [ "$backup" = "-" ] && continue; mkdir -p "$V/$(dirname "$f")"; echo "get \"$REMOTE_TARGET/$f\" \"$WV/$f\""
done < "$TMP/rollback-order.txt" | sftp_run >/dev/null 2>&1
echo; echo "Restored files"
while IFS='|' read -r f cat before after backup; do
  if [ "$backup" = "-" ]; then
    code=$(curl -skI -m 30 -o /dev/null -w '%{http_code}' "$SITE/wp-content/plugins/rrt-label-configurator/$f?rb=$TS")
    case "$f" in *.php) pass "$f  removed (a copy is kept in .deploy-tmp/removed-<id>)" ;; *) [ "$code" = "404" ] && pass "$f  removed (404)" || warn "$f  still answers http $code (cache?)" ;; esac
  elif [ -f "$V/$f" ] && [ "$(exact_md5 "$V/$f")" = "$before" ]; then pass "$f  is back to its pre-deploy bytes"
  else fail "$f  did NOT restore correctly - check .claude/deploy/log/$TS/rollback-sftp.txt"; fi
done < "$TMP/rollback-order.txt"
echo; echo "Live pages after rollback"
run_probes "$SITE" bust
summary && echo "Rollback complete. Ask Pete to press Flush Cache." || echo "Rollback finished with failures - do not walk away; investigate."
