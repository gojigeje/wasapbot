<?php

require_once __DIR__.'/ecc/Curve.php';
require_once __DIR__.'/ecc/ECKeyPair.php';
require_once __DIR__.'/ecc/ECPublicKey.php';
require_once __DIR__.'/logging/Log.php';
require_once __DIR__.'/protocol/CiphertextMessage.php';
require_once __DIR__.'/protocol/KeyExchangeMessage.php';
require_once __DIR__.'/protocol/PreKeyWhisperMessage.php';
require_once __DIR__.'/ratchet/AliceAxolotlParameters.php';
require_once __DIR__.'/ratchet/BobAxolotlParameters.php';
require_once __DIR__.'/ratchet/RatchetingSession.php';
require_once __DIR__.'/ratchet/SymmetricAxolotlParameters.php';
require_once __DIR__.'/state/AxolotlStore.php';
require_once __DIR__.'/state/IdentityKeyStore.php';
require_once __DIR__.'/state/PreKeyBundle.php';
require_once __DIR__.'/state/PreKeyStore.php';
require_once __DIR__.'/state/SessionRecord.php';
require_once __DIR__.'/state/SessionState.php';
require_once __DIR__.'/state/SessionStore.php';
require_once __DIR__.'/state/SignedPreKeyStore.php';
require_once __DIR__.'/util/KeyHelper.php';
require_once __DIR__.'/util/Medium.php';
require_once __DIR__.'/StaleKeyExchangeException.php';
require_once __DIR__.'/UntrustedIdentityException.php';
class SessionBuilder
{
    protected $sessionStore;
    protected $preKeyStore;
    protected $signedPreKeyStore;
    protected $identityKeyStore;
    protected $recipientId;
    protected $deviceId;

    public function SessionBuilder($sessionStore, $preKeyStore, $signedPreKeyStore, $identityKeyStore, $recepientId, $deviceId)
    {
        $this->sessionStore = $sessionStore;
        $this->preKeyStore = $preKeyStore;
        $this->signedPreKeyStore = $signedPreKeyStore;
        $this->identityKeyStore = $identityKeyStore;
        $this->recipientId = $recepientId;
        $this->deviceId = $deviceId;
    }

    public function process($sessionRecord, $message)
    {
        /*
        :param sessionRecord:
        :param message:
        :type message: PreKeyWhisperMessage
        */

        $messageVersion = $message->getMessageVersion();
        $theirIdentityKey = $message->getIdentityKey();

        $unsignedPreKeyId = null;

        if (!$this->identityKeyStore->isTrustedIdentity($this->recipientId, $theirIdentityKey)) {
            throw new  UntrustedIdentityException('Untrusted identity!!');
        }
        if ($messageVersion == 2) {
            $unsignedPreKeyId = $this->processV2($sessionRecord, $message);
        } elseif ($messageVersion == 3) {
            $unsignedPreKeyId = $this->processV3($sessionRecord, $message);
        } else {
            throw new Exception('Unkown version '.$messageVersion);
        }

        $this->identityKeyStore->saveIdentity($this->recipientId, $theirIdentityKey);

        return $unsignedPreKeyId;
    }

