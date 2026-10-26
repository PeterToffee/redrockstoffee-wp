#!/bin/bash
# STAGE 2 - PREFLIGHT: catch "missed parts" and known bug classes BEFORE anything touches the live site.
# Run after stage 1 (it reads the deploy set that stage 1 wrote). Changes nothing on the server.
#
#   .claude/deploy/2-preflight.sh
#
# Checks (each prints [PASS] / [WARN] / [FAIL]; any FAIL means "do not deploy yet"):
#   A  git state          - deploy set is committed and pushed; unfinished local edits are NOT part of the deploy
#   B  php syntax         - every PHP file being deployed parses
#   C  missing pieces     - every file the deployed code points at (require, enqueued js/css, JS imports, images)
#                           exists in the deploy set or already on live
#   D  module versions    - ES-module imports of one file must use the same ?v= number
#   E  non-file steps     - things a file upload cannot do (flush permalinks, create a page, add a role, ...)
#   F  cache safety       - the shortcode's own HTML must never bake in the rrt_label_submit nonce directly
#   G  local page checks  - probes.txt against the local site
#   H  browser check      - headless Edge loads /co-branding/, no JavaScript errors
#   I  version bump       - CSS/JS changed, so RRT_LABEL_VERSION must be higher than live (cache busting)
#
# Pete, 2026-10-26: adapted from TheUnframer-WP's own 2-preflight.sh. This repo's
# deploy target is a PLUGIN (rrt-label-configurator), not a theme, so the
# theme-only checks (get_template_part()/is_page_template()) are dropped, and
# sections F/H/I are rewritten around this plugin's own actual mechanisms
# (its existing rrt_label_fresh_nonce pattern, /co-branding/, RRT_LABEL_VERSION)
# instead of TheUnframer's.

source "$(dirname "$0")/lib.sh"
SET="$TMP/deploy-set.txt"
[ -f "$SET" ] && [ -d "$HEADDIR" ] && [ -d "$LIVEDIR" ] || { echo "Run .claude/deploy/1-compare.sh first."; exit 2; }
cut -d'|' -f2 "$SET" > "$TMP/setfiles.txt"
in_set() { grep -qxF -- "$1" "$TMP/setfiles.txt"; }
echo "== Stage 2: preflight on $(wc -l < "$TMP/setfiles.txt") files to deploy =="

# ---------------------------------------------------------------- A: git state
echo; echo "A. Git state"
UNPUSHED=$(git rev-list --count origin/main..HEAD 2>/dev/null || echo "?")
if [ "$UNPUSHED" = "0" ]; then pass "everything committed is pushed to GitHub"; else warn "$UNPUSHED commit(s) not pushed to GitHub yet (every deploy also pushes)"; fi
MODIFIED=$(git status --porcelain -- "$LOCAL_TARGET" | grep -E '^( M|M |MM)' | sed 's/^...//')
if [ -n "$MODIFIED" ]; then warn "unfinished local edits exist and will NOT be deployed (only committed files go up):"; echo "$MODIFIED" | sed 's/^/           /'; else pass "no uncommitted edits to tracked plugin files"; fi

# ---------------------------------------------------------------- B: php syntax
echo; echo "B. PHP syntax"
BAD=0
while read -r f; do
  case "$f" in *.php) out=$("$PHP" -l "$(winpath "$HEADDIR/$f")" 2>&1) || { fail "$f: $out"; BAD=1; } ;; esac
done < "$TMP/setfiles.txt"
[ "$BAD" -eq 0 ] && pass "all PHP files in the deploy set parse cleanly"

