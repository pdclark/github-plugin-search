(function( $, ghps ){

	var $table = $('table.wp-list-table');

	// Remove default Version and Rating columns
	$table.find('th,td').filter('.column-version,.column-rating').remove();

	if ( undefined !== ghps.plugins && ghps.plugins.length > 0 ) {
		
		// Update Details links
		$links = $table.find('div.action-links a.thickbox');
		var url;

		for (var i = 0; i < ghps.plugins.length; i++) {
			url = ghps.plugins[i].slug;
			$( $links.get( i ) )
				.attr('href', url).attr('target', '_blank')
				.text('View on GitHub')
				.removeClass('thickbox');
		}

		// Add Authors column
		$table.find('thead,tfoot').find('tr th:first').after( '<th id="author" class="manage-column column-author">Author</th>' );

		$rows = $table.find( 'tbody tr' );
		var plugin;
		var $td;

		for (var ii = 0; ii < ghps.plugins.length; ii++) {
			plugin = ghps.plugins[ii];

			$td = $( '<td>' ).addClass('author column-author');

			$td.html( '<a href="' + plugin.author_profile + '" target="_blank"><img src="' + plugin.author_gravatar + '" />' + plugin.author + '</a>' );

			$( $rows.get( ii ) ).find('td:first').after( $td );
		}
		
	}

})( jQuery, GHPSGitResponse );