# Production Deployment Checklist

## Pre-Deployment

- [ ] All tests passing (`php bin/phpunit`)
- [ ] PHPStan level 8 passing (`vendor/bin/phpstan analyse`)
- [ ] Statische Analyse bestanden (`vendor/bin/phpstan analyse`)
- [ ] `.env` properly configured (not `.env.local`)
- [ ] APP_ENV=prod in `.env`
- [ ] APP_DEBUG=0 in `.env`
- [ ] Strong APP_SECRET generated
- [ ] Database credentials secured
- [ ] Mailer configured and tested

## Database

- [ ] Backup existing database
- [ ] Run migrations: `php bin/console doctrine:migrations:migrate --no-interaction`
- [ ] Verify migrations: `php bin/console doctrine:migrations:status`
- [ ] Check database indexes: `SHOW INDEX FROM rooms;`

## Cron Jobs

- [ ] Add to crontab: `*/15 * * * * cd /var/www/pms && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1`
- [ ] Create log directory: `mkdir -p /var/log && touch /var/log/pms-sync.log`
- [ ] Set log permissions: `chmod 664 /var/log/pms-sync.log`
- [ ] Test manual sync: `php bin/console app:sync-calendars`

## Web Server

- [ ] Configure Nginx/Apache for Symfony
- [ ] Enable HTTPS (Let's Encrypt)
- [ ] Configure CORS if needed
- [ ] Set proper file permissions: `chmod -R 755 var/`
- [ ] Clear cache: `php bin/console cache:clear --env=prod`
- [ ] Warm up cache: `php bin/console cache:warmup --env=prod`

## Monitoring

- [ ] Setup error logging (Sentry/Monolog)
- [ ] Configure log rotation for `/var/log/pms-sync.log`
- [ ] Add health check endpoint monitoring
- [ ] Setup alerting for sync failures
- [ ] Monitor database query performance

## Security

- [ ] Review firewall rules
- [ ] Enable rate limiting on API endpoints
- [ ] Verify HTTPS is enforced
- [ ] Check for exposed sensitive files (.env, composer.json)
- [ ] Run security checker: `composer audit`

## Post-Deployment

- [ ] Test booking creation via API
- [ ] Verify iCal sync runs successfully
- [ ] Check email notifications
- [ ] Monitor logs for first 24h
- [ ] Test conflict detection
- [ ] Verify optimistic locking works

## Rollback Plan

If issues occur:
```bash
# 1. Restore database from backup
mysql pms_db < backup.sql

# 2. Rollback code
git checkout <previous-stable-tag>

# 3. Clear cache
php bin/console cache:clear --env=prod

# 4. Restore cron job if changed
crontab -e