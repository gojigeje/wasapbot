<?php

/**
 * Copyright (C) 2013 Open Whisper Systems
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

//namespace libaxolotl;

require_once __DIR__."/ecc/Curve.php";
require_once __DIR__."/ecc/ECKeyPair.php";
require_once __DIR__."/ecc/ECPublicKey.php";
require_once __DIR__."/protocol/CiphertextMessage.php";
require_once __DIR__."/protocol/PreKeyWhisperMessage.php";
require_once __DIR__."/protocol/WhisperMessage.php";
require_once __DIR__."/ratchet/ChainKey.php";
require_once __DIR__."/ratchet/MessageKeys.php";
require_once __DIR__."/ratchet/RootKey.php";
require_once __DIR__."/state/AxolotlStore.php";
require_once __DIR__."/state/IdentityKeyStore.php";
require_once __DIR__."/state/PreKeyStore.php";
require_once __DIR__."/state/SessionRecord.php";
require_once __DIR__."/state/SessionState.php";
require_once __DIR__."/state/SessionStore.php";
require_once __DIR__."/state/SignedPreKeyStore.php";
require_once __DIR__."/util/ByteUtil.php";
require_once __DIR__."/util/Pair.php";

//require_once "/state/SessionState/UnacknowledgedPreKeyMessageItems.php";
class SessionCipher
{

    protected $sessionStore;
    protected $preKeyStore;
    protected $recepientId;
    protected $deviceId;
    protected $sessionBuilder;
    public function SessionCipher($sessionStore, $preKeyStore, $signedPreKeyStore, $identityKeyStore, $recepientId, $deviceId){
        $this->sessionStore = $sessionStore;
        $this->preKeyStore = $preKeyStore;
        $this->recipientId = $recepientId;
        $this->deviceId = $deviceId;
        $this->sessionBuilder = new SessionBuilder($sessionStore, $preKeyStore, $signedPreKeyStore,
                                             $identityKeyStore, $recepientId, $deviceId);

    }

    public function encrypt($paddedMessage){
        /*
        :type paddedMessage: str
        */


    /*paddedMessage = bytearray(paddedMessage.encode()
                                  if (sys.version_info >= (3,0) and not type(paddedMessage) in (bytes, bytearray))
                                     or type(paddedMessage) is unicode else paddedMessage)*/
        $sessionRecord   = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);
        $sessionState    = $sessionRecord->getSessionState();

        $chainKey        = $sessionState->getSenderChainKey();
        $messageKeys     = $chainKey->getMessageKeys();

        $senderEphemeral = $sessionState->getSenderRatchetKey();
        $previousCounter = $sessionState->getPreviousCounter();
        $sessionVersion  = $sessionState->getSessionVersion();

        $ciphertextBody    = $this->getCiphertext($sessionVersion, $messageKeys, $paddedMessage);
        $ciphertextMessage = new WhisperMessage($sessionVersion, $messageKeys->getMacKey(),
                                                               $senderEphemeral, $chainKey->getIndex(),
                                                               $previousCounter, $ciphertextBody,
                                                               $sessionState->getLocalIdentityKey(),
                                                               $sessionState->getRemoteIdentityKey());

        if ($sessionState->hasUnacknowledgedPreKeyMessage()){
            $items = $sessionState->getUnacknowledgedPreKeyMessageItems();
            $localRegistrationid = $sessionState->getLocalRegistrationId();



            $ciphertextMessage = new PreKeyWhisperMessage($sessionVersion, $localRegistrationid, $items->getPreKeyId(),
                                                     $items->getSignedPreKeyId(), $items->getBaseKey(),
                                                     $sessionState->getLocalIdentityKey(),
                                                     $ciphertextMessage);
        }
        $sessionState->setSenderChainKey($chainKey->getNextChainKey());
        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);

        return $ciphertextMessage;
    }

    public function decryptMsg($ciphertext){
        /*
        :type ciphertext: WhisperMessage
        */
        if (!$this->sessionStore->containsSession($this->recipientId, $this->deviceId))
            throw new NoSessionException("No session for: ".$this->recipientId.", ".$this->deviceId);

        $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);
        $plaintext = $this->decryptWithSessionRecord($sessionRecord, $ciphertext);

        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);

        /*if sys.version_info >= (3,0):
            return plaintext.decode()*/
        return $plaintext;
    }

    public function decryptPkmsg($ciphertext){
        /*
        :type ciphertext: PreKeyWhisperMessage
        */
        $sessionRecord = $this->sessionStore->loadSession($this->recipientId, $this->deviceId);
        $unsignedPreKeyId = $this->sessionBuilder->process($sessionRecord, $ciphertext);

        $plaintext = $this->decryptWithSessionRecord($sessionRecord, $ciphertext->getWhisperMessage());

        #callback.handlePlaintext(plaintext);
        $this->sessionStore->storeSession($this->recipientId, $this->deviceId, $sessionRecord);

        if ($unsignedPreKeyId != null){
            $this->preKeyStore->removePreKey($unsignedPreKeyId);
        }
        /*
        if sys.version_info >= (3, 0):
            return plaintext.decode()
        */
        return $plaintext;

    }

    public function decryptWithSessionRecord($sessionRecord, $cipherText){
        /*
        :type sessionRecord: SessionRecord
        :type cipherText: WhisperMessage
        */

        $previousStates = $sessionRecord->getPreviousSessionStates();
        $exceptions = [];
        try{
            $sessionState = new SessionState($sessionRecord->getSessionState());
            $plaintext = $this->decryptWithSessionState($sessionState, $cipherText);
            $sessionRecord->setState($sessionState);
            return $plaintext;
        }
        catch(InvalidMessageException $e){
            echo $e->getMessage()."\n";
            $exceptions[] = $e;

        }

        for ($i = 0;$i<count($previousStates);$i++){
            $previousState = $previousStates[$i];
            try
            {
              $promotedState = new SessionState($previousState);
              $plaintext = $this->decryptWithSessionState($promotedState, $cipherText);
              $sessionRecord->removePreviousSessionStateAt($i); // del $previousStates[$i]
              $sessionRecord->promoteState($promotedState);
              return $plaintext;
            }
            catch(InvalidMessageException $e){
              echo $e->getMessage()."\n";
                $exceptions[]  = $e;

            }

        }

        throw new InvalidMessageException("No valid sessions", $exceptions);
    }
    public function decryptWithSessionState($sessionState, $ciphertextMessage){

        if (!$sessionState->hasSenderChain()){
            throw new InvalidMessageException("Uninitialized session!");
        }

        if($ciphertextMessage->getMessageVersion() != $sessionState->getSessionVersion())
            throw new InvalidMessageException("Message version ".$ciphertextMessage->getMessageVersion().", but session version ". $sessionState->getSessionVersion());

        $messageVersion   = $ciphertextMessage->getMessageVersion();
        $theirEphemeral   = $ciphertextMessage->getSenderRatchetKey();
        $counter          = $ciphertextMessage->getCounter();
        $chainKey         = $this->getOrCreateChainKey($sessionState, $theirEphemeral);
        $messageKeys      = $this->getOrCreateMessageKeys($sessionState, $theirEphemeral,
                                                              $chainKey, $counter);





        $ciphertextMessage->verifyMac($messageVersion,
                                    $sessionState->getRemoteIdentityKey(),
                                    $sessionState->getLocalIdentityKey(),
                                    $messageKeys->getMacKey());

        $plaintext = $this->getPlaintext($messageVersion, $messageKeys, $ciphertextMessage->getBody());
        $sessionState->clearUnacknowledgedPreKeyMessage();

        return $plaintext;
    }

    public function getOrCreateChainKey($sessionState, $ECPublicKey_theirEphemeral){
        $theirEphemeral = $ECPublicKey_theirEphemeral;
        if($sessionState->hasReceiverChain($theirEphemeral)){
            return $sessionState->getReceiverChainKey($theirEphemeral);
        }
        else{
            $rootKey = $sessionState->getRootKey();

            $ourEphemeral = $sessionState->getSenderRatchetKeyPair();
            $receiverChain = $rootKey->createChain($theirEphemeral, $ourEphemeral);
            $ourNewEphemeral = Curve::generateKeyPair();
            $senderChain = $receiverChain[0]->createChain($theirEphemeral, $ourNewEphemeral);

            $sessionState->setRootKey($senderChain[0]);
            $sessionState->addReceiverChain($theirEphemeral, $receiverChain[1]);
            $sessionState->setPreviousCounter(max($sessionState->getSenderChainKey()->getIndex() - 1, 0));
            $sessionState->setSenderChain($ourNewEphemeral, $senderChain[1]);
            return $receiverChain[1];
        }
    }
    public function getOrCreateMessageKeys($sessionState, $ECPublicKey_theirEphemeral, $chainKey, $counter){
        $theirEphemeral = $ECPublicKey_theirEphemeral;
        if($chainKey->getIndex() > $counter){
            if ($sessionState->hasMessageKeys($theirEphemeral, $counter) )
            {
                return $sessionState->removeMessageKeys($theirEphemeral, $counter);
            }
            else{
                throw new DuplicateMessageException("Received message ".
                                 "with old counter: ".$chainKey->getIndex(). " ". $counter);
            }
        }
        if($counter - $chainKey->getIndex() > 2000)
            throw new InvalidMessageException("Over 2000 messages into the future!");

        while($chainKey->getIndex() < $counter){
            $messageKeys = $chainKey->getMessageKeys();
            $sessionState->setMessageKeys($theirEphemeral, $messageKeys);
            $chainKey = $chainKey->getNextChainKey();
        }
        $sessionState->setReceiverChainKey($theirEphemeral, $chainKey->getNextChainKey());
        return $chainKey->getMessageKeys();

    }
    public function getCiphertext($version, $messageKeys, $plainText){
        /*
        :type version: int
        :type messageKeys: MessageKeys
        :type  plainText: bytearray
        */
        $cipher = null;
        if ($version >= 3)
            $cipher = $this->getCipher($messageKeys->getCipherKey(), $messageKeys->getIv());
        else
            $cipher = $this->getCipher_v2($messageKeys->getCipherKey(), $messageKeys->getCounter());

        return $cipher->encrypt($plainText);
    }
    public function getPlaintext($version, $messageKeys, $cipherText){
        $cipher = null;
        if($version >= 3)
           $cipher = $this->getCipher($messageKeys->getCipherKey(), $messageKeys->getIv());
        else
            $cipher = $this->getCipher_v2($messageKeys->getCipherKey(), $messageKeys->getCounter());

        return $cipher->decrypt($cipherText);

    }
    public function getCipher($key, $iv){
        #Cipher.getInstance("AES/CBC/PKCS5Padding");
        #cipher = AES.new(key, AES.MODE_CBC, IV = iv)
        #return cipher
        return new AESCipher($key, $iv);
    }

    public function getCipher_v2($key, $counter){
       /* #AES/CTR/NoPadding
        #counterbytes = struct.pack('>L', counter) + (b'\x00' * 12)
        #counterint = struct.unpack(">L", counterbytes)[0]
        #counterint = int.from_bytes(counterbytes, byteorder='big')
        ctr=Counter.new(128, initial_value= counter)

        #cipher = AES.new(key, AES.MODE_CTR, counter=ctr)
        ivBytes = bytearray(16)
        ByteUtil.intToByteArray(ivBytes, 0, counter)

        cipher = AES.new(key, AES.MODE_CTR, IV = bytes(ivBytes), counter=ctr)

        return cipher;*/
        return new AESCipher($key,null, 2, new CryptoCounter(128,$counter));
        throw new Exception("To be implemented.");
    }
}
class CryptoCounter{
    protected $size;
    protected $val;
    public function CryptoCounter($size = 128, $init_val = 0){
        $this->val = $init_val;
        if(!in_array($size,[128,192,256])) throw new Exception("Counter size cannot be other than 128,192 or 256 bits");
        $this->size = $size/8;
    }
    public function Next(){
        $b = array_reverse(unpack("C*", pack("L", $this->val)));
        //byte array to string
        $ctr_str = implode(array_map("chr", $b));
        // create 16 byte IV from counter
        $ctrVal = str_repeat("\x0", ($this->size-4)).$ctr_str;
        $this->val++;
        return $ctrVal;
    }
}
class AESCipher{

