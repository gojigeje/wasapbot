<?php
require_once 'Handler.php';
if (extension_loaded('curve25519') && extension_loaded('protobuf'))
{
  require_once __DIR__."/../libaxolotl-php/protocol/SenderKeyDistributionMessage.php";
  require_once __DIR__."/../libaxolotl-php/groups/GroupSessionBuilder.php";
  require_once __DIR__."/../pb_wa_messages.php";
}
require_once __DIR__."/../protocol.class.php";
require_once __DIR__."/../Constants.php";
require_once __DIR__."/../func.php";


class MessageHandler implements Handler
{
  protected $node;
  protected $parent;
  protected $phoneNumber;

  public function __construct($parent, $node)
  {
    $this->node         = $node;
    $this->parent       = $parent;
    $this->phoneNumber  = $this->parent->getMyNumber();
  }

  public function Process()
  {

    $this->parent->pushMessageToQueue($this->node);

    if ($this->node->hasChild('x') && $this->parent->lastId == $this->node->getAttribute('id')) {
        $this->parent->sendNextMessage();
    }
    if ($this->parent->getNewMsgBind()  && ($this->node->getChild('body') || $this->node->getChild('media'))) {
        $this->parent->getNewMsgBind()->process($this->node);
    }
   if ($this->node->getAttribute("type") == "text" && ($this->node->getChild('body') != null || $this->node->getChild('enc') != null)) {
        $this->processMessageNode($this->node);
    }
    if ($this->node->getAttribute("type") == "media" && ($this->node->getChild('media') != null || $this->node->getChild("enc") != null)) {
        if($this->node->getChild("enc") != null && $this->node->getAttribute("participant") == null) { // for now only private messages

          if (extension_loaded('curve25519') && extension_loaded('protobuf'))
            $dec_node = $this->processEncryptedNode($this->node);
          if($dec_node)
            $this->node = $dec_node;
        }
        if($this->node->getChild('media') != null) {
          if ($this->node->getChild("media")->getAttribute('type') == 'image') {

            if ($this->node->getAttribute("participant") == null) {
              $this->parent->eventManager()->fire("onGetImage",
                array(
                  $this->phoneNumber,
                  $this->node->getAttribute('from'),
                  $this->node->getAttribute('id'),
                  $this->node->getAttribute('type'),
                  $this->node->getAttribute('t'),
                  $this->node->getAttribute('notify'),
                  $this->node->getChild("media")->getAttribute('size'),
                  $this->node->getChild("media")->getAttribute('url'),
                  $this->node->getChild("media")->getAttribute('file'),
                  $this->node->getChild("media")->getAttribute('mimetype'),
                  $this->node->getChild("media")->getAttribute('filehash'),
                  $this->node->getChild("media")->getAttribute('width'),
                  $this->node->getChild("media")->getAttribute('height'),
                  $this->node->getChild("media")->getData(),
                  $this->node->getChild("media")->getAttribute('caption')
              ));
            } else {
                $this->parent->eventManager()->fire("onGetGroupImage",
                  array(
                    $this->phoneNumber,
                    $this->node->getAttribute('from'),
                    $this->node->getAttribute('participant'),
                    $this->node->getAttribute('id'),
                    $this->node->getAttribute('type'),
                    $this->node->getAttribute('t'),
                    $this->node->getAttribute('notify'),
                    $this->node->getChild("media")->getAttribute('size'),
                    $this->node->getChild("media")->getAttribute('url'),
                    $this->node->getChild("media")->getAttribute('file'),
                    $this->node->getChild("media")->getAttribute('mimetype'),
                    $this->node->getChild("media")->getAttribute('filehash'),
                    $this->node->getChild("media")->getAttribute('width'),
                    $this->node->getChild("media")->getAttribute('height'),
                    $this->node->getChild("media")->getData(),
                    $this->node->getChild("media")->getAttribute('caption')
                  ));
              }

              $msgId = $this->parent->createIqId();
              $ackNode = new ProtocolNode("ack",
                  array(
                      "url" => $this->node->getChild("media")->getAttribute('url')
                  ), null, null);

              $iqNode = new ProtocolNode("iq",
                  array(
                      "id" => $msgId,
                      "xmlns" => "w:m",
                      "type" => "set",
                      "to" => Constants::WHATSAPP_SERVER
                  ), array($ackNode), null);

              $this->parent->sendNode($iqNode);

          } elseif ($this->node->getChild("media")->getAttribute('type') == 'video') {
            if ($this->node->getAttribute("participant") == null) {

              $this->parent->eventManager()->fire("onGetVideo",
                array(
                  $this->phoneNumber,
                  $this->node->getAttribute('from'),
                  $this->node->getAttribute('id'),
                  $this->node->getAttribute('type'),
                  $this->node->getAttribute('t'),
                  $this->node->getAttribute('notify'),
                  $this->node->getChild("media")->getAttribute('url'),
                  $this->node->getChild("media")->getAttribute('file'),
                  $this->node->getChild("media")->getAttribute('size'),
                  $this->node->getChild("media")->getAttribute('mimetype'),
                  $this->node->getChild("media")->getAttribute('filehash'),
                  $this->node->getChild("media")->getAttribute('duration'),
                  $this->node->getChild("media")->getAttribute('vcodec'),
                  $this->node->getChild("media")->getAttribute('acodec'),
                  $this->node->getChild("media")->getData(),
                  $this->node->getChild("media")->getAttribute('caption'),
                  $this->node->getChild("media")->getAttribute('width'),
                  $this->node->getChild("media")->getAttribute('height'),
                  $this->node->getChild("media")->getAttribute('fps'),
                  $this->node->getChild("media")->getAttribute('vbitrate'),
                  $this->node->getChild("media")->getAttribute('asampfreq'),
                  $this->node->getChild("media")->getAttribute('asampfmt'),
                  $this->node->getChild("media")->getAttribute('abitrate')
              ));
          } else {
              $this->parent->eventManager()->fire("onGetGroupVideo",
                array(
                  $this->phoneNumber,
                  $this->node->getAttribute('from'),
                  $this->node->getAttribute('participant'),
                  $this->node->getAttribute('id'),
                  $this->node->getAttribute('type'),
                  $this->node->getAttribute('t'),
                  $this->node->getAttribute('notify'),
                  $this->node->getChild("media")->getAttribute('url'),
                  $this->node->getChild("media")->getAttribute('file'),
                  $this->node->getChild("media")->getAttribute('size'),
                  $this->node->getChild("media")->getAttribute('mimetype'),
                  $this->node->getChild("media")->getAttribute('filehash'),
                  $this->node->getChild("media")->getAttribute('duration'),
                  $this->node->getChild("media")->getAttribute('vcodec'),
                  $this->node->getChild("media")->getAttribute('acodec'),
                  $this->node->getChild("media")->getData(),
                  $this->node->getChild("media")->getAttribute('caption'),
                  $this->node->getChild("media")->getAttribute('width'),
                  $this->node->getChild("media")->getAttribute('height'),
                  $this->node->getChild("media")->getAttribute('fps'),
                  $this->node->getChild("media")->getAttribute('vbitrate'),
                  $this->node->getChild("media")->getAttribute('asampfreq'),
                  $this->node->getChild("media")->getAttribute('asampfmt'),
                  $this->node->getChild("media")->getAttribute('abitrate')
              ));
          }
        } elseif ($this->node->getChild("media")->getAttribute('type') == 'audio') {
          $author = $this->node->getAttribute("participant");
          $this->parent->eventManager()->fire("onGetAudio",
            array(
              $this->phoneNumber,
              $this->node->getAttribute('from'),
              $this->node->getAttribute('id'),
              $this->node->getAttribute('type'),
              $this->node->getAttribute('t'),
              $this->node->getAttribute('notify'),
              $this->node->getChild("media")->getAttribute('size'),
              $this->node->getChild("media")->getAttribute('url'),
              $this->node->getChild("media")->getAttribute('file'),
              $this->node->getChild("media")->getAttribute('mimetype'),
              $this->node->getChild("media")->getAttribute('filehash'),
              $this->node->getChild("media")->getAttribute('seconds'),
              $this->node->getChild("media")->getAttribute('acodec'),
              $author,
            ));
        } elseif ($this->node->getChild("media")->getAttribute('type') == 'vcard') {
          if ($this->node->getChild("media")->hasChild('vcard')) {
            $name = $this->node->getChild("media")->getChild("vcard")->getAttribute('name');
            $data = $this->node->getChild("media")->getChild("vcard")->getData();
          } else {
            $name = "NO_NAME";
            $data = $this->node->getChild("media")->getData();
          }
          $author = $this->node->getAttribute("participant");

          $this->parent->eventManager()->fire("onGetvCard",
            array(
              $this->phoneNumber,
              $this->node->getAttribute('from'),
              $this->node->getAttribute('id'),
              $this->node->getAttribute('type'),
              $this->node->getAttribute('t'),
              $this->node->getAttribute('notify'),
              $name,
              $data,
              $author
            ));
        } elseif ($this->node->getChild("media")->getAttribute('type') == 'location') {
          $url = $this->node->getChild("media")->getAttribute('url');
          $name = $this->node->getChild("media")->getAttribute('name');
          $author = $this->node->getAttribute("participant");

          $this->parent->eventManager()->fire("onGetLocation",
            array(
              $this->phoneNumber,
              $this->node->getAttribute('from'),
              $this->node->getAttribute('id'),
              $this->node->getAttribute('type'),
              $this->node->getAttribute('t'),
              $this->node->getAttribute('notify'),
              $name,
              $this->node->getChild("media")->getAttribute('longitude'),
              $this->node->getChild("media")->getAttribute('latitude'),
              $url,
              $this->node->getChild("media")->getData(),
              $author
            ));
        }
      }

      //Read receipt for media messages
      if ($this->parent->getReadReceipt())
        $this->parent->sendReceipt($this->node, 'read', $this->node->getAttribute("participant"));
      else
        $this->parent->sendReceipt($this->node, 'receipt', $this->node->getAttribute("participant"));
    }
    if ($this->node->getChild('received') != null) {
        $this->parent->eventManager()->fire("onMessageReceivedClient",
          array(
            $this->phoneNumber,
            $this->node->getAttribute('from'),
            $this->node->getAttribute('id'),
            $this->node->getAttribute('type'),
            $this->node->getAttribute('t'),
            $this->node->getAttribute('participant')
        ));
    }
  }

