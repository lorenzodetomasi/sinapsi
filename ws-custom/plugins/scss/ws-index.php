<?php
/**
 * SCSS - an optional plugin, off by default.
 *
 * The themes' stylesheets are CSS, edited by hand: the .scss sources they
 * were once compiled from are obsolete, and a refresh that recompiled them
 * would overwrite weeks of work in the .css files. So the compiler is no
 * longer part of the core, and the admin refresh does not know it exists
 * unless a theme asks for it.
 *
 * To turn it on, list it in a theme's settings:
 *     'plugins' => ['scss'],
 * The refresh (ws-admin/refresh.php, the "CSS" checkbox) then finds every
 * `scss/` directory under the themes and compiles each .scss not starting
 * with `_` into `../css/<name>.css` - overwriting what is there. `scss/`
 * here holds the shared partials (reset, normalize, core) a theme's
 * sources may import; `lib/scssphp` is the compiler
 * (https://scssphp.github.io/scssphp/).
 *
 * @package WS
 * @subpackage SCSS
 */
global $ws_logs;
$ws_logs[] = __('<strong>SCSS</strong> Plugin initialized <code>'.__FILE__.'</code>.');

require_once __DIR__ . '/lib/scssphp/scss.inc.php';
require_once __DIR__ . '/refresh.php';
?>
