/**
 * Providers screen: selection, secret reveal, and the existing API test.
 */
( function ( window, document ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	function selectedProvider( form ) {
		var selected = LocalizePilot.find(
			'input[name="next_translate_settings[translation_provider]"]:checked',
			form
		);

		return selected ? selected.value : 'translatex';
	}

	function syncProvider( form ) {
		var provider = selectedProvider( form );

		LocalizePilot.findAll( '[data-lp-provider-choice]', form ).forEach( function ( choice ) {
			var input = LocalizePilot.find( 'input[type="radio"]', choice );
			choice.classList.toggle( 'is-selected', !! input && input.checked );
		} );

		LocalizePilot.findAll( '[data-lp-provider-panel]', form ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-lp-provider-panel' ) !== provider;
		} );

		var fallback = LocalizePilot.find(
			'select[name="next_translate_settings[fallback_provider]"]',
			form
		);

		if ( fallback ) {
			LocalizePilot.findAll( 'option', fallback ).forEach( function ( option ) {
				option.disabled = option.value === provider;
			} );

			if ( fallback.value === provider ) {
				fallback.value = '';
			}
		}
	}

	function testProvider( form, button ) {
		var provider = selectedProvider( form );
		var panel = LocalizePilot.find( '[data-lp-provider-panel="' + provider + '"]', form );
		var result = LocalizePilot.find( '[data-lp-provider-test-result]', form );
		var language = LocalizePilot.find( '[data-lp-provider-test-language]', form );
		var key = panel ? LocalizePilot.find( '[data-lp-provider-key]', panel ) : null;
		var model = panel ? LocalizePilot.find( '[data-lp-provider-model]', panel ) : null;
		var body = new FormData();

		body.append( 'action', 'next_translate_test_api' );
		body.append( 'nonce', LocalizePilot.settings.providerNonce || '' );
		body.append( 'language', language ? language.value : 'es' );
		body.append( 'provider', provider );
		body.append( 'api_key', key ? key.value : '' );
		body.append( 'model', model ? model.value : '' );

		[
			[ 'style', 'ai_translation_style', 'natural' ],
			[ 'instructions', 'ai_custom_instructions', '' ],
			[ 'temperature', 'ai_temperature', '0.2' ],
			[ 'max_tokens', 'ai_max_output_tokens', '8192' ],
		].forEach( function ( field ) {
			var input = LocalizePilot.find(
				'[name="next_translate_settings[' + field[ 1 ] + ']"]',
				form
			);
			body.append( field[ 0 ], input ? input.value : field[ 2 ] );
		} );

		button.disabled = true;
		button.textContent = LocalizePilot.text( 'testing', 'Testing…' );

		if ( result ) {
			result.textContent = '';
			result.className = 'lp-provider-test__result';
		}

		window.fetch( LocalizePilot.settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( ! result ) {
					return;
				}

				result.textContent = response.data && response.data.message
					? response.data.message
					: LocalizePilot.text( 'unknownResponse', 'Unknown response' );
				result.classList.add( response.success ? 'is-success' : 'is-error' );
			} )
			.catch( function ( error ) {
				if ( result ) {
					result.textContent = error.message;
					result.classList.add( 'is-error' );
				}
			} )
			.finally( function () {
				button.disabled = false;
				button.textContent = LocalizePilot.text( 'testConnection', 'Test connection' );
			} );
	}

	LocalizePilot.ui.register( 'providers', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-provider-form]', scope ).forEach( function ( form ) {
			if ( form.__lpProvidersBound ) {
				syncProvider( form );
				return;
			}

			form.__lpProvidersBound = true;

			form.addEventListener( 'change', function ( event ) {
				if ( event.target.matches( 'input[name="next_translate_settings[translation_provider]"]' ) ) {
					syncProvider( form );
				}
			} );

			form.addEventListener( 'click', function ( event ) {
				var reveal = event.target.closest( '[data-lp-reveal]' );

				if ( reveal ) {
					event.preventDefault();

					var input = LocalizePilot.find( '[data-lp-provider-key]', reveal.parentNode );
					var show = input && input.type === 'password';

					if ( input ) {
						input.type = show ? 'text' : 'password';
						reveal.textContent = LocalizePilot.text( show ? 'hide' : 'show', show ? 'Hide' : 'Show' );
					}
					return;
				}

				var test = event.target.closest( '[data-lp-provider-test]' );

				if ( test ) {
					event.preventDefault();
					testProvider( form, test );
				}
			} );

			syncProvider( form );
		} );
	} );
} )( window, document );
