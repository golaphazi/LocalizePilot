/** Performance screen presentational metric switch. */
( function ( window ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	function selectMetric( button ) {
		var tabs = button.closest( '[data-lp-performance-tabs]' );
		var screen = button.closest( '.lp-screen-body' );
		var overview = LocalizePilot.find( '[data-lp-performance-overview]', screen );
		var summary = LocalizePilot.find( '[data-lp-performance-summary]', screen );

		LocalizePilot.findAll( '[data-lp-performance-metric]', tabs ).forEach( function ( item ) {
			var active = item === button;
			item.classList.toggle( 'is-active', active );
			item.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );

		if ( overview ) {
			var title = LocalizePilot.find( '.lp-empty__title', overview );
			var message = LocalizePilot.find( '.lp-empty__message', overview );

			if ( title ) {
				title.textContent = button.getAttribute( 'data-title' ) || '';
			}

			if ( message ) {
				message.textContent = button.getAttribute( 'data-message' ) || '';
			}
		}

		if ( summary ) {
			LocalizePilot.findAll( '.lp-metric__value', summary ).forEach( function ( value ) {
				value.textContent = '— ' + ( button.getAttribute( 'data-unit' ) || '' );
			} );
		}
	}

	LocalizePilot.ui.register( 'performance-tabs', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-performance-tabs]', scope ).forEach( function ( tabs ) {
			if ( tabs.__lpPerformanceBound ) {
				return;
			}

			tabs.__lpPerformanceBound = true;
			tabs.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-lp-performance-metric]' );

				if ( button ) {
					selectMetric( button );
				}
			} );

			// Keep the initial chart copy and units aligned with the server-
			// selected tab. Without this, "Cache hit rate" could be active while
			// the summary still displayed render-time units until the first click.
			selectMetric(
				LocalizePilot.find( '[data-lp-performance-metric].is-active', tabs )
					|| LocalizePilot.find( '[data-lp-performance-metric]', tabs )
			);
		} );
	} );
} )( window );