    public function processV2($sessionRecord, $message)
    {
        /*
        :type sessionRecord: SessionRecord
        :type message: PreKeyWhisperMessage
        */

        if ($message->getPreKeyId() == null) {
            throw new InvalidKeyIdException('V2 message requires one time prekey id!');
        }
        if (!$this->preKeyStore->containsPreKey($message->getPreKeyId()) &&
            $this->sessionStore->containsSession($this->recipientId, $this->deviceId)) {
            Log::warn('v2', "We've already processed the prekey part of this V2 session, letting bundled message fall through...");

            return;
        }

        $ourPreKey = $this->preKeyStore->loadPreKey($message->getPreKeyId())->getKeyPair();

        $parameters = (new BobBuilder());

        $parameters->setOurIdentityKey($this->identityKeyStore->getIdentityKeyPair())
              ->setOurSignedPreKey($ourPreKey)
              ->setOurRatchetKey($ourPreKey)
              ->setOurOneTimePreKey(null)
              ->setTheirIdentityKey($message->getIdentityKey())
              ->setTheirBaseKey($message->getBaseKey());

        if (!$sessionRecord->isFresh()) {
            $sessionRecord->archiveCurrentState();
        }

        RatchetingSession::initializeSessionAsBob($sessionRecord->getSessionState(), $message->getMessageVersion(), $parameters->create());

        $sessionRecord->getSessionState()->setLocalRegistrationId($this->identityKeyStore->getLocalRegistrationId());
        $sessionRecord->getSessionState()->setRemoteRegistrationId($message->getRegistrationId());
        $sessionRecord->getSessionState()->setAliceBaseKey($message->getBaseKey()->serialize());

        if ($message->getPreKeyId() != Medium::MAX_VALUE) {
            return $message->getPreKeyId();
        } else {
            return;
        }
    }

    public function processV3($sessionRecord, $message)
    {
        /*
        :param sessionRecord:
        :param message:
        :type message: PreKeyWhisperMessage
        :return:
        */
        if ($sessionRecord->hasSessionState($message->getMessageVersion(), $message->getBaseKey()->serialize())) {
            Log::warn('v3', "We've already setup a session for this V3 message, letting bundled message fall through...");

            return;
        }

        $ourSignedPreKey = $this->signedPreKeyStore->loadSignedPreKey($message->getSignedPreKeyId())->getKeyPair();
        $parameters = new BobBuilder();
        $parameters->setTheirBaseKey($message->getBaseKey())
            ->setTheirIdentityKey($message->getIdentityKey())
            ->setOurIdentityKey($this->identityKeyStore->getIdentityKeyPair())
            ->setOurSignedPreKey($ourSignedPreKey)
            ->setOurRatchetKey($ourSignedPreKey);

        if ($message->getPreKeyId() != null) {
            $parameters->setOurOneTimePreKey($this->preKeyStore->loadPreKey($message->getPreKeyId())->getKeyPair());
        } else {
            $parameters->setOurOneTimePreKey(null);
        }

        if (!$sessionRecord->isFresh()) {
            $sessionRecord->archiveCurrentState();
        }

        RatchetingSession::initializeSessionAsBob($sessionRecord->getSessionState(), $message->getMessageVersion(), $parameters->create());
        $sessionRecord->getSessionState()->setLocalRegistrationId($this->identityKeyStore->getLocalRegistrationId());
        $sessionRecord->getSessionState()->setRemoteRegistrationId($message->getRegistrationId());
        $sessionRecord->getSessionState()->setAliceBaseKey($message->getBaseKey()->serialize());

        if ($message->getPreKeyId() != null && $message->getPreKeyId() != Medium::MAX_VALUE) {
            return $message->getPreKeyId();
        } else {
            return;
        }
    }

    public function processPreKeyBundle($preKey)
    {
        /*
        :type preKey: PreKeyBundle
        */
        if (!$this->identityKeyStore->isTrustedIdentity($this->recipientId, $preKey->getIdentityKey())) {
            throw new  UntrustedIdentityException();
        }

        if ($preKey->getSignedPreKey() != null &&
            !Curve::verifySignature($preKey->getIdentityKey()->getPublicKey(),
                                      $preKey->getSignedPreKey()->serialize(),
                                      $preKey->getSignedPreKeySignature())) {
            throw new InvalidKeyException('Invalid signature on device key!');
        }

        if ($preKey->getSignedPreKey() == null && $preKey->getPreKey() == null) {
            throw new InvalidKeyException('Both signed and unsigned prekeys are absent!');
        }

        $supportsV3 = $preKey->getSignedPreKey() != null;
        $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);
        $ourBaseKey = Curve::generateKeyPair();
        $theirSignedPreKey = $supportsV3 ? $preKey->getSignedPreKey() : $preKey->getPreKey();
        $theirOneTimePreKey = $preKey->getPreKey();
        $theirOneTimePreKeyId = $theirOneTimePreKey != null ? $preKey->getPreKeyId() : null;

