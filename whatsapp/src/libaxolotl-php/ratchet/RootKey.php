<?php
require_once __DIR__."/../kdf/DerivedRootSecrets.php";
class RootKey{
    protected $kdf;
    protected $key;
    public function RootKey( $kdf, $key){
        $this->kdf = $kdf;
        $this->key = $key;
    }
    public function getKeyBytes(){
        return $this->key;
    }

    public function createChain($ECPublicKey_theirRatchetKey, $ECKeyPair_ourRatchetKey){
        $sharedSecret = Curve::calculateAgreement($ECPublicKey_theirRatchetKey, $ECKeyPair_ourRatchetKey->getPrivateKey());

        $derivedSecretBytes = $this->kdf->deriveSecrets($sharedSecret, "WhisperRatchet", DerivedRootSecrets::SIZE,$this->key);
        $derivedSecrets = new DerivedRootSecrets($derivedSecretBytes);
        $newRootKey = new RootKey($this->kdf, $derivedSecrets->getRootKey());
        $newChainKey = new ChainKey($this->kdf, $derivedSecrets->getChainKey(), 0);
        return array($newRootKey, $newChainKey);
    }
}

?>
