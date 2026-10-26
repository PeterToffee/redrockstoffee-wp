#!/bin/bash
# STAGE 1 - COMPARE: what would a deploy change, and has anyone edited the live site behind our back?
#
#   .claude/deploy/1-compare.sh              compare committed (HEAD) files against the live server. Read-only.
#   .claude/deploy/1-compare.sh --snapshot   same, then remember the live files as the known-good baseline (drift check).
#
# It reports, for every code file (php/js/css/json/html):
#   SAME  - live already matches what is committed (ignoring Windows/Unix line endings)
#   DIFF  - live differs from what is committed  (a unified diff is saved under .deploy-tmp/report/)
#   NEW   - file does not exist on live yet
#   LIVE-ONLY - file exists on live but not in the repo (someone edited/uploaded on the server)
#   DRIFT - live file changed since our last recorded deploy (live-state.txt)
# and for images/audio/fonts: NEW (404 on live) or size differences. Nothing is uploaded or changed on the server.
# The DIFF + NEW lists become the deploy set (.deploy-tmp/deploy-set.txt) used by the later stages.

source "$(dirname "$0")/lib.sh"
SNAPSHOT=0; REUSE=0
for a in "$@"; do
  [ "$a" = "--snapshot" ] && SNAPSHOT=1
  [ "$a" = "--reuse" ] && REUSE=1      # skip the download and re-use the live copies from the last run (fast re-check)
done
STATE="$DEPLOY_DIR/live-state.txt"

echo "== Stage 1: compare committed files with the live server =="
echo "Committed version: $(git rev-parse --short HEAD)  $(git log -1 --format=%s | cut -c1-70)"
[ "$REUSE" -eq 0 ] && rm -rf "$LIVEDIR"
rm -rf "$REPORT"; mkdir -p "$LIVEDIR" "$REPORT"
export_head
list_code_files  > "$TMP/codefiles.txt"
list_asset_files > "$TMP/assetfiles.txt"
echo "Committed code files: $(wc -l < "$TMP/codefiles.txt")   asset files: $(wc -l < "$TMP/assetfiles.txt")"

# --- download the live copy of every committed code file (small chunks; one SFTP session per chunk) ---
download_live() {
  local list="$1"
  while read -r f; do mkdir -p "$LIVEDIR/$(dirname "$f")"; done < "$list"
  rm -f "$TMP"/chunk.*; split -l 50 "$list" "$TMP/chunk."
  for c in "$TMP"/chunk.*; do
    # Absolute destination paths on purpose: the SFTP wrapper starts in the repo root, so a relative path
    # (or a failed "lcd") would drop live files into the repo instead of the scratch folder.
    local WLIVE; WLIVE="$(winpath "$LIVEDIR")"
    { while read -r f; do echo "get \"$REMOTE_TARGET/$f\" \"$WLIVE/$f\""; done < "$c"; } | sftp_run >/dev/null 2>&1
  done
  rm -f "$TMP"/chunk.*
}
if [ "$REUSE" -eq 0 ]; then
  echo "Downloading live copies (this takes a couple of minutes)..."
  download_live "$TMP/codefiles.txt"
  # retry anything that did not arrive, once, so a hiccup is not mistaken for a NEW file
  : > "$TMP/retry.txt"; while read -r f; do [ -f "$LIVEDIR/$f" ] || echo "$f" >> "$TMP/retry.txt"; done < "$TMP/codefiles.txt"
  [ -s "$TMP/retry.txt" ] && download_live "$TMP/retry.txt"
else
  echo "Re-using the live copies from the last run (--reuse)."
fi
# sanity guard: if almost nothing downloaded, the connection failed - do not report everything as NEW.
# NOTE: unlike TheUnframer (an established site with many live files), RedRocksToffee's
# rrt-label-configurator plugin has never been deployed, so a FIRST-EVER compare run is
# expected to legitimately download ~0 files (everything is NEW). This guard would
# wrongly abort that case - if the very first run reports "only 0 live files downloaded",
# check it's really a connection failure (e.g. `printf 'pwd\n' | .claude/godaddy-sftp.sh`)
# and not just "nothing is live yet" before trusting this guard blindly.
GOT=$(find "$LIVEDIR" -type f | wc -l)
if [ "$GOT" -lt 20 ]; then echo "WARNING: only $GOT live files downloaded. If this is the FIRST deploy of this plugin, that may be expected (nothing live yet) - verify the SFTP connection itself before assuming everything is NEW."; fi

# --- classify ---
: > "$TMP/same.txt"; : > "$TMP/diff.txt"; : > "$TMP/new.txt"; : > "$TMP/drift.txt"
while read -r f; do
  if [ ! -f "$LIVEDIR/$f" ]; then echo "$f" >> "$TMP/new.txt"; continue; fi
  if [ "$(norm_md5 "$HEADDIR/$f")" = "$(norm_md5 "$LIVEDIR/$f")" ]; then
    echo "$f" >> "$TMP/same.txt"
  else
    echo "$f" >> "$TMP/diff.txt"
    mkdir -p "$REPORT/$(dirname "$f")"
    diff -u --strip-trailing-cr "$LIVEDIR/$f" "$HEADDIR/$f" > "$REPORT/$f.diff"
  fi
  # drift: is live different from the last state we recorded?
  if [ -f "$STATE" ]; then
    rec=$(grep -F "  $f" "$STATE" | head -1 | cut -d' ' -f1)
    [ -n "$rec" ] && [ "$rec" != "$(norm_md5 "$LIVEDIR/$f")" ] && echo "$f" >> "$TMP/drift.txt"
  fi
