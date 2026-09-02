<?php
/**
 * Table toolbar: one search field plus filter controls.
 *
 * Filters are native <select> and <input type="checkbox"> elements inside a
 * real GET form, so filtering works with no JavaScript at all. The router
 * treats a console URL carrying query args as a deep link and hands it to the
 * browser, which keeps filtered views shareable.
 *
 * Controls render in the order given, so a screen can place a toggle between
 * two dropdowns the way the design does.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type string $action  Form action URL.
 *     @type array  $hidden  Hidden fields, name => value.
 *     @type array  $search  {name, value, placeholder}.
 *     @type array  $filters Each {type: select|toggle, name, label, value,
 *                           options, icon, checked, divider_before, feature}.
 * }
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_search  = (array) ( $args['search'] ?? array() );
$lp_filters = (array) ( $args['filters'] ?? array() );
?>
<form class="lp-toolbar" method="get" action="<?php echo esc_url( (string) ( $args['action'] ?? '' ) ); ?>" data-lp-autosubmit>
	<?php foreach ( (array) ( $args['hidden'] ?? array() ) as $lp_key => $lp_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( (string) $lp_key ); ?>" value="<?php echo esc_attr( (string) $lp_value ); ?>">
	<?php endforeach; ?>

	<?php if ( ! empty( $lp_search ) ) : ?>
		<p class="lp-search">
			<label class="screen-reader-text" for="lp-search-field"><?php esc_html_e( 'Search', 'localizepilot' ); ?></label>
			<?php Template::the_icon( 'search', 'lp-icon lp-search__icon' ); ?>
			<input
				type="search"
				id="lp-search-field"
				class="lp-search__input"
				name="<?php echo esc_attr( (string) ( $lp_search['name'] ?? 's' ) ); ?>"
				value="<?php echo esc_attr( (string) ( $lp_search['value'] ?? '' ) ); ?>"
				placeholder="<?php echo esc_attr( (string) ( $lp_search['placeholder'] ?? '' ) ); ?>"
			>
		</p>
	<?php endif; ?>

	<div class="lp-toolbar__filters">
		<?php
		foreach ( $lp_filters as $lp_filter ) :
			$lp_name = (string) ( $lp_filter['name'] ?? '' );

			if ( '' === $lp_name ) {
				continue;
			}

			// A control for something the plugin does not record yet renders,
			// but cannot be operated.
			$lp_gate = ! empty( $lp_filter['feature'] ) ? Preview::attributes( (string) $lp_filter['feature'] ) : '';

			if ( ! empty( $lp_filter['divider_before'] ) ) :
				?>
				<span class="lp-toolbar__divider" aria-hidden="true"></span>
				<?php
			endif;

			if ( 'toggle' === ( $lp_filter['type'] ?? 'select' ) ) :
				?>
				<label
					class="lp-chip-toggle<?php echo ! empty( $lp_filter['checked'] ) ? ' is-active' : ''; ?>"
					<?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
				>
					<input
						type="checkbox"
						class="screen-reader-text"
						name="<?php echo esc_attr( $lp_name ); ?>"
						value="1"
						<?php checked( ! empty( $lp_filter['checked'] ) ); ?>
						<?php echo '' !== $lp_gate ? ' disabled' : ''; ?>
					>
					<?php if ( ! empty( $lp_filter['icon'] ) ) : ?>
						<?php Template::the_icon( (string) $lp_filter['icon'], 'lp-icon' ); ?>
					<?php endif; ?>
					<span><?php echo esc_html( (string) ( $lp_filter['label'] ?? $lp_name ) ); ?></span>
				</label>
				<?php
				continue;
			endif;

			$lp_options = (array) ( $lp_filter['options'] ?? array() );

			if ( empty( $lp_options ) ) {
				continue;
			}

			$lp_value = (string) ( $lp_filter['value'] ?? '' );

			// A filter reads as active whenever it is off its default option.
			$lp_default = (string) array_key_first( $lp_options );
			$lp_active  = $lp_value !== $lp_default;
			?>
			<span class="lp-filter<?php echo $lp_active ? ' is-active' : ''; ?>" <?php echo $lp_gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>>
				<label class="screen-reader-text" for="lp-filter-<?php echo esc_attr( $lp_name ); ?>">
					<?php echo esc_html( (string) ( $lp_filter['label'] ?? $lp_name ) ); ?>
				</label>
				<select
					class="lp-filter__select"
					id="lp-filter-<?php echo esc_attr( $lp_name ); ?>"
					name="<?php echo esc_attr( $lp_name ); ?>"
					<?php echo '' !== $lp_gate ? ' disabled' : ''; ?>
				>
					<?php foreach ( $lp_options as $lp_key => $lp_text ) : ?>
						<option value="<?php echo esc_attr( (string) $lp_key ); ?>" <?php selected( (string) $lp_key, $lp_value ); ?>>
							<?php echo esc_html( (string) $lp_text ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php Template::the_icon( 'chevron-down', 'lp-icon lp-filter__chevron' ); ?>
			</span>
		<?php endforeach; ?>

		<button type="submit" class="screen-reader-text"><?php esc_html_e( 'Apply filters', 'localizepilot' ); ?></button>
	</div>
</form>
