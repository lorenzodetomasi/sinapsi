<?php
global $ws_query, $ws_themes, $ws_logs;
$ws_themes[] = [
  'name' => 'WS',
  'id' => 'ws',
  'text_domain' => 'ws',
  'parent_theme' => false,
  'version' => 0.1,
  'description' => __('WS Official Theme', 'ws'),
  'uri' => 'https://localbiz.it/themes/ws/',
  'license_uri' => 'http://www.gnu.org/licenses/gpl-2.0.html',
  'author_name' => 'Lorenzo De Tomasi',
  'author_uri' => 'https://localbiz.it',
  'tags' => 'scss, microdata',
  'plugins' => ['sassphp','bourbon'],
];
?>
