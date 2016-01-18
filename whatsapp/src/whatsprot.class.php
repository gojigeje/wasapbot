<?php

require_once 'protocol.class.php';
require_once 'BinTreeNodeReader.php';
require_once 'BinTreeNodeWriter.php';
require_once 'Login.php';
require_once 'Logger.php';
require_once 'Constants.php';
require_once 'func.php';
require_once 'token.php';
require_once 'rc4.php';
require_once 'mediauploader.php';
require_once 'keystream.class.php';
require_once 'tokenmap.class.php';
require_once 'events/WhatsApiEventsManager.php';
require_once 'SqliteMessageStore.php';
require_once 'SqliteAxolotlStore.php';
require_once 'handlers/NotificationHandler.php';
require_once 'handlers/MessageHandler.php';
require_once 'handlers/IqHandler.php';
if (extension_loaded('curve25519') && extension_loaded('protobuf')) {
    require_once 'pb_wa_messages.php';
    require_once 'libaxolotl-php/util/KeyHelper.php';
    require_once 'libaxolotl-php/ecc/Curve.php';
    require_once 'libaxolotl-php/state/PreKeyRecord.php';
    require_once 'libaxolotl-php/state/PreKeyBundle.php';
    require_once 'libaxolotl-php/SessionBuilder.php';
    require_once 'libaxolotl-php/SessionCipher.php';
    require_once 'libaxolotl-php/groups/GroupCipher.php';
    require_once 'libaxolotl-php/groups/GroupSessionBuilder.php';
}

class WhatsProt
{
    /**
     * Property declarations.
     */
    protected $accountInfo;             // The AccountInfo object.
    protected $challengeFilename;       // Path to nextChallenge.dat.
    protected $challengeData;           //
    protected $debug;                   // Determines whether debug mode is on or off.
    protected $eventManager;            // An instance of the WhatsApiEvent Manager.
    protected $groupList = [];     // An array with all the groups a user belongs in.
    protected $outputKey;               // Instances of the KeyStream class.
    protected $groupId = false;         // Id of the group created.
    protected $lastId = false;          // Id to the last message sent.
    protected $loginStatus;             // Holds the login status.
    protected $mediaFileInfo = []; // Media File Information
    protected $mediaQueue = [];    // Queue for media message nodes
    protected $messageCounter = 0;      // Message counter for auto-id.
    protected $iqCounter = 1;
    protected $messageQueue = [];  // Queue for received messages.
    protected $name;                    // The user name.
    protected $newMsgBind = false;      //
    protected $outQueue = [];      // Queue for outgoing messages.
    protected $password;                // The user password.
    protected $phoneNumber;             // The user phone number including the country code without '+' or '00'.
    protected $serverReceivedId;        // Confirm that the *server* has received your command.
    protected $socket;                  // A socket to connect to the WhatsApp network.
    protected $messageStore;
    protected $nodeId = [];
    protected $messageId;
    protected $voice;
    protected $timeout = 0;
    protected $sessionCiphers = [];
    public $v2Jids = [];
    public $v1Only = [];
    protected $groupCiphers = [];
    protected $pending_nodes = [];
    protected $replaceKey;
    public $retryCounters = [];
    protected $readReceipts = true;
    public $retryNodes = [];
    public $axolotlStore;
    public $writer;                  // An instance of the BinaryTreeNodeWriter class.
    public $reader;                  // An instance of the BinaryTreeNodeReader class.
    public $logger;
    public $log;
    public $dataFolder;              //

    /**
     * Default class constructor.
     *
     * @param string $number
     *                         The user phone number including the country code without '+' or '00'.
     * @param string $nickname
     *                         The user name.
     * @param $debug
     *   Debug on or off, false by default.
     * @param $log
     *  Enable log, false by default.
     * @param $datafolder
     *  The folder for whatsapp data like MEDIA, PICTURES etc.. By default that is wadata in src folder
     */
    public function __construct($number, $nickname, $debug = false, $log = false, $datafolder = null)
    {
        $this->writer = new BinTreeNodeWriter();
        $this->reader = new BinTreeNodeReader();
        $this->debug = $debug;
        $this->phoneNumber = $number;

        if ($datafolder !== null && file_exists($datafolder)) {
            if (substr(trim($datafolder), -1) == DIRECTORY_SEPARATOR) {
                $this->dataFolder = $datafolder;
            } else {
                $this->dataFolder = $datafolder.DIRECTORY_SEPARATOR;
            }
        } else {
            $this->dataFolder = __DIR__.DIRECTORY_SEPARATOR.Constants::DATA_FOLDER.DIRECTORY_SEPARATOR;
        }

        if (!file_exists($this->dataFolder.Constants::MEDIA_FOLDER)) {
            mkdir($this->dataFolder.Constants::MEDIA_FOLDER, 0777, true);
        }

        if (!file_exists($this->dataFolder.Constants::PICTURES_FOLDER)) {
            mkdir($this->dataFolder.Constants::PICTURES_FOLDER, 0777, true);
        }

        if (!file_exists($this->dataFolder.'logs')) {
            mkdir($this->dataFolder.'logs', 0777, true);
        }

        //wadata/nextChallenge.12125557788.dat
        $this->challengeFilename = sprintf('%snextChallenge.%s.dat', $this->dataFolder, $number);
        $this->messageStore = new SqliteMessageStore($number);
        $this->log = $log;
        if ($log) {
            $this->logger = new Logger($this->dataFolder.
                    'logs'.DIRECTORY_SEPARATOR.$number.'.log');
        }

        $this->setAxolotlStore(new axolotlSqliteStore($number, $this->dataFolder));

        $this->name = $nickname;
        $this->loginStatus = Constants::DISCONNECTED_STATUS;
        $this->eventManager = new WhatsApiEventsManager();
    }

    /**
     * If you need use different challenge fileName you can use this.
     *
     * @param string $filename
     */
    public function setChallengeName($filename)
    {
        $this->challengeFilename = $filename;
    }

    /**
     * Add message to the outgoing queue.
     *
     * @param $node
     */
    public function addMsgOutQueue($node)
    {
        $this->outQueue[] = $node;
    }

    /**
     * Connect (create a socket) to the WhatsApp network.
     *
     * @return bool
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return true;
        }

        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket !== false) {
            $result = socket_connect($socket, 'e'.rand(1, 16).'.whatsapp.net', Constants::PORT);
            if ($result === false) {
                $socket = false;
            }
        }

        if ($socket !== false) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => Constants::TIMEOUT_SEC, 'usec' => Constants::TIMEOUT_USEC]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => Constants::TIMEOUT_SEC, 'usec' => Constants::TIMEOUT_USEC]);

            $this->socket = $socket;
            $this->eventManager()->fire('onConnect',
                [
                    $this->phoneNumber,
                    $this->socket,
                ]
            );
            $this->logFile('info', 'Connected to WA server');

            return true;
        } else {
            $this->logFile('error', 'Failed to connect WA server');
            $this->eventManager()->fire('onConnectError',
                [
                    $this->phoneNumber,
                    $this->socket,
                ]
            );

            return false;
        }
    }

    /**
     * Do we have an active socket connection to WhatsApp?
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->socket !== null;
    }

     /**
      * Disconnect from the WhatsApp network.
      */
     public function disconnect()
     {
         if (is_resource($this->socket)) {
             @socket_shutdown($this->socket, 2);
             @socket_close($this->socket);
         }
         $this->socket = null;
         $this->loginStatus = Constants::DISCONNECTED_STATUS;
         $this->logFile('info', 'Disconnected from WA server');
         $this->eventManager()->fire('onDisconnect',
             [
                 $this->phoneNumber,
                 $this->socket,
             ]
         );
     }

    /**
     * @return WhatsApiEventsManager
     */
    public function eventManager()
    {
        return $this->eventManager;
    }

     /**
      * Enable / Disable automatic read receipt
      * This is enabled by default.
      */
     public function enableReadReceipt($enable)
     {
         $this->readReceipts = $enable;
     }

    /**
     * Drain the message queue for application processing.
     *
     * @return ProtocolNode[]
     *                        Return the message queue list.
     */
    public function getMessages()
    {
        $ret = $this->messageQueue;
        $this->messageQueue = [];

        return $ret;
    }

    /**
     * Login to the WhatsApp server with your password.
     *
     * If you already know your password you can log into the Whatsapp server
     * using this method.
     *
     * @param string $password Your whatsapp password. You must already know this!
     */
    public function loginWithPassword($password)
    {
        $this->password = $password;
        if (is_readable($this->challengeFilename)) {
            $challengeData = file_get_contents($this->challengeFilename);
            if ($challengeData) {
                $this->challengeData = $challengeData;
            }
        }
        $login = new Login($this, $this->password);
        $login->doLogin();
    }

    /**
     * Fetch a single message node.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function pollMessage()
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection Closed!');
        }

        $r = [$this->socket];
        $w = [];
        $e = [];
        $s = socket_select($r, $w, $e, Constants::TIMEOUT_SEC, Constants::TIMEOUT_USEC);

        if ($s) {

            // Something to read
            if ($stanza = $this->readStanza()) {
                $this->processInboundData($stanza);

                return true;
            }
        }
        if (time() - $this->timeout > 60) {
            $this->sendPing();
        }

        return false;
    }

    /**
     * Send the active status. User will show up as "Online" (as long as socket is connected).
     */
    public function sendActiveStatus()
    {
        $messageNode = new ProtocolNode('presence', ['type' => 'active'], null, '');
        $this->sendNode($messageNode);
    }

