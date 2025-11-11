# Copy Platform Files to Updates Folder
# Excludes: database, config, uploads, logs, temp, backups, .git, node_modules

$source = "C:\xampp\htdocs\music"
$dest = "C:\Users\HYLINK\Desktop\music - Copy\updates"

# Directories to exclude
$excludeDirs = @(
    'config',
    'database', 
    'uploads',
    'logs',
    'temp',
    'backups',
    '.git',
    'node_modules',
    'updates',
    'vendor',
    'cache'
)

# File patterns to exclude
$excludeFiles = @(
    '*.sql',
    '*.env',
    '*.log',
    '*.cache',
    'config.php',
    'database.php',
    '.htaccess',
    '.gitignore',
    '.gitattributes'
)

# Create destination if it doesn't exist
if (-not (Test-Path $dest)) {
    New-Item -ItemType Directory -Path $dest -Force | Out-Null
    Write-Host "Created destination directory: $dest"
}

$copiedCount = 0
$skippedCount = 0

# Function to check if path should be excluded
function Should-Exclude {
    param($filePath, $relativePath)
    
    # Check directory exclusions
    foreach ($exDir in $excludeDirs) {
        if ($relativePath -like "*\$exDir\*" -or $relativePath -like "$exDir\*" -or $relativePath -like "*\$exDir") {
            return $true
        }
    }
    
    # Check file pattern exclusions
    foreach ($exFile in $excludeFiles) {
        if ($filePath -like $exFile) {
            return $true
        }
    }
    
    return $false
}

# Copy files
Write-Host "Starting copy operation..."
Write-Host "Source: $source"
Write-Host "Destination: $dest"
Write-Host "Excluding: $($excludeDirs -join ', ')"
Write-Host ""

Get-ChildItem -Path $source -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Replace($source, '').TrimStart('\')
    
    if (Should-Exclude $_.FullName $relativePath) {
        $skippedCount++
        return
    }
    
    $destPath = Join-Path $dest $relativePath
    $destDir = Split-Path $destPath -Parent
    
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    
    try {
        Copy-Item -Path $_.FullName -Destination $destPath -Force
        $copiedCount++
        if ($copiedCount % 100 -eq 0) {
            Write-Host "Copied $copiedCount files..."
        }
    } catch {
        Write-Warning "Failed to copy: $relativePath - $($_.Exception.Message)"
    }
}

Write-Host ""
Write-Host "Copy operation completed!"
Write-Host "Files copied: $copiedCount"
Write-Host "Files skipped: $skippedCount"

