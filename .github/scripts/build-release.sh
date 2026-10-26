#!/usr/bin/env bash
#
# NOTICE OF LICENSE
#
# This source file is subject to the Academic Free License (AFL 3.0)
# It is available through the world-wide-web at this URL:
# http://opensource.org/licenses/afl-3.0.php
#
# @author    Buckaroo.nl <plugins@buckaroo.nl>
# @copyright Copyright (c) Buckaroo B.V.
# @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
#
# Builds the release ZIP (buckaroo3/… at the root) from a git tree and validates it.
#
#   .github/scripts/build-release.sh [<git-ref>|--worktree] [<output.zip>]
#
#   <git-ref>    tree to package (default: HEAD). Only committed content is used.
#   --worktree   package the current working tree, including uncommitted changes
#                (handy for a pre-commit validator run).
#   <output.zip> defaults to build/buckaroo3.zip inside the module directory.
#
# `git archive` honours the export-ignore rules in .gitattributes, so tests,
# dev tooling, CI files and dotfiles never end up in the package.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REF="${1:-HEAD}"
OUT="${2:-$ROOT/build/buckaroo3.zip}"

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

if [ "$REF" = "--worktree" ]; then
    TMP_INDEX="$(mktemp)"
    trap 'rm -f "$TMP_INDEX"' EXIT
    cp "$(git -C "$ROOT" rev-parse --git-path index)" "$TMP_INDEX"
    GIT_INDEX_FILE="$TMP_INDEX" git -C "$ROOT" add -A
    TREE="$(GIT_INDEX_FILE="$TMP_INDEX" git -C "$ROOT" write-tree)"
    echo "Packaging working tree (tree $TREE)"
else
    TREE="$(git -C "$ROOT" rev-parse --verify "$REF^{tree}")"
    echo "Packaging $REF (tree $TREE)"
fi

git -C "$ROOT" archive --format=zip --prefix=buckaroo3/ -o "$OUT" "$TREE"

echo "Wrote $OUT ($(wc -c < "$OUT") bytes)"
echo
php "$ROOT/.github/scripts/validate-package.php" zip "$OUT"
