<?php

// Core ships no VCS adapter. A VCS module contributes `adapters.<name>.class` (git-vcs-support
// registers `git`); `default_adapter` names the one RepositoryAdapterInterface resolves to.
return [
    'default_adapter' => 'git',
    'adapters'        => [],
];
