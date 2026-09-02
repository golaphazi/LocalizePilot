<?php
/**
 * Console top bar: breadcrumb, global search, notifications, account.
 *
 * Search and notifications render but do nothing — they are gated through
 * Preview so the unfinished surface stays greppable.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Layout data.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_user     = wp_get_current_user();
$lp_name     = trim( (string) $lp_user->display_name );
$lp_initials = '';

foreach ( preg_split( '/\s+/', $lp_name ) ?: array() as $lp_word ) {
	if ( '' !== $lp_word ) {
		$lp_initials .= mb_strtoupper( mb_substr( $lp_word, 0, 1 ) );
	}
}

$lp_initials = mb_substr( '' !== $lp_initials ? $lp_initials : 'WP', 0, 2 );
?>
<header class="lp-topbar">
	<?php
	/*
	 * Always rendered, only shown once the sidebar becomes an overlay, so the
	 * markup does not depend on the viewport it was rendered at — which it
	 * cannot, since a fragment swapped in by the router keeps the shell.
	 */
	?>
	<button
		type="button"
		class="lp-nav-toggle"
		data-lp-nav-toggle
		aria-controls="lp-sidebar"
		aria-expanded="false"
	>
		<?php Template::the_icon( 'menu', 'lp-icon' ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Show navigation', 'localizepilot' ); ?></span>
	</button>

	<nav class="lp-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'localizepilot' ); ?>">
		<a class="lp-breadcrumb__home" href="<?php echo esc_url( admin_url( 'admin.php?page=localizepilot' ) ); ?>">LocalizePilot</a>
		<span class="lp-breadcrumb__sep" aria-hidden="true">/</span>
		<span class="lp-breadcrumb__current" aria-current="page"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></span>
	</nav>

	<div class="lp-topbar__tools">
		<button
			type="button"
			class="lp-search-trigger"
			data-lp-action="global-search"
			<?php echo Preview::attributes( 'global_search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
		>
			<span class="lp-search-trigger__label">
				<?php Template::the_icon( 'search', 'lp-icon' ); ?>
				<span><?php esc_html_e( 'Search translations, languages, settings…', 'localizepilot' ); ?></span>
			</span>
			<kbd class="lp-kbd">&#8984;K</kbd>
		</button>

		<button
			type="button"
			class="lp-icon-button lp-icon-button--badged"
			data-lp-action="notifications"
			<?php echo Preview::attributes( 'notifications' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped literals in Preview::attributes(). ?>
		>
			<?php Template::the_icon( 'bell', 'lp-icon' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Notifications', 'localizepilot' ); ?></span>
		</button>

		<a
			class="lp-icon-button"
			href="https://localizepilot.com/help/"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php Template::the_icon( 'help', 'lp-icon' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Help Center', 'localizepilot' ); ?></span>
		</a>

		<a class="lp-avatar" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">
			<span aria-hidden="true"><?php echo esc_html( $lp_initials ); ?></span>
			<span class="screen-reader-text">
				<?php
				/* translators: %s is the current user's display name. */
				echo esc_html( sprintf( __( 'Edit profile for %s', 'localizepilot' ), $lp_name ) );
				?>
			</span>
		</a>
	</div>
</header>
