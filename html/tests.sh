#!/bin/bash

# 🧪 Laravel PHPUnit Runner - Enhanced
# Please run this script *inside your Docker container*

start_time=$(date +%s)
junit_file="storage/tests/reports/results.xml"
ci_mode=false

# 📛 Show help + error usage
show_error_and_exit() {
  clear
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
      --testdox --colors=always --log-junit "$junit_file" \
      --coverage-html tests/reports/coverage \
      | tee -a "$log_file"
    ;;
  --isolation)
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always --log-junit "$junit_file" \
      --process-isolation \
      | tee -a "$log_file"
    ;;
  --debug)
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --testdox --colors=always --debug --log-junit "$junit_file" \
      | tee -a "$log_file"
    ;;
  --ci)
    # Silent output suitable for CI environments
    ci_mode=true
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter \
      --colors=always --no-coverage --log-junit "$junit_file" \
      | tee -a "$log_file"
    ;;
  --slack-coverage)
    slack_notify=true
      php -d xdebug.mode=coverage ./vendor/bin/phpunit \
        --configuration phpunit.xml "$route" $filter \
        --testdox --colors=always --log-junit "$junit_file" \
        --coverage-html tests/reports/coverage \
        | tee -a "$log_file"
      ;;
  --slack)
    slack_notify=true
    ./vendor/bin/phpunit --configuration phpunit.xml \
      "$route" $filter --log-junit "$junit_file" \
      --testdox --colors=always\
      | tee -a "$log_file"
    ;;
  *)
    ./vendor/bin/phpunit --configuration phpunit.xml \
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
  printf "Sending to Slack\n"
  slack_webhook_url="${SLACK_PHPUNIT_NOTIFY_WEBHOOK_URL:-unknown}"
  slack_username="${TEST_RUN_USER:-unknown}"
  printf $slack_webhook_url" \n"
  printf $slack_username" \n"

  # Calculate duration
  end_time=$(date +%s)
  duration=$((end_time - start_time))
  duration_fmt=$(printf "%02dm:%02ds" $((duration / 60)) $((duration % 60)))

  # Coverage link (if used)
  if [ "$mode" = "--slack-coverage" ]; then
    printf "Sending also link to see coverage\n"
    coverage_link="http://localhost:${APP_PORT:-80}/tests/reports/coverage/index.html"
  else
    printf "Coverage mode not selected, not sending coverage information\n"
    coverage_link="Coverage mode not selected"
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
var1="\n────────────────────────────────────────────────────────────\n"
var2="Log entry finished at: $timestamp \n"
var3="──────────────────────────────────────────────────────────────\n\n"
echo "$var1" "$var2" "$var3" >> "$log_file"

if [ "$ci_mode" != true ]; then
  # 📝 Log entry finished
  printf "📝 Log entry finished at: \033[1;36m%s\033[0m\n" "$timestamp"

  # 📝 Log file location
  printf "📝 Test results logged to: \033[1;36m%s\033[0m\n" "$log_file"
fi
