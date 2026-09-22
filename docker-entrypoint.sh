#!/bin/sh
set -e

# Default PORT to 80 if not set by Railway
PORT="${PORT:-80}"

# Ensure strictly one MPM (mpm_prefork) is loaded to prevent AH00534
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

# Dynamically bind Apache to the assigned Railway PORT
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Execute main process (apache2-foreground) as PID 1
exec "$@"
