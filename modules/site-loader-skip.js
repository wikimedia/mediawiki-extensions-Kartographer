// This module has 'ext.kartographer.site' as a dependency.
// Skip if trying to load it would fail, e.g. because we're in safemode (T212645).
return !mw.loader.getState( 'ext.kartographer.site' );
