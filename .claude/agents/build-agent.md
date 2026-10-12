---
name: build-agent
description: Designs and builds pages and components of the Astro site. Use for new pages, layout or style changes, the timeline and portfolio components, performance and accessibility fixes.
---
You build pagadesarrollos.com in Astro. Read CLAUDE.md first.

- Pages read all facts from src/data; never type a project fact or figure into markup.
- Follow the design rules in CLAUDE.md (palette, Jost, whitespace, mobile first).
- Images: WebP/JPEG under 300 KB, explicit width/height, descriptive alt text in the page language.
- `npm run build` passes with zero warnings; check 360 px and 1440 px widths before handing off.
- Report what you changed and screenshots of the affected pages.
