# Hacklog

Project management tool built with Laravel 12, Bootstrap 5, HTMX, and SQLite.

## Features

- **Kanban Board** — drag-and-drop task cards across customizable columns
- **Projects & Phases** — projects contain phases; tasks belong to a phase (optional) and a column
- **Task Tracking** — status (planned/active/completed), start/due dates, multi-assignee, comments (Trix rich text), position ordering
- **Activity Log** — per-task activity history and an org-wide admin activity log with date/project/user/type filters
- **Schedule View** — tasks by due date, per-project and org-wide, filterable by assignee
- **Timeline View** — Gantt-style weekly view per-project and org-wide
- **Resources** — links and notes attached to a project
- **Project Sharing** — share projects with specific users
- **User Management** — admin-only; create/edit/deactivate users, LDAP lookup.

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Blade templates, Bootstrap 5 (CSS only, no JS bundle) |
| Interactivity | HTMX for inline updates; vanilla JS for drag-and-drop |
| Database | SQLite |
| Rich Text | Trix editor |
| Auth | CAS (NetID) via `uconndxlab/laravel-cas`, with masquerade mode for local dev |
| Directory | LDAP via `directorytree/ldaprecord-laravel` for user lookups |

## Authentication

CAS-based single sign-on using CAS. No local password login (yet). Flow:

1. User visits `/login` → redirected to CAS server
2. CAS returns NetID → app checks for matching active local user
3. If authorized, Laravel session is created

Set `CAS_MASQUERADE=<netid>` in `.env` to bypass CAS during local development.

## Roles

Two roles: **admin** and **user**. Enforced via middleware. Admins get access to user management, bulk task operations, and the activity log.

## Data Model

- **Project** → has many Phases, Columns, Resources, Shares
- **Phase** → has many Tasks (optional grouping)
- **Column** → has many Tasks (board workflow)
- **Task** → belongs to Project + Column, optionally to Phase; has many Assignees (users), Comments, Activities
- **TaskActivity** / **ProjectActivity** — append-only logs of changes (status, assignee, column, phase, due date, etc.)

## Getting Started

Requires PHP 8.2+, Composer, Node.js, and SQLite.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build
```

Run the dev environment (server + queue + logs + Vite):

```bash
composer dev
```

Or just the server:

```bash
php artisan serve
```

## License

MIT
