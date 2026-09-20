# JamTools Windows build — produces dist\JamTools.exe (one file, windowed)
# Usage:  powershell -ExecutionPolicy Bypass -File .\Build-Windows.ps1
$ErrorActionPreference = "Stop"

Write-Host "=== JamTools build ===" -ForegroundColor Cyan

if (-not (Get-Command py -ErrorAction SilentlyContinue)) {
    throw "Python launcher 'py' not found. Install Python 3.10+ from python.org (tick 'Add to PATH')."
}

if (-not (Test-Path .venv)) {
    Write-Host "Creating venv..." -ForegroundColor Yellow
    py -3 -m venv .venv
}
& .\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
python -m pip install pyinstaller

if (Test-Path dist) { Remove-Item dist -Recurse -Force }
if (Test-Path build) { Remove-Item build -Recurse -Force }

Write-Host "Running PyInstaller..." -ForegroundColor Yellow
python -m PyInstaller --noconfirm --clean `
    --name JamTools `
    --onefile --windowed `
    --icon assets/icon.ico `
    --add-data "assets;assets" `
    --collect-all PySide6 `
    run.py

Write-Host ""
Write-Host "DONE: dist\JamTools.exe" -ForegroundColor Green
Write-Host "Note: OCR needs Tesseract installed separately on the target PC." -ForegroundColor DarkGray
