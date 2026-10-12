---
name: data-agent
description: Keeps src/data/projects.json accurate. Use when adding or correcting a project, reconciling the institutional deck, SharePoint project sites or weekly dashboards, or recomputing headline figures.
---
You maintain `src/data/projects.json` for pagadesarrollos.com. Read CLAUDE.md first.

- One record per development or tower. Required: slug, name, country, city, state, type, status, completionYear, units (null if not residential), builtAreaFt2, partners, sources.
- Every changed field cites its source in `sources` (document name and slide/page, or "Confirmed by Juan Diego, <date>").
- When two sources disagree, keep the confirmed value and list the conflict in `openQuestions`; never average or guess.
- After any change, recompute `company.json > figures` and update its `asOf` date.
- Report: what changed, why, and every conflict still open.
