/**
 * LocalizePilot console — shared UI behaviours.
 *
 * Behaviours attach through data-lp-* attributes rather than CSS classes, so
 * styling and behaviour stay independently changeable. Every behaviour has an
 * idempotent init, so a server-rendered fragment swapped into the page can be
 * re-initialised without double-binding.
 */
( function ( window, document ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot ) {
		return;
	}

	var behaviours = {};

	var ui = {
		/**
		 * Register a behaviour.
		 *
		 * @param {string}   name    Unique behaviour name.
		 * @param {Function} factory Called with a scope element on every init.
		 */
		register: function ( name, factory ) {
			behaviours[ name ] = factory;
		},

		/**
		 * Run every registered behaviour over a scope.
		 *
		 * Call this after swapping a fragment into the page.
		 *
		 * @param {Element|null} scope Defaults to the console root.
		 */
		init: function ( scope ) {
			var root = scope || LocalizePilot.root || document;

			Object.keys( behaviours ).forEach( function ( name ) {
				try {
					behaviours[ name ]( root );
				} catch ( error ) {
					// A single broken behaviour must not take the console down.
					if ( window.console && window.console.error ) {
						window.console.error( '[LocalizePilot] behaviour "' + name + '" failed', error );
					}
				}
			} );
		},
	};

	/**
	 * Controls behind Preview render fully but do nothing. Swallow their
	 * activation so a click never looks like it worked, and never submits a
	 * form by accident.
	 */
	function guardPreviewControls() {
		function swallow( event ) {
			var target = event.target;

			if ( target && target.closest && target.closest( '[data-lp-preview]' ) ) {
				event.preventDefault();
				event.stopPropagation();
			}
		}

		document.addEventListener( 'click', swallow, true );
		document.addEventListener( 'submit', swallow, true );
	}

	/* ---------------------------------------------------------------------
	 * Table selection and the bulk action bar
	 * ------------------------------------------------------------------ */

	ui.register( 'table-select', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-selectable]', scope ).forEach( function ( table ) {
			var card = table.closest( '.lp-card' ) || scope;
			var bar = LocalizePilot.find( '[data-lp-bulk-bar]', card );
			var all = LocalizePilot.find( '[data-lp-select-all]', table );

			function rows() {
				return LocalizePilot.findAll( '[data-lp-select-row]', table );
			}

			function sync() {
				var boxes = rows();
				var checked = boxes.filter( function ( box ) {
					return box.checked;
				} );

				if ( all ) {
					all.checked = boxes.length > 0 && checked.length === boxes.length;
					all.indeterminate = checked.length > 0 && checked.length < boxes.length;
				}

				if ( ! bar ) {
					return;
				}

				bar.hidden = checked.length === 0;

				var count = LocalizePilot.find( '[data-lp-bulk-count]', bar );

				if ( count ) {
					// The translated "%s selected" string comes from the server, so
					// the count stays correct in every locale and word order.
					var template = count.getAttribute( 'data-lp-count-template' ) || '%s';
					count.textContent = template.replace(
						'%s',
						LocalizePilot.formatNumber( checked.length )
					);
				}
			}

			// One listener per table, replacing any left by a previous init.
			if ( table.__lpSelectBound ) {
				sync();
				return;
			}

			table.__lpSelectBound = true;

			table.addEventListener( 'change', function ( event ) {
				var target = event.target;

				if ( target === all ) {
					rows().forEach( function ( box ) {
						box.checked = all.checked;
					} );
				}

				if ( target === all || target.matches( '[data-lp-select-row]' ) ) {
					sync();
				}
			} );

			if ( bar && ! bar.__lpClearBound ) {
				bar.__lpClearBound = true;

				var clear = LocalizePilot.find( '[data-lp-bulk-clear]', bar );

				if ( clear ) {
					clear.addEventListener( 'click', function () {
						rows().forEach( function ( box ) {
							box.checked = false;
						} );
						sync();
					} );
				}
			}

			sync();
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Filter toolbars
	 * ------------------------------------------------------------------ */

	/**
	 * Apply a filter as soon as it changes.
	 *
	 * The toolbar is a real GET form and stays one with JavaScript off — the
	 * submit button is there, just visually hidden. This only removes the
	 * second step for people who can see the change take effect.
	 */
	ui.register( 'toolbar-autosubmit', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-autosubmit]', scope ).forEach( function ( form ) {
			if ( form.__lpAutoSubmitBound ) {
				return;
			}

			form.__lpAutoSubmitBound = true;

			form.addEventListener( 'change', function ( event ) {
				var target = event.target;

				if ( ! target.matches( 'select, input[type="checkbox"], input[type="radio"]' ) ) {
					return;
				}

				if ( target.closest( '[data-lp-preview]' ) ) {
					return;
				}

				// Paging is relative to the previous filter set, so a new one
				// starts at the first page.
				var paged = form.querySelector( 'input[name="paged"]' );

				if ( paged ) {
					paged.value = '1';
				}

				form.submit();
			} );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Overlay body scroll
	 *
	 * Two things can cover the page — the drawer and the phone sidebar — so
	 * the lock is counted rather than toggled, or closing one would release
	 * the page while the other is still open.
	 * ------------------------------------------------------------------ */

	var scrollLocks = 0;
	var scrollTop = 0;

	function lockScroll() {
		scrollLocks++;

		if ( scrollLocks > 1 ) {
			return;
		}

		scrollTop = window.scrollY;
		document.body.style.overflow = 'hidden';
	}

	function releaseScroll() {
		scrollLocks = Math.max( 0, scrollLocks - 1 );

		if ( scrollLocks > 0 ) {
			return;
		}

		document.body.style.overflow = '';
		window.scrollTo( { top: scrollTop, behavior: 'auto' } );
	}

	/* ---------------------------------------------------------------------
	 * Off-canvas sidebar
	 *
	 * Only reachable below 782px, where the rail becomes an overlay. Above it
	 * the toggle is hidden and none of this runs.
	 * ------------------------------------------------------------------ */

	var navReturnFocus = null;

	function navToggle() {
		return LocalizePilot.find( '[data-lp-nav-toggle]' );
	}

	function navIsOpen() {
		return !! ( LocalizePilot.root && LocalizePilot.root.classList.contains( 'is-nav-open' ) );
	}

	function openNav( trigger ) {
		var sidebar = document.getElementById( 'lp-sidebar' );

		if ( ! LocalizePilot.root || ! sidebar || navIsOpen() ) {
			return;
		}

		navReturnFocus = trigger || navToggle();
		LocalizePilot.root.classList.add( 'is-nav-open' );
		lockScroll();

		var toggle = navToggle();

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'true' );
		}

		var first = LocalizePilot.find( '.lp-nav__item', sidebar );

		if ( first ) {
			first.focus();
		}
	}

	function closeNav() {
		if ( ! navIsOpen() ) {
			return;
		}

		LocalizePilot.root.classList.remove( 'is-nav-open' );
		releaseScroll();

		var toggle = navToggle();

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		/*
		 * The toggle only exists below 782px. If the viewport grew while the
		 * overlay was open it is display:none by now and cannot take focus, so
		 * the content region catches it rather than the document body.
		 */
		if ( navReturnFocus && navReturnFocus.offsetParent !== null ) {
			navReturnFocus.focus();
		} else {
			var main = document.getElementById( 'lp-main' );

			if ( main ) {
				main.focus( { preventScroll: true } );
			}
		}

		navReturnFocus = null;
	}

	/* ---------------------------------------------------------------------
	 * Overflow menus
	 * ------------------------------------------------------------------ */

	function closeMenus( except, restoreFocus ) {
		LocalizePilot.findAll( '[data-lp-menu]' ).forEach( function ( menu ) {
			if ( menu === except ) {
				return;
			}

			var list = LocalizePilot.find( '[data-lp-menu-list]', menu );
			var trigger = LocalizePilot.find( '[data-lp-menu-trigger]', menu );

			if ( ! list || list.hidden ) {
				return;
			}

			list.hidden = true;

			if ( trigger ) {
				trigger.setAttribute( 'aria-expanded', 'false' );

				// Closing with a key must not drop focus to the document, or
				// the next Tab starts over at the top of the page.
				if ( restoreFocus && menu.contains( document.activeElement ) ) {
					trigger.focus();
				}
			}
		} );
	}

	/**
	 * The items of one open menu, in the order they are read.
	 *
	 * @param {Element} menu The menu wrapper.
	 * @return {Element[]} Menu items.
	 */
	function menuItems( menu ) {
		return LocalizePilot.findAll( '[role="menuitem"]', menu );
	}

	/**
	 * Move focus within an open menu.
	 *
	 * Menu items are removed from the tab order and driven by the arrow keys,
	 * which is what role="menu" promises a screen reader user.
	 *
	 * @param {Element} menu  The menu wrapper.
	 * @param {number}  step  -1 for previous, 1 for next.
	 * @param {boolean} jump  Go straight to the end the step points at: down
	 *                        and Home mean the first item, up and End the last.
	 */
	function moveMenuFocus( menu, step, jump ) {
		var items = menuItems( menu );

		if ( ! items.length ) {
			return;
		}

		if ( jump ) {
			items[ step > 0 ? 0 : items.length - 1 ].focus();
			return;
		}

		var index = items.indexOf( document.activeElement );

		if ( index === -1 ) {
			items[ step > 0 ? 0 : items.length - 1 ].focus();
			return;
		}

		// Wrapping matches the platform behaviour of a native menu.
		items[ ( index + step + items.length ) % items.length ].focus();
	}

	function openMenu( menu, focusFirst ) {
		var list = LocalizePilot.find( '[data-lp-menu-list]', menu );
		var trigger = LocalizePilot.find( '[data-lp-menu-trigger]', menu );

		if ( ! list ) {
			return;
		}

		closeMenus( menu, false );
		list.hidden = false;

		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'true' );
		}

		if ( focusFirst ) {
			moveMenuFocus( menu, 1, true );
		}
	}

	ui.register( 'menus', function () {
		// Delegated once from the document; nothing to bind per fragment.
		return;
	} );

	/* ---------------------------------------------------------------------
	 * Drawer
	 * ------------------------------------------------------------------ */

	var drawerReturnFocus = null;

	function openDrawer( drawer, trigger ) {
		if ( ! drawer ) {
			return;
		}

		drawerReturnFocus = trigger || null;
		drawer.hidden = false;
		lockScroll();

		// The backdrop also carries data-lp-drawer-close but cannot take focus,
		// so aim at the close button itself.
		var close = LocalizePilot.find( '.lp-drawer__close', drawer );

		if ( close && close.focus ) {
			close.focus();
		}
	}

	function closeDrawer( drawer ) {
		if ( ! drawer || drawer.hidden ) {
			return;
		}

		drawer.hidden = true;
		releaseScroll();

		if ( drawerReturnFocus && drawerReturnFocus.focus ) {
			drawerReturnFocus.focus();
		}

		drawerReturnFocus = null;
	}

	/**
	 * Fetch a drawer and open it.
	 *
	 * Drawers are rendered on demand rather than shipped with every table row,
	 * and the markup comes from the same server template either way. A fetch
	 * that fails hands the click back to the link, which loads the same view
	 * with the drawer already open.
	 *
	 * @param {string}  id      Row identifier.
	 * @param {Element} trigger The link that was clicked.
	 */
	function loadDrawer( id, trigger ) {
		var host = LocalizePilot.find( '[data-lp-drawer-host]' );

		if ( ! host || ! id ) {
			return;
		}

		var existing = document.getElementById( 'lp-url-' + id );

		if ( existing ) {
			openDrawer( existing, trigger );
			return;
		}

		trigger.setAttribute( 'aria-busy', 'true' );

		LocalizePilot.request( 'url_drawer', { id: id } )
			.then( function ( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					throw new Error( 'drawer unavailable' );
				}

				host.insertAdjacentHTML( 'beforeend', response.data.html );

				// The panel arrives open, so this is really about the focus
				// move and the return target, which openDrawer owns.
				openDrawer( document.getElementById( 'lp-url-' + id ), trigger );
			} )
			.catch( function () {
				window.location.href = trigger.href;
			} )
			.finally( function () {
				trigger.removeAttribute( 'aria-busy' );
			} );
	}

	/**
	 * Keep Tab inside an open drawer, which is what aria-modal promises.
	 *
	 * @param {KeyboardEvent} event Keydown event.
	 * @param {Element}       panel The drawer panel.
	 */
	function trapFocus( event, panel ) {
		var focusable = LocalizePilot.findAll(
			'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
			panel
		).filter( function ( node ) {
			return node.offsetParent !== null;
		} );

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function bindGlobalHandlers() {
		LocalizePilot.on( 'click', '[data-lp-copy]', function ( event, button ) {
			event.preventDefault();

			var target = LocalizePilot.find( button.getAttribute( 'data-lp-copy' ) || '' );

			if ( ! target ) {
				return;
			}

			var value = target.textContent || '';
			var original = button.getAttribute( 'data-lp-copy-label' ) || button.textContent;

			button.setAttribute( 'data-lp-copy-label', original );

			function complete() {
				button.textContent = LocalizePilot.text( 'copied', 'Copied' );
				button.classList.add( 'is-copied' );

				window.setTimeout( function () {
					button.textContent = original;
					button.classList.remove( 'is-copied' );
				}, 1600 );
			}

			if ( window.navigator.clipboard && window.isSecureContext ) {
				window.navigator.clipboard.writeText( value ).then( complete );
				return;
			}

			var textarea = document.createElement( 'textarea' );
			textarea.value = value;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				document.execCommand( 'copy' );
				complete();
			} catch ( error ) {
				// Clipboard access is optional; leave the visible code in place.
			}

			document.body.removeChild( textarea );
		} );

		LocalizePilot.on( 'click', '[data-lp-confirm]', function ( event, control ) {
			var message = control.getAttribute( 'data-lp-confirm' );

			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		} );

		LocalizePilot.on( 'click', '[data-lp-menu-trigger]', function ( event, trigger ) {
			event.preventDefault();

			var menu = trigger.closest( '[data-lp-menu]' );
			var list = LocalizePilot.find( '[data-lp-menu-list]', menu );

			if ( ! list ) {
				return;
			}

			if ( list.hidden ) {
				openMenu( menu, false );
			} else {
				closeMenus( null, false );
			}
		} );

		// Down or Up on the trigger opens the menu at the matching end.
		LocalizePilot.on( 'keydown', '[data-lp-menu-trigger]', function ( event, trigger ) {
			if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) {
				return;
			}

			event.preventDefault();
			openMenu( trigger.closest( '[data-lp-menu]' ), false );
			moveMenuFocus( trigger.closest( '[data-lp-menu]' ), event.key === 'ArrowDown' ? 1 : -1, true );
		} );

		LocalizePilot.on( 'keydown', '[data-lp-menu-list]', function ( event, list ) {
			var menu = list.closest( '[data-lp-menu]' );
			var handled = true;

			switch ( event.key ) {
				case 'ArrowDown':
					moveMenuFocus( menu, 1, false );
					break;
				case 'ArrowUp':
					moveMenuFocus( menu, -1, false );
					break;
				case 'Home':
					moveMenuFocus( menu, 1, true );
					break;
				case 'End':
					moveMenuFocus( menu, -1, true );
					break;
				case 'Tab':
					// Tabbing out of a menu closes it, as a native menu does.
					closeMenus( null, false );
					handled = false;
					break;
				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		} );

		LocalizePilot.on( 'click', '[data-lp-nav-toggle]', function ( event, trigger ) {
			event.preventDefault();

			if ( navIsOpen() ) {
				closeNav();
			} else {
				openNav( trigger );
			}
		} );

		LocalizePilot.on( 'click', '[data-lp-nav-close]', function ( event ) {
			event.preventDefault();
			closeNav();
		} );

		// Following a nav link closes the overlay it was chosen from.
		LocalizePilot.on( 'click', '.lp-nav__item', function () {
			closeNav();
		} );

		LocalizePilot.on( 'click', '[data-lp-drawer-open]', function ( event, trigger ) {
			event.preventDefault();
			openDrawer( document.getElementById( trigger.getAttribute( 'data-lp-drawer-open' ) ), trigger );
		} );

		LocalizePilot.on( 'click', '[data-lp-drawer-remote]', function ( event, trigger ) {
			// Modified clicks belong to the browser: the trigger is a real link
			// to the same view, so cmd-click still opens it in a new tab.
			if (
				event.defaultPrevented ||
				event.button !== 0 ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}

			event.preventDefault();
			loadDrawer( trigger.getAttribute( 'data-lp-drawer-remote' ), trigger );
		} );

		LocalizePilot.on( 'click', '[data-lp-drawer-close]', function ( event, control ) {
			event.preventDefault();
			closeDrawer( control.closest( '[data-lp-drawer]' ) );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest || ! event.target.closest( '[data-lp-menu]' ) ) {
				closeMenus( null, false );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				closeMenus( null, true );
				closeNav();

				LocalizePilot.findAll( '[data-lp-drawer]' ).forEach( function ( drawer ) {
					closeDrawer( drawer );
				} );

				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			var open = LocalizePilot.findAll( '[data-lp-drawer]' ).filter( function ( drawer ) {
				return ! drawer.hidden;
			} )[ 0 ];

			if ( open ) {
				trapFocus( event, LocalizePilot.find( '.lp-drawer__panel', open ) || open );
				return;
			}

			if ( navIsOpen() ) {
				trapFocus( event, document.getElementById( 'lp-sidebar' ) );
			}
		} );
	}

	LocalizePilot.ui = ui;

	document.addEventListener( 'localizepilot:ready', function () {
		if ( ! LocalizePilot.root ) {
			return;
		}

		guardPreviewControls();
		bindGlobalHandlers();
		ui.init();
	} );
} )( window, document );
