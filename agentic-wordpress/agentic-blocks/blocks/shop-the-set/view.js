/**
 * Shop the set — "Add all to cart" button, plus hover-to-highlight between
 * the photo's numbered pins and the product list.
 *
 * Adding to cart: posts to WooCommerce's own wc-ajax=add_to_cart endpoint
 * (WC_AJAX::add_to_cart() — no nonce required, always registered regardless
 * of the "AJAX add to cart on archives" setting) once per product, then
 * reloads the page so the header mini-cart block re-renders with the new
 * cart contents. No cart-state syncing of our own to maintain.
 *
 * Highlighting: pins and list rows share a `data-shop-set-index`. Hovering
 * either one dims every *other* row's thumbnail/text (a plain opacity +
 * grayscale toggle) so the matching product reads as the one "in focus" —
 * pins and rows are in separate DOM subtrees (media vs. content column), so
 * this can't be done with a CSS sibling selector alone and needs JS.
 *
 * Layout: the photo is set to the content column's own rendered height
 * (see setupLayout and the comment in style.css on why this can't be done
 * in CSS alone) so a short product list never leaves the photo towering
 * over it. Because that height is content-driven, the object-fit:cover
 * crop it produces is a different slice of the photo at every viewport
 * width/content length — so pin position can't be a single hardcoded
 * percentage either. Each pin's `data-hotspot-x/y` is its position as a
 * percentage of the FULL, uncropped source photo; setupLayout recomputes
 * where that lands inside the *current* crop every time the crop changes,
 * so pins stay correct at any screen size rather than only the one they
 * happened to be tuned against.
 */
( function () {
	function setupAddAll( button ) {
		var endpoint = button.getAttribute( 'data-ajax-url' );
		var ids = ( button.getAttribute( 'data-product-ids' ) || '' )
			.split( ',' )
			.filter( Boolean );
		if ( ! endpoint || ! ids.length ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( button.classList.contains( 'is-loading' ) ) {
				return;
			}
			button.classList.add( 'is-loading' );
			var originalText = button.textContent;
			button.textContent = button.getAttribute( 'data-loading-text' ) || originalText;

			var chain = Promise.resolve();
			ids.forEach( function ( id ) {
				chain = chain.then( function () {
					return fetch( endpoint, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'product_id=' + encodeURIComponent( id ) + '&quantity=1',
					} );
				} );
			} );

			chain
				.then( function () {
					window.location.reload();
				} )
				.catch( function () {
					button.classList.remove( 'is-loading' );
					button.textContent = originalText;
				} );
		} );
	}

	function setupHighlight( root ) {
		var pins = root.querySelectorAll( '[data-shop-set-pin]' );
		var rows = root.querySelectorAll( '[data-shop-set-row]' );
		if ( ! pins.length || ! rows.length ) {
			return;
		}

		function setActive( index ) {
			pins.forEach( function ( pin ) {
				pin.classList.toggle( 'is-active', pin.getAttribute( 'data-shop-set-index' ) === index );
			} );
			rows.forEach( function ( row ) {
				var isOther = null !== index && row.getAttribute( 'data-shop-set-index' ) !== index;
				row.classList.toggle( 'is-dimmed', isOther );
			} );
		}

		pins.forEach( function ( pin ) {
			var index = pin.getAttribute( 'data-shop-set-index' );
			pin.addEventListener( 'mouseenter', function () {
				setActive( index );
			} );
			pin.addEventListener( 'mouseleave', function () {
				setActive( null );
			} );
		} );

		rows.forEach( function ( row ) {
			var index = row.getAttribute( 'data-shop-set-index' );
			row.addEventListener( 'mouseenter', function () {
				setActive( index );
			} );
			row.addEventListener( 'mouseleave', function () {
				setActive( null );
			} );
			row.addEventListener( 'focusin', function () {
				setActive( index );
			} );
			row.addEventListener( 'focusout', function () {
				setActive( null );
			} );
		} );
	}

	function setupLayout( root ) {
		var media = root.querySelector( '.agentic-shop-the-set__media' );
		var content = root.querySelector( '.agentic-shop-the-set__content' );
		var img = media ? media.querySelector( 'img' ) : null;
		var pins = root.querySelectorAll( '[data-shop-set-pin]' );
		if ( ! media || ! content ) {
			return;
		}

		// Matches the `@media (max-width: 780px)` breakpoint in style.css,
		// where media/content stack instead of sitting side by side.
		var sideBySide = window.matchMedia( '(min-width: 781px)' );

		function repositionPins() {
			if ( ! img || ! pins.length || ! img.naturalWidth || ! img.naturalHeight ) {
				return;
			}
			var containerW = media.clientWidth;
			var containerH = media.clientHeight;
			if ( ! containerW || ! containerH ) {
				return;
			}

			// Same math the browser uses for object-fit:cover: scale the
			// source image up to whichever dimension needs more scale to
			// fully cover the container, then crop the overflow evenly
			// from both sides of the other dimension.
			var scale = Math.max( containerW / img.naturalWidth, containerH / img.naturalHeight );
			var visibleSrcW = containerW / scale;
			var visibleSrcH = containerH / scale;
			var cropX = ( img.naturalWidth - visibleSrcW ) / 2;
			var cropY = ( img.naturalHeight - visibleSrcH ) / 2;

			pins.forEach( function ( pin ) {
				var fullX = parseFloat( pin.getAttribute( 'data-hotspot-x' ) );
				var fullY = parseFloat( pin.getAttribute( 'data-hotspot-y' ) );
				if ( isNaN( fullX ) || isNaN( fullY ) ) {
					return;
				}
				var srcX = ( fullX / 100 ) * img.naturalWidth;
				var srcY = ( fullY / 100 ) * img.naturalHeight;
				pin.style.left = ( ( srcX - cropX ) / visibleSrcW ) * 100 + '%';
				pin.style.top = ( ( srcY - cropY ) / visibleSrcH ) * 100 + '%';
			} );
		}

		function sync() {
			media.style.height = sideBySide.matches ? content.offsetHeight + 'px' : '';
			// Read-after-write: only correct once the height above has
			// actually been applied, since repositionPins measures it.
			repositionPins();
		}

		if ( img && ! img.complete ) {
			img.addEventListener( 'load', sync );
		}
		sync();

		var resizeTimer;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( sync, 150 );
		} );

		// Web fonts loading in after first paint can reflow the content
		// column's height (e.g. heading line-wrap changes) — re-sync once
		// they're ready rather than leaving a stale, slightly-off layout.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( sync );
		}
	}

	document
		.querySelectorAll( '[data-shop-set-add-all]' )
		.forEach( setupAddAll );

	document
		.querySelectorAll( '.agentic-shop-the-set' )
		.forEach( function ( root ) {
			setupHighlight( root );
			setupLayout( root );
		} );
} )();
