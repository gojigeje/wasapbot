<?php

// coding=utf-8

require_once __DIR__.'/../SessionBuilder.php';
require_once __DIR__.'/../SessionCipher.php';
//require_once __DIR__."/../ecc/Curve.php";
require_once __DIR__.'/../protocol/CiphertextMessage.php';
require_once __DIR__.'/../protocol/WhisperMessage.php';
require_once __DIR__.'/../protocol/PreKeyWhisperMessage.php';
require_once __DIR__.'/../state/PreKeyBundle.php';
require_once __DIR__.'/inmemoryaxolotlstore.php';
require_once __DIR__.'/../state/PreKeyRecord.php';
require_once __DIR__.'/../state/SignedPreKeyRecord.php';
require_once __DIR__.'/inmemoryidentitykeystore.php';
require_once __DIR__.'/../protocol/KeyExchangeMessage.php';
require_once __DIR__.'/../UntrustedIdentityException.php';
function parseText($txt)
{
    for ($x = 0; $x < strlen($txt); $x++) {
        if (ord($txt[$x]) < 20 || ord($txt[$x]) > 230) {
            $txt = 'HEX:'.bin2hex($txt);

            return $txt;
        }
    }

    return $txt;
}
function niceVarDump($obj, $ident = 0)
{
    $data = '';
    $data .= str_repeat(' ', $ident);
    $original_ident = $ident;
    $toClose = false;
    switch (gettype($obj)) {
        case 'object':
            $vars = (array) $obj;
            $data .= gettype($obj).' ('.get_class($obj).') ('.count($vars).") {\n";
            $ident += 2;
            foreach ($vars as $key => $var) {
                $type = '';
                $k = bin2hex($key);
                if (strpos($k, '002a00') === 0) {
                    $k = str_replace('002a00', '', $k);
                    $type = ':protected';
                } elseif (strpos($k, bin2hex("\x00".get_class($obj)."\x00")) === 0) {
                    $k = str_replace(bin2hex("\x00".get_class($obj)."\x00"), '', $k);
                    $type = ':private';
                }
                $k = hex2bin($k);
                if (is_subclass_of($obj, 'ProtobufMessage') && $k == 'values') {
                    $r = new ReflectionClass($obj);
                    $constants = $r->getConstants();
                    $newVar = [];
                    foreach ($constants as $ckey => $cval) {
                        if (substr($ckey, 0, 3) != 'PB_') {
                            $newVar[$ckey] = $var[$cval];
                        }
                    }
                    $var = $newVar;
                }
                $data .= str_repeat(' ', $ident)."[$k$type]=>\n".niceVarDump($var, $ident)."\n";
            }
            $toClose = true;
        break;
        case 'array':
            $data .= 'array ('.count($obj).") {\n";
            $ident += 2;
            foreach ($obj as $key => $val) {
                $data .= str_repeat(' ', $ident).'['.(is_int($key) ? $key : "\"$key\"")."]=>\n".niceVarDump($val, $ident)."\n";
            }
            $toClose = true;
        break;
        case 'string':
            $data .= 'string "'.parseText($obj)."\"\n";
        break;
        case 'NULL':
            $data .= gettype($obj);
        break;
        default:
            $data .= gettype($obj).'('.strval($obj).")\n";
        break;
    }
    if ($toClose) {
        $data .= str_repeat(' ', $original_ident)."}\n";
    }

    return $data;
}
class SessionBuilderTest extends PHPUnit_Framework_TestCase
{
    const ALICE_RECIPIENT_ID = 5;
    const BOB_RECIPIENT_ID = 2;

