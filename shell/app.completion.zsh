#compdef app

_app() {
    local curcontext="$curcontext" state line
    typeset -A opt_args

    _arguments -C \
        '1: :->command' \
        '2: :->argument' \
        '*: :->args'

    case $state in
        command)
            local commands
            commands=(
                'cron:Run the job scheduler'
                'create:Create a new job from template'
                'disable:Disable a job'
                'enable:Enable a disabled job'
                'list:List all jobs and their status'
                'invoke:Run a specific job once (for testing)'
                'run:Run a job in background'
                'test:Run a job synchronously for testing'
                'install:Install shell completion and add to PATH'
            )
            _describe -t commands 'app commands' commands
            ;;
        argument)
            case $line[1] in
                create|disable|enable|invoke|run|test)
                    local jobs_dir="$(dirname $(dirname $0))/jobs"
                    local -a jobs
                    jobs=( ${jobs_dir}/*.php(N:t:r) )
                    # Filter out disabled jobs
                    jobs=( ${jobs:#*disable} )
                    _wanted jobs expl 'jobs' compadd -a jobs
                    ;;
            esac
            ;;
    esac
}

# Register the completion function
compdef _app app 