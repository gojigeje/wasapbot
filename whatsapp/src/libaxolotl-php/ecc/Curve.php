<?php
require_once __DIR__."/../InvalidKeyException.php";
require_once __DIR__."/ECKeyPair.php";
require_once __DIR__."/ECPrivateKey.php";
require_once __DIR__."/ECPublicKey.php";
require_once __DIR__."/DjbECPublicKey.php";
require_once __DIR__."/DjbECPrivateKey.php";
class Curve {
    const DJB_TYPE = 0x05;  // int
    public static function generateKeyPair ()
    {
        $secureRandom = self::getSecureRandom();
        $private = curve25519_private($secureRandom);
        $public = curve25519_public($private);
        return new ECKeyPair(new DjbECPublicKey($public),new DjbECPrivateKey($private));
    }
    public static function decodePoint ($bytes, $offset) // [byte[] bytes, int offset]
    {
        $type = ((ord($bytes[$offset]) & 0xFF));
        switch ($type) {
            case Curve::DJB_TYPE:
                $keyBytes = substr($bytes,$offset+1);/* from: System.arraycopy(bytes, offset + 1, keyBytes, 0, keyBytes.length) -> php string == java byte array*/;
                //foreach (range(0, (count($keyBytes) /*from: keyBytes.length*/ + 0)) as $_upto) $keyBytes[$_upto] = $bytes[$_upto - (0) + ($offset + 1)]; /* from: System.arraycopy(bytes, offset + 1, keyBytes, 0, keyBytes.length) */;
                return new DjbECPublicKey($keyBytes);
            default:
                throw new InvalidKeyException("Bad key type: " . $type);
        }
    }
    public static function decodePrivatePoint ($bytes) // [byte[] bytes]
    {
        return new DjbECPrivateKey($bytes);
    }
    public static function calculateAgreement ($publicKey, $privateKey) // [ECPublicKey publicKey, ECPrivateKey privateKey]
    {
        if (($publicKey->getType() != $privateKey->getType()))
        {
            throw new InvalidKeyException("Public and private keys must be of the same type!");
        }
        if (($publicKey->getType() == self::DJB_TYPE))
        {
            return curve25519_shared( $privateKey->getPrivateKey(),$publicKey->getPublicKey());
        }
        else
        {
            throw new InvalidKeyException("Unknown type: " . $publicKey->getType());
        }
    }
    public static function verifySignature ($signingKey, $message, $signature) // [ECPublicKey signingKey, byte[] message, byte[] signature]
    {
        if (($signingKey->getType() == self::DJB_TYPE))
        {
            return curve25519_verify($signingKey->getPublicKey(), $message, $signature) == 0;
        }
        else
        {
            throw new InvalidKeyException("Unknown type: " . $signingKey->getType());
        }
    }
    public static function calculateSignature ($signingKey, $message) // [ECPrivateKey signingKey, byte[] message]
    {
        if (($signingKey->getType() == self::DJB_TYPE))
        {
            return curve25519_sign(self::getSecureRandom(64), $signingKey->getPrivateKey(), $message);
        }
        else
        {
            throw new InvalidKeyException("Unknown type: " . $signingKey->getType());
        }
    }
    protected static function getSecureRandom ($len = 32)
    {
        $rand = openssl_random_pseudo_bytes($len,$strong);
        if($strong){
            return $rand;
        }
        else throw new Exception("Cannot generate secure random bytes");
    }
}