    /*public function testBasicPreKeyV2(){
        $aliceStore = new InMemoryAxolotlStore();
        $aliceSessionBuilder = new SessionBuilder($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);


        $bobStore      = new InMemoryAxolotlStore();
        $bobPreKeyPair = Curve::generateKeyPair();
        $bobPreKey     = new  PreKeyBundle($bobStore->getLocalRegistrationId(), 1,
                                                  31337, $bobPreKeyPair->getPublicKey(),
                                                  0, null, null,
                                                  $bobStore->getIdentityKeyPair()->getPublicKey());

        $aliceSessionBuilder->processPreKeyBundle($bobPreKey);

        $this->assertTrue($aliceStore->containsSession(self::BOB_RECIPIENT_ID, 1));
        $this->assertEquals($aliceStore->loadSession(self::BOB_RECIPIENT_ID, 1)->getSessionState()->getSessionVersion(), 2);

        $originalMessage    = "L'homme est condamné à être libre";
        $aliceSessionCipher = new SessionCipher($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);
        $outgoingMessage    = $aliceSessionCipher->encrypt($originalMessage);

        $this->assertTrue($outgoingMessage->getType() == CiphertextMessage::PREKEY_TYPE);

        $incomingMessage = new PreKeyWhisperMessage(null, null,null,null,null,null,null,$outgoingMessage->serialize());
        $bobStore->storePreKey(31337, new PreKeyRecord($bobPreKey->getPreKeyId(), $bobPreKeyPair));

        $bobSessionCipher = new SessionCipher($bobStore, $bobStore, $bobStore, $bobStore, self::ALICE_RECIPIENT_ID, 1);
        $plaintext        = $bobSessionCipher->decryptPkmsg($incomingMessage);
        $this->assertTrue($bobStore->containsSession(self::ALICE_RECIPIENT_ID, 1));
        $this->assertTrue($bobStore->loadSession(self::ALICE_RECIPIENT_ID, 1)->getSessionState()->getSessionVersion() == 2);
        $this->assertEquals($originalMessage, $plaintext);


        $bobOutgoingMessage = $bobSessionCipher->encrypt($originalMessage);
        $this->assertTrue($bobOutgoingMessage->getType() == CiphertextMessage::WHISPER_TYPE);

        $alicePlaintext = $aliceSessionCipher->decryptMsg($bobOutgoingMessage);
        $this->assertEquals($alicePlaintext, $originalMessage);

        $this->runInteraction($aliceStore, $bobStore);

        $aliceStore          = new InMemoryAxolotlStore();
        $aliceSessionBuilder = new  SessionBuilder($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);
        $aliceSessionCipher  = new  SessionCipher($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);

        $bobPreKeyPair = Curve::generateKeyPair();

        $bobPreKey = new PreKeyBundle($bobStore->getLocalRegistrationId(),
                                 1, 31338, $bobPreKeyPair->getPublicKey(),
                                 0, null, null, $bobStore->getIdentityKeyPair()->getPublicKey());


        $bobStore->storePreKey(31338, new PreKeyRecord($bobPreKey->getPreKeyId(), $bobPreKeyPair));
        $aliceSessionBuilder->processPreKeyBundle($bobPreKey);

        $outgoingMessage = $aliceSessionCipher->encrypt($originalMessage);

        try {
            $bobSessionCipher->decryptPkmsg(new PreKeyWhisperMessage(null,null,null,null,null,null,null,$outgoingMessage->serialize()));
            throw new Exception("shouldn't be trusted!");
        }
        catch(Exception $ex){
            $preKeyW = new PreKeyWhisperMessage(null,null,null,null,null,null,null,$outgoingMessage->serialize());
            $bobStore->saveIdentity(self::ALICE_RECIPIENT_ID, $preKeyW->getIdentityKey());
        }
        $plaintext = $bobSessionCipher->decryptPkmsg(new PreKeyWhisperMessage(null,null,null,null,null,null,null,$outgoingMessage->serialize()));
        $this->assertEquals($plaintext, $originalMessage);
        $bobPreKey = new PreKeyBundle($bobStore->getLocalRegistrationId(), 1,
                                 31337, Curve::generateKeyPair()->getPublicKey(),
                                 0, null, null,
                                 $aliceStore->getIdentityKeyPair()->getPublicKey());
        try{
            $aliceSessionBuilder->processPreKeyBundle($bobPreKey);
            throw new Exception("shouldn't be trusted");
        }
        catch(Exception $ex){
            //all ok
        }

        return;

    }*/
    /*
    public function test_basicPreKeyV3(){
        aliceStore = InMemoryAxolotlStore()
        aliceSessionBuilder = SessionBuilder(aliceStore, aliceStore, aliceStore, aliceStore, self::BOB_RECIPIENT_ID, 1)

        bobStore =   InMemoryAxolotlStore()
        bobPreKeyPair = Curve::generateKeyPair()
        bobSignedPreKeyPair = Curve::generateKeyPair()
        bobSignedPreKeySignature = Curve::calculateSignature(bobStore.getIdentityKeyPair().getPrivateKey(),
                                                                           bobSignedPreKeyPair.getPublicKey().serialize())

        bobPreKey = PreKeyBundle(bobStore.getLocalRegistrationId(), 1,
                                              31337, bobPreKeyPair.getPublicKey(),
                                              22, bobSignedPreKeyPair.getPublicKey(),
                                              bobSignedPreKeySignature,
                                              bobStore.getIdentityKeyPair().getPublicKey())

        aliceSessionBuilder.processPreKeyBundle(bobPreKey)
        $this->assertTrue(aliceStore.containsSession(self::BOB_RECIPIENT_ID, 1))
        $this->assertTrue(aliceStore.loadSession(self::BOB_RECIPIENT_ID, 1).getSessionState().getSessionVersion() == 3)

        originalMessage    = "L'homme est condamné à être libre"
        aliceSessionCipher = SessionCipher(aliceStore, aliceStore, aliceStore, aliceStore, self::BOB_RECIPIENT_ID, 1)
        outgoingMessage    = aliceSessionCipher.encrypt(originalMessage)

        $this->assertTrue(outgoingMessage.getType() == CiphertextMessage::PREKEY_TYPE)

        incomingMessage = PreKeyWhisperMessage(serialized=outgoingMessage.serialize())
        bobStore.storePreKey(31337, PreKeyRecord(bobPreKey.getPreKeyId(), bobPreKeyPair))
        bobStore.storeSignedPreKey(22, SignedPreKeyRecord(22, int(time.time() * 1000), bobSignedPreKeyPair, bobSignedPreKeySignature))

        bobSessionCipher = SessionCipher(bobStore, bobStore, bobStore, bobStore, self::ALICE_RECIPIENT_ID, 1)

        plaintext = bobSessionCipher.decryptPkmsg(incomingMessage)
        $this->assertEquals(originalMessage, plaintext)
        # @@TODO: in callback assertion
        # $this->assertFalse(bobStore.containsSession(self::ALICE_RECIPIENT_ID, 1))

        $this->assertTrue(bobStore.containsSession(self::ALICE_RECIPIENT_ID, 1))

        $this->assertTrue(bobStore.loadSession(self::ALICE_RECIPIENT_ID, 1).getSessionState().getSessionVersion() == 3)
        $this->assertTrue(bobStore.loadSession(self::ALICE_RECIPIENT_ID, 1).getSessionState().getAliceBaseKey() != null)
        $this->assertEquals(originalMessage, plaintext)

        bobOutgoingMessage = bobSessionCipher.encrypt(originalMessage)
        $this->assertTrue(bobOutgoingMessage.getType() == CiphertextMessage::WHISPER_TYPE)

        alicePlaintext = aliceSessionCipher.decryptMsg(WhisperMessage(serialized=bobOutgoingMessage.serialize()))
        $this->assertEquals(alicePlaintext, originalMessage)

        self.runInteraction(aliceStore, bobStore)

        aliceStore          = InMemoryAxolotlStore()
        aliceSessionBuilder = SessionBuilder(aliceStore, aliceStore, aliceStore, aliceStore, self::BOB_RECIPIENT_ID, 1)
        aliceSessionCipher  = SessionCipher(aliceStore, aliceStore, aliceStore, aliceStore, self::BOB_RECIPIENT_ID, 1)

        bobPreKeyPair            = Curve::generateKeyPair()
        bobSignedPreKeyPair      = Curve::generateKeyPair()
        bobSignedPreKeySignature = Curve::calculateSignature(bobStore.getIdentityKeyPair().getPrivateKey(), bobSignedPreKeyPair.getPublicKey().serialize())
        bobPreKey = PreKeyBundle(bobStore.getLocalRegistrationId(),
                                 1, 31338, bobPreKeyPair.getPublicKey(),
                                 23, bobSignedPreKeyPair.getPublicKey(), bobSignedPreKeySignature,
                                 bobStore.getIdentityKeyPair().getPublicKey())

        bobStore.storePreKey(31338, PreKeyRecord(bobPreKey.getPreKeyId(), bobPreKeyPair))
        bobStore.storeSignedPreKey(23, SignedPreKeyRecord(23, int(time.time() * 1000), bobSignedPreKeyPair, bobSignedPreKeySignature))
        aliceSessionBuilder.processPreKeyBundle(bobPreKey)

        outgoingMessage = aliceSessionCipher.encrypt(originalMessage)

        try:
            plaintext = bobSessionCipher.decryptPkmsg(PreKeyWhisperMessage(serialized=outgoingMessage))
            throw new AssertionError("shouldn't be trusted!")
        except Exception:
            bobStore.saveIdentity(self::ALICE_RECIPIENT_ID, PreKeyWhisperMessage(serialized=outgoingMessage.serialize()).getIdentityKey())

        plaintext = bobSessionCipher.decryptPkmsg(PreKeyWhisperMessage(serialized=outgoingMessage.serialize()))
        $this->assertEquals(plaintext, originalMessage)


        bobPreKey = PreKeyBundle(bobStore.getLocalRegistrationId(), 1,
                                 31337, Curve::generateKeyPair().getPublicKey(),
                                 23, bobSignedPreKeyPair.getPublicKey(), bobSignedPreKeySignature,
                                 aliceStore.getIdentityKeyPair().getPublicKey())
        try:
            aliceSessionBuilder.process(bobPreKey)
            throw new AssertionError("shouldn't be trusted!")
        except Exception:
            #good
            pass

    public function test_badSignedPreKeySignature(){
        aliceStore          = InMemoryAxolotlStore()
        aliceSessionBuilder = SessionBuilder(aliceStore, aliceStore, aliceStore, aliceStore,
                                             self::BOB_RECIPIENT_ID, 1)

        bobIdentityKeyStore = InMemoryIdentityKeyStore()

        bobPreKeyPair            = Curve::generateKeyPair()
        bobSignedPreKeyPair      = Curve::generateKeyPair()
        bobSignedPreKeySignature = Curve::calculateSignature(bobIdentityKeyStore.getIdentityKeyPair().getPrivateKey(),
                                                                  bobSignedPreKeyPair.getPublicKey().serialize())

        for i in range(0, len(bobSignedPreKeySignature) * 8):
            modifiedSignature = bytearray(bobSignedPreKeySignature[:])
            modifiedSignature[int(i/8)] ^= 0x01 << (i % 8)

            bobPreKey = PreKeyBundle(bobIdentityKeyStore.getLocalRegistrationId(), 1,
                                                31337, bobPreKeyPair.getPublicKey(),
                                                22, bobSignedPreKeyPair.getPublicKey(), modifiedSignature,
                                                bobIdentityKeyStore.getIdentityKeyPair().getPublicKey())

            try:
                aliceSessionBuilder.processPreKeyBundle(bobPreKey)
            except Exception:
                pass
                #good
        bobPreKey = PreKeyBundle(bobIdentityKeyStore.getLocalRegistrationId(), 1,
                                              31337, bobPreKeyPair.getPublicKey(),
                                              22, bobSignedPreKeyPair.getPublicKey(), bobSignedPreKeySignature,
                                              bobIdentityKeyStore.getIdentityKeyPair().getPublicKey())

        aliceSessionBuilder.processPreKeyBundle(bobPreKey)

*/

