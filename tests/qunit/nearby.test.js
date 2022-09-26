( function () {
	// Truncated response adapted from https://en.m.wikipedia.org/w/api.php?action=query&format=json&formatversion=2&prop=coordinates|pageprops|pageprops|pageimages|description&colimit=max&generator=geosearch&ggsradius=10000&ggsnamespace=0&ggslimit=50&ggscoord=43.7383|7.4137&ppprop=displaytitle&piprop=thumbnail&pithumbsize=150&pilimit=50 . Replace with live data.
	const dummyGeosearchResponse = {
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
				},
				{
					pageid: 675150,
					ns: 0,
					title: '2004 Monaco Grand Prix',
					index: 1,
					coordinates: [
						{
							lat: 43.73,
							lon: 7.42,
							primary: true,
							globe: 'earth'
						}
					]
				},
				{
					pageid: 123456,
					ns: 0,
					title: 'No Coordinates',
					index: 1
				}
			]
		}
	};

	QUnit.module( 'ext.kartographer.dialog.nearby', QUnit.newMwEnvironment( {
		beforeEach() {
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;
		}
	} ) );

	QUnit.test( 'Executes a valid geosearch request', function ( assert ) {
		this.server.respond(
			[ 200, { 'Content-Type': 'application/json' }, JSON.stringify( dummyGeosearchResponse ) ]
		);

		const Nearby = require( 'ext.kartographer.dialog' ).private.Nearby;
		const bounds = L.latLngBounds( L.latLng( 40.712, -74.227 ), L.latLng( 40.774, -74.125 ) );
		const zoom = 14;
		const done = assert.async();

		const expectedApiUrl = mw.config.get( 'wgScriptPath' ) + '/api.php?action=query&format=json&formatversion=2&prop=coordinates%7Cpageprops%7Cpageimages%7Cdescription&colimit=max&generator=search&gsrsearch=nearcoord%3A5500m%2C40.74%2C-74.18&gsrnamespace=0&gsrlimit=50&ppprop=displaytitle&piprop=thumbnail&pithumbsize=300&pilimit=50';

		new Nearby().fetch( bounds, zoom ).then( ( nearbyResults ) => {
			assert.deepEqual( nearbyResults, dummyGeosearchResponse );

			const requests = this.server.requests;
			assert.strictEqual( requests.length, 1 );
			assert.strictEqual( requests[ 0 ].url, expectedApiUrl );
			done();
		} );
	} );

	QUnit.test( 'Converts valid geosearch response', function ( assert ) {
		const Nearby = require( 'ext.kartographer.dialog' ).private.Nearby;
		const expectedGeoJSON = [
			{
				geometry: {
					coordinates: [
						7.1,
						43.2
					],
					type: 'Point'
				},
				properties: {
					title: 'Monaco',
					description: 'City-state and microstate on the French Riviera',
					thumbnail: {
						source: 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ea/Flag_of_Monaco.svg/150px-Flag_of_Monaco.svg.png',
						width: 150,
						height: 120
					},
					'marker-color': '0000ff'
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
					title: 'La Condamine',
					description: 'Ward of Monaco',
					thumbnail: {
						source: 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0d/Monaco_-_Port_en_d%C3%A9cembre.jpg/150px-Monaco_-_Port_en_d%C3%A9cembre.jpg',
						width: 150,
						height: 100
					},
					'marker-color': '0000ff'
				},
				type: 'Feature'
			},
			{
				geometry: {
					coordinates: [
						7.42,
						43.73
					],
					type: 'Point'
				},
				properties: {
					title: '2004 Monaco Grand Prix',
					description: undefined,
					thumbnail: undefined,
					'marker-color': '0000ff'
				},
				type: 'Feature'
			}
		];

		assert.deepEqual(
			new Nearby().convertGeosearchToGeoJSON( dummyGeosearchResponse ),
			expectedGeoJSON
		);
	} );
}() );
