<?php
require_once __DIR__. "/../../ecc/Curve.php";
require_once __DIR__."/../../ratchet/RootKey.php";
require_once __DIR__."/../../kdf/HKDF.php";
require_once __DIR__."/../../ratchet/ChainKey.php";
require_once __DIR__."/../../IdentityKey.php";
require_once __DIR__."/../../IdentityKeyPair.php";
require_once __DIR__."/../../ratchet/BobAxolotlParameters.php";
require_once __DIR__."/../../state/SessionState.php";
require_once __DIR__."/../../ratchet/RatchetingSession.php";
class RatchetingSessionTest extends PHPUnit_Framework_TestCase{
    public function test_ratchetingSessionAsBob(){
        $bobPublic   = "\x05\x2c\xb4\x97\x76\xb8\x77\x02\x05\x74\x5a\x3a\x6e\x24\xf5\x79\xcd\xb4\xba\x7a\x89\x04\x10\x05\x92\x8e\xbb\xad\xc9\xc0\x5a\xd4\x58";

        $bobPrivate  = "\xa1\xca\xb4\x8f\x7c\x89\x3f\xaf\xa9\x88\x0a\x28\xc3\xb4\x99\x9d\x28\xd6\x32\x95\x62\xd2\x7a\x4e\xa4\xe2\x2e\x9f\xf1\xbd\xd6\x5a";

        $bobIdentityPublic   = "\x05\xf1\xf4\x38\x74\xf6\x96\x69\x56\xc2\xdd\x47\x3f\x8f\xa1\x5a\xde\xb7\x1d\x1c\xb9\x91\xb2\x34\x16\x92\x32\x4c\xef\xb1\xc5\xe6\x26";

        $bobIdentityPrivate    = "\x48\x75\xcc\x69\xdd\xf8\xea\x07\x19\xec\x94\x7d\x61\x08\x11\x35\x86\x8d\x5f\xd8\x01\xf0\x2c\x02\x25\xe5\x16\xdf\x21\x56\x60\x5e";

        $aliceBasePublic     = "\x05\x47\x2d\x1f\xb1\xa9\x86\x2c\x3a\xf6\xbe\xac\xa8\x92\x02\x77\xe2\xb2\x6f\x4a\x79\x21\x3e\xc7\xc9\x06\xae\xb3\x5e\x03\xcf\x89\x50";

        $aliceEphemeralPublic  = "\x05\x6c\x3e\x0d\x1f\x52\x02\x83\xef\xcc\x55\xfc\xa5\xe6\x70\x75\xb9\x04\x00\x7f\x18\x81\xd1\x51\xaf\x76\xdf\x18\xc5\x1d\x29\xd3\x4b";

        $aliceIdentityPublic   = "\x05\xb4\xa8\x45\x56\x60\xad\xa6\x5b\x40\x10\x07\xf6\x15\xe6\x54\x04\x17\x46\x43\x2e\x33\x39\xc6\x87\x51\x49\xbc\xee\xfc\xb4\x2b\x4a";

        $senderChain           = "\xd2\x2f\xd5\x6d\x3f\xec\x81\x9c\xf4\xc3\xd5\x0c\x56\xed\xfb\x1c\x28\x0a\x1b\x31\x96\x45\x37\xf1\xd1\x61\xe1\xc9\x31\x48\xe3\x6b";

        $bobIdentityKeyPublic   = new IdentityKey($bobIdentityPublic, 0);
        $bobIdentityKeyPrivate  = Curve::decodePrivatePoint($bobIdentityPrivate);
        $bobIdentityKey         =  new IdentityKeyPair($bobIdentityKeyPublic, $bobIdentityKeyPrivate);
        $bobEphemeralPublicKey  = Curve::decodePoint($bobPublic, 0);
        $bobEphemeralPrivateKey = Curve::decodePrivatePoint($bobPrivate);
        $bobEphemeralKey        = new ECKeyPair($bobEphemeralPublicKey, $bobEphemeralPrivateKey);
        $bobBaseKey             = $bobEphemeralKey;

        $aliceBasePublicKey       = Curve::decodePoint($aliceBasePublic, 0);
        $aliceEphemeralPublicKey  = Curve::decodePoint($aliceEphemeralPublic, 0);
        $aliceIdentityPublicKey   = new IdentityKey($aliceIdentityPublic, 0);

        $parameters = BobAxolotlParameters::newBuilder();
        $parameters = $parameters->setOurIdentityKey($bobIdentityKey)
        ->setOurSignedPreKey($bobBaseKey)
        ->setOurRatchetKey($bobEphemeralKey)
        ->setOurOneTimePreKey(null)
        ->setTheirIdentityKey($aliceIdentityPublicKey)
        ->setTheirBaseKey($aliceBasePublicKey)
        ->create();

        $session = new SessionState();

        RatchetingSession::initializeSessionAsBob($session, 2, $parameters);
        $this->assertEquals($session->getLocalIdentityKey(), $bobIdentityKey->getPublicKey());
        $this->assertEquals($session->getRemoteIdentityKey(), $aliceIdentityPublicKey);
        $this->assertEquals($session->getSenderChainKey()->getKey(), $senderChain);
    }
}
?>