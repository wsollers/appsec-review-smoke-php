#!/usr/bin/env bash
# Rebuild this repository's commit graph from scratch, reproducibly.
#
#   tools/make-history.sh [OUT_DIR]
#
# Reads tools/history/steps/NN-slug/ in order and replays each step on a new `main` branch in
# OUT_DIR (default: a fresh temporary directory), then checks that the final tree is identical to
# this checkout. Every commit's author, committer and dates come from the step's `meta` file, so
# the same steps always produce the same commit ids.
#
# A step directory holds:
#   meta     KEY=VALUE lines: AUTHOR_NAME, AUTHOR_EMAIL, DATE, ACTION (patch|revert|reapply), ARG
#   message  the commit message (subject line, blank line, body)
#   patch    optional `git diff --binary` applied with `git apply --index`
#   copy     optional list of paths copied verbatim from this checkout (paths that never change)
#   fetch    optional lines `URL|SHA256|STRIP|DEST|KEEP_REGEX`: a tarball downloaded to the cache,
#            verified, and extracted into DEST keeping only member paths (after STRIP leading
#            components) that match KEEP_REGEX; with STRIP=raw the download is stored as the
#            file DEST unchanged (packages such as .nupkg, .jar, .gem or .crate files)
#
# ACTION=revert ARG=NN reverts step NN's commit; ACTION=reapply ARG=NN applies it again.
# Downloads are cached in ${SMOKE_HISTORY_CACHE:-$HOME/.cache/appsec-review-smoke}; with a warm
# cache the script needs no network.
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$(mktemp -d)}"
CACHE="${SMOKE_HISTORY_CACHE:-$HOME/.cache/appsec-review-smoke}"
STEPS="$SRC/tools/history/steps"
mkdir -p "$OUT" "$CACHE"

if [ -n "$(ls -A "$OUT")" ]; then
  echo "make-history: $OUT is not empty" >&2
  exit 2
fi

git -C "$OUT" init -q -b main
git -C "$OUT" config core.autocrlf false
git -C "$OUT" config commit.gpgsign false

declare -A SHA_OF_STEP

read_meta() {
  sed -n "s/^$2=//p" "$1" | head -n 1
}

do_fetch() {
  local url sha strip dest keep
  IFS='|' read -r url sha strip dest keep <<<"$1"
  local archive="$CACHE/$sha.tar.gz"
  if [ ! -f "$archive" ]; then
    curl -fsSL -o "$archive.part" "$url"
    mv "$archive.part" "$archive"
  fi
  echo "$sha  $archive" | sha256sum -c --quiet -
  if [ "$strip" = "raw" ]; then
    # DEST is a file path; the download is stored as-is (for .nupkg, .jar, .gem, .crate files).
    mkdir -p "$(dirname "$OUT/$dest")"
    cp "$archive" "$OUT/$dest"
    git -C "$OUT" add -A -- "$dest"
    return
  fi
  local tmp
  tmp="$(mktemp -d)"
  tar -xzf "$archive" -C "$tmp"
  rm -rf "$OUT/$dest"
  while IFS= read -r member; do
    local rel
    rel="$(printf '%s\n' "$member" | cut -d/ -f"$((strip + 1))"-)"
    if printf '%s\n' "$rel" | grep -Eq "$keep"; then
      mkdir -p "$(dirname "$OUT/$dest/$rel")"
      cp "$tmp/$member" "$OUT/$dest/$rel"
    fi
  done < <(cd "$tmp" && find . -type f | sed 's#^\./##' | LC_ALL=C sort)
  rm -rf "$tmp"
  git -C "$OUT" add -A -- "$dest"
}

for step in "$STEPS"/*/; do
  step="${step%/}"
  name="$(basename "$step")"
  number="${name%%-*}"
  meta="$step/meta"
  export GIT_AUTHOR_NAME="$(read_meta "$meta" AUTHOR_NAME)"
  export GIT_AUTHOR_EMAIL="$(read_meta "$meta" AUTHOR_EMAIL)"
  export GIT_AUTHOR_DATE="$(read_meta "$meta" DATE)"
  export GIT_COMMITTER_NAME="$GIT_AUTHOR_NAME"
  export GIT_COMMITTER_EMAIL="$GIT_AUTHOR_EMAIL"
  export GIT_COMMITTER_DATE="$GIT_AUTHOR_DATE"
  action="$(read_meta "$meta" ACTION)"
  arg="$(read_meta "$meta" ARG)"

  case "$action" in
    patch)
      if [ -f "$step/copy" ]; then
        while IFS= read -r path; do
          [ -n "$path" ] || continue
          rm -rf "$OUT/$path"
          mkdir -p "$(dirname "$OUT/$path")"
          cp -R "$SRC/$path" "$OUT/$path"
          git -C "$OUT" add -A -- "$path"
        done <"$step/copy"
      fi
      if [ -f "$step/fetch" ]; then
        while IFS= read -r spec; do
          [ -n "$spec" ] || continue
          do_fetch "$spec"
        done <"$step/fetch"
      fi
      if [ -f "$step/patch" ]; then
        git -C "$OUT" apply --index --whitespace=nowarn "$step/patch"
      fi
      ;;
    revert)
      git -C "$OUT" revert --no-commit "${SHA_OF_STEP[$arg]}"
      ;;
    reapply)
      git -C "$OUT" cherry-pick --no-commit "${SHA_OF_STEP[$arg]}"
      ;;
    *)
      echo "make-history: step $name has unknown ACTION '$action'" >&2
      exit 2
      ;;
  esac
  git -C "$OUT" commit -q --allow-empty -F "$step/message"
  SHA_OF_STEP[$number]="$(git -C "$OUT" rev-parse HEAD)"
  printf '%s %s %s\n' "$number" "${SHA_OF_STEP[$number]}" "$(head -n 1 "$step/message")"
done

if diff -r -q --exclude=.git "$SRC" "$OUT" >/dev/null; then
  echo "make-history: final tree matches $SRC"
else
  echo "make-history: final tree differs from $SRC:" >&2
  diff -r -q --exclude=.git "$SRC" "$OUT" >&2 || true
  exit 1
fi
echo "make-history: $(git -C "$OUT" rev-list --count HEAD) commits in $OUT"
