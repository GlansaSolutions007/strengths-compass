# PowerShell script to check Laravel logs for email-related entries
Write-Host "Checking Laravel logs for email-related entries..." -ForegroundColor Cyan
Write-Host ""

$logFile = "storage\logs\laravel.log"

if (Test-Path $logFile) {
    Write-Host "Recent email-related log entries:" -ForegroundColor Yellow
    Write-Host "-----------------------------------" -ForegroundColor Yellow
    
    Get-Content $logFile -Tail 200 | Select-String -Pattern "welcome|Welcome|email|Email|Sending|Failed to send|REGISTRATION EMAIL" -Context 1 | Select-Object -Last 20
    
    Write-Host ""
    Write-Host "To watch logs in real-time, use:" -ForegroundColor Green
    Write-Host "Get-Content storage\logs\laravel.log -Tail 50 -Wait" -ForegroundColor White
} else {
    Write-Host "Log file not found: $logFile" -ForegroundColor Red
}

