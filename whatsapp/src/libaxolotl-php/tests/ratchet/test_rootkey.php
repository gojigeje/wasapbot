<?php

require_once __DIR__.'/../../ecc/Curve.php';
require_once __DIR__.'/../../ratchet/RootKey.php';
require_once __DIR__.'/../../kdf/HKDF.php';
require_once __DIR__.'/../../ratchet/ChainKey.php';
class RootKeyTest extends PHPUnit_Framework_TestCase
{
    public function testRootKeyDerivationV2()
    {
        $rootKeySeed = "\x7b\xa6\xde\xbc\x2b\xc1\xbb\xf9\x1a\xbb\xc1\x36\x74\x04\x17\x6c\xa6\x23\x09\x5b\x7e\xc6\x6b\x45\xf6\x02\xd9\x35\x38\x94\x2d\xcc";

        $alicePublic = "\x05\xee\x4f\xa6\xcd\xc0\x30\xdf\x49\xec\xd0\xba\x6c\xfc\xff\xb2\x33\xd3\x65\xa2\x7f\xad\xbe\xff\x77\xe9\x63\xfc\xb1\x62\x22\xe1\x3a";

        $alicePrivate = "\x21\x68\x22\xec\x67\xeb\x38\x04\x9e\xba\xe7\xb9\x39\xba\xea\xeb\xb1\x51\xbb\xb3\x2d\xb8\x0f\xd3\x89\x24\x5a\xc3\x7a\x94\x8e\x50";

        $bobPublic = "\x05\xab\xb8\xeb\x29\xcc\x80\xb4\x71\x09\xa2\x26\x5a\xbe\x97\x98\x48\x54\x06\xe3\x2d\xa2\x68\x93\x4a\x95\x55\xe8\x47\x57\x70\x8a\x30";

        $nextRoot = "\xb1\x14\xf5\xde\x28\x01\x19\x85\xe6\xeb\xa2\x5d\x50\xe7\xec\x41\xa9\xb0\x2f\x56\x93\xc5\xc7\x88\xa6\x3a\x06\xd2\x12\xa2\xf7\x31";

        $nextChain = "\x9d\x7d\x24\x69\xbc\x9a\xe5\x3e\xe9\x80\x5a\xa3\x26\x4d\x24\x99\xa3\xac\xe8\x0f\x4c\xca\xe2\xda\x13\x43\x0c\x5c\x55\xb5\xca\x5f";

        $alicePublicKey = Curve::decodePoint($alicePublic, 0);
        $alicePrivateKey = Curve::decodePrivatePoint($alicePrivate);
        $aliceKeyPair = new ECKeyPair($alicePublicKey, $alicePrivateKey);
        $bobPublicKey = Curve::decodePoint($bobPublic, 0);
        $rootKey = new RootKey(HKDF::createFor(2), $rootKeySeed);
        $rootKeyChainKeyPair = $rootKey->createChain($bobPublicKey, $aliceKeyPair);

        $nextRootKey = $rootKeyChainKeyPair[0];
        $nextChainKey = $rootKeyChainKeyPair[1];

        $this->assertEquals($rootKey->getKeyBytes(), $rootKeySeed);
        $this->assertEquals($nextRootKey->getKeyBytes(), $nextRoot);
        $this->assertEquals($nextChainKey->getKey(), $nextChain);
    }
}
