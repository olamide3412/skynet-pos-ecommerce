---
description: How to start a new project from the skynet-digital skeleton
---

1.  **Clone the Skeleton Repository**:
    ```bash
    git clone https://github.com/olamide3412/skynet-ecommerce.git .
    ```
    (Note: Replace with the actual skeleton URL if different.)

2.  **Environment Setup**:
    -   Copy `.env.example` to `.env`.
    -   Generate application key: `php artisan key:generate`.
    -   Configure database and other environment variables.

3.  **Dependency Installation**:
    -   Install PHP packages: `composer install`.
    -   Install Node packages: `npm install`.

4.  **Database Migration & Seeding**:
    -   Run migrations: `php artisan migrate`.
    -   Run the seeder to populate default settings: `php artisan db:seed`.
    -   This initializes the `store_settings` table which the frontend depends on.

5.  **Branding and Configuration**:
    -   Login to Admin: `admin@example.com` / `password`.
    -   Go to **Settings** and update:
        -   Company Name and Logo.
        -   Hero Section text and image.
        -   Feature blocks.
    -   Update `APP_URL` and `ZIGGY_URL` in `.env`.

6.  **Development and Build**:
    -   Start dev server: `npm run dev`.
    -   Build for production: `npm run build`.

7.  **Dynamic Page Resolution**:
    -   Verify that `resources/js/app.js` is using dynamic imports (`import.meta.glob`) for page splitting.
    -   Ensure `app.mount(el)` is at the top level of the `setup` function to prevent blank screens.
