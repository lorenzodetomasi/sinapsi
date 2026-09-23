import { useEffect, useRef } from 'react';
import { loadGoogleMaps } from './googleMaps.js';

// Aggancia Google Places Autocomplete a un input. onPick riceve
// { placeId, name, types, address } alla selezione di un luogo.
export function usePlacesAutocomplete(inputRef, onPick) {
  const cb = useRef(onPick);
  cb.current = onPick;

  useEffect(() => {
    let ac;
    let cancelled = false;
    loadGoogleMaps()
      .then((maps) => {
        if (cancelled || !inputRef.current) return;
        ac = new maps.places.Autocomplete(inputRef.current, {
          fields: ['place_id', 'name', 'types', 'formatted_address', 'address_components'],
        });
        ac.addListener('place_changed', () => {
          const p = ac.getPlace();
          if (p && p.place_id) {
            cb.current({
              placeId: p.place_id,
              name: p.name || '',
              types: p.types || [],
              addressComponents: p.address_components || [],
              address: p.formatted_address || '',
            });
          }
        });
      })
      .catch((e) => {
        // In locale/senza chiave resta un normale input di testo. Se invece
        // siamo online ma l'aggancio fallisce, lo segnaliamo per diagnosi.
        const host = window.location.hostname;
        if (!/^(localhost|127\.0\.0\.1|\[::1\])$/.test(host)) {
          console.warn('[Places] autocomplete non attivo:', e?.message || e);
        }
      });
    return () => {
      cancelled = true;
      if (ac && window.google?.maps?.event) window.google.maps.event.clearInstanceListeners(ac);
    };
  }, [inputRef]);
}
