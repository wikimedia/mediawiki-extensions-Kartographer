# Use OpenStack Swift as a vector tile storage
As part of the work to [modernize the vector-tile infrastructure](https://phabricator.wikimedia.org/T263854) supported by the Product Infrastructure team the maps infrastructure
should be able to use a scalable storage backend to allow storing and retrieving pregenerated vector tiles.

## Author
Yiannis Giannelos (jgiannelos@wikimedia.org)

## Date
Feb 18 2021

## Status
Accepted

## Context
Currently kartotherian/tilerator uses Cassandra DB as a storage for pregenerated tiles. Moving forward we are trying to modernize the stack by:

* Decoupling various stack components to a more modular design
* Re-use existing opensource technologies from the GIS ecosystem
* Enable client side rendering but at the same time keep server side rendering for compatibility

Cassandra was identified as a part of our infrastructure that was a potential pain point for maintenance from an SRE point of view.
After investigating our options it looks like Swift is a good option since:

* It's well supported because of its S3 api compatibility
* It's already maintained for other WMF projects and designed for scale
* We can consume it both for raster tiles rendering but also directly in the browser (client-side)
* SREs are confident to operate and maintain it

## Decision
Accepted (Feb 18 2021)

## Consequences
Cassandra DB support in Kartotherian is going to be phased out.
