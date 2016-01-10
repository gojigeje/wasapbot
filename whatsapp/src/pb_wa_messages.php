<?php

require_once __DIR__.'/func.php';
require_once __DIR__.'/libaxolotl-php/protocol/SenderKeyDistributionMessage.php';
require_once __DIR__.'/libaxolotl-php/ecc/Curve.php';
class SenderKeyGroupMessage extends \ProtobufMessage
{
    const GROUP_ID = 1;
    const SENDER_KEY = 2;
  /* @var array Field descriptors */
  protected static $fields = [
      self::GROUP_ID => [
          'name'     => 'group_id',
          'required' => false,
          'type'     => 7,
      ],
      self::SENDER_KEY => [
          'name'     => 'sender_key',
          'required' => false,
          'type'     => 7,
      ],
  ];

    public function __construct()
    {
        $this->reset();
    }

  /**
   * Clears message values and sets default ones.
   *
   * @return null
   */
  public function reset()
  {
      $this->values[self::GROUP_ID] = null;
      $this->values[self::SENDER_KEY] = null;
  }

  /**
   * Returns field descriptors.
   *
   * @return array
   */
  public function fields()
  {
      return self::$fields;
  }

    public function getGroupId()
    {
        return $this->values[self::GROUP_ID];
    }

    public function getSenderKey()
    {
        return $this->values[self::SENDER_KEY];
    }

    public function setGroupId($id)
    {
        $this->values[self::GROUP_ID] = $id;
    }

    public function setSenderKey($sender_key)
    {
        $this->values[self::SENDER_KEY] = $sender_key;
    }
}
class SenderKeyGroupData extends \ProtobufMessage
{
    const MESSAGE = 1;
    const SENDER_KEY = 2;
  /* @var array Field descriptors */
  protected static $fields = [
      self::MESSAGE => [
        'name'     => 'message',
        'required' => false,
        'type'     => 7,
      ],
      self::SENDER_KEY => [
          'name'     => 'sender_key',
          'required' => false,
          'type'     => 'SenderKeyGroupMessage',
      ],

  ];

    public function __construct()
    {
        $this->reset();
    }

  /**
   * Clears message values and sets default ones.
   *
   * @return null
   */
  public function reset()
  {
      $this->values[self::MESSAGE] = null;
      $this->values[self::SENDER_KEY] = null;
  }

  /**
   * Returns field descriptors.
   *
   * @return array
   */
  public function fields()
  {
      return self::$fields;
  }

    public function getMessage()
    {
        return $this->values[self::MESSAGE];
    }

    public function getSenderKey()
    {
        return $this->values[self::SENDER_KEY];
    }

    public function setMessage($data)
    {
        $this->values[self::MESSAGE] = $data;
    }

    public function setSenderKey($sender_key)
    {
        $this->values[self::SENDER_KEY] = $sender_key;
    }
}
class ImageMessage extends \ProtobufMessage
{
    const URL = 1;
    const MIMETYPE = 2;
    const CAPTION = 3;
    const SHA256 = 4;
    const LENGTH = 5;
    const HEIGHT = 6;
    const WIDTH = 7;
    const REFKEY = 8;
    const KEY = 9;
    const IV = 10;
    const THUMBNAIL = 11;
  /* @var array Field descriptors */
  protected static $fields = [
      self::URL => [
          'name'     => 'url',
          'required' => false,
          'type'     => 7,
      ],
      self::MIMETYPE => [
          'name'     => 'mimetype',
          'required' => false,
          'type'     => 7,
      ],
      self::CAPTION => [
          'name'     => 'caption',
          'required' => false,
          'type'     => 7,
      ],
      self::SHA256 => [
          'name'     => 'sha256',
          'required' => false,
          'type'     => 7,
      ],
      self::LENGTH => [
          'name'     => 'length',
          'required' => false,
          'type'     => 5,
      ],
      self::HEIGHT => [
          'name'     => 'height',
          'required' => false,
          'type'     => 5,
      ],
      self::WIDTH => [
          'name'     => 'width',
          'required' => false,
          'type'     => 5,
      ],
      self::REFKEY => [
          'name'     => 'refkey',
          'required' => false,
          'type'     => 7,
      ],
      self::KEY => [
          'name'     => 'key',
          'required' => false,
          'type'     => 7,
      ],
      self::IV => [
          'name'     => 'iv',
          'required' => false,
          'type'     => 7,
      ],
      self::THUMBNAIL => [
          'name'     => 'thumbnail',
          'required' => false,
          'type'     => 7,
      ],
  ];

