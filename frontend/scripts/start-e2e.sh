#!/bin/sh

set -eu

npm run build

exec npm run start -- --hostname 0.0.0.0
