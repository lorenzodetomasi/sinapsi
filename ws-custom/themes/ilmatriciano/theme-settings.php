<?php
global $ws_query, $ws_themes, $ws_logs;
$ws_themes[] = [
  'name' => 'Il Matriciano (Localbiz Restaurant Child)',
  'id' => 'ilmatriciano',
  'text_domain' => 'ilmatriciano',
  'parent_theme' => 'localbiz-restaurant',
  'version' => 0.1,
  'description' => __('Official child theme of ilmatriciano.it 2019', 'ilmatriciano'),
  'uri' => 'https://localbiz.it/themes/ilmatriciano/',
  'license_uri' => 'http://www.gnu.org/licenses/gpl-2.0.html',
  'author_name' => 'Localbiz.it',
  'author_uri' => 'https://localbiz.it',
  'tags' => 'scss, microdata',
  'plugins' => ['sassphp','bourbon'],
];
?>
