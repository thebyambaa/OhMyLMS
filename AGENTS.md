# WordPress development standards

Follow the official WordPress coding standards for all new and modified plugin code:
https://developer.wordpress.org/coding-standards/wordpress-coding-standards/

- PHP: use WordPress spacing, tabs, braces, snake_case for new functions/variables, Yoda comparisons, and PHPDoc. Preserve existing public names, hooks, REST fields, and PSR-4 class/file mappings unless a compatible migration is implemented.
- Validate and sanitize input, check capabilities and nonces where applicable, use prepared SQL, and escape output for its actual context. Do not automatically escape an entire HTML fragment as text or weaken security checks to satisfy a linter.
- JavaScript/JSX: use the official WordPress ESLint and Prettier configurations. Preserve custom question extensions and versioned assessment contracts.
- CSS/SCSS: follow WordPress CSS formatting and keep component styling scoped.
- Do not edit dependencies, recovered third-party runtime bundles, or generated assets directly to fix style. Change authored source and rebuild generated assets when necessary.
- Run the repository's standards checks, syntax checks, and relevant tests after changes. Report remaining violations accurately; never hide them with a blanket baseline, disabled rule, or unjustified suppression.
- Document a narrow compatibility exception when changing an established public identifier or file name would break consumers.

Keep these instructions for future development in this repository.
