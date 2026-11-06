# pagadesarrollos.com — brief for agents

Corporate website of PAGA Desarrollos. Owner: Juan Diego Páez (Mejora Continua). Every change is reviewed by him before it merges; Dirección approves message, official figures and design at each gate.

## What the site must do

Make PAGA read as an **institutional developer**: 25 years, Mexico and the United States, every development dated and documented. Primary audience: capital partners, landowners, banks and co-developers. Buyers are served by each project's own sales site; the corporate site links out to them.

Calls to action stay institutional, not salesy (decided by Juan Diego, Oct 13, 2026): the hero offers only **"Proyectos"** and **"Contáctenos"** (it read "Contáctanos" until the usted decision of Nov 5, 2026). Do not add audience-specific CTAs such as "Invertir o asociarse" or "Tengo un terreno" to the hero or project pages; the visitor picks their profile inside the contact form.

Core line: **"25 años desarrollando valor."** (EN: "25 years developing value.") It pairs with the EOS purpose *Desarrollamos valor*.

## Sources of truth (in this order)

1. `src/data/projects.json` — every project fact (year, city, units, area, status). Pages, the timeline and the figures band all read from it. Never type a project fact into a page.
2. `src/data/company.json` — purpose, niche, values, Three Uniques, Proven Process, Guarantee, headline figures. Public parts of the EOS V/TO (May 2026) only.
3. Facts confirmed by Juan Diego in writing override the institutional deck (e.g. Torre Pietra completed 2022; Torre Acqua has 136 units; Balcones 500 and 400 were not developed by PAGA — PAGA's Balcones work starts with Balcones 300, then 600).

**Not PAGA projects — never add them:** Balcones 500, Balcones 400, and the earlier Sonoma towers Alberi, Vento and Foresta (developed by others). Fortek and Nawa were planned but never built; they do not go in the portfolio or timeline.

If two sources disagree, do not pick one silently: flag it in the PR description.

## Never publish

Portfolio value, IRR, margins, debt, financing or capital terms, investor names, the 10-Year Target figures, 3-Year Picture, 1-Year Plan, Rocks, Issues List, people evaluations. The EOS folder contains all of these; agents work from `company.json`, not from that folder.

Project partners and co-developers are NOT mentioned on the site for now (decided by Juan Diego, Oct 12, 2026) — including Grupo DAGS on Torre Acqua. Keep `partners` empty in projects.json. Dates stay as in projects.json; do not replace them with paezdevelopment.com dates.

## Conventions

- **Years** = completion year (decided Oct 12, 2026). Future years are labelled as estimated completion.
- **Area** = m² in Spanish, ft² in English. Data is stored in ft²; convert with 1 ft² = 0.092903 m², rounded to the nearest 10 m² (or 1,000 for totals).
- **Figures** always carry an as-of date.
- **Language**: Spanish first (`/`), English mirror (`/en/`). Write natively in each language; do not translate word for word.
- **Register (Spanish)**: **usted**, everywhere, the contact form included (decided by Juan Diego, Nov 5, 2026). The site speaks to banks, capital partners and landowners; tú was splitting the voice right at first contact. English has no equivalent split — keep it plain and direct.
- **Renders**: `imagesAreRenders` in projects.json says whether a development's images are renderings rather than photographs of finished work. Seven are marked today. A render is always labelled, on the card and on the project page. If you add a project, look at its images and set the flag; never leave it to guesswork.
- **No video on the server** (decided by Juan Diego, Nov 5, 2026). The two 2:11 institutional films were removed: 75 MB on a shared plan, and one desktop viewer held an Apache process for ~47 seconds. Trayectoria on the home page carries a still photograph instead. The hero background loop stays (720p, 1.8 MB, desktop only, after load). Do not put another film on this server.
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

Astro (static output), no client framework. Data in JSON, pages in `src/pages`, shared pieces in `src/components`. `npm run build` must pass with zero warnings before a PR.

Hosted on the **cPanel of Neubox**, the same server and the same hosting plan that already serve the domain and the Microsoft 365 mail (decided by Juan Diego, Nov 5, 2026 — it replaces the Cloudflare Pages decision of Oct 14: the Neubox plan cannot be cancelled because the mail depends on it, and it is not known yet through whom the domain was bought, so nothing moves out of there). What this means for the code:

- `npm run build` is run on a computer and the contents of `dist/` are uploaded to `public_html`. The server does not build.
- The contact form is **PHP**: `public/api/contact.php`, which sends each message with `mail()` to a Microsoft 365 mailbox of the domain. Since Nov 5, 2026 each profile has its own mailbox and every message is also copied to the CRM (`crm` in the config), as a two-part mail: a plain-text part with one `field: value` line per field and a tag line, which is what CRM parsers read, plus the HTML part for people. The same data travels in `X-PAGA-*` headers. Recipients are configured at the top of that file, or without recompiling in `/home/<user>/paga-contacto-config.php`; every mailbox must exist in Microsoft 365 before launch. `functions/api/contact.js` is the Cloudflare version: it is kept for the future and is not published.
- Apache configuration (security headers, cache, 404) in `public/.htaccess`. `public/_headers` is the Cloudflare equivalent and does nothing here.
- **DNS does not change at all**: `www` and the bare domain already point to this server. Never touch the Microsoft 365 mail records (MX, autodiscover, SPF, DKIM, DMARC, Lync/SIP). The SPF must keep authorizing this server (today with `+a`) or the form's mail ends up in spam.

Public address is **www.pagadesarrollos.com** (canonical); `.htaccess` redirects the bare domain to www. Launch steps, checks and rollback: `docs/LANZAMIENTO.md`.

## Pending Dirección

- **The team is not published** until Dirección confirms the eight titles (`team.draft` in company.json). The footer link to `#equipo` hides itself with the section, so nothing points at an anchor that is not there.
- **Contact publishes phone and email, no street address** (decided by Juan Diego, Nov 5, 2026). `offices` in contact.json is still empty: `address` is optional, `phone` and `email` are what goes in.

## Legal

Every form links the aviso de privacidad and asks for consent (LFPDPPP). Footer on every page: privacy notice, cookie notice, terms, official channels notice.
