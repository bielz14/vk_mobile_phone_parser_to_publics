<?php

require_once 'vendor/autoload.php';

	// connect to database
	$mysqli = new mysqli('localhost', 'root', '', 'rbac');
	if (!empty($mysqli->connect_errno)) {
	    throw new \Exception($mysqli->connect_error, $mysqli->connect_errno);
	}
	 
	// create a bot
	$bot = new \TelegramBot\Api\Client('546927564:AAFJzy7vHWAqzEmoyQeurKwNDwtggh2s23E');
	// run, bot, run!

	$bot->command('start', function ($message) use ($bot) {
	    $answer = 'Добро пожаловать!';
	    $bot->sendMessage($message->getChat()->getId(), $answer);
	});

	$bot->sendMessage(377854547, 'Hello World!');

	//$bot->run();

