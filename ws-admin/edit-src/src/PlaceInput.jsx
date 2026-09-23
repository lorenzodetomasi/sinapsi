import { useRef } from 'react';
import { usePlacesAutocomplete } from './usePlacesAutocomplete.js';

// Input di testo con Google Places Autocomplete. onChange aggiorna il testo
// (digitazione libera); onPick scatta quando si sceglie un luogo dall'elenco.
export default function PlaceInput({ value, onChange, onPick, placeholder, id, disabled }) {
  const ref = useRef(null);
  usePlacesAutocomplete(ref, (place) => onPick?.(place));
  return (
    <input
      ref={ref}
      id={id}
      type="text"
      value={value ?? ''}
      disabled={disabled}
      placeholder={placeholder || 'Cerca…'}
      autoComplete="off"
      onChange={(e) => onChange(e.target.value)}
    />
  );
}
