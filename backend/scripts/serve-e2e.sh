#!/bin/sh

set -eu

cd /app/public

exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
