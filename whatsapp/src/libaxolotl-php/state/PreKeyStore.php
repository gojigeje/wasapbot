<?php

require_once __DIR__.'/../InvalidKeyIdException.php';
abstract class PreKeyStore
{
    abstract public function loadPreKey($preKeyId);

 // [int preKeyId]

    abstract public function storePreKey($preKeyId, $record);

 // [int preKeyId, PreKeyRecord record]

    abstract public function containsPreKey($preKeyId);

 // [int preKeyId]

    abstract public function removePreKey($preKeyId);

 // [int preKeyId]
}
