<?php

class DjbECPublicKey implements ECPublicKey
{
    protected $publicKey;    // byte[]
    const KEY_SIZE = 33;    // int

    public function DjbECPublicKey($publicKey) // [byte[] publicKey]
    {
        $this->publicKey = $publicKey;
    }

    public function serialize()
    {
        return chr(Curve::DJB_TYPE).$this->publicKey;
    }

    public function getType()
    {
        return Curve::DJB_TYPE;
    }

    public function equals($other) // [Object other]
    {
        if (($other == null)) {
            return  false;
        }
        if (!($other instanceof self)) {
            return  false;
        }
        $that = $other;

        return $this->publicKey == $that->publicKey;
    }

    public function compareTo($another) // [ECPublicKey another]
    {

        //return new BigInteger($this->publicKey)::compareTo(new BigInteger(($another)::$publicKey));
        /*$current = unpack("H*",$this->publicKey);
        $current= $current[1];
        //$current = intval($current[1],16);
        $other = unpack("H*",$another->publicKey);
        $other = $other[1];
        //$other = intval($other[1],16);*/
        for ($x = 0; $x < strlen($this->publicKey); $x++) {
            if (ord($this->publicKey[$x]) > ord($another->publicKey[$x])) {
                return 1;
            } elseif (ord($this->publicKey[$x]) > ord($another->publicKey[$x])) {
                return -1;
            }
        }

        return 0;
        //return (($current > $other)?1: (($current == $other)?0:-1));
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }
}
