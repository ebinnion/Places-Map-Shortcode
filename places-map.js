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
				url: place.url,
				label: place.name
			} );
			google.maps.event.addListener( marker, 'click', function() {
				window.location.href = this.url;
			} );
			markers.push( marker );

		}
	}

	var markerCluster = new MarkerClusterer(
		map, 
		markers,
		{
			imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
		}
	);
}