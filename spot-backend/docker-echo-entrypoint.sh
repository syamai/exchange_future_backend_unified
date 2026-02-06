#!/bin/sh
set -e

# Generate laravel-echo-server.json from template with environment variables
cat > /app/laravel-echo-server.json << EOF
{
    "authHost": "${LARAVEL_ECHO_SERVER_AUTH_HOST:-http://spot-backend-api:9000}",
    "authEndpoint": "/broadcasting/auth",
    "clients": [
        {
            "appId": "9cc499882e695daa",
            "key": "ea6fe875a7dbf5931e203dfdea97a5a6"
        }
    ],
    "database": "redis",
    "databaseConfig": {
        "redis": {
            "host": "${LARAVEL_ECHO_SERVER_REDIS_HOST:-redis}",
            "port": "${LARAVEL_ECHO_SERVER_REDIS_PORT:-6379}"
        }
    },
    "devMode": ${LARAVEL_ECHO_SERVER_DEV_MODE:-false},
    "host": null,
    "port": "6001",
    "protocol": "http",
    "socketio": {}
}
EOF

echo "Laravel Echo Server configuration:"
cat /app/laravel-echo-server.json

echo "Starting Laravel Echo Server..."
exec laravel-echo-server start
