#!/bin/sh

# This script downloads the latest version of context-generator from GitHub
# and installs it to a user bin directory.
# To use a GitHub token, pass it through the GITHUB_PAT environment variable.

# GLOBALS

# Colors
RED='\033[31m'
GREEN='\033[32m'
YELLOW='\033[33m'
DEFAULT='\033[0m'

# Project name
PNAME='ctx'
REPO_OWNER='butschster'
REPO_NAME='context-generator'

# GitHub API address
GITHUB_API="https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases"
# GitHub Release address
GITHUB_REL="https://github.com/$REPO_OWNER/$REPO_NAME/releases/download"

# Default install directory
DEFAULT_BIN_DIR="/usr/local/bin"

# FUNCTIONS

# Gets the version of the latest stable version by setting the $latest variable.
# Returns 0 in case of success, 1 otherwise.
get_latest() {
  # temp_file is needed because the grep would start before the download is over
  temp_file=$(mktemp -q /tmp/$PNAME.XXXXXXXXX)
  latest_release="$GITHUB_API/latest"

  if ! temp_file=$(mktemp -q /tmp/$PNAME.XXXXXXXXX); then
    echo "$0: Can't create temp file."
    fetch_release_failure_usage
    exit 1
  fi

  if [ -z "$GITHUB_PAT" ]; then
    curl -s "$latest_release" >"$temp_file" || return 1
  else
    curl -H "Authorization: token $GITHUB_PAT" -s "$latest_release" >"$temp_file" || return 1
  fi

  latest="$(grep <"$temp_file" '"tag_name":' | cut -d ':' -f2 | tr -d '"' | tr -d ',' | tr -d ' ' | tr -d 'v')"
  latestV="$(grep <"$temp_file" '"tag_name":' | cut -d ':' -f2 | tr -d '"' | tr -d ',' | tr -d ' ')"

  rm -f "$temp_file"
  return 0
}

fetch_release_failure_usage() {
  echo ''
  printf "${RED}ERROR: Impossible to get the latest stable version of $PNAME.${DEFAULT}\n"
  echo "Please let us know about this issue: https://github.com/$REPO_OWNER/$REPO_NAME/issues/new"
  echo ''
  echo "In the meantime, you can manually download the appropriate binary from the GitHub release assets here: https://github.com/$REPO_OWNER/$REPO_NAME/releases/latest"
}

ensure_bin_dir() {
  # Create bin directory if it doesn't exist
  if [ ! -d "$bin_dir" ]; then
    printf "${YELLOW}Creating directory $bin_dir...${DEFAULT}\n"
    mkdir -p "$bin_dir" || {
      printf "${RED}ERROR: Could not create directory $bin_dir${DEFAULT}\n"
      exit 1
    }
  fi

  # Check if bin_dir is in PATH
  if ! echo "$PATH" | tr ':' '\n' | grep -q "^$bin_dir$"; then
    printf "${YELLOW}WARNING: $bin_dir is not in your PATH.${DEFAULT}\n"
    printf "You might want to add the following line to your shell profile (.bashrc, .zshrc, etc.):\n"
    printf "    ${GREEN}export PATH=\"\$PATH:$bin_dir\"${DEFAULT}\n\n"
  fi
}

download_and_install() {
  # Get the latest version
  if ! get_latest; then
    fetch_release_failure_usage
    exit 1
  fi

  if [ "$latest" = '' ]; then
    fetch_release_failure_usage
    exit 1
  fi

  # Download the phar file
  printf "Downloading $PNAME $latestV...\n"
  phar_url="$GITHUB_REL/$latestV/$PNAME.phar"

  if ! curl --fail -L "$phar_url" -o "$bin_dir/$PNAME"; then
    printf "${RED}ERROR: Failed to download $phar_url${DEFAULT}\n"
    exit 1
  fi

  # Make executable
  chmod +x "$bin_dir/$PNAME" || {
    printf "${RED}ERROR: Failed to make $bin_dir/$PNAME executable${DEFAULT}\n"
    exit 1
  }

  printf "${GREEN}$PNAME $latestV successfully installed to $bin_dir/$PNAME${DEFAULT}\n"
  printf "You can now run it using: $PNAME generate\n"
}

# Main script execution
echo "Context Generator Installer"
echo "==========================="

# Determine bin directory
if [ -n "$1" ]; then
  bin_dir="$1"
else
  bin_dir="$DEFAULT_BIN_DIR"
  printf "No installation directory specified. Using default: $bin_dir\n"
  printf "You can specify a different directory by passing it as an argument: $0 /path/to/bin\n\n"
fi

# Ensure bin directory exists and is in PATH
ensure_bin_dir

# Download and install the latest version
download_and_install