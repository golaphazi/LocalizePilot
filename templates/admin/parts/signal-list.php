<?php
/**
 * Label-and-value rows on a tinted pill.
 *
 * The drawer's Language Signals list and the Localized Internal Links card are
 * the same row in the design — a short label, a URL or path, and sometimes a
 * state at the right — so they are the same partial here.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $rows  Each {label, value, badge:{label,tone}}.
 *     @type string $class Extra classes.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_rows = (array) ( $args['rows'] ?? array() );

if ( empty( $lp_rows ) ) {
	return;
}
?>
<ul class="lp-signals <?php echo esc_attr( (string) ( $args['class'] ?? '' ) ); ?>">
	<?php
	foreach ( $lp_rows as $lp_row ) :
		$lp_badge = (array) ( $lp_row['badge'] ?? array() );
		?>
		<li class="lp-signals__row">
			<span class="lp-signals__label"><?php echo esc_html( (string) ( $lp_row['label'] ?? '' ) ); ?></span>
			<code class="lp-signals__value" title="<?php echo esc_attr( (string) ( $lp_row['value'] ?? '' ) ); ?>">
				<?php echo esc_html( (string) ( $lp_row['value'] ?? '' ) ); ?>
			</code>
			<?php
			if ( ! empty( $lp_badge['label'] ) ) {
				Template::render(
					'parts/badge',
					array(
						'label' => (string) $lp_badge['label'],
						'tone'  => (string) ( $lp_badge['tone'] ?? 'neutral' ),
					)
				);
			}
			?>
		</li>
	<?php endforeach; ?>
</ul>
