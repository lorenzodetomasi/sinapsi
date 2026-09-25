#!/usr/bin/env bash
# Moves Meetoo's CONTENTS (ws-custom/contents/meetoo), which git does not track.
#
# meetoo.it is the original: people sign in there and edit there. So contents
# travel FROM meetoo.it, and toward it only one piece at a time.
#
#   deploy/contents.sh pull            meetoo.it -> this computer
#   deploy/contents.sh push-dev        this computer -> isotype.org/meetoo,
#                                      the development copy (run pull first)
#   deploy/contents.sh sync-dev        pull, then push-dev: the development
#                                      copy becomes identical to meetoo.it
#   deploy/contents.sh put-prod <path> one file or folder -> meetoo.it, e.g.
#                                      it_IT/events/20261220T2100-IT00121-x
#   deploy/contents.sh seed-prod       the whole folder -> meetoo.it, ONCE:
#                                      it refuses if meetoo.it already has it
#
#   --dry-run   says what it would do, and does nothing
#   --delete    pull / push-dev / sync-dev: also removes what the source no
#               longer has. Without it nothing is ever removed.
#
# Files are compared by size and date; lftp carries the date along (MFMT), so
# what did not change does not travel again.

source "$(dirname "$0")/lib.sh"

cmd=${1:-}; shift || true
DRY=0; DELETE=0; ONE=''; FORCE=0
while (( $# )); do
    case $1 in
        --dry-run) DRY=1 ;;
        --delete) DELETE=1 ;;
        --force) FORCE=1 ;;
        -*) die "unknown option $1" ;;
        *) ONE=$1 ;;
    esac
    shift
done

LOCAL="$REPO/ws-custom/contents/meetoo"
REMOTE="ws-custom/contents/meetoo"
# The lock of the automatic rebuild, and the Mac's folder notes.
SKIP="--exclude-glob .DS_Store --exclude-glob derived.lock"
opts() { echo "--no-perms $SKIP$( (( DRY )) && echo ' --dry-run')$( (( DELETE )) && echo ' --delete')"; }

pull() {
    load_target meetoo; lock
    local c; c=$(mktemp)
    echo "mirror $(opts) $(lq "$REMOTE") $(lq "$LOCAL")" > "$c"
    say "meetoo.it -> this computer$( (( DRY )) && echo ' (dry run)')"
    lftp_run "$c"; rm -f "$c"
}

push_dev() {
    load_target isotype; lock
    local c; c=$(mktemp)
    echo "mkdir -p -f $(lq "$REMOTE")" > "$c"
    echo "mirror -R $(opts) $(lq "$LOCAL") $(lq "$REMOTE")" >> "$c"
    say "this computer -> isotype.org/meetoo$( (( DRY )) && echo ' (dry run)')"
    lftp_run "$c"; rm -f "$c"
}

put_prod() {
    local rel=${ONE#/}
    rel=${rel#ws-custom/contents/meetoo/}
    [[ -n $rel && $rel != . && $rel != it_IT && $rel != it_IT/ ]] \
        || die "put-prod wants one file or folder inside contents/meetoo, not all of it"
    [[ $rel != *..* ]] || die "no .. in the path"
    [[ -e $LOCAL/$rel ]] || die "$LOCAL/$rel does not exist"
    load_target meetoo; lock
    local c d; c=$(mktemp)
    d=$(dirname "$rel")
    echo "mkdir -p -f $(lq "$REMOTE/$d")" > "$c"
    if [[ -d $LOCAL/$rel ]]; then
        echo "mirror -R --no-perms $SKIP$( (( DRY )) && echo ' --dry-run') $(lq "$LOCAL/$rel") $(lq "$REMOTE/$rel")" >> "$c"
    elif (( DRY )); then
        echo "echo would upload $(lq "$rel")" >> "$c"
    else
        echo "put -O $(lq "$REMOTE/$d") $(lq "$LOCAL/$rel")" >> "$c"
    fi
    say "this computer -> meetoo.it: $rel"
    lftp_run "$c"; rm -f "$c"
}

seed_prod() {
    load_target meetoo; lock
    local c; c=$(mktemp)
    # Refuses when meetoo.it already has contents: from the first sign-in on,
    # what is there is the original, and a whole upload would overwrite it.
    if (( ! FORCE )); then
        echo "cls -1 $(lq "$REMOTE/it_IT/events")" > "$c"
        if lftp_run "$c" 2>/dev/null | grep -q .; then
            rm -f "$c"
            die "meetoo.it already has its contents: seed-prod is for the first time only (use put-prod, or --force if you really mean it)"
        fi
    fi
    echo "mkdir -p -f $(lq "$REMOTE")" > "$c"
    echo "mirror -R --no-perms $SKIP$( (( DRY )) && echo ' --dry-run') $(lq "$LOCAL") $(lq "$REMOTE")" >> "$c"
    say "first upload of the contents to meetoo.it$( (( DRY )) && echo ' (dry run)')"
    lftp_run "$c"; rm -f "$c"
}

case $cmd in
    pull) pull ;;
    push-dev) push_dev ;;
    sync-dev) pull; rmdir "$STATE/meetoo.lock" 2>/dev/null || true; push_dev ;;
    put-prod) put_prod ;;
    seed-prod) seed_prod ;;
    *) sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
