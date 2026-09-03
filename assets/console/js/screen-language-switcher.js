/**
 * Language Switcher screen: keep every live control and preview in sync.
 */
( function ( window ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	function inputFor( form, key, checked ) {
		return LocalizePilot.find( '[name$="[' + key + ']"]' + ( checked ? ':checked' : '' ), form );
	}

	function labelsFor( preview ) {
		try {
			return JSON.parse( preview.getAttribute( 'data-lp-switcher-labels' ) || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function syncPreview( form, style, position, format ) {
		var preview = LocalizePilot.find( '[data-lp-switcher-preview]', form );

		if ( ! preview ) {
			return;
		}

		var switcher = LocalizePilot.find( '.localizepilot-language-switcher', preview );
		var labels = labelsFor( preview );

		if ( switcher ) {
			switcher.classList.remove( 'is-dropdown', 'is-inline', 'is-start', 'is-center', 'is-end' );
			switcher.classList.add( 'is-' + style, 'is-' + position );

			LocalizePilot.findAll( '.next-translate-link[lang]', switcher ).forEach( function ( link ) {
				var code = ( link.getAttribute( 'lang' ) || '' ).toLowerCase();
				var values = labels[ code ] || {};

				if ( values[ format ] ) {
					link.textContent = values[ format ];
				}
			} );

			var current = LocalizePilot.find( '.next-translate-link[aria-current="page"]', switcher );
			var summary = LocalizePilot.find( 'summary', switcher );

			if ( current && summary ) {
				summary.textContent = current.textContent;
			}
		}

		var frame = LocalizePilot.find( '[data-lp-switcher-frame]', preview );
		var note = LocalizePilot.find( '[data-lp-switcher-preview-note]', preview );

		if ( note ) {
			var device = frame && frame.classList.contains( 'is-mobile' ) ? 'Mobile' : 'Desktop';
			var count = Number( note.getAttribute( 'data-count' ) || 0 );
			note.textContent = device + ' preview · ' + count + ' ' + ( count === 1 ? 'language' : 'languages' );
		}
	}

	function sync( form ) {
		var styleInput = inputFor( form, 'menu_style', true );
		var positionInput = inputFor( form, 'menu_position', true );
		var labelsInput = inputFor( form, 'language_label', false );
		var headerInput = inputFor( form, 'header_switcher', false );
		var style = styleInput ? styleInput.value : 'dropdown';
		var position = positionInput ? positionInput.value : 'end';
		var format = labelsInput ? labelsInput.value : 'native';

		LocalizePilot.findAll( '.lp-segmented__option', form ).forEach( function ( option ) {
			var input = LocalizePilot.find( 'input[type="radio"]', option );

			if ( input ) {
				option.classList.toggle( 'is-active', input.checked );
			}
		} );

		LocalizePilot.findAll( '[data-lp-switcher-placement]', form ).forEach( function ( placement ) {
			var key = placement.getAttribute( 'data-lp-switcher-placement' );
			var active = key === 'header'
				? !! headerInput && headerInput.checked
				: key === 'shortcode' && ( ! headerInput || ! headerInput.checked );

			placement.classList.toggle( 'is-active', active );
			placement.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );

		syncPreview( form, style, position, format );
	}

	LocalizePilot.ui.register( 'language-switcher', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-switcher-form]', scope ).forEach( function ( form ) {
			if ( ! form.__lpSwitcherBound ) {
				form.__lpSwitcherBound = true;

				form.addEventListener( 'change', function () {
					sync( form );
				} );

				form.addEventListener( 'click', function ( event ) {
					var device = event.target.closest( '[data-lp-preview-device]' );

					if ( device ) {
						var preview = device.closest( '[data-lp-switcher-preview]' );
						var frame = LocalizePilot.find( '[data-lp-switcher-frame]', preview );
						var mobile = device.getAttribute( 'data-lp-preview-device' ) === 'mobile';

						LocalizePilot.findAll( '[data-lp-preview-device]', preview ).forEach( function ( button ) {
							var active = button === device;
							button.classList.toggle( 'is-active', active );
							button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
						} );

						if ( frame ) {
							frame.classList.toggle( 'is-mobile', mobile );
						}

						sync( form );
						return;
					}

					var placement = event.target.closest( '[data-lp-switcher-placement]' );

					if ( placement && ! placement.closest( '[data-lp-preview]' ) ) {
						var header = inputFor( form, 'header_switcher', false );
						var key = placement.getAttribute( 'data-lp-switcher-placement' );

						if ( header && ( key === 'header' || key === 'shortcode' ) ) {
							header.checked = key === 'header';
							header.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						}
					}
				} );
			}

			sync( form );
		} );
	} );
} )( window );
