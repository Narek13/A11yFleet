#!/bin/bash

# Automatically fixes code format after Claude edits a PHP file.
FILE_PATH="$CLAUDE_FILE_PATH"

if [ ! -f "$FILE_PATH" ]; then
  exit
fi

if [[ "$FILE_PATH" =~ \.php$ ]]; then
  RELATIVE_PATH="${FILE_PATH#$(pwd)/}"
  vendor/bin/phpcbf "$RELATIVE_PATH" 2>/dev/null
fi
