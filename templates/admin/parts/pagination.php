<?php
/**
 * Table footer: result count and pager.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type int    $total    Total rows.
 *     @type int    $page     Current page, 1-based.
 *     @type int    $per_page Rows per page.
 *     @type string $base_url URL the page argument is added to.
 * }
 */

defined( 'ABSPATH' ) || exit;

$lp_total    = max( 0, (int) ( $args['total'] ?? 0 ) );
$lp_per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
$lp_pages    = max( 1, (int) ceil( $lp_total / $lp_per_page ) );
$lp_page     = min( max( 1, (int) ( $args['page'] ?? 1 ) ), $lp_pages );
$lp_base     = (string) ( $args['base_url'] ?? '' );

$lp_first = $lp_total > 0 ? ( ( $lp_page - 1 ) * $lp_per_page ) + 1 : 0;
$lp_last  = min( $lp_page * $lp_per_page, $lp_total );

/**
 * Page numbers to show: always the first pages and the last, with an ellipsis
 * standing in for whatever the design elides in between.
 *
 * @return array<int,int|string>
 */
$lp_numbers = static function () use ( $lp_page, $lp_pages ): array {
	if ( $lp_pages <= 5 ) {
		return range( 1, $lp_pages );
	}

	$window = array( 1, 2, 3 );

	if ( $lp_page > 3 ) {
		$window = array( 1, $lp_page - 1, $lp_page, $lp_page + 1 );
		$window = array_filter( $window, static fn( int $n ): bool => $n >= 1 && $n < $lp_pages );
	}

	$window   = array_values( array_unique( $window ) );
	$window[] = '…';
	$window[] = $lp_pages;

	return $window;
};

$lp_url = static function ( int $page ) use ( $lp_base ): string {
	return esc_url( add_query_arg( 'paged', $page, $lp_base ) );
};
?>
<div class="lp-pagination">
	<p class="lp-pagination__summary">
		<?php
		printf(
			/* translators: 1: first row shown, 2: last row shown, 3: total rows. */
			esc_html__( 'Showing %1$s–%2$s of %3$s', 'localizepilot' ),
			esc_html( number_format_i18n( $lp_first ) ),
			esc_html( number_format_i18n( $lp_last ) ),
			esc_html( number_format_i18n( $lp_total ) )
		);
		?>
	</p>

	<?php if ( $lp_pages > 1 ) : ?>
		<nav class="lp-pager" aria-label="<?php esc_attr_e( 'Pagination', 'localizepilot' ); ?>">
			<?php if ( $lp_page > 1 ) : ?>
				<a class="lp-pager__step" href="<?php echo $lp_url( $lp_page - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
					<?php esc_html_e( 'Previous', 'localizepilot' ); ?>
				</a>
			<?php else : ?>
				<span class="lp-pager__step is-disabled"><?php esc_html_e( 'Previous', 'localizepilot' ); ?></span>
			<?php endif; ?>

			<?php foreach ( $lp_numbers() as $lp_number ) : ?>
				<?php if ( ! is_int( $lp_number ) ) : ?>
					<span class="lp-pager__gap" aria-hidden="true"><?php echo esc_html( $lp_number ); ?></span>
				<?php elseif ( $lp_number === $lp_page ) : ?>
					<span class="lp-pager__page is-current" aria-current="page"><?php echo esc_html( number_format_i18n( $lp_number ) ); ?></span>
				<?php else : ?>
					<a class="lp-pager__page" href="<?php echo $lp_url( $lp_number ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
						<?php echo esc_html( number_format_i18n( $lp_number ) ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $lp_page < $lp_pages ) : ?>
				<a class="lp-pager__step" href="<?php echo $lp_url( $lp_page + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
					<?php esc_html_e( 'Next', 'localizepilot' ); ?>
				</a>
			<?php else : ?>
				<span class="lp-pager__step is-disabled"><?php esc_html_e( 'Next', 'localizepilot' ); ?></span>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
