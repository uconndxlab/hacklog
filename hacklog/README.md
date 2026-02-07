# Hacklog

An open-source project management platform built with Laravel 12, Bootstrap 5, and SQLite.

## Features

- **Projects & Epics**: Organize work hierarchically
- **Kanban Board**: Visual task management with customizable columns
- **Task Assignments**: Assign tasks to team members
- **Schedule View**: Timeline view of tasks by due date (project-level and organization-wide)
- **Timeline View**: Gantt-style weekly timeline
- **Resources**: Curated links and notes per project
- **Authentication**: Simple login/register system

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- SQLite

### Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Seed demo users:
   ```bash
   php artisan db:seed
   ```

### Default Users

After seeding, you can log in with these accounts (password: `password`):

- **admin@hacklog.com** (Admin - full access to user management)
- **jane@hacklog.com** (User - standard access)
- **john@hacklog.com** (User - standard access)

### Running the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` and log in with one of the test accounts.

## Stack

- **Backend**: Laravel 12
- **Frontend**: Blade templates + Bootstrap 5
- **Enhancement**: HTMX (progressive enhancement only)
- **Database**: SQLite
- **Rich Text**: Trix editor

## Project Structure

- **Projects** contain **Epics** which contain **Tasks**
- Tasks are organized in **Columns** (kanban workflow)
- Tasks have:
  - Status (planned, active, completed)
  - Dates (start_date, due_date)
  - Assignees (many-to-many with users)
  - Position (ordering within column)

## Authentication

Manual authentication using Laravel's built-in primitives:
- No third-party auth packages
- Simple login/register/logout flows
- Session-based authentication
- Password hashing with bcrypt

## User Management

- Admin-only access to user management at `/users`
- Admins can:
  - Create new users
  - Edit user details (name, email, role)
  - Activate/deactivate user accounts
- Normal users cannot access user management
- Deactivated users cannot log in
- All users can be assigned to tasks

## Roles

**Two roles supported:**
- **Admin**: Full access including user management
- **User**: Standard access to projects, tasks, and schedules

Role enforcement via simple middleware checks - no complex permission system.

## Development Philosophy

- Prefer Laravel conventions
- Keep controllers thin
- Avoid over-abstraction
- Simple role system (admin/user only)
- Calm, professional UI with high contrast

## License

Open source - MIT License

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
