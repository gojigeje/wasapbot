<?php

require_once '../src/whatsprot.class.php';

$debug = false;

echo "####################\n";
echo "#                  #\n";
echo "#     WA RESET     #\n";
echo "#                  #\n";
echo "####################\n";

echo "\n\nUsername (country code + number without + or 00): ";
$username = trim(fgets(STDIN));

// Create a instance of WhastPort.
$w = new WhatsProt($username, '', $debug);

echo "\n\nYour accounts password: ";
$pw = trim(fgets(STDIN));

$w->connect();
try {
    $w->loginWithPassword($pw);
} catch (Exception $e) {
    echo "Failed to login, make sure your account is not blocked (use blockChecker.php) or check if your password is right\n\n";
    exit(0);
}

$w->sendRemoveAccount();

$i = 0;
for ($i; $i < 5; $i++) {
    $w->pollMessage();
}

$w->disconnect();

unlink("../src/wadata/id.$username.dat");
unlink("../src/wadata/nextChallenge.$username.dat");

echo "\n\n OK! Now use registerTool.php \n\n";
