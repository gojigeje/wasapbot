<?php
require_once 'events/WhatsApiEventsManager.php';
require_once 'Constants.php';
require_once 'token.php';
require_once 'func.php';

class Registration
{
  protected $eventManager;
  protected $phoneNumber;
  protected $identity;      //The Device Identity token. Obtained during registration with this API
  protected $debug;

  public function __construct($number, $debug = false, $customPath = false)
  {
    $this->debug        = $debug;
    $this->phoneNumber  = $number;
    $this->eventManager = new WhatsApiEventsManager();
    $this->identity     = $this->buildIdentity($customPath); // directory where identity is going to be saved
  }
  /**
  * Check if account credentials are valid.
  *
  * NOTE: WhatsApp changes your password everytime you use this.
  *       Make sure you update your config file if the output informs about
  *       a password change.
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
      'lg' => $langCode,
      'lc' => $countryCode,
      'id' => $this->identity,
      'mistyped' => '6',
      'network_radio_type' => '1',
      'simnum'  => '1',
      's' => '',
      'copiedrc' => '1',
      'hasinrc' => '1',
      'rcmatch' => '1',
      'pid' => mt_rand(100, 9999),
      //'rchash' => hash('sha256', openssl_random_pseudo_bytes(20)),
      //'anhash' => md5(openssl_random_pseudo_bytes(20)),
      'extexist' => '1',
      'extstate' => '1'
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

    $code = str_replace('-', '', $code);
    //$countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
    //$langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

    // Build the url.
    $host = 'https://' . Constants::WHATSAPP_REGISTER_HOST;
    $query = array(
      'cc' => $phone['cc'],
      'in' => $phone['phone'],
      'lg' => $langCode,
      'lc' => $countryCode,
      'id' => $this->identity,
      'token' => $token,
      'mistyped' => '6',
      'network_radio_type' => '1',
      'simnum'  => '1',
      's' => '',
      'copiedrc' => '1',
      'hasinrc' => '1',
      'rcmatch' => '1',
      'pid' => mt_rand(100, 9999),
      //'rchash' => hash('sha256', openssl_random_pseudo_bytes(20)),
      //'anhash' => md5(openssl_random_pseudo_bytes(20)),
      'extexist' => '1',
      'extstate' => '1',
      'method' => $method,
      'code' => $code,
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
  public function codeRequest($method = 'sms', $carrier = "T-Mobile5", $platform = 'Android')
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
    $token = generateRequestToken($phone['country'], $phone['phone'], $platform);

    // Build the url.
    $host = 'https://' . Constants::WHATSAPP_REQUEST_HOST;
    $query = array(
        'cc' => $phone['cc'],
        'in' => $phone['phone'],
        'lg' => $langCode,
        'lc' => $countryCode,
        'id' => $this->identity,
        'token' => $token,
        'mistyped' => '6',
        'network_radio_type' => '1',
        'simnum'  => '1',
        's' => '',
        'copiedrc' => '1',
        'hasinrc' => '1',
        'rcmatch' => '1',
        'pid' => mt_rand(100, 9999),
        'rchash' => hash('sha256', openssl_random_pseudo_bytes(20)),
        'anhash' => md5(openssl_random_pseudo_bytes(20)),
        'extexist' => '1',
        'extstate' => '1',
        'mcc' => $phone['mcc'],
        'mnc' => $mnc,
        'sim_mcc' => $phone['mcc'],
        'sim_mnc' => $mnc,
        'method' => $method,
        //'reason' => "self-send-jailbroken",
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
   * @return WhatsApiEventsManager
   */
  public function eventManager()
  {
      return $this->eventManager;
  }
}