done < "$TMP/codefiles.txt"

# --- live-only files: list each folder on the server and see what the repo does not have ---
: > "$TMP/liveonly.txt"
( cd "$HEADDIR" && find . -type d | sed 's#^\./##; s#^\.$##' ) | sort > "$TMP/dirs.txt"
{ while read -r d; do echo "ls \"$REMOTE_TARGET${d:+/$d}\""; done < "$TMP/dirs.txt"; } | sftp_run 2>/dev/null \
  | grep "^$REMOTE_TARGET/" | sed "s#^$REMOTE_TARGET/##; s/[[:space:]]*$//" | sort -u > "$TMP/liveall.txt"
grep -E "$CODE_REGEX" "$TMP/liveall.txt" | grep -Ev "$EXCLUDE_REGEX" | sort > "$TMP/livecode.txt"
# backup copies inside the public plugin folder would be readable by anyone
grep -E '\.(bak|rolledback|removed)\.[0-9A-Za-z_-]+$' "$TMP/liveall.txt" > "$TMP/live-baks.txt"
comm -13 "$TMP/codefiles.txt" "$TMP/livecode.txt" > "$TMP/liveonly.txt"

# --- assets (images/audio/fonts): public URLs, so check them over HTTP ---
asset_check() {  # prints: httpcode|remote-size|local-size|path
  local f="$1"; local url="$SITE/wp-content/plugins/rrt-label-configurator/$f"
  local out; out=$(curl -skI --max-time 20 "$url" | tr -d '\r')
  local code; code=$(echo "$out" | head -1 | awk '{print $2}')
  local size; size=$(echo "$out" | grep -i '^content-length:' | awk '{print $2}')
  echo "${code:-000}|${size:-?}|$(stat -c%s "$HEADDIR/$f")|$f"
}
export -f asset_check; export SITE HEADDIR
: > "$TMP/asset-new.txt"; : > "$TMP/asset-size.txt"
xargs -a "$TMP/assetfiles.txt" -P 8 -I{} bash -c 'asset_check "$1"' _ {} > "$TMP/asset-results.txt" 2>/dev/null
while IFS='|' read -r code rsize lsize f; do
  if [ "$code" = "404" ]; then echo "$f" >> "$TMP/asset-new.txt"
  elif [ "$rsize" != "?" ] && [ "$rsize" != "$lsize" ]; then echo "$f (live ${rsize} bytes, committed ${lsize})" >> "$TMP/asset-size.txt"
  fi
done < "$TMP/asset-results.txt"

# --- deploy set = NEW + DIFF code files + NEW assets ---
{ while read -r f; do echo "NEW|$f"; done < "$TMP/new.txt"
  while read -r f; do echo "DIFF|$f"; done < "$TMP/diff.txt"
  while read -r f; do echo "ASSET-NEW|$f"; done < "$TMP/asset-new.txt"; } > "$TMP/deploy-set.txt"

# --- optional baseline ---
if [ "$SNAPSHOT" -eq 1 ]; then
  : > "$STATE"; while read -r f; do [ -f "$LIVEDIR/$f" ] && echo "$(norm_md5 "$LIVEDIR/$f")  $f" >> "$STATE"; done < "$TMP/codefiles.txt"
  echo "Baseline saved: $(wc -l < "$STATE") live files recorded in .claude/deploy/live-state.txt"
fi

# --- report ---
show() {  # title file
  local n; n=$(wc -l < "$2"); echo; echo "$1: $n"; [ "$n" -gt 0 ] && sed 's/^/    /' "$2"
}
echo; echo "==================== RESULT ===================="
echo "SAME as live:      $(wc -l < "$TMP/same.txt")"
{ while read -r f; do echo "$f   (+$(grep -c '^+[^+]' "$REPORT/$f.diff") / -$(grep -c '^-[^-]' "$REPORT/$f.diff") lines)"; done < "$TMP/diff.txt"; } > "$TMP/diff-show.txt"
show "DIFF (live differs from committed - will be changed)" "$TMP/diff-show.txt"
show "NEW (not on live yet - will be added)" "$TMP/new.txt"
show "ASSET-NEW (image/audio/font missing on live - will be added)" "$TMP/asset-new.txt"
show "LIVE-ONLY (on the server but not in the repo - review!)" "$TMP/liveonly.txt"
show "DRIFT (live changed since our last recorded state - someone edited live?)" "$TMP/drift.txt"
show "ASSET size differs (informational: Cloudflare can re-encode images)" "$TMP/asset-size.txt"
BAKN=$(wc -l < "$TMP/live-baks.txt")
[ "$BAKN" -gt 0 ] && { echo; echo "WARNING: $BAKN backup files sit inside the PUBLIC plugin folder on the server (anyone can read them)."; }
echo
echo "Deploy set saved: $(wc -l < "$TMP/deploy-set.txt") files (.deploy-tmp/deploy-set.txt). Diffs are in .deploy-tmp/report/."
echo "Next: .claude/deploy/2-preflight.sh"
