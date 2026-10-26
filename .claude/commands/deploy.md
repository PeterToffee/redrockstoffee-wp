Deploy the committed `rrt-label-configurator` plugin to the live GoDaddy site using the repeatable process in `.claude/deploy/`.

**Only deploy when Pete explicitly says to. Every deploy also commits and pushes to GitHub. Read [.claude/deploy/README.md](../deploy/README.md) once if unsure.**

The connection method is fixed: `.claude/godaddy-sftp.sh` (SSH_ASKPASS + DISPLAY=:0, commands on stdin). Never use `sshpass`, `scp`, or `sftp -b`.
The scripts use it for you. Requires a `.env` at the repo root with this account's `GODADDY_SSH_HOST`/`PORT`/`USER`/`PASS` — never committed.

## Steps (run in order; do not skip a stage)

1. **Compare** - `bash .claude/deploy/1-compare.sh`. Read the result to Pete in plain words: DIFF / NEW / ASSET-NEW (what will change),
   LIVE-ONLY (stray server files), DRIFT (live changed behind our back - stop and look at those diffs in `.deploy-tmp/report/`).
   On a true first deploy of this plugin, expect almost everything to show as NEW, not DIFF.
2. **Preflight** - `bash .claude/deploy/2-preflight.sh`. Any `[FAIL]` blocks the deploy: fix it. Explain each `[WARN]` to Pete; the "Steps a file upload
   cannot do" warnings become a checklist to do after uploading.
3. **Get Pete's go-ahead** for exactly this file list. Then `bash .claude/deploy/3-deploy.sh --go`. (Bump `RRT_LABEL_VERSION` in
   `rrt-label-configurator.php` and commit BEFORE stage 1 if CSS/JS changed; preflight checks it.) Use `--files a,b` to ship only some files, `--dry-run` to preview.
4. **Verify** - `bash .claude/deploy/4-verify.sh <id>` (the id is printed by stage 3). Any FAIL prints the rollback command:
   `bash .claude/deploy/rollback.sh <id> --go`.
5. **Flush caches** - tell Pete to press **Flush Cache** (GoDaddy dashboard or WordPress toolbar; it clears GoDaddy and Cloudflare). Then
   `bash .claude/deploy/4-verify.sh <id> --after-flush --record`.
6. **Do the manual steps** from `.claude/deploy/ON-LIVE-CHECKLIST.md` and the preflight E-warnings, and have Pete test the feature live.
   Add a line to `.claude/deploy/probes.txt` for any new page/feature that must never silently break.
7. **Commit and push** the log/baseline changes (`.claude/deploy-log.md`, `.claude/deploy/live-state.txt`, `.claude/deploy/log/<id>/manifest.txt`).

Never overwrite GoDaddy platform files (`html/wp-config.php`, `html/wp-content/mu-plugins/`, `html/wp-content/object-cache.php`). The scripts only touch
the plugin folder.

## Extra rules learned the hard way (carried over from TheUnframer's pipeline)
- **Backups are private.** The scripts copy each replaced file to `~/deploy-backups/<id>/backup/` (outside the public web folder). Never leave `*.bak.*` files inside the plugin folder on the server: anyone can download them.
- **The server cannot move files between the web folder and the home folder**, so backups are copies, and the swap happens inside the web folder (upload `<name>.new`, rename). A file is only swapped in after its upload is confirmed complete; a half-uploaded file is never swapped in.
- **`--force` uploads what is COMMITTED.** Only use it on a file whose committed copy equals the live copy (self-tests); otherwise you would push different code live.
- **This is this repo's FIRST deploy pipeline** — unlike TheUnframer's, it hasn't been battle-tested against a real live run yet. Treat the first real `1-compare.sh`/`3-deploy.sh` run with extra care, and confirm the `html/wp-content/plugins/...` remote path assumption actually matches this GoDaddy account before trusting it blindly.
