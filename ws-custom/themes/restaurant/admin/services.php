<?php
// The Edit Daily Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_content, $longDate;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if($_POST){
	//print_r($_POST);
	// Save Json file
	$file_abspath = ws_contents_abspath().$ws_query['content'];
	$json_abspath = $file_abspath.'.json';
	echo $ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		abspath2url($json_abspath),
		$json_abspath,
//		basename($ws_sitemap_pathinfo['dirname']).'/'.$ws_sitemap_pathinfo['basename'],
		ws_save_file( $_POST['data'], $json_abspath, $args = array('input_type' => 'object', 'output_type' => 'json', 'file_timestamp' => true))
	); echo '<br />';
	// Save Xml file
	$SimpleXMLElement = new SimpleXMLElement('<?xml version="1.0"?><menu></menu>');
	array2simplexml($_POST['data']['menu'], $SimpleXMLElement);
	$DOMDocument = simplexml2dom($SimpleXMLElement);
	$xml_abspath = $file_abspath.'.xml';
	echo $ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		abspath2url($xml_abspath),
		$ws_query['content'].'.xml',
//		basename($ws_sitemap_pathinfo['dirname']).'/'.$ws_sitemap_pathinfo['basename'],
		ws_save_file( $DOMDocument, $xml_abspath)
	); echo ' ';
	echo $ws_log = sprintf(__('An archive copy with file_timestamp has been saved.'),
		ws_save_file( $DOMDocument, $xml_abspath, $args = array('file_timestamp' => true))
	); echo '<br />';
}
?>
	<form action="<?php echo ws_href('/fraschetta-la-romanella/admin/menu/del-giorno'); ?>" method="POST" name="menu-daily">
		<fieldset class="field">
			<legend class="label">
				<?php _e('Edit your <strong>daily offer</strong>'); ?><br />
			</legend>
			<fieldset class="fieldset">
				<legend><?php _e('Menu'); ?></legend>
				<p class="question">
					<label class="field">
						<strong class="label"><?php _e('Name'); ?></strong>
						<input type="text" name="data[menu][name]" class="flex1" placeholder="<?php _e('i.e. Our Daily Menu', 'localbiz'); ?>" value="<?php if($ws_content->name){ echo $ws_content->name->innerHTML(); } ?>" />
					</label>
				</p>
				<p class="flex question">
					<input type="hidden" name="data[menu][offers][type]" value="Offer" />
					<label class="field">
						<strong class="label"><?php _e('Offer valid from'); ?></strong>
						<input type="datetime-local" name="data[menu][offers][availabilityStarts]" value="<?php if($ws_content->name){ echo $ws_content->offers->availabilityStarts; } ?>" />
					</label>
					<label class="field">
						<strong class="label"><?php _e('to'); ?></strong>
						<input type="datetime-local" name="data[menu][offers][availabilityEnds]" value="<?php if($ws_content->name){ echo $ws_content->offers->availabilityEnds; } ?>" />
					</label>
				</p>
				<p class="question">
					<label class="field">
						<strong class="label"><?php _e('Language'); ?></strong>
						<input type="text" name="data[menu][inLanguage]" class="flex1" placeholder="<?php _e('i.e. en-US'); ?>" value="<?php if($ws_content->name){ echo $ws_content->inLanguage; } ?>" />
					</label>
				</p>
				<section>
					<h2>Parcheggio</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio disponibile</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Prenotazione parcheggio</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Area parcheggio gratuita</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio in strada</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio convenzionato</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio validato</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Servizio parcheggio</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Drive-thru</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio all'aperto</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio coperto</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio automatizzato</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio riservato ai clienti</label></li>
					</ul>
				</section>
				<section>
					<h2>Ambiente</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Casual</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Tradizionale</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Romantico</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Trendy</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Esclusivo</label></li>
					</ul>
				</section>
				<section>
					<h2>Codice di abbigliamento</h2>
					<ul>
						<li>Casual</li>
						<li>Casual da ufficio</li>
						<li>Formale</li>
						<li>Elegante</li>
					</ul>
				</section>
				<section>
					<h2>Accessibilità per disabili</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Parcheggio riservato ai disabili</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Accessibile in sedia a rotelle</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Accessibile ai non vedenti</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Accessibile digitalmente</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Noleggio scooter per disabili</label></li>
					</ul>
				</section>
				<section>
					<h2>Internet e Wi-Fi</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Connessione Wi-Fi gratuita</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Connessione Wi-Fi a pagamento</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Connessione Internet veloce</label></li>
					</ul>
				<section>
					<h2>Animali domestici</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Ammette gli animali domestici</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Accoglienza con animali di servizio</label></li>
					</ul>
				</section>
				<section>
					<h2>Adatto a</h2>
					<ul>
						<li><label><input type="checkbox" name="" value="" checked="" />Sala non fumatori</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Sala fumatori</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Solo non fumatori</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Solo fumatori</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per vegetariani</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per vegani</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per crudisti</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per intolleranti al glutine</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per famiglie con bambini</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per gruppi numerosi</label></li>
						<li><label><input type="checkbox" name="" value="" checked="" />Per incontri d’affari</label></li>
					</ul>
				</section>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Colazione</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Brunch</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Pranzo</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Aperitivo</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Cena</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Dopo mezzanotte</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Bar caffetteria</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Bevande</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Degustazione vini</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Serve alcolici</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Vino</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Birra</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Superalcolici</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Menù bambini</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Aree gioco</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Seggioloni disponibili</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Fasciatoio disponibile</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Contanti</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta carte di credito</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta American Express</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Discover</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Mastercard</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Visa</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta pagamenti digitali</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Satispay</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Paypal</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta Apple Pay</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Bar completo</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Bar con musica jazz</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Sport bar</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Buffet</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Buoni regalo disponibili</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Sala privata</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Tavoli all'esterno</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Musica dal vivo</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Posti a sedere</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Servizio al tavolo</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Consegna a domicilio</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Da asporto</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Accetta prenotazioni</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Prenotazioni obbligatorie</label></li>
				</ul>
				<ul>
					<li><label><input type="checkbox" name="" value="" checked="" />Diritto di tappo <small>(la pratica di portare al ristorante una bottiglia di vino acquistata altrove, pagando al ristoratore una somma corrispondente al servizio, alla stappatura, al lavaggio di bicchieri e decanter)</small></label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Fronte acqua</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Spiaggia</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Stile familiare</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Stile elegante/raffinato</label></li>
					<li><label><input type="checkbox" name="" value="" checked="" />Televisore</label></li>
				</ul>
				<p class="flex question">
					<label class="field">
						<strong class="label"><?php _e('Created'); ?></strong>
						<input type="datetime-local" name="data[menu][dateCreated]" class="flex1" value="<?php if($ws_content->name){ echo $ws_content->dateCreated; } ?>" />
					</label>
					<label class="field">
						<strong class="label"><?php _e('Published'); ?></strong>
						<input type="datetime-local" name="data[menu][datePublished]" class="flex1" value="<?php if($ws_content->name){ echo $ws_content->datePublished; } ?>" />
					</label>
					<label class="field">
						<strong class="label"><?php _e('Modified'); ?></strong>
						<input type="datetime-local" name="data[menu][dateModified]" class="flex1" value="<?php if($ws_content->name){ echo $ws_content->dateModified; } ?>" />
					</label>
				</p>
			</fieldset>
			<fieldset>
				<legend><?php _e('Daily items'); ?></legend>
				<p><small><?php _e('Drag and drop to organize the order of the items.'); ?></small></p>
				<div class="repeat sortable" id="menu-daily">
