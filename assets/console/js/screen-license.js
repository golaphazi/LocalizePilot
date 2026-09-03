/**
 * License Activation screen.
 *
 * The screen is fully interactive, but there is no licence server behind it:
 * the plugin stores no key and calls nothing. So the controls do the part that
 * is real — checking the shape of what was typed — and then say plainly that
 * activation is not connected, rather than being disabled and unusable or
 * faking a success.
 */
( function ( window ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	// XXXX-XXXX-XXXX-XXXX-XXXX, the format the placeholder advertises.
	var KEY_PATTERN = /^[A-Z0-9]{4}(-[A-Z0-9]{4}){4}$/i;

	function text( key, fallback ) {
		return LocalizePilot.text ? LocalizePilot.text( key, fallback ) : fallback;
	}

	function say( form, message, tone ) {
		var result = LocalizePilot.find( '[data-lp-license-result]', form );

		if ( ! result ) {
			return;
		}

		result.textContent = message;
		result.className = 'lp-license-form__result is-' + tone;
		result.hidden = false;
	}

	function activate( form ) {
		var field = LocalizePilot.find( '[data-lp-license-key]', form );
		var value = field ? field.value.trim() : '';

		if ( '' === value ) {
			say( form, text( 'licenseEmpty', 'Enter your license key first.' ), 'warning' );

			if ( field ) {
				field.focus();
			}

			return;
		}

		if ( ! KEY_PATTERN.test( value ) ) {
			say(
				form,
				text( 'licenseFormat', 'That does not look like a license key. The format is XXXX-XXXX-XXXX-XXXX-XXXX.' ),
				'warning'
			);

			if ( field ) {
				field.focus();
				field.select();
			}

			return;
		}

		/*
		 * The key is well formed, and that is as far as this build can go.
		 * Saying so is more useful than a spinner that never resolves.
		 */
		say(
			form,
			text(
				'licenseNotConnected',
				'Key accepted. License activation is not connected in this build, so nothing was stored or verified.'
			),
			'info'
		);
	}

	LocalizePilot.ui.register( 'license', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-license-form]', scope ).forEach( function ( form ) {
			if ( form.__lpLicenseBound ) {
				return;
			}

			form.__lpLicenseBound = true;

			form.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-lp-license-activate]' ) ) {
					event.preventDefault();
					activate( form );
				}
			} );

			form.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key && event.target.closest( '[data-lp-license-key]' ) ) {
					event.preventDefault();
					activate( form );
				}
			} );

			// A fresh attempt should not sit under the previous verdict.
			form.addEventListener( 'input', function ( event ) {
				if ( ! event.target.closest( '[data-lp-license-key]' ) ) {
					return;
				}

				var result = LocalizePilot.find( '[data-lp-license-result]', form );

				if ( result ) {
					result.hidden = true;
				}
			} );
		} );

		LocalizePilot.findAll( '[data-lp-license-check]', scope ).forEach( function ( button ) {
			if ( button.__lpLicenseBound ) {
				return;
			}

			button.__lpLicenseBound = true;

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				var form = LocalizePilot.find( '[data-lp-license-form]', document );

				if ( form ) {
					say(
						form,
						text(
							'licenseCheck',
							'No license is stored for this site, and status checks are not connected in this build.'
						),
						'info'
					);
				}
			} );
		} );
	} );
}( window ) );
