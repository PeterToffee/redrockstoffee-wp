#!/bin/bash
# STAGE 3 - DEPLOY: upload the deploy set to the live site, with a server-side backup of every file it replaces.
# ONLY run this after Pete has explicitly said to deploy.
#
#   .claude/deploy/3-deploy.sh --go                      deploy everything stage 1 put in the deploy set
#   .claude/deploy/3-deploy.sh --go --files a.php,b.js   deploy only these (paths relative to the plugin folder)
#   .claude/deploy/3-deploy.sh --go --files a.php --force   re-upload even if live already matches (used for dry runs)
#   .claude/deploy/3-deploy.sh --dry-run                 show what would happen, change nothing
#
# What it does, in order:
#   1. refuses unless preflight (stage 2) passed for the current commit
#   2. re-downloads the live copy of every file it will replace and aborts if live changed since the compare
#   3. uploads files in dependency order (assets, js/css, inc/, main plugin file last)
#      each replaced file is first copied to a PRIVATE folder on the server (~/deploy-backups/<id>/, outside the public web folder)
#   4. writes a manifest (.claude/deploy/log/<timestamp>/manifest.txt) that stage 4 and rollback.sh use
# To ship a hand-made "minimal patch" of a file instead of the committed version, put that file at
#   .claude/deploy/overrides/<path relative to plugin folder>   and it is used instead of the committed copy.

source "$(dirname "$0")/lib.sh"
GO=0; DRY=0; FORCE=0; ONLY=""
while [ $# -gt 0 ]; do
  case "$1" in
    --go) GO=1 ;; --dry-run) DRY=1 ;; --force) FORCE=1 ;;
    --files) shift; ONLY="$1" ;;
    *) echo "unknown option $1"; exit 2 ;;
  esac; shift
done
[ "$GO" -eq 1 ] || [ "$DRY" -eq 1 ] || { echo "Refusing to run without --go (or --dry-run). Deploy only when Pete says so."; exit 2; }

SET="$TMP/deploy-set.txt"
[ -f "$SET" ] || { echo "Run stages 1 and 2 first."; exit 2; }
if [ "$FORCE" -eq 0 ] || [ -z "$ONLY" ]; then
  [ "$(cat "$TMP/preflight.ok" 2>/dev/null)" = "$(git rev-parse HEAD)" ] || { echo "Preflight has not passed for the current commit ($(git rev-parse --short HEAD)). Run 2-preflight.sh."; exit 2; }
fi

# ---- choose the files (sorted so dependencies go first)
: > "$TMP/todo.txt"
if [ -n "$ONLY" ]; then
  echo "$ONLY" | tr ',' '\n' | while read -r f; do
    cat="$(grep -F "|$f" "$SET" | head -1 | cut -d'|' -f1)"
    if [ -z "$cat" ]; then
      [ "$FORCE" -eq 1 ] && [ -f "$HEADDIR/$f" ] && cat="FORCE" || { echo "Skipping $f (not in the deploy set; use --force to re-upload identical files)"; continue; }
    fi
    echo "$cat|$f" >> "$TMP/todo.txt"
  done
else cp "$SET" "$TMP/todo.txt"; fi
while IFS='|' read -r cat f; do echo "$(deploy_rank "$f")|$cat|$f"; done < "$TMP/todo.txt" | sort -t'|' -k1,1n -k3,3 > "$TMP/todo.sorted"
N=$(wc -l < "$TMP/todo.sorted"); [ "$N" -gt 0 ] || { echo "Nothing to deploy."; exit 0; }
src_for() { if [ -f "$DEPLOY_DIR/overrides/$1" ]; then echo "$DEPLOY_DIR/overrides/$1"; else echo "$HEADDIR/$1"; fi; }

echo "== Stage 3: deploying $N file(s) =="
while IFS='|' read -r rank cat f; do
  s=$(src_for "$f"); tag=""; [ "$s" != "$HEADDIR/$f" ] && tag="  [USING OVERRIDE]"
  printf "  %-10s %s%s\n" "$cat" "$f" "$tag"
