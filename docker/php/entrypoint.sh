#!/bin/bash
set -e

# Ensure permissions on volume-mounted directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database || true
chmod -R 777 /var/www/storage /var/www/bootstrap/cache /var/www/database || true

# Pass control back to the original command
exec "$@"
