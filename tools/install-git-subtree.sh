#!/bin/sh

set -eu

git_exec_path=$(git --exec-path)
if [ -x "$git_exec_path/git-subtree" ]; then
    printf 'git-subtree is already available through Git exec path: %s\n' "$git_exec_path/git-subtree"
    exit 0
fi

if command -v apk >/dev/null 2>&1; then
    apk add --no-cache git-subtree
elif command -v apt-get >/dev/null 2>&1; then
    apt-get update
    apt-get install --yes --no-install-recommends git-subtree
    rm -rf /var/lib/apt/lists/*
else
    printf 'Cannot install git-subtree: neither apk nor apt-get is available.\n' >&2
    exit 1
fi

git_exec_path=$(git --exec-path)
if [ ! -x "$git_exec_path/git-subtree" ]; then
    printf 'git-subtree installation did not make it available through Git exec path: %s\n' "$git_exec_path" >&2
    exit 1
fi
printf 'git-subtree installed and available through Git exec path: %s\n' "$git_exec_path/git-subtree"
