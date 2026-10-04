# FindIT frontend

This folder is the website root. It contains HTML pages, styles in css/, browser scripts in js/, and the PHP backend endpoints in api/.

From the repository root, configure MySQL using `.env.example` and run `start.bat`. Open http://localhost:8000 rather than opening HTML files directly. With PHP on PATH, you can also run `php scripts/setup.php` followed by `php -S localhost:8000 -t public`.

See the [project README](../README.md) for installation, accounts, architecture, database setup, and tests. See the [browser checklist](../TEST-CHECKLIST.md) for manual checks.
