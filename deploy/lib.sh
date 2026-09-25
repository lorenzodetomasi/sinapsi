# Shared by deploy.sh, contents.sh and setup.sh. Sourced, never run.
#
# Two servers, one code:
#   isotype  = isotype.org, where Meetoo lives at /meetoo as a private
#              development copy. It gets every commit.
#   meetoo   = meetoo.it, the real site. It gets the code when you decide.
#
# What differs between the two is not in the code: it is in three files each
# server keeps as its own (ws-custom/ws-config.php, ws-custom/contents/
# ws_sitemap.wsx and sitemap.xml). The scripts never overwrite them, except
# setup.sh, once, and only when asked.
#
# Credentials live in deploy/servers.local.conf, which git ignores. Copy
# servers.sample.conf and fill it in. The password reaches lftp through the
# environment, not the command line, so it does not show in the process list.

set -euo pipefail

DEPLOY_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO=$(cd "$DEPLOY_DIR/.." && pwd)
CONF="$DEPLOY_DIR/servers.local.conf"
STATE="$DEPLOY_DIR/.state"
mkdir -p "$STATE"

die() { echo "deploy: $*" >&2; exit 1; }
say() { [[ ${QUIET:-0} == 1 ]] || echo "$*"; }

# Sets HOST, USER, PASS, ROOT, PROTOCOL, TLS_VERIFY, EXCLUDE for target $1.
load_target() {
    local t=$1 v name
    [[ $t == isotype || $t == meetoo ]] || die "unknown server '$t' (isotype | meetoo)"
    [[ -f $CONF ]] || die "missing $CONF: copy deploy/servers.sample.conf and fill it in"
    command -v lftp >/dev/null || die "lftp is not installed: brew install lftp"
    # shellcheck disable=SC1090
    source "$CONF"
    for v in HOST USER PASS ROOT; do
        name="${t}_$v"
        [[ -n ${!name:-} ]] || die "$name is empty in $CONF"
    done
    TARGET=$t
    HOST="${t}_HOST"; HOST=${!HOST}
    USER_="${t}_USER"; USER_=${!USER_}
    PASS="${t}_PASS"; PASS=${!PASS}
    ROOT="${t}_ROOT"; ROOT=${!ROOT%/}
    PROTOCOL="${t}_PROTOCOL"; PROTOCOL=${!PROTOCOL:-ftp}
    TLS_VERIFY="${t}_TLS_VERIFY"; TLS_VERIFY=${!TLS_VERIFY:-yes}
    EXCLUDE="${t}_EXCLUDE"; EXCLUDE=${!EXCLUDE:-}
}

# Quotes a string for an lftp command line.
lq() { local s=${1//\\/\\\\}; s=${s//\"/\\\"}; printf '"%s"' "$s"; }

# Runs the lftp commands in file $1 on the current target, from its root.
lftp_run() {
    local cmds=$1 script
    script=$(mktemp)
    {
        echo "set cmd:fail-exit yes"
        echo "set net:max-retries 2"
        echo "set net:timeout 30"
        echo "set ftp:ssl-allow yes"
        echo "set ssl:verify-certificate $TLS_VERIFY"
        # The date of a file goes up with it: the next comparison is by size
        # AND date, and without this every file would look changed.
        echo "set ftp:use-mfmt yes"
        echo "open --env-password -u $(lq "$USER_") $(lq "$PROTOCOL://$HOST")"
        echo "cd $(lq "$ROOT")"
        cat "$cmds"
    } > "$script"
    local rc=0
    LFTP_PASSWORD=$PASS lftp -f "$script" || rc=$?
    rm -f "$script"
    return $rc
}

# One run at a time per server: commits in a row start one upload each.
lock() {
    local dir="$STATE/$TARGET.lock" n=0
    until mkdir "$dir" 2>/dev/null; do
        (( n++ < 300 )) || die "$TARGET is locked by another upload ($dir): remove it if nothing is running"
        sleep 2
    done
    trap 'rmdir "$STATE/$TARGET.lock" 2>/dev/null || true' EXIT
}
