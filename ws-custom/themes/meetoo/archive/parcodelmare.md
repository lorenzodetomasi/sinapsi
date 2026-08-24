Nei due json: 
- differenzia i punti di interesse per posizione: lato mare (da posizionare sopra la linea delle fermate) e lato entroterra (da posizionare sotto).
- arriva fino al confine con Fiumicino: ad esempio inserisci l'Idorscalo.
- prevedi l'inserimento dei seguenti campi che verranno acquisiti da Google Maps: 
-- il chilometraggio da Fiumicino a Torvaianica.
-- le categorie dell'attività (ad es. Stabilimento balneare).
-- le info e i servizi offerti.
-- il voto su Google Maps.
-- l'indirizzo postale.
-- l'url del sito web.
-- gli orari di apertura.
-- i contatti (telefono, email, chat [whatsapp, Google ecc.]).
Crea il template di un json tipo per i pois e uno per le beaches.

Ora lavoriamo all'UI:
1. Integra i punti di interesse, mantenendo separati i file json, caricati separatamente.
2. Verifica l'allineamento delle "fermate" alla linea e correggilo.
3. Distanzia le "fermate" proporzionalmente alla distanza.
4. Sposta le card delle beaches sopra alla linea delle fermate. Nel prossimo passaggio nel json differenzieremo i punti di interesse per posizione: lato mare (da posizionare sopra) e lato entroterra (da posizionare sotto): prevedi l'inserimento dei punti di interesse con questa regola.
5. Al caricamento mostra il focus al centro sul Pontile (tra Battistini ed Elmi)
6. Mostra tre fermate alla volta con quella centrale in evidenza; effettua uno swap discreto, che salti direttamente dalla fermata centrale a quella successiva o precedente sia con lo scroll che con il click sul punto di fermata o sul nome.
7. Sposta i badges sotto alla distanza
8. Uniforma l'altezza delle card e allinea verticalmente i name e il bottone "Apri in Maps"

== Scelta della chiave JSON-LD
Le opzioni più pulite e leggibili nel contesto di Schema.org sono:
- coastalPosition (la più professionale per indicare la fascia geografico-costiera)
- seasidePosition o seaProximity (molto intuitive)

== Traduzione in inglese dei valori
coastalPosition:
1. Fronte Mare/Sulla spiaggia
Termini inglesi: Waterfront / On the Beach / Beachfront
Cosa include: Strutture sulla sabbia, stabilimenti balneari, chioschi della spiaggia, ristoranti sul lungomare senza strade in mezzo.

2. Sul lungomare
Termini inglesi: Coastal Strip / Waterfront Street /
Cosa include: Attività sul lato opposto del lungomare (es. i palazzi di Ostia affacciati su Via Giuliano da Sangallo o Piazzale Magellano), hotel e bar divisi dal mare solo dalla strada principale.

3. Primo Entroterra
Termini inglesi: Near inland / Near-coastal / Off-Beach / Landward / Short Walk to Beach / Coastal Hinterland
Cosa include: Il punto esatto a circa 200 metri dove inizia l'abitato interno di Lido di Ostia, l'inizio delle prime aree verdi (come i margini della Pineta di Castel Fusano) e i chioschi interni. Non vedi il mare, ma lo raggiungi a piedi in 2-3 minuti.

4. Entroterra Profondo / Interno
Termini inglesi: Inland / Interior / Mainland Area / Deep inland
Cosa include: La griglia urbana centrale di Ostia, le stazioni del trenino (Ostia Lido Centro, Stella Polare), e le zone profonde della pineta lontane dalla costa.

