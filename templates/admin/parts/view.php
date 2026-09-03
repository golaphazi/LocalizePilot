<?php
/**
 * The swappable region: page header plus screen body.
 *
 * Rendered inline on a full page load and returned verbatim by the navigation
 * endpoint, so a screen has exactly one template and one code path either way.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_screen = $args['screen'] ?? null;
$lp_body   = $lp_screen ? $lp_screen->template() : '';

Template::render( 'parts/page-header', $args );

?>
<div class="lp-screen-body">
	<?php
	if ( '' !== $lp_body && Template::exists( $lp_body ) ) {
		Template::render( $lp_body, $args );
	} else {
		Template::render( 'parts/placeholder', $args );
	}
	?>
</div>
