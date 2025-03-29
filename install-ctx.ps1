# Context Generator PowerShell Installer
# This script downloads and installs the Context Generator (ctx) tool on Windows systems

# Project settings
$ProjectName = "ctx"
$RepoOwner = "context-hub"
$RepoName = "generator"
$GithubApi = "https://api.github.com/repos/$RepoOwner/$RepoName/releases"
$GithubReleases = "https://github.com/$RepoOwner/$RepoName/releases/download"

# Default installation directory (User's bin folder)
$DefaultBinDir = "$env:USERPROFILE\bin"

function Write-Header {
    param([string]$Message)

    Write-Host "`n$Message" -ForegroundColor Blue -BackgroundColor Black
    Write-Host ("-" * $Message.Length) -ForegroundColor Blue -BackgroundColor Black
    Write-Host
}

function Write-Status {
    param([string]$Message)

    Write-Host " >> $Message" -ForegroundColor DarkGray
}

function Write-Success {
    param([string]$Message)

    Write-Host " [OK] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)

    Write-Host " [WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)

    Write-Host " [ERROR] $Message" -ForegroundColor Red
}

function Ensure-BinDirectory {
    param([string]$BinDir)

    # Create bin directory if it doesn't exist
    if (-not (Test-Path -Path $BinDir)) {
        Write-Status "Creating directory $BinDir..."

        try {
            New-Item -ItemType Directory -Path $BinDir -Force | Out-Null
            Write-Success "Directory created successfully"
        }
        catch {
            Write-Error "Could not create directory $BinDir"
            exit 1
        }
    }

    # Check if bin_dir is in PATH
    $currentPath = [Environment]::GetEnvironmentVariable("PATH", "User")
    if (-not $currentPath.Split(';').Contains($BinDir)) {
        Write-Warning "$BinDir is not in your PATH."
        Write-Host "You might want to add it to your Windows PATH:" -ForegroundColor White
        Write-Host "    1. Right-click on 'This PC' or 'My Computer' and select 'Properties'" -ForegroundColor Green
        Write-Host "    2. Click on 'Advanced system settings'" -ForegroundColor Green
        Write-Host "    3. Click on 'Environment Variables'" -ForegroundColor Green
        Write-Host "    4. Under 'User variables', select 'Path' and click 'Edit'" -ForegroundColor Green
        Write-Host "    5. Click 'New' and add: $BinDir" -ForegroundColor Green
        Write-Host "    6. Click 'OK' on all dialogs" -ForegroundColor Green
        Write-Host "`nOr run this command to add it temporarily for this session:" -ForegroundColor White
        Write-Host "    `$env:Path += `";$BinDir`"" -ForegroundColor Green
        Write-Host

        $addToPath = Read-Host "Would you like to add this directory to your PATH now? (y/N)"
        if ($addToPath -eq "y" -or $addToPath -eq "Y") {
            try {
                $newPath = $currentPath + ";" + $BinDir
                [Environment]::SetEnvironmentVariable("PATH", $newPath, "User")
                $env:Path += ";$BinDir"
                Write-Success "Added $BinDir to your PATH"
            }
            catch {
                Write-Error "Failed to add directory to PATH. Please add it manually."
            }
        }
    }
}

function Get-LatestVersion {
    param([string]$Version)

    # If version was specified, use it directly
    if ($Version) {
        # Remove 'v' prefix if present
        $latest = $Version -replace '^v', ''
        $latestV = $latest
        Write-Success "Using specified version: $latestV"
        return $latestV, $latest
    }

    # Otherwise, get latest from GitHub
    Write-Status "Checking for latest version..."

    try {
        $headers = @{}
        if ($env:GITHUB_PAT) {
            $headers["Authorization"] = "token $env:GITHUB_PAT"
        }

        $response = Invoke-RestMethod -Uri "$GithubApi/latest" -Headers $headers
        $latest = $response.tag_name -replace '^v', ''
        $latestV = $response.tag_name

        if ($latest) {
            Write-Success "Latest version found: $latestV"
            return $latestV, $latest
        }
        else {
            throw "No version found"
        }
    }
    catch {
        Write-Error "Impossible to get the latest stable version of $ProjectName."
        Write-Host "Please let us know about this issue: https://github.com/$RepoOwner/$RepoName/issues/new"
        Write-Host "`nIn the meantime, you can manually download the appropriate binary from the GitHub release assets here: https://github.com/$RepoOwner/$RepoName/releases/latest"
        exit 1
    }
}

