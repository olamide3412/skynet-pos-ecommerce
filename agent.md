# Skynet Digital AI Agent Instructions

These instructions outline the core technologies, coding style, and architectural patterns used in this project. Whenever modifying or creating new code, strictly adhere to these guidelines.

## Tech Stack Overview
- **Backend framework:** Laravel 11.x
- **Frontend framework:** Vue 3 (Composition API with `<script setup>`)
- **Routing & Data Bridge:** Inertia.js (SDR)
- **Styling:** Vanilla Tailwind CSS (v4+ via `@tailwindcss/vite`)
- **State Management:** Pinia (using stores in `resources/js/Stores`)
- **Icons & Animation:** FontAwesome (Vue component), AOS (Animate On Scroll)
- **Notifications:** `vue-toastification`

---

## 🚀 Starting a New Project (Skeleton: Skynet Digital)
When starting a new project using the **skynet-digital** (or `skynet-ecommerce`) skeleton:
1. **Clone & Setup:** Use the existing project structure as a template.
2. **Database:** Initialize the `store_settings` table. Use the `DatabaseSeeder` to populate default company branding, hero sections, and feature blocks.
3. **Branding:** Immediately update `Admin/Settings/Index.vue` with the new project's name, logo, and colors.
4. **Environment:** Ensure `APP_URL` is set correctly for Ziggy and social login callbacks.

---

## 🎨 Design System & Aesthetics
- **Premium Look:** Favor vibrant gradients, subtle glassmorphism (`backdrop-blur`), and modern typography (Outfit/Inter).
- **Dark Mode:** Support `dark:` variants for every UI element. Standard background is `bg-white dark:bg-gray-900`.
- **Micro-animations:** Use `AOS` for entry animations. Initialize it lazily in `app.js` to keep the bundle small.
- **No Placeholders:** Use `generate_image` or high-quality Unsplash URLs for demo content.

---

## 🛠 Frontend Development Standards
1. **Dynamic Imports:** Always use dynamic `import()` for Inertia pages in `app.js` (`import.meta.glob('./Pages/**/*.vue')`) to enable code-splitting.
2. **Synchronous Mount:** Ensure `app.mount(el)` is called immediately in `setup` to avoid blank page flashes during dynamic library loads.
3. **Form Handling:** Use `useForm` for all data mutations. Ensure `preserveScroll: true` is used for non-navigation updates.
4. **Global Components:** Generic elements like `Link`, `Head`, `font-awesome-icon`, and `FlashMessages` are registered globally in `app.js`.

---

## 📂 Store Settings Architecture
- **Centralized Config:** All site-wide features (Shop toggle, Order tracking, Hero content) must be stored in the `store_settings` table.
- **Model Usage:** Use `StoreSetting::allAsArray()` to fetch all settings for the frontend and `StoreSetting::set($key, $value)` for updates.
- **Frontend Access:** Access settings via `$page.props.store_settings`. Use fallbacks in Vue templates to handle missing keys gracefully.

---

## 📦 Build & Deployment
- **Vite Optimization:** Use `build.rollupOptions.output.manualChunks` to split large vendor libraries (`vue`, `inertia`, `fontawesome`) into separate chunks.
- **CommonJS Gotchas:** Avoid putting UMD/CommonJS libraries (like `html2pdf.js`, `Quill`) into separate manual chunks if they break with `exports is undefined`. Let Vite handle them or import them dynamically within the component only.
- **Bundle Level:** Keep the main bundle size warning threshold around `1000kB`.

---

## 🔐 Security & Backend
- **Controller Logic:** Keep controllers lean. Use `StoreSetting` for configuration rather than hardcoding.
- **Validation:** Strict validation for all admin inputs. File uploads (logos, images) should specify max sizes and allowed mime types.
- **Auth:** Support Socialite (Google/Facebook) but ensure the fallback redirect logic is robust.

