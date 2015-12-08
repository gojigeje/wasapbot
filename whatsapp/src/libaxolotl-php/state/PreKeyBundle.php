<?php
require_once(__DIR__."/../IdentityKey.php");
require_once(__DIR__."/../ecc/ECPublicKey.php");
class PreKeyBundle {
    protected $registrationId;    // int
    protected $deviceId;    // int
    protected $preKeyId;    // int
    protected $preKeyPublic;    // ECPublicKey
    protected $signedPreKeyId;    // int
    protected $signedPreKeyPublic;    // ECPublicKey
    protected $signedPreKeySignature;    // byte[]
    protected $identityKey;    // IdentityKey
    public function PreKeyBundle ($registrationId, $deviceId, $preKeyId, $preKeyPublic, $signedPreKeyId, $signedPreKeyPublic, $signedPreKeySignature, $identityKey) // [int registrationId, int deviceId, int preKeyId, ECPublicKey preKeyPublic, int signedPreKeyId, ECPublicKey signedPreKeyPublic, byte[] signedPreKeySignature, IdentityKey identityKey]
    {
        $this->registrationId = $registrationId;
        $this->deviceId = $deviceId;
        $this->preKeyId = $preKeyId;
        $this->preKeyPublic = $preKeyPublic;
        $this->signedPreKeyId = $signedPreKeyId;
        $this->signedPreKeyPublic = $signedPreKeyPublic;
        $this->signedPreKeySignature = $signedPreKeySignature;
        $this->identityKey = $identityKey;
    }
    public function getDeviceId ()
    {
        return $this->deviceId;
    }
    public function getPreKeyId ()
    {
        return $this->preKeyId;
    }
    public function getPreKey ()
    {
        return $this->preKeyPublic;
    }
    public function getSignedPreKeyId ()
    {
        return $this->signedPreKeyId;
    }
    public function getSignedPreKey ()
    {
        return $this->signedPreKeyPublic;
    }
    public function getSignedPreKeySignature ()
    {
        return $this->signedPreKeySignature;
    }
    public function getIdentityKey ()
    {
        return $this->identityKey;
    }
    public function getRegistrationId ()
    {
        return $this->registrationId;
    }
}