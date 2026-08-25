<?php
global $ws_query, $ws_themes, $ws_logs;
$ws_themes[] = [
  'name' => 'Meetoo',
  'id' => 'meetoo',
  'text_domain' => 'meetoo',
  'version' => 0.1,
  'description' => __('Tema di Meetoo: eventi, luoghi e gruppi di un quartiere', 'meetoo'),
  'uri' => 'https://meetoo.it',
  // Eredita da your-theme l'impalcatura comune (testa, attributi, navigazione,
  // header compatto). Quello che Meetoo ha di suo — i token, le card, il tema
  // scuro — sta nei suoi fogli di stile e nei suoi template.
  'parent_theme' => 'your-theme',
  'license_uri' => 'http://www.gnu.org/licenses/gpl-2.0.html',
  'author_name' => 'Meetoo',
  'author_uri' => 'https://meetoo.it',
  'tags' => 'json-ld, schema.org, mobile-first',
];
?>
