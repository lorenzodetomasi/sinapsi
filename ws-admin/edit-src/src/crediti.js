// Credits: the schema.org properties a row may stand for, the roles a
// programme usually writes, and the property the words of a role suggest.
// Shared by CreditiRenderer and by the import of programmes; the server side
// is ws-admin/lib/event-credits.php.

export const PROPRIETA = [
  { v: 'performer', l: 'Interpreti (performer)' },
  { v: 'director', l: 'Regia (director)' },
  { v: 'composer', l: 'Musica (composer)' },
  { v: 'workPerformed.author', l: 'Testo, drammaturgia (autore dell’opera)' },
  { v: 'workPerformed.creator', l: 'Ideazione, coreografia, scene, luci, video (creatore dell’opera)' },
  { v: 'workPerformed.producer', l: 'Presenta, produzione (produttore dell’opera)' },
  { v: 'workPerformed.isBasedOn', l: 'Fonte, tratto da (opera di partenza)' },
  { v: 'workFeatured.creator', l: 'Foto, opere esposte (autore delle opere in mostra)' },
  { v: 'funder', l: 'Con il sostegno di (funder)' },
  { v: 'sponsor', l: 'Sponsor' },
  { v: '', l: 'Solo da mostrare (a cura di, guidata da, direzione artistica…)' },
];

export const RUOLI = [
  'in scena', 'interpreti', 'con', 'regia', 'ideazione e regia', 'drammaturgia', 'testo e regia',
  'coreografia', 'musica', 'sonorizzazione', 'scene', 'disegno luci', 'a cura di', 'guidata da',
  'direzione artistica', 'presenta', 'presentano', 'produzione', 'fonte', 'foto di',
];

/** The property the words of a role suggest; '' when they say nothing standard. */
export function proprietaDa(ruolo) {
  const r = String(ruolo || '').toLowerCase();
  if (/\b(a cura di|guidat[ao] da|direzione artistica|curatela|ospiti)\b/.test(r)) return '';
  if (/\bregia\b/.test(r)) return 'director';
  if (/\b(in scena|interpret\w*|^con$|voce|voci|musicisti)\b/.test(r) || r === 'con') return 'performer';
  if (/\b(musica|musiche|sonorizzazione|composizione)\b/.test(r)) return 'composer';
  if (/\b(drammaturgia|testo|scrittura|adattamento|^di$)\b/.test(r) || r === 'di') return 'workPerformed.author';
  if (/\b(ideazione|coreografia|scene|costumi|luci|video|progetto visivo|realizzazione)\b/.test(r)) return 'workPerformed.creator';
  if (/\b(presenta|presentano|produzione|coproduzione)\b/.test(r)) return 'workPerformed.producer';
  if (/\b(fonte|tratto da|da un'idea)\b/.test(r)) return 'workPerformed.isBasedOn';
  if (/\b(foto|fotografie|opere|illustrazioni)\b/.test(r)) return 'workFeatured.creator';
  if (/\bsostegno\b/.test(r)) return 'funder';
  return '';
}