    public function test_basicKeyExchange()
    {
        $aliceStore = new InMemoryAxolotlStore();
        $aliceSessionBuilder = new SessionBuilder($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);

        $bobStore = new InMemoryAxolotlStore();
        $bobSessionBuilder = new SessionBuilder($bobStore, $bobStore, $bobStore, $bobStore, self::ALICE_RECIPIENT_ID, 1);

        $aliceKeyExchangeMessage = $aliceSessionBuilder->processInitKeyExchangeMessage();
        $this->assertTrue($aliceKeyExchangeMessage != null);

        $aliceKeyExchangeMessageBytes = $aliceKeyExchangeMessage->serialize();

        $bobKeyExchangeMessage = $bobSessionBuilder->processKeyExchangeMessage(
                                                                            new KeyExchangeMessage(null, null, null, null, null, null, null, $aliceKeyExchangeMessageBytes));

        $this->assertTrue($bobKeyExchangeMessage != null);

        define('TEST', true);
        $bobKeyExchangeMessageBytes = $bobKeyExchangeMessage->serialize();
        $response = $aliceSessionBuilder->processKeyExchangeMessage(new KeyExchangeMessage(null, null, null, null, null, null, null, $bobKeyExchangeMessageBytes));

        $this->assertTrue($response == null);
        $this->assertTrue($aliceStore->containsSession(self::BOB_RECIPIENT_ID, 1));
        $this->assertTrue($bobStore->containsSession(self::ALICE_RECIPIENT_ID, 1));

        $this->runInteraction($aliceStore, $bobStore);

        $aliceStore = new InMemoryAxolotlStore();
        $aliceSessionBuilder = new SessionBuilder($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);
        $aliceKeyExchangeMessage = $aliceSessionBuilder->processInitKeyExchangeMessage();

        try {
            $bobKeyExchangeMessage = $bobSessionBuilder->processKeyExchangeMessage($aliceKeyExchangeMessage);
            throw new AssertionError("This identity shouldn't be trusted!");
        } catch (UntrustedIdentityException $ex) {
            $bobStore->saveIdentity(self::ALICE_RECIPIENT_ID, $aliceKeyExchangeMessage->getIdentityKey());
        }
        $bobKeyExchangeMessage = $bobSessionBuilder->processKeyExchangeMessage($aliceKeyExchangeMessage);

        $this->assertTrue($aliceSessionBuilder->processKeyExchangeMessage($bobKeyExchangeMessage) == null);

        self.runInteraction($aliceStore, $bobStore);
    }

