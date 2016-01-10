<?php

require_once __DIR__.'/../InvalidKeyException.php';
require_once __DIR__.'/../ecc/Curve.php';
require_once __DIR__.'/../ecc/ECKeyPair.php';
require_once __DIR__.'/../ecc/ECPrivateKey.php';
require_once __DIR__.'/../ecc/ECPublicKey.php';
require_once __DIR__.'/pb_proto_LocalStorageProtocol.php';
class PreKeyRecord
{
    protected $structure;    // PreKeyRecordStructure

    public function PreKeyRecord($id = null, $keyPair = null, $serialized = null) // [int id, ECKeyPair keyPair]
    {
        $this->structure = new Textsecure_PreKeyRecordStructure();
        if ($serialized == null) {
            $this->structure->setId($id)->setPublicKey((string) $keyPair->getPublicKey()->serialize())->setPrivateKey((string) $keyPair->getPrivateKey()->serialize());
        } else {
            try {
                $this->structure->parseFromString($serialized);
            } catch (Exception $ex) {
                throw new Exception('Cannot unserialize PreKEyRecordStructure');
            }
        }
    }

    public function getId()
    {
        return $this->structure->getId();
    }

    public function getKeyPair()
    {
        $publicKey = Curve::decodePoint($this->structure->getPublicKey(), 0);
        $privateKey = Curve::decodePrivatePoint($this->structure->getPrivateKey());

        return new ECKeyPair($publicKey, $privateKey);
    }

    public function serialize()
    {
        return $this->structure->serializeToString();
    }
}
