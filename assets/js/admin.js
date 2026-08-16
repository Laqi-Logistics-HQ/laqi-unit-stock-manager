/* global jQuery */
( function ( $, config ) {
	'use strict';

	$( function () {
		let modalTrigger = null;

		function closeModal( modal ) {
			modal.hidden = true;
			document.body.classList.remove( 'laqi-lusm-modal-open' );
			if ( modalTrigger ) {
				modalTrigger.focus();
				modalTrigger = null;
			}
		}

		document.addEventListener( 'click', ( event ) => {
			const trigger = event.target.closest( '.laqi-lusm-pool-editor-trigger' );
			const close = event.target.closest( '[data-laqi-lusm-close-modal]' );
			if ( trigger ) {
				const modal = document.getElementById( trigger.getAttribute( 'aria-controls' ) );
				if ( modal ) {
					modalTrigger = trigger;
					modal.hidden = false;
					document.body.classList.add( 'laqi-lusm-modal-open' );
					modal.querySelector( 'form input:not([type="hidden"]), form select, form textarea, form button' ).focus();
				}
			} else if ( close ) {
				closeModal( close.closest( '.laqi-lusm-modal' ) );
			}
		} );

		document.addEventListener( 'keydown', ( event ) => {
			const modal = document.querySelector( '.laqi-lusm-modal:not([hidden])' );
			if ( modal && 'Escape' === event.key ) {
				closeModal( modal );
			} else if ( modal && 'Tab' === event.key ) {
				const focusable = Array.from( modal.querySelectorAll( 'button, input:not([type="hidden"]), select, textarea, a[href]' ) )
					.filter( ( element ) => ! element.disabled );
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		$( '.laqi-lusm-pool-search' ).each( function () {
			const field = $( this );
			field.selectWoo( {
				allowClear: false,
				placeholder: field.data( 'placeholder' ),
				width: '100%',
				ajax: {
					url: config.ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: ( params ) => ( {
						action: 'laqi_lusm_search_pools',
						security: config.nonce,
						term: params.term || '',
						page: params.page || 1,
					} ),
					processResults: ( response ) => response.data,
				},
			} );
		} );

		$( '.wc-product-search, .laqi-lusm-pool-search' ).each( function () {
			const field = $( this );
			const label = $( `label[for="${ field.attr( 'id' ) }"]` ).text();
			field
				.next( '.select2' )
				.find( '.select2-selection__rendered' )
				.attr( 'aria-label', label || field.data( 'placeholder' ) );
		} );
	} );
} )( jQuery, window.laqi_lusm_pool_search );
