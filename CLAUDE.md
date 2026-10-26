# RedRocksToffee WP — notes for Claude

## Deploying

The `rrt-label-configurator` plugin has a safe, repeatable deploy pipeline at
`.claude/deploy/` (compare → preflight → deploy --go → verify → rollback),
adapted from TheUnframer-WP's own pipeline. See `.claude/deploy/README.md`
for the full guide, or invoke the `deploy` skill.

**Never deploy without Pete explicitly saying to.** Every deploy also
commits and pushes to GitHub.

**Connection to GoDaddy is always through `.claude/godaddy-sftp.sh`.**
Never use `sshpass` (fails silently on this machine, looks like a
publickey error but isn't) and never use `sftp -b` (batch mode blocks
password login). Requires a `.env` at the repo root with this account's
own `GODADDY_SSH_HOST`/`PORT`/`USER`/`PASS` — never committed (already
gitignored).

## Local dev

XAMPP, `http://localhost:8081`. `C:/xampp/php/php.exe` for syntax checks
and running standalone test scripts.
