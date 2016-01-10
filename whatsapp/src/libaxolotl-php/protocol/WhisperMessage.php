<?php
    require_once __DIR__.'/../ecc/ECPublicKey.php';
    require_once __DIR__.'/../util/ByteUtil.php';
    require_once __DIR__.'/pb_proto_WhisperTextProtocol.php';
    require_once __DIR__.'/CiphertextMessage.php';
    require_once __DIR__.'/../InvalidMessageException.php';
    require_once __DIR__.'/../LegacyMessageException.php';
    class WhisperMessage extends CiphertextMessage
    {
        const MAC_LENGTH = 8;
        protected $messageVersion;
        protected $senderRatchetKey;
        protected $counter;
        protected $previousCounter;
        protected $cipherText;
        protected $serialized;

        public function WhisperMessage($messageVersion = null,
                                        $macKey = null,
                                        $senderRatchetKey = null,
                                        $counter = null,
                                        $previousCounter = null,
                                        $cipherText = null,
                                        $senderIdentityKey = null,
                                        $receiverIdentityKey = null,
                                        $serialized = null)
        {
            if ($serialized == null) {
                $version = ByteUtil::intsToByteHighAndLow($messageVersion, self::CURRENT_VERSION);
                $proto_message = new Textsecure_WhisperMessage();
                $proto_message->setRatchetKey((string) ($senderRatchetKey->serialize()));
                $proto_message->setCounter($counter);
                $proto_message->setPreviousCounter($previousCounter);
                $proto_message->setCiphertext($cipherText);
                $message = $proto_message->serializeToString();

                $mac = $this->getMac($messageVersion, $senderIdentityKey, $receiverIdentityKey, $macKey, ByteUtil::combine([chr((int) $version), $message]));

                $this->serialized = ByteUtil::combine([chr((int) $version), $message, $mac]);
                $this->senderRatchetKey = $senderRatchetKey;
                $this->counter = $counter;
                $this->previousCounter = $previousCounter;
                $this->cipherText = $cipherText;
                $this->messageVersion = $messageVersion;
            } else {
                try {
                    $messageParts = ByteUtil::split($serialized, 1, strlen($serialized) - 1 - self::MAC_LENGTH,
                                                  self::MAC_LENGTH);

                    $version = ord($messageParts[0][0]);
                    $message = $messageParts[1];
                    $mac = $messageParts[2];
                    if (ByteUtil::highBitsToInt($version) <= self::UNSUPPORTED_VERSION) {
                        throw new LegacyMessageException('Legacy message '.ByteUtil::highBitsToInt($version));
                    }
                    if (ByteUtil::highBitsToInt($version) > self::CURRENT_VERSION) {
                        throw new InvalidMessageException('Unknown version: '.ByteUtil::highBitsToInt($version));
                    }

                    $proto_message = new Textsecure_WhisperMessage();
                    try {
                        $proto_message->parseFromString($message);
                    } catch (Exception $ex) {
                        throw new InvalidMessageException('Incomplete message.');
                    }
                    if ($proto_message->getCiphertext() === null || $proto_message->getCounter() === null || $proto_message->getRatchetKey() == null) {
                        throw new InvalidMessageException('Incomplete message.');
                    }
                    $this->serialized = $serialized;
                    $this->senderRatchetKey = Curve::decodePoint($proto_message->getRatchetKey(), 0);
                    $this->messageVersion = ByteUtil::highBitsToInt($version);
                    $this->counter = $proto_message->getCounter();
                    $this->previousCounter = $proto_message->getPreviousCounter();
                    $this->cipherText = $proto_message->getCiphertext();
                } catch (Exception $ex) {
                    throw new InvalidMessageException($ex->getMessage());
                }
            }
        }

        public function getSenderRatchetKey()
        {
            return $this->senderRatchetKey;
        }

        public function getMessageVersion()
        {
            return $this->messageVersion;
        }

        public function getCounter()
        {
            return $this->counter;
        }

        public function getBody()
        {
            return $this->cipherText;
        }

        public function serialize()
        {
            return $this->serialized;
        }

        public function getType()
        {
            return self::WHISPER_TYPE;
        }

        public function isLegacy($message)
        {
            return $message != null &&  strlen($message) >= 1 && ByteUtil::highBitsToInt($message[0]) <= self::UNSUPPORTED_VERSION;
        }

        public function verifyMac($messageVersion, $senderIdentityKey, $receiverIdentityKey, $macKey)
        {
            $parts = ByteUtil::split($this->serialized, strlen($this->serialized) - self::MAC_LENGTH, self::MAC_LENGTH);
            $ourMac = $this->getMac($messageVersion, $senderIdentityKey, $receiverIdentityKey, $macKey, $parts[0]);
            $theirMac = $parts[1];
            if (strcmp($ourMac, $theirMac) != 0) {
                throw new InvalidMessageException('Bad Mac!');
            }
        }

        private function getMac($messageVersion, $senderIdentityKey, $receiverIdentityKey, $macKey, $serialized)
        {
            $mac = hash_init('sha256', HASH_HMAC, $macKey);
            if ($messageVersion >= 3) {
                hash_update($mac, $senderIdentityKey->getPublicKey()->serialize());
                hash_update($mac, $receiverIdentityKey->getPublicKey()->serialize());
            }
            hash_update($mac, $serialized);
            $result = hash_final($mac, true);

            return ByteUtil::trim($result, self::MAC_LENGTH);
        }
    }