    protected $key;
    protected $iv;
    protected $version;
    protected $counter;
    public function AESCipher($key, $iv,$version = 3, $counter = null){
        $this->key = $key;
        $this->iv = $iv;
        $this->version = $version;
        if($this->version < 3 && $counter == null) throw new Exception("Counter is needed for version < 3");
        $this->counter = $counter;
    }
    private function pad($s){
      $BS = 16;
      return $s.str_repeat(chr($BS - (strlen($s) % $BS)), ($BS - (strlen($s) % $BS)));
    }
    private function unpad($s,$diff = 0){
      return substr($s,0,-1*(ord($s[strlen($s)-1])-$diff));
    }
    public function encrypt($raw ){
        # if sys.version_info >= (3,0):
        #     rawPadded = pad(raw.decode()).encode()
        # else:
        if($this->version >= 3){
            $rawPadded = $this->pad($raw);
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->key, $rawPadded, MCRYPT_MODE_CBC, $this->iv);
        }
        else{
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->key, $raw, "ctr", $this->counter->Next());
        }
    }
    public function decrypt($enc){

        if($this->version >= 3){
            $result = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->key, $enc, MCRYPT_MODE_CBC, $this->iv);

            $unpaded = $this->unpad($result);
            $last_unpadded = $unpaded[strlen($unpaded)-1];
            $double_padding = substr($unpaded,-1*(ord($last_unpadded)-1));
            if(ord($last_unpadded)-1 == strlen($double_padding)){
              $has_dp = true;
              for($x=0;$x<strlen($double_padding);$x++)
              {
                if($double_padding[$x]!=$last_unpadded)
                {
                  $has_dp = false;
                  break;
                }
              }
            }
            else $has_dp = false;
            if($has_dp)
              $unpaded = $this->unpad($unpaded,1);
            return $unpaded;
        }
        else{
            return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->key, $enc, "ctr", $this->counter->Next());
        }
    }
}
