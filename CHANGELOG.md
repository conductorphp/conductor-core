Conductor: Core
===============

# 0.9.6
- Added parallel download/upload of files if pcntl PHP extension is enabled

# 0.9.5
- Updated all shell commands to run through bash

# 0.9.4
- Fixed license per https://spdx.org/licenses/

# 0.9.3
- Added initial documentation structure
 
# 0.9.2
- Added consideration for shallow clone

# 0.9.1
- Fixed MountManager excludes/includes processing

# 0.9.0
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
- Added shell adapters and commands
- Fixed timeout issue when running shell commands that don't properly close stderr
- Added RepositoryAdapterInterface to make Conductor work with other version control systems

# 0.1.0
- Initial build copied over from devops tool
