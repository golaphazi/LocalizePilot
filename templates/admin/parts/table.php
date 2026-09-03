<?php
/**
 * Data table.
 *
 * Columns describe the header; each row supplies pre-rendered cell HTML keyed
 * by column, so screens compose cells from the other partials and this file
 * stays generic. A real <table> — the design's rows are tabular data, and
 * screen readers and keyboard users get the semantics for free.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array  $columns    Each {key, label, align, width, class}.
 *     @type array  $rows       Each {id, label, cells, actions, class}.
 *     @type bool   $selectable Render the checkbox column.
 *     @type array  $empty      Args for parts/empty-state when rows is empty.
 *     @type string $label      Accessible table caption.
 *     @type bool   $compact    Allow a small table to fit a narrow card.
 * }
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_columns    = (array) ( $args['columns'] ?? array() );
$lp_rows       = (array) ( $args['rows'] ?? array() );
$lp_selectable = ! empty( $args['selectable'] );
$lp_compact    = ! empty( $args['compact'] );

if ( empty( $lp_rows ) ) {
	Template::render( 'parts/empty-state', (array) ( $args['empty'] ?? array() ) );
	return;
}
?>
<div class="lp-table-wrap<?php echo $lp_compact ? ' lp-table-wrap--compact' : ''; ?>">
	<table class="lp-table<?php echo $lp_compact ? ' lp-table--compact' : ''; ?>" <?php echo $lp_selectable ? ' data-lp-selectable' : ''; ?>>
		<?php if ( ! empty( $args['label'] ) ) : ?>
			<caption class="screen-reader-text"><?php echo esc_html( (string) $args['label'] ); ?></caption>
		<?php endif; ?>

		<thead>
			<tr>
				<?php if ( $lp_selectable ) : ?>
					<th class="lp-table__select" scope="col">
						<label class="lp-checkbox">
							<input type="checkbox" data-lp-select-all>
							<span class="screen-reader-text"><?php esc_html_e( 'Select all rows', 'localizepilot' ); ?></span>
						</label>
					</th>
				<?php endif; ?>

				<?php foreach ( $lp_columns as $lp_column ) : ?>
					<th
						scope="col"
						class="<?php echo esc_attr( trim( 'lp-table__col ' . (string) ( $lp_column['class'] ?? '' ) ) ); ?>"
						<?php echo ! empty( $lp_column['align'] ) ? ' data-align="' . esc_attr( (string) $lp_column['align'] ) . '"' : ''; ?>
						<?php echo ! empty( $lp_column['width'] ) ? ' style="width:' . esc_attr( (string) $lp_column['width'] ) . '"' : ''; ?>
					>
						<?php echo esc_html( (string) ( $lp_column['label'] ?? '' ) ); ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $lp_rows as $lp_row ) :
				$lp_cells = (array) ( $lp_row['cells'] ?? array() );
				?>
				<tr class="<?php echo esc_attr( (string) ( $lp_row['class'] ?? '' ) ); ?>">
					<?php if ( $lp_selectable ) : ?>
						<td class="lp-table__select">
							<label class="lp-checkbox">
								<input
									type="checkbox"
									name="lp_selected[]"
									value="<?php echo esc_attr( (string) ( $lp_row['id'] ?? '' ) ); ?>"
									data-lp-select-row
								>
								<span class="screen-reader-text">
									<?php
									/* translators: %s is the name of the row being selected. */
									echo esc_html( sprintf( __( 'Select %s', 'localizepilot' ), (string) ( $lp_row['label'] ?? '' ) ) );
									?>
								</span>
							</label>
						</td>
					<?php endif; ?>

					<?php foreach ( $lp_columns as $lp_column ) : ?>
						<td
							class="<?php echo esc_attr( trim( 'lp-table__cell ' . (string) ( $lp_column['class'] ?? '' ) ) ); ?>"
							<?php echo ! empty( $lp_column['align'] ) ? ' data-align="' . esc_attr( (string) $lp_column['align'] ) . '"' : ''; ?>
						>
							<?php
							// Cells arrive pre-rendered and escaped by the partial that built them.
							echo $lp_cells[ $lp_column['key'] ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
