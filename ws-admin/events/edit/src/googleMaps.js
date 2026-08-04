// Caricatore (una sola volta) della Google Maps JavaScript API con libreria
// Places. La chiave (VITE_GOOGLE_MAPS_API_KEY) è ristretta ai referrer
// *.isotype.org: in locale il caricamento viene saltato, così i campi restano
// normali input di testo e non compaiono errori di referrer.
let promise;

export function loadGoogleMaps() {
  if (promise) return promise;
  const host = window.location.hostname;
  if (/^(localhost|127\.0\.0\.1|\[::1\])$/.test(host)) {
    return Promise.reject(new Error('Google Maps disabilitato in locale'));
  }
  const key = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!key) {
    return Promise.reject(new Error('VITE_GOOGLE_MAPS_API_KEY mancante'));
  }
  promise = new Promise((resolve, reject) => {
    // Caricamento CLASSICO (come place-add.php, che funziona su isotype.org):
    // NIENTE loading=async, così con libraries=places la libreria è già pronta
    // all'onload. Se per qualche motivo non lo fosse, fallback su importLibrary.
    const ready = async () => {
      try {
        const g = window.google;
        if (g?.maps?.places) return resolve(g.maps);
        if (typeof g?.maps?.importLibrary === 'function') {
          await g.maps.importLibrary('places');
          return resolve(g.maps);
        }
        reject(new Error('Places non disponibile'));
      } catch (e) {
        reject(e);
      }
    };
    if (window.google?.maps) return ready();
    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places&language=it`;
    s.async = true;
    s.onload = ready;
    s.onerror = () => reject(new Error('Caricamento Google Maps fallito'));
    document.head.appendChild(s);
  });
  // Non lasciare in cache una promise rifiutata: consente un nuovo tentativo.
  promise.catch(() => {
    promise = undefined;
  });
  return promise;
}

// Mappa i tipi Google più comuni su tipi schema.org (default: Place).
const TYPE_MAP = {
  restaurant: 'Restaurant',
  cafe: 'CafeOrCoffeeShop',
  bar: 'BarOrPub',
  night_club: 'NightClub',
  park: 'Park',
  museum: 'Museum',
  art_gallery: 'Museum',
  movie_theater: 'MovieTheater',
  stadium: 'StadiumOrArena',
  library: 'Library',
  book_store: 'BookStore',
  school: 'School',
  university: 'CollegeOrUniversity',
  church: 'Church',
  place_of_worship: 'PlaceOfWorship',
  store: 'Store',
  tourist_attraction: 'TouristAttraction',
  lodging: 'LodgingBusiness',
  city_hall: 'CityHall',
  local_government_office: 'GovernmentBuilding',
};

export function schemaTypeForPlace(types = []) {
  for (const t of types) if (TYPE_MAP[t]) return TYPE_MAP[t];
  return 'Place';
}
