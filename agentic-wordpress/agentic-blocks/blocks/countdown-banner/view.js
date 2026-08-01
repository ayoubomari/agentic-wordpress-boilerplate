/**
 * Countdown banner ticker — plain JS, no framework, no build step.
 * Registered via block.json "viewScript" so WordPress only enqueues it on
 * pages where the block is actually present.
 */
( function () {
	function pad( n ) {
		return String( n ).padStart( 2, '0' );
	}

	function tick( el ) {
		var end = new Date( el.dataset.end ).getTime();
		if ( isNaN( end ) ) {
			return;
		}

		var diff = end - Date.now();
		var timer = el.querySelector( '.agentic-countdown-banner__timer' );

		if ( diff <= 0 ) {
			if ( timer ) {
				timer.remove();
			}
			var expired = document.createElement( 'p' );
			expired.className = 'agentic-countdown-banner__text';
			expired.textContent = el.dataset.expiredText || '';
			el.querySelector( '.agentic-countdown-banner__inner' ).insertBefore(
				expired,
				el.querySelector( '.agentic-countdown-banner__cta' ) || null
			);
			clearInterval( el._agenticCountdownInterval );
			return;
		}

		var seconds = Math.floor( diff / 1000 );
		var days = Math.floor( seconds / 86400 );
		var hours = Math.floor( ( seconds % 86400 ) / 3600 );
		var minutes = Math.floor( ( seconds % 3600 ) / 60 );
		var secs = seconds % 60;

		var days_el = el.querySelector( '[data-unit="days"]' );
		var hours_el = el.querySelector( '[data-unit="hours"]' );
		var minutes_el = el.querySelector( '[data-unit="minutes"]' );
		var seconds_el = el.querySelector( '[data-unit="seconds"]' );

		if ( days_el ) days_el.textContent = pad( days );
		if ( hours_el ) hours_el.textContent = pad( hours );
		if ( minutes_el ) minutes_el.textContent = pad( minutes );
		if ( seconds_el ) seconds_el.textContent = pad( secs );
	}

	document.querySelectorAll( '.agentic-countdown-banner[data-end]' ).forEach( function ( el ) {
		tick( el );
		el._agenticCountdownInterval = setInterval( function () {
			tick( el );
		}, 1000 );
	} );
} )();
