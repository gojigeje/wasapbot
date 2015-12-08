<?php
require_once(__DIR__."/../InvalidKeyIdException.php");
abstract class PreKeyStore {
    abstract function loadPreKey ($preKeyId); // [int preKeyId]
    abstract function storePreKey ($preKeyId, $record); // [int preKeyId, PreKeyRecord record]
    abstract function containsPreKey ($preKeyId); // [int preKeyId]
    abstract function removePreKey ($preKeyId); // [int preKeyId]
}