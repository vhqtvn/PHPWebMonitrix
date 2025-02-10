#!/bin/bash

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Add project bin to PATH if not already there
if [[ ":$PATH:" != *":$PROJECT_ROOT/bin:"* ]]; then
    export PATH="$PROJECT_ROOT/bin:$PATH"
fi

# Detect shell type
SHELL_TYPE=$(basename "$SHELL")

# Source appropriate completion file
case "$SHELL_TYPE" in
    "zsh")
        source "$SCRIPT_DIR/app.completion.zsh"
        ;;
    "bash"|*)
        source "$SCRIPT_DIR/app.completion.bash"
        ;;
esac

# Add alias for convenience
alias app="$PROJECT_ROOT/app" 