        $parameters = new AliceBuilder();

        $parameters->setOurBaseKey($ourBaseKey)
                ->setOurIdentityKey($this->identityKeyStore->getIdentityKeyPair())
                ->setTheirIdentityKey($preKey->getIdentityKey())
                ->setTheirSignedPreKey($theirSignedPreKey)
                ->setTheirRatchetKey($theirSignedPreKey)
                ->setTheirOneTimePreKey($supportsV3 ? $theirOneTimePreKey : null);

        if (!$sessionRecord->isFresh()) {
            $sessionRecord->archiveCurrentState();
        }
        RatchetingSession::initializeSessionAsAlice($sessionRecord->getSessionState(),
                                                   ($supportsV3 ? 3 : 2),
                                                   $parameters->create());

        $sessionRecord->getSessionState()->setUnacknowledgedPreKeyMessage($theirOneTimePreKeyId, $preKey->getSignedPreKeyId(), $ourBaseKey->getPublicKey());
        $sessionRecord->getSessionState()->setLocalRegistrationId($this->identityKeyStore->getLocalRegistrationId());
        $sessionRecord->getSessionState()->setRemoteRegistrationId($preKey->getRegistrationId());
        $sessionRecord->getSessionState()->setAliceBaseKey($ourBaseKey->getPublicKey()->serialize());
        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);
        $this->identityKeyStore->saveIdentity($this->recipientId, $preKey->getIdentityKey());
    }

    public function processKeyExchangeMessage($keyExchangeMessage)
    {
        if (!$this->identityKeyStore->isTrustedIdentity($this->recipientId, $keyExchangeMessage->getIdentityKey())) {
            throw new UntrustedIdentityException();
        }

        $responseMessage = null;

        if ($keyExchangeMessage->isInitiate()) {
            $responseMessage = $this->processInitiate($keyExchangeMessage);
        } else {
            $this->processResponse($keyExchangeMessage);
        }

        return $responseMessage;
    }

    public function processInitiate($keyExchangeMessage)
    {
        $flags = KeyExchangeMessage::RESPONSE_FLAG;
        $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);

        if ($keyExchangeMessage->getVersion() >= 3 && !Curve::verifySignature(
                $keyExchangeMessage->getIdentityKey()->getPublicKey(),
                $keyExchangeMessage->getBaseKey()->serialize(),
                $keyExchangeMessage->getBaseKeySignature())) {
            throw new InvalidKeyException('Bad signature!');
        }

        $builder = new SymmetricBuilder();
        if (!$sessionRecord->getSessionState()->hasPendingKeyExchange()) {
            $builder->setOurIdentityKey($this->identityKeyStore->getIdentityKeyPair())
                ->setOurBaseKey(Curve::generateKeyPair())
                ->setOurRatchetKey(Curve::generateKeyPair());
        } else {
            $builder->setOurIdentityKey($sessionRecord->getSessionState()->getPendingKeyExchangeIdentityKey())
                ->setOurBaseKey($sessionRecord->getSessionState()->getPendingKeyExchangeBaseKey())
                ->setOurRatchetKey($sessionRecord->getSessionState()->getPendingKeyExchangeRatchetKey());
            $flags |= KeyExchangeMessage::SIMULTAENOUS_INITIATE_FLAG;
        }

        $builder->setTheirBaseKey($keyExchangeMessage->getBaseKey())
            ->setTheirRatchetKey($keyExchangeMessage->getRatchetKey())
            ->setTheirIdentityKey($keyExchangeMessage->getIdentityKey());

        $parameters = $builder->create();

        if (!$sessionRecord->isFresh()) {
            $sessionRecord->archiveCurrentState();
        }

        RatchetingSession::initializeSession($sessionRecord->getSessionState(),
                                        min($keyExchangeMessage->getMaxVersion(), CiphertextMessage::CURRENT_VERSION),
                                        $parameters);

        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);
        $this->identityKeyStore->saveIdentity($this->recipientId, $keyExchangeMessage->getIdentityKey());

        $baseKeySignature = Curve::calculateSignature($parameters->getOurIdentityKey()->getPrivateKey(),
                                                       $parameters->getOurBaseKey()->getPublicKey()->serialize());

        return new KeyExchangeMessage($sessionRecord->getSessionState()->getSessionVersion(),
                                  $keyExchangeMessage->getSequence(), $flags,
                                  $parameters->getOurBaseKey()->getPublicKey(),
                                  $baseKeySignature, $parameters->getOurRatchetKey()->getPublicKey(),
                                  $parameters->getOurIdentityKey()->getPublicKey());
    }

    public function processResponse($keyExchangeMessage)
    {
        $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);

        $sessionState = $sessionRecord->getSessionState();
        $hasPendingKeyExchange = $sessionState->hasPendingKeyExchange();
        $isSimultaneousInitiateResponse = $keyExchangeMessage->isResponseForSimultaneousInitiate();

        if (!$hasPendingKeyExchange || $sessionState->getPendingKeyExchangeSequence() != $keyExchangeMessage->getSequence()) {
            Log::warn('procResponse', 'No matching sequence for response. Is simultaneous initiate response:'.($isSimultaneousInitiateResponse ? 'true' : 'false'));
            if (!$isSimultaneousInitiateResponse) {
                throw new StaleKeyExchangeException();
            } else {
                return;
            }
        }

        $parameters = new SymmetricBuilder();

        $parameters->setOurBaseKey($sessionRecord->getSessionState()->getPendingKeyExchangeBaseKey())
            ->setOurRatchetKey($sessionRecord->getSessionState()->getPendingKeyExchangeRatchetKey())
            ->setOurIdentityKey($sessionRecord->getSessionState()->getPendingKeyExchangeIdentityKey())
            ->setTheirBaseKey($keyExchangeMessage->getBaseKey())
            ->setTheirRatchetKey($keyExchangeMessage->getRatchetKey())
            ->setTheirIdentityKey($keyExchangeMessage->getIdentityKey());

        if (!$sessionRecord->isFresh()) {
            $sessionRecord->archiveCurrentState();
        }

        RatchetingSession::initializeSession($sessionRecord->getSessionState(),
                                        min($keyExchangeMessage->getMaxVersion(), CiphertextMessage::CURRENT_VERSION),
                                        $parameters->create());

        if ($sessionRecord->getSessionState()->getSessionVersion() >= 3 && !Curve::verifySignature(
                $keyExchangeMessage->getIdentityKey()->getPublicKey(),
                $keyExchangeMessage->getBaseKey()->serialize(),
                $keyExchangeMessage->getBaseKeySignature())) {
            throw new InvalidKeyException("Base key signature doesn't match!");
        }

        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);
        $this->identityKeyStore->saveIdentity($this->recipientId, $keyExchangeMessage->getIdentityKey());
    }

    public function processInitKeyExchangeMessage()
    {
        try {
            $sequence = KeyHelper::getRandomSequence(65534) + 1;
            $flags = KeyExchangeMessage::INITIATE_FLAG;
            $baseKey = Curve::generateKeyPair();
            $ratchetKey = Curve::generateKeyPair();
            $identityKey = $this->identityKeyStore->getIdentityKeyPair();
            $baseKeySignature = Curve::calculateSignature($identityKey->getPrivateKey(), $baseKey->getPublicKey()->serialize());
            $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);

            $sessionRecord->getSessionState()->setPendingKeyExchange($sequence, $baseKey, $ratchetKey, $identityKey);
            $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);

            return new KeyExchangeMessage(2, $sequence, $flags, $baseKey->getPublicKey(), $baseKeySignature,
                                      $ratchetKey->getPublicKey(), $identityKey->getPublicKey());
        } catch (InvalidKeyException $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}
