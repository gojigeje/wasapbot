<?php
abstract class AllEvents
{
    protected $eventsToListenFor = array();
    protected $whatsProt;

    public function __construct($whatsProt)
    {
        $this->whatsProt = $whatsProt;
        return $this;
    }

    /**
     * Register the events you want to listen for.
     *
     * @param array $eventList
     * @return AllEvents
     */
    public function setEventsToListenFor(array $eventList)
    {
        $this->eventsToListenFor = $eventList;
        return $this->startListening();
    }

    /**
     * Binds the requested events to the event manager.
     * @return $this
     */
    protected function startListening()
    {
        foreach ($this->eventsToListenFor as $event) {
            if (is_callable(array($this, $event))) {
                $this->whatsProt->eventManager()->bind($event, array($this, $event));
            }
        }
        return $this;
    }

    //Adding to this list? Please put them in alphabetical order!
    public function onCallReceived($mynumber, $from, $id, $notify, $time, $callId) {}
    public function onClose($mynumber, $error) {}
    public function onCodeRegister($mynumber, $login, $password, $type, $expiration, $kind, $price, $cost, $currency, $price_expiration) {}
    public function onCodeRegisterFailed($mynumber, $status, $reason, $retry_after) {}
    public function onCodeRequest($mynumber, $method, $length) {}
    public function onCodeRequestFailed($mynumber, $method, $reason, $param) {}
    public function onCodeRequestFailedTooRecent($mynumber, $method, $reason, $retry_after) {}
    public function onCodeRequestFailedTooManyGuesses($mynumber, $method, $reason, $retry_after) {}
    public function onConnect($mynumber, $socket) {}
    public function onConnectError($mynumber, $socket) {}
    public function onCredentialsBad($mynumber, $status, $reason) {}
    public function onCredentialsGood($mynumber, $login, $password, $type, $expiration, $kind, $price, $cost, $currency, $price_expiration) {}
    public function onDisconnect($mynumber, $socket) {}
    public function onDissectPhone($mynumber, $phonecountry, $phonecc, $phone, $phonemcc, $phoneISO3166, $phoneISO639, $phonemnc) {}
    public function onDissectPhoneFailed($mynumber) {}
    public function onGetAudio($mynumber, $from, $id, $type, $time, $name, $size, $url, $file, $mimeType, $fileHash, $duration, $acodec, $fromJID_ifGroup = null) {}
    public function onGetBroadcastLists($mynumber, $broadcastLists){}
    public function onGetError($mynumber, $from, $id, $data, $errorType = null) {}
    public function onGetExtendAccount($mynumber, $kind, $status, $creation, $expiration) {}
    public function onGetFeature($mynumber, $from, $encrypt) {}
    public function onGetGroupMessage($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time, $name, $body) {}
    public function onGetGroups($mynumber, $groupList) {}
    public function onGetGroupV2Info( $mynumber, $group_id, $creator, $creation, $subject, $participants, $admins, $fromGetGroup ){}
    public function onGetGroupsSubject($mynumber, $group_jid, $time, $author, $name, $subject) {}
    public function onGetImage($mynumber, $from, $id, $type, $time, $name, $size, $url, $file, $mimeType, $fileHash, $width, $height, $preview, $caption) {}
    public function onGetGroupImage($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time, $name, $size, $url, $file, $mimeType, $fileHash, $width, $height, $preview, $caption) {}
    public function onGetGroupVideo($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time, $name, $url, $file, $size, $mimeType, $fileHash, $duration, $vcodec, $acodec, $preview, $caption) {}
    public function onGetLocation($mynumber, $from, $id, $type, $time, $name, $author, $longitude, $latitude, $url, $preview, $fromJID_ifGroup = null) {}
    public function onGetMessage($mynumber, $from, $id, $type, $time, $name, $body) {}
    public function onGetNormalizedJid($mynumber, $data) {}
    public function onGetPrivacyBlockedList($mynumber, $data) {}
    public function onGetProfilePicture($mynumber, $from, $type, $data) {}
    public function onGetReceipt($from, $id, $offline, $retry) {}
    public function onGetServerProperties($mynumber, $version, $props) {}
    public function onGetServicePricing($mynumber, $price, $cost, $currency, $expiration) {}
    public function onGetStatus($mynumber, $from, $requested, $id, $time, $data) {}
    public function onGetSyncResult($result) {}
    public function onGetVideo($mynumber, $from, $id, $type, $time, $name, $url, $file, $size, $mimeType, $fileHash, $duration, $vcodec, $acodec, $preview, $caption) {}
    public function onGetvCard($mynumber, $from, $id, $type, $time, $name, $vcardname, $vcard, $fromJID_ifGroup = null) {}
    public function onGroupCreate($mynumber, $groupId) {}
    public function onGroupisCreated($mynumber, $creator, $gid, $subject, $admin, $creation, $members = array()) {}
    public function onGroupsChatCreate($mynumber, $gid) {}
    public function onGroupsChatEnd($mynumber, $gid) {}
    public function onGroupsParticipantsAdd($mynumber, $groupId, $jid) {}
    public function onGroupsParticipantChangedNumber($mynumber, $groupId, $time, $oldNumber, $notify, $newNumber) {}
    public function onGroupsParticipantsPromote($myNumber, $groupJID, $time, $issuerJID, $issuerName, $promotedJIDs = array()) {}
    public function onGroupsParticipantsRemove($mynumber, $groupId, $jid) {}
    public function onLoginFailed($mynumber, $data) {}
    public function onLoginSuccess($mynumber, $kind, $status, $creation, $expiration) {}
    public function onAccountExpired($mynumber, $kind, $status, $creation, $expiration ){}
    public function onMediaMessageSent($mynumber, $to, $id, $filetype, $url, $filename, $filesize, $filehash, $caption, $icon) {}
    public function onMediaUploadFailed($mynumber, $id, $node, $messageNode, $statusMessage) {}
    public function onMessageComposing($mynumber, $from, $id, $type, $time) {}
    public function onMessagePaused($mynumber, $from, $id, $type, $time) {}
    public function onGroupMessageComposing($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time) {}
    public function onGroupMessagePaused($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time) {}
    public function onMessageReceivedClient($mynumber, $from, $id, $type, $time, $participant) {}
    public function onMessageReceivedServer($mynumber, $from, $id, $type, $time) {}
    public function onNumberWasAdded($mynumber, $jid) {}
    public function onNumberWasRemoved($mynumber, $jid) {}
    public function onNumberWasUpdated($mynumber, $jid) {}
    public function onPaidAccount($mynumber, $author, $kind, $status, $creation, $expiration) {}
    public function onPaymentRecieved($mynumber, $kind, $status, $creation, $expiration) {}
    public function onPing($mynumber, $id) {}
    public function onPresenceAvailable($mynumber, $from) {}
    public function onPresenceUnavailable($mynumber, $from, $last) {}
    public function onProfilePictureChanged($mynumber, $from, $id, $time) {}
    public function onProfilePictureDeleted($mynumber, $from, $id, $time) {}
    public function onSendMessage($mynumber, $target, $messageId, $node) {}
    public function onSendMessageReceived($mynumber, $id, $from, $type) {}
    public function onSendPong($mynumber, $msgid) {}
    public function onSendPresence($mynumber, $type, $name ) {}
    public function onSendStatusUpdate($mynumber, $txt ) {}
    public function onStreamError($data) {}
    public function onWebSync($mynumber, $from, $id, $syncData, $code, $name) {}
}
