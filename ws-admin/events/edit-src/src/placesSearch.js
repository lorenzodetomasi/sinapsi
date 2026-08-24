import { loadGoogleMaps } from './googleMaps.js';

// Ricerca su Google Places FUORI dal widget. Il widget (places.Autocomplete)
// disegna un elenco suo, che non si può comporre con altro: qui i suggerimenti
// di Google devono stare SOTTO quelli del sito, in un elenco solo, perché la
// domanda «c'è già sul sito?» viene prima di «esiste su Google?».
//
// Il gettone di sessione tiene insieme le battute e il dettaglio finale: senza,
// Google conta ogni battuta come una ricerca a sé.

let suggerimenti = null;
let dettagli = null;
let gettone = null;

async function servizi() {
  const maps = await loadGoogleMaps();
  if (!suggerimenti) suggerimenti = new maps.places.AutocompleteService();
  if (!dettagli) dettagli = new maps.places.PlacesService(document.createElement('div'));
  if (!gettone) gettone = new maps.places.AutocompleteSessionToken();
  return maps;
}

/** Predizioni per un testo. Ritorna sempre un array: se Google non è disponibile
 *  (in locale non lo è mai — la chiave è ristretta ai referrer di isotype.org)
 *  il campo resta un normale elenco del sito, senza errori in faccia. */
export async function cercaLuoghi(testo, { paese = 'it' } = {}) {
  const q = (testo ?? '').trim();
  if (!q) return [];
  try {
    const maps = await servizi();
    return await new Promise((risolvi) => {
      suggerimenti.getPlacePredictions(
        {
          input: q,
          sessionToken: gettone,
          componentRestrictions: paese ? { country: paese } : undefined,
        },
        (p, stato) =>
          risolvi(stato === maps.places.PlacesServiceStatus.OK && Array.isArray(p) ? p : [])
      );
    });
  } catch {
    return [];
  }
}

/** Dettaglio di una predizione, nella forma che l'editor già usa altrove
 *  ({ placeId, name, types, addressComponents, address }). Chiude la sessione:
 *  il prossimo giro di ricerche ne apre una nuova. */
export async function dettaglioLuogo(placeId) {
  if (!placeId) return null;
  try {
    const maps = await servizi();
    const g = gettone;
    gettone = null;
    return await new Promise((risolvi) => {
      dettagli.getDetails(
        {
          placeId,
          sessionToken: g,
          fields: ['place_id', 'name', 'types', 'address_components', 'formatted_address'],
        },
        (p, stato) =>
          risolvi(
            stato === maps.places.PlacesServiceStatus.OK && p
              ? {
                  placeId: p.place_id,
                  name: p.name || '',
                  types: p.types || [],
                  addressComponents: p.address_components || [],
                  address: p.formatted_address || '',
                }
              : null
          )
      );
    });
  } catch {
    return null;
  }
}
