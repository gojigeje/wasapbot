<?php

require_once __DIR__ . "/Medium.php";
require_once __DIR__ . "/../ecc/Curve.php";
require_once __DIR__ . "/../IdentityKey.php";
require_once __DIR__ . "/../IdentityKeyPair.php";
require_once __DIR__ . "/../state/PreKeyRecord.php";
require_once __DIR__ . "/../state/SignedPreKeyRecord.php";

class KeyHelper{

    /*
    Generate an identity key pair.  Clients should only do this once,
    at install time.
    @return the generated IdentityKeyPair.
    */
    public static function  generateIdentityKeyPair(){
        $keyPair   = Curve::generateKeyPair();
        $publicKey = new IdentityKey($keyPair->getPublicKey());
        $serialized = '0a21056e8936e8367f768a7bba008ade7cf58407bdc7a6aae293e2cb7c06668dcd7d5e12205011524f0c15467100dd6'.
                     '03e0d6020f4d293edfbcd82129b14a88791ac81365c';
        $serialized = pack('H*', $serialized);
        $identityKeyPair = new IdentityKeyPair($publicKey, $keyPair->getPrivateKey());
        return $identityKeyPair;
        // return new IdentityKeyPair(serialized=serialized)
    }
    /*
    Generate a registration ID.  Clients should only do this once,
    at install time.
    */
    public static function generateRegistrationId(){
        $regId =  self::getRandomSequence();
        return $regId;
    }
    public static function getRandomSequence($max = 4294967296){
        $size = (int) ((log($max)/ log(2)) / 8);
        $rand = openssl_random_pseudo_bytes((int)$size);
        $randh = unpack("H*",$rand);
        return intval($randh[1], 16);
    }

    /*
    Generate a list of PreKeys.  Clients should do this at install time, and
    subsequently any time the list of PreKeys stored on the server runs low.
    PreKey IDs are shorts, so they will eventually be repeated.  Clients should
    store PreKeys in a circular buffer, so that they are repeated as infrequently
    as possible.
    @param start The starting PreKey ID, inclusive.
    @param count The number of PreKeys to generate.
    @return the list of generated PreKeyRecords.
   */
   public static function generatePreKeys($start, $count){
        $results = [];
        $start -= 1;
        for ($i=0;$i<$count;$i++){
            $preKeyId = (($start + $i) % (Medium::MAX_VALUE-1)) + 1;
            $results[] = (new PreKeyRecord($preKeyId, Curve::generateKeyPair()));
        }
        return $results;
    }
    public static function generateSignedPreKey($identityKeyPair, $signedPreKeyId){
        $keyPair = Curve::generateKeyPair();
        $signature = Curve::calculateSignature($identityKeyPair->getPrivateKey(), $keyPair->getPublicKey()->serialize());

        $spk = new SignedPreKeyRecord($signedPreKeyId, (int) round(time()*1000), $keyPair, $signature);

        return $spk;
    }
    public static function generateSenderSigningKey(){
        return Curve::generateKeyPair();
    }

    public static function generateSenderKey(){
        return openssl_random_pseudo_bytes(32);
    }

    public static function generateSenderKeyId(){
        return self::getRandomSequence(2147483647);
    }
}
