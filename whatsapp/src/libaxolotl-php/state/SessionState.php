<?php
class SessionState{
    protected $sessionStructure;
    public function SessionState($session = null){

        if ($session == null){
            $this->sessionStructure = new Textsecure_SessionStructure();
        }
        else if($session instanceof SessionState){
            $this->sessionStructure = new Textsecure_SessionStructure();
            $this->sessionStructure->parseFromString($session->getStructure()->serializeToString());
        }
        else{
            $this->sessionStructure = $session;
        }
    }
    public function getStructure(){
        return $this->sessionStructure;
    }
    public function getAliceBaseKey(){
        return $this->sessionStructure->getAliceBaseKey();
    }
    public function setAliceBaseKey($aliceBaseKey){
        $this->sessionStructure->setAliceBaseKey($aliceBaseKey);
    }

    public function setSessionVersion($version){
        $this->sessionStructure->setSessionVersion($version);
    }

    public function getSessionVersion(){
        $sessionVersion = $this->sessionStructure->getSessionVersion();
        return $sessionVersion == 0?2:$sessionVersion;
    }
    public function setRemoteIdentityKey($identityKey){
        $this->sessionStructure->setRemoteIdentityPublic($identityKey->serialize());
    }

    public function setLocalIdentityKey($identityKey){
        $this->sessionStructure->setLocalIdentityPublic($identityKey->serialize());
    }


    public function getRemoteIdentityKey(){
        if($this->sessionStructure->getRemoteIdentityPublic() == null)
            return null;
        return new IdentityKey($this->sessionStructure->getRemoteIdentityPublic(), 0);
    }
    public function getLocalIdentityKey(){
        return new IdentityKey($this->sessionStructure->getLocalIdentityPublic(), 0);
    }
    public function getPreviousCounter(){
        return $this->sessionStructure->getPreviousCounter();
    }
    public function setPreviousCounter($previousCounter){
        $this->sessionStructure->setPreviousCounter($previousCounter);
    }

    public function getRootKey(){
        return new RootKey(HKDF::createFor($this->getSessionVersion()), $this->sessionStructure->getRootKey());
    }
    public function setRootKey($rootKey){
        $this->sessionStructure->setRootKey($rootKey->getKeyBytes());
    }


