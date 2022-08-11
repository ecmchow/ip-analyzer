#!/bin/bash

while getopts u:o:e:p:c: flag
do
    case "${flag}" in
        u) phpunit=${OPTARG};;
        o) output=${OPTARG};;
        e) entry=${OPTARG};;
        p) phar=${OPTARG};;
        c) ci=${OPTARG};;
    esac
done

# when $TERM is empty
[[ ${TERM}=="" ]] && TPUTTERM='-T xterm-256color' || TPUTTERM=''

bold=$(tput ${TPUTTERM} bold)
underline=$(tput ${TPUTTERM} smul)
italic=$(tput ${TPUTTERM} sitm)

info=$(tput ${TPUTTERM} setaf 2)
error=$(tput ${TPUTTERM} setaf 160)
warn=$(tput ${TPUTTERM} setaf 214)
highlight=$(tput ${TPUTTERM} smso)
reset=$(tput ${TPUTTERM} sgr0)

tests=0
assertions=0
failures=0
errors=0

declare -x FRAME=("⠋" "⠙" "⠹" "⠸" "⠼" "⠴" "⠦" "⠧" "⠇" "⠏")
declare -x FRAME_INTERVAL=0.1

# --------- Add/Modify Test BELOW ---------
declare -x STEPS=(
    'Basic Service with Redis'
    'Authenticated Service with Redis'
    'Service with Redis List'
)
declare -x CMDS=(
    'testRedisBasic'
    'testRedisAuth'
    'testRedisList'
)

# redis-basic
testRedisBasic () {
    if [ "$phar" = "yes" ]; then
        php "${entry}" start --basepath ../ --env test/env/redis-basic.env & sleep 0.1
    else
        php "${entry}" start --env test/env/redis-basic.env & sleep 0.1
    fi
    php "${phpunit}" --testsuite redis-basic || true
    result=$( tail -n 1 ${output} )
    php "${entry}" stop & sleep 0.1
    echo "${result}"
}

# redis-auth
testRedisAuth () {
    if [ "$phar" = "yes" ]; then
        php "${entry}" start --basepath ../ --env test/env/redis-auth.env & sleep 0.1
    else
        php "${entry}" start --env test/env/redis-auth.env & sleep 0.1
    fi
    php "${phpunit}" --testsuite redis-auth || true
    result=$( tail -n 1 ${output} )
    php "${entry}" stop & sleep 0.1
    echo "${result}"
}

# redis-list
testRedisList () {
    if [ "$phar" = "yes" ]; then
        php "${entry}" start --basepath ../ --env test/env/redis-list.env & sleep 0.1
    else
        php "${entry}" start --env test/env/redis-list.env & sleep 0.1
    fi
    php "${phpunit}" --testsuite redis-list || true
    result=$( tail -n 1 ${output} )
    php "${entry}" stop & sleep 0.1
    echo "${result}"
}
# --------- Add/Modify Test ABOVE ---------

