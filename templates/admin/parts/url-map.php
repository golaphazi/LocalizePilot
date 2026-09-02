<?php
/**
 * A bordered list of language → URL rows.
 *
 * Two layouts from the design, one partial: "inline" puts the URL at the right
 * of the language name, which is what the URL structure card shows; "stacked"
 * puts it under the name and adds a status badge and an open-in-new affordance,
 * which is what the drawer shows.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $rows     Each {code, name, url, tone, label}.
 *     @type string $variant  inline | stacked.
 *     @type bool   $external Add a link out to each row.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_rows = (array) ( $args['rows'] ?? array() );

if ( empty( $lp_rows ) ) {
	return;
}

$lp_stacked  = 'stacked' === ( $args['variant'] ?? 'inline' );
$lp_external = ! empty( $args['external'] );
?>
<ul class="lp-url-map<?php echo $lp_stacked ? ' lp-url-map--stacked' : ''; ?>">
	<?php
	foreach ( $lp_rows as $lp_row ) :
		$lp_code = (string) ( $lp_row['code'] ?? '' );
		$lp_url  = (string) ( $lp_row['url'] ?? '' );
		?>
		<li class="lp-url-map__row">
			<?php
			Template::render(
				'parts/lang-chip',
				array(
					'code'  => $lp_code,
					'tone'  => empty( $lp_row['source'] ) ? 'target' : 'source',
					'title' => (string) ( $lp_row['name'] ?? '' ),
				)
			);
			?>

			<span class="lp-url-map__text">
				<strong class="lp-url-map__name"><?php echo esc_html( (string) ( $lp_row['name'] ?? '' ) ); ?></strong>
				<code class="lp-url-map__url" title="<?php echo esc_attr( $lp_url ); ?>"><?php echo esc_html( $lp_url ); ?></code>
			</span>

			<?php
			if ( ! empty( $lp_row['label'] ) ) {
				Template::render(
					'parts/badge',
					array(
						'label' => (string) $lp_row['label'],
						'tone'  => (string) ( $lp_row['tone'] ?? 'neutral' ),
					)
				);
			}
			?>

			<?php if ( $lp_external && '' !== $lp_url ) : ?>
				<a class="lp-url-map__open" href="<?php echo esc_url( $lp_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
					<span class="screen-reader-text">
						<?php
						/* translators: %s is a language name. */
						echo esc_html( sprintf( __( 'Open the %s version in a new tab', 'localizepilot' ), (string) ( $lp_row['name'] ?? '' ) ) );
						?>
					</span>
				</a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