    public function getSenderRatchetKey(){
        return Curve::decodePoint($this->sessionStructure->getSenderChain()->getSenderRatchetKey(), 0);
    }
    public function getSenderRatchetKeyPair(){
        $publicKey = $this->getSenderRatchetKey();
        $privateKey = Curve::decodePrivatePoint($this->sessionStructure->getSenderChain()->getSenderRatchetKeyPrivate());

        return new ECKeyPair($publicKey, $privateKey);
    }
    public function hasReceiverChain($ECPublickKey_senderEphemeral){
        return $this->getReceiverChain($ECPublickKey_senderEphemeral) != null;
    }
    public function hasSenderChain(){
        return $this->sessionStructure->getSenderChain() != null;
    }
    public function getReceiverChain($ECPublickKey_senderEphemeral){
        $receiverChains = $this->sessionStructure->getReceiverChains();
        $index = 0;
        foreach($receiverChains as $receiverChain){
            $chainSenderRatchetKey = Curve::decodePoint($receiverChain->getSenderRatchetKey(), 0);
            if ($chainSenderRatchetKey == $ECPublickKey_senderEphemeral){
                return [$receiverChain, $index];
            }
            $index += 1;
        }
    }
    public function getReceiverChainKey($ECPublicKey_senderEphemeral){
        $receiverChainAndIndex = $this->getReceiverChain($ECPublicKey_senderEphemeral);
        $receiverChain = $receiverChainAndIndex[0];
        if ($receiverChain == null)
            return null;

        return new ChainKey(HKDF::createFor($this->getSessionVersion()),
                        $receiverChain->getChainKey()->getKey(),
                        $receiverChain->getChainKey()->getIndex());
    }
    public function addReceiverChain($ECPublickKey_senderRatchetKey, $chainKey){
        $senderRatchetKey = $ECPublickKey_senderRatchetKey;


        $chain = new Textsecure_SessionStructure_Chain();
        $chain->setSenderRatchetKey($senderRatchetKey->serialize());
        $chain->setChainKey(new Textsecure_SessionStructure_Chain_ChainKey());
        $chain->getChainKey()->setKey($chainKey->getKey());
        $chain->getChainKey()->setIndex($chainKey->getIndex());

        $this->sessionStructure->appendReceiverChains($chain);

        if (count($this->sessionStructure->getReceiverChains()) > 5){
            $chains = $this->sessionStructure->getReceiverChains();
            $chains = array_slice($chains, 1);
            $this->sessionStructure->clearReceiverChains();
            foreach($chains as $chain)
                $this->sessionStructure->appendReceiverChains($chain);
            //$this->sessionStructure->setReceiverChains($chains);
        }

    }
    public function setSenderChain($ECKeyPair_senderRatchetKeyPair, $chainKey){
        $senderRatchetKeyPair = $ECKeyPair_senderRatchetKeyPair;

        $senderChain = new Textsecure_SessionStructure_Chain();
        $this->sessionStructure->setSenderChain($senderChain);
        $this->sessionStructure->getSenderChain()->setSenderRatchetKey($senderRatchetKeyPair->getPublicKey()->serialize());
        $this->sessionStructure->getSenderChain()->setSenderRatchetKeyPrivate($senderRatchetKeyPair->getPrivateKey()->serialize());
        $this->sessionStructure->getSenderChain()->setChainKey(new Textsecure_SessionStructure_Chain_ChainKey());
        $this->sessionStructure->getSenderChain()->getChainKey()->setKey($chainKey->getKey());
        $this->sessionStructure->getSenderChain()->getChainKey()->setIndex($chainKey->getIndex());
    }
    public function getSenderChainKey(){
        $chainKeyStructure = $this->sessionStructure->getSenderChain()->getChainKey();
        return new ChainKey(HKDF::createFor($this->getSessionVersion()),
                        $chainKeyStructure->getKey(), $chainKeyStructure->getIndex());
    }
    public function setSenderChainKey($ChainKey_nextChainKey){
        $nextChainKey = $ChainKey_nextChainKey;

        $this->sessionStructure->getSenderChain()->getChainKey()->setKey($nextChainKey->getKey());
        $this->sessionStructure->getSenderChain()->getChainKey()->setIndex($nextChainKey->getIndex());

    }
    public function hasMessageKeys($ECPublickKey_senderEphemeral, $counter){
        $senderEphemeral = $ECPublickKey_senderEphemeral;
        $chainAndIndex = $this->getReceiverChain($senderEphemeral);
        $chain = $chainAndIndex[0];
        if ($chain == null)
            return false;

        $messageKeyList = $chain->getMessageKeys();
        foreach($messageKeyList as $messageKey){
            if($messageKey->getIndex() == $counter)
                return true;
        }

        return false;
    }
    public function removeMessageKeys($ECPublicKey_senderEphemeral, $counter){
        $senderEphemeral = $ECPublicKey_senderEphemeral;
        $chainAndIndex = $this->getReceiverChain($senderEphemeral);
        $chain = $chainAndIndex[0];
        if($chain  == null)
            return null;

        $messageKeyList = $chain->getMessageKeys();
        $result = null;

        for ($i =0;$i<count($messageKeyList);$i++)
        {
            $messageKey = $messageKeyList[$i];
            if ($messageKey->getIndex() == $counter){
                $result = new MessageKeys($messageKey->getCipherKey(), $messageKey->getMacKey(), $messageKey->getIv(), $messageKey->getIndex());
                unset($messageKeyList[$i]);
                //del messageKeyList[i] <- 1. is a copy of the original array so go to 2
                break;
            }
        }
        $chain->clearMessageKeys();
        foreach($messageKeyList as $msgKey){
          $chain->appendMessageKeys($msgKey);
        }
        $this->sessionStructure->getReceiverChains()[$chainAndIndex[1]]->parseFromString($chain->serializeToString());

        return $result;
    }
    public function setMessageKeys($ECPublicKey_senderEphemeral, $messageKeys){
        $senderEphemeral = $ECPublicKey_senderEphemeral;
        $chainAndIndex = $this->getReceiverChain($senderEphemeral);
        $chain = $chainAndIndex[0];
        $messageKeyStructure = new Textsecure_SessionStructure_Chain_MessageKey();//$chain->messageKeys.add() #storageprotos.SessionStructure.Chain.MessageKey()
        $messageKeyStructure->setCipherKey($messageKeys->getCipherKey());
        $messageKeyStructure->setMacKey($messageKeys->getMacKey());
        $messageKeyStructure->setIndex($messageKeys->getCounter());
        $messageKeyStructure->setIv($messageKeys->getIv());
        $chain->appendMessageKeys($messageKeyStructure); //$chain->messageKeys.add()


        #chain.messageKeys.append(messageKeyStructure)

        $this->sessionStructure->getReceiverChains()[$chainAndIndex[1]]->parseFromString($chain->serializeToString());
    }
    public function setReceiverChainKey($ECPublicKey_senderEphemeral, $chainKey){
        $senderEphemeral = $ECPublicKey_senderEphemeral;
        $chainAndIndex = $this->getReceiverChain($senderEphemeral);
        $chain = $chainAndIndex[0];
        $chain->getChainKey()->setKey($chainKey->getKey());
        $chain->getChainKey()->setIndex($chainKey->getIndex());

        #$this->sessionStructure.receiverChains[chainAndIndex[1]].ClearField()
        $this->sessionStructure->getReceiverChains()[$chainAndIndex[1]]->parseFromString($chain->serializeToString());
    }

