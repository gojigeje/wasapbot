<?php
    require_once(__DIR__."/../ecc/ECPublicKey.php");
    require_once(__DIR__."/../util/ByteUtil.php");
    require_once __DIR__."/pb_proto_WhisperTextProtocol.php";
    require_once __DIR__."/CiphertextMessage.php";
    require_once __DIR__."/../InvalidMessageException.php";
    require_once __DIR__. "/../LegacyMessageException.php";
    class SenderKeyMessage extends CiphertextMessage{
        const SIGNATURE_LENGTH = 64;
        protected $serialized;
        protected $messageVersion;
        protected $keyId;
        protected $iteration;
        protected $ciphertext;
        public function SenderKeyMessage($keyId = null, $iteration = null, $ciphertext = null, $signatureKey = null, $serialized = null){
            if($serialized == null){
                $version = ByteUtil::intsToByteHighAndLow(self::CURRENT_VERSION,self::CURRENT_VERSION);
                $proto_message = new Textsecure_SenderKeyMessage();
                $proto_message->setId($keyId);
                $proto_message->setIteration($iteration);
                $proto_message->setCiphertext($ciphertext);

                $message = $proto_message->serializeToString();

                $signature =  $this->getSignature($signatureKey,ByteUtil::combine([chr((int)$version),$message]));

                $this->serialized = ByteUtil::combine([chr((int)$version),$message,$signature]);
                $this->messageVersion = self::CURRENT_VERSION;
                $this->keyId = $keyId;
                $this->iteration = $iteration;
                $this->ciphertext = $ciphertext;
            }
            else{
                try{
                        $messageParts = ByteUtil::split($serialized, 1, strlen($serialized)- 1 - self::SIGNATURE_LENGTH,
                                                      self::SIGNATURE_LENGTH);

                        $version      = ord($messageParts[0][0]);
                        $message      = $messageParts[1];
                        $signature    = $messageParts[2];
                        if (ByteUtil::highBitsToInt($version) < 3)
                            throw new LegacyMessageException("Legacy message: ". ByteUtil::highBitsToInt($version));

                        if (ByteUtil::highBitsToInt($version) > self::CURRENT_VERSION)
                            throw new InvalidMessageException("Unknown version: ". ByteUtil::highBitsToInt($version));

                        $proto_message = new Textsecure_SenderKeyMessage();
                        try{
                            $proto_message->parseFromString($message);

                        }
                        catch(Exception $ex){

                            throw new InvalidMessageException("Incomplete message");
                        }

                        if($proto_message->getId() === null || $proto_message->getIteration() === null || $proto_message->getCiphertext() == null)
                            throw new InvalidMessageException("Incomplete message");

                        $this->serialized = $serialized;
                        $this->messageVersion = ByteUtil::highBitsToInt($version);
                        $this->keyId          = $proto_message->getId();
                        $this->iteration      = $proto_message->getIteration();
                        $this->ciphertext     = $proto_message->getCiphertext();

                }
                catch(Exception $ex){
                    throw new InvalidMessageException($ex->getMessage());
                }
            }

        }
        public function getKeyId(){
            return $this->keyId;
        }
        public function getIteration(){
            return $this->iteration;
        }
        public function getCiphertext(){
            return $this->ciphertext;
        }
        public function verifySignature($signatureKey){
            try{
                $parts = ByteUtil::split($this->serialized,strlen($this->serialized) - self::SIGNATURE_LENGTH, self::SIGNATURE_LENGTH);
                if(!Curve::verifySignature($signatureKey,$parts[0],$parts[1]))
                    throw new InvalidMessageException("Invalid signature!");
            }
            catch(InvalidKeyException $ex){
                throw new InvalidMessageException($ex->getMessage());
            }

        }
        private function getSignature($signatureKey,$serialized){
            try{
                return Curve::calculateSignature($signatureKey,$serialized);
            }
            catch(InvalidKeyException $ex){
                throw new Exception($ex->getMessage());
            }
        }
        public function serialize(){
            return $this->serialized;
        }
        public function getType(){
            return self::SENDERKEY_TYPE;
        }

    }
