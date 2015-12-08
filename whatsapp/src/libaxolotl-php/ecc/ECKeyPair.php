<?php
class ECKeyPair {
    protected $publicKey;    // ECPublicKey
    protected $privateKey;    // ECPrivateKey
    public function ECKeyPair ($publicKey, $privateKey) // [ECPublicKey publicKey, ECPrivateKey privateKey]
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }
    public function getPublicKey ()
    {
        return $this->publicKey;
    }
    public function getPrivateKey ()
    {
        return $this->privateKey;
    }
}
