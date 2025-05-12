#!/bin/bash

# 🧪 Laravel PHPUnit Runner - Enhanced
# Please run this script *inside your Docker container*

# 📛 Show help + error usage
show_error_and_exit() {
  printf "🚫 \033[1;31mError:\033[0m %s\n\n" "$1"
  printf "📘 \033[1mUsage:\033[0m\n"
  printf "  \033[1;32msh tests.sh <TestFolder> [--filter keyword] [--coverage|--isolation|--debug]\033[0m\n\n"
  printf "💡 \033[1mExamples:\033[0m\n"
  printf "  📁 Run all unit tests:\n"
  printf "     \033[1;32msh tests.sh tests/Unit\033[0m\n\n"
  printf "  📁 Run all feature tests:\n"
  printf "     \033[1;32msh tests.sh tests/Feature\033[0m\n\n"
  printf "  🔍 Run only tests containing 'upd' keywork in test name:\n"
  printf "     \033[1;32msh tests.sh tests/Feature --filter upd\033[0m\n\n"
  printf "  📊 Generate code coverage report:\n"
  printf "     \033[1;32msh tests.sh tests/Unit --coverage\033[0m\n\n"
  printf "  🧩 Run each test in isolation:\n"
  printf "     \033[1;32msh tests.sh tests/Feature --isolation\033[0m\n\n"
  printf "  🐛 Enable debug output:\n"
  printf "     \033[1;32msh tests.sh tests/Feature --debug\033[0m\n\n"
  printf "  💻 Silent test output for CI environment:\n"
  printf "     \033[1;32msh tests.sh tests/Feature --ci\033[0m\n\n"
  exit 1
}

# 🧾 Validate route
route="$1"
[ -z "$route" ] || [ "$route" = "--help" ] && show_error_and_exit "Must provide a valid test folder path."
clear
# ⛏️ Parse all arguments
shift
filter=""
mode=""
slack_notify=false

while [ $# -gt 0 ]; do
  case "$1" in
    --filter)
      shift
      [ -z "$1" ] && show_error_and_exit "Missing filter keyword after --filter"
      filter="--filter $1"
      ;;
    --coverage|--isolation|--debug|--ci|--slack)
      mode="$1"
      ;;
    *)
      show_error_and_exit "Invalid option: $1"
      ;;
  esac
  shift
done

# 🧼 Clean coverage reports if needed
if [ "$mode" = "--coverage" ]; then
  printf "🧹 Cleaning old coverage reports...\n"
  rm -rf tests/reports/coverage
fi

# 🖥️ Info
printf "📂 Running tests in: \033[1;36m%s\033[0m\n" "$route"
printf "📌 Mode: %s\n" "${mode:-standard}"
[ -n "$filter" ] && printf "🔎 Filter: %s\n" "$filter"
printf "⚠️  Make sure you are INSIDE your Docker container\n"
printf "────────────────────────────────────────────────────────────\n\n"

# 🏃 Execute PHPUnit and log the output
log_file="storage/logs/phpunit.log"
timestamp=$(date +"%Y-%m-%d %H:%M:%S")

# 🏃 Execute PHPUnit
case "$mode" in
  --coverage)
    php -d xdebug.mode=coverage ./vendor/bin/phpunit \
      --configuration phpunit.xml "$route" $filter \
      --testdox --colors=always \
      --coverage-html tests/reports/coverage \
      | tee -a "$log_file"
    ;;
  --isolation)
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always \
      --process-isolation \
      | tee -a "$log_file"
    ;;
  --debug)
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always --debug \
      | tee -a "$log_file"
    ;;
  --ci)
    # Silent output suitable for CI environments
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --colors=always --no-coverage \
      | tee -a "$log_file"
    ;;
  --slack)
    slack_notify=true
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always\
      | tee -a "$log_file"
    ;;
  *)
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always --quiet \
      | tee -a "$log_file"
    ;;
esac

# 🧑‍💻 Send Slack notification if --slack is set
if [ "$slack_notify" = true ]; then
  timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  slack_message=":tada: Tests completed at $timestamp\nStatus: Check logs for errors\nLogs: $log_file"

  # Replace with your Slack webhook URL
  webhook_url="https://hooks.slack.com/services/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"

  curl -X POST -H 'Content-type: application/json' \
    --data "{\"text\":\"$slack_message\"}" \
    "$webhook_url"
  printf "🔔 Slack notification sent!\n"
fi
# Add a separator and timestamp at the end of the log entry
var1="\n────────────────────────────────────────────────────────────\n"
var2="Log entry finished at: $timestamp \n"
var3="──────────────────────────────────────────────────────────────\n\n"
echo "$var1" "$var2" "$var3" >> "$log_file"


# 📝 Log file location
printf "📝 Test results logged to: \033[1;36m%s\033[0m\n" "$log_file"

