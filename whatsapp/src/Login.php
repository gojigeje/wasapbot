<?php

class Login
{
    protected $phoneNumber;
    protected $password;
    protected $challengeData;
    protected $inputKey;
    protected $outputKey;
    protected $parent;

    public function __construct(WhatsProt $parent, $password)
    {
        $this->parent = $parent;
        $this->password = $password;
        $this->phoneNumber = $this->parent->getMyNumber();
    }

  /**
   * Send the nodes to the WhatsApp server to log in.
   *
   * @throws Exception
   */
  public function doLogin()
  {
      if ($this->parent->isLoggedIn()) {
          return true;
      }

      $this->parent->writer->resetKey();
      $this->parent->reader->resetKey();
      $resource = Constants::PLATFORM.'-'.Constants::WHATSAPP_VER;
      $data = $this->parent->writer->StartStream(Constants::WHATSAPP_SERVER, $resource);
      $feat = $this->createFeaturesNode();
      $auth = $this->createAuthNode();
      $this->parent->sendData($data);
      $this->parent->sendNode($feat);
      $this->parent->sendNode($auth);

      $this->parent->pollMessage();
      $this->parent->pollMessage();
      $this->parent->pollMessage();

      if ($this->parent->getChallengeData() != null) {
          $data = $this->createAuthResponseNode();
          $this->parent->sendNode($data);
          $this->parent->reader->setKey($this->inputKey);
          $this->parent->writer->setKey($this->outputKey);
          while (!$this->parent->pollMessage()) {
          };
      }

      if ($this->parent->getLoginStatus() === Constants::DISCONNECTED_STATUS) {
          throw new LoginFailureException();
      }

      $this->parent->logFile('info', '{number} successfully logged in', ['number' => $this->phoneNumber]);
      $this->parent->sendAvailableForChat();
      $this->parent->sendGetPrivacyBlockedList();
      $this->parent->sendGetClientConfig();
      $this->parent->setMessageId(substr(bin2hex(mcrypt_create_iv(64, MCRYPT_DEV_URANDOM)), 0, 22)); // 11 char hex

      if (extension_loaded('curve25519') || extension_loaded('protobuf')) {
          if (file_exists($this->parent->dataFolder.'axolotl-'.$this->phoneNumber.'.db')) {
              $pre_keys = $this->parent->getAxolotlStore()->loadPreKeys();
              if (empty($pre_keys)) {
                  $this->parent->sendSetPreKeys();
                  $this->parent->logFile('info', 'Sending prekeys to WA server');
              }
          }
      }

      return true;
  }

  /**
   * Add stream features.
   *
   * @return ProtocolNode Return itself.
   */
  protected function createFeaturesNode()
  {
      /* $readreceipts = new ProtocolNode("readreceipts", null, null, null);
      $groupsv2     = new ProtocolNode("groups_v2", null, null, null);
      $privacy      = new ProtocolNode("privacy", null, null, null);
      $presencev2   = new ProtocolNode("presence", null, null, null);*/
      $parent = new ProtocolNode('stream:features', null, null, null);

      return $parent;
  }

  /**
   * Add the authentication nodes.
   *
   * @return ProtocolNode Returns an authentication node.
   */
  protected function createAuthNode()
  {
      $data = $this->createAuthBlob();
      $attributes = [
          'user'      => $this->phoneNumber,
          'mechanism' => 'WAUTH-2',

      ];
      $node = new ProtocolNode('auth', $attributes, null, $data);

      return $node;
  }

    protected function createAuthBlob()
    {
        if ($this->parent->getChallengeData()) {
            $key = wa_pbkdf2('sha1', base64_decode($this->password), $this->parent->getChallengeData(), 16, 20, true);
            $this->inputKey = new KeyStream($key[2], $key[3]);
            $this->outputKey = new KeyStream($key[0], $key[1]);
            $this->parent->reader->setKey($this->inputKey);
          //$this->writer->setKey($this->outputKey);
          $array = "\0\0\0\0".$this->phoneNumber.$this->parent->getChallengeData().time();
            $this->parent->setChallengeData(null);

            return $this->outputKey->EncodeMessage($array, 0, strlen($array), false);
        }

        return;
    }

  /**
   * Add the auth response to protocoltreenode.
   *
   * @return ProtocolNode Returns a response node.
   */
  protected function createAuthResponseNode()
  {
      return new ProtocolNode('response', null, null, $this->authenticate());
  }

  /**
   * Authenticate with the WhatsApp Server.
   *
   * @return string Returns binary string
   */
  protected function authenticate()
  {
      $keys = KeyStream::GenerateKeys(base64_decode($this->password), $this->parent->getChallengeData());
      $this->inputKey = new KeyStream($keys[2], $keys[3]);
      $this->outputKey = new KeyStream($keys[0], $keys[1]);
      $array = "\0\0\0\0".$this->phoneNumber.$this->parent->getChallengeData().''.time().'000'.hex2bin('00').'000'.hex2bin('00')
       .Constants::OS_VERSION.hex2bin('00').Constants::MANUFACTURER.hex2bin('00').Constants::DEVICE.hex2bin('00').Constants::BUILD_VERSION;
      $response = $this->outputKey->EncodeMessage($array, 0, 4, strlen($array) - 4);
      $this->parent->setOutputKey($this->outputKey);

      return $response;
  }
}
