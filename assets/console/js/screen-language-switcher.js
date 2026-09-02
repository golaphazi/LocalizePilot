/**
 * Language Switcher screen: immediate preview of the saved form controls.
 */
( function ( window ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	function sync( form ) {
		var style = LocalizePilot.find( '[data-lp-switcher-style]:checked', form );
		var position = LocalizePilot.find( '#lp-switcher-position', form );
		var labels = LocalizePilot.find( '#lp-switcher-labels', form );
		var preview = LocalizePilot.find( '[data-lp-switcher-preview]', form );
		var stage = LocalizePilot.find( '[data-lp-switcher-stage]', form );

		LocalizePilot.findAll( '.lp-choice-card', form ).forEach( function ( card ) {
			var input = LocalizePilot.find( 'input[type="radio"]', card );
			card.classList.toggle( 'is-selected', !! input && input.checked );
		} );

		if ( preview ) {
			preview.classList.toggle( 'is-inline', !! style && style.value === 'inline' );
			preview.classList.toggle( 'is-code-labels', !! labels && labels.value === 'code' );

			LocalizePilot.findAll( '[data-lp-switcher-language]', preview ).forEach( function ( language ) {
				var text = LocalizePilot.find( 'b', language );
				var format = labels ? labels.value : 'native';

				if ( text ) {
					text.textContent = language.getAttribute(
						format === 'code' ? 'data-code' : ( format === 'english' ? 'data-english' : 'data-native' )
					);
				}
			} );
		}

		if ( stage ) {
			stage.classList.remove( 'is-start', 'is-center', 'is-end' );
			stage.classList.add( 'is-' + ( position ? position.value : 'end' ) );
		}
	}

	LocalizePilot.ui.register( 'language-switcher', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-switcher-form]', scope ).forEach( function ( form ) {
			if ( ! form.__lpSwitcherBound ) {
				form.__lpSwitcherBound = true;
				form.addEventListener( 'change', function () {
					sync( form );
				} );
			}

			sync( form );
		} );
	} );
} )( window );
