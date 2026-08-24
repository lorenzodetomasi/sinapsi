<?php
global $ws_query, $ws_themes, $ws_logs;
$ws_themes[] = [
  'name' => 'Your theme',
  'id' => 'your-theme',
  'text_domain' => 'your-theme',
  'version' => 0.1,
  'description' => __('Local business theme 2024', 'localbiz'),
  'uri' => 'https://localbiz.it/themes/your-theme/',
  'license_uri' => 'http://www.gnu.org/licenses/gpl-2.0.html',
  'author_name' => 'Localbiz.it',
  'author_uri' => 'https://localbiz.it',
  'tags' => 'scss, microdata',
  'plugins' => ['google-login', 'google-recaptcha'],
];
// DateTime
define( 'DEFAULT_HUMAN_DATE_FORMAT', __('F jS, Y'));//__('%e %B %Y')
?>