# ---------------------------------------------------------------- C: missing pieces
echo; echo "C. Missing pieces (files the deployed code points at)"
ref_exists() {  # $1 = path relative to the plugin folder
  local r="$1"
  echo "$r" | grep -Eq "$EXCLUDE_REGEX" && return 0
  in_set "$r" && return 0
  [ -f "$LIVEDIR/$r" ] && return 0
  if echo "$r" | grep -Eq "$ASSET_REGEX"; then
    [ "$(curl -skI -m 20 -o /dev/null -w '%{http_code}' "$SITE/wp-content/plugins/rrt-label-configurator/$r")" = "200" ] && return 0
  fi
  return 1
}
: > "$TMP/refs-missing.txt"
report_missing() {  # kind file ref severity
  echo "$4|$1|$2|$3" >> "$TMP/refs-missing.txt"
}
while read -r f; do
  file="$HEADDIR/$f"; dir=$(dirname "$f")
  case "$f" in
    *.php)
      # require / include of another PHP file
      grep -E "(require|include)(_once)?" "$file" | grep -vE "ABSPATH|WPINC|WP_CONTENT_DIR|WP_PLUGIN_DIR" | grep -oE "['\"][^'\"\$]+\.php['\"]" | tr -d "'\"" | sed 's#^/##' | sort -u | while read -r r; do
        case "$r" in wp-admin/*|wp-includes/*|vendor/*|..*) continue ;; esac
        ref_exists "$r" || report_missing "require" "$f" "$r" FAIL
      done ;;
  esac
  case "$f" in
    *.php|*.js)
      # plugin asset paths written out in full (assets/js/x.js, assets/images/y.png, ...)
      grep -oE "assets/[A-Za-z0-9_./-]+\.(js|css|png|jpe?g|gif|svg|webp|mp3|mp4|woff2?|ttf|json)" "$file" | sort -u | while read -r r; do
        if ! ref_exists "$r"; then
          case "$r" in *.js|*.css) report_missing "asset" "$f" "$r" FAIL ;; *) report_missing "asset" "$f" "$r" WARN ;; esac
        fi
      done ;;
  esac
  case "$f" in
    *.js)
      # relative ES-module imports: from './x.js'  /  import('./x.js')
      grep -oE "(from|import\()[[:space:]]*['\"]\.{1,2}/[^'\"?]+" "$file" | sed -E "s/.*['\"]//" | sort -u | while read -r rel; do
        r=$(realpath -m --relative-to="$HEADDIR" "$HEADDIR/$dir/$rel")
        ref_exists "$r" || report_missing "js import" "$f" "$r" FAIL
      done ;;
  esac
done < "$TMP/setfiles.txt"
if [ -s "$TMP/refs-missing.txt" ]; then
  while IFS="|" read -r sev kind f r; do
    if [ "$sev" = "FAIL" ]; then fail "$f points at missing $kind: $r"; else warn "$f mentions $kind not found on live or in the deploy set: $r"; fi
  done < <(sort -u "$TMP/refs-missing.txt")
else pass "every referenced file is either in the deploy set or already on live"; fi
# the reverse: a NEW file that nothing points at may be a forgotten dependency on the other side
while IFS='|' read -r kind f; do
  case "$f" in inc/*.php) [ "$kind" = "NEW" ] || continue; base=$(basename "$f")
    if ! grep -rqE "^[[:space:]]*(require|include)(_once)?[^;]*inc/$base" "$HEADDIR" --include=*.php --exclude="$base"; then
      fail "$f is new but no committed file loads it (no require of inc/$base) - the feature would upload but stay switched off"
    fi ;;
  esac
done < "$SET"

# ---------------------------------------------------------------- D: module versions
echo; echo "D. ES-module ?v= consistency"
MISMATCH=$(grep -rhoE "\./[A-Za-z0-9_.-]+\.js(\?v=[0-9]+)?['\"]" --include=*.js "$HEADDIR" | tr -d "'\"" | sort -u \
  | awk -F'[?]v=' '{n=$1; v=(NF>1)?$2:"none"; c[n]=c[n]" "v; k[n]++} END{for(n in k) if(k[n]>1) print n" uses versions:"c[n]}')
if [ -n "$MISMATCH" ]; then warn "same module imported with different ?v= (two module copies = broken shared state). Already the case on live if the file is unchanged:"; echo "$MISMATCH" | sed 's/^/           /'; else pass "each module is imported with one consistent ?v="; fi

# ---------------------------------------------------------------- E: non-file steps
echo; echo "E. Steps a file upload cannot do"
NONFILE=0
advice() {  # pattern | advice
  local pat="$1" adv="$2" hits=""
  while IFS='|' read -r kind f; do
    case "$kind" in
      DIFF) grep -qE "^\+.*($pat)" "$REPORT/$f.diff" 2>/dev/null && hits="$hits $f" ;;
      NEW)  case "$f" in *.php) grep -qE "$pat" "$HEADDIR/$f" && hits="$hits $f" ;; esac ;;
    esac
  done < "$SET"
  [ -n "$hits" ] && { warn "$adv   (in:$hits)"; NONFILE=1; }
}
advice "register_post_type|register_taxonomy|add_rewrite_rule|add_rewrite_tag|flush_rewrite_rules" "new content types / URLs: after deploy save Settings -> Permalinks once so the new addresses work"
advice "add_role|remove_role|add_cap|->add_cap" "user roles/capabilities changed: check the role exists and the right users have it"
advice "wp_schedule_event|register_activation_hook|register_deactivation_hook" "scheduled task / activation code: it may need a one-time trigger (plugin deactivate/reactivate)"
advice "add_option|update_option|register_setting" "site options touched: check the setting exists / has a sensible default on live"
advice "dbDelta|ALTER TABLE|CREATE TABLE" "database change: needs a database step, not just files"
advice "acf_add_options_page|acf_add_local_field_group" "ACF fields/options page defined in code: check they appear correctly in the admin"
[ "$NONFILE" -eq 0 ] && pass "no permalink / role / cron / database steps detected"

# ---------------------------------------------------------------- F: cache safety
echo; echo "F. Cache safety (does the shortcode's own HTML bake in an expiring token?)"
# Pete, 2026-10-26: this plugin already solved the exact problem TheUnframer's
# equivalent check was built to catch - inc/handle-submission.php's own header
# comment: "This page can be served from cache... bakes a nonce into the cached
# HTML that goes stale... JS fetches a fresh one right before submit instead of
# trusting whatever nonce shipped with the (possibly cached) page - same pattern
# used on theunframer.com after a stale-nonce 403 bug there." This check confirms
# that pattern still holds: the real submit nonce is never printed directly into
# the shortcode's own HTML output (only ever handed out live via the
# rrt_label_fresh_nonce AJAX action). If a future change ever bakes it in
# directly, THIS is what would catch it.
CACHEBAD=0
body=$(curl -sk -m 30 "$LOCAL_SITE/co-branding/" 2>/dev/null)
if [ -n "$body" ]; then
  if echo "$body" | grep -qE "rrt_label_submit[^a-zA-Z_].{0,200}\"nonce\":\"[0-9a-f]+\"|name=.nonce. value=.[0-9a-f]+."; then
    fail "/co-branding/ appears to bake a real nonce value directly into its HTML - a cached copy would go stale (12-24h) and submissions would 403. Confirm rrt_label_fresh_nonce is still being used to refresh it client-side before submit."
  else
    pass "/co-branding/ does not bake a nonce value directly into its HTML (fetched fresh via rrt_label_fresh_nonce before submit)"
  fi
else
  warn "could not fetch $LOCAL_SITE/co-branding/ to check - is local XAMPP running?"
fi

# ---------------------------------------------------------------- G: local page checks
echo; echo "G. Page checks on the LOCAL site ($LOCAL_SITE)"
run_probes "$LOCAL_SITE"

# ---------------------------------------------------------------- H: browser check
echo; echo "H. Headless browser check of /co-branding/ (local)"
# Pete, 2026-10-26: scoped down from TheUnframer's equivalent check - this
# plugin's JS has no "initialized successfully" console marker to look for
# (that one exists in TheUnframer's codebase specifically to serve this check).
# Checks for the absence of JS errors only; add a real init marker later if a
# full-parity smoke test is wanted.
if [ -x "$EDGE" ] || [ -f "$EDGE" ]; then
  "$EDGE" --headless --disable-gpu --enable-logging=stderr --v=0 --virtual-time-budget=12000 --dump-dom "$LOCAL_SITE/co-branding/" > "$TMP/edge-dom.html" 2> "$TMP/edge.err"
  ERRS=$(grep "INFO:CONSOLE" "$TMP/edge.err" | grep -v "chrome-extension://" | grep -E "Uncaught|SyntaxError|ReferenceError|TypeError" | cut -c1-200)
  if [ -n "$ERRS" ]; then fail "JavaScript errors on /co-branding/:"; echo "$ERRS" | sed 's/^/           /'; else pass "no JavaScript errors on /co-branding/"; fi
else warn "Edge not found - skipped browser check"; fi

# ---------------------------------------------------------------- I: version bump
echo; echo "I. Version bump (cache busting)"
NEEDBUMP=0; while read -r f; do case "$f" in rrt-label-configurator.php) ;; *.css|*.js) NEEDBUMP=1 ;; esac; done < "$TMP/setfiles.txt"
LIVEVER=$(grep -m1 -E "define\( *'RRT_LABEL_VERSION'" "$LIVEDIR/rrt-label-configurator.php" 2>/dev/null | grep -oE "'[0-9][0-9.]*'" | tail -1 | tr -d "'")
HEADVER=$(grep -m1 -E "define\( *'RRT_LABEL_VERSION'" "$HEADDIR/rrt-label-configurator.php" | grep -oE "'[0-9][0-9.]*'" | tail -1 | tr -d "'")
if [ "$NEEDBUMP" -eq 1 ]; then
  if [ "$LIVEVER" != "$HEADVER" ] && in_set "rrt-label-configurator.php"; then pass "CSS/JS changed and RRT_LABEL_VERSION goes ${LIVEVER:-(none live)} -> $HEADVER (rrt-label-configurator.php is in the deploy set)"
  else fail "CSS/JS changed but RRT_LABEL_VERSION is not being raised (live ${LIVEVER:-none}, committed $HEADVER, rrt-label-configurator.php in set: $(in_set rrt-label-configurator.php && echo yes || echo no)) - visitors would keep old cached files"; fi
else pass "no CSS/JS changes, no version bump needed"; fi

if summary; then
  git rev-parse HEAD > "$TMP/preflight.ok"      # stage 3 refuses to run unless preflight passed for this exact commit
  echo "Preflight passed. Deploy only after Pete says go:  .claude/deploy/3-deploy.sh --go"
else
  rm -f "$TMP/preflight.ok"; echo "Fix the FAIL items above before deploying."
fi
