<?php

class BinTreeNodeReader
{
    private $input;
    /** @var $key KeyStream */
    private $key;

    public function resetKey()
    {
        $this->key = null;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function nextTree($input = null)
    {
        if ($input != null) {
            $this->input = $input;
        }
        $firstByte  = $this->peekInt8();
        $stanzaFlag = ($firstByte & 0xF0) >> 4;
        $stanzaSize = $this->peekInt16(1) | (($firstByte & 0x0F) << 16);
        if ($stanzaSize > strlen($this->input)) {
            throw new Exception("Incomplete message $stanzaSize != " . strlen($this->input));
        }
        $this->readInt24();
        if ($stanzaFlag & 8) {
            if (isset($this->key)) {
                $realSize    = $stanzaSize - 4;
                $this->input = $this->key->DecodeMessage($this->input, $realSize, 0, $realSize);// . $remainingData;
            } else {
                throw new Exception("Encountered encrypted message, missing key");
            }
        }
        if ($stanzaSize > 0) {
            return $this->nextTreeInternal();
        }

        return null;
    }

    protected function readNibble() {
        $byte = $this->readInt8();

        $ignoreLastNibble = (bool) ($byte & 0x80);
        $size = ($byte & 0x7f);
        $nrOfNibbles = $size * 2 - (int) $ignoreLastNibble;

        $data = $this->fillArray($size);
        $string = '';

        for ($i = 0; $i < $nrOfNibbles; $i++) {
            $byte = $data[(int) floor($i / 2)];
            $ord = ord($byte);

            $shift = 4 * (1 - $i % 2);
            $decimal = ($ord & (15 << $shift)) >> $shift;

            switch ($decimal) {
                case 0:
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:
                    $string .= $decimal;
                    break;
                case 10:
                case 11:
                    $string .= chr($decimal - 10 + 45);
                    break;
                default:
                    throw new Exception("Bad nibble: $decimal");
            }
        }

        return $string;
    }

    protected function getToken($token)
    {
        $ret     = "";
        $subdict = false;
        TokenMap::GetToken($token, $subdict, $ret);
        if (!$ret) {
            $token = $this->readInt8();
            TokenMap::GetToken($token, $subdict, $ret);
            if (!$ret) {
                throw new Exception("BinTreeNodeReader->getToken: Invalid token $token");
            }
        }

        return $ret;
    }

    protected function readString($token)
    {
        $ret = "";

        if ($token == -1) {
            throw new Exception("BinTreeNodeReader->readString: Invalid token $token");
        }

        if (($token > 2) && ($token < 0xf5)) {
            $ret = $this->getToken($token);
        } elseif ($token == 0) {
            $ret = "";
        } elseif ($token == 0xfc) {
            $size = $this->readInt8();
            $ret  = $this->fillArray($size);
        } elseif ($token == 0xfd) {
            $size = $this->readInt24();
            $ret  = $this->fillArray($size);
        } elseif ($token == 0xfa) {
            $user   = $this->readString($this->readInt8());
            $server = $this->readString($this->readInt8());
            if ((strlen($user) > 0) && (strlen($server) > 0)) {
                $ret = $user . "@" . $server;
            } elseif (strlen($server) > 0) {
                $ret = $server;
            }
        } elseif ($token == 0xff) {
            $ret = $this->readNibble();
        }

        return $ret;
    }

    protected function readAttributes($size)
    {
        $attributes  = array();
        $attribCount = ($size - 2 + $size % 2) / 2;

        for ($i = 0; $i < $attribCount; $i++) {
            $key              = $this->readString($this->readInt8());
            $value            = $this->readString($this->readInt8());
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    protected function nextTreeInternal()
    {
        $token = $this->readInt8();
        $size  = $this->readListSize($token);
        $token = $this->readInt8();
        if ($token == 1) {
            $attributes = $this->readAttributes($size);

            return new ProtocolNode("start", $attributes, null, "");
        } elseif ($token == 2) {
            return null;
        }
        $tag        = $this->readString($token);
        $attributes = $this->readAttributes($size);
        if (($size % 2) == 1) {
            return new ProtocolNode($tag, $attributes, null, "");
        }
        $token = $this->readInt8();
        if ($this->isListTag($token)) {
            return new ProtocolNode($tag, $attributes, $this->readList($token), "");
        }

        return new ProtocolNode($tag, $attributes, null, $this->readString($token));
    }

    protected function isListTag($token)
    {
        return ($token == 248 || $token == 0 || $token == 249);
    }

    protected function readList($token)
    {
        $size = $this->readListSize($token);
        $ret  = array();
        for ($i = 0; $i < $size; $i++) {
            array_push($ret, $this->nextTreeInternal());
        }

        return $ret;
    }

    protected function readListSize($token)
    {
        if ($token == 0xf8) {
            return $this->readInt8();
        } elseif ($token == 0xf9) {
            return $this->readInt16();
        }

        throw new Exception("BinTreeNodeReader->readListSize: Invalid token $token");
    }

    protected function peekInt24($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (3 + $offset)) {
            $ret = ord(substr($this->input, $offset, 1)) << 16;
            $ret |= ord(substr($this->input, $offset + 1, 1)) << 8;
            $ret |= ord(substr($this->input, $offset + 2, 1)) << 0;
        }

        return $ret;
    }

    protected function readInt24()
    {
        $ret = $this->peekInt24();
        if (strlen($this->input) >= 3) {
            $this->input = substr($this->input, 3);
        }

        return $ret;
    }

    protected function peekInt16($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (2 + $offset)) {
            $ret = ord(substr($this->input, $offset, 1)) << 8;
            $ret |= ord(substr($this->input, $offset + 1, 1)) << 0;
        }

        return $ret;
    }

    protected function readInt16()
    {
        $ret = $this->peekInt16();
        if ($ret > 0) {
            $this->input = substr($this->input, 2);
        }

        return $ret;
    }

    protected function peekInt8($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (1 + $offset)) {
            $sbstr = substr($this->input, $offset, 1);
            $ret   = ord($sbstr);
        }

        return $ret;
    }

    protected function readInt8()
    {
        $ret = $this->peekInt8();
        if (strlen($this->input) >= 1) {
            $this->input = substr($this->input, 1);
        }

        return $ret;
    }

    protected function fillArray($len)
    {
        $ret = "";
        if (strlen($this->input) >= $len) {
            $ret         = substr($this->input, 0, $len);
            $this->input = substr($this->input, $len);
        }

        return $ret;
    }
}
