#!/usr/bin/env bash

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$SCRIPT_DIR"

# Add project bin to PATH if not already there
if [[ ":$PATH:" != *":$PROJECT_ROOT/bin:"* ]]; then
    export PATH="$PROJECT_ROOT/bin:$PATH"
fi

# Detect shell type
SHELL_TYPE=$(basename "$SHELL")

# Set up completion based on shell type
case "$SHELL_TYPE" in
    "zsh")
        # Create temporary zshrc
        TMPRC=$(mktemp)
        cat > "$TMPRC" << EOL
# Source original zshrc if it exists
[[ -f ~/.zshrc ]] && source ~/.zshrc

# Source completion
source "$PROJECT_ROOT/shell/app.completion.zsh"

# Set prompt to indicate monitor shell
PS1="(monitor) \$PS1"
EOL
        # Launch zsh with temporary config
        exec env ZDOTDIR="$TMPRC:A:h" zsh
        ;;
    "bash"|*)
        # Create temporary bashrc
        TMPRC=$(mktemp)
        cat > "$TMPRC" << EOL
# Source original bashrc if it exists
[[ -f ~/.bashrc ]] && source ~/.bashrc

# Source completion
source "$PROJECT_ROOT/shell/app.completion.bash"

# Set prompt to indicate monitor shell
PS1="(monitor) \$PS1"
EOL
        # Launch bash with temporary config
        exec bash --rcfile "$TMPRC"
        ;;
esac 