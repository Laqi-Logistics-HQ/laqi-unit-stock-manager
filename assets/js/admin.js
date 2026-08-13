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
	} );
}( jQuery, window.laqi_lusm_pool_search ) );