    public function setPendingKeyExchange($sequence, $ourBaseKey, $ourRatchetKey, $ourIdentityKey){
        /*
        :type sequence: int
        :type ourBaseKey: ECKeyPair
        :type ourRatchetKey: ECKeyPair
        :type  ourIdentityKey: IdentityKeyPair
        */

        $structure = $this->sessionStructure->getPendingKeyExchange();
        if($structure == null) $structure = new Textsecure_SessionStructure_PendingKeyExchange();
        $structure->setSequence($sequence);
        $structure->setLocalBaseKey($ourBaseKey->getPublicKey()->serialize());
        $structure->setLocalBaseKeyPrivate($ourBaseKey->getPrivateKey()->serialize());
        $structure->setLocalRatchetKey($ourRatchetKey->getPublicKey()->serialize());
        $structure->setLocalRatchetKeyPrivate($ourRatchetKey->getPrivateKey()->serialize());
        $structure->setLocalIdentityKey($ourIdentityKey->getPublicKey()->serialize());
        $structure->setLocalIdentityKeyPrivate($ourIdentityKey->getPrivateKey()->serialize());

        $this->sessionStructure->setPendingKeyExchange($structure); // should be the same as merge since it only have string/int fields and none of them repeated
    }
    public function getPendingKeyExchangeSequence(){
        return $this->sessionStructure->getPendingKeyExchange()->getSequence();
    }
    public function getPendingKeyExchangeBaseKey(){
        $publicKey = Curve::decodePoint($this->sessionStructure->getPendingKeyExchange()->getLocalBaseKey(), 0);
        $privateKey = Curve::decodePrivatePoint($this->sessionStructure->getPendingKeyExchange()->getLocalBaseKeyPrivate());
        return new ECKeyPair($publicKey, $privateKey);
    }

    public function getPendingKeyExchangeRatchetKey(){
        $publicKey = Curve::decodePoint($this->sessionStructure->getPendingKeyExchange()->getLocalRatchetKey(), 0);
        $privateKey = Curve::decodePrivatePoint($this->sessionStructure->getPendingKeyExchange()->getLocalRatchetKeyPrivate());
        return new ECKeyPair($publicKey, $privateKey);

    }
    public function getPendingKeyExchangeIdentityKey(){
        $publicKey = new IdentityKey($this->sessionStructure->getPendingKeyExchange()->getLocalIdentityKey(), 0);

        $privateKey = Curve::decodePrivatePoint($this->sessionStructure->getPendingKeyExchange()->getLocalIdentityKeyPrivate());
        return new IdentityKeyPair($publicKey, $privateKey);
    }
    public function hasPendingKeyExchange(){
        return $this->sessionStructure->getPendingKeyExchange() != null;
    }

    public function setUnacknowledgedPreKeyMessage($preKeyId, $signedPreKeyId, $baseKey){
        /*
        :type preKeyId: int
        :type signedPreKeyId: int
        :type baseKey: ECPublicKey
        */
        if(!$this->hasUnacknowledgedPreKeyMessage())
            $this->sessionStructure->setPendingPreKey(new Textsecure_SessionStructure_PendingPreKey());
        $this->sessionStructure->getPendingPreKey()->setSignedPreKeyId($signedPreKeyId);
        $this->sessionStructure->getPendingPreKey()->setBaseKey($baseKey->serialize());

        if ($preKeyId != null)
            $this->sessionStructure->getPendingPreKey()->setPreKeyId($preKeyId);
    }
    public function hasUnacknowledgedPreKeyMessage(){
        return $this->sessionStructure->getPendingPreKey() != null;
    }
    public function getUnacknowledgedPreKeyMessageItems(){
        $preKeyId = null;
        if($this->sessionStructure->getPendingPreKey()->getPreKeyId() != null){
            $preKeyId = $this->sessionStructure->getPendingPreKey()->getPreKeyId();
        }

        return new UnacknowledgedPreKeyMessageItems($preKeyId,
                                     $this->sessionStructure->getPendingPreKey()->getSignedPreKeyId(),
                                     Curve::decodePoint($this->sessionStructure->getPendingPreKey()->getBaseKey(), 0));
    }
    public function clearUnacknowledgedPreKeyMessage(){
        $this->sessionStructure->clearPendingPreKey();
    }
    public function setRemoteRegistrationId($registrationId){
        $this->sessionStructure->setRemoteRegistrationId($registrationId);
    }

    public function getRemoteRegistrationId($registrationId){
        return $this->sessionStructure->getRemoteRegistrationId();
    }
    public function setLocalRegistrationId($registrationId){
        $this->sessionStructure->setLocalRegistrationId($registrationId);
    }
    public function getLocalRegistrationId(){
        return $this->sessionStructure->getLocalRegistrationId();
    }
    public function serialize(){
        return $this->sessionStructure->serializeToString();
    }

}
class UnacknowledgedPreKeyMessageItems{
    public function UnacknowledgedPreKeyMessageItems($preKeyId, $signedPreKeyId, $baseKey){
        /*
        :type preKeyId: int
        :type signedPreKeyId: int
        :type baseKey: ECPublicKey
        */
        $this->preKeyId       = $preKeyId;
        $this->signedPreKeyId = $signedPreKeyId;
        $this->baseKey        = $baseKey;
    }

    public function getPreKeyId(){
        return $this->preKeyId;
    }
    public function getSignedPreKeyId(){
        return $this->signedPreKeyId;
    }
    public function getBaseKey(){
        return $this->baseKey;
    }
}
