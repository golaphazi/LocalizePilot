/**
 * LocalizePilot console — core.
 *
 * Namespace, DOM helpers, a fetch wrapper that carries the nonce, and
 * formatters. No dependencies, no build step.
 *
 * Everything registers onto window.LocalizePilot; nothing else leaks.
 */
( function ( window, document ) {
	'use strict';

	var settings = window.localizePilotConsole || {};

	var LocalizePilot = {
		settings: settings,

		/** Root element of the console app, or null off-console. */
		root: null,

		/**
		 * First match within a scope.
		 *
		 * @param {string}       selector CSS selector.
		 * @param {Element|null} scope    Optional scope, defaults to document.
		 * @return {Element|null} The element, or null.
		 */
		find: function ( selector, scope ) {
			return ( scope || document ).querySelector( selector );
		},

		/**
		 * All matches within a scope, as a real array.
		 *
		 * @param {string}       selector CSS selector.
		 * @param {Element|null} scope    Optional scope, defaults to document.
		 * @return {Element[]} Matching elements.
		 */
		findAll: function ( selector, scope ) {
			return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
		},

		/**
		 * Delegate an event from the app root.
		 *
		 * Listeners live on the root rather than on individual nodes, so
		 * server-rendered fragments swapped into the page need no re-binding.
		 *
		 * @param {string}   type     Event name.
		 * @param {string}   selector Selector the target must match or be inside.
		 * @param {Function} handler  Called with ( event, matchedElement ).
		 */
		on: function ( type, selector, handler ) {
			document.addEventListener(
				type,
				function ( event ) {
					var target = event.target;

					if ( ! target || ! target.closest ) {
						return;
					}

					var match = target.closest( selector );

					if ( match && document.contains( match ) ) {
						handler( event, match );
					}
				},
				type === 'focus' || type === 'blur'
			);
		},

		/**
		 * POST to admin-ajax with the console nonce attached.
		 *
		 * @param {string} action WordPress AJAX action, without the prefix.
		 * @param {Object} data   Payload.
		 * @return {Promise<Object>} Parsed JSON response.
		 */
		request: function ( action, data ) {
			var body = new FormData();

			body.append( 'action', 'localizepilot_' + action );
			body.append( 'nonce', settings.nonce || '' );

			Object.keys( data || {} ).forEach( function ( key ) {
				body.append( key, data[ key ] );
			} );

			return window
				.fetch( settings.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				} )
				.then( function ( response ) {
					return response.json();
				} );
		},

		/**
		 * Localized integer formatting, matching the design's thousands
		 * separators.
		 *
		 * @param {number} value Number to format.
		 * @return {string} Formatted number.
		 */
		formatNumber: function ( value ) {
			var number = Number( value );

			if ( isNaN( number ) ) {
				return String( value );
			}

			try {
				return number.toLocaleString();
			} catch ( error ) {
				return String( number );
			}
		},

		/**
		 * A translated string from the localized payload.
		 *
		 * @param {string} key      Key in the i18n map.
		 * @param {string} fallback Used when the key is absent.
		 * @return {string} The string.
		 */
		text: function ( key, fallback ) {
			return ( settings.i18n && settings.i18n[ key ] ) || fallback || '';
		},
	};

	function boot() {
		LocalizePilot.root = LocalizePilot.find( '.lp-app' );
		document.dispatchEvent( new CustomEvent( 'localizepilot:ready' ) );
	}

	window.LocalizePilot = LocalizePilot;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )( window, document );
