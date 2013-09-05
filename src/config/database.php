<?php

return array(
	'connections' => array(

		'laravel-pdo-via-oci8' => array(
			'driver'   => 'pdo-via-oci8',
			'host'     => 'localhost',
            'port'     => '1521',
			'database' => 'database',
			'username' => 'root',
			'password' => '',
			'prefix'   => '',
		),

	),
);