  protected function processMessageNode(ProtocolNode $node){
    //encrypted node
    if($node->getChild("enc") != null){

      if (extension_loaded('curve25519') && extension_loaded('protobuf')){
        $ack = new ProtocolNode("ack",["to"=>$node->getAttribute("from"),"class"=>"message","id"=>$node->getAttribute("id"),"t"=>$node->getAttribute("t")],null,null);
        $this->parent->sendNode($ack);
        $dec_node = $this->processEncryptedNode($node);
        if($dec_node instanceof ProtocolNode){
          $node = $dec_node;
        }
      }
    }
    if($node){
      $author = $node->getAttribute("participant");
      if ($author == "") {
        // Single chats
          if ($node->hasChild("body")) {
            if ($this->parent->getReadReceipt()) {
                $this->parent->sendReceipt($node, 'read', $author);
            }
            else
              $this->parent->sendReceipt($this->node, 'receipt', $author);

          $this->parent->eventManager()->fire("onGetMessage",
              array(
                  $this->phoneNumber,
                  $node->getAttribute('from'),
                  $node->getAttribute('id'),
                  $node->getAttribute('type'),
                  $node->getAttribute('t'),
                  $node->getAttribute("notify"),
                  $node->getChild("body")->getData()
              ));

          if ($this->parent->getMessageStore() !== null) {
              $this->parent->getMessageStore()->saveMessage(ExtractNumber($node->getAttribute('from')), $this->phoneNumber, $node->getChild("body")->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
          }
        }
      } else {
          //group chat message
          if ($node->hasChild('body'))
          {
            if ($this->parent->getReadReceipt())
              $this->parent->sendReceipt($node, 'read', $author);
            else
              $this->parent->sendReceipt($this->node, 'receipt', $this->node->getAttribute("participant"));

            $this->parent->eventManager()->fire("onGetGroupMessage",
              array(
                  $this->phoneNumber,
                  $node->getAttribute('from'),
                  $author,
                  $node->getAttribute('id'),
                  $node->getAttribute('type'),
                  $node->getAttribute('t'),
                  $node->getAttribute("notify"),
                  $node->getChild("body")->getData()
              ));
            if ($this->parent->getMessageStore() !== null)
              $this->parent->getMessageStore()->saveMessage($author, $node->getAttribute('from'), $node->getChild("body")->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
          }
      }
    }
  }
  protected function processEncryptedNode(ProtocolNode $node){
    if($this->parent->getAxolotlStore() == null){
      return null;
    }
    //is a chat encrypted message
    $from = $node->getAttribute('from');
    if (strpos($from,Constants::WHATSAPP_SERVER) !== false)
    {

       $author = ExtractNumber($node->getAttribute("from"));

       $version  = $node->getChild(0)->getAttribute("v");
       $encType  = $node->getChild(0)->getAttribute("type");
       $encMsg   = $node->getChild("enc")->getData();
       if(!$this->parent->getAxolotlStore()->containsSession($author, 1)){
         //we don't have the session to decrypt, save it in pending and process it later
         $this->parent->addPendingNode($node);
         $this->parent->logFile('info', 'Requesting cipher keys from {from}', array('from' => $author));
         $this->parent->sendGetCipherKeysFromUser($author);

       }
       else{
         //decrypt the message with the session
           if ($node->getChild("enc")->getAttribute('count') == "")
            $this->parent->setRetryCounter($node->getAttribute("id"),1);

           if ($version == "2")
           {
            if (!in_array($author, $this->parent->getv2Jids()))
              $this->parent->setv2Jids($author);
           }

          $plaintext = $this->decryptMessage($from, $encMsg, $encType, $node->getAttribute('id'), $node->getAttribute('t'));

          //$plaintext ="A";
          if($plaintext === false) {
            $this->parent->sendRetry($this->node,$from, $node->getAttribute('id'), $node->getAttribute('t'));
            $this->parent->logFile('info', 'Couldn\'t decrypt message with {id} id from {from}. Retrying...', array('id' => $node->getAttribute('id'), 'from' => ExtractNumber($from)));
            return $node; // could not decrypt
          }
          if(isset($this->parent->retryNodes[$node->getAttribute('id')])) unset($this->parent->retryNodes[$node->getAttribute('id')]);
          if(isset($this->parent->retryCounters[$node->getAttribute('id')])) unset($this->parent->retryCounters[$node->getAttribute('id')]);
          switch($node->getAttribute("type")){
            case "text":
              $node->addChild(new ProtocolNode("body", null, null, $plaintext));
              break;
            case "media":

              switch($node->getChild("enc")->getAttribute("mediatype")){
                case "image":
                  $image = new ImageMessage();
                  $image->parseFromString($plaintext);
                  $keys = (new HKDFv3())->deriveSecrets($image->getRefKey(), hex2bin("576861747341707020496d616765204b657973"), 112);
                  $iv = substr($keys,0,16);
                  $keys = substr($keys,16);
                  $parts = str_split($keys,32);
                  $key = $parts[0];
                  $macKey = $parts[1];
                  $refKey = $parts[2];

                  //should be changed to nice curl, no extra headers :D
                  $file_enc = file_get_contents($image->getUrl());
                  //requires mac check , last 10 chars
                  $mac = substr($file_enc,-10);
                  $cipherImage = substr($file_enc,0,strlen($file_enc)-10);
                  $decrypted_image = pkcs5_unpad(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $cipherImage, MCRYPT_MODE_CBC,$iv));
                  //$save_file = tempnam(sys_get_temp_dir(),"WAIMG_");
                  //file_put_contents($save_file,$decrypted_image);
                  $child = new ProtocolNode("media",
                    array(
                        "size" => $image->getLength(),
                        "caption" => $image->getCaption(),
                        "url" => $image->getUrl(),
                        "mimetype" => $image->getMimeType(),
                        "filehash" => bin2hex($image->getSha256()),
                        "width" => 0,
                        "height" => 0,
                        "file" => $decrypted_image,
                        "type" => "image"
                      ), null, $image->getThumbnail());
                  $node->addChild($child);
                break;
              }
            break;

          }
          $this->parent->logFile('info', 'Decrypted message with {id} from {from}', array('id' => $node->getAttribute('id'), 'from' => ExtractNumber($from)));
          return $node;
       }
     }
     //is a group encrypted message
     else {
       $author =  ExtractNumber($node->getAttribute("participant"));
       $group_number = ExtractNumber($node->getAttribute("from"));
       $childs = $node->getChildren();
       foreach($childs as $child){
         if($child->getAttribute("type") == "pkmsg" || $child->getAttribute("type") == "msg"){
           if(!$this->parent->getAxolotlStore()->containsSession($author,1))
           {
             $this->parent->addPendingNode($node);

             $this->parent->sendGetCipherKeysFromUser($author);
             break;
           }
           else{

             //decrypt senderKey and save it
             $encType  = $child->getAttribute("type");
             $encMsg   = $child->getData();
             $from     = $node->getAttribute("participant");
             $version  = $child->getAttribute("v");
             if ($node->getChild("enc")->getAttribute('count') == "")
               $this->parent->setRetryCounter($node->getAttribute("id"),1);

             if ($version == "2")
             {
               if (!in_array($author, $this->parent->getv2Jids()))
                 $this->parent->setv2Jids($author);
             }
             $skip_unpad = $node->getChild("enc",["type"=>"skmsg"]) == null;
             $senderKeyBytes = $this->decryptMessage($from, $encMsg, $encType, $node->getAttribute('id'), $node->getAttribute('t'),$node->getAttribute("from"),$skip_unpad);
             if($senderKeyBytes){
               if(!$skip_unpad){
                 $senderKeyGroupMessage = new SenderKeyGroupMessage();
                 $senderKeyGroupMessage->parseFromString($senderKeyBytes);
               }
               else{
                $senderKeyGroupMessage = new SenderKeyGroupData();
                try{
                  $senderKeyGroupMessage->parseFromString($senderKeyBytes);
                }
                catch(Exception $ex){
                  try{
                    $senderKeyGroupMessage->parseFromString(substr($senderKeyBytes,0,-1));
                  }
                  catch(Exception $ex){ return $node;}
                }
                $message = $senderKeyGroupMessage->getMessage();
                $senderKeyGroupMessage = $senderKeyGroupMessage->getSenderKey();
               }
               $senderKey = new SenderKeyDistributionMessage(null, null, null, null, $senderKeyGroupMessage->getSenderKey());
               $groupSessionBuilder = new GroupSessionBuilder($this->parent->axolotlStore);
               $groupSessionBuilder->processSender($group_number.":".$author,$senderKey);
               if(isset($message)){
                 $this->parent->sendReceipt($node, 'receipt', $this->parent->getJID($this->phoneNumber));
                 $node->addChild(new ProtocolNode("body", null, null, $message));
               }
             }

           }
         }
         else if($child->getAttribute("type") == "skmsg"){
           $version  = $child->getAttribute("v");
           if ($version == "2")
           {
             if (!in_array($author, $this->parent->v2Jids))
               $this->parent->setv2Jids($author);
           }

           $plaintext =  $this->decryptMessage([$group_number,$author], $child->getData(), $child->getAttribute("type"), $node->getAttribute('id'), $node->getAttribute('t'));

           if(!$plaintext) {
             $this->parent->sendRetry($this->node,$from, $node->getAttribute('id'), $node->getAttribute('t'),$node->getAttribute('participant'));
             $this->parent->logFile('info', 'Couldn\'t decrypt group message with {id} id from {from}. Retrying...', array('id' => $node->getAttribute('id'), 'from' => $from));
             return $node; // could not decrypt
           } else {
             if(isset($this->parent->retryNodes[$node->getAttribute('id')])) unset($this->parent->retryNodes[$node->getAttribute('id')]);
             if(isset($this->parent->retryCounters[$node->getAttribute('id')])) unset($this->parent->retryCounters[$node->getAttribute('id')]);
             $this->parent->logFile('info', 'Decrypted group message with {id} from {from}', array('id' => $node->getAttribute('id'), 'from' => $from));
             $this->parent->sendReceipt($node, 'receipt', $this->parent->getJID($this->phoneNumber));
             $node->addChild(new ProtocolNode("body", null, null, $plaintext));
           }
         }

       }
     }
  }

  function decryptMessage($from, $ciphertext, $type, $id, $t, $retry_from = null, $skip_unpad = false)
  {
    $version = "1";
    $this->parent->debugPrint("\n-> Decrypted Message: ");
    if ($type == "pkmsg")
    {
      if(in_array(ExtractNumber($from), $this->parent->getv2Jids())) $version = "2";

      try{
        $preKeyWhisperMessage = new PreKeyWhisperMessage(null, null, null, null, null, null, null, $ciphertext);
        $sessionCipher = $this->parent->getSessionCipher(ExtractNumber($from));
        $plaintext = $sessionCipher->decryptPkmsg($preKeyWhisperMessage);

        if ($version == "2" && !$skip_unpad){
          $plaintext = unpadV2Plaintext($plaintext);
        }
        $this->parent->debugPrint(parseText($plaintext)."\n\n");
        return $plaintext;
      } catch (Exception $e)
      {
        $this->parent->debugPrint($e->getMessage()." - ".$e->getFile()." - ".$e->getLine());
       // if ($e->getMessage() != "Null values!"){
          $this->parent->debugPrint("Message $id could not be decrypted, sending retry.\n\n");
          $participant = null;
          if($retry_from != null){
            if(strpos($retry_from,"-") !== false)
              $participant = $from;
            $from = $retry_from;
          }
          //$this->sendRetry($from, $id, $t, $participant);
          return false;
        //}
      }
    }
    // msg, WhisperMessage
    else if($type == "msg")
    {
      if(in_array(ExtractNumber($from), $this->parent->getv2Jids())) $version = "2";
      try{
        $whisperMessage = new WhisperMessage(null, null, null, null, null, null, null, null, $ciphertext);
        $sessionCipher = $this->parent->getSessionCipher(ExtractNumber($from));
        $plaintext = $sessionCipher->decryptMsg($whisperMessage);

        if ($version == "2" && !$skip_unpad){
          $plaintext = unpadV2Plaintext($plaintext);
        }
        $this->parent->debugPrint(parseText($plaintext)."\n\n");
        return $plaintext;
      } catch (Exception $e)
      {
        $this->parent->debugPrint($e->getMessage()." - ".$e->getFile()." - ".$e->getLine());
        $this->parent->debugPrint("Message $id could not be decrypted, sending retry.\n\n");
        if($retry_from != null)
          $from = $retry_from;
        //$this->sendRetry($from, $id, $t);
        return false;
      }
    }
    else if($type == "skmsg"){
      if(in_array($from[1], $this->parent->v2Jids)) $version = "2";
      try{
        $groupCipher = $this->parent->getGroupCipher(ExtractNumber($from[0]).":".$from[1]);
        $plaintext =  $groupCipher->decrypt($ciphertext);
        if($version == "2" && !$skip_unpad)
            $plaintext = unpadV2Plaintext($plaintext);
        $this->parent->debugPrint("Message $id decrypted to ".parseText($plaintext)."\n\n");
        return $plaintext;
      }catch(Exception $e){
        $this->parent->debugPrint($e->getMessage()." - ".$e->getFile()." - ".$e->getLine());
        if($retry_from != null) $from = $retry_from;
        $this->parent->sendRetry($this->node,$this->parent->getJID($from[0]), $id, $t);
        return false;
      }
    }
  }
}
