Conductor: Core
==============================================

# 1.0.0 (Unreleased)
- Renamed to Conductor
- Added DatabaseAdapterManager
- Merged DatabaseMetadataProviderInterface into DatabaseAdapterInterface
- Added exception handling around entire app
- Added FilesystemAdapterManager
- Added filesystem:ls, filesystem:copy, and filesystem:sync commands
- Added MountManager with SyncPlugin
- Added amphp/amp for parallel processing
- Applied parallel processing to SyncPlugin
- Removed composer requirements that were pushed to other repos
- Added Crypt commands and model
- Added posix PHP extension suggestion
- Added support for setting config on filesystems

# 0.1.0
- Initial build copied over from devops tool
