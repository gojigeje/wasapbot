<?php

require_once '../src/Registration.php';

$debug = true;

echo "####################\n";
echo "#                  #\n";
echo "# WA Register Tool #\n";
echo "#                  #\n";
echo "####################\n";

echo "\n\nUsername (country code + number, do not use + or 00): ";
$username = str_replace('+', '', trim(fgets(STDIN)));
if (!preg_match('!^\d+$!', $username)) {
    echo "Wrong number. Do NOT use '+' or '00' before your number\n";
    exit(0);
}
$identityExists = file_exists("../src/wadata/id.$username.dat");

$w = new Registration($username, $debug);

if (!$identityExists) {
    echo "\n\nType sms or voice: ";
    $option = fgets(STDIN);

    try {
        $w->codeRequest(trim($option));
    } catch (Exception $e) {
        echo $e->getMessage()."\n";
        exit(0);
    }

    echo "\n\nEnter the received code: ";
    $code = str_replace('-', '', fgets(STDIN));

    try {
        $result = $w->codeRegister(trim($code));
        echo "\nYour username is: ".$result->login."\n";
        echo 'Your password is: '.$result->pw."\n";
    } catch (Exception $e) {
        echo $e->getMessage()."\n";
        exit(0);
    }
} else {
    try {
        $result = $w->checkCredentials();
    } catch (Exception $e) {
        echo $e->getMessage()."\n";
        exit(0);
    }
}
