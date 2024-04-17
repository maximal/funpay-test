<?php

use FpDbTest\Database;
use FpDbTest\DatabaseTest;

echo 'Running tests...', PHP_EOL;

spl_autoload_register(static function ($class) {
	$a = array_slice(explode('\\', $class), 1);
	if (!$a) {
		throw new Exception();
	}
	$filename = implode('/', [__DIR__, 'src', ...$a]) . '.php';
	require_once $filename;
});

$mysqli = @new mysqli(...getDatabaseCredentials());
if ($mysqli->connect_errno) {
	throw new Exception($mysqli->connect_error);
}

$db = new Database($mysqli);
$test = new DatabaseTest($db);
$test->testBuildQuery();

echo 'Tests OK', PHP_EOL;
exit(0);


function getDatabaseCredentials(): array
{
	return getenv('IN_DOCKER')
		? ['database', 'root', 'password', 'database', 3306]
		: ['127.0.0.1', 'root', 'password', 'database', 3306];
}
