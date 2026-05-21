#!/bin/bash
set -e

cd /var/www/html

# Ждем пару секунд, чтобы дать app время гарантированно завершить миграции
sleep 3

# Выставляем права на всякий случай
chmod -R 777 storage bootstrap/cache

php artisan rabbitmq:setup-rabbit-queues

exec php artisan queue:work rabbitmq \
  --queue=notifications.high,notifications.normal \
  --tries=3 \
  --backoff=5 \
  --sleep=1 \
  --timeout=60