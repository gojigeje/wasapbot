<?php

require_once __DIR__.'/inmemorysenderkeystore.php';
require_once __DIR__.'/../../groups/GroupSessionBuilder.php';
require_once __DIR__.'/../../groups/GroupCipher.php';
require_once __DIR__.'/../../util/KeyHelper.php';
require_once __DIR__.'/../../DuplicateMessageException.php';
require_once __DIR__.'/../../NoSessionException.php';

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
class GroupCipherTest extends PHPUnit_Framework_TestCase
{
    public function test_basicEncryptDecrypt()
    {
        $aliceStore = new InMemorySenderKeyStore();
        $bobStore = new InMemorySenderKeyStore();
        $charlieStore = new InMemorySenderKeyStore();

        $aliceSessionBuilder = new GroupSessionBuilder($aliceStore);
        $bobSessionBuilder = new GroupSessionBuilder($bobStore);
        $charlieSessionBuilder = new GroupSessionBuilder($charlieStore);

        $aliceGroupCipher = new GroupCipher($aliceStore, 'groupWithBobInIt');
        $bobGroupCipher = new GroupCipher($bobStore, 'groupWithBobInIt::aliceUserName');
        $charlieGroupCipher = new GroupCipher($charlieStore, 'groupWithBobInIt::aliceUserName');

        $aliceSenderKey = KeyHelper::generateSenderKey();
        $aliceSenderSigningKey = KeyHelper::generateSenderSigningKey();
        $aliceSenderKeyId = KeyHelper::generateSenderKeyId();

        $aliceDistributionMessage = $aliceSessionBuilder->process('groupWithBobInIt', $aliceSenderKeyId, 0,
                                $aliceSenderKey, $aliceSenderSigningKey);
        echo niceVarDump($aliceDistributionMessage);
        echo niceVarDump($aliceDistributionMessage->serialize());
        echo $aliceDistributionMessage->serialize();
        $bobSessionBuilder->processSender('groupWithBobInIt::aliceUserName', $aliceDistributionMessage);

        $ciphertextFromAlice = $aliceGroupCipher->encrypt('smert ze smert');
        $plaintextFromAlice_Bob = $bobGroupCipher->decrypt($ciphertextFromAlice);
        $ciphertextFromAlice_2 = $aliceGroupCipher->encrypt('smert ze smert');
        echo niceVarDump($aliceDistributionMessage);
        $charlieSessionBuilder->processSender('groupWithBobInIt::aliceUserName', $aliceDistributionMessage);
        $plaintextFromAlice_Charlie = $charlieGroupCipher->decrypt($ciphertextFromAlice_2);

        $this->assertEquals($plaintextFromAlice_Bob, 'smert ze smert');
        $this->assertEquals($plaintextFromAlice_Charlie, 'smert ze smert');
    }

  /*  public function test_basicRatchet()
    {
        $aliceStore = new InMemorySenderKeyStore();
        $bobStore   = new InMemorySenderKeyStore();

        $aliceSessionBuilder = new GroupSessionBuilder($aliceStore);
        $bobSessionBuilder   = new GroupSessionBuilder($bobStore);

        $aliceGroupCipher = new GroupCipher($aliceStore, "groupWithBobInIt");
        $bobGroupCipher   = new GroupCipher($bobStore, "groupWithBobInIt::aliceUserName");

        $aliceSenderKey        = KeyHelper::generateSenderKey();
        $aliceSenderSigningKey = KeyHelper::generateSenderSigningKey();
        $aliceSenderKeyId      = KeyHelper::generateSenderKeyId();

        $aliceDistributionMessage = $aliceSessionBuilder->process("groupWithBobInIt", $aliceSenderKeyId, 0,
                                    $aliceSenderKey, $aliceSenderSigningKey);

        $bobSessionBuilder->processSender("groupWithBobInIt::aliceUserName", $aliceDistributionMessage);

        $ciphertextFromAlice  = $aliceGroupCipher->encrypt("smert ze smert");
        $ciphertextFromAlice2 = $aliceGroupCipher->encrypt("smert ze smert2");
        $ciphertextFromAlice3 = $aliceGroupCipher->encrypt("smert ze smert3");

        $plaintextFromAlice   = $bobGroupCipher->decrypt($ciphertextFromAlice);

        try {
          $bobGroupCipher->decrypt($ciphertextFromAlice);
          throw new AssertionError("Should have ratcheted forward!");
        } catch (DuplicateMessageException $dme) {
            #good
        }

        $plaintextFromAlice2  = $bobGroupCipher->decrypt($ciphertextFromAlice2);
        $plaintextFromAlice3  = $bobGroupCipher->decrypt($ciphertextFromAlice3);

        $this->assertEquals($plaintextFromAlice,"smert ze smert");
        $this->assertEquals($plaintextFromAlice2, "smert ze smert2");
        $this->assertEquals($plaintextFromAlice3, "smert ze smert3");

    }
    public function test_outOfOrder()
    {

        $aliceStore = new InMemorySenderKeyStore();
        $bobStore   = new InMemorySenderKeyStore();

        $aliceSessionBuilder = new GroupSessionBuilder($aliceStore);
        $bobSessionBuilder   = new GroupSessionBuilder($bobStore);

        $aliceGroupCipher = new GroupCipher($aliceStore, "groupWithBobInIt");
        $bobGroupCipher   = new GroupCipher($bobStore, "groupWithBobInIt::aliceUserName");

        $aliceSenderKey        = KeyHelper::generateSenderKey();
        $aliceSenderSigningKey = KeyHelper::generateSenderSigningKey();
        $aliceSenderKeyId      = KeyHelper::generateSenderKeyId();

        $aliceDistributionMessage = $aliceSessionBuilder->process("groupWithBobInIt", $aliceSenderKeyId, 0,
                                    $aliceSenderKey, $aliceSenderSigningKey);

        $bobSessionBuilder->processSender("groupWithBobInIt::aliceUserName", $aliceDistributionMessage);

        $ciphertexts = [];
        for ($i = 0; $i < 100; $i++)
            $ciphertexts[] = $aliceGroupCipher->encrypt("up the punks");
        while (count($ciphertexts) > 0)
        {
            $index = KeyHelper::getRandomSequence(2147483647) % count($ciphertexts);
            $elements = array_splice($ciphertexts,$index,1);
            $ciphertext = $elements[0];
            $plaintext = $bobGroupCipher->decrypt($ciphertext);
            $this->assertEquals($plaintext, "up the punks");
        }
    }

    public function test_encryptNoSession()
    {
        $aliceStore = new InMemorySenderKeyStore();
        $aliceGroupCipher = new GroupCipher($aliceStore, "groupWithBobInIt");
        try
        {
            $aliceGroupCipher->encrypt("up the punks");
            throw new AssertionError("Should have failed!");
        }
        catch (NoSessionException $nse)
        {
            # good
        }
    }*/
}