    public function runInteraction($aliceStore, $bobStore)
    {
        /*
        :type aliceStore: AxolotlStore
        :type  bobStore: AxolotlStore
        */

        $aliceSessionCipher = new SessionCipher($aliceStore, $aliceStore, $aliceStore, $aliceStore, self::BOB_RECIPIENT_ID, 1);
        $bobSessionCipher = new SessionCipher($bobStore, $bobStore, $bobStore, $bobStore, self::ALICE_RECIPIENT_ID, 1);

        $originalMessage = 'smert ze smert';
        $aliceMessage = $aliceSessionCipher->encrypt($originalMessage);

        $this->assertTrue($aliceMessage->getType() == CiphertextMessage::WHISPER_TYPE);
        $plaintext = $bobSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $aliceMessage->serialize()));
        $this->assertEquals($plaintext, $originalMessage);

        $bobMessage = $bobSessionCipher->encrypt($originalMessage);

        $this->assertTrue($bobMessage->getType() == CiphertextMessage::WHISPER_TYPE);

        $plaintext = $aliceSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $bobMessage->serialize()));
        $this->assertEquals($plaintext, $originalMessage);

        for ($i = 0; $i < 10; $i++) {
            $loopingMessage = 'What do we mean by saying that existence precedes essence? '.
                             'We mean that man first of all exists, encounters himself, '.
                             'surges up in the world--and defines himself aftward. '.$i;
            $aliceLoopingMessage = $aliceSessionCipher->encrypt($loopingMessage);
            $loopingPlaintext = $bobSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $aliceLoopingMessage->serialize()));
            $this->assertEquals($loopingPlaintext, $loopingMessage);
        }

        for ($i = 0; $i < 10; $i++) {
            $loopingMessage = 'What do we mean by saying that existence precedes essence? '.
                 'We mean that man first of all exists, encounters himself, '.
                 'surges up in the world--and defines himself aftward. '.$i;
            $bobLoopingMessage = $bobSessionCipher->encrypt($loopingMessage);

            $loopingPlaintext = $aliceSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $bobLoopingMessage->serialize()));
            $this->assertEquals($loopingPlaintext, $loopingMessage);
        }
        $aliceOutOfOrderMessages = [];

        for ($i = 0; $i < 10; $i++) {
            $loopingMessage = 'What do we mean by saying that existence precedes essence? '.
                 'We mean that man first of all exists, encounters himself, '.
                 'surges up in the world--and defines himself aftward. '.$i;
            $aliceLoopingMessage = $aliceSessionCipher->encrypt($loopingMessage);
            $aliceOutOfOrderMessages[] = [$loopingMessage, $aliceLoopingMessage];
        }
        for ($i = 0; $i < 10; $i++) {
            $loopingMessage = 'What do we mean by saying that existence precedes essence? '.
                 'We mean that man first of all exists, encounters himself, '.
                 'surges up in the world--and defines himself aftward.'.$i;
            $aliceLoopingMessage = $aliceSessionCipher->encrypt($loopingMessage);
            $loopingPlaintext = $bobSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $aliceLoopingMessage->serialize()));
            $this->assertEquals($loopingPlaintext, $loopingMessage);
        }
        for ($i = 0; $i < 10; $i++) {
            $loopingMessage = 'You can only desire based on what you know: '.$i;
            $bobLoopingMessage = $bobSessionCipher->encrypt($loopingMessage);

            $loopingPlaintext = $aliceSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $bobLoopingMessage->serialize()));
            $this->assertEquals($loopingPlaintext, $loopingMessage);
        }
        foreach ($aliceOutOfOrderMessages as $aliceOutOfOrderMessage) {
            $outOfOrderPlaintext = $bobSessionCipher->decryptMsg(new WhisperMessage(null, null, null, null, null, null, null, null, $aliceOutOfOrderMessage[1]->serialize()));
            $this->assertEquals($outOfOrderPlaintext, $aliceOutOfOrderMessage[0]);
        }
    }
}
