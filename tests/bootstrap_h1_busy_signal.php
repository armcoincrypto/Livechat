<?php

declare(strict_types=1);

// Minimal bootstrap: load only the H1 busy-signal class (avoids live-release
// composer classmap hijacking worktree App\ sources).
require dirname(__DIR__).'/app/Console/Schedule/BestchangeCompilerBusySignal.php';
