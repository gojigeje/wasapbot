<?php
require_once 'protocol.class.php';
require_once 'BinTreeNodeReader.php';
require_once 'BinTreeNodeWriter.php';
require_once 'Constants.php';
require_once 'func.php';
require_once 'token.php';
require_once 'rc4.php';
require_once 'mediauploader.php';
require_once 'keystream.class.php';
require_once 'tokenmap.class.php';
require_once 'events/WhatsApiEventsManager.php';
require_once 'SqliteMessageStore.php';

class SyncResult
{
    public $index;
    public $syncId;
    /** @var array $existing */
    public $existing;
    /** @var array $nonExisting */
    public $nonExisting;

    public function __construct($index, $syncId, $existing, $nonExisting)
    {
        $this->index = $index;
        $this->syncId = $syncId;
        $this->existing = $existing;
        $this->nonExisting = $nonExisting;
    }
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
    protected $event;                   // An instance of the WhatsApiEvent Manager.
    protected $groupList = array();     // An array with all the groups a user belongs in.
    protected $identity;                // The Device Identity token. Obtained during registration with this API or using Missvenom to sniff from your phone.
    protected $inputKey;                // Instances of the KeyStream class.
    protected $outputKey;               // Instances of the KeyStream class.
    protected $groupId = false;         // Id of the group created.
    protected $lastId = false;          // Id to the last message sent.
    protected $loginStatus;             // Holds the login status.
    protected $mediaFileInfo = array(); // Media File Information
    protected $mediaQueue = array();    // Queue for media message nodes
    protected $messageCounter = 0;      // Message counter for auto-id.
    protected $iqCounter = 1;
    protected $messageQueue = array();  // Queue for received messages.
    protected $name;                    // The user name.
    protected $newMsgBind = false;      //
    protected $outQueue = array();      // Queue for outgoing messages.
    protected $password;                // The user password.
    protected $phoneNumber;             // The user phone number including the country code without '+' or '00'.
    protected $serverReceivedId;        // Confirm that the *server* has received your command.
    protected $socket;                  // A socket to connect to the WhatsApp network.
    protected $writer;                  // An instance of the BinaryTreeNodeWriter class.
    protected $messageStore;
    protected $nodeId = array();
    protected $messageId;
    protected $timeout;
    protected $voice;
    public    $reader;                  // An instance of the BinaryTreeNodeReader class.

    /**
     * Default class constructor.
     *
     * @param string $number
     *   The user phone number including the country code without '+' or '00'.
     * @param string $nickname
     *   The user name.
     * @param $debug
     *   Debug on or off, false by default.
     * @param mixed $identityFile
     *  Path to identity file, overrides default path
     */
    public function __construct($number, $nickname, $debug = false, $identityFile = false)
    {
        $this->writer = new BinTreeNodeWriter();
        $this->reader = new BinTreeNodeReader();
        $this->debug = $debug;
        $this->phoneNumber = $number;

        //e.g. ./cache/nextChallenge.12125557788.dat
        $this->challengeFilename = sprintf('%s%s%snextChallenge.%s.dat',
            __DIR__,
            DIRECTORY_SEPARATOR,
            Constants::DATA_FOLDER . DIRECTORY_SEPARATOR,
            $number);

        $this->identity = $this->buildIdentity($identityFile);

        $this->name         = $nickname;
        $this->loginStatus  = Constants::DISCONNECTED_STATUS;
        $this->eventManager = new WhatsApiEventsManager();
    }

    /**
     * If you need use different challenge fileName you can use this
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
     * Check if account credentials are valid.
     *
     * WARNING: WhatsApp now changes your password everytime you use this.
     * Make sure you update your config file if the output informs about
     * a password change.
     *
     * @return object
     *   An object with server response.
     *   - status: Account status.
     *   - login: Phone number with country code.
     *   - pw: Account password.
     *   - type: Type of account.
     *   - expiration: Expiration date in UNIX TimeStamp.
     *   - kind: Kind of account.
     *   - price: Formatted price of account.
     *   - cost: Decimal amount of account.
     *   - currency: Currency price of account.
     *   - price_expiration: Price expiration in UNIX TimeStamp.
     *
     * @throws Exception
     */
    public function checkCredentials()
    {
        if (!$phone = $this->dissectPhone()) {
            throw new Exception('The provided phone number is not valid.');
        }

        $countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        $langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        // Build the url.
        $host  = 'https://' . Constants::WHATSAPP_CHECK_HOST;
        $query = array(
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
        //  'network_radio_type' => "1"
        );

        $response = $this->getResponse($host, $query);

        if ($response->status != 'ok') {
            $this->eventManager()->fire("onCredentialsBad",
                array(
                    $this->phoneNumber,
                    $response->status,
                    $response->reason
                ));

            $this->debugPrint($query);
            $this->debugPrint($response);

            throw new Exception('There was a problem trying to request the code.');
        } else {
            $this->eventManager()->fire("onCredentialsGood",
                array(
                    $this->phoneNumber,
                    $response->login,
                    $response->pw,
                    $response->type,
                    $response->expiration,
                    $response->kind,
                    $response->price,
                    $response->cost,
                    $response->currency,
                    $response->price_expiration
                ));
        }

        return $response;
    }

    /**
     * Register account on WhatsApp using the provided code.
     *
     * @param integer $code
     *   Numeric code value provided on requestCode().
     *
     * @return object
     *   An object with server response.
     *   - status: Account status.
     *   - login: Phone number with country code.
     *   - pw: Account password.
     *   - type: Type of account.
     *   - expiration: Expiration date in UNIX TimeStamp.
     *   - kind: Kind of account.
     *   - price: Formatted price of account.
     *   - cost: Decimal amount of account.
     *   - currency: Currency price of account.
     *   - price_expiration: Price expiration in UNIX TimeStamp.
     *
     * @throws Exception
     */
    public function codeRegister($code)
    {
        if (!$phone = $this->dissectPhone()) {
            throw new Exception('The provided phone number is not valid.');
        }

        //$countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        //$langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        // Build the url.
        $host = 'https://' . Constants::WHATSAPP_REGISTER_HOST;
        $query = array(
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'code' => $code,
            //'lg' => $langCode,
            //'lc' => $countryCode,
            //'network_radio_type' => "1"
        );

        $response = $this->getResponse($host, $query);


        if ($response->status != 'ok') {
            $this->eventManager()->fire("onCodeRegisterFailed",
                array(
                    $this->phoneNumber,
                    $response->status,
                    $response->reason,
                    isset($response->retry_after) ? $response->retry_after : null
                ));

            $this->debugPrint($query);
            $this->debugPrint($response);

            if ($response->reason == 'old_version')
                $this->update();

            throw new Exception("An error occurred registering the registration code from WhatsApp. Reason: $response->reason");
        } else {
            $this->eventManager()->fire("onCodeRegister",
                array(
                    $this->phoneNumber,
                    $response->login,
                    $response->pw,
                    $response->type,
                    $response->expiration,
                    $response->kind,
                    $response->price,
                    $response->cost,
                    $response->currency,
                    $response->price_expiration
                ));
        }

        return $response;
    }

