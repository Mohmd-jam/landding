@echo off
REM JamTools Windows build (cmd alternative) — produces dist\JamTools.exe
where py >nul 2>nul || (echo [ERROR] Python launcher 'py' not found. Install Python 3.10+. & exit /b 1)

if not exist .venv (
    echo Creating venv...
    py -3 -m venv .venv
)
call .venv\Scripts\activate.bat
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
python -m pip install pyinstaller

if exist dist rmdir /s /q dist
if exist build rmdir /s /q build

python -m PyInstaller --noconfirm --clean --name JamTools --onefile --windowed --icon assets/icon.ico --add-data "assets;assets" --collect-all PySide6 run.py

echo.
echo DONE: dist\JamTools.exe
echo NOTE: OCR needs Tesseract installed separately on the target PC.