<?php
$menuItemIndex = 0;
foreach ($ws_content->hasMenuItem as $key => $menuItem) {
?>
					<fieldset class="fieldset repeat-item" id="menu_item__<?php echo $menuItemIndex; ?>" draggable="true">
						<div class="flex input-group background-color3-lighter no-margin">
							<button class="link" style="align-self: center;"><i class="material-icons">drag_handle</i></button>
							<div class="flex1">
								<p class="question">
									<label class="field">
										<strong class="label"><?php _e('Name'); ?></strong>
										<input type="text" name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][name]" data-copy-index class="flex1" placeholder="<?php _e('ad es. Spaghetti alla carbonara', 'localbiz'); ?>" value="<?php echo $menuItem->name; ?>" />
									</label>
								</p>
								<p class="question">
									<label class="field textarea">
										<strong class="label"><?php _e('Description'); ?></strong>
										<textarea name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][description]" class="flex1" placeholder="<?php _e('ad es. Spaghetti, uova fresche a km0, guanciale di Amatrice DOP, pecorino romano DOP, sale, pepe nero, olio extravergine di oliva della Sabina DOP', 'localbiz'); ?>"><?php echo $menuItem->description; ?></textarea>
									</label>
								</p>
								<fieldset class="question">
									<legend><?php _e('Offer'); ?></legend>
									<div class="flex">
										<label class="field price-currency">
											<strong class="label"><?php _e('Currency'); ?></strong>
											<input type="text" name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][offers][priceCurrency]" class="flex1" placeholder="<?php _e('ad es. EUR', 'isotype'); ?>" value="<?php echo $menuItem->offers->priceCurrency; ?>" />
										</label>
										<label class="field price-value flex1 margin-left-d4">
											<strong class="label"><?php _e('Price'); ?></strong>
											<input type="text" name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][offers][price]" class="flex1" placeholder="<?php _e('ad es. 10,00', 'isotype'); ?>" value="<?php echo $menuItem->offers->price; ?>" />
										</label>
									</div>
								</fieldset>
							</div>
							<button class="link repeat-delete margin-left-d4" type="button" data-at="#menu_item__0" title="Elimina" disabled="disabled"><i class="material-icons">remove</i></button>
						</div>
					</fieldset>
<?php
	$menuItemIndex++;
}
?>
				</div>
				<p><button class="button repeat-insert" type="button" data-at="#menu-daily" data-position="after" title="Inserisci un nuovo menù"><i class="material-icons">add</i><span class="text">Aggiungi</span></button></p>
			</fieldset>
			<p class="submit">
				<button name="save_draft" type="submit" class="button">
					<span class="button-text"><?php _e('Save draft'); ?></span>
					<i class="material-icons right">cloud_upload</i>
				</button>
				<button name="download" type="submit" class="button">
					<span class="button-text"><?php _e('Download'); ?></span>
					<i class="material-icons right">cloud_download</i>
				</button>
				<button name="publish" type="submit" class="button">
					<span class="button-text"><?php _e('Publish'); ?></span>
					<i class="material-icons right">publish</i>
				</button>
			</p>
		</form>
	</fieldset>
</div>
<?php
include_template('template-parts/footer');
?>
