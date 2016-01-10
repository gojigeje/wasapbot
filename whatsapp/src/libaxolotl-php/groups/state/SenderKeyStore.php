<?php

abstract class SenderKeyStore
{
    abstract public function storeSenderKey($senderKeyId, $record);

 // [String senderKeyId, SenderKeyRecord record]

    abstract public function loadSenderKey($senderKeyId);

 // [String senderKeyId]
}
