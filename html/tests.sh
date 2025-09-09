#!/bin/bash

# 🧪 Laravel PHPUnit Runner - Enhanced
# Please run this script *inside your Docker container*

start_time=$(date +%s)
junit_file="storage/tests/reports/results.xml"
ci_mode=false

# 🔧 Xdebug/IDE settings (auto-detect project and use proper port)
# Always enable Xdebug when running this script.
# - mystorepanel  -> 59896
# - web-templates -> 59895
# - IDE host uses host.docker.internal (works with Docker Desktop)
IDE_HOST="${IDE_HOST:-host.docker.internal}"
if [[ "$PWD" == *"/var/www/mystorepanel/html"* ]]; then
  SERVER_NAME="mystorepanel"
  XDEBUG_PORT="${XDEBUG_PORT:-59896}"
elif [[ "$PWD" == *"/var/www/web-templates/html"* ]]; then
  SERVER_NAME="web-templates"
  XDEBUG_PORT="${XDEBUG_PORT:-59895}"
else
  # Fallback (tweak if needed)
  SERVER_NAME="${SERVER_NAME:-mystorepanel}"
  XDEBUG_PORT="${XDEBUG_PORT:-59896}"
fi

# 📛 Show help + error usage
show_error_and_exit() {
  clear
  printf "🚫 \033[1;31mError:\033[0m %s\n\n" "$1"
  printf "📘 \033[1mUsage:\033[0m\n"
  printf "  \033[1;32mbash tests.sh <TestFolder> [--filter keyword] [--coverage|--isolation|--debug]\033[0m\n\n"
  printf "💡 \033[1mExamples:\033[0m\n"
  printf "  📁 Run all unit tests:\n"
  printf "     \033[1;32mbash tests.sh tests/Unit\033[0m\n\n"
  printf "  📁 Run all feature tests:\n"
  printf "     \033[1;32mbash tests.sh tests/Feature\033[0m\n\n"
  printf "  🔍 Run only tests containing 'upd' keywork in test name:\n"
  printf "     \033[1;32mbash tests.sh tests/Feature --filter upd\033[0m\n\n"
  printf "  📊 Generate code coverage report:\n"
  printf "     \033[1;32mbash tests.sh tests/Unit --coverage\033[0m\n\n"
  printf "  🧩 Run each test in isolation:\n"
  printf "     \033[1;32mbash tests.sh tests/Feature --isolation\033[0m\n\n"
  printf "  🐛 Enable debug output:\n"
  printf "     \033[1;32mbash tests.sh tests/Feature --debug\033[0m\n\n"
  printf "  💻 Silent test output for CI environment:\n"
  printf "     \033[1;32mbash tests.sh tests/Feature --ci\033[0m\n\n"
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
printf '🌐  IDE_HOST:%s\n' "         $IDE_HOST"
printf '🪪  SERVER_NAME:%s\n' "      $SERVER_NAME"
printf '🔌  XDEBUG_PORT:%s\n' "      $XDEBUG_PORT"


while [ $# -gt 0 ]; do
  case "$1" in
    --filter)
      shift
      [ -z "$1" ] && show_error_and_exit "Missing filter keyword after --filter"
      filter="--filter $1"
      ;;
    --coverage|--isolation|--debug|--ci|--slack|--slack-coverage)
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
  rm -rf storage/tests/reports/coverage
fi

# Always send mapping & connection info to the IDE
export PHP_IDE_CONFIG="serverName=${SERVER_NAME}"
export XDEBUG_CONFIG="client_host=${IDE_HOST} client_port=${XDEBUG_PORT} idekey=PHPSTORM"

# Select the correct Xdebug mode:
# - coverage modes: enable coverage engine (required by phpunit/php-code-coverage)
# - other modes: enable step debugger
if [ "$mode" = "--coverage" ] || [ "$mode" = "--slack-coverage" ]; then
  export XDEBUG_MODE="coverage"
