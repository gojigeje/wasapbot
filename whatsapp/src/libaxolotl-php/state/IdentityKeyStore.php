<?php
require_once(__DIR__."/../IdentityKey.php");
require_once(__DIR__."/../IdentityKeyPair.php");
abstract class IdentityKeyStore {
    abstract function getIdentityKeyPair ();
    abstract function getLocalRegistrationId ();
    abstract function saveIdentity ($recipientId, $identityKey); // [long recipientId, IdentityKey identityKey]
    abstract function isTrustedIdentity ($recipientId, $identityKey); // [long recipientId, IdentityKey identityKey]
}