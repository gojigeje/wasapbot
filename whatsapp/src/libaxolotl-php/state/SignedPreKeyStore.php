<?php
require_once(__DIR__."/../InvalidKeyIdException.php");
abstract class SignedPreKeyStore {
    abstract function loadSignedPreKey ($signedPreKeyId); // [int signedPreKeyId]
    abstract function loadSignedPreKeys ();
    abstract function storeSignedPreKey ($signedPreKeyId, $record); // [int signedPreKeyId, SignedPreKeyRecord record]
    abstract function containsSignedPreKey ($signedPreKeyId); // [int signedPreKeyId]
    abstract function removeSignedPreKey ($signedPreKeyId); // [int signedPreKeyId]
}