else
  export XDEBUG_MODE="debug"
fi

# 🖥️ Info
printf "📂  Running tests in: \033[1;36m%s\033[0m\n" "$route"
printf "📌  Mode:             %s\n" "${mode:-standard}"
[ -n "$filter" ] && printf "🔎 Filter: %s\n" "$filter"
printf "⚠️  Make sure you are INSIDE your Docker container\n"
printf "────────────────────────────────────────────────────────────\n\n"

# 🏃 Execute PHPUnit and log the output
log_file="storage/logs/phpunit.log"
timestamp=$(date +"%Y-%m-%d %H:%M:%S")

# 🧲 Always-on Xdebug env (will be prefixed to all php/phpunit runs)
# - XDEBUG_MODE=coverage when coverage is requested; otherwise debug.
if [ "$mode" = "--coverage" ] || [ "$mode" = "--slack-coverage" ]; then
  XDBG_MODE="coverage"
else
  XDBG_MODE="debug"
fi
# Common ENV prefix used below
XDBG_ENV='PHP_IDE_CONFIG=serverName='"$SERVER_NAME"' XDEBUG_MODE='"$XDBG_MODE"' XDEBUG_CONFIG="client_host='"$IDE_HOST"' client_port='"$XDEBUG_PORT"' idekey=PHPSTORM"'

# For debugging:
# printf "XDEBUG ENV  = $XDBG_ENV %s\n"
# printf "XDEBUG MODE = $XDBG_MODE %s\n"
# 🏃 Execute PHPUnit
case "$mode" in
  --coverage)
    # NOTE: When generating coverage we prefer disabling step debugger; still Xdebug is ON.
    eval $XDBG_ENV php -d xdebug.mode=coverage ./vendor/bin/phpunit \
      --configuration phpunit.xml "$route" $filter \
      --testdox --colors=always --log-junit "$junit_file" \
      --coverage-html storage/tests/reports/coverage \
      | tee -a "$log_file"
    ;;
  --isolation)
    eval $XDBG_ENV ./vendor/bin/phpunit --configuration phpunit.xml \
      --no-coverage \
      "$route" $filter \
      --testdox --colors=always --log-junit "$junit_file" \
      --process-isolation \
      | tee -a "$log_file"
    ;;
  --debug)
    eval $XDBG_ENV ./vendor/bin/phpunit --configuration phpunit.xml \
      --no-coverage \
      "$route" $filter \
      --testdox --colors=always --debug --log-junit "$junit_file" \
      | tee -a "$log_file"
    ;;
  --ci)
    # Silent output suitable for CI environments
    ci_mode=true
    eval $XDBG_ENV ./vendor/bin/phpunit --configuration phpunit.xml \
      --no-coverage \
      "$route" $filter \
      --colors=always --no-coverage --log-junit "$junit_file" \
      | tee -a "$log_file"
    ;;
  --slack-coverage)
    slack_notify=true
    eval $XDBG_ENV php -d xdebug.mode=coverage ./vendor/bin/phpunit \
      --configuration phpunit.xml "$route" $filter \
      --testdox --colors=always --log-junit "$junit_file" \
      --coverage-html storage/tests/reports/coverage \
      | tee -a "$log_file"
    ;;
  --slack)
    slack_notify=true
    eval $XDBG_ENV ./vendor/bin/phpunit --configuration phpunit.xml \
      --no-coverage \
      "$route" $filter --log-junit "$junit_file" \
      --testdox --colors=always \
      | tee -a "$log_file"
    ;;
  *)
    eval $XDBG_ENV ./vendor/bin/phpunit --configuration phpunit.xml \
      --no-coverage \
      "$route" $filter --log-junit "$junit_file" \
      --testdox --colors=always \
      | tee -a "$log_file"
    ;;
esac

