<?php

require_once __DIR__.'/../InvalidKeyException.php';
require_once __DIR__.'/../ecc/Curve.php';
require_once __DIR__.'/../ecc/ECKeyPair.php';
require_once __DIR__.'/../ecc/ECPrivateKey.php';
require_once __DIR__.'/../ecc/ECPublicKey.php';
require_once __DIR__.'/../state/pb_proto_LocalStorageProtocol.php';
class SignedPreKeyRecord
{
    protected $structure;

    public function SignedPreKeyRecord($id = null, $timestamp = null, $keyPair = null, $signature = null, $serialized = null) // [int id, long timestamp, ECKeyPair keyPair, byte[] signature]
    {
        $struct = new Textsecure_SignedPreKeyRecordStructure();
        if ($serialized == null) {
            $struct->setId($id);
            $struct->setPublicKey((string) $keyPair->getPublicKey()->serialize());
            $struct->setPrivateKey((string) $keyPair->getPrivateKey()->serialize());
            $struct->setSignature((string) $signature);
            $struct->setTimestamp($timestamp);
        } else {
            $struct->parseFromString($serialized);
        }
        $this->structure = $struct; //$SignedPreKeyRecordStructure->newBuilder()->setId($id)->setPublicKey($ByteString->copyFrom($keyPair->getPublicKey()->serialize()))->setPrivateKey($ByteString->copyFrom($keyPair->getPrivateKey()->serialize()))->setSignature($ByteString->copyFrom($signature))->setTimestamp($timestamp)->build();
    }

    public function getId()
    {
        return $this->structure->getId();
    }

    public function getTimestamp()
    {
        return $this->structure->getTimestamp();
    }

    public function getKeyPair()
    {
        try {
            $publicKey = Curve::decodePoint($this->structure->getPublicKey(), 0);
            $privateKey = Curve::decodePrivatePoint($this->structure->getPrivateKey());

            return  new ECKeyPair($publicKey, $privateKey);
        } catch (InvalidKeyException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getSignature()
    {
        return $this->structure->getSignature();
    }

    public function serialize()
    {
        return $this->structure->serializeToString();
    }
}
