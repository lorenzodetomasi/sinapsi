#!/usr/bin/env bash
# The files each server keeps as its OWN: done once, by hand, never by deploy.sh.
#
#   deploy/setup.sh meetoo    meetoo.it: its ws-config.php (this computer's,
#                             plus deploy/meetoo.it/ws-config.additions.php)
#                             and its main map (deploy/meetoo.it/ws_sitemap.wsx).
#                             Refuses to overwrite what is already there.
#   deploy/setup.sh isotype   isotype.org: adds the private copy of Meetoo
#                             (deploy/isotype.org/ws-config.additions.php) to
#                             the ws-config.php that is ON THE SERVER. A copy
#                             of the server's file stays in deploy/.state/.
#
#   --dry-run   downloads and shows, uploads nothing
#   --force     meetoo: overwrites files that are already there

source "$(dirname "$0")/lib.sh"

target=${1:-}; shift || true
DRY=0; FORCE=0
while (( $# )); do
    case $1 in
        --dry-run) DRY=1 ;;
        --force) FORCE=1 ;;
        *) die "unknown option $1" ;;
    esac
    shift
done
load_target "$target"
lock
work=$(mktemp -d)
c="$work/cmds"
trap 'rm -rf "$work"; rmdir "$STATE/$TARGET.lock" 2>/dev/null || true' EXIT

# Is this path already on the server?
exists() {
    echo "cls -1 $(lq "$1")" > "$c"
    lftp_run "$c" 2>/dev/null | grep -q .
}

# A config file ending in `?>` would print whatever follows it: strip it.
without_closing_tag() { perl -0pe 's/\?>\s*\z//' "$1"; }

# The super-admins of the server (ws-admin/lib/ws-auth.php), taken from THIS
# computer's ws-config.php when it declares them: Google subs and emails are
# personal, so they are not in the repository. Nothing printed when absent.
super_admins() {
    php -r 'error_reporting(0); @include getenv("CFG"); if (defined("WS_SUPER_ADMINS")) {
        echo "\n// --- super-admins of this server (deploy/setup.sh, from the author'"'"'s ws-config.php) ---\n";
        echo "define(\"WS_SUPER_ADMINS\", " . var_export(array_values(WS_SUPER_ADMINS), true) . ");\n"; }' \
        CFG="$REPO/ws-custom/ws-config.php" 2>/dev/null || true
}

case $TARGET in
meetoo)
    for f in ws-custom/ws-config.php ws-custom/contents/ws_sitemap.wsx; do
        if (( ! FORCE )) && exists "$f"; then
            die "meetoo.it already has $f: nothing overwritten (--force to replace it)"
        fi
    done
    [[ -f $REPO/ws-custom/ws-config.php ]] || die "this computer has no ws-custom/ws-config.php to start from"
    grep -qE "WS_MOUNTS|WS_PRIVATE_SITES" "$REPO/ws-custom/ws-config.php" && die "this computer's ws-config.php already defines WS_MOUNTS or WS_PRIVATE_SITES: they belong to one server, fix the copy by hand"
    { without_closing_tag "$REPO/ws-custom/ws-config.php"; cat "$DEPLOY_DIR/meetoo.it/ws-config.additions.php"; } > "$work/ws-config.php"
    # The local file may already declare them (it was copied whole just above).
    grep -q WS_SUPER_ADMINS "$work/ws-config.php" || super_admins >> "$work/ws-config.php"
    php -l "$work/ws-config.php" >/dev/null || die "the resulting ws-config.php does not parse"
    say "meetoo.it: ws-custom/ws-config.php and ws-custom/contents/ws_sitemap.wsx"
    if (( DRY )); then tail -8 "$work/ws-config.php"; exit 0; fi
    {
        echo "mkdir -p -f ws-custom/contents"
        echo "put -O ws-custom $(lq "$work/ws-config.php")"
        echo "put -O ws-custom/contents $(lq "$DEPLOY_DIR/meetoo.it/ws_sitemap.wsx")"
    } > "$c"
    lftp_run "$c"
    ;;
isotype)
    echo "get ws-custom/ws-config.php -o $(lq "$work/remote.php")" > "$c"
    lftp_run "$c" || die "could not download ws-custom/ws-config.php from isotype.org"
    cp "$work/remote.php" "$STATE/isotype.ws-config.$(date +%Y%m%d-%H%M%S).php"
    # Whatever is missing, and only that: a second run adds what came later.
    without_closing_tag "$work/remote.php" > "$work/ws-config.php"
    aggiunto=''
    if ! grep -q "WS_PRIVATE_SITES" "$work/remote.php"; then
        cat "$DEPLOY_DIR/isotype.org/ws-config.additions.php" >> "$work/ws-config.php"; aggiunto+=' WS_PRIVATE_SITES'
    fi
    if ! grep -q "WS_SUPER_ADMINS" "$work/remote.php"; then
        blocco=$(super_admins)
        [[ -n $blocco ]] && { printf '%s\n' "$blocco" >> "$work/ws-config.php"; aggiunto+=' WS_SUPER_ADMINS'; }
    fi
    [[ -n $aggiunto ]] || { say "isotype.org already has everything: nothing to do"; exit 0; }
    php -l "$work/ws-config.php" >/dev/null || die "the resulting ws-config.php does not parse"
    say "isotype.org: added to ws-custom/ws-config.php:$aggiunto"
    if (( DRY )); then tail -10 "$work/ws-config.php"; exit 0; fi
    echo "put -O ws-custom $(lq "$work/ws-config.php")" > "$c"
    lftp_run "$c"
    ;;
esac
say "done"
