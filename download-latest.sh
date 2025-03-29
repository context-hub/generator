#!/bin/sh

# This script downloads the latest version of context-generator from GitHub
# and installs it to a user bin directory.
# To use a GitHub token, pass it through the GITHUB_PAT environment variable.

# GLOBALS

# Colors
RED='\033[31m'
MUTED='\033[2m'
GREEN='\033[32m'
YELLOW='\033[33m'
BLUE='\033[34m'
BOLD='\033[1m'
DEFAULT='\033[0m'

# Symbols (ASCII compatible)
CHECK_MARK="✓"
CROSS_MARK="✗"
ARROW="→"

# Project name
PNAME='ctx'
REPO_OWNER='context-hub'
REPO_NAME='generator'

# GitHub API address
GITHUB_API="https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases"
# GitHub Release address
GITHUB_REL="https://github.com/$REPO_OWNER/$REPO_NAME/releases/download"

# Default install directory
DEFAULT_BIN_DIR="/usr/local/bin"

# FUNCTIONS

# Print a section header
print_header() {
  printf "\n${BOLD}$1${DEFAULT}\n"
  printf "%-${#1}s\n" | tr " " "-"
  printf "\n"
}

# Print a status message
print_status() {
  printf " ${MUTED} >>  ${MUTED}$1${DEFAULT}\n"
}

# Print a success message
print_success() {
  printf " ${GREEN}[OK]${DEFAULT} $1\n"
}

# Print an error message
print_error() {
  printf " ${RED}[ERROR]${DEFAULT} $1\n"
}

# Print a warning message
print_warning() {
  printf " ${YELLOW}[WARNING]${DEFAULT} $1\n"
}

# Gets the version of the latest stable version by setting the $latest variable.
# Returns 0 in case of success, 1 otherwise.
get_latest() {
  # temp_file is needed because the grep would start before the download is over
  temp_file=$(mktemp -q /tmp/$PNAME.XXXXXXXXX)
  latest_release="$GITHUB_API/latest"

  if ! temp_file=$(mktemp -q /tmp/$PNAME.XXXXXXXXX); then
    print_error "Can't create temp file."
    fetch_release_failure_usage
    exit 1
  fi

  print_status "Checking for latest version..."

  if [ -z "$GITHUB_PAT" ]; then
    curl -s "$latest_release" >"$temp_file" || return 1
  else
    curl -H "Authorization: token $GITHUB_PAT" -s "$latest_release" >"$temp_file" || return 1
  fi

  latest="$(grep <"$temp_file" '"tag_name":' | cut -d ':' -f2 | tr -d '"' | tr -d ',' | tr -d ' ' | tr -d 'v')"
  latestV="$(grep <"$temp_file" '"tag_name":' | cut -d ':' -f2 | tr -d '"' | tr -d ',' | tr -d ' ')"

  rm -f "$temp_file"

  if [ -n "$latest" ]; then
    print_success "Latest version found: $latestV"
  fi

  return 0
}

# Gets the OS by setting the $os variable.
# Returns 0 in case of success, 1 otherwise.
get_os() {
  os_name=$(uname -s)
  case "$os_name" in
    # ---
  'Linux')
    os='linux'
    ;;

    # ---
  *)
    return 1
    ;;
  esac
  return 0
}

# Gets the architecture by setting the $arch variable.
# Returns 0 in case of success, 1 otherwise.
get_arch() {
  architecture=$(uname -m)

  # case 1
  case "$architecture" in
  'x86_64' | 'amd64')
    arch='amd64'
    ;;

    # case 2
  'arm64' | 'aarch64')
    arch='arm64'
    ;;

  # all other
  *)
    return 1
    ;;
  esac

  return 0
}

not_available_failure_usage() {
  print_error 'ctx binary is not available for your OS distribution or your architecture yet.'
  echo ''
  echo 'However, you can easily compile the binary from the source files.'
  echo 'Follow the steps at the page ("Source" tab): TODO'
}

