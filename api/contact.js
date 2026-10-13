// Vercel Serverless Function: POST /api/contact
// Sends each form submission by email through Resend (https://resend.com).
// Configure in Vercel → Project → Settings → Environment Variables:
//   RESEND_API_KEY   API key from Resend
//   CONTACT_TO       inbox that receives messages (e.g. contacto@pagadesarrollos.com)
//   CONTACT_FROM     verified sender (e.g. "PAGA Desarrollos <web@pagadesarrollos.com>")
//   CONTACT_TO_<PROFILE> optional per-profile inbox, e.g. CONTACT_TO_TERRENO, CONTACT_TO_COMPRADOR
// Until RESEND_API_KEY and CONTACT_TO are set it answers 503 and the page shows "not connected yet".

const PROFILES = ['inversion', 'terreno', 'banco', 'comprador', 'broker', 'proveedor', 'prensa', 'empleo'];
const LABELS = { inversion: 'Inversión / socio de capital', terreno: 'Terreno', banco: 'Banco / institución financiera', comprador: 'Comprador', broker: 'Broker', proveedor: 'Proveedor', prensa: 'Prensa', empleo: 'Bolsa de trabajo' };
const clean = (v, n = 500) => String(v ?? '').replace(/[\u0000-\u001f\u007f]/g, ' ').trim().slice(0, n);
const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).json({ error: 'method_not_allowed' });
  const b = typeof req.body === 'string' ? JSON.parse(req.body || '{}') : req.body || {};
  if (b.website) return res.status(200).json({ ok: true }); // honeypot: silently drop bots

  const d = {
    profile: PROFILES.includes(b.profile) ? b.profile : '',
    name: clean(b.name, 120), email: clean(b.email, 160), phone: clean(b.phone, 40), company: clean(b.company, 120),
    message: clean(b.message, 3000), project: clean(b.project, 80), landLocation: clean(b.landLocation, 200), landArea: clean(b.landArea, 20),
    deck: b.deck ? 'Sí' : 'No', consent: !!b.consent, lang: b.lang === 'en' ? 'en' : 'es', page: clean(b.page, 200),
  };
  const errors = [];
  if (!d.profile) errors.push('profile');
  if (!d.name) errors.push('name');
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) errors.push('email');
  if (!d.message) errors.push('message');
  if (!d.consent) errors.push('consent');
  if (errors.length) return res.status(400).json({ error: 'invalid', fields: errors });

  const key = process.env.RESEND_API_KEY;
  const to = process.env[`CONTACT_TO_${d.profile.toUpperCase()}`] || process.env.CONTACT_TO;
  if (!key || !to) return res.status(503).json({ error: 'not_configured' });

  const rows = [
    ['Perfil', LABELS[d.profile]], ['Nombre', d.name], ['Correo', d.email], ['Teléfono', d.phone], ['Empresa', d.company],
    ['Proyecto', d.project], ['Ubicación del terreno', d.landLocation], ['Superficie (m²)', d.landArea],
    ['Presentación corporativa', d.deck], ['Idioma', d.lang], ['Página', d.page], ['Consentimiento aviso de privacidad', 'Sí'],
  ].filter(([, v]) => v);
  const html = `<h2>Nuevo mensaje desde pagadesarrollos.com</h2><table cellpadding="6">${rows.map(([k, v]) => `<tr><td><b>${esc(k)}</b></td><td>${esc(v)}</td></tr>`).join('')}</table><p style="white-space:pre-wrap">${esc(d.message)}</p>`;

  const r = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      from: process.env.CONTACT_FROM || 'PAGA Desarrollos <onboarding@resend.dev>',
      to: to.split(',').map((s) => s.trim()),
      reply_to: d.email,
      subject: `[Web] ${LABELS[d.profile]} · ${d.name}`,
      html,
    }),
  });
  if (!r.ok) return res.status(502).json({ error: 'send_failed' });
  return res.status(200).json({ ok: true });
}