done < "$TMP/todo.sorted"
[ "$DRY" -eq 1 ] && { echo "(dry run - nothing changed)"; exit 0; }

# ---- guard: has live changed since the compare?
echo; echo "Checking live has not changed since the compare..."
GUARD="$TMP/predeploy"; rm -rf "$GUARD"; mkdir -p "$GUARD"
WGUARD="$(winpath "$GUARD")"
while IFS='|' read -r rank cat f; do
  [ -f "$LIVEDIR/$f" ] || continue
  mkdir -p "$GUARD/$(dirname "$f")"; echo "get \"$REMOTE_TARGET/$f\" \"$WGUARD/$f\""
done < "$TMP/todo.sorted" | sftp_run >/dev/null 2>&1
STALE=0
while IFS='|' read -r rank cat f; do
  [ -f "$LIVEDIR/$f" ] || continue
  if [ ! -f "$GUARD/$f" ] || [ "$(exact_md5 "$GUARD/$f")" != "$(exact_md5 "$LIVEDIR/$f")" ]; then echo "  CHANGED ON LIVE since the compare: $f"; STALE=1; fi
done < "$TMP/todo.sorted"
[ "$STALE" -eq 0 ] || { echo "ABORT: live changed after stage 1. Re-run stage 1 and 2, then deploy."; exit 3; }
echo "  live is unchanged. Good."

# ---- go
TS=$(date +%s); LOG="$DEPLOY_DIR/log/$TS"; BK="$TMP/backups/$TS"; mkdir -p "$LOG" "$BK"
MAN="$LOG/manifest.txt"
{ echo "# deploy $TS  $(date '+%Y-%m-%d %H:%M')  commit $(git rev-parse --short HEAD)"
  echo "# path|category|before_md5|after_md5|server_backup"; } > "$MAN"
echo "Deploy id: $TS   (rollback: .claude/deploy/rollback.sh $TS --go)"

# remote folders for brand-new files
# Pete, 2026-10-27: this plugin's FIRST-EVER deploy uploaded "112 file(s)"
# with no error reported, yet 4-verify.sh found nothing live at all - every
# single `put` had actually failed with "No such file or directory". Root
# cause: the loop below only ever creates SUBdirectories UNDER
# $REMOTE_TARGET (it explicitly skips d="." - a file sitting directly in
# the plugin root, like rrt-label-configurator.php itself) - it never
# creates $REMOTE_TARGET itself. TheUnframer's original version of this
# script never needed to, because its target is an established theme
# folder that always already exists; this plugin's target folder did not
# exist yet on a true first deploy. Harmless to repeat on every later
# deploy (an mkdir of an already-existing folder just errors, same as the
# deploy-backups mkdirs below - this whole block's output is discarded).
echo "mkdir \"$REMOTE_TARGET\"" | sftp_run >/dev/null 2>&1
while IFS='|' read -r rank cat f; do
  [ -f "$LIVEDIR/$f" ] && continue; d=$(dirname "$f"); acc=""
  [ "$d" = "." ] && continue
  IFS='/' read -ra parts <<< "$d"; for p in "${parts[@]}"; do acc="${acc:+$acc/}$p"; echo "mkdir \"$REMOTE_TARGET/$acc\""; done
done < "$TMP/todo.sorted" | sort -u | sftp_run >/dev/null 2>&1

# Backups live in a PRIVATE folder in the account's home directory, next to (not inside) the public web folder "html".
# Files inside html are served to anyone, so a backup like footer.php.bak.123 there would hand out our source code.
# Layout:  ~/deploy-backups/<id>/backup/<path>
# (The server cannot MOVE a file between html and the home folder, so the backup is a COPY: we upload the pre-deploy file we
#  just downloaded and proved identical to live in the guard above.)
PRIV="deploy-backups/$TS"
{ echo "mkdir \"deploy-backups\""; echo "mkdir \"$PRIV\""; echo "mkdir \"$PRIV/backup\""
  while IFS='|' read -r rank cat f; do
    [ -f "$LIVEDIR/$f" ] || continue; d=$(dirname "$f"); [ "$d" = "." ] && continue; acc=""
    IFS='/' read -ra parts <<< "$d"; for p in "${parts[@]}"; do acc="${acc:+$acc/}$p"; echo "mkdir \"$PRIV/backup/$acc\""; done
  done < "$TMP/todo.sorted"; } | sort -u | sftp_run > "$LOG/sftp-mkdir.txt" 2>&1

