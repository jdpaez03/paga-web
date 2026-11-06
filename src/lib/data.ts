import projectsData from '../data/projects.json';
import company from '../data/company.json';
import contact from '../data/contact.json';
import site from '../data/site.json';

export type Lang = 'es' | 'en';
export type Project = (typeof projectsData.projects)[number];

export const asOf = projectsData.asOf;
// Cuando se vuelven a revisar las cifras. Un banco que mira un sitio no puede saber si la
// operacion sigue viva: decir cuando vuelve a actualizarse es la forma mas barata de decirselo.
// Vacio = no se publica la linea.
export const nextUpdate: string = (projectsData as { nextUpdate?: string }).nextUpdate ?? '';
export const projects: Project[] = projectsData.projects;
export { company };

// Lo que sigue en borrador solo se ve en la vista previa. El pie de pagina y el indice de
// Nosotros preguntan aqui, para que nunca quede una liga apuntando a una seccion que no existe.
export const showTeam = !company.team.draft || site.preview;
export const showGuarantee = !company.guarantee.draft || site.preview;

export const t = (lang: Lang) => company.ui[lang];
export const tx = (o: { es: string; en: string }, lang: Lang) => o[lang];

const FT2_TO_M2 = 0.092903;
const nf = (lang: Lang) => new Intl.NumberFormat(lang === 'es' ? 'es-MX' : 'en-US');

export function area(ft2: number, lang: Lang, round = 10) {
  if (lang === 'es') return `${nf(lang).format(Math.round((ft2 * FT2_TO_M2) / round) * round)} m²`;
  return `${nf(lang).format(Math.round(ft2 / round) * round)} ft²`;
}
export const num = (n: number, lang: Lang) => nf(lang).format(n);

export const FOUNDED = 2001;
export const years = new Date(asOf).getFullYear() - FOUNDED;

// Las cifras de la banda, todas calculadas: nunca se escribe un numero de proyecto en una pagina.
// Lo entregado y lo que esta en curso van separados a proposito. Mezclados en un solo dato, un
// banco descuenta el total entero porque no puede saber que parte ya existe.
export function figures() {
  const delivered = projects.filter((p) => p.status === 'delivered');
  const pipeline = projects.filter((p) => p.status !== 'delivered');
  const sum = (list: Project[], k: 'units' | 'builtAreaFt2') =>
    list.reduce((s, p) => s + (p[k] ?? 0), 0);
  return {
    years,
    developments: projects.length,
    delivered: delivered.length,
    pipeline: pipeline.length,
    units: sum(projects, 'units'),
    deliveredUnits: sum(delivered, 'units'),
    totalFt2: sum(projects, 'builtAreaFt2'),
    deliveredFt2: sum(delivered, 'builtAreaFt2'),
    pipelineFt2: sum(pipeline, 'builtAreaFt2'),
  };
}

export const typeLabel: Record<string, { es: string; en: string }> = {
  residencial: { es: 'Residencial', en: 'Residential' },
  comercial: { es: 'Comercial', en: 'Retail' },
  oficinas: { es: 'Oficinas', en: 'Offices' },
  'usos-mixtos': { es: 'Usos mixtos', en: 'Mixed use' },
};
export const masterPlans: Record<string, string> = {
  sonoma: 'Sonoma Club & Residences',
  balcones: 'Balcones Residencial',
  amara: 'Amāra',
  'cinnamon-shore': 'Cinnamon Shore',
};
export const countryLabel = (c: string, lang: Lang) =>
  c === 'MX' ? t(lang).countryMX : t(lang).countryUS;

