#!/bin/bash
cd "$(dirname "$0")/.."
python3 tools/scan_headers.py > /dev/null 2>&1
echo "SCRIPT_EXIT_CODE=$?"
ls -la tools/header_report.txt
