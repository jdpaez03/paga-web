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

Deploy: import the repo in Vercel (framework preset: Astro). The production domain is connected only at launch; keep the existing Microsoft 365 mail DNS records untouched.
