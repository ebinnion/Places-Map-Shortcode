function initPlacesMap() {
	var myLatLng = {
		lat: parseFloat( placesMapConfig.initialCenterLat ),
		lng: parseFloat( placesMapConfig.initialCenterLon )
	};

	var map = new google.maps.Map( document.getElementById('map'), {
		zoom: parseInt( placesMapConfig.initialZoom, 10 ),
		center: myLatLng
	} );

	var markers = [];

	for ( var key in places ) {
		if ( places.hasOwnProperty( key ) ) {
			var place = places[ key ];
			var marker = new google.maps.Marker( {
				position: {
					'lat': parseFloat( place.lat ),
					'lng': parseFloat( place.lon )
				},
				id: key,
				url: place.url,
				label: place.name
			} );
			google.maps.event.addListener( marker, 'click', function() {
				jQuery.ajax({
					url: placesMapConfig.restUrl + this.id
				} ).done( function( data ) {
					jQuery( '#places-map-images' ).html( data );
				} );
			} );
			markers.push( marker );
		}
	}

	var markerCluster = new MarkerClusterer(
		map, 
		markers,
		{
			imagePath: placesMapConfig.clusterImagePath
		}
	);
}
