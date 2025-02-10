# bash completion for app monitor tool

_app_completion()
{
    local cur prev words cword
    _init_completion || return

    # List of all available commands
    local commands="cron create disable enable list invoke run test install"

    case $prev in
        create|disable|enable|invoke|run|test)
            # Get list of jobs (excluding .disable.php and -disable.php files)
            local jobs_dir="$(dirname $(dirname ${BASH_SOURCE[0]}))/jobs"
            COMPREPLY=( $(compgen -W "$(ls $jobs_dir/*.php 2>/dev/null | grep -v '[.-]disable\.php$' | xargs -n1 basename -s .php)" -- "$cur") )
            return 0
            ;;
        app)
            # Complete command names
            COMPREPLY=( $(compgen -W "$commands" -- "$cur") )
            return 0
            ;;
    esac

    # Default to command names if no specific completion
    COMPREPLY=( $(compgen -W "$commands" -- "$cur") )
} &&
complete -F _app_completion app 