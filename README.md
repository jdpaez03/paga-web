# pagadesarrollos.com

Corporate website of PAGA Desarrollos. Astro static site, Spanish (`/`) and English (`/en/`).

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # static output in dist/
```

- Project facts: `src/data/projects.json` (single source of truth)
- Company copy (purpose, values, process, guarantee, UI labels): `src/data/company.json`
- Agent brief: `CLAUDE.md`; agent team: `.claude/agents/`

Deploy: the cPanel of Neubox, the same server as the domain and the Microsoft 365 mail. `npm run build` on a computer, then the contents of `dist/` go to `public_html` — the server does not build. The contact form is `public/api/contact.php` (sends with `mail()` to a mailbox of the domain) and the Apache configuration is `public/.htaccess`. Public address www.pagadesarrollos.com. No DNS change is needed; never touch the Microsoft 365 mail records. Steps, checks and rollback: `docs/LANZAMIENTO.md`.
