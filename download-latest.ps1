<#
.SYNOPSIS
Downloads and installs the latest version of CTX (Context Generator) on Windows.

.DESCRIPTION
This script downloads and installs the CTX native binary from GitHub releases.
By default, installs to %LOCALAPPDATA%\ctx for the current user.

EXECUTION POLICY:
If you get an execution policy error, run this command instead:
  PowerShell -ExecutionPolicy Bypass -File download-latest.ps1

.PARAMETER Path
Installation directory. Defaults to $env:LOCALAPPDATA\ctx.

.PARAMETER CurrentDir
Install to current directory instead of default location.

.PARAMETER AddToPath
Add installation directory to PATH environment variable (default: $true for default location, $false for current directory).

.PARAMETER Force
Force reinstallation even if same version exists.

.PARAMETER Silent
Suppress interactive prompts and use defaults.

.PARAMETER SkipVerification
Skip checksum verification and post-installation checks.

.EXAMPLE
PowerShell -ExecutionPolicy Bypass -File download-latest.ps1
Installs CTX to %LOCALAPPDATA%\ctx and adds to PATH.

.EXAMPLE
.\download-latest.ps1 -CurrentDir
Installs CTX to current directory without adding to PATH.

.EXAMPLE
.\download-latest.ps1 -CurrentDir -AddToPath
Installs CTX to current directory and adds to PATH.

.EXAMPLE
.\download-latest.ps1 -Path "C:\Tools\ctx"
Installs CTX to custom location and adds to PATH.

.EXAMPLE
.\download-latest.ps1 -Silent -Force
Silent reinstallation to default location.
#>

[CmdletBinding(SupportsShouldProcess)]
param(
    [Parameter(Position=0)]
    [string]$Path = "",
    
    [Parameter()]
    [switch]$CurrentDir,
    
    [Parameter()]
    [switch]$AddToPath,
    
    [Parameter()]
    [switch]$Force,
    
    [Parameter()]
    [switch]$Silent,
    
    [Parameter()]
    [switch]$SkipVerification
)

#Requires -Version 5.1

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# Constants
$Script:GITHUB_API = "https://api.github.com/repos/context-hub/generator/releases/latest"
$Script:GITHUB_REPO = "context-hub/generator"
$Script:BINARY_NAME = "ctx.exe"
$Script:ARCH = "amd64"
$Script:DEFAULT_INSTALL_PATH = Join-Path $env:LOCALAPPDATA "ctx"

# Color output functions
function Write-ColorOutput {
    param(
        [Parameter(Mandatory=$true)]
        [string]$Message,
        [Parameter(Mandatory=$false)]
        [ConsoleColor]$ForegroundColor = [ConsoleColor]::White
    )
    
    try {
        $previousColor = $Host.UI.RawUI.ForegroundColor
        $Host.UI.RawUI.ForegroundColor = $ForegroundColor
        Write-Output $Message
        $Host.UI.RawUI.ForegroundColor = $previousColor
    } catch {
        Write-Output $Message
    }
}

function Write-Success {
    param([string]$Message)
    Write-ColorOutput "[OK] $Message" -ForegroundColor Green
}

function Write-Info {
    param([string]$Message)
    Write-ColorOutput "[INFO] $Message" -ForegroundColor Cyan
}

function Write-Warning {
    param([string]$Message)
    Write-ColorOutput "[WARN] $Message" -ForegroundColor Yellow
}

function Write-ErrorMessage {
    param([string]$Message)
    Write-ColorOutput "[ERROR] $Message" -ForegroundColor Red
}

function Write-Step {
    param([string]$Message)
    Write-Output ""
    Write-ColorOutput "==> $Message" -ForegroundColor Magenta
}

# Get latest release information from GitHub
function Get-LatestRelease {
    Write-Step "Fetching latest release information"
    
    try {
        $headers = @{
            'User-Agent' = 'CTX-Installer/1.0'
            'Accept' = 'application/vnd.github.v3+json'
        }
        
        $response = Invoke-WebRequest -Uri $Script:GITHUB_API -Headers $headers -UseBasicParsing
        $release = $response.Content | ConvertFrom-Json
        
        Write-Success "Found release: $($release.tag_name)"
        return $release
        
    } catch {
        throw "Failed to fetch release information: $_"
    }
}

# Find appropriate asset in release
function Get-ReleaseAsset {
    param(
        [Parameter(Mandatory=$true)]
        $Release
    )
    
    $pattern = "ctx-.*-windows-$($Script:ARCH)\.exe$"
    Write-Info "Looking for: $pattern"
    
    $asset = $Release.assets | Where-Object { $_.name -match $pattern } | Select-Object -First 1
    
    if (-not $asset) {
        Write-ErrorMessage "Available assets:"
        foreach ($a in $Release.assets) {
            Write-Info "  - $($a.name)"
        }
        throw "Could not find Windows binary for $($Script:ARCH)"
    }
    
    Write-Info "Selected: $($asset.name)"
    return $asset
}

