/**
 * Search drawer toggle — plain JS, no framework, no build step.
 *
 * Opens/closes the panel via a class on the block root (CSS handles the
 * slide/fade transitions), traps Escape to close, and moves focus into the
 * search input on open so keyboard/screen-reader users land somewhere
 * useful rather than the trigger button.
 */
( function () {
	function setup( root ) {
		var openBtn = root.querySelector( '[data-search-drawer-open]' );
		var closeEls = root.querySelectorAll( '[data-search-drawer-close]' );
		var input = root.querySelector( '[data-search-drawer-input]' );

		function open() {
			root.classList.add( 'is-open' );
			document.body.classList.add( 'agentic-search-drawer-open' );
			if ( input ) {
				// Wait for the slide-in transition to start before focusing,
				// so screen readers announce the panel rather than jumping
				// focus mid-transition.
				window.setTimeout( function () {
					input.focus();
				}, 50 );
			}
		}

		function close() {
			root.classList.remove( 'is-open' );
			document.body.classList.remove( 'agentic-search-drawer-open' );
			if ( openBtn ) {
				openBtn.focus();
			}
		}

		if ( openBtn ) {
			openBtn.addEventListener( 'click', open );
		}
		closeEls.forEach( function ( el ) {
			el.addEventListener( 'click', close );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && root.classList.contains( 'is-open' ) ) {
				close();
			}
		} );
	}

	document.querySelectorAll( '.agentic-search-drawer' ).forEach( setup );
} )();
