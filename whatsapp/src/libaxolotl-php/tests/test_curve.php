<?php

require_once __DIR__.'/../ecc/Curve.php';
class Curve25519Test extends PHPUnit_Framework_TestCase
{
    public function testAgreement()
    {
        $alicePublic = "\x05\x1b\xb7\x59\x66\xf2\xe9\x3a\x36\x91\xdf\xff\x94\x2b\xb2\xa4\x66\xa1\xc0\x8b\x8d\x78\xca\x3f\x4d\x6d\xf8\xb8\xbf\xa2\xe4\xee\x28";

        $alicePrivate = "\xc8\x06\x43\x9d\xc9\xd2\xc4\x76\xff\xed\x8f\x25\x80\xc0\x88\x8d\x58\xab\x40\x6b\xf7\xae\x36\x98\x87\x90\x21\xb9\x6b\xb4\xbf\x59";

        $bobPublic = "\x05\x65\x36\x14\x99\x3d\x2b\x15\xee\x9e\x5f\xd3\xd8\x6c\xe7\x19\xef\x4e\xc1\xda\xae\x18\x86\xa8\x7b\x3f\x5f\xa9\x56\x5a\x27\xa2\x2f";

        $bobPrivate = "\xb0\x3b\x34\xc3\x3a\x1c\x44\xf2\x25\xb6\x62\xd2\xbf\x48\x59\xb8\x13\x54\x11\xfa\x7b\x03\x86\xd4\x5f\xb7\x5d\xc5\xb9\x1b\x44\x66";

        $shared = "\x32\x5f\x23\x93\x28\x94\x1c\xed\x6e\x67\x3b\x86\xba\x41\x01\x74\x48\xe9\x9b\x64\x9a\x9c\x38\x06\xc1\xdd\x7c\xa4\xc4\x77\xe6\x29";

        $alicePublicKey = Curve::decodePoint($alicePublic, 0);
        $alicePrivateKey = Curve::decodePrivatePoint($alicePrivate);

        $bobPublicKey = Curve::decodePoint($bobPublic, 0);
        $bobPrivateKey = Curve::decodePrivatePoint($bobPrivate);

        $sharedOne = Curve::calculateAgreement($alicePublicKey, $bobPrivateKey);
        $sharedTwo = Curve::calculateAgreement($bobPublicKey, $alicePrivateKey);

        $this->assertEquals($sharedOne, $shared);
        $this->assertEquals($sharedTwo, $shared);
    }

    public function testRandomAgreements()
    {
        for ($i = 0; $i < 50; $i++) {
            $alice = Curve::generateKeyPair();
            $bob = Curve::generateKeyPair();

            $sharedAlice = Curve::calculateAgreement($bob->getPublicKey(), $alice->getPrivateKey());
            $sharedBob = Curve::calculateAgreement($alice->getPublicKey(), $bob->getPrivateKey());

            $this->assertEquals($sharedAlice, $sharedBob);
        }
    }

    public function testSignature()
    {
        $aliceIdentityPrivate = "\xc0\x97\x24\x84\x12\xe5\x8b\xf0\x5d\xf4\x87\x96\x82\x05\x13\x27\x94\x17\x8e\x36\x76\x37\xf5\x81\x8f\x81\xe0\xe6\xce\x73\xe8\x65";
        $aliceIdentityPublic = "\x05\xab\x7e\x71\x7d\x4a\x16\x3b\x7d\x9a\x1d\x80\x71\xdf\xe9\xdc\xf8\xcd\xcd\x1c\xea\x33\x39\xb6\x35\x6b\xe8\x4d\x88\x7e\x32\x2c\x64";

        $aliceEphemeralPublic = "\x05\xed\xce\x9d\x9c\x41\x5c\xa7\x8c\xb7\x25\x2e\x72\xc2\xc4\xa5\x54\xd3\xeb\x29\x48\x5a\x0e\x1d\x50\x31\x18\xd1\xa8\x2d\x99\xfb\x4a";

        $aliceSignature = "\x5d\xe8\x8c\xa9\xa8\x9b\x4a\x11\x5d\xa7\x91\x09\xc6\x7c\x9c\x74\x64\xa3\xe4\x18\x02\x74\xf1\xcb\x8c\x63\xc2\x98\x4e\x28\x6d\xfb\xed\xe8\x2d\xeb\x9d\xcd\x9f\xae\x0b\xfb\xb8\x21\x56\x9b\x3d\x90\x01\xbd\x81\x30\xcd\x11\xd4\x86\xce\xf0\x47\xbd\x60\xb8\x6e\x88";

        $alicePrivateKey = Curve::decodePrivatePoint($aliceIdentityPrivate);
        $alicePublicKey = Curve::decodePoint($aliceIdentityPublic, 0);
        $aliceEphemeral = Curve::decodePoint($aliceEphemeralPublic, 0);

        if (!Curve::verifySignature($alicePublicKey, $aliceEphemeral->serialize(), $aliceSignature)) {
            throw new Exception('Sig verification failed!');
        }

        for ($i = 0; $i < strlen($aliceSignature); $i++) {
            $modifiedSignature = (string) $aliceSignature;

            $modifiedSignature[$i] = chr(ord($modifiedSignature[$i]) ^ 0x01);
            if (Curve::verifySignature($alicePublicKey, $aliceEphemeral->serialize(), $modifiedSignature)) {
                throw new Exception('Sig verification succeeded!');
            }
        }
    }
}
