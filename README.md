# TechMart Online Store

A bespoke e-commerce experience for premium consumer electronics and IT gear, built with HTML, CSS, JavaScript, PHP, and MySQL. The interface draws inspiration from OpenAI.com’s clean aesthetic while satisfying the project requirements for database-driven product browsing, checkout, and order tracking.

## Project Structure
```
├── assets/
│   ├── css/main.css         # Global styling (single external stylesheet)
│   ├── js/app.js            # Modal, validation, navigation interactions
│   └── images/              # Placeholder for locally hosted images
├── includes/
│   ├── db.php               # PDO connection helper
│   ├── functions.php        # Catalog + cart helpers
│   ├── header.php           # Shared <head> + navigation
│   ├── footer.php           # Shared footer + newsletter modal
│   └── mailer.php           # Plain-text order confirmation email helper
├── scripts/subscribe.php    # Newsletter modal server-side handler (returns JSON)
├── index.php                # Home page (hero, featured items, categories)
├── products.php             # Product listing with category filter
├── product.php              # Server-rendered product detail + specs table
├── checkout.php             # Cart overview, checkout form, order processing
├── order-status.php         # Order acknowledgement + tracking lookup
├── config.sample.php        # Sample configuration (copy to config.php)
└── database.sql             # Schema + seed data for MySQL
```

## Getting Started on XAMPP
1. **Clone or copy** the project into your XAMPP `htdocs` directory (e.g., `C:/xampp/htdocs/techmart`).
2. **Create configuration:**
   - Duplicate `config.sample.php` to `config.php`.
   - Update database credentials and outbound mail settings to match your local environment.
3. **Import the database:**
   - Launch phpMyAdmin (http://localhost/phpmyadmin).
   - Create a database named `techmart` or your preferred name (update `config.php` accordingly).
   - Import `database.sql` to create tables and seed sample products.
4. **Ensure PHP sessions are enabled** in `php.ini` (enabled by default in XAMPP).
5. **Optional email testing:** configure MercuryMail, MailHog, or another SMTP relay. The checkout flow calls `mail()` using the `MAIL_FROM_*` constants.
6. **Access the site:** visit `http://localhost/techmart/index.php`.

## Key Features
- **Dynamic catalog:** Products, categories, and featured sections read live from MySQL via PDO prepared statements.
- **Product detail page:** Server-rendered specs table, stock-aware cart form, and validation feedback.
- **Shopping cart + checkout:** Session-based cart, quantity updates, checkout form with client/server validation, and transactional order persistence (orders, order_items, stock adjustments).
- **Order acknowledgement:** Confirmation redirect to `order-status.php` plus email notification hook.
- **Order tracking:** Lookup form for order number + email, returning status and line items.
- **Newsletter modal:** Promotional modal triggered on first visit with JavaScript validation and PHP insertion into `newsletter_subscribers`.

## Validation & Requirements
- Single external stylesheet with extensive styling.
- Forms include client-side checks (HTML5 + JS) and server-side sanitisation/validation (PHP).
- Database interactions cover `SELECT`, `INSERT`, and `UPDATE` across catalog, orders, and newsletter flows.
- Server-side generated pages (`product.php`, `checkout.php`, `order-status.php`) leverage PHP to render dynamic content.

## Additional Notes
- Replace remote Unsplash image URLs with locally hosted assets in `assets/images/` if an offline deployment is required.
- `assets/js/app.js` stores a flag in `localStorage` to avoid showing the newsletter modal repeatedly.
- Extendable architecture: create new pages by setting `$pageTitle` before including `includes/header.php`, then reusing shared components.