# Files are handled in small chunks. For a file that already exists on live:
#   A) upload the private backup copy and the new file beside the live one (as <name>.new)
#   B) ONLY IF the .new file arrived complete (same size as ours): swap by renaming (same folder, so it works), then drop the old copy.
#      A half-uploaded file is never swapped in: the live file stays as it was and the problem is reported.
# Brand-new files (nothing to replace) are uploaded directly; stage 4 checks them.
i=0; chunk=1; SWAP_FILES=(); PROBLEMS=0
: > "$TMP/batchA.txt"
flush_chunk() {
  [ -s "$TMP/batchA.txt" ] && sftp_run < "$TMP/batchA.txt" > "$LOG/sftp-$chunk-upload.txt" 2>&1
  : > "$TMP/batchB.txt"
  if [ ${#SWAP_FILES[@]} -gt 0 ]; then
    for cf in "${SWAP_FILES[@]}"; do echo "ls -l \"$REMOTE_TARGET/$cf.new\""; done | sftp_run 2>/dev/null > "$TMP/newsizes.txt"
    for cf in "${SWAP_FILES[@]}"; do
      R2="$REMOTE_TARGET/$cf"; want=$(stat -c%s "$(src_for "$cf")")
      got=$(grep -E "^-" "$TMP/newsizes.txt" | grep -F "$R2.new" | awk '{print $5}' | head -1)
      if [ "$got" = "$want" ]; then
        { echo "rename \"$R2\" \"$R2.old\""; echo "rename \"$R2.new\" \"$R2\""; echo "rm \"$R2.old\""; } >> "$TMP/batchB.txt"
      else
        echo "  UPLOAD INCOMPLETE, not swapped (the live file is untouched): $cf  (arrived: ${got:-nothing} of $want bytes)"
        echo "rm \"$R2.new\"" >> "$TMP/batchB.txt"; PROBLEMS=$((PROBLEMS+1))
      fi
    done
    [ -s "$TMP/batchB.txt" ] && sftp_run < "$TMP/batchB.txt" > "$LOG/sftp-$chunk-swap.txt" 2>&1
  fi
  : > "$TMP/batchA.txt"; SWAP_FILES=(); chunk=$((chunk+1))
}
while IFS='|' read -r rank cat f; do
  s=$(src_for "$f"); ws=$(winpath "$s"); R="$REMOTE_TARGET/$f"
  after=$(exact_md5 "$s")
  if [ -f "$LIVEDIR/$f" ]; then
    before=$(exact_md5 "$LIVEDIR/$f"); mkdir -p "$BK/$(dirname "$f")"; cp "$LIVEDIR/$f" "$BK/$f"
    { echo "put \"$(winpath "$LIVEDIR/$f")\" \"$PRIV/backup/$f\""; echo "put \"$ws\" \"$R.new\""; } >> "$TMP/batchA.txt"
    SWAP_FILES+=("$f")
    echo "$f|$cat|$before|$after|$PRIV/backup/$f" >> "$MAN"
  else
    echo "put \"$ws\" \"$R\"" >> "$TMP/batchA.txt"
    echo "$f|$cat|-|$after|-" >> "$MAN"
  fi
  i=$((i+1)); [ $((i % 10)) -eq 0 ] && flush_chunk
done < "$TMP/todo.sorted"
flush_chunk

echo; echo "Processed $i file(s). Old versions are in the private folder ~/$PRIV/backup on the server (not on the web) and locally in .deploy-tmp/backups/$TS."
[ "$PROBLEMS" -gt 0 ] && echo "WARNING: $PROBLEMS file(s) were NOT swapped because their upload was incomplete. Re-run stages 1-3 for them."
echo "Next: .claude/deploy/4-verify.sh $TS"
echo "$TS" > "$TMP/last-deploy-id"
