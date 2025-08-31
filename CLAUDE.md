# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ABI ZAID is a comprehensive Quran memorization management system built with Laravel 11 and Filament 3. It manages students, teachers, groups, attendance, progress tracking, and guardian communication for Quran memorization schools.

## Tech Stack

- **PHP**: 8.2+
- **Laravel**: 11.x
- **Filament**: 3.2.57 (Admin panel)
- **Livewire**: 3.5
- **Database**: SQLite (default) or MySQL
- **Frontend**: Tailwind CSS 3.x, Vite
- **Testing**: Pest 2.x

## Development Commands

### Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Development
```bash
# Run development server
php artisan serve

# Build frontend assets (watch mode)
npm run dev

# Build for production
npm run build

# Run database migrations
php artisan migrate

# Refresh migrations with seeders
php artisan migrate:fresh --seed
```

### Code Quality
```bash
# Format PHP code
./vendor/bin/pint

# Run tests
php artisan test
# or
./vendor/bin/pest

# Run specific test
./vendor/bin/pest tests/Feature/ExampleTest.php
```

### Artisan Commands
```bash
# Clear all caches
php artisan optimize:clear

# Cache configuration
php artisan config:cache

# List routes
php artisan route:list

# Filament upgrade
php artisan filament:upgrade
```

## Architecture Overview

### Directory Structure
- `app/Filament/` - Filament admin panel resources, pages, and widgets
  - `Resources/` - CRUD resources for models (Student, Group, Progress, etc.)
  - `Pages/` - Custom admin pages
  - `Widgets/` - Dashboard widgets
  - `Association/` - Association-specific Filament panel

- `app/Models/` - Eloquent models
  - Core models: Student, Group, Guardian, Teacher (User)
  - Tracking: Attendance, Progress, Payment, StudentDisconnection
  - Communication: Message, ReminderLog, GroupMessageTemplate
  - Quran: Page, Ayah, MemoGroup, Round

- `app/Livewire/` - Livewire components for interactive features

- `app/Services/` - Business logic services

- `app/Traits/` - Reusable trait behaviors

- `database/migrations/` - Database schema migrations

### Key Models & Relationships
- **Student** belongs to Group, has many Progress records, Attendance records, and Payments
- **Group** has many Students, belongs to Teacher (User)
- **Progress** tracks student's Quran memorization page by page
- **Attendance** records daily check-in/check-out times
- **Guardian** has many Students (parent-child relationship)
- **Message** handles SMS/notification sending to guardians

### Filament Admin Panels
The application uses multiple Filament panels:
- Main admin panel at `/association`
- Resources follow Filament 3 conventions with:
  - Form schemas for create/edit
  - Table configurations for listing
  - Custom actions and bulk actions

### Database
- Default: SQLite (`database/database.sqlite`)
- Can be configured for MySQL via `.env`
- Uses Laravel migrations for schema management

## Laravel Boost Integration

This project includes Laravel Boost MCP server which provides specialized tools. The Boost guidelines in `.cursor/rules/laravel-boost.mdc` should be followed, emphasizing:
- Following existing code conventions
- Reusing existing components
- Writing tests over verification scripts
- Being concise in explanations

## Important Conventions

- Use descriptive variable/method names (e.g., `isRegisteredForDiscounts`)
- Follow existing directory structure - don't create new base folders
- Check for existing components before creating new ones
- Prefer editing existing files over creating new ones
- Only create documentation when explicitly requested
- Run `npm run dev` or `npm run build` if frontend changes aren't reflected

## Testing

Tests use Pest PHP framework:
- Unit tests in `tests/Unit/`
- Feature tests in `tests/Feature/`
- Run all tests: `php artisan test` or `./vendor/bin/pest`
- Tests are preferred over tinker scripts for verification