fetch_release_failure_usage() {
  print_error "Impossible to get the latest stable version of $PNAME."
  printf "Please let us know about this issue: https://github.com/$REPO_OWNER/$REPO_NAME/issues/new\n"
  printf "\nIn the meantime, you can manually download the appropriate binary from the GitHub release assets here: https://github.com/$REPO_OWNER/$REPO_NAME/releases/latest\n"
}

ensure_bin_dir() {
  # Create bin directory if it doesn't exist
  if [ ! -d "$bin_dir" ]; then
    print_status "Creating directory $bin_dir..."
    mkdir -p "$bin_dir" || {
      print_error "Could not create directory $bin_dir"
      exit 1
    }
    print_success "Directory created successfully"
  fi

  # Check if bin_dir is in PATH
  if ! echo "$PATH" | tr ':' '\n' | grep -q "^$bin_dir$"; then
    print_warning "$bin_dir is not in your PATH."
    printf "You might want to add the following line to your shell profile (.bashrc, .zshrc, etc.):\n"
    printf "    ${GREEN}export PATH=\"\$PATH:$bin_dir\"${DEFAULT}\n\n"
  fi
}

download_and_install() {
  # Get the latest version
  print_header "Checking for updates"

  if ! get_latest; then
    fetch_release_failure_usage
    exit 1
  fi

  if [ "$latest" = '' ]; then
    fetch_release_failure_usage
    exit 1
  fi

  # Fill $os variable.
  if ! get_os; then
    not_available_failure_usage
    exit 1
  fi
  # Fill $arch variable.
  if ! get_arch; then
    not_available_failure_usage
    exit 1
  fi

  release_file="$PNAME-$latest-$os-$arch"

  # Download the binary file
  print_header "Downloading the latest version"
  print_status "Preparing download from: $GITHUB_REL/$latestV/$release_file"

  temp_file=$(mktemp -q /tmp/$release_file-XXXXXXXXX)
  if ! temp_file=$(mktemp -q /tmp/$release_file-XXXXXXXXX); then
    print_error "Can't create temp file for download."
    exit 1
  fi

  echo "\n"

  # Use curl with progress bar but suppress most headers
  if ! curl --fail -L "$GITHUB_REL/$latestV/$release_file" -o "$temp_file" \
       --progress-bar --write-out "%{http_code}" | grep -q "^2"; then
    printf "] ${RED}Failed!${DEFAULT}\n"
    print_error "Failed to download $GITHUB_REL/$latestV/$release_file"
    rm -f "$temp_file"
    exit 1
  fi

  echo "\n"

  print_success "Successfully downloaded version $latestV"
  print_status "Saved to temporary file: $temp_file"

  # Install the binary
  print_header "Installing the update"
  print_status "Replacing current binary at: $bin_dir/$release_file"

  if ! mv "$temp_file" "$bin_dir/$release_file"; then
    print_error "Failed to move binary to $bin_dir/$release_file"
    rm -f "$temp_file"
    exit 1
  fi

  # Make executable
  if ! chmod +x "$bin_dir/$release_file"; then
    print_error "Failed to make $bin_dir/$release_file executable"
    exit 1
  fi

  print_success "Successfully replaced the binary file"
  print_success "Successfully installed $release_file $latestV to $bin_dir/$release_file\n"

  printf "     You can now run it using:\n"
  printf "         ${BOLD}$release_file${DEFAULT}\n\n"
  printf "     📚 Documentation: https://docs.ctxgithub.com\n"
  printf "     🚀 Happy AI coding!\n\n"
}

# Main script execution
printf "${BOLD}Context Generator Installer${DEFAULT}\n"
printf "===========================\n\n"

# Determine bin directory
if [ -n "$1" ]; then
  bin_dir="$1"
  print_status "Installation directory: $bin_dir"
else
  bin_dir="$DEFAULT_BIN_DIR"
  print_status "No installation directory specified. Using default: $bin_dir\n"
  printf "      ${MUTED}You can specify a different directory by running:${DEFAULT}\n"
  printf "      ${MUTED}curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh -s /path/to/bin${DEFAULT}\n\n"
fi

# Ensure bin directory exists and is in PATH
ensure_bin_dir

# Download and install the latest version
download_and_install