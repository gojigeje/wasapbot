<?php

//require_once("com/google/protobuf/ByteString.php");
require_once __DIR__.'/../ecc/ECPublicKey.php';
require_once __DIR__.'/../util/ByteUtil.php';
require_once __DIR__.'/pb_proto_WhisperTextProtocol.php';
require_once __DIR__.'/CiphertextMessage.php';
require_once __DIR__.'/../InvalidMessageException.php';
require_once __DIR__.'/../LegacyMessageException.php';
class SenderKeyDistributionMessage extends CiphertextMessage
{
    protected $id;    // int
    protected $iteration;    // int
    protected $chainKey;    // byte[]
    protected $signatureKey;    // ECPublicKey
    protected $serialized;    // byte[]

    public function SenderKeyDistributionMessage($id = null, $iteration = null, $chainKey = null, $signatureKey = null, $serialized = null) // [int id, int iteration, byte[] chainKey, ECPublicKey signatureKey]
    {
        if ($serialized == null) {
            $version = ByteUtil::intsToByteHighAndLow(self::CURRENT_VERSION, self::CURRENT_VERSION);
            $this->id = $id;
            $this->iteration = $iteration;
            $this->chainKey = $chainKey;
            $this->signatureKey = $signatureKey;

            $proto_skdm = new Textsecure_SenderKeyDistributionMessage();
            $proto_skdm->setId($id);
            $proto_skdm->setIteration($iteration);
            $proto_skdm->setChainKey((string) $chainKey);
            $proto_skdm->setSigningKey((string) ($signatureKey->serialize()));
            $this->serialized = chr($version).$proto_skdm->serializeToString();
        } else {
            $parts = ByteUtil::split($serialized, 1, strlen($serialized) - 1);
            $version = ord($parts[0][0]);
            $message = $parts[1];
            if (ByteUtil::highBitsToInt($version) < self::CURRENT_VERSION) {
                throw new LegacyMessageException('Legacy message: ' + ByteUtil::highBitsToInt($version));
            }
            if (ByteUtil::highBitsToInt($version) > self::CURRENT_VERSION) {
                throw new InvalidMessageException('Unknown version: ' + ByteUtil::highBitsToInt($version));
            }
            $proto_skdm = new Textsecure_SenderKeyDistributionMessage();
            try {
                $proto_skdm->parseFromString($message);
            } catch (Exception $ex) {
                throw new InvalidMessageException('Incomplete message.');
            }
            if ($proto_skdm->getId() === null
                || $proto_skdm->getIteration() === null
                || $proto_skdm->getChainKey() === null
                || $proto_skdm->getSigningKey() === null) {
                throw new InvalidMessageException('Incomplete message.');
            }
            $this->serialized = $serialized;
            $this->id = $proto_skdm->getId();
            $this->iteration = $proto_skdm->getIteration();
            $this->chainKey = $proto_skdm->getChainKey();
            $this->signatureKey = Curve::decodePoint($proto_skdm->getSigningKey(), 0);
        }
    }

    public function serialize()
    {
        return $this->serialized;
    }

    public function getType()
    {
        return self::SENDERKEY_DISTRIBUTION_TYPE;
    }

    public function getIteration()
    {
        return $this->iteration;
    }

    public function getChainKey()
    {
        return $this->chainKey;
    }

    public function getSignatureKey()
    {
        return $this->signatureKey;
    }

    public function getId()
    {
        return $this->id;
    }
}
