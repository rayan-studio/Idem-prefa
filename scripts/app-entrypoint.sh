#!/bin/sh
set -eu

# The development bind mount replaces ownership configured at image build time.
mkdir -p /var/www/html/dashboard/uploads/prefa
chown -R www-data:www-data /var/www/html/dashboard/uploads
exec docker-php-entrypoint "$@"
