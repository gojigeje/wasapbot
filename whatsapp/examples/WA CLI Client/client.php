<?php
/*************************************
 * Autor: mgp25                      *
 * Github: https://github.com/mgp25  *
 *************************************/

 // ################ CONFIG PATHS #####################
 require_once('../../src/whatsprot.class.php');
 require '../../src//events/MyEvents.php';
 // ###################################################

 // ############## CONFIG TIMEZONE ###################
 date_default_timezone_set('Europe/Madrid');
 // ##################################################

//  ############## DEBUG DEV MODE ####################
 $debug = false;
//  ##################################################

// ############### MESSAGE DB PATH ###################
$GLOBALS["msg_db"] = "";
// ###################################################

echo "####################################\n";
echo "#                                  #\n";
echo "#           WA CLI CLIENT          #\n";
echo "#                                  #\n";
echo "####################################\n\n";
echo "====================================\n";

$fileName = __DIR__ . DIRECTORY_SEPARATOR . 'data.db';
$contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
if (isset($argv[1])) {
  if (!file_exists($fileName))
  {
    $db = new \PDO("sqlite:" . $fileName, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $db->exec('CREATE TABLE data (`username` TEXT, `password` TEXT, `nickname` TEXT, `login` TEXT)');
    $sql = 'INSERT INTO data (`username`, `password`, `nickname`, `login`) VALUES (:username, :password, :nickname, :login)';
    $query = $db->prepare($sql);

    $query->execute(
        array(
            ':username' => $argv[1],
            ':password' => $argv[2],
            ':nickname' => $argv[3],
            ':login'    => '1'
        )
    );
  }
}

if ((!file_exists($fileName)))
{

    echo "Welcome to CLI WA Client\n";
    echo "========================\n\n\n";
    echo "Your number > ";
    $number = trim(fgets(STDIN));
    $w = new WhatsProt($number, $nickname, $debug);

    try
    {
        $result = $w->codeRequest('sms');
    } catch (Exception $e)
    {
       echo "there is an error" . $e;
    }
    echo "\nEnter sms code you have received > ";
    $code = trim(str_replace("-", "", fgets(STDIN)));
    try
    {
        $result = $w->codeRegister($code);
    } catch (Exception $e)
    {
       echo "there is an error";
    }

    echo "\nYour nickname > ";
    $nickname = trim(fgets(STDIN));
    do
    {
       echo "Is '$nickname' right?\n";
       echo "yes/no > ";
       $right = trim(fgets(STDIN));
       if ($right != 'yes')
       {
         echo "\nYour nickname > ";
         $nickname = trim(fgets(STDIN));
       }

    } while ($right != 'yes');

    $db = new \PDO("sqlite:" . $fileName, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $db->exec('CREATE TABLE data (`username` TEXT, `password` TEXT, `nickname` TEXT, `login` TEXT)');

    $sql = 'INSERT INTO data (`username`, `password`, `nickname`, `login`) VALUES (:username, :password, :nickname, :login)';
    $query = $db->prepare($sql);

    $query->execute(
        array(
            ':username' => $number,
            ':password' => $result->pw,
            ':nickname' => $nickname,
            ':login'    => '1'
        )
    );
}

$db = new \PDO("sqlite:" . $fileName, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$sql = 'SELECT username, password, nickname, login FROM data';
$row = $db->query($sql);
$result = $row->fetchAll();
$username = $result[0]['username'];
$password = $result[0]['password'];
$nickname = $result[0]['nickname'];
$login    = $result[0]['login'];

$w = new WhatsProt($username, $nickname, $debug);
$GLOBALS["wa"] = $w;
$w->setMessageStore(new SqliteMessageStore($username));
$events = new MyEvents($w);
$w->eventManager()->bind('onGetSyncResult', 'onSyncResult');
$w->eventManager()->bind('onGetRequestLastSeen', 'onGetRequestLastSeen');
$w->eventManager()->bind('onPresenceAvailable', 'onPresenceAvailable');
$w->eventManager()->bind('onPresenceUnavailable', 'onPresenceUnavailable');
$w->eventManager()->bind('onGetImage', 'onGetImage');
$w->eventManager()->bind('onGetVideo', 'onGetVideo');
$w->eventManager()->bind('onGetAudio', 'onGetAudio');

$w->connect();
try
{
  $w->loginWithPassword($password);
}
catch (Exception $e)
{
    echo "Error: $e";
    exit();
}
echo "\nConnected to WA\n\n";
if ($login == '1')
{
    $w->sendGetClientConfig();
    $w->sendGetServerProperties();
    $w->sendGetGroups();
    $w->sendGetBroadcastLists();

    $sql = "UPDATE data SET login=?";
    $query = $db->prepare($sql);
    $query->execute(array('0'));
}
$w->sendGetPrivacyBlockedList();
$w->sendAvailableForChat($nickname);
$show = true;
global $onlineContacts;
$GLOBALS["online_contacts"] = array();
$GLOBALS["current_contact"];
$poll = 0;
do
{
    if ($show)
    {
        showContacts();
        $show = false;
    }
    $poll++;
    if ($poll == 10)
    {
        $w->pollMessage();
        $poll = 0;
    }
    $mainCmd = fgets_u(STDIN);
    switch ($mainCmd) {
      case '/add':
        echo "\nEnter the number you want to add > ";
        $numberToAdd = trim(fgets(STDIN));
        do {
          echo "\nIs it right yes/no > ";
          $check = trim(fgets(STDIN));
          if ($check != 'yes')
          {
              echo "\nEnter the number you want to add > ";
              $numberToAdd = trim(fgets(STDIN));
          }
        } while ($check != 'yes');
        echo "\nEnter the nickname/name > ";
        $nickname = trim(fgets(STDIN));
        $w->sendSync(array($numberToAdd), null, 3);
        if ($existUser)
        {
            $w->sendPresenceSubscription($numberToAdd);
            addContact($numberToAdd, $nickname);
        }
        break;
      case '/delete':
        echo "\nEnter the nickname you want to remove > ";
        $nickname = trim(fgets(STDIN));
        do {
          echo "\nIs it right yes/no > ";
          $check = trim(fgets(STDIN));
          if ($check != 'yes')
          {
              echo "\nEnter the nickname you want to remove > ";
              $nickname = trim(fgets(STDIN));
          }
        } while ($check != 'yes');
        $numberToRemove = findPhoneByNickname($nickname);
        $w->sendSync(array(), array($numberToRemove), 3);
        $w->sendPresenceUnsubscription($numberToRemove);
        $contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
        $cDB = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $sql = "DELETE FROM contacts WHERE nickname = :nickname";
        $query = $cDB->prepare($sql);
        $query->execute(array(':nickname' => $nickname));
        break;
      case '/contacts':
        $show = true;
        break;
      case '/status':
          echo "\nEnter your status > ";
          $status = trim(fgets(STDIN));
          do {
            echo "\nIs it right yes/no > ";
            $check = trim(fgets(STDIN));
            if ($check != 'yes')
            {
                echo "\nEnter your status > ";
                $status = trim(fgets(STDIN));
            }
          } while ($check != 'yes');
          $w->sendStatusUpdate($status);
          break;
      case '/profile':
          echo "\nEnter your profile picture URL > ";
          $profile = trim(fgets(STDIN));
          do {
              echo "\nIs it right yes/no > ";
              $check = trim(fgets(STDIN));
              if ($check != 'yes')
              {
                  echo "\nEnter your profile picture URL > ";
                  $profile = trim(fgets(STDIN));
              }
          } while ($check != 'yes');
          if (!filter_var($profile, FILTER_VALIDATE_URL) === false) {
              if(@getimagesize($profile) !== false) {
                  $w->sendSetProfilePicture($profile);
              }
          }
          else {
              echo "$profile is NOT a valid URL\n\n";
          }
          break;
      case '/credits':
        echo "\nSpecials thanks to 0xtryCatch :D\n";
        break;
      case '/secret';
        echo "If you are a spammer or bulk sender, this is your lucky day! Follow the link:\n";
        echo "http://bit.ly/1dOj8e0\n\n";
        echo ":)\n\n";
        exit();
        break;
      case '/chat':
        echo "\nEnter the name of the contact > ";
        $nickname = trim(fgets(STDIN));
        do {
          echo "\nIs it right yes/no > ";
          $check = trim(fgets(STDIN));
          if ($check != 'yes')
          {
              echo "\nEnter the number you want to add > ";
              $nickname = trim(fgets(STDIN));
          }
        } while ($check != 'yes');

        echo "\n\n";
        echo "You are chatting with $nickname\n";
        echo "=================================\n\n";
        $contact = findPhoneByNickname($nickname);
        $latestMsgs = getLatestMessages($contact);
        $GLOBALS["current_contact"] = $contact;
        foreach ($latestMsgs as $msg)
        {
            echo "\n- ".$nickname.": ".$msg['message']."    ".date('t/m/Y h:i:s A', $msg['t'])."\n";
        }
          $pn = new ProcessNode($w, $contact);
          $w->setNewMessageBind($pn);
          $chatting = true;
          $compose = true;
          $lastSeen = true;
          while ($chatting) {
              $w->pollMessage();
              $msgs = $w->getMessages();
              foreach ($msgs as $m) {
              # process inbound messages
              //print($m->NodeString("") . "\n");
              }
              if ($compose)
              {
                $w->sendMessageComposing($contact);
                $compose = false;
              }
              if ($lastSeen)
              {
                  if (!in_array($contact, $GLOBALS["online_contacts"]))
                      $w->sendGetRequestLastSeen($contact);
                  $lastSeen = false;
              }
              $line = fgets_u(STDIN);
              /*
              $typing = true;
              while (($c = fread(STDIN, 1)) && ($w->pollMessage()))
              {
                if ($typing)
                    $w->sendMessageComposing($contact);
                switch (ord($c)) {
                    case 8:
                      // Backspace
                      $text = substr($line, 0, -1);
                      break;
                    case 10:
                      // Newline
                      $line = $text;
                      $text = "";
                      $w->sendMessagePaused($contact);
                      break 2;
                    default:
                      $text .= $c;
                      break;
                }
                $typing = false;
              }
              */
              if ($line != "") {
                if (strrchr($line, " ")) {
                  $command = trim(strstr($line, ' ', TRUE));
                } else {
                  $command = $line;
                }
                switch ($command) {
                  case "/current":
                      $nickname = findNicknameByPhone($contact);
                      echo "[] Interactive conversation with $nickname:\n";
                      break;
                  case "/lastseen":
                      echo "[] Last seen $contact: ";
                      $w->sendMessagePaused($contact);
                      $compose = true;
                      $w->sendGetRequestLastSeen($contact);
                      break;
                  case "/block":
                      echo "< User is now blocked >\n";
                      $w->sendMessagePaused($contact);
                      $compose = true;
                      $blocked = privacySettings($contact, 'block');
                      $w->sendSetPrivacyBlockedList($blocked);
                      break;
                  case "/unblock":
                      echo "< User is now unblocked >\n";
                      $w->sendMessagePaused($contact);
                      $compose = true;
                      $blocked = privacySettings($contact, 'unblock');
                      $w->sendSetPrivacyBlockedList($blocked);
                      break;
                  case "/back":
                      echo "\nYou are now in the main menu\n";
                      echo "================================\n\n";
                      $chatting = false;
                      break;
                  case '/time':
                      echo date("l jS \of F Y h:i:s A") . "\n\n";
                      break;
                  case '/help':
                      echo "Available commands\n";
                      echo "==================\n\n";
                      echo "/query      - Shows the number you are chatting with\n";
                      echo "/lastseen   - Last seen of the user\n";
                      echo "/block      - Blocks the user\n";
                      echo "/unblock    - Unblocks user\n";
                      echo "/time       - Current time\n";
                      echo "/back       - Return to main menu\n\n";
                      break;
                  default:
                      $w->sendMessagePaused($contact);
                      if (!filter_var($line, FILTER_VALIDATE_URL) === false) {
                          if(@getimagesize($line) !== false) {
                              $w->sendMessageImage($contact, $line);
                          }
                      }
                      else
                          $w->sendMessage($contact , $line);
                      $compose = true;
                      break;
                }
              }
            }
        break;
      case '/time':
        echo date("l jS \of F Y h:i:s A") . "\n\n";
        break;
      case '/help':
        echo "Available commands\n";
        echo "==================\n\n";
        echo "/add      - Adds a contact\n";
        echo "/delete   - Removes a contact\n";
        echo "/chat     - Starts a conversation\n";
        echo "/contacts - Shows all contacts\n";
        echo "/status   - Change status\n";
        echo "/profile  - Change profile image\n";
        echo "/time     - Current time\n";
        echo "/credits  - Credits & special thanks\n";
        echo "/exit     - Close WA CLI Client\n\n";
      break;
      default:
        //code
        break;
    }
} while (($mainCmd != '/exit'));

$w->disconnect();
echo "Disconnected. Bye! :D\n";

function showContacts()
{
  $contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
  if (file_exists($contactsDB))
  {

    $cDB = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $sql = 'SELECT nickname FROM contacts';
    $row = $cDB->query($sql);
    $contacts = $row->fetchAll();
    echo "\n   Contacts\n";
    echo "==================\n\n";
    foreach ($contacts as $contact)
    {
         echo "- " . $contact['nickname'] . "\n";
    }
    echo "\n\n";
  }
}

function fgets_u($pStdn)
{
    $pArr = array($pStdn);

    if (false === ($num_changed_streams = stream_select($pArr, $write = NULL, $except = NULL, 0))) {
        print("\$ 001 Socket Error : UNABLE TO WATCH STDIN.\n");

        return FALSE;
    } elseif ($num_changed_streams > 0) {
        return trim(fgets($pStdn, 1024));
    }
    return null;
}

function addContact($number, $name)
{
  $contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
  if (!file_exists($contactsDB))
  {
    $db = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $db->exec('CREATE TABLE contacts (`phone` TEXT, `nickname` TEXT)');
  }
  else {
    $db = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  }
  $sql = 'INSERT INTO contacts (`phone`, `nickname`) VALUES (:phone, :nickname)';
  $query = $db->prepare($sql);

  $query->execute(
      array(
          ':phone' => $number,
          ':nickname' => $name
      )
  );

}

function findPhoneByNickname($contact)
{
  $contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
  if (file_exists($contactsDB))
  {
      $cDB = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      $sql = 'SELECT phone FROM contacts WHERE nickname = :nickname';
      $query = $cDB->prepare($sql);
      $query->execute(array(':nickname' => $contact));
      $contact = $query->fetchAll();
      $contact = $contact[0]['phone'];

      return $contact;
  }
}

function findNicknameByPhone($phone)
{
  $contactsDB = __DIR__ . DIRECTORY_SEPARATOR . 'contacts.db';
  if (file_exists($contactsDB))
  {
      $cDB = new \PDO("sqlite:" . $contactsDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      $sql = 'SELECT nickname FROM contacts WHERE phone = :phone';
      $query = $cDB->prepare($sql);
      $query->execute(array(':phone' => $phone));
      $contact = $query->fetchAll();
      $contact = $contact[0]['nickname'];

      return $contact;
  }
}

function getLatestMessages($phone)
{
  $msgDB = $GLOBALS["msg_db"];
  if (file_exists($msgDB))
  {
      $cDB = new \PDO("sqlite:" . $msgDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      $sql = 'SELECT message, t FROM messages WHERE `from` = :phone LIMIT 20';
      $query = $cDB->prepare($sql);
      $query->execute(array(':phone' => $phone));
      $messages = $query->fetchAll();

      return $messages;
  }
}

function privacySettings($number, $option)
{
  if ($option == 'block')
  {
      $privacyDB = __DIR__ . DIRECTORY_SEPARATOR . 'privacy.db';
      if (!file_exists($privacyDB))
      {
          $pDB = new \PDO("sqlite:" . $privacyDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
          $pDB->exec('CREATE TABLE blocked (`phone` TEXT)');
      }
      else {
          $pDB = new \PDO("sqlite:" . $privacyDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      }
      $sql = 'INSERT INTO blocked (`phone`) VALUES (:phone)';
      $query = $pDB->prepare($sql);

      $query->execute(
          array(
              ':phone' => $number
            )
        );

      $sql = 'SELECT phone FROM blocked';
      $query = $pDB->prepare($sql);
      $query->execute();
      $blocked = $query->fetchAll();
      $i = 0;
      for ($i; $i < count($blocked); $i++)
      {
          $blockedList[] = $blocked[$i]['phone'];
      }

      return $blockedList;
  }
  else {
    $privacyDB = __DIR__ . DIRECTORY_SEPARATOR . 'privacy.db';
    if (file_exists($privacyDB))
    {
        $pDB = new \PDO("sqlite:" . $privacyDB, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $sql = "DELETE FROM blocked WHERE phone = :phone";
        $query = $pDB->prepare($sql);
        $query->execute(array(':phone' => $number));

        $sql = 'SELECT phone FROM blocked';
        $query = $pDB->prepare($sql);
        $query->execute();
        $blocked = $query->fetchAll();
        $i = 0;
        for ($i; $i < count($blocked); $i++)
        {
            $blockedList[] = $blocked[$i]['phone'];
        }

        return $blockedList;
    }
  }

}

function onSyncResult($result)
{
    foreach ($result->existing as $number) {
        global $existUser;
        $existUser = true;
    }
}

function onGetRequestLastSeen( $mynumber, $from, $id, $seconds )
{
  $nickname = findNicknameByPhone(ExtractNumber($from));
  if (($seconds != "") || ($seconds != null))
      echo "$nickname last seen: " . gmdate('l jS \of F Y h:i:s A', intval($seconds)). "\n";
}

function onPresenceAvailable($mynumber, $from)
{
    $number = ExtractNumber($from);
    if (!in_array($number, $GLOBALS["online_contacts"]))
        array_push($GLOBALS["online_contacts"], $number);
    $nickname = findNicknameByPhone($number);
    echo " < $nickname is now online >\n";
}

function onPresenceUnavailable($mynumber, $from, $last)
{
    $number = ExtractNumber($from);
    if(($key = array_search($number, $GLOBALS["online_contacts"])) !== false) {
        unset($GLOBALS["online_contacts"][$key]);
    }
    $nickname = findNicknameByPhone($number);
    echo " < $nickname is now offline >\n";
}

function onGetMessage($mynumber, $from, $id, $type, $time, $name, $body)
{
    $number = ExtractNumber($from);
    if ($number != $GLOBALS["current_contact"])
    {
        $nickname = findNicknameByPhone($number);
        if (($nickname != "") || ($nickname != null))
            echo " < New message from $nickname >";
        else{
            echo " < New message from $name ($number) >";
            do{
              echo "\nDo you want to add $name ($number)? add/block/nothing\n";
              echo "> ";
              $option = trim(fgets(STDIN));
            } while(($option != 'add') || ($option != 'block') || ($option != 'nothing'));

            switch ($option)
            {
              case 'add':
                echo "\nEnter the nickname/name > ";
                $nickname = trim(fgets(STDIN));
                addContact($number, $nickname);
                $GLOBALS['wa']->sendSync(array($number), null, 3);
                $GLOBALS['wa']->sendPresenceSubscription($number);
                break;
              case 'block':
                $blockedContacts = privacySettings($number, 'block');
                $GLOBALS['wa']->sendSetPrivacyBlockedList($blockedContacts);
                echo "$name ($number) is now blocked\n";
                break;
            }

        }
    }
}

function onGetImage($mynumber, $from, $id, $type, $time, $name, $size, $url, $file, $mimeType, $fileHash, $width, $height, $preview, $caption)
{
    $number = ExtractNumber($from);
    $nickname = findNicknameByPhone($number);
    $path = __DIR__ . DIRECTORY_SEPARATOR . "data/media/$nickname/";
    if (!file_exists($path))
        mkdir($path);
    $filename = $path . time() . ".jpg";
    $data = file_get_contents($url);
    $fp = @fopen($filename, "w");
    if ($fp) {
        fwrite($fp, $data);
        fclose($fp);
    }
    echo " < Received image from $nickname >\n";
}

function onGetVideo($mynumber, $from, $id, $type, $time, $name, $url, $file, $size, $mimeType, $fileHash, $duration, $vcodec, $acodec, $preview, $caption)
{
    $number = ExtractNumber($from);
    $nickname = findNicknameByPhone($number);
    $path = "data/media/$nickname/";
    if (!file_exists($path))
        mkdir($path);
    $filename = __DIR__ . DIRECTORY_SEPARATOR . $path . time() . ".jpg";
    $data = file_get_contents($url);
    $fp = @fopen($filename, "w");
    if ($fp) {
      fwrite($fp, $data);
      fclose($fp);
    }
    echo " < Received video from $nickname >\n";
}

function onGetAudio($mynumber, $from, $id, $type, $time, $name, $size, $url, $file, $mimeType, $fileHash, $duration, $acodec, $fromJID_ifGroup = null)
{
    $number = ExtractNumber($from);
    $nickname = findNicknameByPhone($number);
    $path = "data/media/$nickname/";
    if (!file_exists($path))
        mkdir($path);
    $filename = __DIR__ . DIRECTORY_SEPARATOR . $path . time() . ".jpg";
    $data = file_get_contents($url);
    $fp = @fopen($filename, "w");
    if ($fp) {
        fwrite($fp, $data);
        fclose($fp);
    }
    echo " < Received audio from $nickname >\n";
}

class ProcessNode
{
    protected $wp = false;
    protected $target = false;

    public function __construct($wp, $target)
    {
        $this->wp = $wp;
        $this->target = $target;
    }

    public function process($node)
    {
        if ($node->getAttribute("type") == 'text')
        {
            $text = $node->getChild('body');
            $text = $text->getData();
            $number = ExtractNumber($node->getAttribute("from"));
            $nickname = findNicknameByPhone($number);

            echo "\n- ".$nickname.": ".$text."    ".date('H:i')."\n";
        }

    }
}
