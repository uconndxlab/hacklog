You are writing code for Hacklog, an open-source project management platform.

Canonical stack (do not deviate without explicit permission):
- Laravel 12
- PHP 8.3+
- SQLite (single database file)
- HTMX loaded via CDN
- Bootstrap 5 loaded via CDN
- Trix editor for rich text fields
- Blade templates (no frontend frameworks)
- No Livewire, no Vue, no React, no Alpine

Hard constraints:
- Do NOT add any dependencies without asking first
- Do NOT write custom CSS; use Bootstrap utility and component classes only
- Do NOT use icon libraries or iconography of any kind
- Favor simple, explicit, readable code over clever abstractions
- Prefer server-rendered HTML with HTMX for interactivity
- Assume this is an early foundation and optimize for clarity and extensibility

Design philosophy:
- This is a developer-first tool
- Avoid enterprise complexity
- Avoid premature abstraction
- Code should be legible to a mid-level Laravel developer reading it for the first time