    public function sendSetPreKeys($new = false)
    {
        $axolotl = new KeyHelper();

        $identityKeyPair = $axolotl->generateIdentityKeyPair();
        $privateKey = $identityKeyPair->getPrivateKey()->serialize();
        $publicKey = $identityKeyPair->getPublicKey()->serialize();
        $keys = $axolotl->generatePreKeys(mt_rand(), 200);
        $this->axolotlStore->storePreKeys($keys);

        for ($i = 0; $i < 200; $i++) {
            $prekeyId = adjustId($keys[$i]->getId());
            $prekey = substr($keys[$i]->getKeyPair()->getPublicKey()->serialize(), 1);
            $id = new ProtocolNode('id', null, null, $prekeyId);
            $value = new ProtocolNode('value', null, null, $prekey);
            $prekeys[] = new ProtocolNode('key', null, [$id, $value], null); // 200 PreKeys
        }

        if ($new) {
            $registrationId = $this->axolotlStore->getLocalRegistrationId();
        } else {
            $registrationId = $axolotl->generateRegistrationId();
        }
        $registration = new ProtocolNode('registration', null, null, adjustId($registrationId));
        $identity = new ProtocolNode('identity', null, null, substr($publicKey, 1));
        $type = new ProtocolNode('type', null, null, chr(Curve::DJB_TYPE));

        $this->axolotlStore->storeLocalData($registrationId, $identityKeyPair);

        $list = new ProtocolNode('list', null, $prekeys, null);

        $signedRecord = $axolotl->generateSignedPreKey($identityKeyPair, $axolotl->getRandomSequence(65536));
        $this->axolotlStore->storeSignedPreKey($signedRecord->getId(), $signedRecord);

        $sid = new ProtocolNode('id', null, null, adjustId($signedRecord->getId()));
        $value = new ProtocolNode('value', null, null, substr($signedRecord->getKeyPair()->getPublicKey()->serialize(), 1));
        $signature = new ProtocolNode('signature', null, null, $signedRecord->getSignature());

        $secretKey = new ProtocolNode('skey', null, [$sid, $value, $signature], null);

        $iqId = $this->nodeId['sendcipherKeys'] = $this->createIqId();
        $iqNode = new ProtocolNode('iq',
        [
          'id'    => $iqId,
          'to'    => Constants::WHATSAPP_SERVER,
          'type'  => 'set',
          'xmlns' => 'encrypt',
        ], [$identity, $registration, $type, $list, $secretKey], null);
        $this->sendNode($iqNode);
        $this->waitForServer($iqId);
    }

    /**
     * Send a request to get cipher keys from an user.
     *
     * @param $number
     *    Phone number of the user you want to get the cipher keys.
     */
    public function sendGetCipherKeysFromUser($numbers, $replaceKey = false)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $this->replaceKey = $replaceKey;
        $msgId = $this->nodeId['cipherKeys'] = $this->createIqId();

        $userNode = [];
        foreach ($numbers as $number) {
            $userNode[] = new ProtocolNode('user',
              [
                  'jid' => $this->getJID($number),
              ], null, null);
        }
        $keyNode = new ProtocolNode('key', null, $userNode, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'encrypt',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$keyNode], null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    public function resetEncryption()
    {
        if ($this->axolotlStore) {
            $this->axolotlStore->clear();
        }
        $this->retryCounters = [];
        $this->sendSetPreKeys();
        $this->pollMessage();
        $this->pollMessage();
        $this->disconnect();
        $this->connect();
        $this->loginWithPassword($this->password);
        foreach ($this->retryNodes as $node) {
            $this->processInboundDataNode($node);
        }
    }

    public function sendRetry($node, $to, $id, $t, $participant = null)
    {
        if (!isset($this->retryCounters[$id])) {
            $this->retryCounters[$id] = 1;
        } else {
            if (!isset($this->retryNodes[$id])) {
                $this->retryNodes[$id] = $node;
            } elseif ($this->retryCounters[$id] > 2) {
                $this->resetEncryption();
            }
        }
        $retryNode = new ProtocolNode('retry',
        [
          'v'     => '1',
          'count' => '1', //$this->retryCounters[$id]
          'id'    => $id,
          't'     => $t,
        ], null, null);
        $registrationNode = new ProtocolNode('registration', null, null, adjustId($this->axolotlStore->getLocalRegistrationId()));
        if ($participant != null) { //isgroups
        //group retry
        $node = new ProtocolNode('receipt',
            [
                'id'          => $id,
                'to'          => $to,
                'participant' => $participant,
                'type'        => 'retry',
                't'           => $t,
            ], [$retryNode, $registrationNode], null);
        } else {
            $node = new ProtocolNode('receipt',
            [
                'id'   => $id,
                'to'   => $to,
                'type' => 'retry',
                't'    => $t,
            ], [$retryNode, $registrationNode], null);
	    if(!isset($this->retryCounters[$id])) $this->retryCounters[$id] = 0;
            $this->retryCounters[$id]++;
        }
        $this->sendNode($node);
        $this->waitForServer($id);
    }

