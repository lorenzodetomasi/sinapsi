#!/usr/bin/env bash
# Uploads the CODE to a server: what is committed, not what is on the disk.
#
#   deploy/deploy.sh isotype            the commits not yet on isotype.org
#   deploy/deploy.sh meetoo             the commits not yet on meetoo.it
#
#   --dry-run       lists what would go up and what would be removed, and stops
#   --all           every tracked file, not only the changed ones (first time)
#   --since <rev>   the changes after <rev>, whatever was uploaded last
#   --bundles       also the built editors (ws-admin/*/edit/), which git does
#                   not track: run it after `npm run build` in ws-admin/edit-src
#   --quiet         prints nothing (the post-commit hook)
#
# Files come from the COMMIT, exported with git archive: an edit you have not
# committed yet does not travel, even in a file that another commit touched.
# Contents (ws-custom/contents) and the per-server files are not code, and
# git does not track them: this script never touches them. See contents.sh.
#
# The last commit uploaded to each server is in deploy/.state/<server>.last:
# the next run starts from there, so a failed or skipped run is caught up by
# the next one.

source "$(dirname "$0")/lib.sh"

target=${1:-}; shift || true
DRY=0; ALL=0; SINCE=''; BUNDLES=0; QUIET=0
while (( $# )); do
    case $1 in
        --dry-run) DRY=1 ;;
        --all) ALL=1 ;;
        --since) SINCE=${2:-}; shift ;;
        --bundles) BUNDLES=1 ;;
        --quiet) QUIET=1 ;;
        *) die "unknown option $1" ;;
    esac
    shift
done
load_target "$target"
cd "$REPO"

# Never on a server: tools, sources of the built editors, notes, the past.
DEFAULT_EXCLUDE='^(\.gitignore|\.claude/|deploy/|archive/|ws-admin/edit-src/|ws-dev-router\.php$|LICENSE$)|\.md$'

head=$(git rev-parse HEAD)
last_file="$STATE/$TARGET.last"
base=$SINCE
[[ -z $base && -f $last_file ]] && base=$(cat "$last_file")

uploads=(); deletes=()
keep() {
    [[ $1 =~ $DEFAULT_EXCLUDE ]] && return 1
    [[ -n $EXCLUDE && $1 =~ $EXCLUDE ]] && return 1
    return 0
}
if (( ALL )); then
    while IFS= read -r f; do keep "$f" && uploads+=("$f"); done < <(git ls-tree -r --name-only HEAD)
else
    [[ -n $base ]] || die "first upload to $TARGET: use --all, or --since <the commit already on the server>"
    git cat-file -e "$base^{commit}" 2>/dev/null || die "$base is not a commit of this repository"
    # --no-renames: a rename is a removal and an addition, the two things a
    # server understands.
    while IFS=$'\t' read -r st f; do
        keep "$f" || continue
        case $st in
            D) deletes+=("$f") ;;
            *) uploads+=("$f") ;;
        esac
    done < <(git diff --name-status --no-renames "$base" "$head")
fi

say "$TARGET ($HOST$ROOT): ${#uploads[@]} to upload, ${#deletes[@]} to remove$( (( BUNDLES )) && echo ', plus the built editors')"
if (( DRY )); then
    for f in ${uploads[@]+"${uploads[@]}"}; do echo "  + $f"; done
    for f in ${deletes[@]+"${deletes[@]}"}; do echo "  - $f"; done
    exit 0
fi
if (( ${#uploads[@]} == 0 && ${#deletes[@]} == 0 && ! BUNDLES )); then
    say "nothing new since $(git log -1 --format='%h %s' "$base")"
    echo "$head" > "$last_file"
    exit 0
fi

lock
tmp=$(mktemp -d)
cmds=$(mktemp)
cleanup() { rm -rf "$tmp" "$cmds"; rmdir "$STATE/$TARGET.lock" 2>/dev/null || true; }
trap cleanup EXIT

if (( ${#uploads[@]} )); then
    git archive --format=tar "$head" -- "${uploads[@]}" | tar -x -C "$tmp"
    made=$'\n'   # bash 3.2 on macOS: no associative arrays
    for f in "${uploads[@]}"; do
        d=$(dirname "$f")
        if [[ $d != . && $made != *$'\n'"$d"$'\n'* ]]; then
            echo "mkdir -p -f $(lq "$d")" >> "$cmds"
            made+="$d"$'\n'
        fi
        echo "put -O $(lq "$d") $(lq "$tmp/$f")" >> "$cmds"
    done
fi
for f in ${deletes[@]+"${deletes[@]}"}; do
    echo "rm -f $(lq "$f")" >> "$cmds"
done
if (( BUNDLES )); then
    for d in ws-admin/pages/edit ws-admin/places/edit ws-admin/events/edit; do
        [[ -d $d ]] || continue
        # --delete only inside the bundle: the old hashed files go away.
        echo "mirror -R --delete --no-perms --exclude-glob .DS_Store $(lq "$REPO/$d") $(lq "$d")" >> "$cmds"
    done
fi

lftp_run "$cmds"
echo "$head" > "$last_file"
say "done: $TARGET is at $(git log -1 --format='%h %s' "$head")"
