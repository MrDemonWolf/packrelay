/**
 * PackRelay Admin JavaScript
 *
 * Handles placeholder variable insertion and reset email button.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

( function( $ ) {
	'use strict';

	/**
	 * Insert a placeholder variable at the cursor position in the target field.
	 */
	$( document ).on( 'click', '.packrelay-placeholder-btn', function( e ) {
		e.preventDefault();

		var targetId = $( this ).data( 'target' );
		var value    = $( this ).data( 'value' );
		var $target  = $( '#' + targetId );

		if ( ! $target.length ) {
			return;
		}

		var el    = $target[0];
		var start = el.selectionStart;
		var end   = el.selectionEnd;
		var text  = $target.val();

		$target.val( text.substring( 0, start ) + value + text.substring( end ) );

		// Move cursor to after the inserted value.
		var newPos = start + value.length;
		el.selectionStart = newPos;
		el.selectionEnd   = newPos;
		$target.focus();
	} );

	/**
	 * Reset notification email to admin email.
	 */
	$( document ).on( 'click', '#packrelay-reset-email', function( e ) {
		e.preventDefault();

		if ( typeof packrelayAdmin !== 'undefined' && packrelayAdmin.adminEmail ) {
			$( '#packrelay_notification_email' ).val( packrelayAdmin.adminEmail );
		}
	} );

} )( jQuery );
