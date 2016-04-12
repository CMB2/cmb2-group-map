window.CMB2Map = window.CMB2Map || {};

( function( window, document, $, app, undefined ) {
	'use strict';

	console.log('CMB2Map');

	app.cache = function() {
		app.$ = {};
		app.$.removeBtn = $( '.button.cmb-remove-group-row' );
	};

	app.init = function() {
		if ( ! window.cmb2_post_search ) {
			return console.error( 'CMB2 Post Search Field is required! https://github.com/WebDevStudios/CMB2-Post-Search-field' );
		}

		app.cache();

		app.$.removeBtn.each( function() {
			var $this = $( this );
			$this.after( '<button type="button" class="button alignright dashicons dashicons-search cmb2-mapping-select" title="Replace Item"></button>' );
		});

		$( document.body ).on( 'click', '.cmb2-mapping-select', window.cmb2_post_search.openSearch );
	};

	app.selectPost = function() {

	};


	$( app.init );

} )( window, document, jQuery, window.CMB2Map );
