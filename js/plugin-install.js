(function( $, ghps ){

	// Remove default Version and Rating columns
	$('#wpbody-content th,td').filter('.column-version,.column-rating').remove();

	// Update Details links
	if ( undefined !== ghps.plugins && ghps.plugins.length > 0 ) {
		
		$links = $('div.action-links a.thickbox');
		var url;

		for (var i = 0; i < ghps.plugins.length; i++) {
			url = ghps.plugins[i].slug;
			$( $links.get( i ) ).attr('href', url).attr('target', '_blank').removeClass('thickbox');
		}
		
	}

})( jQuery, GHPSGitResponse );