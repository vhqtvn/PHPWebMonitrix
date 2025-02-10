#!/usr/bin/env bash

# Prevent nested monitor shells
if [[ -n "$MONITOR_SHELL" ]]; then
    echo "Error: Already in a monitor shell"
    exit 1
fi

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Add directories to PATH if not already there
if [[ ":$PATH:" != *":$SCRIPT_DIR:"* ]]; then
    export PATH="$SCRIPT_DIR:$PATH"
fi
if [[ ":$PATH:" != *":$SCRIPT_DIR/bin:"* ]]; then
    export PATH="$SCRIPT_DIR/bin:$PATH"
fi

# Detect current shell, not login shell
CURRENT_SHELL=$(ps -p $$ -o comm=)

# Set up completion based on shell type
case "$CURRENT_SHELL" in
    *"zsh"*)
        # Create temporary zshrc
        TMPRC=$(mktemp)
        cat > "$TMPRC" << EOL
# Source original zshrc first
[[ -f ~/.zshrc ]] && source ~/.zshrc

# Source completion
source "$SCRIPT_DIR/shell/app.completion.zsh"

# Add convenience aliases
alias app="$SCRIPT_DIR/app"

# Set custom prompt
setopt PROMPT_SUBST
PS1='%F{cyan}(monitor)%f $PS1'

# Mark as monitor shell
export MONITOR_SHELL=1

# Restore current directory
cd "$(pwd)"
EOL
        # Launch zsh with temporary config
        exec zsh -c "ZDOTDIR='$(dirname $TMPRC)' exec zsh"
        ;;
    *"bash"*)
        # Create temporary bashrc
        TMPRC=$(mktemp)
        cat > "$TMPRC" << EOL
# Source original bashrc first
[[ -f ~/.bashrc ]] && source ~/.bashrc

# Source completion
source "$SCRIPT_DIR/shell/app.completion.bash"

# Add convenience aliases
alias app="$SCRIPT_DIR/app"

# Set custom prompt
PS1='\[\e[36m\](monitor)\[\e[0m\] $PS1'

# Mark as monitor shell
export MONITOR_SHELL=1

# Restore current directory
cd "$(pwd)"
EOL
        # Launch bash with temporary config
        exec bash --rcfile "$TMPRC"
        ;;
    *)
        echo "Unsupported shell: $CURRENT_SHELL"
        exit 1
        ;;
esac 