    /**
     * Request a registration code from WhatsApp.
     *
     * @param string $method Accepts only 'sms' or 'voice' as a value.
     * @param string $carrier
     *
     * @return object
     *   An object with server response.
     *   - status: Status of the request (sent/fail).
     *   - length: Registration code lenght.
     *   - method: Used method.
     *   - reason: Reason of the status (e.g. too_recent/missing_param/bad_param).
     *   - param: The missing_param/bad_param.
     *   - retry_after: Waiting time before requesting a new code.
     *
     * @throws Exception
     */
    public function codeRequest($method = 'sms', $carrier = "T-Mobile5")
    {
        if (!$phone = $this->dissectPhone()) {
            throw new Exception('The provided phone number is not valid.');
        }

        $countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        $langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        if ($carrier != null) {
            $mnc = $this->detectMnc(strtolower($countryCode), $carrier);
        } else {
            $mnc = $phone['mnc'];
        }

        // Build the token.
        $token = generateRequestToken($phone['country'], $phone['phone']);

        // Build the url.
        $host = 'https://' . Constants::WHATSAPP_REQUEST_HOST;
        $query = array(
            'in' => $phone['phone'],
            'cc' => $phone['cc'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
            //'mcc' => '000',
            //'mnc' => '000',
            'sim_mcc' => $phone['mcc'],
            'sim_mnc' => $mnc,
            'method' => $method,
            //'reason' => "self-send-jailbroken",
            'token' => $token,
            //'network_radio_type' => "1"
        );

        $this->debugPrint($query);

        $response = $this->getResponse($host, $query);

        $this->debugPrint($response);

        if ($response->status == 'ok') {
            $this->eventManager()->fire("onCodeRegister",
                array(
                    $this->phoneNumber,
                    $response->login,
                    $response->pw,
                    $response->type,
                    $response->expiration,
                    $response->kind,
                    $response->price,
                    $response->cost,
                    $response->currency,
                    $response->price_expiration
                ));
        } else if ($response->status != 'sent') {
            if (isset($response->reason) && $response->reason == "too_recent") {
                $this->eventManager()->fire("onCodeRequestFailedTooRecent",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        $response->retry_after
                    ));
                $minutes = round($response->retry_after / 60);
                throw new Exception("Code already sent. Retry after $minutes minutes.");

            } else if (isset($response->reason) && $response->reason == "too_many_guesses") {
                $this->eventManager()->fire("onCodeRequestFailedTooManyGuesses",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        $response->retry_after
                    ));
                $minutes = round($response->retry_after / 60);
                throw new Exception("Too many guesses. Retry after $minutes minutes.");

            }  else {
                $this->eventManager()->fire("onCodeRequestFailed",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        isset($response->param) ? $response->param : NULL
                    ));
                throw new Exception('There was a problem trying to request the code.');
            }
        } else {
            $this->eventManager()->fire("onCodeRequest",
                array(
                    $this->phoneNumber,
                    $method,
                    $response->length
                ));
        }

        return $response;
    }

    public function update()
    {
        $WAData = json_decode(file_get_contents(Constants::WHATSAPP_VER_CHECKER), true);
        $WAver = $WAData['e'];

        if(Constants::WHATSAPP_VER != $WAver)
        {
            updateData('token.php', null, $WAData['h']);
            updateData('Constants.php', $WAver);
        }
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
            $result = socket_connect($socket, "e" . rand(1, 16) . ".whatsapp.net", Constants::PORT);
            if ($result === false) {
                $socket = false;
            }
        }

        if ($socket !== false) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => Constants::TIMEOUT_SEC, 'usec' => Constants::TIMEOUT_USEC));
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => Constants::TIMEOUT_SEC, 'usec' => Constants::TIMEOUT_USEC));

            $this->socket = $socket;
            $this->eventManager()->fire("onConnect",
                array(
                    $this->phoneNumber,
                    $this->socket
                )
            );
            return true;
        } else {
            $this->eventManager()->fire("onConnectError",
                array(
                    $this->phoneNumber,
                    $this->socket
                )
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
        return ($this->socket !== null);
    }

    /**
     * Reconnect
     */
    public function reconnect()
    {
      $this->connect();
      $this->loginWithPassword($this->password);
    }

    /**
     * Disconnect from the WhatsApp network.
     */
    public function disconnect()
    {
        if (is_resource($this->socket)) {
            @socket_shutdown($this->socket, 2);
            @socket_close($this->socket);
            $this->socket = null;
            $this->loginStatus  = Constants::DISCONNECTED_STATUS;
            $this->eventManager()->fire("onDisconnect",
                array(
                    $this->phoneNumber,
                    $this->socket
                )
            );
        }
    }

    /**
     * @return WhatsApiEventsManager
     */
    public function eventManager()
    {
        return $this->eventManager;
    }

    /**
     * Drain the message queue for application processing.
     *
     * @return ProtocolNode[]
     *   Return the message queue list.
     */
    public function getMessages()
    {
        $ret = $this->messageQueue;
        $this->messageQueue = array();

        return $ret;
    }

    /**
     * Log into the WhatsApp server.
     *
     * ###Warning### using this method will generate a new password
     * from the WhatsApp servers each time.
     *
     * If you know your password and wish to use it without generating
     * a new password - use the loginWithPassword() method instead.
     */
    public function login()
    {
        $this->accountInfo = (array) $this->checkCredentials();
        if ($this->accountInfo['status'] == 'ok') {
            $this->debugPrint("New password received: " . $this->accountInfo['pw'] . "\n");
            $this->password = $this->accountInfo['pw'];
        }
        $this->doLogin();
    }

    /**
     * Login to the WhatsApp server with your password
     *
     * If you already know your password you can log into the Whatsapp server
     * using this method.
     *
     * @param  string  $password         Your whatsapp password. You must already know this!
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
        $this->doLogin();
    }

    /**
     * Fetch a single message node
     * @param  bool   $autoReceipt
     * @param  string $type
     * @return bool
     *
     * @throws Exception
     */
    public function pollMessage($autoReceipt = true, $type = "read")
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection Closed!');
        }

        $r = array($this->socket);
        $w = array();
        $e = array();
        $s = socket_select($r, $w, $e, Constants::TIMEOUT_SEC, Constants::TIMEOUT_USEC);

        if ($s) {
            // Something to read
            if ($stanza = $this->readStanza()) {
                $this->processInboundData($stanza, $autoReceipt, $type);
                $this->timeout = null;
                return true;
            }
            else {
              $s = 0;
            }
        }

        if ($s == 0)
        {
            if (!isset($this->timeout))
              $this->timeout = time();

            if ((time() - $this->timeout) > 300)
            {
              $this->timeout = null;
              $this->disconnect();
              throw new ConnectionException('Connectivity error');
            }
        }

        return false;
    }

    /**
     * Send the active status. User will show up as "Online" (as long as socket is connected).
     */
    public function sendActiveStatus()
    {
        $messageNode = new ProtocolNode("presence", array("type" => "active"), null, "");
        $this->sendNode($messageNode);
    }

    /**
     * Send a request to get cipher keys from an user
     *
     * @param $number
     *    Phone number of the user you want to get the cipher keys.
     */
    public function sendGetCipherKeysFromUser($number)
    {
        $msgId = $this->createIqId();

        $userNode = new ProtocolNode("user",
            array(
                "jid" => $this->getJID($number)
            ), null, null);
        $keyNode = new ProtocolNode("key", null, array($userNode), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "encrypt",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($keyNode), null);

        $this->sendNode($node);
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
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastAudio($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
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
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastImage($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
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
     * @param  array  $targets  An array of numbers to send to.
     * @param  float $long      The longitude of the location eg 54.31652
     * @param  float $lat       The latitude if the location eg -6.833496
     * @param  string $name     (Optional) A name to describe the location
     * @param  string $url      (Optional) A URL to link location to web resource
     * @return string           Message ID
     */
    public function sendBroadcastLocation($targets, $long, $lat, $name = null, $url = null)
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageLocation($targets, $long, $lat, $name, $url);
    }

    /**
     * Send a Broadcast Message
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param  array  $targets      An array of numbers to send to.
     * @param  string $message      Your message
     * @return string               Message ID
     */
    public function sendBroadcastMessage($targets, $message)
    {
        $bodyNode = new ProtocolNode("body", null, null, $message);
        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $bodyNode, "text");
    }

    /**
     * Send a Broadcast Message with a video.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array   $targets       An array of numbers to send to.
     * @param string  $path          URL or local path to the video file to send
     * @param bool    $storeURLmedia Keep a copy of the audio file on your server
     * @param int     $fsize
     * @param string  $fhash
     * @param string  $caption
     * @return string|null           Message ID if successfully, null if not.
     */
    public function sendBroadcastVideo($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageVideo($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Delete Broadcast lists
     *
     * @param  string array $lists
     * Contains the broadcast-id list
     */
    public function sendDeleteBroadcastLists($lists)
    {
        $msgId = $this->createIqId();
        $listNode = array();
        if ($lists != null && count($lists) > 0) {
            for ($i = 0; $i < count($lists); $i++) {
                $listNode[$i] = new ProtocolNode("list", array("id" => $lists[$i]), null, null);
            }
        } else {
            $listNode = null;
        }
        $deleteNode = new ProtocolNode("delete", null, $listNode, null);
        $node       = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:b",
                "type" => "set",
                "to" => Constants::WHATSAPP_SERVER
            ), array($deleteNode), null);

        $this->sendNode($node);
    }

    /**
     * Clears the "dirty" status on your account
     *
     * @param  array $categories
     */
    protected function sendClearDirty($categories)
    {
        $msgId = $this->createIqId();

        $catnodes = array();
        foreach ($categories as $category) {
            $catnode = new ProtocolNode("clean", array("type" => $category), null, null);
            $catnodes[] = $catnode;
        }
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "type" => "set",
                "to" => Constants::WHATSAPP_SERVER,
                "xmlns" => "urn:xmpp:whatsapp:dirty"
            ), $catnodes, null);

        $this->sendNode($node);
    }

    public function sendClientConfig()
    {
        $attr = array();
        $attr["platform"] = Constants::WHATSAPP_DEVICE;
        $attr["version"] = Constants::WHATSAPP_VER;
        $child = new ProtocolNode("config", $attr, null, "");
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createIqId(),
                "type" => "set",
                "xmlns" => "urn:xmpp:whatsapp:push",
                "to" => Constants::WHATSAPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
    }

    public function sendGetClientConfig()
    {
        $msgId = $this->createIqId();
        $child = new ProtocolNode("config", null, null, null);
        $node  = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:push",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Transfer your number to new one
     *
     * @param  string  $number
     * @param  string  $identity
     */
    public function sendChangeNumber($number, $identity)
    {
        $msgId = $this->createIqId();

        $usernameNode = new ProtocolNode("username", null, null, $number);
        $passwordNode = new ProtocolNode("password", null, null, urldecode($identity));

        $modifyNode = new ProtocolNode("modify", null, array($usernameNode, $passwordNode), null);

        $iqNode = new ProtocolNode("iq",
            array(
                "xmlns" => "urn:xmpp:whatsapp:account",
                "id" => $msgId,
                "type" => "get",
                "to" => "c.us"
            ), array($modifyNode), null);

        $this->sendNode($iqNode);
    }

    /**
     * Send a request to return a list of groups user is currently participating in.
     *
     * To capture this list you will need to bind the "onGetGroups" event.
     */
    public function sendGetGroups()
    {
        $this->sendGetGroupsFiltered("participating");
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

        $queryNode = new ProtocolNode("query",
            array(
                "request" => "interactive"
            ), null, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "get",
                "to" => $this->getJID($groupID)
            ), array($queryNode), null);

        $this->sendNode($node);
    }


    /**
     * Send a request to get a list of people you have currently blocked.
     */
    public function sendGetPrivacyBlockedList()
    {
        $msgId = $this->nodeId['privacy'] = $this->createIqId();
        $child = new ProtocolNode("list",
            array(
                "name" => "default"
            ), null, null);

        $child2 = new ProtocolNode("query", array(), array($child), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "jabber:iq:privacy",
                "type" => "get"
            ), array($child2), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a request to get privacy settings.
     */
    public function sendGetPrivacySettings()
    {
        $msgId = $this->nodeId['privacy_settings'] = $this->createIqId();
        $privacyNode = new ProtocolNode("privacy", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "id" => $msgId,
                "xmlns" => "privacy",
                "type" => "get"
            ), array($privacyNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Set privacy of 'last seen', status or profile picture to all, contacts or none.
     *
     * @param string $category
     *   Options: 'last', 'status' or 'profile'
     * @param string $value
     *   Options: 'all', 'contacts' or 'none'
     */
    public function sendSetPrivacySettings($category, $value)
    {
        $msgId = $this->createIqId();
        $categoryNode = new ProtocolNode("category",
            array(
                "name" => $category,
                "value" => $value
            ), null, null);

        $privacyNode = new ProtocolNode("privacy", null, array($categoryNode), null);
        $node = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "type" => "set",
                "id" => $msgId,
                "xmlns" => "privacy"
            ), array($privacyNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Get profile picture of specified user.
     *
     * @param string $number
     *  Number or JID of user
     * @param bool $large
     *  Request large picture
     */
    public function sendGetProfilePicture($number, $large = false)
    {
        $msgId = $this->nodeId['getprofilepic'] = $this->createIqId();

        $hash = array();
        $hash["type"] = "image";
        if (!$large) {
            $hash["type"] = "preview";
        }
        $picture = new ProtocolNode("picture", $hash, null, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "type" => "get",
                "xmlns" => "w:profile:picture",
                "to" => $this->getJID($number)
            ), array($picture), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * @param  mixed $numbers Numbers to get profile profile photos of.
     * @return bool
     */
    public function sendGetProfilePhotoIds($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = array($numbers);
        }

        $msgId = $this->createIqId();

        $userNode = array();
        for ($i=0; $i < count($numbers); $i++) {
            $userNode[$i] = new ProtocolNode("user",
                array(
                    "jid" => $this->getJID($numbers[$i])
                ), null, null);
        }

        if (!sizeof($userNode)) {
            return false;
        }

        $listNode = new ProtocolNode("list", null, $userNode, null);

        $iqNode = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:profile:picture",
                "type" => "get"
            ), array($listNode), null);

        $this->sendNode($iqNode);
        $this->waitForServer($msgId);

        return true;
    }

    /**
     * Send a request to get the current server properties.
     */
    public function sendGetServerProperties()
    {
        $id = $this->createIqId();
        $child = new ProtocolNode("props", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $id,
                "type" => "get",
                "xmlns" => "w",
                "to" => Constants::WHATSAPP_SERVER
            ), array($child), null);

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
        $pricingNode = new ProtocolNode("pricing",
            array(
                "lg" => $lg,
                "lc" => $lc
            ), null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($pricingNode), null);

        $this->sendNode($node);
    }

    /**
     * Send a request to extend the account.
     */
    public function sendExtendAccount()
    {

        $msgId = $this->createIqId();
        $extendingNode = new ProtocolNode("extend", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "set",
                "to" => Constants::WHATSAPP_SERVER
            ), array($extendingNode), null);

        $this->sendNode($node);
    }

    /**
     * Gets all the broadcast lists for an account.
     */
    public function sendGetBroadcastLists()
    {
        $msgId = $this->nodeId['get_lists'] = $this->createIqId();
        $listsNode = new ProtocolNode("lists", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:b",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($listsNode), null);

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
        $ccNode = new ProtocolNode("cc", null, null, $countryCode);
        $inNode = new ProtocolNode("in", null, null, $number);
        $normalizeNode = new ProtocolNode("normalize", null, array($ccNode, $inNode), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($normalizeNode), null);

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
        if ($feedback != null && strlen($feedback) > 0)
        {
            if ($lg == null) {
                $lg = "";
            }

            if ($lc == null) {
                $lc = "";
            }

            $child = new ProtocolNode("body",
                array(
                    "lg" => $lg,
                    "lc" => $lc
                ), null, $feedback);
            $childNode = array($child);
        } else {
            $childNode = null;
        }

        $removeNode = new ProtocolNode("remove", null, $childNode, null);
        $node = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "get",
                "id" => $msgId
            ), array($removeNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a ping to the server.
     */
    public function sendPing()
    {
        $msgId = $this->createIqId();
        $pingNode = new ProtocolNode("ping", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:p",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($pingNode), null);

        $this->sendNode($node);
    }

    /**
     * Get VoIP information of a number or numbers.
     *
     * @param mixed $jids
     */
    public function sendGetHasVoipEnabled($jids)
    {

        $msgId = $this->createIqId();

        if (!is_array($jids))
        {
            $jids = array($jids);
        }
        $userNode = array();
        foreach ($jids as $jid)
        {
            $userNode[] = new ProtocolNode("user", array('jid' => $this->getJID($jid)), null, null);
        }

        $eligibleNode = new ProtocolNode("eligible", null, $userNode, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "voip",
                "type" => "get",
                "to" => Constants::WHATSAPP_SERVER
            ), array($eligibleNode), null);

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
            $jids = array($jids);
        }

        $children = array();
        foreach ($jids as $jid) {
            $children[] = new ProtocolNode("user", array("jid" => $this->getJID($jid)), null, null);
        }

        $iqId = $this->nodeId['getstatuses'] = $this->createIqId();

        $node = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "type" => "get",
                "xmlns" => "status",
                "id" => $iqId
            ), array(
                new ProtocolNode("status", null, $children, null)
            ), null);

        $this->sendNode($node);
        $this->waitForServer($iqId);
    }

    /**
     * Create a group chat.
     *
     * @param string $subject
     *   The group Subject
     * @param array $participants
     *   An array with the participants numbers.
     *
     * @return string
     *   The group ID.
     */
    public function sendGroupsChatCreate($subject, $participants)
    {
        if (!is_array($participants)) {
            $participants = array($participants);
        }

        $participantNode = array();
        foreach ($participants as $participant) {
            $participantNode[] = new ProtocolNode("participant", array(
                "jid" => $this->getJID($participant)
            ), null, null);
        }

        $id = $this->nodeId['groupcreate'] = $this->createIqId();

        $createNode = new ProtocolNode("create",
            array(
                "subject" => $subject
            ), $participantNode, null);

        $iqNode = new ProtocolNode("iq",
            array(
                "xmlns" => "w:g2",
                "id" => $id,
                "type" => "set",
                "to" => Constants::WHATSAPP_GROUP_SERVER
            ), array($createNode), null);

        $this->sendNode($iqNode);
        $this->waitForServer($id);
        $groupId = $this->groupId;

        $this->eventManager()->fire("onGroupCreate",
            array(
                $this->phoneNumber,
                $groupId
            ));

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
        $child = new ProtocolNode("subject", null, null, $subject);
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createIqId(),
                "type" => "set",
                "to" => $this->getJID($gjid),
                "xmlns" => "w:g2"
            ), array($child), null);

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
            $gjids = array($this->getJID($gjids));
        }

        $nodes = array();
        foreach ($gjids as $gjid) {
            $nodes[] = new ProtocolNode("group",
                array(
                    "id" => $this->getJID($gjid)
                ), null, null);
        }

        $leave = new ProtocolNode("leave",
            array(
                'action'=>'delete'
            ), $nodes, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "to" => Constants::WHATSAPP_GROUP_SERVER,
                "type" => "set",
                "xmlns" => "w:g2"
            ), array($leave), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Add participant(s) to a group.
     *
     * @param string $groupId      The group ID.
     * @param string  $participants An array with the participants numbers to add
     */
    public function sendGroupsParticipantsAdd($groupId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($groupId, $participant, 'add', $msgId);
    }

    /**
     * Remove participant from a group.
     *
     * @param string $groupId      The group ID.
     * @param string $participant  The number of the participant you want to remove
     */
    public function sendGroupsParticipantsRemove($groupId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($groupId, $participant, 'remove', $msgId);
    }

    /**
     * Promote participant of a group; Make a participant an admin of a group.
     *
     * @param string $gId          The group ID.
     * @param string $participant  The number of the participant you want to promote
     */
    public function sendPromoteParticipants($gId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($gId, $participant, "promote", $msgId);
    }

    /**
     * Demote participant of a group; remove participant of being admin of a group.
     *
     * @param string $gId          The group ID.
     * @param string $participant  The number of the participant you want to demote
     */
    public function sendDemoteParticipants($gId, $participant)
    {
        $msgId = $this->createMsgId();
        $this->sendGroupsChangeParticipants($gId, $participant, "demote", $msgId);
    }

    /**
     * Lock group: participants cant change group subject or profile picture except admin.
     *
     * @param string $gId The group ID.
     */
    public function sendLockGroup($gId)
    {
        $msgId = $this->createIqId();
        $lockedNode = new ProtocolNode("locked", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "set",
                "to" => $this->getJID($gId)
            ), array($lockedNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Unlock group: Any participant can change group subject or profile picture.
     *
     *
     * @param string $gId The group ID.
     */
    public function sendUnlockGroup($gId)
    {
        $msgId = $this->createIqId();
        $unlockedNode = new ProtocolNode("unlocked", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "set",
                "to" => $this->getJID($gId)
            ), array($unlockedNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a text message to the user/group.
     *
     * @param string $to  The recipient.
     * @param string $txt The text message.
     * @param $id
     *
     * @return string     Message ID.
     */
    public function sendMessage($to, $txt, $id = null)
    {
        $bodyNode = new ProtocolNode("body", null, null, $txt);
        $id = $this->sendMessageNode($to, $bodyNode, $id);

        if ($this->messageStore !== null) {
            $this->messageStore->saveMessage($this->phoneNumber, $to, $txt, $id, time());
        }

        return $id;
    }

    /**
     * Send a read receipt to a message.
     *
     * @param string $to The recipient.
     * @param string $id
     */
    public function sendMessageRead($to, $id)
    {
        $messageNode = new ProtocolNode("receipt",
            array(
                "type" => "read",
                "to" => $to,
                "id" => $id
            ), null, null);

        $this->sendNode($messageNode);
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
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageAudio($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "", $voice = false)
    {
        $this->voice = $voice;

        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('3gp', 'caf', 'wav', 'mp3', 'wma', 'ogg', 'aif', 'aac', 'm4a');
            $size = 10 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'audio', $allowedExtensions, $storeURLmedia);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'audio', $fsize, $filepath, $to);
        }
    }

    /**
     * Send the composing message status. When typing a message.
     *
     * @param string $to The recipient to send status to.
     */
    public function sendMessageComposing($to)
    {
        $this->sendChatState($to, "composing");
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
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageImage($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
            $size = 5 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'image', $allowedExtensions, $storeURLmedia, $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'image', $fsize, $filepath, $to, $caption);
        }
    }

    /**
     * Send a location to the user/group.
     *
     * If no name is supplied, the receiver will see a large google maps thumbnail of the lat/long,
     * but NO name or url of the location.
     *
     * When a name supplied, a combined map thumbnail/name box is displayed.
     *
     * @param mixed  $to    The recipient(s) to send the location to.
     * @param float  $long  The longitude of the location, e.g. 54.31652.
     * @param float  $lat   The latitude of the location, e.g. -6.833496.
     * @param string $name  (Optional) A custom name for the specified location.
     * @param string $url   (Optional) A URL to attach to the specified location.
     * @return string       Message ID
     */
    public function sendMessageLocation($to, $long, $lat, $name = null, $url = null)
    {
        $mediaNode = new ProtocolNode("media",
            array(
                "type" => "location",
                "encoding" => "raw",
                "latitude" => $lat,
                "longitude" => $long,
                "name" => $name,
                "url" => $url
            ), null, null);

        $id = (is_array($to)) ? $this->sendBroadcast($to, $mediaNode, "media") : $this->sendMessageNode($to, $mediaNode);

        $this->waitForServer($id);

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
        $this->sendChatState($to, "paused");
    }

    protected function sendChatState($to, $state)
    {
        $node = new ProtocolNode("chatstate",
            array(
                "to" => $this->getJID($to)
            ), array(new ProtocolNode($state, null, null, null)), null);

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
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageVideo($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('3gp', 'mp4', 'mov', 'avi');
            $size = 20 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'video', $allowedExtensions, $storeURLmedia, $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'video', $fsize, $filepath, $to, $caption);
        }
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
        $messageNode = new ProtocolNode("presence", array("type" => "inactive"), null, "");
        $this->sendNode($messageNode);
    }

    /**
     * Send a pong to the WhatsApp server. I'm alive!
     *
     * @param string $msgid The id of the message.
     */
    public function sendPong($msgid)
    {
        $messageNode = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "id" => $msgid,
                "type" => "result"
            ), null, "");

        $this->sendNode($messageNode);
        $this->eventManager()->fire("onSendPong",
            array(
                $this->phoneNumber,
                $msgid
            ));
    }

    public function sendAvailableForChat($nickname = null)
    {
        $presence = array();
        if ($nickname) {
            //update nickname
            $this->name = $nickname;
        }

        $presence['name'] = $this->name;
        $node = new ProtocolNode("presence", $presence, null, "");
        $this->sendNode($node);
    }

    /**
     * Send presence status.
     *
     * @param string $type The presence status.
     */
    public function sendPresence($type = "active")
    {
        $node = new ProtocolNode("presence",
            array(
                "type" => $type
            ), null, "");

        $this->sendNode($node);
        $this->eventManager()->fire("onSendPresence",
            array(
                $this->phoneNumber,
                $type,
                $this->name
            ));
    }

    /**
     * Send presence subscription, automatically receive presence updates as long as the socket is open.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceSubscription($to)
    {
        $node = new ProtocolNode("presence", array("type" => "subscribe", "to" => $this->getJID($to)), null, "");
        $this->sendNode($node);
    }

    /**
     * Unsubscribe, will stop subscription.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceUnsubscription($to)
    {
        $node = new ProtocolNode("presence", array("type" => "unsubscribe", "to" => $this->getJID($to)), null, "");
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
    public function sendSetPrivacyBlockedList($blockedJids = array())
    {
        if (!is_array($blockedJids)) {
            $blockedJids = array($blockedJids);
        }

        $items = array();
        foreach ($blockedJids as $index => $jid) {
            $item = new ProtocolNode("item",
                array(
                    "type" => "jid",
                    "value" => $this->getJID($jid),
                    "action" => "deny",
                    "order" => $index + 1//WhatsApp stream crashes on zero index
                ), null, null);
            $items[] = $item;
        }

        $child = new ProtocolNode("list",
            array(
                "name" => "default"
            ), $items, null);

        $child2 = new ProtocolNode("query", null, array($child), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createIqId(),
                "xmlns" => "jabber:iq:privacy",
                "type" => "set"
            ), array($child2), null);

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

        $picture = new ProtocolNode("picture", null, null, null);

        $thumb = new ProtocolNode("picture",
            array(
                "type" => "preview"
            ), null, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "to" => $this->getJID($this->phoneNumber),
                "type" => "set",
                "xmlns" => "w:profile:picture"
            ), array($picture, $thumb), null);

        $this->sendNode($node);
    }

    /**
     * Set the recovery token for your account to allow you to retrieve your password at a later stage.
     *
     * @param  string $token A user generated token.
     */
    public function sendSetRecoveryToken($token)
    {
        $child = new ProtocolNode("pin",
            array(
                "xmlns" => "w:ch:p"
            ), null, $token);

        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createIqId(),
                "type" => "set",
                "to" => Constants::WHATSAPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
    }

    /**
     * Update the user status.
     *
     * @param string $txt The text of the message status to send.
     */
    public function sendStatusUpdate($txt)
    {
        $child = new ProtocolNode("status", null, null, $txt);
        $nodeID = $this->createIqId();
        $node = new ProtocolNode("iq",
            array(
                "to" => Constants::WHATSAPP_SERVER,
                "type" => "set",
                "id" => $nodeID,
                "xmlns" => "status"
            ), array($child), null);

        $this->sendNode($node);
        $this->waitForServer($nodeID);
        $this->eventManager()->fire("onSendStatusUpdate",
            array(
                $this->phoneNumber,
                $txt
            ));
    }

    /**
     * Send a vCard to the user/group.
     *
     * @param string $to    The recipient to send.
     * @param string $name  The contact name.
     * @param object $vCard The contact vCard to send.
     * @return string       Message ID
     */
    public function sendVcard($to, $name, $vCard)
    {
        $vCardNode = new ProtocolNode("vcard",
            array(
                "name" => $name
            ), null, $vCard);

        $mediaNode = new ProtocolNode("media",
            array(
                "type" => "vcard"
            ), array($vCardNode), "");

        // Return message ID. Make pull request for this.
        return $this->sendMessageNode($to, $mediaNode);
    }

    /**
     * Send a vCard to the user/group as Broadcast.
     *
     * @param array  $targets An array of recipients to send to.
     * @param string $name    The vCard contact name.
     * @param object $vCard   The contact vCard to send.
     * @return string         Message ID
     */
    public function sendBroadcastVcard($targets, $name, $vCard)
    {
        $vCardNode = new ProtocolNode("vcard",
            array(
                "name" => $name
            ), null, $vCard);

        $mediaNode = new ProtocolNode("media",
            array(
                "type" => "vcard"
            ), array($vCardNode), "");

        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $mediaNode, "media");
    }


    /**
     * Rejects a call
     *
     * @param array  $to      Phone number.
     * @param string $id      The main node id
     * @param string $callId  The call-id
     */
    public function rejectCall($to, $id, $callId)
    {
        $rejectNode = new ProtocolNode("reject",
            array(
              "call-id" => $callId
            ), null, null);

        $callNode = new ProtocolNode("call",
            array(
              "id" => $id,
              "to" => $this->getJID($to)
            ), array($rejectNode), null);

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
     * @param string $id The id of the node sent that we are awaiting acknowledgement of.
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
     * Authenticate with the WhatsApp Server.
     *
     * @return string Returns binary string
     */
    protected function authenticate()
    {
        $keys = KeyStream::GenerateKeys(base64_decode($this->password), $this->challengeData);
        $this->inputKey = new KeyStream($keys[2], $keys[3]);
        $this->outputKey = new KeyStream($keys[0], $keys[1]);
        $array = "\0\0\0\0" . $this->phoneNumber . $this->challengeData;// . time() . Constants::WHATSAPP_USER_AGENT . " MccMnc/" . str_pad($phone["mcc"], 3, "0", STR_PAD_LEFT) . "001";
        $response = $this->outputKey->EncodeMessage($array, 0, 4, strlen($array) - 4);
        return $response;
    }

    /**
     * Add the authentication nodes.
     *
     * @return ProtocolNode Returns an authentication node.
     */
    protected function createAuthNode()
    {
        $data = $this->createAuthBlob();
        $node = new ProtocolNode("auth", array(
            'mechanism' => 'WAUTH-2',
            'user'      => $this->phoneNumber
        ), null, $data);

        return $node;
    }

    protected function createAuthBlob()
    {
        if ($this->challengeData) {
            $key = wa_pbkdf2('sha1', base64_decode($this->password), $this->challengeData, 16, 20, true);
            $this->inputKey = new KeyStream($key[2], $key[3]);
            $this->outputKey = new KeyStream($key[0], $key[1]);
            $this->reader->setKey($this->inputKey);
            //$this->writer->setKey($this->outputKey);
            $array = "\0\0\0\0" . $this->phoneNumber . $this->challengeData . time();
            $this->challengeData = null;
            return $this->outputKey->EncodeMessage($array, 0, strlen($array), false);
        }
        return null;
    }

    /**
     * Add the auth response to protocoltreenode.
     *
     * @return ProtocolNode Returns a response node.
     */
    protected function createAuthResponseNode()
    {
        return new ProtocolNode("response", null, null, $this->authenticate());
    }

    /**
     * Add stream features.
     *
     * @return ProtocolNode Return itself.
     */
    protected function createFeaturesNode()
    {
        $readreceipts = new ProtocolNode("readreceipts", null, null, null);
        $groupsv2 = new ProtocolNode("groups_v2", null, null, null);
        $privacy = new ProtocolNode("privacy", null, null, null);
        $presencev2 = new ProtocolNode("presence", null, null, null);
        $parent = new ProtocolNode("stream:features", null, array($readreceipts, $groupsv2, $privacy, $presencev2), null);

        return $parent;
    }

    /**
     * Create a unique msg id.
     *
     * @return string
     *   A message id string.
     */
    protected function createMsgId()
    {
        return $this->messageId . dechex($this->messageCounter++);
    }

    /**
     * iq id
     *
     * @return string
     *    Iq id
     */
    protected function createIqId()
    {
        $iqId = $this->iqCounter;
        $this->iqCounter++;

        return dechex($iqId);
    }

    /**
     * Print a message to the debug console.
     *
     * @param  mixed $debugMsg The debug message.
     * @return bool
     */
    protected function debugPrint($debugMsg)
    {
        if ($this->debug) {
            if (is_array($debugMsg) || is_object($debugMsg)) {
                print_r($debugMsg);
            }
            else {
                echo $debugMsg;
            }
            return true;
        }

        return false;
    }

    /**
     * Dissect country code from phone number.
     *
     * @return array
     *   An associative array with country code and phone number.
     *   - country: The detected country name.
     *   - cc: The detected country code (phone prefix).
     *   - phone: The phone number.
     *   - ISO3166: 2-Letter country code
     *   - ISO639: 2-Letter language code
     *   Return false if country code is not found.
     */
    protected function dissectPhone()
    {
        if (($handle = fopen(dirname(__FILE__).'/countries.csv', 'rb')) !== false) {
            while (($data = fgetcsv($handle, 1000)) !== false) {
                if (strpos($this->phoneNumber, $data[1]) === 0) {
                    // Return the first appearance.
                    fclose($handle);

                    $mcc = explode("|", $data[2]);
                    $mcc = $mcc[0];

                    //hook:
                    //fix country code for North America
                    if ($data[1][0] == "1") {
                        $data[1] = "1";
                    }

                    $phone = array(
                        'country' => $data[0],
                        'cc' => $data[1],
                        'phone' => substr($this->phoneNumber, strlen($data[1]), strlen($this->phoneNumber)),
                        'mcc' => $mcc,
                        'ISO3166' => @$data[3],
                        'ISO639' => @$data[4],
                        'mnc' => $data[5]
                    );

                    $this->eventManager()->fire("onDissectPhone",
                        array(
                            $this->phoneNumber,
                            $phone['country'],
                            $phone['cc'],
                            $phone['phone'],
                            $phone['mcc'],
                            $phone['ISO3166'],
                            $phone['ISO639'],
                            $phone['mnc']
                        )
                    );

                    return $phone;
                }
            }
            fclose($handle);
        }

        $this->eventManager()->fire("onDissectPhoneFailed",
            array(
                $this->phoneNumber
            ));

        return false;
    }

    /**
     * Detects mnc from specified carrier.
     *
     * @param string $lc          LangCode
     * @param string $carrierName Name of the carrier
     * @return string
     *
     * Returns mnc value
     */
    protected function detectMnc($lc, $carrierName)
    {
        $fp = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'networkinfo.csv', 'r');
        $mnc = null;

        while ($data = fgetcsv($fp, 0, ',')) {
            if ($data[4] === $lc && $data[7] === $carrierName) {
                $mnc = $data[2];
                break;
            }
        }

        if ($mnc == null) {
            $mnc = '000';
        }

        fclose($fp);

        return $mnc;
    }

    /**
     * Send the nodes to the WhatsApp server to log in.
     *
     * @throws Exception
     */
    protected function doLogin()
    {
        if ($this->isLoggedIn()) {
            return true;
        }

        $this->writer->resetKey();
        $this->reader->resetKey();
        $resource = Constants::WHATSAPP_DEVICE . '-' . Constants::WHATSAPP_VER . '-' . Constants::PORT;
        $data = $this->writer->StartStream(Constants::WHATSAPP_SERVER, $resource);
        $feat = $this->createFeaturesNode();
        $auth = $this->createAuthNode();
        $this->sendData($data);
        $this->sendNode($feat);
        $this->sendNode($auth);

        $this->pollMessage();
        $this->pollMessage();
        $this->pollMessage();

        if ($this->challengeData != null) {
            $data = $this->createAuthResponseNode();
            $this->sendNode($data);
            $this->reader->setKey($this->inputKey);
            $this->writer->setKey($this->outputKey);
            while (!$this->pollMessage()) {};
        }

        if ($this->loginStatus === Constants::DISCONNECTED_STATUS) {
            throw new LoginFailureException();
        }

        $this->eventManager()->fire("onLogin",
            array(
                $this->phoneNumber
            ));
        $this->sendAvailableForChat();
        $this->messageId = substr(base64_encode(mcrypt_create_iv(64, MCRYPT_DEV_URANDOM)), 0, 12);

        return true;
    }

    /**
     * Have we an active connection with WhatsAPP AND a valid login already?
     *
     * @return bool
     */
    protected function isLoggedIn(){
        //If you aren't connected you can't be logged in! ($this->isConnected())
        //We are connected - but are we logged in? (the rest)
        return ($this->isConnected() && !empty($this->loginStatus) && $this->loginStatus === Constants::CONNECTED_STATUS);
    }

    /**
     * Create an identity string
     *
     * @param  mixed $identity_file IdentityFile (optional).
     * @return string           Correctly formatted identity
     *
     * @throws Exception        Error when cannot write identity data to file.
     */
    protected function buildIdentity($identity_file = false)
    {
        if ($identity_file === false)
            $identity_file = sprintf('%s%s%sid.%s.dat', __DIR__, DIRECTORY_SEPARATOR, Constants::DATA_FOLDER . DIRECTORY_SEPARATOR, $this->phoneNumber);

        if (is_readable($identity_file)) {
            $data = urldecode(file_get_contents($identity_file));
            $length = strlen($data);

            if ($length == 20 || $length == 16) {
                return $data;
            }
        }

        $bytes = strtolower(openssl_random_pseudo_bytes(20));

        if (file_put_contents($identity_file, urlencode($bytes)) === false) {
            throw new Exception('Unable to write identity file to ' . $identity_file);
        }

        return $bytes;
    }

    public function sendSync(array $numbers, array $deletedNumbers = null, $syncType = 4, $index = 0, $last = true)
    {
        $users = array();

        for ($i=0; $i<count($numbers); $i++) { // number must start with '+' if international contact
            $users[$i] = new ProtocolNode("user", null, null, (substr($numbers[$i], 0, 1) != '+')?('+' . $numbers[$i]):($numbers[$i]));
        }

        if ($deletedNumbers != null || count($deletedNumbers)) {
            for ($j=0; $j<count($deletedNumbers); $j++, $i++) {
                $users[$i] = new ProtocolNode("user", array("jid" => $this->getJID($deletedNumbers[$j]), "type" => "delete"), null, null);
            }
        }

        switch($syncType)
        {
            case 0:
                $mode = "full";
                $context = "registration";
                break;
            case 1:
                $mode = "full";
                $context = "interactive";
                break;
            case 2:
                $mode = "full";
                $context = "background";
                break;
            case 3:
                $mode = "delta";
                $context = "interactive";
                break;
            case 4:
                $mode = "delta";
                $context = "background";
                break;
            case 5:
                $mode = "query";
                $context = "interactive";
                break;
            case 6:
                $mode = "chunked";
                $context = "registration";
                break;
            case 7:
                $mode = "chunked";
                $context = "interactive";
                break;
            case 8:
                $mode = "chunked";
                $context = "background";
                break;
            default:
                $mode = "delta";
                $context = "background";
        }

        $id = $this->createIqId();

        $node = new ProtocolNode("iq",
            array(
                "id" => $id,
                "xmlns" => "urn:xmpp:whatsapp:sync",
                "type" => "get"
            ), array(
                new ProtocolNode("sync",
                    array(
                        "mode" => $mode,
                        "context" => $context,
                        "sid" => "".((time() + 11644477200) * 10000000),
                        "index" => "".$index,
                        "last" => $last ? "true" : "false"
                    ), $users, null)
            ), null);

        $this->sendNode($node);
        $this->waitForServer($id);

        return $id;
    }

    public function setMessageStore(MessageStoreInterface $messageStore)
    {
        $this->messageStore = $messageStore;
    }

    /**
     * Process number/jid and turn it into a JID if necessary
     *
     * @param string $number
     *  Number to process
     * @return string
     */
    protected function getJID($number)
    {
        if (!stristr($number, '@')) {
            //check if group message
            if (stristr($number, '-')) {
                //to group
                $number .= "@" . Constants::WHATSAPP_GROUP_SERVER;
            } else {
                //to normal user
                $number .= "@" . Constants::WHATSAPP_SERVER;
            }
        }

        return $number;
    }

    /**
     * Retrieves media file and info from either a URL or localpath
     *
     * @param string  $filepath     The URL or path to the mediafile you wish to send
     * @param integer $maxsizebytes The maximum size in bytes the media file can be. Default 5MB
     *
     * @return bool  false if file information can not be obtained.
     */
    protected function getMediaFile($filepath, $maxsizebytes = 5242880)
    {
        if (filter_var($filepath, FILTER_VALIDATE_URL) !== false) {
            $this->mediaFileInfo = array();
            $this->mediaFileInfo['url'] = $filepath;

            $media = file_get_contents($filepath);
            $this->mediaFileInfo['filesize'] = strlen($media);

            if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                $this->mediaFileInfo['filepath'] = tempnam(__DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . Constants::MEDIA_FOLDER, 'WHA');
                file_put_contents($this->mediaFileInfo['filepath'], $media);
                $this->mediaFileInfo['filemimetype']  = get_mime($this->mediaFileInfo['filepath']);
                $this->mediaFileInfo['fileextension'] = getExtensionFromMime($this->mediaFileInfo['filemimetype']);
                return true;
            } else {
                return false;
            }
        } else if (file_exists($filepath)) {
            //Local file
            $this->mediaFileInfo['filesize'] = filesize($filepath);
            if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                $this->mediaFileInfo['filepath']      = $filepath;
                $this->mediaFileInfo['fileextension'] = pathinfo($filepath, PATHINFO_EXTENSION);
                $this->mediaFileInfo['filemimetype']  = get_mime($filepath);
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Get a decoded JSON response from Whatsapp server
     *
     * @param  string $host  The host URL
     * @param  array  $query A associative array of keys and values to send to server.
     *
     * @return null|object   NULL if the json cannot be decoded or if the encoded data is deeper than the recursion limit
     */
    protected function getResponse($host, $query)
    {
        // Build the url.
        $url = $host . '?' . http_build_query($query);

        // Open connection.
        $ch = curl_init();

        // Configure the connection.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, Constants::WHATSAPP_USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/json'));
        // This makes CURL accept any peer!
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Get the response.
        $response = curl_exec($ch);

        // Close the connection.
        curl_close($ch);

        return json_decode($response);
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
     * @param      $data
     * @param bool $autoReceipt
     * @param      $type
     *
     * @throws Exception
     */
    protected function processInboundData($data, $autoReceipt = true, $type = "read")
    {
        $node = $this->reader->nextTree($data);
        if ($node != null) {
            $this->processInboundDataNode($node, $autoReceipt, $type);
        }
    }

    /**
     * Will process the data from the server after it's been decrypted and parsed.
     *
     * This also provides a convenient method to use to unit test the event framework.
     * @param ProtocolNode $node
     * @param bool         $autoReceipt
     * @param              $type
     *
     * @throws Exception
     */
    protected function processInboundDataNode(ProtocolNode $node, $autoReceipt = true, $type = "read") {
        $this->debugPrint($node->nodeString("rx  ") . "\n");
        $this->serverReceivedId = $node->getAttribute('id');

        if ($node->getTag() == "challenge") {
            $this->processChallenge($node);
        } elseif ($node->getTag() == "failure") {
            $this->loginStatus = Constants::DISCONNECTED_STATUS;
            $this->eventManager()->fire("onLoginFailed",
                array(
                    $this->phoneNumber,
                    $node->getChild(0)->getTag()
                ));
        } elseif ($node->getTag() == "success") {
            if ($node->getAttribute("status") == "active") {
                $this->loginStatus = Constants::CONNECTED_STATUS;
                $challengeData = $node->getData();
                file_put_contents($this->challengeFilename, $challengeData);
                $this->writer->setKey($this->outputKey);

                $this->eventManager()->fire("onLoginSuccess",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute("kind"),
                        $node->getAttribute("status"),
                        $node->getAttribute("creation"),
                        $node->getAttribute("expiration")
                    ));
            } elseif ($node->getAttribute("status") == "expired") {
                $this->eventManager()->fire("onAccountExpired",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute("kind"),
                        $node->getAttribute("status"),
                        $node->getAttribute("creation"),
                        $node->getAttribute("expiration")
                    ));
            }
        } elseif ($node->getTag() == 'ack' && $node->getAttribute("class") == "message") {
            $this->eventManager()->fire("onMessageReceivedServer",
                array(
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('class'),
                    $node->getAttribute('t')
                ));
        } elseif ($node->getTag() == 'receipt') {
            if ($node->hasChild("list")) {
                foreach ($node->getChild("list")->getChildren() as $child) {
                    $this->eventManager()->fire("onMessageReceivedClient",
                        array(
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $child->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('participant')
                        ));
                }
            }

            $this->eventManager()->fire("onMessageReceivedClient",
                array(
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('type'),
                    $node->getAttribute('t'),
                    $node->getAttribute('participant')
                ));

            $this->sendAck($node, 'receipt');
        }
        if ($node->getTag() == "message") {
            array_push($this->messageQueue, $node);

            if ($node->hasChild('x') && $this->lastId == $node->getAttribute('id')) {
                $this->sendNextMessage();
            }
            if ($this->newMsgBind  && ($node->getChild('body') || $node->getChild('media'))) {
                $this->newMsgBind->process($node);
            }
            if ($node->getAttribute("type") == "text" && $node->getChild('body') != null) {
                $author = $node->getAttribute("participant");
                if ($autoReceipt) {
                    $this->sendReceipt($node, $type, $author);
                }
                if ($author == "") {
                    //private chat message
                    $this->eventManager()->fire("onGetMessage",
                        array(
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute("notify"),
                            $node->getChild("body")->getData()
                        ));

                    if ($this->messageStore !== null) {
                        $this->messageStore->saveMessage(ExtractNumber($node->getAttribute('from')), $this->phoneNumber, $node->getChild("body")->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
                    }
                } else {
                    //group chat message
                    $this->eventManager()->fire("onGetGroupMessage",
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
                    if ($this->messageStore !== null) {
                        $this->messageStore->saveMessage($author, $node->getAttribute('from'), $node->getChild("body")->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
                    }
                }

            }
            if ($node->getAttribute("type") == "text" && $node->getChild(0)->getTag() == 'enc') {
                // TODO
                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getAttribute("type") == "media" && $node->getChild('media') != null) {
                if ($node->getChild("media")->getAttribute('type') == 'image') {

                    if ($node->getAttribute("participant") == null) {
                        $this->eventManager()->fire("onGetImage",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    } else {
                        $this->eventManager()->fire("onGetGroupImage",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    }
                } elseif ($node->getChild("media")->getAttribute('type') == 'video') {
                    if ($node->getAttribute("participant") == null) {
                        $this->eventManager()->fire("onGetVideo",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('duration'),
                                $node->getChild("media")->getAttribute('vcodec'),
                                $node->getChild("media")->getAttribute('acodec'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getAttribute('fps'),
                                $node->getChild("media")->getAttribute('vbitrate'),
                                $node->getChild("media")->getAttribute('asampfreq'),
                                $node->getChild("media")->getAttribute('asampfmt'),
                                $node->getChild("media")->getAttribute('abitrate')
                            ));
                    } else {
                        $this->eventManager()->fire("onGetGroupVideo",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('duration'),
                                $node->getChild("media")->getAttribute('vcodec'),
                                $node->getChild("media")->getAttribute('acodec'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getAttribute('fps'),
                                $node->getChild("media")->getAttribute('vbitrate'),
                                $node->getChild("media")->getAttribute('asampfreq'),
                                $node->getChild("media")->getAttribute('asampfmt'),
                                $node->getChild("media")->getAttribute('abitrate')
                            ));
                    }
                } elseif ($node->getChild("media")->getAttribute('type') == 'audio') {
                    $author = $node->getAttribute("participant");
                    $this->eventManager()->fire("onGetAudio",
                        array(
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild("media")->getAttribute('size'),
                            $node->getChild("media")->getAttribute('url'),
                            $node->getChild("media")->getAttribute('file'),
                            $node->getChild("media")->getAttribute('mimetype'),
                            $node->getChild("media")->getAttribute('filehash'),
                            $node->getChild("media")->getAttribute('seconds'),
                            $node->getChild("media")->getAttribute('acodec'),
                            $author,
                        ));
                } elseif ($node->getChild("media")->getAttribute('type') == 'vcard') {
                    if ($node->getChild("media")->hasChild('vcard')) {
                        $name = $node->getChild("media")->getChild("vcard")->getAttribute('name');
                        $data = $node->getChild("media")->getChild("vcard")->getData();
                    } else {
                        $name = "NO_NAME";
                        $data = $node->getChild("media")->getData();
                    }
                    $author = $node->getAttribute("participant");

                    $this->eventManager()->fire("onGetvCard",
                        array(
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $name,
                            $data,
                            $author
                        ));
                } elseif ($node->getChild("media")->getAttribute('type') == 'location') {
                    $url = $node->getChild("media")->getAttribute('url');
                    $name = $node->getChild("media")->getAttribute('name');
                    $author = $node->getAttribute("participant");

                    $this->eventManager()->fire("onGetLocation",
                        array(
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $name,
                            $node->getChild("media")->getAttribute('longitude'),
                            $node->getChild("media")->getAttribute('latitude'),
                            $url,
                            $node->getChild("media")->getData(),
                            $author
                        ));
                }

                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getChild('received') != null) {
                $this->eventManager()->fire("onMessageReceivedClient",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('participant')
                    ));
            }
        }
        if ($node->getTag() == "presence" && $node->getAttribute("status") == "dirty") {
            //clear dirty
            $categories = array();
            if (count($node->getChildren()) > 0) {
                foreach ($node->getChildren() as $child) {
                    if ($child->getTag() == "category") {
                        $categories[] = $child->getAttribute("name");
                    }
                }
            }
            $this->sendClearDirty($categories);
        }
        if (strcmp($node->getTag(), "presence") == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), "-") === false) {
            $presence = array();
            if ($node->getAttribute('type') == null) {
                $this->eventManager()->fire("onPresenceAvailable",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                    ));
            } else {
                $this->eventManager()->fire("onPresenceUnavailable",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('last')
                    ));
            }
        }
        if ($node->getTag() == "presence"
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), "-") !== false
            && $node->getAttribute('type') != null) {
            $groupId = $this->parseJID($node->getAttribute('from'));
            if ($node->getAttribute('add') != null) {
                $this->eventManager()->fire("onGroupsParticipantsAdd",
                    array(
                        $this->phoneNumber,
                        $groupId,
                        $this->parseJID($node->getAttribute('add'))
                    ));
            } elseif ($node->getAttribute('remove') != null) {
                $this->eventManager()->fire("onGroupsParticipantsRemove",
                    array(
                        $this->phoneNumber,
                        $groupId,
                        $this->parseJID($node->getAttribute('remove'))
                    ));
            }
        }
        if (strcmp($node->getTag(), "chatstate") == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), "-") === false) {
            if($node->getChild(0)->getTag() == "composing"){
                $this->eventManager()->fire("onMessageComposing",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        "composing",
                        $node->getAttribute('t')
                    ));
            } else {
                $this->eventManager()->fire("onMessagePaused",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        "paused",
                        $node->getAttribute('t')
                    ));
            }
        }
        if ($node->getTag() == "iq"
            && $node->getAttribute('type') == "get"
            && $node->getAttribute('xmlns') == "urn:xmpp:ping") {
            $this->eventManager()->fire("onPing",
                array(
                    $this->phoneNumber,
                    $node->getAttribute('id')
                ));
            $this->sendPong($node->getAttribute('id'));
        }
        if ($node->getTag() == "iq"
            && $node->getChild("sync") != null) {

            //sync result
            $sync = $node->getChild('sync');
            $existing = $sync->getChild("in");
            $nonexisting = $sync->getChild("out");

            //process existing first
            $existingUsers = array();
            if (!empty($existing)) {
                foreach ($existing->getChildren() as $child) {
                    $existingUsers[$child->getData()] = $child->getAttribute("jid");
                }
            }

            //now process failed numbers
            $failedNumbers = array();
            if (!empty($nonexisting)) {
                foreach ($nonexisting->getChildren() as $child) {
                    $failedNumbers[] = str_replace('+', '', $child->getData());
                }
            }

            $index = $sync->getAttribute("index");

            $result = new SyncResult($index, $sync->getAttribute("sid"), $existingUsers, $failedNumbers);

            $this->eventManager()->fire("onGetSyncResult",
                array(
                    $result
                ));
        }
        if ($node->getTag() == "receipt") {
            $this->eventManager()->fire("onGetReceipt",
                array(
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('offline'),
                    $node->getAttribute('retry')
                ));
        }
        if ($node->getTag() == "iq"
            && $node->getAttribute('type') == "result") {
            if ($node->getChild("query") != null) {
                if (isset($this->nodeId['privacy']) && ($this->nodeId['privacy'] == $node->getAttribute('id'))) {
                    $listChild = $node->getChild(0)->getChild(0);
                    foreach ($listChild->getChildren() as $child) {
                        $blockedJids[] = $child->getAttribute('value');
                    }
                    $this->eventManager()->fire("onGetPrivacyBlockedList",
                        array(
                            $this->phoneNumber,
                            $blockedJids
                        ));
                    return;
                }
                $this->eventManager()->fire("onGetRequestLastSeen",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getChild(0)->getAttribute('seconds')
                    ));
            }
            if ($node->getChild("props") != null) {
                //server properties
                $props = array();
                foreach($node->getChild(0)->getChildren() as $child) {
                    $props[$child->getAttribute("name")] = $child->getAttribute("value");
                }
                $this->eventManager()->fire("onGetServerProperties",
                    array(
                        $this->phoneNumber,
                        $node->getChild(0)->getAttribute("version"),
                        $props
                    ));
            }
            if ($node->getChild("picture") != null) {
                $this->eventManager()->fire("onGetProfilePicture",
                    array(
                        $this->phoneNumber,
                        $node->getAttribute("from"),
                        $node->getChild("picture")->getAttribute("type"),
                        $node->getChild("picture")->getData()
                    ));
            }
            if ($node->getChild("media") != null || $node->getChild("duplicate") != null) {
                $this->processUploadResponse($node);
            }
            if (strpos($node->getAttribute("from"), Constants::WHATSAPP_GROUP_SERVER) !== false)  {
                //There are multiple types of Group reponses. Also a valid group response can have NO children.
                //Events fired depend on text in the ID field.
                $groupList = array();
                $groupNodes = array();
                if ($node->getChild(0) != null && $node->getChild(0)->getChildren() != null) {
                    foreach ($node->getChild(0)->getChildren() as $child) {
                        $groupList[] = $child->getAttributes();
                        $groupNodes[] = $child;
                    }
                }
                if (isset($this->nodeId['groupcreate']) && ($this->nodeId['groupcreate'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getAttribute('id');
                    $this->eventManager()->fire("onGroupsChatCreate",
                        array(
                            $this->phoneNumber,
                            $this->groupId
                        ));
                }
                if (isset($this->nodeId['leavegroup']) && ($this->nodeId['leavegroup'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getChild(0)->getAttribute('id');
                    $this->eventManager()->fire("onGroupsChatEnd",
                        array(
                            $this->phoneNumber,
                            $this->groupId
                        ));
                }
                if (isset($this->nodeId['getgroups']) && ($this->nodeId['getgroups'] == $node->getAttribute('id'))) {
                    $this->eventManager()->fire("onGetGroups",
                        array(
                            $this->phoneNumber,
                            $groupList
                        ));
                    //getGroups returns a array of nodes which are exactly the same as from getGroupV2Info
                    //so lets call this event, we have all data at hand, no need to call getGroupV2Info for every
                    //group we are interested
                    foreach ($groupNodes AS $groupNode) {
                        $this->handleGroupV2InfoResponse($groupNode, true);
                    }

                }
            if (isset($this->nodeId['get_groupv2_info']) && ($this->nodeId['get_groupv2_info'] == $node->getAttribute('id'))) {
                $groupChild = $node->getChild(0);
                if ($groupChild != null) {
                    $this->handleGroupV2InfoResponse($groupChild);
                }
            }
          }
            if (isset($this->nodeId['get_lists']) && ($this->nodeId['get_lists'] == $node->getAttribute('id'))) {
                $broadcastLists = array();
                if ($node->getChild(0) != null) {
                    $childArray = $node->getChildren();
                    foreach ($childArray as $list) {
                        if ($list->getChildren() != null) {
                            foreach ( $list->getChildren() as $sublist) {
                                $id = $sublist->getAttribute("id");
                                $name = $sublist->getAttribute("name");
                                $broadcastLists[$id]['name'] = $name;
                                $recipients = array();
                                foreach ($sublist->getChildren() as $recipient) {
                                    array_push($recipients, $recipient->getAttribute('jid'));
                                }
                                $broadcastLists[$id]['recipients'] = $recipients;
                            }
                        }
                    }
                }
                $this->eventManager()->fire("onGetBroadcastLists",
                    array(
                        $this->phoneNumber,
                        $broadcastLists
                    ));
            }
            if ($node->getChild("pricing") != null) {
                $this->eventManager()->fire("onGetServicePricing",
                    array(
                        $this->phoneNumber,
                        $node->getChild(0)->getAttribute("price"),
                        $node->getChild(0)->getAttribute("cost"),
                        $node->getChild(0)->getAttribute("currency"),
                        $node->getChild(0)->getAttribute("expiration")
                    ));
            }
            if ($node->getChild("extend") != null) {
                $this->eventManager()->fire("onGetExtendAccount",
                    array(
                        $this->phoneNumber,
                        $node->getChild("account")->getAttribute("kind"),
                        $node->getChild("account")->getAttribute("status"),
                        $node->getChild("account")->getAttribute("creation"),
                        $node->getChild("account")->getAttribute("expiration")
                    ));
            }
            if ($node->getChild("normalize") != null) {
                $this->eventManager()->fire("onGetNormalizedJid",
                    array(
                        $this->phoneNumber,
                        $node->getChild(0)->getAttribute("result")
                    ));
            }
            if ($node->getChild("status") != null) {
                $child = $node->getChild("status");
                foreach($child->getChildren() as $status)
                {
                    $this->eventManager()->fire("onGetStatus",
                        array(
                            $this->phoneNumber,
                            $status->getAttribute("jid"),
                            "requested",
                            $node->getAttribute("id"),
                            $status->getAttribute("t"),
                            $status->getData()
                        ));
                }
            }
        }
        if ($node->getTag() == "iq" && $node->getAttribute('type') == "error") {
            $errorType=null;
            foreach ($this->nodeId AS $type => $nodeID) {
                if ($nodeID == $node->getAttribute('id')) {
                    $errorType = $type;
                    break;
                }
            }
            $this->eventManager()->fire("onGetError",
                array(
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getChild(0),
                    $errorType
                ));
        }

        if ($node->getTag() == "message" && $node->getAttribute('type') == "media" && $node->getChild(0)->getAttribute('type') == "image" ) {
            $msgId = $this->createIqId();

            $ackNode = new ProtocolNode("ack",
                array(
                    "url" => $node->getChild(0)->getAttribute('url')
                ), null, null);

            $iqNode = new ProtocolNode("iq",
                array(
                    "id" => $msgId,
                    "xmlns" => "w:m",
                    "type" => "set",
                    "to" => Constants::WHATSAPP_SERVER
                ), array($ackNode), null);

            $this->sendNode($iqNode);
        }

        $children = $node->getChild(0);
        if ($node->getTag() == "stream:error" && !empty($children) && $node->getChild(0)->getTag() == "system-shutdown")
        {
            $this->eventManager()->fire("onStreamError",
                array(
                    $node->getChild(0)->getTag()
                ));
        }

        if ($node->getTag() == "stream:error") {
            $this->eventManager()->fire("onStreamError",
                array(
                    $node->getChild(0)->getTag()
                ));
        }

        if ($node->getTag() == "notification") {
            $name = $node->getAttribute("notify");
            $type = $node->getAttribute("type");
            switch($type)
            {
                case "status":
                    $this->eventManager()->fire("onGetStatus",
                        array(
                            $this->phoneNumber, //my number
                            $node->getAttribute("from"),
                            $node->getChild(0)->getTag(),
                            $node->getAttribute("id"),
                            $node->getAttribute("t"),
                            $node->getChild(0)->getData()
                        ));
                    break;
                case "picture":
                    if ($node->hasChild('set')) {
                        $this->eventManager()->fire("onProfilePictureChanged",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('t')
                            ));
                    } else if ($node->hasChild('delete')) {
                        $this->eventManager()->fire("onProfilePictureDeleted",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('t')
                            ));
                    }
                    //TODO
                    break;
                case "contacts":
                    $notification = $node->getChild(0)->getTag();
                    if ($notification == 'add')
                    {
                        $this->eventManager()->fire("onNumberWasAdded",
                            array(
                                $this->phoneNumber,
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    elseif ($notification == 'remove')
                    {
                        $this->eventManager()->fire("onNumberWasRemoved",
                            array(
                                $this->phoneNumber,
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    elseif ($notification == 'update')
                    {
                        $this->eventManager()->fire("onNumberWasUpdated",
                            array(
                                $this->phoneNumber,
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    break;
                case "encrypt":
                    $value = $node->getChild(0)->getAttribute('value');
                    if (is_numeric($value)) {
                        $this->eventManager()->fire("onGetKeysLeft",
                            array(
                                $this->phoneNumber,
                                $node->getChild(0)->getAttribute('value')
                            ));
                    }
                    else {
                        echo "Corrupt Stream: value " . $value . "is not numeric";
                    }
                    break;
                case "w:gp2":
                    if ($node->hasChild('remove')) {
                        if ($node->getChild(0)->hasChild('participant'))
                            $this->eventManager()->fire("onGroupsParticipantsRemove",
                                array(
                                    $this->phoneNumber,
                                    $node->getAttribute('from'),
                                    $node->getChild(0)->getChild(0)->getAttribute('jid')
                                ));
                    } else if ($node->hasChild('add')) {
                        $this->eventManager()->fire("onGroupsParticipantsAdd",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('jid')
                            ));
                    }
                    else if ($node->hasChild('create')) {
                        $groupMembers = array();
                        foreach ($node->getChild(0)->getChild(0)->getChildren() AS $cn) {
                            $groupMembers[] = $cn->getAttribute('jid');
                        }
                        $this->eventManager()->fire("onGroupisCreated",
                            array(
                                $this->phoneNumber,
                                $node->getChild(0)->getChild(0)->getAttribute('creator'),
                                $node->getChild(0)->getChild(0)->getAttribute('id'),
                                $node->getChild(0)->getChild(0)->getAttribute('subject'),
                                $node->getAttribute('participant'),
                                $node->getChild(0)->getChild(0)->getAttribute('creation'),
                                $groupMembers
                            ));
                    }
                    else if ($node->hasChild('subject')) {
                        $this->eventManager()->fire("onGetGroupsSubject",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('t'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('notify'),
                                $node->getChild(0)->getAttribute('subject')
                            ));
                    }
                    else if ($node->hasChild('promote')) {
                        $promotedJIDs = array();
                        foreach ($node->getChild(0)->getChildren() AS $cn) {
                            $promotedJIDs[] = $cn->getAttribute('jid');
                        }
                        $this->eventManager()->fire("onGroupsParticipantsPromote",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),        //Group-JID
                                $node->getAttribute('t'),           //Time
                                $node->getAttribute('participant'), //Issuer-JID
                                $node->getAttribute('notify'),      //Issuer-Name
                                $promotedJIDs,
                            )
                        );
                    }
                    else if ($node->hasChild('modify')) {
                        $this->eventManager()->fire("onGroupsParticipantChangedNumber",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('t'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('notify'),
                                $node->getChild(0)->getChild(0)->getAttribute('jid')
                            )
                        );
                    }
                    break;
                case "account":
                    if (($node->getChild(0)->getAttribute('author')) == "")
                        $author = "Paypal";
                    else
                        $author = $node->getChild(0)->getAttribute('author');
                    $this->eventManager()->fire("onPaidAccount",
                        array(
                            $this->phoneNumber,
                            $author,
                            $node->getChild(0)->getChild(0)->getAttribute('kind'),
                            $node->getChild(0)->getChild(0)->getAttribute('status'),
                            $node->getChild(0)->getChild(0)->getAttribute('creation'),
                            $node->getChild(0)->getChild(0)->getAttribute('expiration')
                        ));
                    break;
                case "features":
                    if ($node->getChild(0)->getChild(0) == "encrypt") {
                        $this->eventManager()->fire("onGetFeature",
                            array(
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('value'),
                            ));
                    }
                    break;
                case "web":
                      if (($node->getChild(0)->getTag() == 'action') && ($node->getChild(0)->getAttribute('type') == 'sync'))
                      {
                            $data = $node->getChild(0)->getChildren();
                            $this->eventManager()->fire("onWebSync",
                                array(
                                    $this->phoneNumber,
                                    $node->getAttribute('from'),
                                    $node->getAttribute('id'),
                                    $data[0]->getData(),
                                    $data[1]->getData(),
                                    $data[2]->getData()
                            ));
                      }
                    break;
                default:
                    throw new Exception("Method $type not implemented");
            }
            $this->sendAck($node, 'notification');
        }
        if ($node->getTag() == "call")
        {
            if ($node->getChild(0)->getTag() == "offer")
            {
                $callId = $node->getChild(0)->getAttribute("call-id");
                $this->sendReceipt($node, null, null, $callId);

                $this->eventManager()->fire("onCallReceived",
                array(
                    $this->phoneNumber,
                    $node->getAttribute("from"),
                    $node->getAttribute("id"),
                    $node->getAttribute("notify"),
                    $node->getAttribute("t"),
                    $node->getChild(0)->getAttribute("call-id")
                ));
            }
            else
            {
                $this->sendAck($node, 'call');
            }

        }
        if ($node->getTag() == "ib")
        {
            foreach($node->getChildren() as $child)
            {
                switch($child->getTag())
                {
                    case "dirty":
                        $this->sendClearDirty(array($child->getAttribute("type")));
                        break;
                    case "account":
                        $this->eventManager()->fire("onPaymentRecieved",
                        array(
                            $this->phoneNumber,
                            $child->getAttribute("kind"),
                            $child->getAttribute("status"),
                            $child->getAttribute("creation"),
                            $child->getAttribute("expiration")
                        ));
                        break;
                    case "offline":

                        break;
                    default:
                        throw new Exception("ib handler for " . $child->getTag() . " not implemented");
                }
            }
        }

        // Disconnect socket on stream error.
        if ($node->getTag() == "stream:error")
        {
            $this->disconnect();
        }
    }

    /**
     * @param $node  ProtocolNode
     * @param $class string
     */
    protected function sendAck($node, $class)
    {
        $from = $node->getAttribute("from");
        $to = $node->getAttribute("to");
        $participant = $node->getAttribute("participant");
        $id = $node->getAttribute("id");
        $type = $node->getAttribute("type");

        $attributes = array();
        if ($to)
            $attributes["from"] = $to;
        if ($participant)
            $attributes["participant"] = $participant;
        $attributes["to"] = $from;
        $attributes["class"] = $class;
        $attributes["id"] = $id;
        if ($type != null)
            $attributes["type"] = $type;

        $ack = new ProtocolNode("ack", $attributes, null, null);

        $this->sendNode($ack);
    }

    /**
     * Process and save media image.
     *
     * @param ProtocolNode $node ProtocolNode containing media
     */
    protected function processMediaImage($node)
    {
        $media = $node->getChild("media");

        if ($media != null) {
            $filename = $media->getAttribute("file");
            $url = $media->getAttribute("url");

            //save thumbnail
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . Constants::MEDIA_FOLDER . DIRECTORY_SEPARATOR . 'thumb_' . $filename, $media->getData());
            //download and save original
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . Constants::MEDIA_FOLDER . DIRECTORY_SEPARATOR . $filename, file_get_contents($url));
        }
    }

    /**
     * Processes received picture node.
     *
     * @param ProtocolNode $node ProtocolNode containing the picture
     */
    protected function processProfilePicture($node)
    {
        $pictureNode = $node->getChild("picture");

        if ($pictureNode != null) {
            if ($pictureNode->getAttribute("type") == "preview") {
                $filename = __DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . Constants::PICTURES_FOLDER . DIRECTORY_SEPARATOR . 'preview_' . $node->getAttribute('from') . 'jpg';
            } else {
                $filename = __DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . Constants::PICTURES_FOLDER . DIRECTORY_SEPARATOR . $node->getAttribute('from') . '.jpg';
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
        if (isset($this->mediaFileInfo['url'])) {
            if ($storeURLmedia) {
                if (is_file($this->mediaFileInfo['filepath'])) {
                    rename($this->mediaFileInfo['filepath'], $this->mediaFileInfo['filepath'].'.'.$this->mediaFileInfo['fileextension']);
                }
            } else {
                if (is_file($this->mediaFileInfo['filepath'])) {
                    unlink($this->mediaFileInfo['filepath']);
                }
            }
        }
    }

    /**
     * Process media upload response
     *
     * @param ProtocolNode $node Message node
     * @return bool
     */
    protected function processUploadResponse($node)
    {
        $id = $node->getAttribute("id");
        $messageNode = @$this->mediaQueue[$id];
        if ($messageNode == null) {
            //message not found, can't send!
            $this->eventManager()->fire("onMediaUploadFailed",
                array(
                    $this->phoneNumber,
                    $id,
                    $node,
                    $messageNode,
                    "Message node not found in queue"
                ));
            return false;
        }

        $duplicate = $node->getChild("duplicate");
        if ($duplicate != null) {
            //file already on whatsapp servers
            $url = $duplicate->getAttribute("url");
            $filesize = $duplicate->getAttribute("size");
//          $mimetype = $duplicate->getAttribute("mimetype");
            $filehash = $duplicate->getAttribute("filehash");
            $filetype = $duplicate->getAttribute("type");
//          $width = $duplicate->getAttribute("width");
//          $height = $duplicate->getAttribute("height");
            $exploded = explode("/", $url);
            $filename = array_pop($exploded);
        } else {
            //upload new file
            $json = WhatsMediaUploader::pushFile($node, $messageNode, $this->mediaFileInfo, $this->phoneNumber);

            if (!$json) {
                //failed upload
                $this->eventManager()->fire("onMediaUploadFailed",
                    array(
                        $this->phoneNumber,
                        $id,
                        $node,
                        $messageNode,
                        "Failed to push file to server"
                    ));
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

        $mediaAttribs = array();
        $mediaAttribs["type"] = $filetype;
        $mediaAttribs["url"] = $url;
        $mediaAttribs["encoding"] = "raw";
        $mediaAttribs["file"] = $filename;
        $mediaAttribs["size"] = $filesize;
        if ($this->mediaQueue[$id]['caption'] != '') {
            $mediaAttribs["caption"] = $this->mediaQueue[$id]['caption'];
        }
        if ($this->voice == true)
        {
          $mediaAttribs["origin"] = 'live';
          $this->voice = false;
        }

        $filepath = $this->mediaQueue[$id]['filePath'];
        $to = $this->mediaQueue[$id]['to'];

        switch ($filetype) {
            case "image":
                $caption = $this->mediaQueue[$id]['caption'];
                $icon = createIcon($filepath);
                break;
            case "video":
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

        $mediaNode = new ProtocolNode("media", $mediaAttribs, null, $icon);
        if (is_array($to)) {
            $this->sendBroadcast($to, $mediaNode, "media");
        } else {
            $this->sendMessageNode($to, $mediaNode, $message_id);
        }
        $this->eventManager()->fire("onMediaMessageSent",
            array(
                $this->phoneNumber,
                $to,
                $id,
                $filetype,
                $url,
                $filename,
                $filesize,
                $filehash,
                $caption,
                $icon
            ));
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
        if ($this->socket != null) {
            $header = @socket_read($this->socket, 3);//read stanza header
            if ($header === false) {
                $error = "socket EOF, closing socket...";
                socket_close($this->socket);
                $this->socket = null;
                $this->eventManager()->fire("onClose",
                    array(
                        $this->phoneNumber,
                        $error
                    )
                );
            }

            if (strlen($header) == 0) {
                //no data received
                return;
            }
            if (strlen($header) != 3) {
                throw new ConnectionException("Failed to read stanza header");
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
                throw new ConnectionException("Tree length did not match received length (buff = " . strlen($buff) . " & treeLength = $treeLength)");
            }
            $buff = $header . $buff;
        } else {
            $this->eventManager()->fire("onDisconnect",
                array(
                    $this->phoneNumber,
                    $this->socket
                ));
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
     * @return string|null              Message ID if successfully, null if not.
     */
    protected function sendCheckAndSendMedia($filepath, $maxSize, $to, $type, $allowedExtensions, $storeURLmedia, $caption = "")
    {
        if ($this->getMediaFile($filepath, $maxSize) == true) {
            if (in_array($this->mediaFileInfo['fileextension'], $allowedExtensions)) {
                $b64hash = base64_encode(hash_file("sha256", $this->mediaFileInfo['filepath'], true));
                //request upload and get Message ID
                $id =$this->sendRequestFileUpload($b64hash, $type, $this->mediaFileInfo['filesize'], $this->mediaFileInfo['filepath'], $to, $caption);
                $this->processTempMediaFile($storeURLmedia);
                // Return message ID. Make pull request for this.
                return $id;
            } else {
                //Not allowed file type.
                $this->processTempMediaFile($storeURLmedia);
                return null;
            }
        } else {
            //Didn't get media file details.
            return null;
        }
    }

    /**
     * Send a broadcast
     * @param array  $targets Array of numbers to send to
     * @param object $node
     * @param        $type
     * @return string
     */
    protected function sendBroadcast($targets, $node, $type)
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }

        $toNodes = array();
        foreach ($targets as $target) {
            $jid = $this->getJID($target);
            $hash = array("jid" => $jid);
            $toNode = new ProtocolNode("to", $hash, null, null);
            $toNodes[] = $toNode;
        }

        $broadcastNode = new ProtocolNode("broadcast", null, $toNodes, null);

        $msgId = $this->createMsgId();

        $messageNode = new ProtocolNode("message",
            array(
                "to" => time()."@broadcast",
                "type" => $type,
                "id" => $msgId
            ), array($node, $broadcastNode), null);

        $this->sendNode($messageNode);
        $this->waitForServer($msgId);
        //listen for response
        $this->eventManager()->fire("onSendMessage",
            array(
                $this->phoneNumber,
                $targets,
                $msgId,
                $node
            ));

        return $msgId;
    }

    /**
     * Send data to the WhatsApp server.
     * @param string $data
     *
     * @throws Exception
     */
    protected function sendData($data)
    {
        if ($this->socket != null) {
            if (socket_write($this->socket, $data, strlen($data)) === false) {
                $this->disconnect();
                throw new ConnectionException('Connection Closed!');
            }
        }
    }

    /**
     * Send the getGroupList request to WhatsApp
     * @param  string $type Type of list of groups to retrieve. "owning" or "participating"
     */
    protected function sendGetGroupsFiltered($type)
    {
        $msgID = $this->nodeId['getgroups'] = $this->createIqId();
        $child = new ProtocolNode($type, null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgID,
                "type" => "get",
                "xmlns" => "w:g2",
                "to" => Constants::WHATSAPP_GROUP_SERVER
            ), array($child), null);

        $this->sendNode($node);
        $this->waitForServer($msgID);
    }

    /**
     * Change participants of a group.
     *
     * @param string $groupId      The group ID.
     * @param string $participant  The participant.
     * @param string $tag          The tag action. 'add', 'remove', 'promote' or 'demote'
     * @param        $id
     */
    protected function sendGroupsChangeParticipants($groupId, $participant, $tag, $id)
    {

        $participants = new ProtocolNode("participant", array("jid" => $this->getJID($participant)), null, "");

        $childHash = array();
        $child = new ProtocolNode($tag, $childHash, $participants, "");

        $node = new ProtocolNode("iq",
            array(
                "id" => $id,
                "type" => "set",
                "xmlns" => "w:g2",
                "to" => $this->getJID($groupId)
            ), array($child), "");

        $this->sendNode($node);
        $this->waitForServer($id);
    }

    /**
     * Send node to the servers.
     *
     * @param              $to
     * @param ProtocolNode $node
     * @param null         $id
     *
     * @return string            Message ID.
     */
    protected function sendMessageNode($to, $node, $id = null)
    {
        $msgId = ($id == null) ? $this->createMsgId() : $id;
        $to = $this->getJID($to);

        $messageNode = new ProtocolNode("message", array(
            'to'   => $to,
            'type' => ($node->getTag() == "body") ? 'text' : 'media',
            'id'   => $msgId,
            't'    => time()
        ), array($node), "");

        $this->sendNode($messageNode);

        $this->eventManager()->fire("onSendMessage",
            array(
                $this->phoneNumber,
                $to,
                $msgId,
                $node
            ));

        $this->waitForServer($msgId);

        return $msgId;
    }

    /**
     * Tell the server we received the message.
     *
     * @param ProtocolNode $node The ProtocolTreeNode that contains the message.
     * @param string       $type
     * @param string       $participant
     * @param string       $callId
     */
    protected function sendReceipt($node, $type = "read", $participant = null, $callId = null)
    {
        $messageHash = array();
        if ($type == "read") {
            $messageHash["type"] = $type;
        }
        if ($participant != null) {
            $messageHash["participant"] = $participant;
        }
        $messageHash["to"] = $node->getAttribute("from");
        $messageHash["id"] = $node->getAttribute("id");

        if ($callId != null)
        {
            $offerNode = new ProtocolNode("offer", array("call-id" => $callId), null, null);
            $messageNode = new ProtocolNode("receipt", $messageHash, array($offerNode), null);
        }
        else
        {
            $messageNode = new ProtocolNode("receipt", $messageHash, null, null);
        }
        $this->sendNode($messageNode);
        $this->eventManager()->fire("onSendMessageReceived",
            array(
                $this->phoneNumber,
                $node->getAttribute("id"),
                $node->getAttribute("from"),
                $type
            ));
    }

    /**
     * Send node to the WhatsApp server.
     * @param ProtocolNode $node
     * @param bool         $encrypt
     */
    protected function sendNode($node, $encrypt = true)
    {
        $this->debugPrint($node->nodeString("tx  ") . "\n");
        $this->sendData($this->writer->write($node, $encrypt));
    }

    /**
     * Send request to upload file
     *
     * @param string $b64hash  A base64 hash of file
     * @param string $type     File type
     * @param string $size     File size
     * @param string $filepath Path to image file
     * @param mixed  $to       Recipient(s)
     * @param string $caption
     * @return string          Message ID
     */
    protected function sendRequestFileUpload($b64hash, $type, $size, $filepath, $to, $caption = "")
    {
        $id = $this->createIqId();

        if (!is_array($to)) {
            $to = $this->getJID($to);
        }

        $mediaNode = new ProtocolNode("media", array(
            'hash'  => $b64hash,
            'type'  => $type,
            'size'  => $size
        ), null, null);

        $node = new ProtocolNode("iq", array(
            'id'    => $id,
            'to'    => Constants::WHATSAPP_SERVER,
            'type'  => 'set',
            'xmlns' => 'w:m'
        ), array($mediaNode), null);

        //add to queue
        $messageId = $this->createMsgId();
        $this->mediaQueue[$id] = array(
            "messageNode" => $node,
            "filePath"    => $filepath,
            "to"          => $to,
            "message_id"  => $messageId,
            "caption"     => $caption
        );

        $this->sendNode($node);
        $this->waitForServer($id);

        // Return message ID. Make pull request for this.
        return $messageId;
    }

    /**
     * Set your profile picture
     *
     * @param string $jid
     * @param string $filepath URL or localpath to image file
     */
    protected function sendSetPicture($jid, $filepath)
    {
        $nodeID = $this->createIqId();

        $data = preprocessProfilePicture($filepath);
        $preview = createIconGD($filepath, 96, true);

        $picture = new ProtocolNode("picture", array("type" => "image"), null, $data);
        $preview = new ProtocolNode("picture", array("type" => "preview"), null, $preview);

        $node = new ProtocolNode("iq", array(
            'id' => $nodeID,
            'to' => $this->getJID($jid),
            'type' => 'set',
            'xmlns' => 'w:profile:picture'
        ), array($picture, $preview), null);

        $this->sendNode($node);
        $this->waitForServer($nodeID);
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



    /**
     * @param ProtocolNode $groupNode
     * @param mixed        $fromGetGroups
     */
    protected function handleGroupV2InfoResponse(ProtocolNode $groupNode, $fromGetGroups = false)
    {
        $creator = $groupNode->getAttribute('creator');
        $creation = $groupNode->getAttribute('creation');
        $subject = $groupNode->getAttribute('subject');
        $groupID = $groupNode->getAttribute('id');
        $participants = array();
        $admins = array();
        if ($groupNode->getChild(0) != null) {
            foreach ($groupNode->getChildren() as $child) {
                $participants[] = $child->getAttribute('jid');
                if ($child->getAttribute('type') == "admin")
                    $admins[] = $child->getAttribute('jid');
            }
        }
        $this->eventManager()->fire("onGetGroupV2Info",
            array(
                $this->phoneNumber,
                $groupID,
                $creator,
                $creation,
                $subject,
                $participants,
                $admins,
                $fromGetGroups
            )
        );
    }
}
