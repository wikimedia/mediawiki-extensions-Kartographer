( function () {
	QUnit.module( 'ext.kartographer.box.nearby', QUnit.newMwEnvironment() );

	QUnit.test( 'Converts valid geosearch response', function ( assert ) {
		// Truncated response adapted from https://en.m.wikipedia.org/w/api.php?action=query&format=json&formatversion=2&prop=coordinates|pageprops|pageprops|pageimages|description&colimit=max&generator=geosearch&ggsradius=10000&ggsnamespace=0&ggslimit=50&ggscoord=43.7383|7.4137&ppprop=displaytitle&piprop=thumbnail&pithumbsize=150&pilimit=50 . Replace with live data.
		var dummyGeosearchResponse = {
			query: {
				pages: [
					{
						pageid: 19261,
						ns: 0,
						title: 'Monaco',
						index: -1,
						coordinates: [
							{
								lat: 43.2,
								lon: 7.1,
								primary: true,
								globe: 'earth'
							}
						],
						thumbnail: {
							source: 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ea/Flag_of_Monaco.svg/150px-Flag_of_Monaco.svg.png',
							width: 150,
							height: 120
						},
						description: 'City-state and microstate on the French Riviera',
						descriptionsource: 'local'
					},
					{
						pageid: 658766,
						ns: 0,
						title: 'La Condamine',
						index: 0,
						coordinates: [
							{
								lat: 43.7,
								lon: 7.4,
								primary: true,
								globe: 'earth'
							}
						],
						thumbnail: {
							source: 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0d/Monaco_-_Port_en_d%C3%A9cembre.jpg/150px-Monaco_-_Port_en_d%C3%A9cembre.jpg',
							width: 150,
							height: 100
						},
						description: 'Ward of Monaco',
						descriptionsource: 'local'
					}
				]
			}
		};

		var Nearby = require( 'ext.kartographer.box' ).private.Nearby;
		var expectedGeojson = [
			{
				geometry: {
					coordinates: [
						7.1,
						43.2
					],
					type: 'Point'
				},
				properties: {
					name: 'Monaco'
				},
				type: 'Feature'
			},
			{
				geometry: {
					coordinates: [
						7.4,
						43.7
					],
					type: 'Point'
				},
				properties: {
					name: 'La Condamine'
				},
				type: 'Feature'
			}
		];

		assert.deepEqual(
			Nearby.convertGeosearchToGeojson( dummyGeosearchResponse ),
			expectedGeojson
		);
	} );
}() );
