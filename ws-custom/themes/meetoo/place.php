<?php
/**
 * La pagina di un luogo: che cos'è, e che cosa ci succede.
 *
 * Un luogo non è solo un indirizzo su una mappa: è il posto dove le cose capitano.
 * Quindi porta le stesse tre sezioni del gruppo — gli appuntamenti che si ripetono
 * qui, i prossimi, e l'archivio su richiesta — con l'AMBITO puntato su di sé.
 *
 * Vale per i luoghi e per le attività (LocalBusiness): per Meetoo sono la stessa
 * cosa vista da due lati, e infatti il CMS le manda tutte e due qui.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');

/* L'ambito è l'@id del luogo, che è come lo scrivono gli eventi quando dicono dove
 * succedono. Va dichiarato prima del frammento, o il caricamento pigro
 * continuerebbe con gli eventi di tutti. */
$pezzi = explode('/', trim((string)($ws_query['content'] ?? ''), '/'));
array_shift($pezzi);   // sito
array_shift($pezzi);   // locale
meetoo_ambito('place', implode('/', $pezzi));
meetoo_frammento();

$tutto = (string)($_GET['tutti'] ?? '');
$SEZIONI = meetoo_sezioni(true);

// L'indirizzo, come lo direbbe una persona.
$indirizzo = array();
if(!empty($e->address)){
	foreach(array('streetAddress', 'postalCode', 'addressLocality') as $campo){
		$v = trim((string)($e->address->$campo ?? ''));
		if($v !== ''){
			$indirizzo[] = $v;
		}
	}
}
$mappa = '';
if(!empty($e->hasMap)){
	$mappa = (string)$e->hasMap;
} else if(!empty($e->geo->latitude)){
	$mappa = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string)$e->geo->latitude.','.(string)$e->geo->longitude);
}

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-entita-pagina'))); ?>>
<?php
/* La copertina: il percorso nel documento è relativo alla SUA cartella
 * (`media-sources/cover.jpg`), e questa pagina la serve da un altro indirizzo —
 * `meetoo_media()` fa i conti. Senza, era un 404 su ogni scheda. */
$cover = meetoo_media(meetoo_rel_corrente(), (string)($e->image ?? ''));
if($cover !== ''){ ?>
				<figure class="mt-copertina">
					<img src="<?php echo mt_esc($cover); ?>" alt="" loading="lazy" decoding="async" />
				</figure>
<?php } ?>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?></h1>
<?php if(count($indirizzo)){ ?>
				<p class="mt-sommario"><?php echo mt_icona('location_on'); ?> <?php echo mt_esc(implode(', ', $indirizzo)); ?></p>
<?php } ?>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
				<p class="mt-link">
<?php if(!empty($e->url)){ ?>
					<a href="<?php echo mt_esc((string)$e->url); ?>" rel="noopener"><?php _e('Sito web'); ?></a>
<?php } ?>
<?php if($mappa !== ''){ ?>
					<a href="<?php echo mt_esc($mappa); ?>" rel="noopener" target="_blank"><?php _e('Apri in mappa'); ?></a>
<?php } ?>
				</p>

<?php
meetoo_sezione('collezioni', $SEZIONI['collezioni'] ?? null, $tutto);
meetoo_sezione('eventi', $SEZIONI['eventi'] ?? null, $tutto);
meetoo_sezione('archivio', $SEZIONI['archivio'] ?? null, $tutto);
?>
			</article>
<?php
include_template('template-parts/footer');
