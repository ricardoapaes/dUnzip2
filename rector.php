<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->withPhpSets(false, false, false, false, false, false, false, false, false, true)
	->withPreparedSets(true, true, true, true, true);
