<?php
/**
 * A migration run: how far it has got and what happened to each translation.
 *
 * Rendered with the screen and again after every batch; the script swaps the
 * whole card, so this is the only place its markup is decided.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args {
 *     @type array $run Run state from Migrator::run().
 * }
 */

use LocalizePilot\Admin\Template;
use LocalizePilot\Language_Catalog;

defined( 'ABSPATH' ) || exit;

$lp_run    = (array) ( $args['run'] ?? array() );
$lp_status = (string) ( $lp_run['status'] ?? '' );
$lp_errors = (array) ( $lp_run['errors'] ?? array() );

$lp_badges = array(
	'running'   => array( __( 'In progress', 'localizepilot' ), 'info' ),
	'done'      => array( __( 'Finished', 'localizepilot' ), 'success' ),
	'cancelled' => array( __( 'Cancelled', 'localizepilot' ), 'neutral' ),
);
$lp_badge = $lp_badges[ $lp_status ] ?? array( ucfirst( $lp_status ), 'neutral' );

$lp_counts = array(
	array( 'created', __( 'Imported', 'localizepilot' ), '' ),
	array( 'existing', __( 'Already in LocalizePilot', 'localizepilot' ), '' ),
	array( 'no_default', __( 'No version in the default language', 'localizepilot' ), 'is-muted' ),
	array( 'unsupported', __( 'Language not supported', 'localizepilot' ), 'is-muted' ),
	array( 'not_translatable', __( 'Content type not translatable', 'localizepilot' ), 'is-muted' ),
	array( 'failed', __( 'Failed', 'localizepilot' ), 'is-failed' ),
);

$lp_enabled = array_map(
	static fn( $code ) => Language_Catalog::label( (string) $code, 'english' ),
	(array) ( $lp_run['enabled'] ?? array() )
);
?>
<section class="lp-card lp-migration-run" data-lp-migration-run data-lp-migration-status="<?php echo esc_attr( $lp_status ); ?>">
	<header class="lp-card__head">
		<div>
			<h2 class="lp-card__title">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: plugin name such as WPML. */
						__( 'Migrating from %s', 'localizepilot' ),
						(string) ( $lp_run['label'] ?? '' )
					)
				);
				?>
			</h2>
			<p class="lp-card__subtitle lp-migration-run__status" role="status">
				<?php Template::render( 'parts/badge', array( 'label' => $lp_badge[0], 'tone' => $lp_badge[1] ) ); ?>
			</p>
		</div>
	</header>

	<div class="lp-card__body lp-migration-run__body">
		<?php
		Template::render(
			'parts/progress',
			array(
				'value' => (int) ( $lp_run['percent'] ?? 0 ),
				'label' => true,
				'width' => '100%',
			)
		);
		?>

		<p class="lp-migration-run__groups">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: translation groups checked so far, 2: groups in total. */
					__( '%1$s of %2$s translation groups checked', 'localizepilot' ),
					number_format_i18n( (int) ( $lp_run['processed'] ?? 0 ) ),
					number_format_i18n( (int) ( $lp_run['total'] ?? 0 ) )
				)
			);
			?>
		</p>

		<dl class="lp-migration-run__counts">
			<?php foreach ( $lp_counts as $lp_count ) : ?>
				<div class="<?php echo esc_attr( (int) ( $lp_run[ $lp_count[0] ] ?? 0 ) > 0 ? $lp_count[2] : 'is-zero' ); ?>">
					<dt><?php echo esc_html( $lp_count[1] ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( (int) ( $lp_run[ $lp_count[0] ] ?? 0 ) ) ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>

		<?php if ( ! empty( $lp_enabled ) ) : ?>
			<p class="lp-migration-run__note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: comma-separated language names. */
						__( 'Turned on for this migration: %s.', 'localizepilot' ),
						implode( ', ', $lp_enabled )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( (int) ( $lp_run['drafted'] ?? 0 ) > 0 ) : ?>
			<p class="lp-migration-run__note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of posts, 2: plugin name such as WPML. */
						_n(
							'%1$s of %2$s\'s translated posts was moved to draft. You can put it back below.',
							'%1$s of %2$s\'s translated posts were moved to draft. You can put them back below.',
							(int) $lp_run['drafted'],
							'localizepilot'
						),
						number_format_i18n( (int) $lp_run['drafted'] ),
						(string) ( $lp_run['label'] ?? '' )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $lp_errors ) ) : ?>
			<details class="lp-migration-run__errors">
				<summary>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of errors shown. */
							_n( '%s recent problem', '%s recent problems', count( $lp_errors ), 'localizepilot' ),
							number_format_i18n( count( $lp_errors ) )
						)
					);
					?>
				</summary>
				<ul>
					<?php foreach ( array_reverse( $lp_errors ) as $lp_error ) : ?>
						<li>
							<strong><?php echo esc_html( (string) ( $lp_error['item'] ?? '' ) ); ?></strong>
							<span><?php echo esc_html( (string) ( $lp_error['message'] ?? '' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
	</div>

	<?php if ( 'running' === $lp_status ) : ?>
		<footer class="lp-migration-run__actions">
			<?php
			/*
			 * Both are rendered; the script shows whichever fits. Loaded on its
			 * own, a run in progress is not being worked on — nothing moves it
			 * until someone presses Continue.
			 */
			?>
			<button type="button" class="lp-btn lp-btn--primary" data-lp-migration-action="continue"><?php esc_html_e( 'Continue migration', 'localizepilot' ); ?></button>
			<button type="button" class="lp-btn lp-btn--ghost" data-lp-migration-action="pause" hidden><?php esc_html_e( 'Pause', 'localizepilot' ); ?></button>
			<button type="button" class="lp-btn lp-btn--danger" data-lp-migration-action="cancel"><?php esc_html_e( 'Cancel', 'localizepilot' ); ?></button>
		</footer>
	<?php endif; ?>
</section>