# 🧪 Parse test results from JUnit XML
if [ "$ci_mode" != true ] && [ -f "$junit_file" ]; then
  tests=$(xmllint --xpath "string(//testsuite/@tests)" "$junit_file")
  failures=$(xmllint --xpath "string(//testsuite/@failures)" "$junit_file")
  errors=$(xmllint --xpath "string(//testsuite/@errors)" "$junit_file")
  assertions=$(xmllint --xpath "string(//testsuite/@assertions)" "$junit_file")

  summary="🧪 $tests tests, ✅ $assertions assertions, ❌ $failures failures, 🔥 $errors errors"
  printf "%s\n" "$summary"
  echo "$summary" >> "$log_file"
fi

# 📨 Slack Notification
if [ "$slack_notify" = true ]; then
  printf "Sending to Slack... (Channel: my-store-admin-test-runner)\n"
  slack_webhook_url="${SLACK_PHPUNIT_NOTIFY_WEBHOOK_URL:-unknown}"
  slack_username="${TEST_RUN_USER:-unknown}"

  # Calculate duration
  end_time=$(date +%s)
  duration=$((end_time - start_time))
  duration_fmt=$(printf "%02dm:%02ds" $((duration / 60)) $((duration % 60)))

  # Build public coverage URL from SERVER_NAME (no localhost, no port).
  # If SERVER_NAME already has a dot (e.g., mystorepanel.test), use it as-is;
  # otherwise assume ".test".
  if [ -n "${SERVER_NAME:-}" ]; then
    case "$SERVER_NAME" in
      *.*) COVERAGE_HOST="$SERVER_NAME" ;;     # already FQDN-like
      *)   COVERAGE_HOST="${SERVER_NAME}.test" ;;
    esac
  else
    COVERAGE_HOST="localhost"
  fi
  # printf $COVERAGE_HOST" \n"

  # 📊 Build coverage link (only for --slack-coverage) and guard by file existence
  coverage_file="storage/tests/reports/coverage/index.html"
  if [ "$mode" = "--slack-coverage" ]; then
    if [ -f "$coverage_file" ]; then
      coverage_link="http://${COVERAGE_HOST}/storage/tests/reports/coverage/index.html"
      printf "📊  Coverage: link ready →  %s\n" "$coverage_link"
    else
      coverage_link="(coverage report not found)"
      printf "📊  Coverage: report not found at %s\n" "$coverage_file"
    fi
  else
    coverage_link="Coverage mode not selected"
    printf "📊  Coverage: mode not selected; no link will be sent\n"
  fi

  # Only add filter info if a filter is provided
  if [ -n "$filter" ]; then
    filter_info="$filter"
  else
    filter_info="No filter applied"
  fi
  # Slack payload
  slack_payload=$(cat <<EOF
{
  "username": "PHPUnit Bot",
  "icon_emoji": ":test_tube:",
  "text": "*✅ Tests finished by:* \`$slack_username\`
  *📂 Folder:* \`$route\`
  *🔎 Filter:* \`$filter_info\`
  *⚙️ Mode:* \`$mode\`
  *⏱ Duration:* \`$duration_fmt\`
  *📄 Summary:* $summary
  *📊 Coverage:* $coverage_link"
}
EOF
)

  curl -s -X POST -H 'Content-type: application/json' \
    --data "$slack_payload" \
    "$slack_webhook_url" > /dev/null
fi

# Add a separator and timestamp at the end of the log entry
{
  printf '\n──────────────────────────────────────────\n'
  printf 'Log entry finished at: %s\n' "$timestamp"
  printf '──────────────────────────────────────────\n\n'
} >> "$log_file"


if [ "$ci_mode" != true ]; then
  # 📝 Log entry finished
  printf "📝  Log entry finished at:  \033[1;36m%s\033[0m\n" "$timestamp"

  # 📝 Log file location
  printf "📝  Test results logged to: \033[1;36m%s\033[0m\n" "$log_file"
fi
