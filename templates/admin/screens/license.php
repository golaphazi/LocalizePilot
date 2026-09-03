<?php
/**
 * License Activation screen.
 *
 * A centred column of four cards. The controls work — the key field accepts
 * input, the buttons respond, the links open. What the screen will not do is
 * pretend to reach a licence server, because the plugin has none: activating
 * validates the key's shape and then says plainly that licensing is not
 * connected. Every status value reports the real, inactive state.
 *
 * @package LocalizePilot
 *
 * @var array<string,mixed> $args Screen arguments.
 */

use LocalizePilot\Admin\Preview;
use LocalizePilot\Admin\Template;

defined( 'ABSPATH' ) || exit;

$lp_data     = (array) ( $args['data'] ?? array() );
$lp_status   = (array) ( $lp_data['status'] ?? array() );
$lp_details  = (array) ( $lp_data['details'] ?? array() );
$lp_features = (array) ( $lp_data['features'] ?? array() );
$lp_links    = (array) ( $lp_data['links'] ?? array() );
/*
 * The screen is interactive: fields accept input and buttons respond. What it
 * will not do is pretend to reach a licence server, so the controls answer
 * inline instead of being disabled and unusable.
 */
$lp_preview  = Preview::is_preview( 'license' );
?>
<div class="lp-column">

	<section class="lp-card">
		<div class="lp-card__body">
			<span class="lp-card__eyebrow"><?php esc_html_e( 'License activation', 'localizepilot' ); ?></span>
			<h2 class="lp-card__title"><?php esc_html_e( 'Activate your license', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Enter your LocalizePilot license key to activate this site and access your plan features.', 'localizepilot' ); ?></p>

			<div class="lp-license-form" data-lp-license-form<?php echo $lp_preview ? ' data-lp-license-preview="1"' : ''; ?>>
				<label class="lp-field__label" for="lp-license-key"><?php esc_html_e( 'License key', 'localizepilot' ); ?></label>

				<div class="lp-license-form__row">
					<input
						class="lp-field__control lp-license-form__key"
						type="text"
						id="lp-license-key"
						name="localizepilot_license_key"
						value=""
						placeholder="XXXX-XXXX-XXXX-XXXX-XXXX"
						autocomplete="off"
						spellcheck="false"
						data-lp-license-key
					>

					<button type="button" class="lp-btn lp-btn--primary" data-lp-license-activate>
						<?php esc_html_e( 'Activate License', 'localizepilot' ); ?>
						<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
					</button>
				</div>

				<p class="lp-license-form__result" data-lp-license-result role="status" hidden></p>

				<p class="lp-field__hint">
					<?php
					printf(
						/* translators: %s: Link to the LocalizePilot account page. */
						esc_html__( 'Your license key is available in your %s.', 'localizepilot' ),
						'<a href="' . esc_url( (string) ( $lp_links['account'] ?? '' ) ) . '" target="_blank" rel="noopener noreferrer">'
							. esc_html__( 'LocalizePilot account', 'localizepilot' ) . '</a>'
					);
					?>
				</p>
			</div>
		</div>
	</section>

	<section class="lp-card">
		<div class="lp-card__body">
			<header class="lp-license-status__head">
				<h2 class="lp-card__title"><?php esc_html_e( 'License status', 'localizepilot' ); ?></h2>

				<?php
				Template::render(
					'parts/badge',
					array(
						'label' => (string) ( $lp_status['badge'] ?? '' ),
						'tone'  => ! empty( $lp_status['active'] ) ? 'success' : 'warning',
					)
				);
				?>
			</header>

			<dl class="lp-detail-list">
				<?php foreach ( $lp_details as $lp_detail ) : ?>
					<div class="lp-detail-list__row">
						<dt><?php echo esc_html( (string) ( $lp_detail['label'] ?? '' ) ); ?></dt>
						<dd>
							<?php if ( ! empty( $lp_detail['code'] ) ) : ?>
								<code><?php echo esc_html( (string) ( $lp_detail['value'] ?? '' ) ); ?></code>
							<?php else : ?>
								<?php echo esc_html( (string) ( $lp_detail['value'] ?? '' ) ); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>

			<div class="lp-license-status__foot">
				<button type="button" class="lp-card__link" data-lp-license-check>
					<?php esc_html_e( 'Check license status', 'localizepilot' ); ?>
					<?php Template::the_icon( 'arrow-right-sm', 'lp-icon' ); ?>
				</button>
			</div>
		</div>
	</section>

	<section class="lp-card">
		<div class="lp-card__body">
			<h2 class="lp-card__title"><?php esc_html_e( 'Unlock the full LocalizePilot experience', 'localizepilot' ); ?></h2>
			<p class="lp-card__subtitle"><?php esc_html_e( 'Upgrade to LocalizePilot Pro to unlock advanced translation, performance, analytics, and automation features.', 'localizepilot' ); ?></p>

			<ul class="lp-feature-list">
				<?php foreach ( $lp_features as $lp_feature ) : ?>
					<li>
						<?php Template::the_icon( 'check-circle', 'lp-icon lp-feature-list__icon' ); ?>
						<span><?php echo esc_html( (string) $lp_feature ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="lp-license-upgrade__foot">
				<a
					class="lp-btn lp-btn--primary"
					href="<?php echo esc_url( (string) ( $lp_links['plans'] ?? '' ) ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php esc_html_e( 'View Plans', 'localizepilot' ); ?>
					<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
				</a>

				<span class="lp-license-upgrade__note">
					<?php esc_html_e( 'Already purchased? Activate your license above.', 'localizepilot' ); ?>
				</span>
			</div>
		</div>
	</section>

	<section class="lp-card">
		<div class="lp-card__body lp-license-help">
			<div>
				<strong><?php esc_html_e( 'Need help with your license?', 'localizepilot' ); ?></strong>
				<p><?php esc_html_e( 'Find your license key, subscription details, and billing information in your account.', 'localizepilot' ); ?></p>
			</div>

			<div class="lp-license-help__links">
				<?php
				$lp_help_links = array(
					'account'       => __( 'Open Account', 'localizepilot' ),
					'documentation' => __( 'Documentation', 'localizepilot' ),
					'support'       => __( 'Contact Support', 'localizepilot' ),
				);

				foreach ( $lp_help_links as $lp_key => $lp_label ) :
					?>
					<a
						class="lp-card__link"
						href="<?php echo esc_url( (string) ( $lp_links[ $lp_key ] ?? '' ) ); ?>"
						target="_blank"
						rel="noopener noreferrer"
					>
						<?php echo esc_html( $lp_label ); ?>
						<?php Template::the_icon( 'external-link', 'lp-icon' ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

</div>
