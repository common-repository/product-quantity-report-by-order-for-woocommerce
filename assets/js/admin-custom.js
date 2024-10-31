jQuery( function( $ ) {
	$( "#wpwo-from-date" ).datepicker({
		dateFormat: 'dd-mm-yy',
		onClose: function( selectedDate ) {
			$( "#wpwo-to-date" ).datepicker( "option", "minDate", selectedDate );
		},
		constrainInput: true
    });
    $( "#wpwo-to-date" ).datepicker({
		dateFormat: 'dd-mm-yy',
		onClose: function( selectedDate ) {
			$( "#wpwo-from-date" ).datepicker( "option", "maxDate", selectedDate );
		}
    });

	$( '.wpwo-datepicker' ).datepicker({ 
		changeMonth: true
	});
});