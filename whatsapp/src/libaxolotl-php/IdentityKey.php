<?php
require_once(__DIR__."/ecc/Curve.php");
require_once(__DIR__."/ecc/ECPublicKey.php");
class IdentityKey {
    protected $publicKey;    // ECPublicKey
    public function IdentityKey ($publicKeyOrBytes, $offset = null) // [ECPublicKey publicKey]
    {
    
        if($offset === null){
            $this->publicKey = $publicKeyOrBytes;
        } 
        else $this->publicKey = Curve::decodePoint($publicKeyOrBytes,$offset);
    }

    public function getPublicKey ()
    {
        return $this->publicKey;
    }
    public function serialize ()
    {
        return $this->publicKey->serialize();
    }
    public function getFingerprint ()
    {
        $hex = unpack("H*",$this->publicKey->serialize());
        $hex = implode(" ",str_split($hex,2));
        return $hex;
    }
    public function equals ($other) // [Object other]
    {
        if (($other == null))
            return  FALSE ;
        if (!($other instanceof IdentityKey))
            return  FALSE ;
        return $this->publicKey->equals($other->getPublicKey());
    }
    public function hashCode ()
    {
        return $this->publicKey->hashCode();
    }
}
