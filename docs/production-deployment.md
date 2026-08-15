# Production deployment checklist

1. Copy `.env.production.example` to the deployment environment and set all
   secret values there. Never commit a production `.env` file.
2. Confirm `APP_ENV=production` and `APP_DEBUG=false` before serving traffic.
   Debug pages can expose query details, filesystem paths, and stack traces.
3. Build and cache configuration after the environment is present:

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm ci
   npm run build
   ```

4. Verify the deployed process reports a production environment with debug
   disabled:

   ```bash
   php artisan about --only=environment
   ```

5. Run migrations with the normal deployment process and a reviewed backup.
   Do not use `migrate:fresh`, `db:wipe`, seeders, or destructive commands on
   the business database.
