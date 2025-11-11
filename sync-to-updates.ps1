# Sync Modified Files to Updates Folder
# PowerShell script to copy all platform files to updates folder

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Syncing Files to Updates Folder" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Target: C:\Users\HYLINK\Desktop\music - Copy\updates" -ForegroundColor Yellow
Write-Host ""

# Change to script directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

# Run PHP sync script
php sync-to-updates.php

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Sync Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Read-Host "Press Enter to exit"