// La fuente que se publica en cada ficha.
//
// `sources` en projects.json son notas internas de trabajo —«54 units confirmed by juan diego,
// 2026-10-12»— y no se imprimen tal cual: traen el nombre de quien confirmo el dato y estan a
// medias en ingles. Lo que se publica es de donde salio la cifra, que es lo que un banco quiere
// saber, y si se reviso despues. Nunca quien la confirmo.
const DECK = /presentaci[óo]n institucional/i;
export function sourceLine(p: Project, lang: Lang): string {
  const es = lang === 'es';
  const list = ((p as { sources?: string[] }).sources ?? []).filter(Boolean);
  const base = list.some((s) => DECK.test(s))
    ? es
      ? 'Presentación institucional de PAGA, junio 2026 (v6)'
      : 'PAGA institutional presentation, June 2026 (v6)'
    : '';
  // Cualquier nota que no sea la presentacion es una revision posterior de la cifra.
  const notes = list.filter((s) => !DECK.test(s));
  if (notes.length === 0) return base;
  const stamps = notes.map((s) => s.match(/\d{4}-\d{2}-\d{2}/)?.[0]).filter(Boolean) as string[];
  const last = stamps.sort()[stamps.length - 1];
  const when = last
    ? new Date(last + 'T12:00:00').toLocaleDateString(es ? 'es-MX' : 'en-US', { month: 'long', year: 'numeric' })
    : '';
  const revised = es
    ? `con cifras revisadas por Dirección${when ? ` en ${when}` : ''}`
    : `with figures reviewed by management${when ? ` in ${when}` : ''}`;
  if (!base) return revised.charAt(0).toUpperCase() + revised.slice(1);
  return `${base}, ${revised}`;
}

export type LegalDoc = 'aviso-de-privacidad' | 'aviso-de-cookies' | 'terminos' | 'canales-oficiales';
export const legalSlugs: Record<LegalDoc, { es: string; en: string }> = {
  'aviso-de-privacidad': { es: 'aviso-de-privacidad', en: 'privacy-notice' },
  'aviso-de-cookies': { es: 'aviso-de-cookies', en: 'cookie-notice' },
  terminos: { es: 'terminos', en: 'terms' },
  'canales-oficiales': { es: 'canales-oficiales', en: 'official-channels' },
};

export const paths = {
  home: (l: Lang) => (l === 'es' ? '/' : '/en/'),
  projects: (l: Lang) => (l === 'es' ? '/proyectos/' : '/en/projects/'),
  project: (l: Lang, slug: string) => (l === 'es' ? `/proyectos/${slug}/` : `/en/projects/${slug}/`),
  about: (l: Lang) => (l === 'es' ? '/nosotros/' : '/en/about/'),
  legal: (l: Lang, doc: LegalDoc) => (l === 'es' ? `/${legalSlugs[doc].es}/` : `/en/${legalSlugs[doc].en}/`),
};

export const sortedByYear = () =>
  [...projects].sort((a, b) => (a.completionYear ?? 9999) - (b.completionYear ?? 9999));

/** Responsive WebP srcset for a project or gallery image (variants live in /img/w/). */
export function srcset(src: string, naturalW: number) {
  const base = src.split('/').pop()!.replace(/\.jpg$/, '');
  const widths = [480, 800, 1200, 1600].filter((w) => w === 480 || w <= naturalW);
  return widths.map((w) => `/img/w/${base}-${w}.webp ${Math.min(w, naturalW)}w`).join(', ');
}

/** Projects people can still ask about (not delivered). */
export const liveProjects = () =>
  [...projects].filter((p) => p.status !== 'delivered').sort((a, b) => (a.completionYear ?? 9999) - (b.completionYear ?? 9999));

/** Oficinas que salen en Contacto y en el pie. El domicilio es opcional: por decisión de Juan
 *  Diego (5 nov 2026) se publican teléfono y correo, y el domicilio solo si algún día se decide. */
export type Office = { city: { es: string; en: string }; address?: string; phone?: string; email?: string };
export const offices: Office[] = (contact as { offices?: Office[] }).offices ?? [];

export const contactPath = (l: Lang, q: Record<string, string> = {}) => {
  const base = l === 'es' ? '/contacto/' : '/en/contact/';
  const qs = new URLSearchParams(q).toString();
  return qs ? `${base}?${qs}` : base;
};