# Download file
function Get-File {
    param(
        [Parameter(Mandatory=$true)]
        [string]$Url,
        [Parameter(Mandatory=$true)]
        [string]$OutFile
    )
    
    Write-Info "Downloading from: $Url"
    
    $originalProgress = $ProgressPreference
    $ProgressPreference = 'Continue'
    
    Invoke-WebRequest -Uri $Url -OutFile $OutFile -UseBasicParsing
    
    $ProgressPreference = $originalProgress
    Write-Success "Downloaded successfully"
}

# Verify file checksum
function Test-FileChecksum {
    param(
        [Parameter(Mandatory=$true)]
        [string]$FilePath,
        [Parameter(Mandatory=$true)]
        [string]$AssetName,
        [Parameter(Mandatory=$true)]
        $Release
    )
    
    Write-Step "Verifying file integrity"
    
    try {
        $checksumAsset = $Release.assets | Where-Object { $_.name -eq "$AssetName.sha256" }
        
        if (-not $checksumAsset) {
            Write-Warning "No checksum file found"
            return $false
        }
        
        $checksumPath = "$FilePath.sha256"
        Get-File -Url $checksumAsset.browser_download_url -OutFile $checksumPath
        
        $checksumContent = Get-Content $checksumPath -Raw
        $expectedChecksum = $checksumContent.Split()[0].Trim().ToLower()
        
        $hash = Get-FileHash -Path $FilePath -Algorithm SHA256
        $actualChecksum = $hash.Hash.ToLower()
        
        Remove-Item $checksumPath -Force -ErrorAction SilentlyContinue
        
        if ($actualChecksum -eq $expectedChecksum) {
            Write-Success "Checksum verified"
            return $true
        } else {
            Write-ErrorMessage "Checksum mismatch!"
            return $false
        }
    } catch {
        Write-Warning "Could not verify checksum: $_"
        return $false
    }
}

# Get installed version
function Get-InstalledVersion {
    param([string]$BinaryPath)
    
    if (Test-Path $BinaryPath) {
        try {
            $versionOutput = & $BinaryPath --version 2>$null
            if ($versionOutput -match 'CTX version (\S+)') {
                return $matches[1]
            }
        } catch {
            # Could not determine version
        }
    }
    
    return $null
}

# Compare semantic versions
function Compare-Version {
    param(
        [string]$Version1,
        [string]$Version2
    )
    
    $v1 = $Version1 -replace '^v', ''
    $v2 = $Version2 -replace '^v', ''
    
    try {
        $ver1 = [version]$v1
        $ver2 = [version]$v2
        
        if ($ver1 -gt $ver2) { return 1 }
        elseif ($ver1 -lt $ver2) { return -1 }
        else { return 0 }
    } catch {
        if ($v1 -eq $v2) { return 0 }
        return -1
    }
}

