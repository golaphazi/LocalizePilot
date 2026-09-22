/**
 * Migration screen: start a run, then keep asking for the next batch until it
 * is done — but only while this screen is open and only after someone has
 * pressed Start or Continue. Nothing here moves a run on by itself.
 */
( function ( window, document ) {
	'use strict';

	var LocalizePilot = window.LocalizePilot;

	if ( ! LocalizePilot || ! LocalizePilot.ui ) {
		return;
	}

	/** True while this page is asking for batches. */
	var looping = false;

	function slot() {
		return document.querySelector( '[data-lp-migration-slot]' );
	}

	function card() {
		return document.querySelector( '[data-lp-migration-run]' );
	}

	/**
	 * Put the server's card in place, keeping keyboard focus inside it.
	 */
	function show( html ) {
		var target = slot();
		var old = card();
		var hadFocus = old && old.contains( document.activeElement );

		if ( ! target ) {
			return;
		}

		target.innerHTML = html || '';
		buttons();

		if ( hadFocus ) {
			var next = target.querySelector( 'button:not([hidden])' );

			if ( next ) {
				next.focus();
			}
		}
	}

	/** Show Pause while this page is working the run, Continue otherwise. */
	function buttons() {
		var current = card();

		if ( ! current ) {
			return;
		}

		var pause = current.querySelector( '[data-lp-migration-action="pause"]' );
		var resume = current.querySelector( '[data-lp-migration-action="continue"]' );

		if ( pause ) {
			pause.hidden = ! looping;
		}

		if ( resume ) {
			resume.hidden = looping;
		}
	}

	function notice( where, message ) {
		var line;

		if ( ! where ) {
			return;
		}

		line = where.querySelector( '.lp-migration-run__notice' );

		if ( ! line ) {
			line = document.createElement( 'p' );
			line.className = 'lp-migration-run__notice';
			line.setAttribute( 'role', 'alert' );
			where.appendChild( line );
		}

		line.textContent = message;
	}

	/**
	 * Redraw the whole screen, so the previews and the restore banner reflect
	 * what the run just did. Falls back to a page load without the router.
	 */
	function refresh() {
		if ( LocalizePilot.router && LocalizePilot.router.refresh && LocalizePilot.router.refresh() ) {
			return;
		}

		window.location.reload();
	}

	function stop() {
		looping = false;
		buttons();
	}

	/** Ask for batches until the run is finished, paused, or left behind. */
	function loop() {
		if ( looping ) {
			return;
		}

		looping = true;
		buttons();

		( function step() {
			if ( ! looping || ! card() ) {
				stop();
				return;
			}

			LocalizePilot.request( 'migration_step', {} )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						notice(
							card() && card().querySelector( '.lp-migration-run__body' ),
							( response && response.data && response.data.message ) || LocalizePilot.text( 'migrationFailed', 'The migration could not continue. Press Continue to try again.' )
						);
						stop();
						return;
					}

					show( response.data.html );

					if ( 'running' !== response.data.status ) {
						stop();
						refresh();
						return;
					}

					window.setTimeout( step, response.data.busy ? 1500 : 100 );
				} )
				.catch( function () {
					notice(
						card() && card().querySelector( '.lp-migration-run__body' ),
						LocalizePilot.text( 'migrationFailed', 'The migration could not continue. Press Continue to try again.' )
					);
					stop();
				} );
		} )();
	}

	function start( form, button ) {
		var draftOld = form.querySelector( 'input[name="draft_old"]' );
		var enable = form.querySelector( 'input[name="enable_languages"]' );
		var source = form.querySelector( 'input[name="source"]' );
		var holder = form.querySelector( '.lp-migration-source__actions' );

		if (
			draftOld &&
			draftOld.checked &&
			! window.confirm( LocalizePilot.text( 'migrationDraftConfirm', 'The old plugin\'s translated posts will be moved to draft as they are imported. You can put them back from this screen. Continue?' ) )
		) {
			return;
		}

		button.disabled = true;

		LocalizePilot.request( 'migration_start', {
			source: source ? source.value : '',
			enable_languages: enable && enable.checked ? '1' : '',
			draft_old: draftOld && draftOld.checked ? '1' : '',
		} )
			.then( function ( response ) {
				if ( ! response || ! response.success ) {
					button.disabled = false;
					notice( holder, ( response && response.data && response.data.message ) || LocalizePilot.text( 'migrationFailed', '' ) );
					return;
				}

				// One run at a time: every other Start waits for this one.
				LocalizePilot.findAll( '[data-lp-migration-action="start"]' ).forEach( function ( other ) {
					other.disabled = true;
				} );

				show( response.data.html );

				var target = slot();

				if ( target && target.scrollIntoView ) {
					target.scrollIntoView( { block: 'nearest' } );
				}

				loop();
			} )
			.catch( function () {
				button.disabled = false;
				notice( holder, LocalizePilot.text( 'migrationFailed', 'The migration could not continue. Press Continue to try again.' ) );
			} );
	}

	function cancel( button ) {
		stop();
		button.disabled = true;

		LocalizePilot.request( 'migration_cancel', {} ).then( function ( response ) {
			if ( response && response.success ) {
				refresh();
			} else {
				button.disabled = false;
			}
		} );
	}

	/** Put back everything a migration moved to draft, a hundred at a time. */
	function restore( button ) {
		if ( ! window.confirm( LocalizePilot.text( 'migrationRestoreConfirm', 'Put the posts the migration moved to draft back the way they were?' ) ) ) {
			return;
		}

		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );

		( function next() {
			LocalizePilot.request( 'migration_restore', {} )
				.then( function ( response ) {
					if ( response && response.success && response.data.remaining > 0 ) {
						next();
						return;
					}

					refresh();
				} )
				.catch( function () {
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );
				} );
		} )();
	}

	LocalizePilot.ui.register( 'migration', function ( scope ) {
		var root = LocalizePilot.find( '[data-lp-migration]', scope );

		if ( ! root || root.__lpMigration ) {
			return;
		}

		root.__lpMigration = true;

		// A new screen: whatever an earlier visit was doing, this one is not
		// working a run until someone asks it to.
		looping = false;
		buttons();

		root.addEventListener( 'submit', function ( event ) {
			var form = event.target.closest( '[data-lp-migration-form]' );

			if ( form ) {
				event.preventDefault();
				start( form, form.querySelector( '[data-lp-migration-action="start"]' ) );
			}
		} );

		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-lp-migration-action]' );
			var action = button && button.getAttribute( 'data-lp-migration-action' );

			if ( ! button || button.disabled || 'start' === action ) {
				return;
			}

			event.preventDefault();

			if ( 'continue' === action ) {
				loop();
			} else if ( 'pause' === action ) {
				stop();
			} else if ( 'cancel' === action ) {
				cancel( button );
			} else if ( 'restore' === action ) {
				restore( button );
			}
		} );
	} );
} )( window, document );
