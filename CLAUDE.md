# pagadesarrollos.com — brief for agents

Corporate website of PAGA Desarrollos. Owner: Juan Diego Páez (Mejora Continua). Every change is reviewed by him before it merges; Dirección approves message, official figures and design at each gate.

## What the site must do

Make PAGA read as an **institutional developer**: 25 years, Mexico and the United States, every development dated and documented. Primary audience: capital partners, landowners, banks and co-developers. Buyers are served by each project's own sales site; the corporate site links out to them.

Core line: **"25 años desarrollando valor."** (EN: "25 years developing value.") It pairs with the EOS purpose *Desarrollamos valor*.

## Sources of truth (in this order)

1. `src/data/projects.json` — every project fact (year, city, units, area, status). Pages, the timeline and the figures band all read from it. Never type a project fact into a page.
2. `src/data/company.json` — purpose, niche, values, Three Uniques, Proven Process, Guarantee, headline figures. Public parts of the EOS V/TO (May 2026) only.
3. Facts confirmed by Juan Diego in writing override the institutional deck (e.g. Torre Pietra completed 2022; Torre Acqua has 136 units).

If two sources disagree, do not pick one silently: flag it in the PR description.

## Never publish

Portfolio value, IRR, margins, debt, financing or capital terms, investor names, the 10-Year Target figures, 3-Year Picture, 1-Year Plan, Rocks, Issues List, people evaluations. The EOS folder contains all of these; agents work from `company.json`, not from that folder.

Torre Acqua is co-developed with Grupo DAGS (independent companies; neither owns the other). Any co-developed project must name its partner.

## Conventions

- **Years** = completion year (decided Oct 12, 2026). Future years are labelled as estimated completion.
- **Area** = m² in Spanish, ft² in English. Data is stored in ft²; convert with 1 ft² = 0.092903 m², rounded to the nearest 10 m² (or 1,000 for totals).
- **Figures** always carry an as-of date.
- **Language**: Spanish first (`/`), English mirror (`/en/`). Write natively in each language; do not translate word for word.
- **Tone**: calm and specific, like an annual report. Numbers, places, names. No superlatives ("el mejor", "líder", "exclusivo", "de clase mundial"), no exclamation marks, no emoji.
- **Values** (V/TO May 2026, five, in this order): Generación de valor · Eficiencia · Comunicación · Proactividad · Entrega / Compromiso.
- **Proven Process** (adopted Oct 12, 2026): Identificamos · Estructuramos · Desarrollamos · Ejecutamos · Comercializamos · Entregamos.
- **Guarantee**: "Si lo estructuramos, lo entregamos." Draft pending Dirección and Legal approval — keep the `draft` flag in data until approved.

## Design

- Palette: PAGA navy `#13294B` (deck), deep navy `#0B1B33`, corporate blue `#1F4E78`, paper `#F5F3EF`, white, warm grey text `#5B6573`. One accent only; no gradients.
- Type: Jost (300/400/500) for everything; Cormorant Garamond italic only for the phrase "desarrollando valor" and similar single accents.
- Wide-tracked uppercase eyebrows (letter-spacing .2em), generous whitespace, photography full-bleed.
- Mobile first; no horizontal scroll at 360 px.

## Stack

Astro (static output), no client framework. Data in JSON, pages in `src/pages`, shared pieces in `src/components`. `npm run build` must pass with zero warnings before a PR. Deployed on Vercel; previews per branch. The production domain is connected only at launch.

## Legal

Every form links the aviso de privacidad and asks for consent (LFPDPPP). Footer on every page: privacy notice, cookie notice, terms, official channels notice.
