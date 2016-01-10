<?php

require_once __DIR__.'/../state/AxolotlStore.php';
require_once __DIR__.'/inmemoryidentitykeystore.php';
require_once __DIR__.'/inmemoryprekeystore.php';
require_once __DIR__.'/inmemorysessionstore.php';
require_once __DIR__.'/inmemorysignedprekeystore.php';
class inmemoryaxolotlstore extends AxolotlStore
{
    protected $identityKeyStore;
    protected $preKeyStore;
    protected $signedPreKeyStore;
    protected $sessionStore;

    public function InMemoryAxolotlStore()
    {
        $this->identityKeyStore = new InMemoryIdentityKeyStore();
        $this->preKeyStore = new InMemoryPreKeyStore();
        $this->signedPreKeyStore = new InMemorySignedPreKeyStore();
        $this->sessionStore = new InMemorySessionStore();
    }

    public function getIdentityKeyPair()
    {
        return $this->identityKeyStore->getIdentityKeyPair();
    }

    public function getLocalRegistrationId()
    {
        return $this->identityKeyStore->getLocalRegistrationId();
    }

    public function saveIdentity($recepientId, $identityKey)
    {
        $this->identityKeyStore->saveIdentity($recepientId, $identityKey);
    }

    public function isTrustedIdentity($recepientId, $identityKey)
    {
        return $this->identityKeyStore->isTrustedIdentity($recepientId, $identityKey);
    }

    public function loadPreKey($preKeyId)
    {
        return $this->preKeyStore->loadPreKey($preKeyId);
    }

    public function storePreKey($preKeyId, $preKeyRecord)
    {
        $this->preKeyStore->storePreKey($preKeyId, $preKeyRecord);
    }

    public function containsPreKey($preKeyId)
    {
        return $this->preKeyStore->containsPreKey($preKeyId);
    }

    public function removePreKey($preKeyId)
    {
        $this->preKeyStore->removePreKey($preKeyId);
    }

    public function loadSession($recepientId, $deviceId)
    {
        return $this->sessionStore->loadSession($recepientId, $deviceId);
    }

    public function getSubDeviceSessions($recepientId)
    {
        return $this->sessionStore->getSubDeviceSessions($recepientId);
    }

    public function storeSession($recepientId, $deviceId, $sessionRecord)
    {
        $this->sessionStore->storeSession($recepientId, $deviceId, $sessionRecord);
    }

    public function containsSession($recepientId, $deviceId)
    {
        return $this->sessionStore->containsSession($recepientId, $deviceId);
    }

    public function deleteSession($recepientId, $deviceId)
    {
        $this->sessionStore->deleteSession($recepientId, $deviceId);
    }

    public function deleteAllSessions($recepientId)
    {
        $this->sessionStore->deleteAllSessions($recepientId);
    }

    public function loadSignedPreKey($signedPreKeyId)
    {
        return $this->signedPreKeyStore->loadSignedPreKey($signedPreKeyId);
    }

    public function loadSignedPreKeys()
    {
        return $this->signedPreKeyStore->loadSignedPreKeys();
    }

    public function storeSignedPreKey($signedPreKeyId, $signedPreKeyRecord)
    {
        $this->signedPreKeyStore->storeSignedPreKey($signedPreKeyId, $signedPreKeyRecord);
    }

    public function containsSignedPreKey($signedPreKeyId)
    {
        return $this->signedPreKeyStore->containsSignedPreKey($signedPreKeyId);
    }

    public function removeSignedPreKey($signedPreKeyId)
    {
        return $this->signedPreKeyStore->containsSignedPreKey();
    }
}
