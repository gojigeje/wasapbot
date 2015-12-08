<?php
    require_once __DIR__."/MessageKeys.php";
    require_once __DIR__."/../kdf/DerivedMessageSecrets.php";
    class ChainKey{

        const MESSAGE_KEY_SEED    = "\x01";
        const CHAIN_KEY_SEED      = "\x02";
        protected $kdf;
        protected $key;
        protected $index;
        public function ChainKey($kdf, $key, $index){
            $this->kdf = $kdf;
            $this->key = $key;
            $this->index = $index;
        }
        public function getKey(){
            return $this->key;
        }

        public function getIndex(){
            return $this->index;
        }
        public function getNextChainKey(){
            $nextKey = $this->getBaseMaterial(self::CHAIN_KEY_SEED);
            return new ChainKey($this->kdf, $nextKey, $this->index + 1);
        }
        public function getMessageKeys(){
            $inputKeyMaterial = $this->getBaseMaterial(self::MESSAGE_KEY_SEED);
            $keyMaterialBytes = $this->kdf->deriveSecrets($inputKeyMaterial, "WhisperMessageKeys", DerivedMessageSecrets::SIZE);
            $keyMaterial = new DerivedMessageSecrets($keyMaterialBytes);
            return new MessageKeys($keyMaterial->getCipherKey(), $keyMaterial->getMacKey(), $keyMaterial->getIv(), $this->index);
        }
        public function getBaseMaterial($seedBytes){
            $mac =  hash_init("sha256",HASH_HMAC,$this->key);
            hash_update($mac,$seedBytes);
            $data = hash_final($mac,true);
            return $data;
        }


    }

?>