== beaches.json
"km_form_border_south": 0, = Via Litoranea, 1750

    {
      "@type": "ListItem",
      "position": 2,
      "item": {
        "@type": "Beach",
        "identifier": "stabilimento-esempio",
        "name": "Nome Stabilimento Balneare",
        "chilometraggio_da_fiumicino_km": 1.2,
        "distanza_m": 350,
        "tipo": "privato",
        "stato": "aperto",
        "note": "Gestione rinnovata",
        "categorie": ["Stabilimento balneare", "Ristorante", "Bar"],
        "servizi_offerti": [
          "Noleggio ombrelloni e lettini",
          "Cabine",
          "Piscina",
          "Ristorante di pesce",
          "Wi-Fi gratuito",
          "Accesso disabili"
        ],
        "voto_google_maps": 4.4,
        "indirizzo_postale": "Lungomare Amerigo Vespucci, 120, 00122 Roma RM",
        "sito_web": "https://www.nomestabilesito.it",
        "orari_apertura": {
          "lunedi_domenica": "08:00 - 19:30"
        },
        "contatti": {
          "telefono": "+39 065612345",
          "email": "info@nomestabilesito.it",
          "chat": "https://wa.me/393331234567"
        },
        "url": "https://goo.gl/maps/esempio"
      }
    }
  ]
}

== pois.json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Punti di Interesse Lungomare Ostia",
  "description": "Luoghi di interesse turistico, monumenti e attrazioni suddivisi per posizione rispetto alla linea di costa.",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "item": {
        "@type": "TouristAttraction",
        "identifier": "porto-turistico",
        "name": "Porto Turistico di Roma",
        "posizione": "lato_mare",
        "chilometraggio_da_fiumicino_km": 0.5,
        "categorie": ["Porto turistico", "Centro commerciale", "Passeggiata"],
        "descrizione": "Moderno porto turistico con passeggiata pedonale, ristoranti e servizi nautici.",
        "servizi_offerti": ["Ristoranti", "Negozi", "Parcheggio custodito", "Colonnine ricarica elettrica"],
        "voto_google_maps": 4.5,
        "indirizzo_postale": "Lungomare della Marina, 00122 Roma RM",
        "sito_web": "https://www.portoturistico.it",
        "orari_apertura": {
          "lunedi_domenica": "08:00 - 00:00"
        },
        "contatti": {
          "telefono": "+39 0665651",
          "email": "info@portoturistico.it",
          "chat": null
        },
        "url": "https://goo.gl/maps/porto_ostia"
      }
    },
    {
      "@type": "ListItem",
      "position": 2,
      "item": {
        "@type": "TouristAttraction",
        "identifier": "pontile-ostia",
        "name": "Il Pontile (Piazza dei Ravennati)",
        "posizione": "lato_mare",
        "chilometraggio_da_fiumicino_km": 4.8,
        "categorie": ["Monumento", "Punto panoramico", "Piazza"],
        "descrizione": "Il celebre pontile che si protende nel mare, cuore della passeggiata centrale di Ostia.",
        "servizi_offerti": ["Area pedonale", "Panorama sul mare"],
        "voto_google_maps": 4.7,
        "indirizzo_postale": "Piazza dei Ravennati, 00122 Roma RM",
        "sito_web": "https://www.comune.roma.it",
        "orari_apertura": {
          "sempre_aperto": "24/7"
        },
        "contatti": {
          "telefono": "+39 060606",
          "email": "urp@comune.roma.it",
          "chat": null
        },
        "url": "https://goo.gl/maps/pontile"
      }
    },
    {
      "@type": "ListItem",
      "position": 3,
      "item": {
        "@type": "TouristAttraction",
        "identifier": "pineta-castelfusano",
        "name": "Parco Urbano Pineta di Castel Fusano",
        "posizione": "lato_entroterra",
        "chilometraggio_da_fiumicino_km": 7.5,
        "categorie": ["Riserva naturale", "Area verde", "Parco"],
        "descrizione": "Storica pineta mediterranea situata nell'entroterra protetto a ridosso del litorale.",
        "servizi_offerti": ["Sentieri naturalistici", "Aree pic-nic"],
        "voto_google_maps": 4.2,
        "indirizzo_postale": "Viale della Pineta di Castel Fusano, 00122 Roma RM",
        "sito_web": "https://www.romaatura.it",
        "orari_apertura": {
          "lunedi_domenica": "Alba - Tramonto"
        },
        "contatti": {
          "telefono": "+39 0667101",
          "email": "ambienteparchi@comune.roma.it",
          "chat": null
        },
        "url": "https://goo.gl/maps/pineta"
      }
    }
  ]
}
