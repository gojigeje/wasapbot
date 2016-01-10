<?php

require_once __DIR__.'/state/pb_proto_LocalStorageProtocol.php';
require_once __DIR__.'/IdentityKey.php';
class IdentityKeyPair
{
    protected $publicKey;    // IdentityKey
    protected $privateKey;    // ECPrivateKey

    public function IdentityKeyPair($publicKey = null, $privateKey = null, $serialized = null) // [IdentityKey publicKey, ECPrivateKey privateKey]
    {
        if ($serialized == null) {
            $this->publicKey = $publicKey;
            $this->privateKey = $privateKey;
        } else {
            $structure = new Textsecure_IdentityKeyPairStructure();
            $structure->parseFromString($serialized);
            $this->publicKey = new IdentityKey($structure->getPublicKey(), 0);
            $this->privateKey = Curve::decodePrivatePoint($structure->getPrivateKey());
        }
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    public function serialize()
    {
        $struct = new Textsecure_IdentityKeyPairStructure();

        return $struct->setPublicKey((string) $this->publicKey->serialize())->setPrivateKey((string) $this->privateKey->serialize())->serializeToString();
    }
}
