# How we deploy the RedRocksToffee label configurator (plain-English guide)

Adapted from TheUnframer-WP's own deploy pipeline (same mechanism, same safety
guarantees) — the deployed unit here is the `rrt-label-configurator` **plugin**,
not a theme.

Every deploy follows the same five steps. Each step is a small script in this folder. Nothing is uploaded until step 3, and
step 3 only runs when Pete has said "deploy".

| Step | Command | What it does | Changes the live site? |
|---|---|---|---|
| 1. Compare | `.claude/deploy/1-compare.sh` | Downloads the live copies of our plugin's code files and compares them with what is committed. Lists what would change (DIFF), what is new (NEW), what is on the server but not in our repo (LIVE-ONLY), and whether anyone edited live behind our back (DRIFT). | No |
| 2. Preflight | `.claude/deploy/2-preflight.sh` | Checks for missed parts: PHP syntax, files the new code points at that are missing, a security token that would go stale in the cache, steps a file upload cannot do (flush permalinks, create a page...), local page checks, a headless-browser check of `/co-branding/`, version bump. Any `[FAIL]` = do not deploy. | No |
| 3. Deploy | `.claude/deploy/3-deploy.sh --go` | Uploads the files in a safe order. Each replaced file is first moved to a PRIVATE folder on the server (`~/deploy-backups/<id>/backup/`, outside the public web folder). | **Yes** |
| 4. Verify | `.claude/deploy/4-verify.sh <id>` | Downloads every file back and compares it to what was sent, then checks the live pages. After Pete presses **Flush Cache**, run it again with `--after-flush --record` to test what visitors see and save the result. | No |
| Undo | `.claude/deploy/rollback.sh <id> --go` | Puts every file back exactly as it was before that deploy. Nothing is deleted. | Yes (restores) |

The deploy id is printed by step 3 and stored in `.claude/deploy/log/<id>/manifest.txt`.

## Rules
- **Never deploy without Pete saying so.** The deploy script refuses to run without `--go`.
- Only **committed** files are ever uploaded (the scripts use what is saved in git, so half-finished local edits cannot leak).
- Every deploy also commits and pushes to GitHub, and adds an entry to `.claude/deploy-log.md`.
- Connection to GoDaddy goes through `.claude/godaddy-sftp.sh` (never `sshpass`; it does not work on this PC).
- After every deploy Pete presses **Flush Cache** (GoDaddy dashboard or WordPress toolbar). It clears GoDaddy and Cloudflare.

## Things you can edit by hand
- `probes.txt` - the list of pages that must work, and words that must / must not appear on them. Add a line for any page you never want to silently break.
- The `CONFIG` block at the top of `lib.sh` - site addresses, paths, which file types count as code.
- `overrides/` - to upload a hand-made "minimal patch" of a file instead of the committed version, put that file at
  `.claude/deploy/overrides/<same path as in the plugin folder>`. The deploy script uses it and says `[USING OVERRIDE]`.
  (Use this when a local file also contains unreleased changes you are not shipping.)

## When something looks wrong
- `1-compare.sh` says "only N live files downloaded" -> on a TRUE FIRST deploy of this plugin, 0 is expected (nothing live yet) - but test the connection itself before assuming that: `printf 'pwd\n' | .claude/godaddy-sftp.sh`.
- `LIVE-ONLY` files -> stray files on the server (older manual uploads). Harmless unless one is a leftover you expected to be gone.
- `DRIFT` -> a live file no longer matches the last state we recorded. Someone (or a plugin) edited it. Look at the file before deploying over it.
- A deploy went wrong -> `rollback.sh <id> --go`, then Flush Cache.
- Backups now live in `~/deploy-backups/<id>/` on the server (NOT on the web). Delete old ones through SFTP once you are sure everything is fine.

## First-ever deploy of this plugin: a few things to expect
- Since nothing from this plugin has ever been deployed, `1-compare.sh` will report almost everything as NEW, not DIFF — that's correct, not an error.
- `.env` (GoDaddy SSH credentials for THIS account, not TheUnframer's) must exist at the repo root before stage 1 can connect — see the repo's own setup notes. Never committed (already gitignored).
- Confirm the `html/wp-content/plugins/...` remote path convention actually matches this GoDaddy account on the very first real connection — it was assumed to match TheUnframer's own account layout, not yet verified for this one.

## Why these checks exist (real incidents, carried over from TheUnframer's pipeline)
- A file that pointed at files not yet on the server was almost uploaded alone -> step 2 "missing pieces" scan.
- Uploads failed only on the live site because a security token was baked into a cached page and expired -> step 2 "cache safety" scan (this plugin already builds around this with its own `rrt_label_fresh_nonce` pattern — the check confirms that holds).
- Deploys were blocked for two days by connecting the wrong way -> one wrapper script, documented.
