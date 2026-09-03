<?php
/**
 * KPI grid.
 *
 * Two variants, both taken from the design: the tall Overview card, and the
 * compact summary card the list screens run above their tables.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $items   Each {label, value, value_tone, display, note, note_tone,
 *                           delta, delta_tone, progress}. `display` is number by
 *                           default; `status` renders the value as a coloured dot
 *                           and word, which is how the design shows cache health.
 *     @type string $variant default | compact.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_items = (array) ( $args['items'] ?? array() );

if ( empty( $lp_items ) ) {
	return;
}

$lp_compact = 'compact' === ( $args['variant'] ?? 'default' );
?>
<div class="lp-kpis<?php echo $lp_compact ? ' lp-kpis--compact' : ''; ?>">
	<?php
	foreach ( $lp_items as $lp_item ) :
		$lp_note      = (string) ( $lp_item['note'] ?? '' );
		$lp_note_tone = (string) ( $lp_item['note_tone'] ?? 'muted' );
		$lp_delta     = (string) ( $lp_item['delta'] ?? '' );
		$lp_progress  = $lp_item['progress'] ?? null;
		$lp_status    = 'status' === ( $lp_item['display'] ?? 'number' );
		$lp_tone      = (string) ( $lp_item['value_tone'] ?? '' );

		$lp_value_class = 'lp-kpi__value';

		if ( $lp_status ) {
			$lp_value_class .= ' lp-kpi__value--status';
		}

		if ( '' !== $lp_tone ) {
			$lp_value_class .= ' lp-kpi__value--' . $lp_tone;
		}
		?>
		<article class="lp-kpi">
			<span class="lp-kpi__label"><?php echo esc_html( (string) ( $lp_item['label'] ?? '' ) ); ?></span>

			<div class="lp-kpi__figure">
				<strong class="<?php echo esc_attr( $lp_value_class ); ?>">
					<?php if ( $lp_status ) : ?>
						<i aria-hidden="true"></i>
					<?php endif; ?>
					<?php echo esc_html( (string) ( $lp_item['value'] ?? '' ) ); ?>
				</strong>

				<?php if ( $lp_compact && '' !== $lp_note ) : ?>
					<span class="lp-kpi__note lp-kpi__note--<?php echo esc_attr( $lp_note_tone ); ?>"><?php echo esc_html( $lp_note ); ?></span>
				<?php endif; ?>

				<?php if ( ! $lp_compact && '' !== $lp_delta ) : ?>
					<span class="lp-kpi__delta lp-kpi__delta--<?php echo esc_attr( (string) ( $lp_item['delta_tone'] ?? 'up' ) ); ?>">
						<?php Template::the_icon( 'arrow-right', 'lp-icon lp-kpi__delta-icon' ); ?>
						<?php echo esc_html( $lp_delta ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php
			if ( ! $lp_compact && null !== $lp_progress ) {
				Template::render(
					'parts/progress',
					array(
						'value' => (int) $lp_progress,
						'tone'  => 'brand',
						'width' => '100%',
					)
				);
			}
			?>

			<?php if ( ! $lp_compact && '' !== $lp_note ) : ?>
				<span class="lp-kpi__sub lp-kpi__sub--<?php echo esc_attr( $lp_note_tone ); ?>">
					<?php if ( 'warning' === $lp_note_tone ) : ?>
						<i aria-hidden="true"></i>
					<?php endif; ?>
					<?php echo esc_html( $lp_note ); ?>
				</span>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
