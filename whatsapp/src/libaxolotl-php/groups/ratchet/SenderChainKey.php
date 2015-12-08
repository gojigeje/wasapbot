<?php
    require_once __DIR__."/SenderMessageKey.php";
    class SenderChainKey{
        const MESSAGE_KEY_SEED = "\x01";
        const CHAIN_KEY_SEED = "\x02";
        protected $iteration;
        protected $chainKey;
        public function SenderChainKey($iteration,$chainKey){
            $this->iteration = $iteration;
            $this->chainKey = $chainKey;
        }
        public function getIteration(){
            return $this->iteration;
        }
        public function getSenderMessageKey(){            
            return new SenderMessageKey($this->iteration,$this->getDerivative(self::MESSAGE_KEY_SEED,$this->chainKey));
        }
        public function getNext(){
            return new SenderChainKey($this->iteration+1,$this->getDerivative(self::CHAIN_KEY_SEED,$this->chainKey));
        }
        public function getSeed(){
            return $this->chainKey;
        }
        public function getDerivative($seed,$key){
            $mac = hash_init("sha256",HASH_HMAC,$key);
            hash_update($mac, $seed);
            return hash_final($mac,true);
        }

    }

    