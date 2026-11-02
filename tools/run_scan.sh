#!/bin/bash
cd /media/divarion/FILES/Programming/Vateron_media/UyPanel
python3 tools/scan_headers.py > /dev/null 2>&1
echo "SCRIPT_EXIT_CODE=$?"
ls -la tools/header_report.txt
