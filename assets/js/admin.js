/* global jQuery */
( function ( $, config ) {
	'use strict';

	$( function () {
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

/* global BarcodeDetector */
( function ( config ) {
	'use strict';

	const root = document.getElementById( 'laqi-lusm-mobile-stocktake' );
	if ( ! root || ! config ) {
		return;
	}

	const scanForm = root.querySelector( '.laqi-lusm-mobile-scan-form' );
	const countForm = root.querySelector( '.laqi-lusm-mobile-count-form' );
	const code = root.querySelector( '#laqi-lusm-mobile-code' );
	const pool = root.querySelector( '#laqi-lusm-mobile-pool' );
	const quantity = root.querySelector( '#laqi-lusm-mobile-quantity' );
	const reason = root.querySelector( '#laqi-lusm-mobile-reason' );
	const status = root.querySelector( '.laqi-lusm-mobile-status' );
	const product = root.querySelector( '.laqi-lusm-mobile-product' );
	const balance = root.querySelector( '.laqi-lusm-mobile-balance' );
	const unit = root.querySelector( '.laqi-lusm-mobile-unit' );
	const cameraButton = root.querySelector( '.laqi-lusm-camera-button' );
	const preview = root.querySelector( '.laqi-lusm-camera-preview' );
	let pools = [];
	let requestKey = '';
	let stream = null;

	const newRequestKey = () => window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : `count-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	const message = ( value, error = false ) => {
		status.textContent = value;
		status.classList.toggle( 'notice-error', error );
	};
	const api = async ( path, options = {} ) => {
		const response = await window.fetch( `${ config.restUrl }${ path }`, {
			...options,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce, ...( options.headers || {} ) },
		} );
		const data = await response.json();
		if ( ! response.ok ) {
			throw new Error( data.message || config.strings.error );
		}
		return data;
	};
	const selectedPool = () => pools.find( ( item ) => String( item.id ) === pool.value );
	const updatePool = () => {
		const item = selectedPool();
		balance.textContent = item ? item.quantity_display : '';
		unit.textContent = item ? item.display_unit : '';
		quantity.value = '';
		requestKey = newRequestKey();
	};

	scanForm.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		message( config.strings.finding );
		countForm.hidden = true;
		try {
			const data = await api( `scan?code=${ encodeURIComponent( code.value.trim() ) }` );
			pools = data.pools || [];
			pool.replaceChildren();
			pools.forEach( ( item ) => {
				const option = document.createElement( 'option' );
				option.value = item.id;
				option.textContent = `${ item.name } — ${ item.quantity_display }`;
				pool.appendChild( option );
			} );
			product.textContent = data.product.name;
			countForm.hidden = false;
			updatePool();
			message( '' );
			quantity.focus();
		} catch ( error ) {
			message( error.message || config.strings.error, true );
		}
	} );

	pool.addEventListener( 'change', updatePool );
	countForm.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const item = selectedPool();
		if ( ! item ) {
			return;
		}
		message( config.strings.saving );
		try {
			const data = await api( `pools/${ item.id }/stocktake`, {
				method: 'POST',
				body: JSON.stringify( { quantity: quantity.value, unit: item.display_unit, reason: reason.value, idempotency_key: requestKey } ),
			} );
			item.quantity_base = data.pool.quantity_base;
			item.quantity_display = data.pool.quantity_display;
			balance.textContent = data.pool.quantity_display;
			quantity.value = '';
			reason.value = '';
			requestKey = newRequestKey();
			message( config.strings.saved );
		} catch ( error ) {
			message( error.message || config.strings.error, true );
		}
	} );

	const stopCamera = () => {
		if ( stream ) {
			stream.getTracks().forEach( ( track ) => track.stop() );
		}
		stream = null;
		preview.hidden = true;
	};
	if ( 'BarcodeDetector' in window && navigator.mediaDevices && navigator.mediaDevices.getUserMedia ) {
		cameraButton.hidden = false;
		cameraButton.addEventListener( 'click', async () => {
			try {
				const detector = new BarcodeDetector();
				stream = await navigator.mediaDevices.getUserMedia( { video: { facingMode: { ideal: 'environment' } }, audio: false } );
				preview.srcObject = stream;
				preview.hidden = false;
				await preview.play();
				message( config.strings.camera );
				const detect = async () => {
					if ( ! stream ) {
						return;
					}
					const results = await detector.detect( preview );
					if ( results.length ) {
						code.value = results[ 0 ].rawValue;
						stopCamera();
						scanForm.requestSubmit();
						return;
					}
					window.requestAnimationFrame( detect );
				};
				window.requestAnimationFrame( detect );
			} catch ( error ) {
				stopCamera();
				message( error.message || config.strings.error, true );
			}
		} );
	}
} )( window.laqi_lusm_mobile_stocktake );