# Add to PATH environment variable
function Add-ToPath {
    param(
        [Parameter(Mandatory=$true)]
        [string]$Directory
    )
    
    Write-Step "Adding to PATH"
    
    try {
        $currentPath = [Environment]::GetEnvironmentVariable('Path', 'User')
        $pathEntries = $currentPath -split ';' | Where-Object { $_ }
        
        $normalizedDir = $Directory.TrimEnd('\')
        $alreadyExists = $pathEntries | Where-Object { 
            $_.TrimEnd('\') -eq $normalizedDir 
        }
        
        if ($alreadyExists) {
            Write-Info "Directory already in PATH"
            return $true
        }
        
        $newPath = (@($pathEntries) + $Directory) -join ';'
        [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        
        $env:Path = [Environment]::GetEnvironmentVariable('Path', 'User') + ';' + 
                    [Environment]::GetEnvironmentVariable('Path', 'Machine')
        
        Write-Success "Added to User PATH"
        return $true
        
    } catch {
        Write-Warning "Could not add to PATH: $_"
        return $false
    }
}

# Verify installation
function Test-Installation {
    param(
        [Parameter(Mandatory=$true)]
        [string]$BinaryPath
    )
    
    Write-Step "Verifying installation"
    
    if (-not (Test-Path $BinaryPath)) {
        Write-ErrorMessage "Binary not found: $BinaryPath"
        return $false
    }
    
    try {
        $output = & $BinaryPath --version 2>&1
        if ($output -match 'CTX version') {
            Write-Success "Installation verified: $output"
            return $true
        } else {
            Write-ErrorMessage "Unexpected output: $output"
            return $false
        }
    } catch {
        Write-ErrorMessage "Could not execute binary: $_"
        return $false
    }
}

# Main installation logic
function Install-Ctx {
    Write-Output ""
    Write-ColorOutput "=== CTX Installer ===" -ForegroundColor Cyan
    Write-ColorOutput "Context Generator for AI-Assisted Development" -ForegroundColor Gray
    Write-Output ""
    
    # Determine installation path
    $isCurrentDir = $false
    $isDefaultLocation = $false
    
    if ($CurrentDir) {
        $Path = Get-Location | Select-Object -ExpandProperty Path
        $isCurrentDir = $true
        Write-Info "Installing to current directory"
    } elseif (-not $Path) {
        $Path = $Script:DEFAULT_INSTALL_PATH
        $isDefaultLocation = $true
        Write-Info "Installing to default user location"
    } else {
        Write-Info "Installing to custom location"
    }
    
    Write-Info "Installation path: $Path"
    
    # Determine AddToPath behavior if not explicitly set
    $shouldAddToPath = $AddToPath
    if (-not $PSBoundParameters.ContainsKey('AddToPath')) {
        # Default: true for default/custom location, false for current directory
        $shouldAddToPath = -not $isCurrentDir
    }
    
    # Check for existing installation
    $binaryPath = Join-Path $Path $Script:BINARY_NAME
    $existingVersion = Get-InstalledVersion -BinaryPath $binaryPath
    
    if ($existingVersion) {
        Write-Info "Existing installation: v$existingVersion"
    }
    
    # Get latest release
    $release = Get-LatestRelease
    $latestVersion = $release.tag_name -replace '^v', ''
    
    # Check if upgrade needed
    if ($existingVersion -and -not $Force) {
        $comparison = Compare-Version -Version1 $latestVersion -Version2 $existingVersion
        
        if ($comparison -eq 0) {
            Write-Info "CTX v$existingVersion is already installed (latest version)"
            
            if (-not $Silent) {
                $response = Read-Host "Reinstall anyway? (y/N)"
                if ($response -notmatch '^[Yy]') {
                    Write-Info "Installation cancelled"
                    return
                }
            } else {
                Write-Info "Use -Force to reinstall"
                return
            }
        } elseif ($comparison -lt 0) {
            Write-Warning "Installed version (v$existingVersion) is newer than latest (v$latestVersion)"
            
            if (-not $Silent) {
                $response = Read-Host "Downgrade? (y/N)"
                if ($response -notmatch '^[Yy]') {
                    Write-Info "Installation cancelled"
                    return
                }
            } else {
                Write-Info "Use -Force to downgrade"
                return
            }
        } else {
            Write-Info "Upgrading from v$existingVersion to v$latestVersion"
        }
    }
    
    # Find appropriate asset
    $asset = Get-ReleaseAsset -Release $release
    
    # Create temporary directory
    $tempDir = Join-Path $env:TEMP "ctx-install-$(Get-Random)"
    New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
    
    try {
        # Download asset
        Write-Step "Downloading CTX v$latestVersion"
        $downloadPath = Join-Path $tempDir $asset.name
        Get-File -Url $asset.browser_download_url -OutFile $downloadPath
        
        # Verify checksum
        if (-not $SkipVerification) {
            $checksumVerified = Test-FileChecksum -FilePath $downloadPath -AssetName $asset.name -Release $release
            
            if (-not $checksumVerified) {
                Write-Warning "Checksum verification failed or not available"
                if (-not $Silent) {
                    $response = Read-Host "Continue anyway? (y/N)"
                    if ($response -notmatch '^[Yy]') {
                        throw "Installation cancelled by user"
                    }
                }
            }
        }
        
        # Create installation directory if needed
        if (-not (Test-Path $Path)) {
            New-Item -ItemType Directory -Path $Path -Force | Out-Null
            Write-Success "Created installation directory"
        }
        
        # Install binary
        Write-Step "Installing CTX"
        Copy-Item -Path $downloadPath -Destination $binaryPath -Force
        Write-Success "Installed: $binaryPath"
        
        # Add to PATH if requested
        if ($shouldAddToPath) {
            Add-ToPath -Directory $Path | Out-Null
        }
        
        # Verify installation
        if (-not $SkipVerification) {
            $verified = Test-Installation -BinaryPath $binaryPath
            
            if (-not $verified) {
                throw "Installation verification failed"
            }
        }
        
        # Success message
        Write-Output ""
        Write-ColorOutput "=== Installation Complete ===" -ForegroundColor Green
        Write-Success "CTX v$latestVersion installed successfully"
        Write-Info "Location: $binaryPath"
        
        if ($shouldAddToPath) {
            Write-Output ""
            Write-Info "Restart your terminal to use 'ctx' command globally"
        } else {
            Write-Output ""
            if ($isCurrentDir) {
                Write-Info "Run: .\$($Script:BINARY_NAME)"
                Write-Info "To add to PATH, use: -AddToPath parameter"
            } else {
                Write-Info "Run: $binaryPath"
            }
        }
        
        Write-Output ""
        Write-ColorOutput "Get started: ctx --help" -ForegroundColor Cyan
        Write-ColorOutput "Documentation: https://github.com/$Script:GITHUB_REPO" -ForegroundColor Gray
        Write-Output ""
        
    } catch {
        Write-Output ""
        Write-ErrorMessage "Installation failed: $_"
        throw
        
    } finally {
        # Clean up temporary directory
        if (Test-Path $tempDir) {
            Remove-Item -Path $tempDir -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
}

# Execute installation
try {
    Install-Ctx
    exit 0
} catch {
    Write-Output ""
    Write-ErrorMessage "Error: $_"
    Write-Output ""
    Write-ColorOutput "If you get an execution policy error, run:" -ForegroundColor Yellow
    Write-ColorOutput "PowerShell -ExecutionPolicy Bypass -File download-latest.ps1" -ForegroundColor White
    Write-Output ""
    exit 1
}
