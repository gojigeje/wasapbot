<?php
    require_once __DIR__.'/../ecc/ECPublicKey.php';
    require_once __DIR__.'/../util/ByteUtil.php';
    require_once __DIR__.'/pb_proto_WhisperTextProtocol.php';
    require_once __DIR__.'/CiphertextMessage.php';
    require_once __DIR__.'/../InvalidMessageException.php';
    require_once __DIR__.'/../LegacyMessageException.php';
    require_once __DIR__.'/../InvalidVersionException.php';
    require_once __DIR__.'/../InvalidKeyException.php';
    class KeyExchangeMessage
    {
        const INITIATE_FLAG = 0x01;
        const RESPONSE_FLAG = 0x02;
        const SIMULTANEOUS_INITIATE_FLAG = 0x04;
        protected $version;
        protected $supportedVersion;
        protected $sequence;
        protected $flags;
        protected $baseKey;
        protected $baseKeySignature;
        protected $ratchetKey;
        protected $identityKey;
        protected $serialized;

        public function KeyExchangeMessage($messageVersion = null, $sequence = null, $flags = null,
                                            $baseKey = null, $baseKeySignature = null,
                                            $ratchetKey = null,
                                            $identityKey = null,
                                            $serialized = null)
        {
            /*
        :type messageVersion: int
        :type  sequence: int
        :type flags:int
        :type baseKey: ECPublicKey
        :type baseKeySignature: bytearray
        :type ratchetKey: ECPublicKey
        :type identityKey: IdentityKey
        :type serialized: bytearray
        */
            if ($serialized == null) {
                $this->supportedVersion = CiphertextMessage::CURRENT_VERSION;
                $this->version = $messageVersion;
                $this->sequence = $sequence;
                $this->flags = $flags;
                $this->baseKey = $baseKey;
                $this->baseKeySignature = $baseKeySignature;
                $this->ratchetKey = $ratchetKey;
                $this->identityKey = $identityKey;

                $version = ByteUtil::intsToByteHighAndLow($this->version, $this->supportedVersion);
                $keyExchangeMessage = new Textsecure_KeyExchangeMessage();
                $keyExchangeMessage->setId(($this->sequence << 5) | $this->flags);
                $keyExchangeMessage->setBaseKey($baseKey->serialize());
                $keyExchangeMessage->setRatchetKey($ratchetKey->serialize());
                $keyExchangeMessage->setIdentityKey($identityKey->serialize());

                if ($messageVersion >= 3) {
                    $keyExchangeMessage->setBaseKeySignature($baseKeySignature);
                }

                $this->serialized = ByteUtil::combine([chr((int) $version), $keyExchangeMessage->serializeToString()]);
            } else {
                try {
                    $parts = ByteUtil::split($serialized, 1, strlen($serialized) - 1);
                    $this->version = ByteUtil::highBitsToInt(ord($parts[0][0]));
                    $this->supportedVersion = ByteUtil::lowBitsToInt(ord($parts[0][0]));
                    if ($this->version <= CiphertextMessage::UNSUPPORTED_VERSION) {
                        throw new LegacyMessageException('Unsupported legacy version: '.$this->version);
                    }
                    if ($this->version > CiphertextMessage::CURRENT_VERSION) {
                        throw new InvalidVersionException('Unkown version: '.$this->version);
                    }
                    $message = new Textsecure_KeyExchangeMessage();
                    $message->parseFromString($parts[1]);

                    if ($message->getId() == null || $message->getBaseKey() == null ||
                       $message->getRatchetKey() == null || $message->getIdentityKey() == null ||
                        ($this->version >= 3 && $message->getBaseKeySignature() == null)) {
                        throw new InvalidMessageException('Some required fields are missing!');
                    }

                    $this->sequence = $message->getId() >> 5;
                    $this->flags = $message->getId() & 0x1f;
                    $this->serialized = $serialized;
                    $this->baseKey = Curve::decodePoint($message->getBaseKey(), 0);
                    $this->baseKeySignature = $message->getBaseKeySignature();
                    $this->ratchetKey = Curve::decodePoint($message->getRatchetKey(), 0);
                    $this->identityKey = new IdentityKey($message->getIdentityKey(), 0);
                } catch (InvalidKeyException $ex) {
                    throw new InvalidMessageException($ex->getMessage());
                }
            }
        }

        public function getVersion()
        {
            return $this->version;
        }

        public function getBaseKey()
        {
            return $this->baseKey;
        }

        public function getBaseKeySignature()
        {
            return $this->baseKeySignature;
        }

        public function getRatchetKey()
        {
            return $this->ratchetKey;
        }

        public function getIdentityKey()
        {
            return $this->identityKey;
        }

        public function hasIdentityKey()
        {
            return true;
        }

        public function getMaxVersion()
        {
            return $this->supportedVersion;
        }

        public function isResponse()
        {
            return ($this->flags & self::RESPONSE_FLAG) != 0;
        }

        public function isInitiate()
        {
            return ($this->flags & self::INITIATE_FLAG) != 0;
        }

        public function isResponseForSimultaneousInitiate()
        {
            return ($this->flags & self::SIMULTANEOUS_INITIATE_FLAG) != 0;
        }

        public function getFlags()
        {
            return $this->flags;
        }

        public function getSequence()
        {
            return $this->sequence;
        }

        public function serialize()
        {
            return $this->serialized;
        }
    }