function Download-And-Install {
    param(
        [string]$BinDir,
        [string]$Version
    )

    # Get the latest version
    Write-Header "Checking for updates"

    $versionInfo = Get-LatestVersion -Version $Version
    $latestV = $versionInfo[0]
    $latest = $versionInfo[1]

    # Determine OS architecture
    if ([Environment]::Is64BitOperatingSystem) {
        $arch = "amd64"
    }
    else {
        Write-Error "Only 64-bit Windows is supported at this time"
        exit 1
    }

    $releaseFile = "$ProjectName-$latest-windows-$arch.exe"
    $binaryName = "$ProjectName.exe"

    # Download the binary file
    Write-Header "Downloading the latest version"
    Write-Status "Preparing download from: $GithubReleases/$latestV/$releaseFile"

    $tempFile = [System.IO.Path]::GetTempFileName() + ".exe"

    try {
        # Download with progress bar
        $webClient = New-Object System.Net.WebClient
        $webClient.DownloadFile("$GithubReleases/$latestV/$releaseFile", $tempFile)

        Write-Success "Successfully downloaded version $latestV"
        Write-Status "Saved to temporary file: $tempFile"
    }
    catch {
        Write-Error "Failed to download $GithubReleases/$latestV/$releaseFile"
        Write-Host "Error: $_"
        if (Test-Path $tempFile) {
            Remove-Item -Force $tempFile
        }
        exit 1
    }

    # Install the binary
    Write-Header "Installing the update"
    Write-Status "Installing binary to: $BinDir\$binaryName"

    try {
        # Make sure there isn't an existing file being used
        if (Test-Path "$BinDir\$binaryName") {
            # Try to remove the existing file
            try {
                Remove-Item -Force "$BinDir\$binaryName"
            }
            catch {
                Write-Error "Couldn't remove existing binary. The file may be in use."
                Write-Host "Please close any running instances of $binaryName and try again."
                exit 1
            }
        }

        # Move the file to the destination
        Move-Item -Path $tempFile -Destination "$BinDir\$binaryName" -Force

        Write-Success "Successfully installed $latestV to $BinDir\$binaryName"
        Write-Host "`n     You can now run it using:" -ForegroundColor White
        Write-Host "         $binaryName" -ForegroundColor Cyan
        Write-Host "`n     📚 Documentation: https://docs.ctxgithub.com"
        Write-Host "     🚀 Happy AI coding!"
    }
    catch {
        Write-Error "Failed to install binary to $BinDir\$binaryName"
        Write-Host "Error: $_"
        if (Test-Path $tempFile) {
            Remove-Item -Force $tempFile
        }
        exit 1
    }
}

# Main execution
Write-Host "Context Generator Installer" -ForegroundColor Cyan -BackgroundColor Black
Write-Host "===========================" -ForegroundColor Cyan -BackgroundColor Black
Write-Host

# Parse arguments
$BinDir = $DefaultBinDir
$Version = ""

# Check for version flag
foreach ($arg in $args) {
    if ($arg -match '^--version=(.+)$' -or $arg -match '^-v=(.+)$') {
        $Version = $matches[1]
    }
    elseif ($arg -eq '--version' -or $arg -eq '-v') {
        # Next argument should be the version
        $versionIndex = [array]::IndexOf($args, $arg) + 1
        if ($versionIndex -lt $args.Count) {
            $Version = $args[$versionIndex]
        }
        else {
            Write-Error "Version argument is missing"
            exit 1
        }
    }
    elseif (-not ($arg -match '^-')) {
        # If it's not a flag, assume it's the bin directory
        $BinDir = $arg
    }
}

Write-Status "Installation directory: $BinDir"
if ($Version) {
    Write-Status "Installing version: $Version"
}
else {
    Write-Status "No version specified. Will install the latest version."
    Write-Host "`n      You can specify a different directory by running:" -ForegroundColor DarkGray
    Write-Host '      .\install-ctx.ps1 C:\path\to\bin' -ForegroundColor DarkGray
    Write-Host "      Specify a specific version with:" -ForegroundColor DarkGray
    Write-Host '      .\install-ctx.ps1 -v v1.2.3 C:\path\to\bin' -ForegroundColor DarkGray
    Write-Host
}

# Ensure bin directory exists and is in PATH
Ensure-BinDirectory -BinDir $BinDir

# Download and install the latest version
Download-And-Install -BinDir $BinDir -Version $Version