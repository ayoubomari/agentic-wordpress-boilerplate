/**
 * Shared scroll-snap carousel — plain JS, no framework, no build step.
 * Drives any `.agentic-carousel` on the page: prev/next buttons and dot
 * navigation scroll the native-scrolling track; touch/trackpad swipe works
 * for free since the track is just a normal scroll container.
 *
 * Registered once as the "agentic-carousel" script handle (see
 * agentic-blocks.php) and referenced by handle — not by file path — from
 * every block that needs it (hero-banner, testimonials), so the browser
 * loads it once even when both blocks are on the same page.
 *
 * Markup contract:
 *   <div class="agentic-carousel">
 *     <div class="agentic-carousel__track"> ...slide elements... </div>
 *     <button data-carousel-prev>‹</button>
 *     <button data-carousel-next>›</button>
 *     <button data-carousel-dot></button> (one per slide, optional)
 *   </div>
 *
 * Autoplay is opt-in per instance via a `data-carousel-autoplay="<ms>"`
 * attribute on the root (hero-banner sets it, testimonials doesn't) — it
 * advances on a wraparound timer, pauses on hover/keyboard focus so it never
 * fights a user mid-click or mid-read, and is skipped entirely under
 * prefers-reduced-motion. While enabled it also toggles "is-filling" on the
 * active dot so its progress-fill animation (see hero-banner/style.css)
 * stays in sync with each slide change.
 *
 * Looping is infinite in both directions, the same illusion the
 * announcement-bar marquee uses: the last slide is cloned before the first
 * and the first slide is cloned after the last, so stepping past either end
 * scrolls smoothly onto a pixel-identical clone — then, once that scroll
 * settles, the position is silently snapped (no animation) to the matching
 * real slide. The swap is imperceptible since the clone and the real slide
 * look identical, and it means "next" and "prev" never hit a dead end.
 */
( function () {
	function setupCarousel( root ) {
		var track = root.querySelector( '.agentic-carousel__track' );
		if ( ! track ) {
			return;
		}

		var realSlides = Array.prototype.slice.call( track.children );
		var count = realSlides.length;
		var looping = count > 1;
		var prevBtn = root.querySelector( '[data-carousel-prev]' );
		var nextBtn = root.querySelector( '[data-carousel-next]' );
		var dots = Array.prototype.slice.call( root.querySelectorAll( '[data-carousel-dot]' ) );

		// Real slide i ends up at extended index i + offset once its clones
		// are spliced in on either side.
		var offset = looping ? 1 : 0;
		if ( looping ) {
			var leadClone = realSlides[ count - 1 ].cloneNode( true );
			var trailClone = realSlides[ 0 ].cloneNode( true );
			leadClone.setAttribute( 'aria-hidden', 'true' );
			trailClone.setAttribute( 'aria-hidden', 'true' );
			track.insertBefore( leadClone, realSlides[ 0 ] );
			track.appendChild( trailClone );
		}
		var slides = Array.prototype.slice.call( track.children );

		function currentIndex() {
			var trackLeft = track.getBoundingClientRect().left;
			var closest = 0;
			var closestDist = Infinity;
			slides.forEach( function ( slide, i ) {
				var dist = Math.abs( slide.getBoundingClientRect().left - trackLeft );
				if ( dist < closestDist ) {
					closestDist = dist;
					closest = i;
				}
			} );
			return closest;
		}

		function scrollToSlide( index, behavior ) {
			index = Math.max( 0, Math.min( slides.length - 1, index ) );
			// track.scrollTo(), not slide.scrollIntoView() — scrollIntoView
			// walks every scrollable ancestor including the page itself, so
			// on a carousel not already fully in view (testimonials, further
			// down the page) it would also scroll the *page* vertically to
			// bring the slide into frame. Scrolling the track's own
			// scrollLeft only ever moves the track, never the page.
			var delta = slides[ index ].getBoundingClientRect().left - track.getBoundingClientRect().left;
			track.scrollTo( { left: track.scrollLeft + delta, behavior: behavior || 'smooth' } );
		}

		// Jump to the first real slide before anything paints — instant, not
		// smooth, so the page never shows an animated scroll on load.
		if ( looping ) {
			scrollToSlide( offset, 'auto' );
		}

		function correctLoopPosition() {
			if ( ! looping ) {
				return;
			}
			var index = currentIndex();
			if ( index === 0 ) {
				scrollToSlide( count, 'auto' ); // Landed on the leading clone of the last slide — snap to the real last slide.
			} else if ( index === slides.length - 1 ) {
				scrollToSlide( offset, 'auto' ); // Landed on the trailing clone of the first slide — snap to the real first slide.
			}
		}

		var autoplayDelay = parseInt( root.getAttribute( 'data-carousel-autoplay' ), 10 );
		var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var autoplayEnabled = !! autoplayDelay && count > 1 && ! reducedMotion;
		var autoplayTimer = null;

		function setActiveDot() {
			var index = ( currentIndex() - offset + count ) % count;
			dots.forEach( function ( dot, i ) {
				var isActive = i === index;
				dot.classList.toggle( 'is-active', isActive );
				dot.setAttribute( 'aria-current', isActive ? 'true' : 'false' );
				dot.classList.remove( 'is-filling' );
			} );
			if ( autoplayEnabled ) {
				var activeDot = dots[ index ];
				if ( activeDot ) {
					// Force a reflow before re-adding the class so the fill
					// animation restarts from empty on every slide change
					// instead of continuing a run already in progress.
					void activeDot.offsetWidth;
					activeDot.classList.add( 'is-filling' );
				}
			}
		}

		function stopAutoplay() {
			clearInterval( autoplayTimer );
			autoplayTimer = null;
			dots.forEach( function ( dot ) {
				dot.classList.remove( 'is-filling' );
			} );
		}

		function startAutoplay() {
			if ( ! autoplayEnabled ) {
				return;
			}
			clearInterval( autoplayTimer );
			autoplayTimer = setInterval( function () {
				// No modulo needed — going one past the last real slide lands
				// on its clone, and the scroll-settle handler below silently
				// snaps that back to the real first slide.
				scrollToSlide( currentIndex() + 1 );
			}, autoplayDelay );
			setActiveDot();
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				scrollToSlide( currentIndex() - 1 );
				startAutoplay();
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				scrollToSlide( currentIndex() + 1 );
				startAutoplay();
			} );
		}
		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				scrollToSlide( i + offset );
				startAutoplay();
			} );
		} );

		var scrollTimeout;
		track.addEventListener(
			'scroll',
			function () {
				clearTimeout( scrollTimeout );
				scrollTimeout = setTimeout( function () {
					correctLoopPosition();
					setActiveDot();
				}, 100 );
			},
			{ passive: true }
		);

		if ( autoplayEnabled ) {
			[ 'mouseenter', 'focusin' ].forEach( function ( evt ) {
				root.addEventListener( evt, stopAutoplay );
			} );
			[ 'mouseleave', 'focusout' ].forEach( function ( evt ) {
				root.addEventListener( evt, startAutoplay );
			} );
		}

		setActiveDot();
		startAutoplay();
	}

	document.querySelectorAll( '.agentic-carousel' ).forEach( setupCarousel );
} )();
