<?php
abstract class SenderKeyStore {
    abstract function storeSenderKey ($senderKeyId, $record); // [String senderKeyId, SenderKeyRecord record]
    abstract function loadSenderKey ($senderKeyId); // [String senderKeyId]
}
