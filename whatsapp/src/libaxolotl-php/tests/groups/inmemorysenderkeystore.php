<?php

require_once __DIR__.'/../../groups/state/SenderKeyStore.php';
require_once __DIR__.'/../../groups/state/SenderKeyRecord.php';

class inmemorysenderkeystore extends SenderKeyStore
{
    protected $store;

    public function InMemorySenderKeyStore()
    {
        $this->store = [];
    }

    public function storeSenderKey($senderKeyId, $senderKeyRecord)
    {
        $this->store[$senderKeyId] = $senderKeyRecord;
    }

    public function loadSenderKey($senderKeyId)
    {
        if (isset($this->store[$senderKeyId])) {
            return new SenderKeyRecord($this->store[$senderKeyId]->serialize());
        }

        return new SenderKeyRecord();
    }
}
