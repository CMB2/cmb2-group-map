window.CMB2Map = window.CMB2Map || {};

( function( window, document, $, l10n, app, undefined ) {
	'use strict';

	app.cache = function() {
		app.$ = {};
	};

	app.init = function() {
		if ( ! window.cmb2_post_search ) {
			return app.logError( 'CMB2 Post Search Field is required! https://github.com/WebDevStudios/CMB2-Post-Search-field' );
		}

		app.cache();

		// Make sure window.cmb2_post_search is setup
		setTimeout( app.overridePostSearch, 500 );

		$( '.cmb2-group-map-group .cmb-repeatable-grouping' )
			.on( 'click', '.cmb-remove-group-row', app.maybeDelete )
			.each( app.moveButtons );

		$( document.body ).on( 'click', '.cmb2-mapping-select', app.openSearch );
	};

	app.moveButtons = function() {
		var $this = $( this );
		var $id_row = $this.find( '.cmb2-group-map-id' );

		var $btn = $id_row.find( '.cmb2-post-search-button' )
			.data( 'fieldID', $id_row.find( 'input[type="text"]' ).attr( 'id' ) )
			.addClass( 'button cmb2-mapping-select' );

		$this.find( '.button.cmb-remove-group-row' ).after( $btn );
	};

	app.openSearch = function( evt ) {
		var search = window.cmb2_post_search;

		search.$idInput = $( document.getElementById( $( evt.currentTarget ).data( 'fieldID' ) ) );

		// Setup our variables from the field data
		$.extend( search, search.$idInput.data( 'search' ) );

		search.trigger( 'open' );
	};

	app.overridePostSearch = function() {
		app.handleSelected = window.cmb2_post_search.handleSelected;

		// once a post is selected...
		window.cmb2_post_search.handleSelected = function( checked ) {
			if ( this.$idInput.hasClass( 'cmb2-group-map-data' ) ) {

				// ajax-grab the data we need
				app.get_and_set_post_data( checked[0], this.$idInput, checked );
				app.cachePrev = this.$idInput.val();
			}

			app.handleSelected.call( window.cmb2_post_search, checked );
		};
	};

	app.reset = function() {
		window.cmb2_post_search.$idInput.val( app.cachePrev );
	};

	app.get_and_set_post_data = function( post_id, $object, checked ) {
		var params = {
			action    : 'cmb2_group_map_get_post_data',
			ajaxurl   : l10n.ajaxurl,
			post_id   : post_id,
			host_id   : document.getElementById( 'post_ID' ).value,
			fieldName : $object.attr( 'name' )
		};

		var $group = $object.parents( '.cmb2-group-map-group .cmb-repeatable-grouping' );

		$.post( l10n.ajaxurl, params, function( response ) {

			if ( ! response.success || ! response.data ) {
				app.reset();
				return app.logError( response );
			}

			$.each( response.data, function( key, input ) {
				var $input = $( input.html );
				var $replace = {};
				var classesAttr;
				var nameAttr;
				var idAttr;

				// Special handling for certain types.
				if ( 'colorpicker' === input.type ) {
					$replace = $group.find( '.wp-picker-container' );
				}
				// else if ( 'wysiwyg' === input.type ) {
				// 	$replace = $group.find( '.wp-picker-container' );
				// }
				// Try id first
				else if ( ( idAttr = $input.attr( 'id' ) ) ) {
					$replace = $( document.getElementById( idAttr ) );
				}
				// Then try by class(es)
				else if ( ( classesAttr = $input.attr( 'class' ) ) ) {
					// Join classes to create a jQuery selector string.
					$replace = $group.find( '.' + classesAttr.split( /\s+/ ).join( '.' ) );
				}
				// Finally, try by name attribute
				else if ( ( nameAttr = $input.attr( 'name' ) ) ) {
					$replace = $group.find( '[name="' + nameAttr + '"]' );
				}

				// If we DIDN'T find an element to replace (we should have!)
				if ( ! $replace.length ) {
					app.logError( 'Could not find suitable selector in element.', [key, input] );
				}

				// Ok, replace the element.
				$replace.replaceWith( $input );
				window.CMB2.afterRowInsert( $group );

			} );

		} ).fail( app.reset );
	};

	app.maybeDelete = function( evt ) {
		evt.preventDefault();

		if ( window.confirm( l10n.strings.delete_permanent ) ) {
			app.doDelete( $( this ) );
			return;
		}
	};

	app.doDelete = function( $btn ) {
		var groupData = $btn.parents( '.cmb2-group-map-group' ).data();
		var $group    = $btn.parents( '.cmb2-group-map-group .cmb-repeatable-grouping' );
		var post_id   = $group.find( '.cmb2-group-map-data' ).val();

		var deleteFail = function() {
			app.logError( 'Sorry! unable to delete '+ post_id +'!' );
		};

		if ( ! post_id || ! groupData.groupid ) {
			return deleteFail();
		}

		var params = {
			action   : 'cmb2_group_map_delete_item',
			ajaxurl  : l10n.ajaxurl,
			post_id  : post_id,
			nonce    : groupData.nonce,
			group_id : groupData.groupid
		};

		$.post( l10n.ajaxurl, params, function( response ) {
			if ( ! response.success ) {
				deleteFail();

				if ( response.data ) {
					app.logError( response.data );
				}
			}
		} ).fail( deleteFail );
	};

	app.logError = function() {
		app.logError.history = app.logError.history || [];
		app.logError.history.push( arguments );
		if ( window.console ) {
			window.console.error( Array.prototype.slice.call( arguments) );
		}
	};

	$( app.init );

} )( window, document, jQuery, window.CMB2Mapl10n, window.CMB2Map );
