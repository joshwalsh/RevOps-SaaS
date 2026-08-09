# RevOps-SaaS

Damron Media's RevOps SaaS application, built on Laravel with the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire) and Laravel Breeze for authentication.

## Requirements

- [DDEV](https://ddev.com) and Docker

## Getting Started

```bash
ddev start
ddev composer install
ddev npm install
ddev npm run build
```

The app will be available at https://revops.ddev.site

## Useful commands

```bash
ddev artisan migrate     # run migrations
ddev artisan tinker      # REPL
ddev npm run dev         # Vite dev server with hot reload
ddev launch              # open the site in your browser
```

## Stack

- **Laravel** 13
- **Livewire** 3 + **Volt** (via Breeze's Livewire stack)
- **Alpine.js**
- **Tailwind CSS**
- **MariaDB** (via DDEV)
