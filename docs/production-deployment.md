# Production deployment checklist

1. Point the deployment `.env` to the intended production database. Use a
   distinct `CACHE_PREFIX` and `SESSION_COOKIE` (the template uses
   `profitpoint` values) so no cache or session entry from the former TradeFlow
   deployment can be reused. Never commit a production `.env` file.
2. Set a unique `APP_KEY`. For a first deployment with a blank key, run:

   ```bash
   php artisan key:generate --force
   ```

   Do not regenerate an existing production key: it invalidates encrypted
   cookies and stored encrypted values.
3. Confirm `APP_ENV=production` and `APP_DEBUG=false` before serving traffic.
   Debug pages can expose query details, filesystem paths, and stack traces.
4. Configure the domain's document root as the project's `public` directory.
   If the host must serve the project root instead, deploy the root
   `index.php` and `.htaccess` together; they forward requests safely without
   exposing `/public` in URLs.
5. For a new, empty Profit Point database, clear old cached configuration,
   run schema migrations, then initialize only the required platform records:

   ```bash
   php artisan optimize:clear
   php artisan migrate --force
   php artisan tradeflow:initialize-production --admin-name="Your Name" --admin-email="you@example.com" --platform-name="Profit Point" --trial-days=14
   ```

   The default seeder now contains only a private internal Custom Access
   record and clean Profit Point settings; it does not create a Super Admin.
   Use the initializer
   command above for a new production database; it creates the initial admin
   without any demo business data.
6. Build and cache configuration after the environment is present:

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm ci
   npm run build
   ```

7. Verify the deployed process reports a production environment with debug
   disabled:

   ```bash
   php artisan about --only=environment
   ```

8. Ensure `storage` and `bootstrap/cache` are writable. Run
   `php artisan storage:link` once when public uploads are enabled.
