/* global jQuery */
( function ( $, config ) {
	'use strict';

	$( function () {
		let modalTrigger = null;

		function openProductContext() {
			const params = new URLSearchParams( window.location.search );
			const requested = params.get( 'laqi_lusm_open' );
			if ( 'unit-stock' === requested ) {
				$( '.laqi_lusm_options' )
					.trigger( 'click' )
					.find( 'a' )
					.focus();
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
				const currentPage =
					parseInt( list.attr( 'data-page' ), 10 ) || 1;
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
				const variationsTab =
					document.querySelector( '.variations_tab a' );
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

		document
			.querySelectorAll( '.laqi-lusm-unit-picker' )
			.forEach( ( picker ) => {
				const family = picker.querySelector( '.laqi-lusm-unit-family' );
				const system = picker.querySelector( '.laqi-lusm-unit-system' );
				const unit = picker.querySelector( '.laqi-lusm-unit-choice' );
				const options = Array.from( unit.options ).map( ( option ) =>
					option.cloneNode( true )
				);
				const systemOptions = Array.from( system.options ).map(
					( option ) => option.cloneNode( true )
				);

				const filterSystems = () => {
					const available = new Set(
						options
							.filter(
								( option ) =>
									option.dataset.family === family.value
							)
							.map( ( option ) => option.dataset.system )
					);
					const matches = systemOptions.filter(
						( option ) =>
							'' === option.value || available.has( option.value )
					);
					const previous = system.value;
					system.replaceChildren(
						...matches.map( ( option ) => option.cloneNode( true ) )
					);
					if (
						matches.some( ( option ) => option.value === previous )
					) {
						system.value = previous;
					}
				};

				const filterUnits = () => {
					const previous = unit.value;
					const matches = options.filter(
						( option ) =>
							option.dataset.family === family.value &&
							( '' === system.value ||
								option.dataset.system === system.value )
					);
					unit.replaceChildren(
						...matches.map( ( option ) => option.cloneNode( true ) )
					);
					if (
						matches.some( ( option ) => option.value === previous )
					) {
						unit.value = previous;
					}
				};

				family.addEventListener( 'change', () => {
					filterSystems();
					filterUnits();
				} );
				system.addEventListener( 'change', filterUnits );
				filterSystems();
				filterUnits();
			} );

		const converter = document.querySelector( '.laqi-lusm-unit-converter' );
		if ( converter ) {
			const value = converter.querySelector(
				'#laqi-lusm-conversion-value'
			);
			const from = converter.querySelector(
				'[name="conversion_from_unit"]'
			);
			const to = converter.querySelector( '[name="conversion_to_unit"]' );
			const result = converter.querySelector(
				'.laqi-lusm-conversion-result'
			);

			const decimalRatio = ( numerator, denominator ) => {
				const whole = numerator / denominator;
				let remainder = numerator % denominator;
				let fraction = '';
				for ( let index = 0; index < 12 && 0n !== remainder; index++ ) {
					remainder *= 10n;
					fraction += ( remainder / denominator ).toString();
					remainder %= denominator;
				}
				return {
					approximate: 0n !== remainder,
					value: fraction
						? `${ whole }.${ fraction }`
						: whole.toString(),
				};
			};

			const calculate = () => {
				const input = value.value.trim();
				const match = input.match(
					/^(0|[1-9][0-9]*)(?:\.([0-9]{1,12}))?$/
				);
				if ( ! match ) {
					result.textContent = config.i18n.invalidConversionQuantity;
					result.classList.add( 'notice-error' );
					return;
				}

				const fromOption = from.selectedOptions[ 0 ];
				const toOption = to.selectedOptions[ 0 ];
				if (
					! fromOption ||
					! toOption ||
					fromOption.dataset.family !== toOption.dataset.family
				) {
					result.textContent = config.i18n.incompatibleUnitFamilies;
					result.classList.add( 'notice-error' );
					return;
				}

				const fraction = match[ 2 ] || '';
				const scaled = window.BigInt( match[ 1 ] + fraction );
				const scale = 10n ** window.BigInt( fraction.length );
				const converted = decimalRatio(
					scaled * window.BigInt( fromOption.dataset.factor ),
					scale * window.BigInt( toOption.dataset.factor )
				);
				const prefix = converted.approximate ? '≈ ' : '';
				result.textContent = `${ input } ${ fromOption.dataset.symbol } = ${ prefix }${ converted.value } ${ toOption.dataset.symbol }`;
				result.classList.remove( 'notice-error' );
			};

			converter.addEventListener( 'input', calculate );
			converter.addEventListener( 'change', calculate );
			calculate();
		}

		openProductContext();
	} );
} )( jQuery, window.laqi_lusm_pool_search );
