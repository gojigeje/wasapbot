<?php
    require_once(__DIR__."/../ecc/ECPublicKey.php");
    require_once(__DIR__."/../util/ByteUtil.php");
    require_once __DIR__."/pb_proto_WhisperTextProtocol.php";
    require_once __DIR__."/CiphertextMessage.php";
    require_once __DIR__."/../InvalidMessageException.php";
    require_once __DIR__."/../InvalidVersionException.php";
    require_once __DIR__. "/../LegacyMessageException.php";
    require_once __DIR__. "/../IdentityKey.php";
    require_once(__DIR__."/../protocol/WhisperMessage.php");
    class PreKeyWhisperMessage extends CiphertextMessage{
        protected $version;
        protected $registrationId;
        protected $preKeyId;
        protected $signedPreKeyId;
        protected $baseKey;
        protected $identityKey;
        protected $message;
        protected $serialized;
        public function PreKeyWhisperMessage($messageVersion = null,
                                             $registrationId  = null,
                                             $preKeyId = null,
                                             $signedPreKeyId = null,
                                             $ecPublicBaseKey = null,
                                             $identityKey = null,
                                             $whisperMessage = null,
                                             $serialized = null)
        {
            if($serialized != null){
                $this->version = ByteUtil::highBitsToInt($serialized[0]);
                if($this->version  > self::CURRENT_VERSION){
                    throw new InvalidVersionException("Unknown version ".$this->version);
                }
                $preKeyWhisperMessage = new Textsecure_PreKeyWhisperMessage();

                $preKeyWhisperMessage->parseFromString(substr($serialized,1));
                if (($this->version == 2 && $preKeyWhisperMessage->getPreKeyId() == null) ||
                    ($this->version == 3 && $preKeyWhisperMessage->getSignedPreKeyId() == null) ||
                    $preKeyWhisperMessage->getBaseKey() == null ||
                    $preKeyWhisperMessage->getIdentityKey() == null ||
                    $preKeyWhisperMessage->getMessage() == null)
                {
                    throw new InvalidMessageException("Incomplete message");

                }
               
                $this->serialized = $serialized;
                $this->registrationId = $preKeyWhisperMessage->getRegistrationId();
                $this->preKeyId = $preKeyWhisperMessage->getPreKeyId();
                $this->signedPreKeyId = $preKeyWhisperMessage->getSignedPreKeyId();
                $this->baseKey = Curve::decodePoint((string)$preKeyWhisperMessage->getBaseKey(),0);
                $this->identityKey = new IdentityKey(Curve::decodePoint((string)$preKeyWhisperMessage->getIdentityKey(),0));
                $this->message = new WhisperMessage(null,
                                        null,
                                        null,
                                        null,
                                        null,
                                        null,
                                        null,
                                        null,
                                        $preKeyWhisperMessage->getMessage());

            }
            else{
                try{
                    $this->version = $messageVersion;
                    $this->registrationId = $registrationId;
                    $this->preKeyId = $preKeyId;
                    $this->signedPreKeyId = $signedPreKeyId;
                    $this->baseKey = $ecPublicBaseKey;
                    $this->identityKey = $identityKey;
                    $this->message = $whisperMessage;

                    $builder = new Textsecure_PreKeyWhisperMessage();
                    $builder->setSignedPreKeyId($this->signedPreKeyId);
                    $builder->setBaseKey($this->baseKey->serialize());
                    $builder->setIdentityKey($this->identityKey->serialize());
                    $builder->setMessage($whisperMessage->serialize());
                    $builder->setRegistrationId($this->registrationId);
                    if($preKeyId != null){
                        $builder->setPreKeyId($preKeyId);
                    }
                    $versionBytes = ByteUtil::intsToByteHighAndLow($this->version,self::CURRENT_VERSION);
                    $messageBytes = $builder->serializeToString();
                    $this->serialized = ByteUtil::combine([chr($versionBytes),$messageBytes]);
                }
                catch(Exception $ex){
                    throw new InvalidMessageException($ex->getMessage()." - ".$ex->getLine()." - ".$ex->getFile());
                }
            }
        }
        public function getMessageVersion(){
            return $this->version;
        }

        public function getIdentityKey(){
            return $this->identityKey;
        }

        public function getRegistrationId(){
            return $this->registrationId;
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

        public function getWhisperMessage(){
            return $this->message;
        }

        public function serialize(){
            return $this->serialized;
        }

        public function getType(){
            return self::PREKEY_TYPE;
        }

    }
