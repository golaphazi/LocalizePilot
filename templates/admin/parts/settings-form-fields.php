<?php
/**
 * WordPress Settings API fields for a console form.
 *
 * settings_fields() uses the current request as its return URL. That request
 * is admin-ajax.php when a screen arrived through SPA navigation, so this
 * partial emits the same nonce fields with an explicit canonical screen URL.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $tab      Sanitizer branch for this form.
 *     @type string $redirect Canonical console URL after save.
 * }
 */

use LocalizePilot\Plugin;

defined( 'ABSPATH' ) || exit;
?>
<input type="hidden" name="option_page" value="next_translate_group">
<input type="hidden" name="action" value="update">
<?php wp_nonce_field( 'next_translate_group-options', '_wpnonce', false ); ?>
<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( (string) ( $args['redirect'] ?? '' ) ); ?>">
<input
	type="hidden"
	name="<?php echo esc_attr( Plugin::OPTION ); ?>[settings_tab]"
	value="<?php echo esc_attr( (string) ( $args['tab'] ?? '' ) ); ?>"
>
