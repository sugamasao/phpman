<?php

define('PHPMAN_APPENV','local');

\phpman\Conf::set('phpman.Dao','connection',array(
		'*'=>array()
));


\phpman\Conf::set('phpman.Log','disp',true);
\phpman\Conf::set('phpman.Log','level','warn');

\phpman\Conf::set('local.log.OneFile','path',__DIR__.'/work/output.log');
//\phpman\Log::set_module(new \local\log\OneFile());
