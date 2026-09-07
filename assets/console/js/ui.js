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
	function filterUrl( form ) {
		var url = new URL( form.action || window.location.href, window.location.origin );
		var data = new FormData( form );

		url.search = '';
		data.forEach( function ( value, key ) {
			if ( typeof value === 'string' && ( '' !== value || key === 'page' ) ) {
				url.searchParams.append( key, value );
			}
		} );

		return url.href;
	}

	function submitFilter( form, replace ) {
		if ( ! LocalizePilot.router || ! LocalizePilot.router.navigate ) {
			return false;
		}

		return LocalizePilot.router.navigate( filterUrl( form ), { replace: !! replace } );
	}

	ui.register( 'toolbar-autosubmit', function ( scope ) {
		LocalizePilot.findAll( '[data-lp-autosubmit], [data-lp-spa-filter]', scope ).forEach( function ( form ) {
			if ( form.__lpAutoSubmitBound ) {
				return;
			}

			form.__lpAutoSubmitBound = true;
			var searchTimer = 0;

			form.addEventListener( 'submit', function ( event ) {
				if ( submitFilter( form, false ) ) {
					event.preventDefault();
				}
			} );

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

				if ( ! submitFilter( form, false ) ) {
					form.submit();
				}
			} );

			var search = form.querySelector( 'input[type="search"], input[name="url"]' );

			if ( search ) {
				search.addEventListener( 'input', function () {
					window.clearTimeout( searchTimer );
					searchTimer = window.setTimeout( function () {
						var paged = form.querySelector( 'input[name="paged"]' );

						if ( paged ) {
							paged.value = '1';
						}

						submitFilter( form, true );
					}, 320 );
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------------
	 * AJAX settings forms
	 * ------------------------------------------------------------------ */

	function notify( message, tone ) {
		var toast = document.getElementById( 'lp-console-toast' );

		if ( ! toast ) {
			toast = document.createElement( 'div' );
			toast.id = 'lp-console-toast';
			toast.className = 'lp-toast';
			toast.setAttribute( 'role', 'status' );
			document.body.appendChild( toast );
		}

		window.clearTimeout( toast.__lpTimer );
		toast.className = 'lp-toast lp-toast--' + ( tone || 'success' ) + ' is-visible';
		toast.textContent = message;
		toast.__lpTimer = window.setTimeout( function () {
			toast.classList.remove( 'is-visible' );
		}, 3200 );
	}

	ui.notify = notify;

	ui.register( 'ajax-settings', function ( scope ) {
		LocalizePilot.findAll( '.lp-settings-form', scope ).forEach( function ( form ) {
			if ( form.__lpAjaxSaveBound || ! window.fetch || ! window.FormData ) {
				return;
			}

			form.__lpAjaxSaveBound = true;

			form.addEventListener( 'submit', function ( event ) {
				if ( event.defaultPrevented || form.getAttribute( 'aria-busy' ) === 'true' ) {
					return;
				}

				event.preventDefault();

				var button = event.submitter || form.querySelector( 'button[type="submit"]' );
				var original = button ? button.textContent : '';
				var body = new FormData( form );
				var fallback = false;

				body.set( 'action', 'localizepilot_save_settings' );
				body.set( 'nonce', LocalizePilot.settings.nonce || '' );
				form.setAttribute( 'aria-busy', 'true' );

				if ( button ) {
					button.disabled = true;
					button.textContent = LocalizePilot.text( 'saving', 'Saving…' );
				}

				window.fetch( LocalizePilot.settings.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				} )
					.then( function ( response ) {
						fallback = ! response.ok && response.status >= 500;
						return response.json();
					} )
					.then( function ( response ) {
						if ( ! response || ! response.success ) {
							var message = response && response.data && response.data.message
								? response.data.message
								: LocalizePilot.text( 'saveFailed', 'Changes could not be saved.' );

							notify( message, 'error' );
							return;
						}

						notify(
							response.data.message || LocalizePilot.text( 'saved', 'Changes saved.' ),
							'success'
						);

						document.dispatchEvent( new CustomEvent( 'localizepilot:saved', { detail: response.data } ) );

						if ( LocalizePilot.router && LocalizePilot.router.refresh ) {
							LocalizePilot.router.refresh( { preservePosition: true } );
						}
					} )
					.catch( function () {
						/* Network and invalid-response failures retain the native Settings
						 * API path as a reliable progressive-enhancement fallback. */
						fallback = true;
					} )
					.finally( function () {
						form.removeAttribute( 'aria-busy' );

						if ( button ) {
							button.disabled = false;
							button.textContent = original;
						}

						if ( fallback ) {
							HTMLFormElement.prototype.submit.call( form );
						}
					} );
			} );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Top-bar command search
	 * ------------------------------------------------------------------ */

	var commandReturnFocus = null;
	var commandTimer = 0;
	var commandSequence = 0;

	function commandElement() {
		return LocalizePilot.find( '[data-lp-command]' );
	}

	function loadCommandResults( query ) {
		var command = commandElement();
		var results = command ? LocalizePilot.find( '[data-lp-command-results]', command ) : null;
		var sequence = ++commandSequence;

		if ( ! results ) {
			return;
		}

		results.setAttribute( 'aria-busy', 'true' );

		LocalizePilot.request( 'search', { q: query } )
			.then( function ( response ) {
				if ( sequence !== commandSequence || ! response || ! response.success || ! response.data ) {
					return;
				}

				results.innerHTML = response.data.html || '';
			} )
			.catch( function () {
				if ( sequence === commandSequence ) {
					results.innerHTML = '<p class="lp-command__error">' +
						LocalizePilot.text( 'searchFailed', 'Search is temporarily unavailable.' ) + '</p>';
				}
			} )
			.finally( function () {
				if ( sequence === commandSequence ) {
					results.removeAttribute( 'aria-busy' );
				}
			} );
	}

	function openCommand( trigger ) {
		var command = commandElement();

		if ( ! command || ! command.hidden ) {
			return;
		}

		commandReturnFocus = trigger || document.activeElement;
		command.hidden = false;
		lockScroll();

		var input = LocalizePilot.find( '[data-lp-command-input]', command );

		if ( input ) {
			input.value = '';
			input.focus();
		}

		loadCommandResults( '' );
	}

	function closeCommand() {
		var command = commandElement();

		if ( ! command || command.hidden ) {
			return;
		}

		command.hidden = true;
		commandSequence++;
		releaseScroll();

		if ( commandReturnFocus && commandReturnFocus.focus ) {
			commandReturnFocus.focus();
		}

		commandReturnFocus = null;
	}

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

	function menuList( menu ) {
		return menu ? ( menu.__lpMenuList || LocalizePilot.find( '[data-lp-menu-list]', menu ) ) : null;
	}

	function restoreMenuList( menu, list ) {
		if ( ! menu || ! list ) {
			return;
		}

		list.classList.remove( 'is-ported' );
		list.removeAttribute( 'style' );

		if ( list.parentNode !== menu ) {
			menu.appendChild( list );
		}
	}

	function positionMenu( menu ) {
		var list = menuList( menu );
		var trigger = LocalizePilot.find( '[data-lp-menu-trigger]', menu );

		if ( ! list || ! trigger ) {
			return;
		}

		/*
		 * Tables need horizontal overflow, but CSS then also clips vertical
		 * overflow. Port the open popup to <body> and anchor it to the trigger;
		 * the menu is restored to its row when it closes.
		 */
		list.__lpMenu = menu;
		menu.__lpMenuList = list;
		list.classList.add( 'is-ported' );
		list.style.visibility = 'hidden';
		document.body.appendChild( list );

		var anchor = trigger.getBoundingClientRect();
		var popup = list.getBoundingClientRect();
		var gap = 4;
		var edge = 8;
		var top = anchor.bottom + gap;
		var left = anchor.right - popup.width;

		if ( top + popup.height > window.innerHeight - edge ) {
			top = Math.max( edge, anchor.top - popup.height - gap );
		}

		left = Math.max( edge, Math.min( left, window.innerWidth - popup.width - edge ) );

		list.style.top = Math.round( top ) + 'px';
		list.style.left = Math.round( left ) + 'px';
		list.style.visibility = '';
	}

	function closeMenus( except, restoreFocus ) {
		LocalizePilot.findAll( '[data-lp-menu]' ).forEach( function ( menu ) {
			if ( menu === except ) {
				return;
			}

			var list = menuList( menu );
			var trigger = LocalizePilot.find( '[data-lp-menu-trigger]', menu );

			if ( ! list || list.hidden ) {
				return;
			}

			var hadFocus = menu.contains( document.activeElement ) || list.contains( document.activeElement );

			list.hidden = true;
			restoreMenuList( menu, list );

			if ( trigger ) {
				trigger.setAttribute( 'aria-expanded', 'false' );

				// Closing with a key must not drop focus to the document, or
				// the next Tab starts over at the top of the page.
				if ( restoreFocus && hadFocus ) {
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
		var list = menuList( menu );

		return list ? LocalizePilot.findAll( '[role="menuitem"]', list ) : [];
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
		var list = menuList( menu );
		var trigger = LocalizePilot.find( '[data-lp-menu-trigger]', menu );

		if ( ! list ) {
			return;
		}

		closeMenus( menu, false );
		list.hidden = false;
		positionMenu( menu );

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

	ui.closeMenus = closeMenus;

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

	/* ---------------------------------------------------------------------
	 * The paywall modal
	 *
	 * One dialog for the whole console, filled in from the catalogue in the
	 * payload. A screen with a dozen locked controls costs one dialog.
	 *
	 * The trigger is whatever carries data-lp-paywall. On a control that has
	 * to stay operable-looking but inert — a disabled <select> — the marker
	 * sits on the wrapper instead, because a disabled control emits no
	 * events at all and could never open this.
	 * ------------------------------------------------------------------ */

	var paywallReturnFocus = null;

	function paywallElement() {
		return LocalizePilot.find( '[data-lp-paywall-modal]' );
	}

	function paywallIsOpen() {
		var modal = paywallElement();

		return !! modal && ! modal.hidden;
	}

	/**
	 * The catalogue entry for a feature, or null when the payload has none.
	 *
	 * @param {string} feature Feature key.
	 * @return {Object|null} Entry.
	 */
	function paywallEntry( feature ) {
		var paywall = LocalizePilot.settings.paywall || {};
		var features = paywall.features || {};

		return features[ feature ] || null;
	}

	function openPaywall( feature, trigger ) {
		var modal = paywallElement();

		if ( ! modal ) {
			return;
		}

		var entry = paywallEntry( feature );
		var title = LocalizePilot.find( '[data-lp-paywall-title]', modal );
		var promise = LocalizePilot.find( '[data-lp-paywall-promise]', modal );
		var points = LocalizePilot.find( '[data-lp-paywall-points]', modal );

		// textContent, never innerHTML: these strings are translated and can
		// carry anything a translator wrote.
		if ( title ) {
			title.textContent = ( entry && entry.title ) || LocalizePilot.text( 'paywallFallback', '' );
		}

		if ( promise ) {
			promise.textContent = ( entry && entry.promise ) || '';
			promise.hidden = ! promise.textContent;
		}

		if ( points ) {
			points.textContent = '';

			var template = LocalizePilot.find( '[data-lp-paywall-point]', modal );

			( ( entry && entry.points ) || [] ).forEach( function ( point ) {
				var item;

				if ( template && template.content ) {
					item = template.content.firstElementChild.cloneNode( true );

					// Named, not positional: the first span in the clone is
					// the icon's own wrapper, and writing the text into that
					// replaces the icon with the text.
					var label = item.querySelector( '[data-lp-point-label]' );

					if ( label ) {
						label.textContent = point;
					}
				} else {
					item = document.createElement( 'li' );
					item.textContent = point;
				}

				points.appendChild( item );
			} );

			points.hidden = ! points.children.length;
		}

		paywallReturnFocus = trigger || document.activeElement;
		modal.hidden = false;
		lockScroll();

		// Focus the way out before the way to pay: the first thing a keyboard
		// user meets should not be a purchase.
		var close = LocalizePilot.find( '[data-lp-paywall-close]:not([tabindex="-1"])', modal );

		if ( close ) {
			close.focus();
		}
	}

	function closePaywall() {
		var modal = paywallElement();

		if ( ! modal || modal.hidden ) {
			return;
		}

		modal.hidden = true;
		releaseScroll();

		if ( paywallReturnFocus && paywallReturnFocus.focus ) {
			paywallReturnFocus.focus();
		}

		paywallReturnFocus = null;
	}

	/**
	 * Locked controls render fully and do nothing, exactly as preview-gated
	 * ones do — but where a preview control simply swallows its activation,
	 * this one has something to say, so it opens the modal instead.
	 */
	function bindPaywallControls() {
		function intercept( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			var locked = target.closest( '[data-lp-paywall]' );

			if ( ! locked ) {
				return;
			}

			// A keypress that is not an activation is left alone, so Tab and
			// the arrow keys still move.
			if ( event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			if ( event.type !== 'submit' ) {
				openPaywall( locked.getAttribute( 'data-lp-paywall' ) || '', locked );
			}
		}

		document.addEventListener( 'click', intercept, true );
		document.addEventListener( 'keydown', intercept, true );
		document.addEventListener( 'submit', intercept, true );
	}

	function bindGlobalHandlers() {
		LocalizePilot.on( 'click', '[data-lp-paywall-close]', function ( event ) {
			event.preventDefault();
			closePaywall();
		} );

		LocalizePilot.on( 'click', '[data-lp-command-open]', function ( event, trigger ) {
			event.preventDefault();
			openCommand( trigger );
		} );

		LocalizePilot.on( 'click', '[data-lp-command-close]', function ( event ) {
			event.preventDefault();
			closeCommand();
		} );

		LocalizePilot.on( 'input', '[data-lp-command-input]', function ( event, input ) {
			window.clearTimeout( commandTimer );
			commandTimer = window.setTimeout( function () {
				loadCommandResults( input.value.trim() );
			}, 180 );
		} );

		LocalizePilot.on( 'keydown', '[data-lp-command-input]', function ( event, input ) {
			if ( event.key !== 'ArrowDown' ) {
				return;
			}

			var command = input.closest( '[data-lp-command]' );
			var first = command ? LocalizePilot.find( '.lp-command__result', command ) : null;

			if ( first ) {
				event.preventDefault();
				first.focus();
			}
		} );

		LocalizePilot.on( 'keydown', '.lp-command__result', function ( event, result ) {
			if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) {
				return;
			}

			var items = LocalizePilot.findAll( '.lp-command__result', result.closest( '[data-lp-command]' ) );
			var index = items.indexOf( result );
			var next = index + ( event.key === 'ArrowDown' ? 1 : -1 );

			event.preventDefault();

			if ( next < 0 ) {
				LocalizePilot.find( '[data-lp-command-input]', result.closest( '[data-lp-command]' ) ).focus();
			} else {
				items[ Math.min( next, items.length - 1 ) ].focus();
			}
		} );

		LocalizePilot.on( 'click', '.lp-command__result', function () {
			closeCommand();
		} );

		LocalizePilot.on( 'click', '[data-lp-copy]', function ( event, button ) {
			event.preventDefault();

			var target = LocalizePilot.find( button.getAttribute( 'data-lp-copy' ) || '' );

			if ( ! target ) {
				return;
			}

			// Form controls hold their text in value, everything else in the node.
			var value = 'value' in target ? target.value : ( target.textContent || '' );

			/*
			 * An icon-only button has no text to swap, and overwriting it would
			 * remove the icon. Those confirm with a class alone.
			 */
			var label = button.querySelector( '[data-lp-copy-text]' ) ||
				( button.firstElementChild ? null : button );
			var original = label ? ( button.getAttribute( 'data-lp-copy-label' ) || label.textContent ) : '';

			if ( label ) {
				button.setAttribute( 'data-lp-copy-label', original );
			}

			function complete() {
				if ( label ) {
					label.textContent = LocalizePilot.text( 'copied', 'Copied' );
				}

				button.classList.add( 'is-copied' );

				window.setTimeout( function () {
					if ( label ) {
						label.textContent = original;
					}

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
			var list = menuList( menu );

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
			var menu = list.__lpMenu || list.closest( '[data-lp-menu]' );
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
			if ( ! event.target.closest || ! event.target.closest( '[data-lp-menu], [data-lp-menu-list]' ) ) {
				closeMenus( null, false );
			}
		} );

		window.addEventListener( 'resize', function () {
			closeMenus( null, false );
		} );

		window.addEventListener( 'scroll', function () {
			closeMenus( null, false );
		}, true );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ( event.ctrlKey || event.metaKey ) && event.key.toLowerCase() === 'k' ) {
				event.preventDefault();
				openCommand( LocalizePilot.find( '[data-lp-command-open]' ) );
				return;
			}

			if ( event.key === 'Escape' ) {
				closeMenus( null, true );
				closeNav();
				closeCommand();
				closePaywall();

				LocalizePilot.findAll( '[data-lp-drawer]' ).forEach( function ( drawer ) {
					closeDrawer( drawer );
				} );

				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			if ( paywallIsOpen() ) {
				var paywall = paywallElement();
				trapFocus( event, LocalizePilot.find( '.lp-modal__panel', paywall ) || paywall );
				return;
			}

			var command = commandElement();

			if ( command && ! command.hidden ) {
				trapFocus( event, LocalizePilot.find( '.lp-command__panel', command ) || command );
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
		bindPaywallControls();
		bindGlobalHandlers();
		ui.init();
	} );
} )( window, document );
