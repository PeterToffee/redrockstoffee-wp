#!/bin/bash
# Run SFTP commands against the GoDaddy server using the ONLY method that works on this PC.
#
# Usage (commands come in on stdin, never with sftp's -b flag):
#   printf 'pwd\nls html/wp-content/plugins/rrt-label-configurator\n' | .claude/godaddy-sftp.sh
#
# Why it looks like this:
#   - sshpass does NOT work here (server answers "partial success", then fails).
#   - SSH_ASKPASS_REQUIRE=force + DISPLAY=:0 makes ssh accept the password from a tiny script.
#   - sftp -b sets BatchMode, which blocks password login, so commands must be piped in.
# Credentials are read from .env (GODADDY_SSH_HOST / PORT / USER / PASS). Never print them.

set -e
cd "$(dirname "$0")/.."
set -a; source .env; set +a

ASKPASS_SCRIPT=$(mktemp)
trap 'rm -f "$ASKPASS_SCRIPT"' EXIT
printf '#!/bin/sh\necho "%s"\n' "$GODADDY_SSH_PASS" > "$ASKPASS_SCRIPT"
chmod +x "$ASKPASS_SCRIPT"

SSH_ASKPASS="$ASKPASS_SCRIPT" SSH_ASKPASS_REQUIRE=force DISPLAY=:0 \
  sftp -o StrictHostKeyChecking=no \
       -o PreferredAuthentications=password -o PasswordAuthentication=yes \
       -P "$GODADDY_SSH_PORT" "$GODADDY_SSH_USER@$GODADDY_SSH_HOST" 2>&1 \
  | grep -v "post-quantum\|store now, decrypt later\|openssh.com/pq"
