/**
 * Scroll-reveal — plain JS, no build step, enqueued only on templates that
 * actually have reveal-eligible content (see functions.php).
 *
 * Nothing gets hidden until this script confirms, via getBoundingClientRect(),
 * whether it is inside the viewport at load. That split matters beyond the
 * obvious "don't leave something invisible until the user happens to
 * scroll to it": CSS in scroll-reveal.css only applies opacity:0 to
 * elements carrying `is-agentic-pending`/`is-agentic-load-pending`, and
 * only this script adds those classes, so nothing can ever be hidden by a
 * static server-rendered class alone — hiding it that way (no JS gate at
 * all) is exactly what broke the shop page's Largest Contentful Paint
 * score the first time this shipped: product-subcategories' tiles sit
 * above the fold there and are the page's LCP element, and a scroll-gated
 * reveal left them (and the LCP timing) waiting on an observer callback
 * that a real visitor might not trigger for seconds.
 *
 * Above-the-fold items still get a reveal, just triggered differently: a
 * "load" fade (`is-agentic-load-pending`, fired on the next two animation
 * frames after this script runs, so it always actually plays) rather than
 * the scroll-triggered fade (`is-agentic-pending`, fired by
 * IntersectionObserver). Both are slow and sequential — see
 * scroll-reveal.css for the shared ~0.6–0.9s durations and staggered
 * per-item delays — the only difference is what starts the clock. It still
 * costs a little LCP — anything that starts at opacity:0 necessarily
 * delays "largest paint" until it reaches opacity:1 — but nowhere near
 * what an indefinite scroll-gated wait costs, and it means content already
 * on screen doesn't just sit fully static.
 *
 * Three reveal modes, chosen here by what markup a section/grid actually
 * contains — see assets/css/scroll-reveal.css for the matching transitions:
 *
 * 1. Item stagger (`.agentic-reveal-item`). Blocks that render a row/column
 *    of same-shaped children mark each one in their own render.php:
 *    agentic/featured-collection, agentic/collection-list,
 *    agentic/shop-the-set, agentic/photo-statement, agentic/image-with-text,
 *    agentic/product-subcategories — plus WooCommerce's own product-
 *    collection grid, marked via the render_block_woocommerce/product-
 *    collection filter in functions.php since core has no class attribute
 *    to set this from block markup directly. Items are grouped by their
 *    DOM parent (each such block already puts its items under one shared
 *    <ul>/<ol>/grid <div>), and each group is staggered independently by
 *    DOM order — safe only because none of these containers clip overflow.
 * 2. CSS-only stagger (`[data-agentic-reveal-mode="stagger-css"]`) — for
 *    agentic/testimonials only. Its cards sit inside a horizontally
 *    scrolling, clipped `agentic-carousel__track`; a card scrolled out of
 *    view there is clipped out of the intersection rect entirely, so mode 1
 *    (observing each card) would leave it permanently invisible. Instead
 *    this only observes the *section* (adding `is-agentic-visible` without
 *    fading the section itself — the heading stays put) and lets
 *    scroll-reveal.css cascade that into each card via plain nth-child
 *    transition-delay, which runs regardless of the card's own clip state.
 * 3. Whole-section fade (`.agentic-reveal`) — the fallback for any
 *    `.agentic-section` that isn't mode 2 and contains no `.agentic-reveal-
 *    item` children (photo-marquee, shop-the-set's own outer section, image-
 *    with-text's outer section, …).
 */
( function () {
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	var main = document.querySelector( 'main.wp-block-group' );
	if ( ! main ) {
		return;
	}

	function isAlreadyOnScreen( el ) {
		var rect = el.getBoundingClientRect();
		return rect.bottom > 0 && rect.top < window.innerHeight;
	}

	var targets = [];
	var loadTargets = [];

	// Above the fold: fast load-fade, staggered less (already on screen
	// together, no need for a long cascade). Below the fold: slow
	// scroll-fade, observed as before. `markerClasses` are contract classes
	// this script itself owns adding (mode 3's `agentic-reveal`) — mode 1/2's
	// own markers are already present in the server-rendered markup.
	function activate( el, markerClasses, delaySeconds ) {
		markerClasses.forEach( function ( markerClass ) {
			el.classList.add( markerClass );
		} );
		var isLoad = isAlreadyOnScreen( el );
		el.classList.add( isLoad ? 'is-agentic-load-pending' : 'is-agentic-pending' );
		if ( undefined !== delaySeconds ) {
			el.style.setProperty( '--agentic-reveal-delay', delaySeconds + 's' );
		}
		( isLoad ? loadTargets : targets ).push( el );
	}

	// Mode 1 — group every reveal-item by its DOM parent so each grid
	// (product grid, collection list, shop-the-set rows, …) staggers from
	// its own start rather than continuing another grid's count.
	var items = Array.prototype.slice.call( main.querySelectorAll( '.agentic-reveal-item' ) );
	var groupParents = [];
	var groupItems = [];
	items.forEach( function ( item ) {
		var parent = item.parentElement;
		var groupIndex = groupParents.indexOf( parent );
		if ( -1 === groupIndex ) {
			groupIndex = groupParents.push( parent ) - 1;
			groupItems.push( [] );
		}
		groupItems[ groupIndex ].push( item );
	} );
	groupItems.forEach( function ( group ) {
		group.forEach( function ( item, itemIndex ) {
			var step = isAlreadyOnScreen( item ) ? 0.15 : 0.09;
			activate( item, [], ( itemIndex % 6 ) * step );
		} );
	} );

	// Mode 2 — CSS-cascade sections: observed for the trigger class only.
	Array.prototype.slice
		.call( main.querySelectorAll( '.agentic-section[data-agentic-reveal-mode="stagger-css"]' ) )
		.forEach( function ( section ) {
			activate( section, [] );
		} );

	// Mode 3 — whole-section fade: everything else, skipping any section
	// already covered by mode 1 (has its own reveal-items) or mode 2
	// (stagger-css), and skipping the hero outright — unlike every other
	// above-the-fold case, it isn't just "already visible," it's this
	// page's deliberately preloaded LCP element (see the hero preload in
	// functions.php) and already runs its own carousel animation, so even
	// the cheap load-fade would work against the exact optimization that
	// preload exists for.
	var sectionIndex = 0;
	Array.prototype.slice.call( main.querySelectorAll( '.agentic-section' ) ).forEach( function ( section ) {
		if ( section.classList.contains( 'agentic-hero-banner' ) ) {
			return;
		}
		if ( 'stagger-css' === section.dataset.agenticRevealMode ) {
			return;
		}
		if ( section.querySelector( '.agentic-reveal-item' ) ) {
			return;
		}
		activate( section, [ 'agentic-reveal' ], ( sectionIndex % 3 ) * 0.2 );
		sectionIndex++;
	} );

	if ( loadTargets.length ) {
		// Double rAF: guarantees the browser has painted the pending
		// (opacity:0) state at least once before the class swap, so the
		// transition actually runs instead of the two classes landing in
		// the same style recalc and skipping straight to the end state.
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				loadTargets.forEach( function ( target ) {
					target.classList.add( 'is-agentic-visible' );
				} );
			} );
		} );
	}

	if ( ! targets.length ) {
		return;
	}

	if ( ! ( 'IntersectionObserver' in window ) ) {
		targets.forEach( function ( target ) {
			target.classList.add( 'is-agentic-visible' );
		} );
		return;
	}

	var observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-agentic-visible' );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.15, rootMargin: '0px 0px -8% 0px' }
	);

	targets.forEach( function ( target ) {
		observer.observe( target );
	} );
} )();