    public function __construct()
    {
        $this->reset();
    }

  /**
   * Clears message values and sets default ones.
   *
   * @return null
   */
  public function reset()
  {
      $this->values[self::URL] = null;
      $this->values[self::MIMETYPE] = null;
      $this->values[self::CAPTION] = null;
      $this->values[self::SHA256] = null;
      $this->values[self::LENGTH] = null;
      $this->values[self::HEIGHT] = null;
      $this->values[self::WIDTH] = null;
      $this->values[self::REFKEY] = null;
      $this->values[self::KEY] = null;
      $this->values[self::IV] = null;
      $this->values[self::THUMBNAIL] = null;
  }

  /**
   * Returns field descriptors.
   *
   * @return array
   */
  public function fields()
  {
      return self::$fields;
  }

    public function getUrl()
    {
        return $this->values[self::URL];
    }

    public function getMimeType()
    {
        return $this->values[self::MIMETYPE];
    }

    public function getCaption()
    {
        return $this->values[self::CAPTION];
    }

    public function getSha256()
    {
        return $this->values[self::SHA256];
    }

    public function getLength()
    {
        return $this->values[self::LENGTH];
    }

    public function getHeight()
    {
        return $this->values[self::HEIGHT];
    }

    public function getWidth()
    {
        return $this->values[self::WIDTH];
    }

    public function getRefKey()
    {
        return $this->values[self::REFKEY];
    }

    public function getKey()
    {
        return $this->values[self::KEY];
    }

    public function getIv()
    {
        return $this->values[self::IV];
    }

    public function getThumbnail()
    {
        return $this->values[self::THUMBNAIL];
    }

    public function setUrl($newValue)
    {
        $this->values[self::URL] = $newValue;
    }

    public function setMimeType($newValue)
    {
        $this->values[self::MIMETYPE] = $newValue;
    }

    public function setCaption($newValue)
    {
        $this->values[self::CAPTION] = $newValue;
    }

    public function setSha256($newValue)
    {
        $this->values[self::SHA256] = $newValue;
    }

    public function setLength($newValue)
    {
        $this->values[self::LENGTH] = $newValue;
    }

    public function setHeight($newValue)
    {
        $this->values[self::HEIGHT] = $newValue;
    }

    public function setWidth($newValue)
    {
        $this->values[self::WIDTH] = $newValue;
    }

    public function setRefKey($newValue)
    {
        $this->values[self::REFKEY] = $newValue;
    }

    public function setKey($newValue)
    {
        $this->values[self::KEY] = $newValue;
    }

    public function setIv($newValue)
    {
        $this->values[self::IV] = $newValue;
    }

    public function setThumbnail($newValue)
    {
        $this->values[self::THUMBNAIL] = $newValue;
    }

    public function parseFromString($data)
    {
        parent::parseFromString($data);
        $this->setThumbnail(stristr($data, hex2bin('ffd8ffe0')));
    }

    protected function WriteUInt32($val)
    {
        $result = '';
        $num1 = null;
        while (true) {
            $num1 = ($val & 127);
            $val >>= 7;
            if ($val != 0) {
                $num2 = $num1 | 128;
                $result .= chr($num2);
            } else {
                break;
            }
        }
        $result .= chr($num1);

        return $result;
    }

    public function serializeToString()
    {
        $thumb = $this->getThumbnail();
        $this->setThumbnail(null);
        $data = parent::serializeToString();
        $data .= hex2bin('8201');
        $data .= $this->WriteUInt32(strlen($thumb));
        $data .= $thumb;
        $this->setThumbnail($thumb);

        return $data;
    }
}
