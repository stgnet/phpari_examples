<?php

header('content-type: text/plain');
if (!file_exists('composer.phar')) {
	echo "Downloading composer...\n";
	$composer = file_get_contents('https://getcomposer.org/installer');
	if (!$composer) {
		die("Unable to download composer\n");
	}
	file_put_contents('composer-installer.php', $composer);
	echo "Installing composer...\n";
	passthru('php composer-installer.php');
	unlink('composer-installer.php');
	echo "\n";
	if (!file_exists('composer.phar')) {
		die("After install composer.phar is missing?\n");
	}
}
echo "Installing dependencies...\n";
passthru('ls');
passthru('pwd');
passthru('php composer.phar install');
