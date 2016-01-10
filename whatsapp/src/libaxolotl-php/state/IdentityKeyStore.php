<?php

require_once __DIR__.'/../IdentityKey.php';
require_once __DIR__.'/../IdentityKeyPair.php';
abstract class IdentityKeyStore
{
    abstract public function getIdentityKeyPair();

    abstract public function getLocalRegistrationId();

    abstract public function saveIdentity($recipientId, $identityKey);

 // [long recipientId, IdentityKey identityKey]

    abstract public function isTrustedIdentity($recipientId, $identityKey);

 // [long recipientId, IdentityKey identityKey]
}
