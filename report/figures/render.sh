#!/bin/bash
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
cd "$(dirname "$0")"
render() {
  pkill -f "Google Chrome.*headless" 2>/dev/null
  sleep 2
  "$CHROME" --headless --disable-gpu --hide-scrollbars --force-device-scale-factor=2 \
    --virtual-time-budget=3000 --screenshot="fig-$1.png" --window-size=$2,$3 \
    "file://$PWD/figures.html#$1" >/dev/null 2>&1
  echo "$1 $(stat -f%z "fig-$1.png") bytes"
}
render arch 1200 600
render flow 1200 660
render usecase 1200 640
render dfd 1200 620
render er 1200 680
render eta 1200 560
render chart-promise 960 510
render chart-error 960 490
render chart-latency 960 470
echo DONE
