import projectsData from '../data/projects.json';
import company from '../data/company.json';

export type Lang = 'es' | 'en';
export type Project = (typeof projectsData.projects)[number];

export const asOf = projectsData.asOf;
export const projects: Project[] = projectsData.projects;
export { company };

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

export function figures() {
  const delivered = projects.filter((p) => p.status === 'delivered');
  const totalFt2 = projects.reduce((s, p) => s + p.builtAreaFt2, 0);
  return { years, developments: projects.length, delivered: delivered.length, totalFt2 };
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

export const contactPath = (l: Lang, q: Record<string, string> = {}) => {
  const base = l === 'es' ? '/contacto/' : '/en/contact/';
  const qs = new URLSearchParams(q).toString();
  return qs ? `${base}?${qs}` : base;
};
