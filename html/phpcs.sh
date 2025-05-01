#!/bin/bash

# Please run this script inside of your container.

# Function to display error messages and usage instructions
show_error_and_exit() {
  printf "%s\033[1;31m$1\033[0m"
  printf "\nUsage\n"
  printf " sh phpcs.sh s Path/To/File/Or/Folder/To/Sniff\n\n"
  printf "Common Examples (Will run in the\033[1;94m app\033[0m folder or the\033[1;94m tests\033[0m folder)\n"
  printf "\033[1;32m sh phpcs.sh s app\033[0m\n"
  printf "\033[1;32m sh phpcs.sh s tests\033[0m\n"
  echo "" 1>&2
  exit 1
}

# Validate input arguments
condition=$1
file=$2

clear
echo "**********************************************************************"
echo "Running PHP Code Sniffer"
echo "**********************************************************************"
echo ""

if [ -z "$condition" ]; then
  show_error_and_exit "Must provide condition to run (sniff 's' or beautify 'b')"
fi

if [ -z "$file" ]; then
  show_error_and_exit "Must provide file or path to sniff."
fi

if [ "$file" = "/" ] || [ "$file" = "./vendor" ] || [ "$file" = "vendor" ]; then
  show_error_and_exit "The script cannot be run on the root of the project or the vendor folder."
fi

if [ ! -f "$file" ] && [ ! -d "$file" ]; then
  show_error_and_exit "File or directory does not exist: $file"
fi

# Execute the appropriate command based on the condition
if [ "$condition" = "s" ]; then
  # php ./vendor/bin/phpcs -s --standard=PSR12 "$file"
  php ./vendor/bin/phpcs -s --standard=phpcs.xml "$file"
elif [ "$condition" = "b" ]; then
  php ./vendor/bin/phpcbf --standard=phpcs.xml "$file"
else
  show_error_and_exit "Invalid condition. Use 's' for sniff or 'b' for beautify."
fi
