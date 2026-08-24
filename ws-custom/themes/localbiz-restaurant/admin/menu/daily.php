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
	$json_abspath = $file_abspath.'/index.json';
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
	$xml_abspath = $file_abspath.'/index.xml';
	echo $ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		abspath2url($xml_abspath),
		$ws_query['content'].'/index.xml',
//		basename($ws_sitemap_pathinfo['dirname']).'/'.$ws_sitemap_pathinfo['basename'],
		ws_save_file( $DOMDocument, $xml_abspath)
	); echo ' ';
	echo $ws_log = sprintf(__('An archive copy with file_timestamp has been saved.'),
		ws_save_file( $DOMDocument, $xml_abspath, $args = array('file_timestamp' => true))
	); echo '<br />';
}
?>
	<form action="<?php echo ws_href('/admin/menu/del-giorno'); ?>" method="POST" name="menu-daily">
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
			<fieldset class="fieldset">
				<legend><?php _e('Daily items'); ?></legend>
				<p><small><?php _e('Drag and drop to organize the order of the items.'); ?></small></p>
				<div class="repeat sortable" id="menu-daily">
<?php
$menuItemIndex = 0;
foreach ($ws_content->hasMenuItem as $key => $menuItem) {
?>
					<fieldset class="fieldset repeat-item background-color3-lighter padding-bottom margin-bottom" id="menu_item__<?php echo $menuItemIndex; ?>" draggable="true">
						<div class="flex input-group">
							<button class="link" style="align-self: center;"><i class="material-icons">drag_handle</i></button>
							<div class="flex1">
								<p class="question">
									<label class="flex field">
										<strong class="label"><?php _e('Name'); ?></strong>
										<input type="text" name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][name]" data-copy-index class="flex1" placeholder="<?php _e('ad es. Spaghetti alla carbonara', 'localbiz'); ?>" value="<?php echo $menuItem->name; ?>" />
									</label>
								</p>
								<p class="question">
									<label class="flex field textarea">
										<strong class="label"><?php _e('Description'); ?></strong>
										<textarea name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][description]" class="flex1" placeholder="<?php _e('ad es. Spaghetti, uova fresche a km0, guanciale di Amatrice DOP, pecorino romano DOP, sale, pepe nero, olio extravergine di oliva della Sabina DOP', 'localbiz'); ?>"><?php echo $menuItem->description; ?></textarea>
									</label>
								</p>
								<fieldset class="question">
									<legend><?php _e('Offer'); ?></legend>
									<div class="flex">
										<label class="flex field price-currency">
											<strong class="label"><?php _e('Currency'); ?></strong>
											<input type="text" name="data[menu][hasMenuItem][<?php echo $menuItemIndex; ?>][offers][priceCurrency]" class="flex1" placeholder="<?php _e('ad es. EUR', 'isotype'); ?>" value="<?php echo $menuItem->offers->priceCurrency; ?>" />
										</label>
										<label class="flex field price-value flex1 margin-left-d4">
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
