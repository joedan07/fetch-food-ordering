#!/bin/bash
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
cd "$(dirname "$0")"
for f in "chart-promise 960 510" "chart-error 960 490"; do
  set -- $f
  pkill -f "Google Chrome.*headless" 2>/dev/null; sleep 2
  "$CHROME" --headless --disable-gpu --hide-scrollbars --force-device-scale-factor=2 --virtual-time-budget=3000 --screenshot="fig-$1.png" --window-size=$2,$3 "file://$PWD/figures.html#$1" >/dev/null 2>&1
  echo "$1 $(stat -f%z fig-$1.png)"
done
