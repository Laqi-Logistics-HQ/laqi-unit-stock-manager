/* global jQuery */
( function ( $, config ) {
	'use strict';

	$( function () {
		let modalTrigger = null;

		function openProductContext() {
			const params = new URLSearchParams( window.location.search );
			const requested = params.get( 'laqi_lusm_open' );
			if ( 'unit-stock' === requested ) {
				$( '.laqi_lusm_options' ).trigger( 'click' ).find( 'a' ).focus();
				return;
			}
			if ( 'variations' !== requested && 'variation' !== requested ) {
				return;
			}

			const variationsTab = $( '.variations_tab a' );
			variationsTab.trigger( 'click' );
			if ( 'variations' === requested ) {
				variationsTab.focus();
				return;
			}

			const variationId = parseInt(
				params.get( 'laqi_lusm_variation' ),
				10
			);
			const targetPage = Math.max(
				1,
				parseInt( params.get( 'laqi_lusm_variation_page' ), 10 ) || 1
			);
			let pageRequested = false;
			const reveal = () => {
				const list = $(
					'#variable_product_options .woocommerce_variations'
				);
				const currentPage = parseInt( list.attr( 'data-page' ), 10 ) || 1;
				if ( currentPage !== targetPage && ! pageRequested ) {
					pageRequested = true;
					$( '.variations-pagenav .page-selector' )
						.first()
						.val( targetPage )
						.trigger( 'change' );
					return;
				}
				const variation = list
					.find( `.variable_post_id[value="${ variationId }"]` )
					.closest( '.woocommerce_variation' );
				if ( ! variation.length ) {
					variationsTab.focus();
					return;
				}
				if ( variation.hasClass( 'closed' ) ) {
					variation.find( 'h3' ).first().trigger( 'click' );
				}
				const mapping = variation.find(
					'.laqi-lusm-variation-mapping'
				);
				window.setTimeout( () => {
					mapping.attr( 'tabindex', '-1' ).trigger( 'focus' );
					mapping[ 0 ]?.scrollIntoView( {
						behavior: 'smooth',
						block: 'center',
					} );
				}, 250 );
			};

			$( '#woocommerce-product-data' ).on(
				'woocommerce_variations_loaded.laqiLusmContext',
				reveal
			);
			reveal();
		}

		document.addEventListener( 'change', ( event ) => {
			if ( event.target.matches( '.laqi-lusm-page-picker select' ) ) {
				event.target.form.submit();
			}
		} );

		function closeModal( modal ) {
			modal.hidden = true;
			document.body.classList.remove( 'laqi-lusm-modal-open' );
			if ( modalTrigger ) {
				modalTrigger.focus();
				modalTrigger = null;
			}
		}

		document.addEventListener( 'click', ( event ) => {
			const variationsTrigger = event.target.closest(
				'[data-laqi-lusm-open-variations]'
			);
			const trigger = event.target.closest(
				'.laqi-lusm-pool-editor-trigger'
			);
			const close = event.target.closest(
				'[data-laqi-lusm-close-modal]'
			);
			if ( variationsTrigger ) {
				event.preventDefault();
				const variationsTab = document.querySelector(
					'.variations_tab a'
				);
				if ( variationsTab ) {
					variationsTab.click();
					variationsTab.focus();
				}
			} else if ( trigger ) {
				const modal = document.getElementById(
					trigger.getAttribute( 'aria-controls' )
				);
				if ( modal ) {
					modalTrigger = trigger;
					modal.hidden = false;
					document.body.classList.add( 'laqi-lusm-modal-open' );
					modal
						.querySelector(
							'form input:not([type="hidden"]), form select, form textarea, form button'
						)
						.focus();
				}
			} else if ( close ) {
				closeModal( close.closest( '.laqi-lusm-modal' ) );
			}
		} );

		document.addEventListener( 'keydown', ( event ) => {
			const modal = document.querySelector(
				'.laqi-lusm-modal:not([hidden])'
			);
			if ( modal && 'Escape' === event.key ) {
				closeModal( modal );
			} else if ( modal && 'Tab' === event.key ) {
				const focusable = Array.from(
					modal.querySelectorAll(
						'button, input:not([type="hidden"]), select, textarea, a[href]'
					)
				).filter( ( element ) => ! element.disabled );
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if (
					event.shiftKey &&
					modal.ownerDocument.activeElement === first
				) {
					event.preventDefault();
					last.focus();
				} else if (
					! event.shiftKey &&
					modal.ownerDocument.activeElement === last
				) {
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

		openProductContext();
	} );
} )( jQuery, window.laqi_lusm_pool_search );
