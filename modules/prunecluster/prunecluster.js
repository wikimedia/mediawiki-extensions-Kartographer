/* globals PruneCluster, PruneClusterForLeaflet */
/**
 * Exports the PruneCluster library.
 *
 * See [PruneCluster](https://github.com/SINTEF-9012/PruneCluster)
 * documentation for more details about this plugin.
 *
 * @alias PruneCluster
 * @alias ext.kartographer.prunecluster
 * @class Kartographer.PruneCluster
 * @singleton
 */
module.exports = {
	/**
	 * @type {PruneCluster}
	 */
	PruneCluster: PruneCluster,

	/**
	 * See [PruneClusterForLeaflet](https://github.com/SINTEF-9012/PruneCluster#pruneclusterforleaflet-constructor)
	 * documentation for more details about this class.
	 *
	 * @type {PruneClusterForLeaflet}
	 */
	PruneClusterForLeaflet: PruneClusterForLeaflet
};
