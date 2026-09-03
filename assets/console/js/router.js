/**
 * LocalizePilot console — client-side navigation.
 *
 * Moving between console screens swaps a server-rendered fragment into #lp-view
 * instead of reloading the admin page, so the sidebar, top bar and the
 * WordPress chrome around them stay put and navigation is instant.
 *
 * This is progressive enhancement, not a single-page framework: every nav item
 * is a real link to a real admin URL. With JavaScript off, or if a request
 * fails, the browser follows it normally and the same screen renders from the
 * same template.
 */
( function ( window, document ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! window.history || ! window.history.pushState ) {
		return;
	}

	var settings = LocalizePilot.settings || {};
	var screens = settings.screens || {};
	var loaded = {};
	var pending = null;

	/**
	 * The console screen a URL points at, or an empty string if it points
	 * somewhere else entirely.
	 *
	 * @param {string} href Absolute or relative URL.
	 * @return {string} Screen slug.
	 */
	function slugFor( href ) {
		var url;

		try {
			url = new URL( href, window.location.origin );
		} catch ( error ) {
			return '';
		}

		if ( url.origin !== window.location.origin ) {
			return '';
		}

		var page = url.searchParams.get( 'page' );

		if ( ! page ) {
			return '';
		}

		var match = '';

		Object.keys( screens ).forEach( function ( slug ) {
			if ( screens[ slug ].menuSlug === page ) {
				match = slug;
			}
		} );

		return match;
	}

	function view() {
		return document.getElementById( 'lp-view' );
	}

	/**
	 * Tell a screen reader which screen it is now on.
	 *
	 * The view itself is not a live region: wrapping one around the whole
	 * screen makes assistive tech read every word of it after each swap. This
	 * announces one sentence instead, and only after the fragment has landed.
	 *
	 * @param {string} title Screen name.
	 */
	function announce( title ) {
		var region = document.getElementById( 'lp-announcer' );

		if ( ! region || ! title ) {
			return;
		}

		var message = ( settings.i18n && settings.i18n.screenLoaded )
			? settings.i18n.screenLoaded.replace( '%s', title )
			: title;

		// A live region only speaks when its text changes, and navigating back
		// to the same screen should still be announced.
		region.textContent = '';

		window.setTimeout( function () {
			region.textContent = message;
		}, 60 );
	}

	function setBusy( busy ) {
		var region = view();

		if ( ! region ) {
			return;
		}

		region.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		region.classList.toggle( 'is-loading', busy );

		var app = LocalizePilot.root;

		if ( app ) {
			app.classList.toggle( 'is-navigating', busy );
		}
	}

	/**
	 * Move the sidebar highlight, breadcrumb and document title onto the screen
	 * that is now showing.
	 *
	 * @param {Object} payload Response payload from the navigation endpoint.
	 */
	function syncChrome( payload ) {
		/*
		 * The shell advertises which screen it is showing. Nothing styles off it
		 * today, but leaving it on the screen you arrived from makes it a trap
		 * for the first rule that does.
		 */
		var app = LocalizePilot.find( '.lp-app' );

		if ( app && payload.slug ) {
			app.setAttribute( 'data-lp-screen', payload.slug );
		}

		LocalizePilot.findAll( '.lp-nav__item' ).forEach( function ( link ) {
			var active = slugFor( link.getAttribute( 'href' ) || '' ) === payload.slug;

			link.classList.toggle( 'is-active', active );

			if ( active ) {
				link.setAttribute( 'aria-current', 'page' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );

		var crumb = LocalizePilot.find( '.lp-breadcrumb__current' );

		if ( crumb ) {
			crumb.textContent = payload.title;
		}

		if ( payload.documentTitle ) {
			document.title = payload.documentTitle;
		}

		syncAdminMenu( payload.menuSlug );
	}

	/**
	 * Keep the WordPress admin menu's own highlight in step, since it was
	 * rendered for the screen we navigated away from.
	 *
	 * @param {string} menuSlug WordPress page slug now showing.
	 */
	function syncAdminMenu( menuSlug ) {
		var menu = document.getElementById( 'adminmenu' );

		if ( ! menu || ! menuSlug ) {
			return;
		}

		var parent = menu.querySelector( '#toplevel_page_' + ( settings.parentSlug || '' ) );

		if ( ! parent ) {
			return;
		}

		LocalizePilot.findAll( 'li', parent ).forEach( function ( item ) {
			item.classList.remove( 'current' );
		} );

		LocalizePilot.findAll( 'a', parent ).forEach( function ( link ) {
			var href = link.getAttribute( 'href' ) || '';
			var match = href.indexOf( 'page=' + menuSlug ) !== -1
				&& href.indexOf( 'page=' + menuSlug + '-' ) === -1;

			link.classList.toggle( 'current', match );

			if ( match && link.parentNode && link.parentNode.classList ) {
				link.parentNode.classList.add( 'current' );
			}
		} );
	}

	/**
	 * Pull in a screen's own script the first time that screen is visited.
	 *
	 * @param {string} src Script URL, or empty.
	 * @return {Promise} Resolves once the script has run.
	 */
	function loadScreenScript( src ) {
		if ( ! src || loaded[ src ] ) {
			return Promise.resolve();
		}

		loaded[ src ] = true;

		return new Promise( function ( resolve ) {
			var script = document.createElement( 'script' );

			script.src = src;
			script.onload = resolve;
			script.onerror = resolve;
			document.body.appendChild( script );
		} );
	}

	/**
	 * Navigate to a console screen.
	 *
	 * @param {string}  slug             Screen slug.
	 * @param {boolean} replace          Replace the history entry instead of pushing one.
	 * @param {boolean} silent           Skip the history update entirely (popstate).
	 * @param {string}  href             Full filtered/deep-linked target URL.
	 * @param {boolean} preservePosition Keep focus and scroll while refreshing a form.
	 */
	function go( slug, replace, silent, href, preservePosition ) {
		var region = view();
		var target = screens[ slug ];
		var targetUrl;

		if ( ! region || ! target ) {
			return Promise.reject( new Error( 'invalid navigation target' ) );
		}

		try {
			targetUrl = new URL( href || target.url, window.location.origin );
		} catch ( error ) {
			return Promise.reject( error );
		}

		if ( pending ) {
			pending.aborted = true;
		}

		var request = { aborted: false };
		pending = request;
		var previousScroll = window.scrollY;

		if ( LocalizePilot.ui && LocalizePilot.ui.closeMenus ) {
			LocalizePilot.ui.closeMenus( null, false );
		}

		setBusy( true );

		return LocalizePilot.request( 'screen', { screen: slug, query: targetUrl.search } )
			.then( function ( response ) {
				if ( request.aborted ) {
					return;
				}

				if ( ! response || ! response.success || ! response.data ) {
					throw new Error( 'navigation failed' );
				}

				var payload = response.data;
				payload.url = targetUrl.href;

				region.innerHTML = payload.html;
				syncChrome( payload );

				// Shared controls (search, filters, menus, settings forms) do not
				// depend on a screen bundle. Bind them as soon as the fragment is
				// visible so a fast first interaction cannot land while an optional
				// screen script is still loading. The second idempotent init below
				// picks up behaviours registered by that screen script.
				LocalizePilot.ui.init( region );

				if ( ! silent ) {
					window.history[ replace ? 'replaceState' : 'pushState' ](
						{ lpScreen: payload.slug },
						'',
						payload.url
					);
				}

				settings.screen = payload.slug;

				return loadScreenScript( payload.script ).then( function () {
					LocalizePilot.ui.init( region );
					announce( payload.title );
					document.dispatchEvent(
						new CustomEvent( 'localizepilot:navigated', { detail: payload } )
					);

					// Send the reader to the top of the new screen, and the
					// keyboard with it.
					if ( preservePosition ) {
						window.scrollTo( { top: previousScroll, behavior: 'auto' } );
					} else {
						var main = document.getElementById( 'lp-main' );

						if ( main ) {
							main.focus( { preventScroll: true } );
						}

						window.scrollTo( { top: 0, behavior: 'auto' } );
					}
				} );
			} )
			.catch( function () {
				if ( request.aborted ) {
					return;
				}

				// A failed swap must never leave a half-navigated console.
				window.location.href = targetUrl.href;
			} )
			.finally( function () {
				if ( ! request.aborted ) {
					pending = null;
					setBusy( false );
				}
			} );
	}

	function navigate( href, options ) {
		var slug = slugFor( href );
		var opts = options || {};

		if ( ! slug || ! screens[ slug ] ) {
			return false;
		}

		go( slug, !! opts.replace, !! opts.silent, href, !! opts.preservePosition );

		return true;
	}

	LocalizePilot.router = {
		navigate: navigate,
		refresh: function ( options ) {
			var opts = options || {};
			opts.replace = true;
			opts.preservePosition = opts.preservePosition !== false;

			return navigate( window.location.href, opts );
		},
	};

	/**
	 * Ordinary modified clicks — new tab, new window, download — belong to the
	 * browser, not to us.
	 *
	 * @param {MouseEvent} event Click event.
	 * @return {boolean} True when the browser should handle it.
	 */
	function isPlainClick( event ) {
		return ! (
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		);
	}

	document.addEventListener( 'localizepilot:ready', function () {
		if ( ! LocalizePilot.root || ! view() ) {
			return;
		}

		window.history.replaceState(
			{ lpScreen: settings.screen },
			'',
			window.location.href
		);

		LocalizePilot.on( 'click', 'a[href]', function ( event, link ) {
			if ( ! isPlainClick( event ) || link.target === '_blank' || link.hasAttribute( 'download' ) ) {
				return;
			}

			// Only links inside the console navigate this way; anything in the
			// WordPress chrome is a normal page load.
			if ( ! LocalizePilot.root.contains( link ) ) {
				return;
			}

			var href = link.getAttribute( 'href' ) || '';
			var slug = slugFor( href );

			if ( ! slug ) {
				return;
			}

			var linkUrl = new URL( href, window.location.origin );
			var currentUrl = new URL( window.location.href );

			// Same-screen anchors belong to the browser's native scrolling and
			// focus behaviour; no fragment request is needed.
			if (
				linkUrl.hash &&
				linkUrl.pathname === currentUrl.pathname &&
				linkUrl.search === currentUrl.search
			) {
				return;
			}

			event.preventDefault();

			if ( linkUrl.href === window.location.href ) {
				return;
			}

			go( slug, false, false, href, false );
		} );

		window.addEventListener( 'popstate', function ( event ) {
			var slug = ( event.state && event.state.lpScreen ) || slugFor( window.location.href );

			if ( slug && screens[ slug ] ) {
				go( slug, false, true, window.location.href, false );
			}
		} );
	} );
} )( window, document );