    /**
     * Send a Broadcast Message with audio.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets       An array of numbers to send to.
     * @param string $path          URL or local path to the audio file to send
     * @param bool   $storeURLmedia Keep a copy of the audio file on your server
     * @param int    $fsize
     * @param string $fhash
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendBroadcastAudio($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        // Return message ID. Make pull request for this.
        return  $this->sendMessageAudio($targets, $path, $storeURLmedia, $fsize, $fhash);
    }

    /**
     * Send a Broadcast Message with an image.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets       An array of numbers to send to.
     * @param string $path          URL or local path to the image file to send
     * @param bool   $storeURLmedia Keep a copy of the audio file on your server
     * @param int    $fsize
     * @param string $fhash
     * @param string $caption
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendBroadcastImage($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        // Return message ID. Make pull request for this.
        return  $this->sendMessageImage($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Send a Broadcast Message with location data.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * If no name is supplied , receiver will see large sized google map
     * thumbnail of entered Lat/Long but NO name/url for location.
     *
     * With name supplied, a combined map thumbnail/name box is displayed
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets An array of numbers to send to.
     * @param float  $long    The longitude of the location eg 54.31652
     * @param float  $lat     The latitude if the location eg -6.833496
     * @param string $name    (Optional) A name to describe the location
     * @param string $url     (Optional) A URL to link location to web resource
     *
     * @return string Message ID
     */
    public function sendBroadcastLocation($targets, $long, $lat, $name = null, $url = null)
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageLocation($targets, $long, $lat, $name, $url);
    }

    /**
     * Send a Broadcast Message.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets An array of numbers to send to.
     * @param string $message Your message
     *
     * @return string Message ID
     */
    public function sendBroadcastMessage($targets, $message)
    {
        $bodyNode = new ProtocolNode('body', null, null, $message);
        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $bodyNode, 'text');
    }

    /**
     * Send a Broadcast Message with a video.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets       An array of numbers to send to.
     * @param string $path          URL or local path to the video file to send
     * @param bool   $storeURLmedia Keep a copy of the audio file on your server
     * @param int    $fsize
     * @param string $fhash
     * @param string $caption
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendBroadcastVideo($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageVideo($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Delete Broadcast lists.
     *
     * @param string array $lists
     *                            Contains the broadcast-id list
     */
    public function sendDeleteBroadcastLists($lists)
    {
        $msgId = $this->createIqId();
        $listNode = [];
        if ($lists != null && count($lists) > 0) {
            for ($i = 0; $i < count($lists); $i++) {
                $listNode[$i] = new ProtocolNode('list', ['id' => $lists[$i]], null, null);
            }
        } else {
            $listNode = null;
        }
        $deleteNode = new ProtocolNode('delete', null, $listNode, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'w:b',
                'type'  => 'set',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$deleteNode], null);

        $this->sendNode($node);
    }

    /**
     * Clears the "dirty" status on your account.
     *
     * @param array $categories
     */
    public function sendClearDirty($categories)
    {
        $msgId = $this->createIqId();

        $catnodes = [];
        foreach ($categories as $category) {
            $catnode = new ProtocolNode('clean', ['type' => $category], null, null);
            $catnodes[] = $catnode;
        }
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'type'  => 'set',
                'to'    => Constants::WHATSAPP_SERVER,
                'xmlns' => 'urn:xmpp:whatsapp:dirty',
            ], $catnodes, null);

        $this->sendNode($node);
    }

    public function sendClientConfig()
    {
        $attr = [];
        $attr['platform'] = Constants::PLATFORM;
        $attr['version'] = Constants::WHATSAPP_VER;
        $child = new ProtocolNode('config', $attr, null, '');
        $node = new ProtocolNode('iq',
            [
                'id'    => $this->createIqId(),
                'type'  => 'set',
                'xmlns' => 'urn:xmpp:whatsapp:push',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    public function sendSetGCM($gcm = null)
    {
        if (is_null($gcm)) {
          $gcm = getRandomGCM();
        }
        $attr = [];
        $attr['platform'] = 'gcm';
        $attr['id'] = $gcm;
        $child = new ProtocolNode('config', $attr, null, '');
        $node = new ProtocolNode('iq',
            [
                'id'    => $this->createIqId(),
                'type'  => 'set',
                'xmlns' => 'urn:xmpp:whatsapp:push',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    public function sendGetClientConfig()
    {
        $msgId = $this->createIqId();
        $child = new ProtocolNode('config', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'urn:xmpp:whatsapp:push',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    /**
     * Transfer your number to new one.
     *
     * @param string $number
     * @param string $identity
     */
    public function sendChangeNumber($number, $identity)
    {
        $msgId = $this->createIqId();

        $usernameNode = new ProtocolNode('username', null, null, $number);
        $passwordNode = new ProtocolNode('password', null, null, urldecode($identity));

        $modifyNode = new ProtocolNode('modify', null, [$usernameNode, $passwordNode], null);

        $iqNode = new ProtocolNode('iq',
            [
                'xmlns' => 'urn:xmpp:whatsapp:account',
                'id'    => $msgId,
                'type'  => 'get',
                'to'    => 'c.us',
            ], [$modifyNode], null);

        $this->sendNode($iqNode);
    }

    /**
     * Send a request to return a list of groups user is currently participating in.
     *
     * To capture this list you will need to bind the "onGetGroups" event.
     */
    public function sendGetGroups()
    {
        $this->sendGetGroupsFiltered('participating');
    }

    /**
     * Send a request to get new Groups V2 info.
     *
     * @param $groupID
     *    The group JID
     */
    public function sendGetGroupV2Info($groupID)
    {
        $msgId = $this->nodeId['get_groupv2_info'] = $this->createIqId();

        $queryNode = new ProtocolNode('query',
            [
                'request' => 'interactive',
            ], null, null);

        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'w:g2',
                'type'  => 'get',
                'to'    => $this->getJID($groupID),
            ], [$queryNode], null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get a list of people you have currently blocked.
     */
    public function sendGetPrivacyBlockedList()
    {
        $msgId = $this->nodeId['privacy'] = $this->createIqId();
        $child = new ProtocolNode('list',
            [
                'name' => 'default',
            ], null, null);

        $child2 = new ProtocolNode('query', [], [$child], null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'jabber:iq:privacy',
                'type'  => 'get',
            ], [$child2], null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get privacy settings.
     */
    public function sendGetPrivacySettings()
    {
        $msgId = $this->nodeId['privacy_settings'] = $this->createIqId();
        $privacyNode = new ProtocolNode('privacy', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'to'    => Constants::WHATSAPP_SERVER,
                'id'    => $msgId,
                'xmlns' => 'privacy',
                'type'  => 'get',
            ], [$privacyNode], null);

        $this->sendNode($node);
    }

    /**
     * Set privacy of 'last seen', status or profile picture to all, contacts or none.
     *
     * @param string $category
     *                         Options: 'last', 'status' or 'profile'
     * @param string $value
     *                         Options: 'all', 'contacts' or 'none'
     */
    public function sendSetPrivacySettings($category, $value)
    {
        $msgId = $this->createIqId();
        $categoryNode = new ProtocolNode('category',
            [
                'name'  => $category,
                'value' => $value,
            ], null, null);

        $privacyNode = new ProtocolNode('privacy', null, [$categoryNode], null);
        $node = new ProtocolNode('iq',
            [
                'to'    => Constants::WHATSAPP_SERVER,
                'type'  => 'set',
                'id'    => $msgId,
                'xmlns' => 'privacy',
            ], [$privacyNode], null);

        $this->sendNode($node);
    }

    /**
     * Get profile picture of specified user.
     *
     * @param string $number
     *                       Number or JID of user
     * @param bool   $large
     *                       Request large picture
     */
    public function sendGetProfilePicture($number, $large = false)
    {
        $msgId = $this->nodeId['getprofilepic'] = $this->createIqId();

        $hash = [];
        $hash['type'] = 'image';
        if (!$large) {
            $hash['type'] = 'preview';
        }
        $picture = new ProtocolNode('picture', $hash, null, null);

        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'type'  => 'get',
                'xmlns' => 'w:profile:picture',
                'to'    => $this->getJID($number),
            ], [$picture], null);

        $this->sendNode($node);
    }

    /**
     * @param mixed $numbers Numbers to get profile profile photos of.
     *
     * @return bool
     */
    public function sendGetProfilePhotoIds($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $msgId = $this->createIqId();

        $userNode = [];
        for ($i = 0; $i < count($numbers); $i++) {
            $userNode[$i] = new ProtocolNode('user',
                [
                    'jid' => $this->getJID($numbers[$i]),
                ], null, null);
        }

        if (!count($userNode)) {
            return false;
        }

        $listNode = new ProtocolNode('list', null, $userNode, null);

        $iqNode = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'w:profile:picture',
                'type'  => 'get',
            ], [$listNode], null);

        $this->sendNode($iqNode);

        return true;
    }

    /**
     * Send a request to get the current server properties.
     */
    public function sendGetServerProperties()
    {
        $id = $this->createIqId();
        $child = new ProtocolNode('props', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $id,
                'type'  => 'get',
                'xmlns' => 'w',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get the current service pricing.
     *
     *  @param string $lg
     *   Language
     *  @param string $lc
     *   Country
     */
    public function sendGetServicePricing($lg, $lc)
    {
        $msgId = $this->createIqId();
        $pricingNode = new ProtocolNode('pricing',
            [
                'lg' => $lg,
                'lc' => $lc,
            ], null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'urn:xmpp:whatsapp:account',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$pricingNode], null);

        $this->sendNode($node);
    }

    /**
     * Send a request to extend the account.
     */
    public function sendExtendAccount()
    {
        $msgId = $this->createIqId();
        $extendingNode = new ProtocolNode('extend', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'urn:xmpp:whatsapp:account',
                'type'  => 'set',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$extendingNode], null);

        $this->sendNode($node);
    }

    /**
     * Gets all the broadcast lists for an account.
     */
    public function sendGetBroadcastLists()
    {
        $msgId = $this->nodeId['get_lists'] = $this->createIqId();
        $listsNode = new ProtocolNode('lists', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'w:b',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$listsNode], null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get the normalized mobile number representing the JID.
     *
     *  @param string $countryCode Country Code
     *  @param string $number      Mobile Number
     */
    public function sendGetNormalizedJid($countryCode, $number)
    {
        $msgId = $this->createIqId();
        $ccNode = new ProtocolNode('cc', null, null, $countryCode);
        $inNode = new ProtocolNode('in', null, null, $number);
        $normalizeNode = new ProtocolNode('normalize', null, [$ccNode, $inNode], null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'urn:xmpp:whatsapp:account',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$normalizeNode], null);

        $this->sendNode($node);
    }

    /**
     * Removes an account from WhatsApp.
     *
     * @param string $lg       Language
     * @param string $lc       Country
     * @param string $feedback User Feedback
     */
    public function sendRemoveAccount($lg = null, $lc = null, $feedback = null)
    {
        $msgId = $this->createIqId();
        if ($feedback != null && strlen($feedback) > 0) {
            if ($lg == null) {
                $lg = '';
            }

            if ($lc == null) {
                $lc = '';
            }

            $child = new ProtocolNode('body',
                [
                    'lg' => $lg,
                    'lc' => $lc,
                ], null, $feedback);
            $childNode = [$child];
        } else {
            $childNode = null;
        }

        $removeNode = new ProtocolNode('remove', null, $childNode, null);
        $node = new ProtocolNode('iq',
            [
                'to'    => Constants::WHATSAPP_SERVER,
                'xmlns' => 'urn:xmpp:whatsapp:account',
                'type'  => 'get',
                'id'    => $msgId,
            ], [$removeNode], null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a ping to the server.
     */
    public function sendPing()
    {
        $msgId = $this->createIqId();
        $pingNode = new ProtocolNode('ping', null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'xmlns' => 'w:p',
                'type'  => 'get',
                'to'    => Constants::WHATSAPP_SERVER,
            ], [$pingNode], null);

        $this->sendNode($node);
    }

    /**
     * Get the current status message of a specific user.
     *
     * @param mixed $jids The users' JIDs
     */
    public function sendGetStatuses($jids)
    {
        if (!is_array($jids)) {
            $jids = [$jids];
        }

        $children = [];
        foreach ($jids as $jid) {
            $children[] = new ProtocolNode('user', ['jid' => $this->getJID($jid)], null, null);
        }

        $iqId = $this->nodeId['getstatuses'] = $this->createIqId();

        $node = new ProtocolNode('iq',
            [
                'to'    => Constants::WHATSAPP_SERVER,
                'type'  => 'get',
                'xmlns' => 'status',
                'id'    => $iqId,
            ], [
                new ProtocolNode('status', null, $children, null),
            ], null);

        $this->sendNode($node);
    }

    /**
     * Create a group chat.
     *
     * @param string $subject
     *                             The group Subject
     * @param array  $participants
     *                             An array with the participants numbers.
     *
     * @return string
     *                The group ID.
     */
    public function sendGroupsChatCreate($subject, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }

        $participantNode = [];
        foreach ($participants as $participant) {
            $participantNode[] = new ProtocolNode('participant', [
                'jid' => $this->getJID($participant),
            ], null, null);
        }

        $id = $this->nodeId['groupcreate'] = $this->createIqId();

        $createNode = new ProtocolNode('create',
            [
                'subject' => $subject,
            ], $participantNode, null);

        $iqNode = new ProtocolNode('iq',
            [
                'xmlns' => 'w:g2',
                'id'    => $id,
                'type'  => 'set',
                'to'    => Constants::WHATSAPP_GROUP_SERVER,
            ], [$createNode], null);

        $this->sendNode($iqNode);
        $this->waitForServer($id);
        $groupId = $this->groupId;

        $this->eventManager()->fire('onGroupCreate',
            [
                $this->phoneNumber,
                $groupId,
            ]);

        return $groupId;
    }

    /**
     * Change group's subject.
     *
     * @param string $gjid    The group id
     * @param string $subject The subject
     */
    public function sendSetGroupSubject($gjid, $subject)
    {
        $child = new ProtocolNode('subject', null, null, $subject);
        $node = new ProtocolNode('iq',
            [
                'id'    => $this->createIqId(),
                'type'  => 'set',
                'to'    => $this->getJID($gjid),
                'xmlns' => 'w:g2',
            ], [$child], null);

        $this->sendNode($node);
    }

    /**
     * Leave a group chat.
     *
     * @param mixed $gjids Group or group's ID(s)
     */
    public function sendGroupsLeave($gjids)
    {
        $msgId = $this->nodeId['leavegroup'] = $this->createIqId();

        if (!is_array($gjids)) {
            $gjids = [$this->getJID($gjids)];
        }

        $nodes = [];
        foreach ($gjids as $gjid) {
            $nodes[] = new ProtocolNode('group',
                [
                    'id' => $this->getJID($gjid),
                ], null, null);
        }

        $leave = new ProtocolNode('leave',
            [
                'action' => 'delete',
            ], $nodes, null);

        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'to'    => Constants::WHATSAPP_GROUP_SERVER,
                'type'  => 'set',
                'xmlns' => 'w:g2',
            ], [$leave], null);

        $this->sendNode($node);
    }

    /**
     * Add participant(s) to a group.
     *
     * @param string $groupId      The group ID.
     * @param string $participants An array with the participants numbers to add
     */
    public function sendGroupsParticipantsAdd($groupId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($groupId, $participant, 'add', $msgId);
    }

    /**
     * Remove participant from a group.
     *
     * @param string $groupId     The group ID.
     * @param string $participant The number of the participant you want to remove
     */
    public function sendGroupsParticipantsRemove($groupId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($groupId, $participant, 'remove', $msgId);
    }

    /**
     * Promote participant of a group; Make a participant an admin of a group.
     *
     * @param string $gId         The group ID.
     * @param string $participant The number of the participant you want to promote
     */
    public function sendPromoteParticipants($gId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($gId, $participant, 'promote', $msgId);
    }

    /**
     * Demote participant of a group; remove participant of being admin of a group.
     *
     * @param string $gId         The group ID.
     * @param string $participant The number of the participant you want to demote
     */
    public function sendDemoteParticipants($gId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($gId, $participant, 'demote', $msgId);
    }

    /**
     * Send a text message to the user/group.
     *
     * @param string $to  The recipient.
     * @param string $txt The text message.
     * @param bool   $enc
     *
     * @return string Message ID.
     */
    public function sendMessage($to, $plaintext, $force_plain = false)
    {
        if (extension_loaded('curve25519') && extension_loaded('protobuf') && !$force_plain) {
            $to_num = ExtractNumber($to);
            if (!(strpos($to, '-') !== false)) {
                if (!$this->axolotlStore->containsSession($to_num, 1)) {
                    $this->sendGetCipherKeysFromUser($to_num);
                }

                $sessionCipher = $this->getSessionCipher($to_num);

                if (in_array($to_num, $this->v2Jids) && !isset($this->v1Only[$to_num])) {
                    $version = '2';
                    $plaintext = padMessage($plaintext);
                } else {
                    $version = '1';
                }
                $cipherText = $sessionCipher->encrypt($plaintext);

                if ($cipherText instanceof WhisperMessage) {
                    $type = 'msg';
                } else {
                    $type = 'pkmsg';
                }
                $message = $cipherText->serialize();
                $msgNode = new ProtocolNode('enc',
              [
                'v'     => $version,
                'type'  => $type,
              ], null, $message);
            } else {
                /* if (in_array($to, $this->v2Jids))
          {
            $version = "2";
            $plaintext = padMessage($plaintext);
          }
          else
            $version = "1";

          if(!$this->axolotlStore->containsSenderKey($to)){
            $gsb = new GroupSessionBuilder($this->axolotlStore);
            $senderKey = $gsb->process ($groupId, $keyId, $iteration, $chainKey, $signatureKey)
          }
          $thi*/
          $msgNode = new ProtocolNode('body', null, null, $plaintext);
            }
        } else {
            $msgNode = new ProtocolNode('body', null, null, $plaintext);
        }
        $plaintextNode = new ProtocolNode('body', null, null, $plaintext);
        $id = $this->sendMessageNode($to, $msgNode, null, $plaintextNode);

        if ($this->messageStore !== null) {
            $this->messageStore->saveMessage($this->phoneNumber, $to, $plaintext, $id, time());
        }

        return $id;
    }

    /**
     * Send audio to the user/group.
     *
     * @param string $to            The recipient.
     * @param string $filepath      The url/uri to the audio file.
     * @param bool   $storeURLmedia Keep copy of file
     * @param int    $fsize
     * @param string $fhash         *
     * @param bool   $voice
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendMessageAudio($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '', $voice = false)
    {
        $this->voice = $voice;

        if ($fsize != 0 && $fhash != '') {
            return $this->sendRequestFileUpload($fhash, 'audio', $fsize, $filepath, $to);
        }

        $allowedExtensions = ['3gp', 'caf', 'wav', 'mp3', 'wma', 'ogg', 'aif', 'aac', 'm4a'];
        $size = 10 * 1024 * 1024; // Easy way to set maximum file size for this media type.
        // Return message ID. Make pull request for this.
        return $this->sendCheckAndSendMedia($filepath, $size, $to, 'audio', $allowedExtensions, $storeURLmedia);
    }

    /**
     * Send the composing message status. When typing a message.
     *
     * @param string $to The recipient to send status to.
     */
    public function sendMessageComposing($to)
    {
        $this->sendChatState($to, 'composing');
    }

    /**
     * Send an image file to group/user.
     *
     * @param string $to            Recipient number
     * @param string $filepath      The url/uri to the image file.
     * @param bool   $storeURLmedia Keep copy of file
     * @param int    $fsize         size of the media file
     * @param string $fhash         base64 hash of the media file
     * @param string $caption
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendMessageImage($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if ($fsize != 0 && $fhash != '') {
          return $this->sendRequestFileUpload($fhash, 'image', $fsize, $filepath, $to, $caption);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'gif', 'png'];
        $size = 5 * 1024 * 1024; // Easy way to set maximum file size for this media type.
        // Return message ID. Make pull request for this.
        return $this->sendCheckAndSendMedia($filepath, $size, $to, 'image', $allowedExtensions, $storeURLmedia, $caption);
    }

    /**
     * Send a location to the user/group.
     *
     * If no name is supplied, the receiver will see a large google maps thumbnail of the lat/long,
     * but NO name or url of the location.
     *
     * When a name supplied, a combined map thumbnail/name box is displayed.
     *
     * @param mixed  $to   The recipient(s) to send the location to.
     * @param float  $long The longitude of the location, e.g. 54.31652.
     * @param float  $lat  The latitude of the location, e.g. -6.833496.
     * @param string $name (Optional) A custom name for the specified location.
     * @param string $url  (Optional) A URL to attach to the specified location.
     *
     * @return string Message ID
     */
    public function sendMessageLocation($to, $long, $lat, $name = null, $url = null)
    {
        $mediaNode = new ProtocolNode('media',
            [
                'type'      => 'location',
                'encoding'  => 'raw',
                'latitude'  => $lat,
                'longitude' => $long,
                'name'      => $name,
                'url'       => $url,
            ], null, null);

        $id = (is_array($to)) ? $this->sendBroadcast($to, $mediaNode, 'media') : $this->sendMessageNode($to, $mediaNode);

        //$this->waitForServer($id);

        // Return message ID. Make pull request for this.
        return $id;
    }

    /**
     * Send the 'paused composing message' status.
     *
     * @param string $to The recipient number or ID.
     */
    public function sendMessagePaused($to)
    {
        $this->sendChatState($to, 'paused');
    }

    protected function sendChatState($to, $state)
    {
        $node = new ProtocolNode('chatstate',
            [
                'to' => $this->getJID($to),
            ], [new ProtocolNode($state, null, null, null)], null);

        $this->sendNode($node);
    }

    /**
     * Send a video to the user/group.
     *
     * @param string $to            The recipient to send.
     * @param string $filepath      A URL/URI to the MP4/MOV video.
     * @param bool   $storeURLmedia Keep a copy of media file.
     * @param int    $fsize         Size of the media file
     * @param string $fhash         base64 hash of the media file
     * @param string $caption       *
     *
     * @return string|null Message ID if successfully, null if not.
     */
    public function sendMessageVideo($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if ($fsize != 0 && $fhash != '') {
            return $this->sendRequestFileUpload($fhash, 'video', $fsize, $filepath, $to, $caption);
        }

        $allowedExtensions = ['3gp', 'mp4', 'mov', 'avi'];
        $size = 20 * 1024 * 1024; // Easy way to set maximum file size for this media type.
        // Return message ID. Make pull request for this.
        return $this->sendCheckAndSendMedia($filepath, $size, $to, 'video', $allowedExtensions, $storeURLmedia, $caption);
    }

    /**
     * Send the next message.
     */
    public function sendNextMessage()
    {
        if (count($this->outQueue) > 0) {
            $msgnode = array_shift($this->outQueue);
            $msgnode->refreshTimes();
            $this->lastId = $msgnode->getAttribute('id');
            $this->sendNode($msgnode);
        } else {
            $this->lastId = false;
        }
    }

    /**
     * Send the offline status. User will show up as "Offline".
     */
    public function sendOfflineStatus()
    {
        $messageNode = new ProtocolNode('presence', ['type' => 'inactive'], null, '');
        $this->sendNode($messageNode);
    }

    /**
     * Send a pong to the WhatsApp server. I'm alive!
     *
     * @param string $msgid The id of the message.
     */
    public function sendPong($msgid)
    {
        $messageNode = new ProtocolNode('iq',
            [
                'to'   => Constants::WHATSAPP_SERVER,
                'id'   => $msgid,
                'type' => 'result',
            ], null, '');

        $this->sendNode($messageNode);
        $this->eventManager()->fire('onSendPong',
            [
                $this->phoneNumber,
                $msgid,
            ]);
    }

    public function sendAvailableForChat($nickname = null)
    {
        $presence = [];
        if ($nickname) {
            //update nickname
            $this->name = $nickname;
        }

        $presence['name'] = $this->name;
        $presence['type'] = 'available';
        $node = new ProtocolNode('presence', $presence, null, '');
        $this->sendNode($node);
    }

    /**
     * Send presence status.
     *
     * @param string $type The presence status.
     */
    public function sendPresence($type = 'active')
    {
        $node = new ProtocolNode('presence',
            [
                'type' => $type,
            ], null, '');

        $this->sendNode($node);
        $this->eventManager()->fire('onSendPresence',
            [
                $this->phoneNumber,
                $type,
                $this->name,
            ]);
    }

    /**
     * Send presence subscription, automatically receive presence updates as long as the socket is open.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceSubscription($to)
    {
        $node = new ProtocolNode('presence', ['type' => 'subscribe', 'to' => $this->getJID($to)], null, '');
        $this->sendNode($node);
    }

    /**
     * Unsubscribe, will stop subscription.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceUnsubscription($to)
    {
        $node = new ProtocolNode('presence', ['type' => 'unsubscribe', 'to' => $this->getJID($to)], null, '');
        $this->sendNode($node);
    }

    /**
     * Set the picture for the group.
     *
     * @param string $gjid The groupID
     * @param string $path The URL/URI of the image to use
     */
    public function sendSetGroupPicture($gjid, $path)
    {
        $this->sendSetPicture($gjid, $path);
    }

    /**
     * Set the list of numbers you wish to block receiving from.
     *
     * @param mixed $blockedJids One or more numbers to block messages from.
     */
    public function sendSetPrivacyBlockedList($blockedJids = [])
    {
        if (!is_array($blockedJids)) {
            $blockedJids = [$blockedJids];
        }

        $items = [];
        foreach ($blockedJids as $index => $jid) {
            $item = new ProtocolNode('item',
                [
                    'type'   => 'jid',
                    'value'  => $this->getJID($jid),
                    'action' => 'deny',
                    'order'  => $index + 1,//WhatsApp stream crashes on zero index
                ], null, null);
            $items[] = $item;
        }

        $child = new ProtocolNode('list',
            [
                'name' => 'default',
            ], $items, null);

        $child2 = new ProtocolNode('query', null, [$child], null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $this->createIqId(),
                'xmlns' => 'jabber:iq:privacy',
                'type'  => 'set',
            ], [$child2], null);

        $this->sendNode($node);
    }

    /**
     * Set your profile picture.
     *
     * @param string $path URL/URI of image
     */
    public function sendSetProfilePicture($path)
    {
        $this->sendSetPicture($this->phoneNumber, $path);
    }

    /*
     *	Removes the profile photo.
     */

    public function sendRemoveProfilePicture()
    {
        $msgId = $this->createIqId();

        $picture = new ProtocolNode('picture', null, null, null);

        $thumb = new ProtocolNode('picture',
            [
                'type' => 'preview',
            ], null, null);

        $node = new ProtocolNode('iq',
            [
                'id'    => $msgId,
                'to'    => $this->getJID($this->phoneNumber),
                'type'  => 'set',
                'xmlns' => 'w:profile:picture',
            ], [$picture, $thumb], null);

        $this->sendNode($node);
    }

    /**
     * Set the recovery token for your account to allow you to retrieve your password at a later stage.
     *
     * @param string $token A user generated token.
     */
    public function sendSetRecoveryToken($token)
    {
        $child = new ProtocolNode('pin',
            [
                'xmlns' => 'w:ch:p',
            ], null, $token);

        $node = new ProtocolNode('iq',
            [
                'id'   => $this->createIqId(),
                'type' => 'set',
                'to'   => Constants::WHATSAPP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    /**
     * Update the user status.
     *
     * @param string $txt The text of the message status to send.
     */
    public function sendStatusUpdate($txt)
    {
        $child = new ProtocolNode('status', null, null, $txt);
        $nodeID = $this->createIqId();
        $node = new ProtocolNode('iq',
            [
                'to'    => Constants::WHATSAPP_SERVER,
                'type'  => 'set',
                'id'    => $nodeID,
                'xmlns' => 'status',
            ], [$child], null);

        $this->sendNode($node);
        $this->eventManager()->fire('onSendStatusUpdate',
            [
                $this->phoneNumber,
                $txt,
            ]);
    }

    /**
     * Send a vCard to the user/group.
     *
     * @param string $to    The recipient to send.
     * @param string $name  The contact name.
     * @param object $vCard The contact vCard to send.
     *
     * @return string Message ID
     */
    public function sendVcard($to, $name, $vCard)
    {
        $vCardNode = new ProtocolNode('vcard',
            [
                'name' => $name,
            ], null, $vCard);

        $mediaNode = new ProtocolNode('media',
            [
                'type' => 'vcard',
            ], [$vCardNode], '');

        // Return message ID. Make pull request for this.
        return $this->sendMessageNode($to, $mediaNode);
    }

    /**
     * Send a vCard to the user/group as Broadcast.
     *
     * @param array  $targets An array of recipients to send to.
     * @param string $name    The vCard contact name.
     * @param object $vCard   The contact vCard to send.
     *
     * @return string Message ID
     */
    public function sendBroadcastVcard($targets, $name, $vCard)
    {
        $vCardNode = new ProtocolNode('vcard',
            [
                'name' => $name,
            ], null, $vCard);

        $mediaNode = new ProtocolNode('media',
            [
                'type' => 'vcard',
            ], [$vCardNode], '');

        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $mediaNode, 'media');
    }

    /**
     * Rejects a call.
     *
     * @param array  $to     Phone number.
     * @param string $id     The main node id
     * @param string $callId The call-id
     */
    public function rejectCall($to, $id, $callId)
    {
        $rejectNode = new ProtocolNode('reject',
            [
              'call-id' => $callId,
            ], null, null);

        $callNode = new ProtocolNode('call',
            [
              'id' => $id,
              'to' => $this->getJID($to),
            ], [$rejectNode], null);

        $this->sendNode($callNode);
    }

    /**
     * Sets the bind of the new message.
     *
     * @param $bind
     */
    public function setNewMessageBind($bind)
    {
        $this->newMsgBind = $bind;
    }

    /**
     * Wait for WhatsApp server to acknowledge *it* has received message.
     *
     * @param string $id      The id of the node sent that we are awaiting acknowledgement of.
     * @param int    $timeout
     */
    public function waitForServer($id, $timeout = 5)
    {
        $time = time();
        $this->serverReceivedId = false;
        do {
            $this->pollMessage();
        } while ($this->serverReceivedId !== $id && time() - $time < $timeout);
    }

    /**
     * Create a unique msg id.
     *
     * @return string
     *                A message id string.
     */
    protected function createMsgId()
    {
        $msg = hex2bin($this->messageId);
        $chars = str_split($msg);
        $chars_val = array_map('ord', $chars);
        $pos = count($chars_val) - 1;
        while (true) {
            if ($chars_val[$pos] < 255) {
                $chars_val[$pos]++;
                break;
            } else {
                $chars_val[$pos] = 0;
                $pos--;
            }
        }
        $chars = array_map('chr', $chars_val);
        $msg = bin2hex(implode($chars));
        $this->messageId = $msg;

        return $this->messageId;
    }

    /**
     * iq id.
     *
     * @return string
     *                Iq id
     */
    public function createIqId()
    {
        $iqId = $this->iqCounter;
        $this->iqCounter++;
        $id = dechex($iqId);
        if (strlen($id) % 2 == 1) {
            $id = str_pad($id, strlen($id) + 1, '0', STR_PAD_LEFT);
        }

        return $id;
    }

    /**
     * Print a message to the debug console.
     *
     * @param mixed $debugMsg The debug message.
     *
     * @return bool
     */
    public function debugPrint($debugMsg)
    {
        if ($this->debug) {
            if (is_array($debugMsg) || is_object($debugMsg)) {
                print_r($debugMsg);
            } else {
                echo $debugMsg;
            }

            return true;
        }

        return false;
    }

    public function logFile($tag, $message, $context = [])
    {
        if ($this->log) {
            $this->logger->log($tag, $message, $context);
        }
    }

    /**
     * Have we an active connection with WhatsAPP AND a valid login already?
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        //If you aren't connected you can't be logged in! ($this->isConnected())
        //We are connected - but are we logged in? (the rest)
        return $this->isConnected() && !empty($this->loginStatus) && $this->loginStatus === Constants::CONNECTED_STATUS;
    }

    public function sendSync($numbers, $deletedNumbers = null, $syncType = 3)
    {
        $users = [];
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        for ($i = 0; $i < count($numbers); $i++) { // number must start with '+' if international contact
            $users[$i] = new ProtocolNode('user', null, null, (substr($numbers[$i], 0, 1) != '+') ? ('+'.$numbers[$i]) : ($numbers[$i]));
        }

        if (!is_null($deletedNumbers)) {
            if (!is_array($deletedNumbers)) {
                $deletedNumbers = [$deletedNumbers];
            }
            for ($j = 0; $j < count($deletedNumbers); $j++, $i++) {
                $users[$i] = new ProtocolNode('user', ['jid' => $this->getJID($deletedNumbers[$j]), 'type' => 'delete'], null, null);
            }
        }

        switch ($syncType) {
            case 0:
                $mode = 'full';
                $context = 'registration';
                break;
            case 1:
                $mode = 'full';
                $context = 'interactive';
                break;
            case 2:
                $mode = 'full';
                $context = 'background';
                break;
            case 3:
                $mode = 'delta';
                $context = 'interactive';
                break;
            case 4:
                $mode = 'delta';
                $context = 'background';
                break;
            case 5:
                $mode = 'query';
                $context = 'interactive';
                break;
            case 6:
                $mode = 'chunked';
                $context = 'registration';
                break;
            case 7:
                $mode = 'chunked';
                $context = 'interactive';
                break;
            case 8:
                $mode = 'chunked';
                $context = 'background';
                break;
            default:
                $mode = 'delta';
                $context = 'background';
        }

        $id = $this->createIqId();

        $node = new ProtocolNode('iq',
            [
                'id'    => $id,
                'xmlns' => 'urn:xmpp:whatsapp:sync',
                'type'  => 'get',
            ], [
                new ProtocolNode('sync',
                    [
                        'mode'    => $mode,
                        'context' => $context,
                        'sid'     => 'sync_sid_full_'.sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
                        'index' => '0',
                        'last'  => 'true',
                    ], $users, null),
            ], null);

        $this->sendNode($node);
        $this->waitForServer($id);

        return $id;
    }

    public function setMessageStore(MessageStoreInterface $messageStore)
    {
        $this->messageStore = $messageStore;
    }

    public function setAxolotlStore(axolotlInterface $axolotlStore)
    {
        $this->axolotlStore = $axolotlStore;
    }

    /**
     * Process number/jid and turn it into a JID if necessary.
     *
     * @param string $number
     *                       Number to process
     *
     * @return string
     */
    public function getJID($number)
    {
        if (!stristr($number, '@')) {
            //check if group message
            if (stristr($number, '-')) {
                //to group
                $number .= '@'.Constants::WHATSAPP_GROUP_SERVER;
            } else {
                //to normal user
                $number .= '@'.Constants::WHATSAPP_SERVER;
            }
        }

        return $number;
    }

    /**
     * Retrieves media file and info from either a URL or localpath.
     *
     * @param string $filepath     The URL or path to the mediafile you wish to send
     * @param int    $maxsizebytes The maximum size in bytes the media file can be. Default 5MB
     *
     * @return bool false if file information can not be obtained.
     */
    protected function getMediaFile($filepath, $maxsizebytes = 5242880)
    {
        if (filter_var($filepath, FILTER_VALIDATE_URL) !== false) {
            $this->mediaFileInfo = [];
            $this->mediaFileInfo['url'] = $filepath;

            $media = file_get_contents($filepath);
            $this->mediaFileInfo['filesize'] = strlen($media);

            if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                $this->mediaFileInfo['filepath'] = tempnam($this->dataFolder.Constants::MEDIA_FOLDER, 'WHA');
                file_put_contents($this->mediaFileInfo['filepath'], $media);
                $this->mediaFileInfo['filemimetype'] = get_mime($this->mediaFileInfo['filepath']);
                $this->mediaFileInfo['fileextension'] = getExtensionFromMime($this->mediaFileInfo['filemimetype']);

                return true;
            } else {
                return false;
            }
        } elseif (file_exists($filepath)) {
            //Local file
            $this->mediaFileInfo['filesize'] = filesize($filepath);
            if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                $this->mediaFileInfo['filepath'] = $filepath;
                $this->mediaFileInfo['fileextension'] = pathinfo($filepath, PATHINFO_EXTENSION);
                $this->mediaFileInfo['filemimetype'] = get_mime($filepath);

                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Process the challenge.
     *
     * @param ProtocolNode $node The node that contains the challenge.
     */
    protected function processChallenge($node)
    {
        $this->challengeData = $node->getData();
    }

    /**
     * Process inbound data.
     *
     * @param   $data
     *
     * @throws Exception
     */
    protected function processInboundData($data)
    {
        $node = $this->reader->nextTree($data);
        if ($node != null) {
            $this->processInboundDataNode($node);
        }
    }

    public function addPendingNode(ProtocolNode $node)
    {
        $from = $node->getAttribute('from');
        if (strpos($from, Constants::WHATSAPP_SERVER) !== false) {
            $number = ExtractNumber($node->getAttribute('from'));
        } else {
            $number = ExtractNumber($node->getAttribute('participant'));
        }

        if (!isset($this->pending_nodes[$number])) {
            $this->pending_nodes[$number] = [];
        }

        $this->pending_nodes[$number][] = $node;
    }

    /**
     * Will process the data from the server after it's been decrypted and parsed.
     *
     * This also provides a convenient method to use to unit test the event framework.
     *
     * @param ProtocolNode $node
     * @param              $type
     *
     * @throws Exception
     */
    protected function processInboundDataNode(ProtocolNode $node)
    {
        $this->timeout = time();
        //echo niceVarDump($node);
        $this->debugPrint($node->nodeString('rx  ')."\n");
        $this->serverReceivedId = $node->getAttribute('id');

        if ($node->getTag() == 'challenge') {
            $this->processChallenge($node);
        } elseif ($node->getTag() == 'failure') {
            $this->loginStatus = Constants::DISCONNECTED_STATUS;
            $this->eventManager()->fire('onLoginFailed',
                [
                    $this->phoneNumber,
                    $node->getChild(0)->getTag(),
                ]);
            if ($node->getChild(0)->getTag() == 'not-authorized') {
                $this->logFile('error', 'Blocked number or wrong password. Use blockChecker.php');
            }
        } elseif ($node->getTag() == 'success') {
            if ($node->getAttribute('status') == 'active') {
                $this->loginStatus = Constants::CONNECTED_STATUS;
                $challengeData = $node->getData();
                file_put_contents($this->challengeFilename, $challengeData);
                $this->writer->setKey($this->outputKey);

                $this->eventManager()->fire('onLoginSuccess',
                    [
                        $this->phoneNumber,
                        $node->getAttribute('kind'),
                        $node->getAttribute('status'),
                        $node->getAttribute('creation'),
                        $node->getAttribute('expiration'),
                    ]);
            } elseif ($node->getAttribute('status') == 'expired') {
                $this->eventManager()->fire('onAccountExpired',
                    [
                        $this->phoneNumber,
                        $node->getAttribute('kind'),
                        $node->getAttribute('status'),
                        $node->getAttribute('creation'),
                        $node->getAttribute('expiration'),
                    ]);
            }
        } elseif ($node->getTag() == 'ack') {
            if ($node->getAttribute('class') == 'message') {
                $this->eventManager()->fire('onMessageReceivedServer',
                [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('class'),
                    $node->getAttribute('t'),
                ]);
            }
        } elseif ($node->getTag() == 'receipt') {
            if ($node->hasChild('list')) {
                foreach ($node->getChild('list')->getChildren() as $child) {
                    $this->eventManager()->fire('onMessageReceivedClient',
                        [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $child->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('participant'),
                        ]);
                }
            }
            if ($node->hasChild('retry')) {
                $this->sendGetCipherKeysFromUser(ExtractNumber($node->getAttribute('from')), true);
                $this->messageStore->setPending($node->getAttribute('id'), $node->getAttribute('from'));
            }
            if ($node->hasChild('error') && $node->getChild('error')->getAttribute('type') == 'enc-v1') {
                $this->v1Only[ExtractNumber($node->getAttribute('from'))] = true;
                $this->messageStore->setPending($node->getAttribute('id'), $node->getAttribute('from'));
                $this->sendPendingMessages($node->getAttribute('from'));
            }

            $this->eventManager()->fire('onMessageReceivedClient',
                [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('type'),
                    $node->getAttribute('t'),
                    $node->getAttribute('participant'),
                ]);

            $this->sendAck($node, 'receipt');
        }
        if ($node->getTag() == 'message') {
            $handler = new MessageHandler($this, $node);
        }
        if ($node->getTag() == 'presence' && $node->getAttribute('status') == 'dirty') {
            //clear dirty
            $categories = [];
            if (count($node->getChildren()) > 0) {
                foreach ($node->getChildren() as $child) {
                    if ($child->getTag() == 'category') {
                        $categories[] = $child->getAttribute('name');
                    }
                }
            }
            $this->sendClearDirty($categories);
        }
        if (strcmp($node->getTag(), 'presence') == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), '-') === false) {
            $presence = [];
            if ($node->getAttribute('type') == null) {
                $this->eventManager()->fire('onPresenceAvailable',
                    [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                    ]);
            } else {
                $this->eventManager()->fire('onPresenceUnavailable',
                    [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('last'),
                    ]);
            }
        }
        if ($node->getTag() == 'presence'
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), '-') !== false
            && $node->getAttribute('type') != null) {
            $groupId = $this->parseJID($node->getAttribute('from'));
            if ($node->getAttribute('add') != null) {
                $this->eventManager()->fire('onGroupsParticipantsAdd',
                    [
                        $this->phoneNumber,
                        $groupId,
                        $this->parseJID($node->getAttribute('add')),
                    ]);
            } elseif ($node->getAttribute('remove') != null) {
                $this->eventManager()->fire('onGroupsParticipantsRemove',
                    [
                        $this->phoneNumber,
                        $groupId,
                        $this->parseJID($node->getAttribute('remove')),
                    ]);
            }
        }
        if (strcmp($node->getTag(), 'chatstate') == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0) { // remove if isn't group
            if (strpos($node->getAttribute('from'), '-') === false) {
                if ($node->getChild(0)->getTag() == 'composing') {
                    $this->eventManager()->fire('onMessageComposing',
                  [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    'composing',
                    $node->getAttribute('t'),
                  ]);
                } else {
                    $this->eventManager()->fire('onMessagePaused',
                  [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    'paused',
                    $node->getAttribute('t'),
                  ]);
                }
            } else {
                if ($node->getChild(0)->getTag() == 'composing') {
                    $this->eventManager()->fire('onGroupMessageComposing',
                    [
                      $this->phoneNumber,
                      $node->getAttribute('from'),
                      $node->getAttribute('participant'),
                      $node->getAttribute('id'),
                      'composing',
                      $node->getAttribute('t'),
                    ]);
                } else {
                    $this->eventManager()->fire('onGroupMessagePaused',
                    [
                      $this->phoneNumber,
                      $node->getAttribute('from'),
                      $node->getAttribute('participant'),
                      $node->getAttribute('id'),
                      'paused',
                      $node->getAttribute('t'),
                    ]);
                }
            }
        }
        if ($node->getTag() == 'receipt') {
            $this->eventManager()->fire('onGetReceipt',
                [
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('offline'),
                    $node->getAttribute('retry'),
                ]);
        }
        if ($node->getTag() == 'iq') {
            $handler = new IqHandler($this, $node);
        }

        if ($node->getTag() == 'notification') {
            $handler = new NotificationHandler($this, $node);
        }
        if ($node->getTag() == 'call') {
            if ($node->getChild(0)->getTag() == 'offer') {
                $callId = $node->getChild(0)->getAttribute('call-id');
                $this->sendReceipt($node, null, null, $callId);

                $this->eventManager()->fire('onCallReceived',
                [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('notify'),
                    $node->getAttribute('t'),
                    $node->getChild(0)->getAttribute('call-id'),
                ]);
            } else {
                $this->sendAck($node, 'call');
            }
        }
        if ($node->getTag() == 'ib') {
            foreach ($node->getChildren() as $child) {
                switch ($child->getTag()) {
                    case 'dirty':
                        $this->sendClearDirty([$child->getAttribute('type')]);
                        break;
                    case 'account':
                        $this->eventManager()->fire('onPaymentRecieved',
                        [
                            $this->phoneNumber,
                            $child->getAttribute('kind'),
                            $child->getAttribute('status'),
                            $child->getAttribute('creation'),
                            $child->getAttribute('expiration'),
                        ]);
                        break;
                    case 'offline':

                        break;
                    default:
                        throw new Exception('ib handler for '.$child->getTag().' not implemented');
                }
            }
        }

        // Disconnect socket on stream error.
        if ($node->getTag() == 'stream:error') {
            $this->eventManager()->fire('onStreamError',
            [
                $node->getChild(0)->getTag(),
            ]);

            $this->logFile('error', 'Stream error {error}', ['error' => $node->getChild(0)->getTag()]);
            $this->disconnect();
        }
        if (isset($handler)) {
            $handler->Process();
            unset($handler);
        }
    }

    /**
     * @param $node  ProtocolNode
     * @param $class string
     */
    public function sendAck($node, $class, $isGroup = false)
    {
        $from = $node->getAttribute('from');
        $to = $node->getAttribute('to');
        $id = $node->getAttribute('id');
        $participant = null;
        $type = null;
        if (!$isGroup) {
            $type = $node->getAttribute('type');
            $participant = $node->getAttribute('participant');
        }

        $attributes = [];
        if ($to) {
            $attributes['from'] = $to;
        }
        if ($participant) {
            $attributes['participant'] = $participant;
        }
        if ($isGroup) {
            $attributes['count'] = $this->retryCounters[$id];
        }
        $attributes['to'] = $from;
        $attributes['class'] = $class;
        $attributes['id'] = $id;
    //  if ($node->getAttribute("id") != null)
    //    $attributes["t"] = $node->getAttribute("t");
        if ($type != null) {
            $attributes['type'] = $type;
        }

        $ack = new ProtocolNode('ack', $attributes, null, null);

        $this->sendNode($ack);
    }

    /**
     * Process and save media image.
     *
     * @param ProtocolNode $node ProtocolNode containing media
     */
    protected function processMediaImage($node)
    {
        $media = $node->getChild('media');

        if ($media != null) {
            $filename = $media->getAttribute('file');
            $url = $media->getAttribute('url');

            //save thumbnail
            file_put_contents($this->dataFolder.Constants::MEDIA_FOLDER.DIRECTORY_SEPARATOR.'thumb_'.$filename, $media->getData());
            //download and save original
            file_put_contents($this->dataFolder.Constants::MEDIA_FOLDER.DIRECTORY_SEPARATOR.$filename, file_get_contents($url));
        }
    }

    /**
     * Processes received picture node.
     *
     * @param ProtocolNode $node ProtocolNode containing the picture
     */
    protected function processProfilePicture($node)
    {
        $pictureNode = $node->getChild('picture');

        if ($pictureNode != null) {
            if ($pictureNode->getAttribute('type') == 'preview') {
                $filename = $this->dataFolder.Constants::PICTURES_FOLDER.DIRECTORY_SEPARATOR.'preview_'.$node->getAttribute('from').'jpg';
            } else {
                $filename = $this->dataFolder.Constants::PICTURES_FOLDER.DIRECTORY_SEPARATOR.$node->getAttribute('from').'.jpg';
            }

            file_put_contents($filename, $pictureNode->getData());
        }
    }

    /**
     * If the media file was originally from a URL, this function either deletes it
     * or renames it depending on the user option.
     *
     * @param bool $storeURLmedia Save or delete the media file from local server
     */
    protected function processTempMediaFile($storeURLmedia)
    {
        if (!isset($this->mediaFileInfo['url'])) {
            return false;
        }

        if ($storeURLmedia && is_file($this->mediaFileInfo['filepath'])) {
            rename($this->mediaFileInfo['filepath'], $this->mediaFileInfo['filepath'].'.'.$this->mediaFileInfo['fileextension']);
        } elseif (is_file($this->mediaFileInfo['filepath'])) {
            unlink($this->mediaFileInfo['filepath']);
        }
    }

    /**
     * Process media upload response.
     *
     * @param ProtocolNode $node Message node
     *
     * @return bool
     */
    public function processUploadResponse($node)
    {
        $id = $node->getAttribute('id');
        $messageNode = @$this->mediaQueue[$id];
        if ($messageNode == null) {
            //message not found, can't send!
            $this->eventManager()->fire('onMediaUploadFailed',
                [
                    $this->phoneNumber,
                    $id,
                    $node,
                    $messageNode,
                    'Message node not found in queue',
                ]);

            return false;
        }

        $duplicate = $node->getChild('duplicate');
        if ($duplicate != null) {
            //file already on whatsapp servers
            $url = $duplicate->getAttribute('url');
            $filesize = $duplicate->getAttribute('size');
//          $mimetype = $duplicate->getAttribute("mimetype");
            $filehash = $duplicate->getAttribute('filehash');
            $filetype = $duplicate->getAttribute('type');
//          $width = $duplicate->getAttribute("width");
//          $height = $duplicate->getAttribute("height");
            $exploded = explode('/', $url);
            $filename = array_pop($exploded);
        } else {
            //upload new file
            $json = WhatsMediaUploader::pushFile($node, $messageNode, $this->mediaFileInfo, $this->phoneNumber);

            if (!$json) {
                //failed upload
                $this->eventManager()->fire('onMediaUploadFailed',
                    [
                        $this->phoneNumber,
                        $id,
                        $node,
                        $messageNode,
                        'Failed to push file to server',
                    ]);

                return false;
            }

            $url = $json->url;
            $filesize = $json->size;
//          $mimetype = $json->mimetype;
            $filehash = $json->filehash;
            $filetype = $json->type;
//          $width = $json->width;
//          $height = $json->height;
            $filename = $json->name;
        }

        $mediaAttribs = [];
        $mediaAttribs['type'] = $filetype;
        $mediaAttribs['url'] = $url;
        $mediaAttribs['encoding'] = 'raw';
        $mediaAttribs['file'] = $filename;
        $mediaAttribs['size'] = $filesize;
        if ($this->mediaQueue[$id]['caption'] != '') {
            $mediaAttribs['caption'] = $this->mediaQueue[$id]['caption'];
        }
        if ($this->voice == true) {
            $mediaAttribs['origin'] = 'live';
            $this->voice = false;
        }

        $filepath = $this->mediaQueue[$id]['filePath'];
        $to = $this->mediaQueue[$id]['to'];

        switch ($filetype) {
            case 'image':
                $caption = $this->mediaQueue[$id]['caption'];
                $icon = createIcon($filepath);
                break;
            case 'video':
                $caption = $this->mediaQueue[$id]['caption'];
                $icon = createVideoIcon($filepath);
                break;
            default:
                $caption = '';
                $icon = '';
                break;
        }
        //Retrieve Message ID
        $message_id = $messageNode['message_id'];

        $mediaNode = new ProtocolNode('media', $mediaAttribs, null, $icon);
        if (is_array($to)) {
            $this->sendBroadcast($to, $mediaNode, 'media');
        } else {
            $this->sendMessageNode($to, $mediaNode, $message_id);
        }
        $this->eventManager()->fire('onMediaMessageSent',
            [
                $this->phoneNumber,
                $to,
                $message_id,
                $filetype,
                $url,
                $filename,
                $filesize,
                $filehash,
                $caption,
                $icon,
            ]);

        return true;
    }

    /**
     * Read 1024 bytes from the whatsapp server.
     *
     * @throws Exception
     */
    public function readStanza()
    {
        $buff = '';

        if ($this->isConnected()) {
            $header = @socket_read($this->socket, 3); //read stanza header
           // if($header !== false && strlen($header) > 1){

            if ($header === false) {
                $this->eventManager()->fire('onClose',
                    [
                        $this->phoneNumber,
                        'Socket EOF',
                    ]
                );
            }
            if (strlen($header) == 0) {
                //no data received
                return;
            }
            if (strlen($header) != 3) {
                throw new ConnectionException('Failed to read stanza header');
            }
            $treeLength = (ord($header[0]) & 0x0F) << 16;
            $treeLength |= ord($header[1]) << 8;
            $treeLength |= ord($header[2]) << 0;

            //read full length
            $buff = socket_read($this->socket, $treeLength);
            //$trlen = $treeLength;
            $len = strlen($buff);
            //$prev = 0;
            while (strlen($buff) < $treeLength) {
                $toRead = $treeLength - strlen($buff);
                $buff .= socket_read($this->socket, $toRead);
                if ($len == strlen($buff)) {
                    //no new data read, fuck it
                    break;
                }
                $len = strlen($buff);
            }

            if (strlen($buff) != $treeLength) {
                throw new ConnectionException('Tree length did not match received length (buff = '.strlen($buff)." & treeLength = $treeLength)");
            }
            $buff = $header.$buff;
        }

        return $buff;
    }

    /**
     * Checks that the media file to send is of allowable filetype and within size limits.
     *
     * @param string $filepath          The URL/URI to the media file
     * @param int    $maxSize           Maximum filesize allowed for media type
     * @param string $to                Recipient ID/number
     * @param string $type              media filetype. 'audio', 'video', 'image'
     * @param array  $allowedExtensions An array of allowable file types for the media file
     * @param bool   $storeURLmedia     Keep a copy of the media file
     * @param string $caption           *
     *
     * @return string|null Message ID if successfully, null if not.
     */
    protected function sendCheckAndSendMedia($filepath, $maxSize, $to, $type, $allowedExtensions, $storeURLmedia, $caption = '')
    {
        if ($this->getMediaFile($filepath, $maxSize) == true) {
            if (in_array($this->mediaFileInfo['fileextension'], $allowedExtensions)) {
                $b64hash = base64_encode(hash_file('sha256', $this->mediaFileInfo['filepath'], true));
                //request upload and get Message ID
                $id = $this->sendRequestFileUpload($b64hash, $type, $this->mediaFileInfo['filesize'], $this->mediaFileInfo['filepath'], $to, $caption);
                $this->processTempMediaFile($storeURLmedia);
                // Return message ID. Make pull request for this.
                return $id;
            } else {
                //Not allowed file type.
                $this->processTempMediaFile($storeURLmedia);

                return;
            }
        } else {
            //Didn't get media file details.
            return;
        }
    }

    /**
     * Send a broadcast.
     *
     * @param array  $targets Array of numbers to send to
     * @param object $node
     * @param        $type
     *
     * @return string
     */
    protected function sendBroadcast($targets, $node, $type)
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        $toNodes = [];
        foreach ($targets as $target) {
            $jid = $this->getJID($target);
            $hash = ['jid' => $jid];
            $toNode = new ProtocolNode('to', $hash, null, null);
            $toNodes[] = $toNode;
        }

        $broadcastNode = new ProtocolNode('broadcast', null, $toNodes, null);

        $msgId = $this->createMsgId();

        $messageNode = new ProtocolNode('message',
            [
                'to'   => time().'@broadcast',
                'type' => $type,
                'id'   => $msgId,
            ], [$node, $broadcastNode], null);

        $this->sendNode($messageNode);
        $this->waitForServer($msgId);
        //listen for response
        $this->eventManager()->fire('onSendMessage',
            [
                $this->phoneNumber,
                $targets,
                $msgId,
                $node,
            ]);

        return $msgId;
    }

    /**
     * Send data to the WhatsApp server.
     *
     * @param string $data
     *
     * @throws Exception
     */
    public function sendData($data)
    {
        if ($this->isConnected()) {
            if (socket_write($this->socket, $data, strlen($data)) === false) {
                $this->eventManager()->fire('onClose',
                   [
                        $this->phoneNumber,
                        'Connection closed!',
                    ]
              );
            }
        }
    }

    /**
     * Send the getGroupList request to WhatsApp.
     *
     * @param string $type Type of list of groups to retrieve. "owning" or "participating"
     */
    protected function sendGetGroupsFiltered($type)
    {
        $msgID = $this->nodeId['getgroups'] = $this->createIqId();
        $child = new ProtocolNode($type, null, null, null);
        $node = new ProtocolNode('iq',
            [
                'id'    => $msgID,
                'type'  => 'get',
                'xmlns' => 'w:g2',
                'to'    => Constants::WHATSAPP_GROUP_SERVER,
            ], [$child], null);

        $this->sendNode($node);
    }

    /**
     * Change participants of a group.
     *
     * @param string $groupId     The group ID.
     * @param string $participant The participant.
     * @param string $tag         The tag action. 'add', 'remove', 'promote' or 'demote'
     * @param        $id
     */
    protected function sendGroupsChangeParticipants($groupId, $participant, $tag, $id)
    {
        $participants = new ProtocolNode('participant', ['jid' => $this->getJID($participant)], null, '');

        $childHash = [];
        $child = new ProtocolNode($tag, $childHash, [$participants], '');

        $node = new ProtocolNode('iq',
            [
                'id'    => $id,
                'type'  => 'set',
                'xmlns' => 'w:g2',
                'to'    => $this->getJID($groupId),
            ], [$child], '');

        $this->sendNode($node);
    }

    /**
     * Send node to the servers.
     *
     * @param              $to
     * @param ProtocolNode $node
     * @param null         $id
     *
     * @return string Message ID.
     */
    protected function sendMessageNode($to, $node, $id = null, $plaintextNode = null)
    {
        $msgId = ($id == null) ? $this->createMsgId() : $id;
        $to = $this->getJID($to);

        if ($node->getTag() == 'body' || $node->getTag() == 'enc') {
            $type = 'text';
        } else {
            $type = 'media';
        }

        $messageNode = new ProtocolNode('message', [
            'to'      => $to,
            'type'    => $type,
            'id'      => $msgId,
            't'       => time(),
            'notify'  => $this->name,
        ], [$node], '');

        $this->sendNode($messageNode);

        if ($node->getTag() == 'enc') {
            $node = $plaintextNode;
        }

        $this->logFile('info', '{type} message with id {id} sent to {to}', ['type' => $type, 'id' => $msgId, 'to' => ExtractNumber($to)]);
        $this->eventManager()->fire('onSendMessage',
            [
                $this->phoneNumber,
                $to,
                $msgId,
                $node,
            ]);

       // $this->waitForServer($msgId);

        return $msgId;
    }

    /**
     * Tell the server we received the message.
     *
     * @param ProtocolNode $node        The ProtocolTreeNode that contains the message.
     * @param string       $type
     * @param string       $participant
     * @param string       $callId
     */
    public function sendReceipt($node, $type = 'read', $participant = null, $callId = null)
    {
        $messageHash = [];
        if ($type == 'read') {
            $messageHash['type'] = $type;
        }
        if ($participant != null) {
            $messageHash['participant'] = $participant;
        }
        $messageHash['to'] = $node->getAttribute('from');
        $messageHash['id'] = $node->getAttribute('id');
        $messageHash['t'] = $node->getAttribute('t');

        if ($callId != null) {
            $offerNode = new ProtocolNode('offer', ['call-id' => $callId], null, null);
            $messageNode = new ProtocolNode('receipt', $messageHash, [$offerNode], null);
        } else {
            $messageNode = new ProtocolNode('receipt', $messageHash, null, null);
        }
        $this->sendNode($messageNode);
        $this->eventManager()->fire('onSendMessageReceived',
            [
                $this->phoneNumber,
                $node->getAttribute('id'),
                $node->getAttribute('from'),
                $type,
            ]);
    }

    /**
     * Send a read receipt to a message.
     *
     * @param string $to The recipient.
     * @param mixed String or Array $id
     */
    public function sendMessageRead($to, $id)
    {
        $listNode = null;
        $idNode = $id;
        if (is_array($id) && count($id > 1)) {
            $idNode = array_shift($id);
            foreach($id as $itemId) {
                $items[] = new ProtocolNode('item',
                [
                  'id' => $itemId
                ], null, null);
            }
            $listNode = new ProtocolNode('list', null, $items, null);
        }

        $messageNode = new ProtocolNode('receipt',
        [
          'type' => 'read',
          't'    => time(),
          'to'   => $this->getJID($to),
          'id'   => $idNode
        ], [$listNode], null);

        $this->sendNode($messageNode);
    }

    /**
     * Send node to the WhatsApp server.
     *
     * @param ProtocolNode $node
     * @param bool         $encrypt
     */
    public function sendNode($node, $encrypt = true)
    {
        $this->timeout = time();
        $this->debugPrint($node->nodeString('tx  ')."\n");
        $this->sendData($this->writer->write($node, $encrypt));
    }

    /**
     * Send request to upload file.
     *
     * @param string $b64hash  A base64 hash of file
     * @param string $type     File type
     * @param string $size     File size
     * @param string $filepath Path to image file
     * @param mixed  $to       Recipient(s)
     * @param string $caption
     *
     * @return string Message ID
     */
    protected function sendRequestFileUpload($b64hash, $type, $size, $filepath, $to, $caption = '')
    {
        $id = $this->createIqId();

        if (!is_array($to)) {
            $to = $this->getJID($to);
        }

        $mediaNode = new ProtocolNode('media', [
            'hash'  => $b64hash,
            'type'  => $type,
            'size'  => $size,
        ], null, null);

        $node = new ProtocolNode('iq', [
            'id'    => $id,
            'to'    => Constants::WHATSAPP_SERVER,
            'type'  => 'set',
            'xmlns' => 'w:m',
        ], [$mediaNode], null);

        //add to queue
        $messageId = $this->createMsgId();
        $this->mediaQueue[$id] = [
            'messageNode' => $node,
            'filePath'    => $filepath,
            'to'          => $to,
            'message_id'  => $messageId,
            'caption'     => $caption,
        ];

        $this->sendNode($node);
        $this->waitForServer($id);

        // Return message ID. Make pull request for this.
        return $messageId;
    }

    /**
     * Set your profile picture.
     *
     * @param string $jid
     * @param string $filepath URL or localpath to image file
     */
    protected function sendSetPicture($jid, $filepath)
    {
        $nodeID = $this->createIqId();

        $data = preprocessProfilePicture($filepath);
        $preview = createIconGD($filepath, 96, true);

        $picture = new ProtocolNode('picture', ['type' => 'image'], null, $data);
        $preview = new ProtocolNode('picture', ['type' => 'preview'], null, $preview);

        $node = new ProtocolNode('iq', [
            'id'    => $nodeID,
            'to'    => $this->getJID($jid),
            'type'  => 'set',
            'xmlns' => 'w:profile:picture',
        ], [$picture, $preview], null);

        $this->sendNode($node);
    }

    /**
     * @param string $jid
     *
     * @return string
     */
    private function parseJID($jid)
    {
        $parts = explode('@', $jid);
        $parts = reset($parts);

        return $parts;
    }

    public function sendPendingMessages($jid)
    {
        if ($this->messageStore != null && $this->isLoggedIn()) {
            $messages = $this->messageStore->getPending($jid);
            foreach ($messages as $message) {
                $this->sendMessage($message['to'], $message['message']);
            }
        }
    }

    public function getSessionCipher($number)
    {
        if (!isset($this->sessionCiphers[$number])) {
          $this->sessionCiphers[$number] = new SessionCipher($this->axolotlStore, $this->axolotlStore, $this->axolotlStore, $this->axolotlStore, $number, 1);
        }

        return $this->sessionCiphers[$number];
    }

    public function getGroupCipher($groupId)
    {
        if (!isset($this->groupCiphers[$groupId])) {
            $this->groupCiphers[$groupId] = new GroupCipher($this->axolotlStore, $groupId);
        }

        return $this->groupCiphers[$groupId];
    }

    public function getMyNumber()
    {
        return $this->phoneNumber;
    }

    public function getReadReceipt()
    {
        return $this->readReceipts;
    }

    public function getNodeId()
    {
        return $this->nodeId;
    }

    public function getv2Jids()
    {
        return $this->v2Jids;
    }

    public function setv2Jids($author)
    {
        $this->v2Jids[] = $author;
    }

    public function setRetryCounter($id, $counter)
    {
        $this->retryCounters[$id] = $counter;
    }

    public function setGroupId($id)
    {
        $this->groupId = $id;
    }

    public function setMessageId($id)
    {
        $this->messageId = $id;
    }

    public function getChallengeData()
    {
        return $this->challengeData;
    }

    public function setChallengeData($data)
    {
        $this->challengeData = $data;
    }

    public function setOutputKey($outputKey)
    {
        $this->outputKey = $outputKey;
    }

    public function getLoginStatus()
    {
        return $this->loginStatus;
    }

    public function getPendingNodes()
    {
        return $this->pending_nodes;
    }

    public function unsetPendingNode($jid)
    {
        unset($this->pending_nodes[ExtractNumber($jid)]);
    }

    public function getNewMsgBind()
    {
        return $this->newMsgBind;
    }

    public function getMessageStore()
    {
        return $this->messageStore;
    }

    public function getAxolotlStore()
    {
        return $this->axolotlStore;
    }

    public function pushMessageToQueue($node)
    {
        array_push($this->messageQueue, $node);
    }

    /**
     * @return mixed
     */
    public function getLastId()
    {
        return $this->lastId;
    }

    /**
     * @param mixed $lastId
     *
     * @return WhatsProt
     */
    public function setLastId($lastId)
    {
        $this->lastId = $lastId;
        return $this;
    }

}
