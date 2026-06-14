<?php
// Copy this file to .config.php to get started.
return [
	'app_base_url' => 'https://www.someurl.com/', // should end in a trailing slash
	'enable_smtp' => false,
	'smtp' => [
		'username' => 'user',
		'password' => 'pass',
		'host' => 'something.smtp.net',
		'port' => 25,
		'from_email' => 'commie@example.com',
		'from_name' => 'Commie Bot'
	],
	'encryption_key' => '', // required: run vendor/bin/generate-defuse-key and copy value here
	'api_key' => '', // optional: only needed for the HTTP API; vendor/bin/generate-defuse-key can generate one
	// 'enable_rate_limit' => false, // not implemented; placeholder for future use
	// 'max_paste_bytes' => 0, // 0 = unlimited; not implemented; placeholder for future use
];