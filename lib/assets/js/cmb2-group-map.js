window.CMB2Map = window.CMB2Map || {};

( function( window, document, $, l10n, app, undefined ) {
	'use strict';

	/**
	 * Kicked off when jQuery is ready.
	 *
	 * @since  0.1.0
	 */
	app.init = function() {
		if ( ! window.cmb2_post_search ) {
			return app.logError( 'CMB2 Post Search Field is required! https://github.com/WebDevStudios/CMB2-Post-Search-field' );
		}

		// Make sure window.cmb2_post_search is setup
		setTimeout( app.overridePostSearch, 500 );

		$( '.cmb2-group-map-group' )
			// If removing a row, check if post should be deleted as well.
			.on( 'click', '.cmb-remove-group-row', app.maybeDelete )
			// Move the post-search-field's button to somewhere visible
			.find( '.cmb-repeatable-grouping' ).each( app.moveButtons );

		// And setup the click handler for that button.
		$( document.body ).on( 'click', '.cmb2-mapping-select, .cmb2-group-map-id-wrap', app.openSearch );
	};

	/**
	 * Moves the cmb-post-search button and input next to the remove button
	 *
	 * @since  0.1.0
	 */
	app.moveButtons = function() {
		var $this = $( this );
		var $id_row = $this.find( '.cmb2-group-map-id' );

		var $btn = $id_row.find( '.cmb2-post-search-button' ).addClass( 'button cmb2-mapping-select' );
		var $input = $id_row.find( 'input[type="text"]' ).prop( 'readonly', 1 );
		var $span = $( '<span class="cmb2-group-map-id-wrap">'+ $input.attr( 'title' ) +'</span>' );
		$span.append( app.sizeInput( $input ) );

		$this.find( '.button.cmb-remove-group-row' ).after( $btn ).after( $span );
		$id_row.remove();
	};

	/**
	 * Opens the search modal. Is triggered when clicking on the search button/input.
	 *
	 * @since  0.1.0
	 *
	 * @param  {object} evt Click event object.
	 */
	app.openSearch = function( evt ) {
		var search  = window.cmb2_post_search;
		var $this   = $( this );
		// var $grouping = $this.parents( '.cmb-repeatable-grouping' );
		var $input = $this.hasClass( 'cmb2-group-map-id-wrap' ) ? $this.find( 'input' ) : $this.prev().find( 'input' );

		search.$idInput = $input;

		// Setup our variables from the field data
		$.extend( search, search.$idInput.data( 'search' ) );

		search.trigger( 'open' );
	};

	/**
	 * Handles overriding the cmb2_post_search handleSelected method,
	 * new method fetches post data to populate group field inputs.
	 *
	 * @since  0.1.0
	 */
	app.overridePostSearch = function() {
		app.handleSelected = window.cmb2_post_search.handleSelected;

		// once a post is selected...
		window.cmb2_post_search.handleSelected = function( checked ) {
			if ( this.$idInput.hasClass( 'cmb2-group-map-data' ) ) {

				// ajax-grab the data we need
				app.get_and_set_post_data( checked[0], this.$idInput );
				// Cache previous value in case we need to reset it.
				app.cachePrev = this.$idInput.val();
			}

			// Fire standard method to update the post-search field's value.
			app.handleSelected.call( window.cmb2_post_search, checked );
			app.sizeInput( this.$idInput );
		};
	};

	/**
	 * Resets the last-touched post search field's value, used when something went wrong.
	 *
	 * @since  0.1.0
	 */
	app.resetPostSearchField = function() {
		window.cmb2_post_search.$idInput.val( app.cachePrev );
		app.sizeInput( window.cmb2_post_search.$idInput );
	};

	/**
	 * Fetches post data to populate group field inputs.
	 *
	 * @since 0.1.0
	 *
	 * @param {int}    post_id Post ID to get the data for.
	 * @param {object} $input  jQuery object for cmb2_post_search field input
	 */
	app.get_and_set_post_data = function( post_id, $input ) {
		var params = {
			action    : 'cmb2_group_map_get_post_data',
			ajaxurl   : l10n.ajaxurl,
			post_id   : post_id,
			host_id   : document.getElementById( 'post_ID' ).value,
			fieldName : $input.attr( 'name' )
		};

		var $group = $input.parents( '.cmb2-group-map-group .cmb-repeatable-grouping' );

		// Ajax success handler
		var updateInputs = function( key, input ) {
			var $input = $( input.html );
			var $replace = {};
			var classesAttr;
			var nameAttr;
			var idAttr;

			// Special handling for certain types.
			if ( 'colorpicker' === input.type ) {
				$replace = $group.find( '.wp-picker-container' );
			}
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

			// And fire CMB2's cleanup
			window.CMB2.afterRowInsert( $group );
		};

		// Fire ajax action to fetch new post/data.
		// If it fails, reset the post search field.
		$.post( l10n.ajaxurl, params, function( response ) {
			if ( ! response.success || ! response.data ) {
				app.resetPostSearchField();
				return app.logError( response );
			}

			$.each( response.data, updateInputs );

		} ).fail( app.resetPostSearchField );
	};

	/**
	 * When clicking "remove", check if user intends to delete
	 * associated post.
	 *
	 * @since  0.1.0
	 *
	 * @param  {object} evt Click event object
	 */
	app.maybeDelete = function( evt ) {
		evt.preventDefault();

		// Check with user.. Delete the post as well?
		if ( window.confirm( l10n.strings.delete_permanent ) ) {
			app.doDelete( $( this ) );
		}
	};

	app.doDelete = function( $btn ) {
		var $groupWrap = $btn.parents( '.cmb2-group-map-group' );
		var groupData  = $groupWrap.data();
		var $group     = $groupWrap.find( '.cmb-repeatable-grouping' );
		var post_id    = $group.find( '.cmb2-group-map-data' ).val();

		var deleteFail = function() {
			app.logError( 'Sorry! unable to delete '+ post_id +'!' );
		};

		// Check if requirements are all here.
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

	/**
	 * Set the size of the input to the length of its value.
	 *
	 * @since  0.1.0
	 *
	 * @param  {object} $input jQuery object for the input.
	 *
	 * @return {object}        jQuery object for the input.
	 */
	app.sizeInput = function( $input ) {
		var size = $input.val().length;
		// Set width of input to the size of its value or 4.
		return $input.prop( 'size', size > 4 ? size : 4 );
	};

	/**
	 * Logs errors to the console.
	 *
	 * @since  0.1.0
	 */
	app.logError = function() {
		app.logError.history = app.logError.history || [];
		app.logError.history.push( arguments );
		if ( window.console ) {
			window.console.error( Array.prototype.slice.call( arguments) );
		}
	};

	// kick it off.
	$( app.init );

} )( window, document, jQuery, window.CMB2Mapl10n, window.CMB2Map );
