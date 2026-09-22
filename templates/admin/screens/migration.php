<?php
/**
 * Migration screen.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_sources  = (array) ( $lp_data['sources'] ?? array() );
$lp_run      = $lp_data['run'] ?? null;
$lp_running  = is_array( $lp_run ) && 'running' === ( $lp_run['status'] ?? '' );
$lp_drafted  = (int) ( $lp_data['drafted'] ?? 0 );
$lp_detected = array_filter( $lp_sources, static fn( $source ) => ! empty( $source['detected'] ) );
?>
<div class="lp-migration" data-lp-migration>
	<div class="lp-migration__run" data-lp-migration-slot>
		<?php
		if ( is_array( $lp_run ) ) {
			Template::render( 'parts/migration-progress', array( 'run' => $lp_run ) );
		}
		?>
	</div>

	<?php if ( $lp_drafted > 0 && ! $lp_running ) : ?>
		<div class="lp-banner lp-banner--info lp-migration__restore" data-lp-migration-restore-banner>
			<strong><?php esc_html_e( 'Old translated posts are in draft', 'localizepilot' ); ?></strong>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of posts. */
						_n(
							'%s post was moved to draft by a migration. Put it back if you still need it where it was.',
							'%s posts were moved to draft by a migration. Put them back if you still need them where they were.',
							$lp_drafted,
							'localizepilot'
						),
						number_format_i18n( $lp_drafted )
					)
				);
				?>
			</p>
			<button type="button" class="lp-btn lp-btn--ghost" data-lp-migration-action="restore"><?php esc_html_e( 'Put them back', 'localizepilot' ); ?></button>
		</div>
	<?php endif; ?>

	<?php if ( empty( $lp_detected ) ) : ?>
		<section class="lp-card">
			<div class="lp-card__body">
				<?php
				Template::render(
					'parts/empty-state',
					array(
						'icon'    => 'nav-migration',
						'title'   => __( 'Nothing to migrate on this site', 'localizepilot' ),
						'message' => __( 'LocalizePilot can bring translations over from WPML and Polylang. It reads what they left in the database, so the old plugin can already be switched off — but neither has any translations here.', 'localizepilot' ),
					)
				);
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php foreach ( $lp_detected as $lp_source ) : ?>
		<?php
		$lp_preview   = (array) $lp_source['preview'];
		$lp_to_enable = (array) ( $lp_preview['to_enable'] ?? array() );
		$lp_can_start = ! empty( $lp_preview['has_default'] ) && ! $lp_running;
		?>
		<section class="lp-card lp-migration-source" data-lp-migration-source="<?php echo esc_attr( (string) $lp_source['id'] ); ?>">
			<header class="lp-card__head">
				<div>
					<h2 class="lp-card__title">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: plugin name such as WPML. */
								__( 'Translations from %s', 'localizepilot' ),
								(string) $lp_source['label']
							)
						);
						?>
					</h2>
					<p class="lp-card__subtitle">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of groups, 2: plugin name. */
								_n(
									'%1$s group of translated pages found in %2$s\'s data.',
									'%1$s groups of translated pages found in %2$s\'s data.',
									(int) $lp_preview['groups'],
									'localizepilot'
								),
								number_format_i18n( (int) $lp_preview['groups'] ),
								(string) $lp_source['label']
							)
						);
						?>
					</p>
				</div>
			</header>

			<div class="lp-card__body lp-migration-source__body">
				<?php if ( empty( $lp_preview['has_default'] ) ) : ?>
					<div class="lp-banner lp-banner--warning">
						<strong><?php esc_html_e( 'No content in your default language', 'localizepilot' ); ?></strong>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: plugin name, 2: language name such as English. */
									__( '%1$s has nothing in %2$s, LocalizePilot\'s default language, so there are no pages to attach its translations to. If your site is written in another language, change the default language in Settings first.', 'localizepilot' ),
									(string) $lp_source['label'],
									(string) $lp_preview['default_name']
								)
							);
							?>
						</p>
						<a class="lp-btn lp-btn--ghost" href="<?php echo esc_url( (string) ( $lp_data['settings_url'] ?? '' ) ); ?>"><?php esc_html_e( 'Open Settings', 'localizepilot' ); ?></a>
					</div>
				<?php endif; ?>

				<div class="lp-migration-source__grid">
					<div>
						<h3 class="lp-field__label"><?php esc_html_e( 'Languages', 'localizepilot' ); ?></h3>
						<div class="lp-table-wrap">
							<table class="lp-table lp-table--compact">
								<thead>
									<tr>
										<th scope="col">
											<?php
											/* translators: %s: plugin name such as WPML. */
											echo esc_html( sprintf( __( 'In %s', 'localizepilot' ), (string) $lp_source['label'] ) );
											?>
										</th>
										<th scope="col"><?php esc_html_e( 'In LocalizePilot', 'localizepilot' ); ?></th>
										<th scope="col" data-align="right"><?php esc_html_e( 'Posts', 'localizepilot' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( (array) $lp_preview['languages'] as $lp_language ) : ?>
										<tr>
											<td><code><?php echo esc_html( (string) $lp_language['code'] ); ?></code></td>
											<td>
												<?php if ( null === $lp_language['maps_to'] ) : ?>
													<?php Template::render( 'parts/badge', array( 'label' => __( 'Not supported', 'localizepilot' ), 'tone' => 'danger' ) ); ?>
												<?php else : ?>
													<span class="lp-migration-lang"><?php echo esc_html( (string) $lp_language['name'] ); ?></span>
													<?php
													if ( ! empty( $lp_language['is_default'] ) ) {
														Template::render( 'parts/badge', array( 'label' => __( 'Default language', 'localizepilot' ), 'tone' => 'info' ) );
													} elseif ( ! empty( $lp_language['enabled'] ) ) {
														Template::render( 'parts/badge', array( 'label' => __( 'On', 'localizepilot' ), 'tone' => 'success' ) );
													} else {
														Template::render( 'parts/badge', array( 'label' => __( 'Off', 'localizepilot' ), 'tone' => 'warning' ) );
													}
													?>
												<?php endif; ?>
											</td>
											<td data-align="right"><?php echo esc_html( number_format_i18n( (int) $lp_language['posts'] ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

					<div>
						<h3 class="lp-field__label"><?php esc_html_e( 'Content types', 'localizepilot' ); ?></h3>
						<div class="lp-table-wrap">
							<table class="lp-table lp-table--compact">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Type', 'localizepilot' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Migrated', 'localizepilot' ); ?></th>
										<th scope="col" data-align="right"><?php esc_html_e( 'Posts', 'localizepilot' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( (array) $lp_preview['post_types'] as $lp_type ) : ?>
										<tr>
											<td><?php echo esc_html( (string) $lp_type['label'] ); ?></td>
											<td>
												<?php
												Template::render(
													'parts/badge',
													! empty( $lp_type['translatable'] )
														? array( 'label' => __( 'Yes', 'localizepilot' ), 'tone' => 'success' )
														: array( 'label' => __( 'Not translatable', 'localizepilot' ), 'tone' => 'neutral' )
												);
												?>
											</td>
											<td data-align="right"><?php echo esc_html( number_format_i18n( (int) $lp_type['posts'] ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php if ( in_array( false, array_column( (array) $lp_preview['post_types'], 'translatable' ), true ) ) : ?>
							<p class="lp-field__hint">
								<?php esc_html_e( 'Types that are not translatable are left where they are. Turn them on under Settings, Translatable content, then run the migration again — it picks up only what it has not already brought over.', 'localizepilot' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<form class="lp-migration-source__form" data-lp-migration-form>
					<input type="hidden" name="source" value="<?php echo esc_attr( (string) $lp_source['id'] ); ?>">

					<?php if ( ! empty( $lp_to_enable ) ) : ?>
						<label class="lp-migration-option">
							<span class="lp-checkbox"><input type="checkbox" name="enable_languages" value="1" checked></span>
							<span>
								<strong><?php esc_html_e( 'Turn on the languages being brought over', 'localizepilot' ); ?></strong>
								<span><?php echo esc_html( implode( ', ', $lp_to_enable ) ); ?> — <?php esc_html_e( 'without this, their translations are imported but not shown on the site.', 'localizepilot' ); ?></span>
							</span>
						</label>
					<?php endif; ?>

					<label class="lp-migration-option">
						<span class="lp-checkbox"><input type="checkbox" name="draft_old" value="1"></span>
						<span>
							<strong>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: plugin name such as WPML. */
										__( 'Move %s\'s translated posts to draft afterwards', 'localizepilot' ),
										(string) $lp_source['label']
									)
								);
								?>
							</strong>
							<span><?php esc_html_e( 'Once the old plugin is switched off, its translated pages show up as ordinary pages in blog lists and archives. This hides them. Nothing is deleted, and you can put them back from this screen.', 'localizepilot' ); ?></span>
						</span>
					</label>

					<div class="lp-migration-source__actions">
						<button type="submit" class="lp-btn lp-btn--primary" data-lp-migration-action="start" <?php disabled( ! $lp_can_start ); ?>>
							<?php esc_html_e( 'Start migration', 'localizepilot' ); ?>
						</button>
						<span class="lp-field__hint">
							<?php
							echo $lp_running
								? esc_html__( 'Finish or cancel the migration above first.', 'localizepilot' )
								: esc_html__( 'Existing LocalizePilot translations are never replaced, so running this again is safe.', 'localizepilot' );
							?>
						</span>
					</div>
				</form>
			</div>
		</section>
	<?php endforeach; ?>

	<section class="lp-card lp-migration-promises">
		<header class="lp-card__head">
			<div>
				<h2 class="lp-card__title"><?php esc_html_e( 'What a migration does', 'localizepilot' ); ?></h2>
			</div>
		</header>
		<div class="lp-card__body">
			<ul class="lp-migration-promises__list">
				<li><?php esc_html_e( 'Copies each translated page — title, content, excerpt, address and featured image — into LocalizePilot, attached to the page in your default language.', 'localizepilot' ); ?></li>
				<li><?php esc_html_e( 'Marks them as edited by a person, so automatic translation never writes over them.', 'localizepilot' ); ?></li>
				<li><?php esc_html_e( 'Only reads the old plugin\'s data. Nothing of it is changed or deleted, unless you choose to move its posts to draft.', 'localizepilot' ); ?></li>
				<li><?php esc_html_e( 'Never replaces a translation LocalizePilot already has, and never calls a translation provider.', 'localizepilot' ); ?></li>
				<li><?php esc_html_e( 'Runs while this screen is open. Close it and the migration waits where it stopped until you press Continue.', 'localizepilot' ); ?></li>
			</ul>
		</div>
	</section>
</div>
