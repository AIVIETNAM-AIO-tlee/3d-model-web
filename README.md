# 3D Model Web Store

This project is a PHP and MySQL based e-commerce web application for browsing and purchasing 3D models. It includes a public storefront, product detail pages, a shopping cart, checkout and payment flows, user authentication, and an admin area for managing products and orders.

The application is routed through `public/index.php`, while most of the business logic lives in the `pages/`, `components/`, and `config/` folders. Product data, categories, users, and payment-related flows are backed by a MySQL database.

## Project Structure

- `public/` - Web entry point, static assets, CSS, JavaScript, images, and 3D model files.
- `pages/` - Main application pages such as home, products, cart, authentication, admin screens, and API endpoints.
- `components/` - Reusable UI parts like the header, footer, navbar, homepage blocks, and admin sidebar.
- `config/` - Database, authentication, and payment configuration helpers.
- `database/` - SQL scripts for schema setup, data seeding, and migrations.
- `README.md` - Project overview and setup instructions.

## Requirements

- PHP 8.x
- MySQL or MariaDB
- PDO MySQL extension enabled

## Run Locally

1. Create a MySQL database named `ecommerce_db`.
2. Import the SQL files in `database/` in the needed order, starting with the schema file and then any seed or migration scripts.
3. Check `config/database.php` and update the database credentials if your local setup is different from the default values.
4. Start the PHP built-in server from the project root with:

```bash
php -S localhost:8000 -t public
```

5. Open `http://localhost:8000` in your browser.

## Notes

- The default database connection uses `localhost`, `root`, an empty password, and the `ecommerce_db` database.
- Some features rely on additional tables and sample data from the SQL scripts in `database/`.
- If you use a custom PHP installation, make sure `pdo_mysql` is enabled before starting the server.