start () {
    step=0

    while [ "$step" -lt "${#CMDS[@]}" ]; do
        echo -ne "\\n"
        if [ "$step" = 0 ]; then
            if [ "$ci" = "yes" ]; then
                ${CMDS[$step]} > "${output}" 2> /dev/null
            else
                ${CMDS[$step]} > "${output}" 2> /dev/null & pid=$!
            fi
        else
            if [ "$ci" = "yes" ]; then
                ${CMDS[$step]} >> "${output}" 2> /dev/null
            else
                ${CMDS[$step]} >> "${output}" 2> /dev/null & pid=$!
            fi
        fi

        while ps -p $pid &>/dev/null; do
            displaystep=$((step + 1))
            echo -ne "\\r${info}[   ]${reset} ${bold}Running e2e test (${displaystep} of ${#STEPS[@]}${reset}): ${STEPS[$step]}"

            for k in "${!FRAME[@]}"; do
                echo -ne "\\r${info}[ ${FRAME[k]} ]${reset}"
                sleep $FRAME_INTERVAL
            done
        done

        result=$( tail -n 1 ${output} )

        num='([0-9^]+)'
        nonum='[^0-9^]+'

        if [[ "$result" == *"Errors"* ]]; then
            if [[ $result =~ $nonum$num$nonum$num$nonum$num$nonum ]] ; then
                t=${BASH_REMATCH[1]#0}
                a=${BASH_REMATCH[2]#0}
                e=${BASH_REMATCH[3]#0}
                tests=$((tests + t))
                assertions=$((assertions + a))
                errors=$((errors + e))
            fi
            echo -ne "\\r${error}[ ✗ ]${reset} ${bold}Finished e2e test (${displaystep} of ${#STEPS[@]}${reset}): ${STEPS[$step]}\\n"
            echo -ne "      -> ${error}${highlight} ERROR ${reset} ${error}${bold}${result}${reset}\\n"
        elif [[ "$result" == *"Failures"* ]]; then
            if [[ $result =~ $nonum$num$nonum$num$nonum$num$nonum ]] ; then
                t=${BASH_REMATCH[1]#0}
                a=${BASH_REMATCH[2]#0}
                f=${BASH_REMATCH[3]#0}
                tests=$((tests + t))
                assertions=$((assertions + a))
                failures=$((failures + f))
            fi
            echo -ne "\\r${warn}[ ✗ ]${reset} ${bold}Finished e2e test (${displaystep} of ${#STEPS[@]}${reset}): ${STEPS[$step]}\\n"
            echo -ne "      -> ${warn}${highlight} FAILED ${reset} ${warn}${bold}${result}${reset}\\n"
        else
            if [[ $result =~ $nonum$num$nonum$num$nonum ]] ; then
                t=${BASH_REMATCH[1]#0}
                a=${BASH_REMATCH[2]#0}
                tests=$((tests + t))
                assertions=$((assertions + a))
            fi
            echo -ne "\\r${info}[ ✔ ]${reset} ${bold}Finished e2e test (${displaystep} of ${#STEPS[@]}${reset}): ${STEPS[$step]}\\n"
            echo -ne "      -> ${info}${highlight} PASSED ${reset} ${info}${bold}${result}${reset}\\n"
        fi
        
        step=$((step + 1))
    done
}

timestart=$(php -r "echo microtime(true);")
start
timeend=$(php -r "echo microtime(true);")

echo -ne "\\n"
echo -ne "${bold}e2e tests completed${reset} in $(php -r "echo sprintf('%.2f', ${timeend}-${timestart});") s\\n"
echo -ne "\\n"

if [ "$errors" -gt 0 ]; then
    echo -ne "${bold}SUMMARY:${reset} ${error}${highlight} ERROR ${reset}\\n"
    echo -ne "${error}Tests: ${tests}, Assertions: ${assertions}, Failures: ${failures}, Errors: ${errors}${reset}\\n"
    echo -ne "\\n"
    echo -ne "${italic}Please view ${output} for details${reset}\\n"
    trap 'php "${entry}" stop >/dev/null 2>/dev/null' EXIT
    exit 5
elif [ "$failures" -gt 0 ]; then
    echo -ne "${bold}SUMMARY:${reset} ${warn}${highlight} FAILED ${reset}\\n"
    echo -ne "${warn}Tests: ${tests}, Assertions: ${assertions}, Failures: ${failures}${reset}\\n"
    echo -ne "\\n"
    echo -ne "${italic}Please view ${output} for details${reset}\\n"
    trap 'php "${entry}" stop >/dev/null 2>/dev/null' EXIT
    exit 5
else
    echo -ne "${bold}SUMMARY:${reset} ${info}${highlight} PASSED ${reset}\\n"
    echo -ne "${info}Tests: ${tests}, Assertions: ${assertions}${reset}\\n"
    echo -ne "\\n"
    echo -ne "${italic}Please view ${output} for details${reset}\\n"
    trap 'php "${entry}" stop >/dev/null 2>/dev/null' EXIT
    exit 0
fi
