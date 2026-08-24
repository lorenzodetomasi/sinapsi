<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * e.g., it puts together the home page when no home.php file exists.
 *
 * Learn more: {@link https://codex.wordpress.org/Template_Hierarchy}
 *
 * @package WordPress
 * @subpackage Isotype_Theme
 * @since Isotype 1.0
 */
global $ws_content;
include_template('template-parts/header');
$load_file = array(

);
?>
		<main>
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name; ?></h1>
<?php
}
?>
<?php
if($ws_content->description){
?>
			<div itemprop="description">
				<?php ?>
			</div>
<?php
}
?>
			<div class="content">
<?php
if($ws_content->main){
	echo $ws_content->main->innerHTML();
}
?>
				<section>
					<h1>Log</h1>
					<ol>
<?php
foreach ($logs as $log) {
?>
						<li><?php echo $log; ?></li>
<?php
}
?>
					</ol>
				</section>
				<form>
					<p>
						<label class="question field url">
							<span class="label">Kml Url</span>
							<span class="input url flex1">
								<input name="kml_url" type="url" value="<?php echo $kml_url; ?>" />
							</span>
						</label>
					</p>
					<p>
						<label class="question field text">
							<span class="label"><?php printf(__('Save file as <code>%s</code>', 'isotype'), ws_content_relpath()); ?></span>
							<span class="input flex1">
								<input name="relpath" type="text" value="<?php echo $basename; ?>" />
							</span>
						</label>
					</p>
					<p class="submit">
						<button type="submit" class="button h48" disabled="disabled">
							<span class="button-text">Crea file xml</span>
							<i class="material-icons right">send</i>
						</button>
					</p>
				</form>
			</div>
		</main>
<?php
include_template('template-parts/footer');
?>
