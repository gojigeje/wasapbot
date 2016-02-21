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
        $firstByte = $this->peekInt8();
        $stanzaFlag = ($firstByte & 0xF0) >> 4; //ENCRYPTED
      /*$isCompressed = (0x400000 & $firstByte) > 0;
        $isEncrypted = (0x800000 & $firstByte) > 0;*/
        $stanzaSize = $this->peekInt16(1) | (($firstByte & 0x0F) << 16);
        if ($stanzaSize > strlen($this->input)) {
            throw new Exception("Incomplete message $stanzaSize != ".strlen($this->input));
        }

        $head = $this->readInt24();
        if ($stanzaFlag & 8) {
            if (isset($this->key)) {
                $realSize = $stanzaSize - 4;
                $this->input = $this->key->DecodeMessage($this->input, $realSize, 0, $realSize); // . $remainingData;
                if($stanzaFlag & 4) { //compressed
                  $this->input = gzuncompress($this->input); // done
                }
            } else {
                throw new Exception('Encountered encrypted message, missing key');
            }
        }
        if ($stanzaSize > 0) {
            return $this->nextTreeInternal();
        }

        return;
    }

    protected function readNibble()
    {
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
        $ret = '';
        $subdict = false;
        TokenMap::GetToken($token, $subdict, $ret);
        if (!$ret) {
            $token = $this->readInt8();
            TokenMap::GetToken($token, $subdict, $ret);
            if (!$ret) {
                throw new Exception("BinTreeNodeReader->getToken: Invalid token/length in getToken $token");
            }
        }

        return $ret;
    }

    protected function getTokenDouble($n, $n2)
    {
        $pos = $n2 + $n * 256;
        $ret = '';
        $subdict = true;
        TokenMap::GetToken($pos, $subdict, $ret);
        if (!$ret) {
            throw new Exception("BinTreeNodeReader->getToken: Invalid token $pos($n + $n * 256)");
        }

        return $ret;
    }

    protected function readString($token)
    {
        $ret = '';
        if ($token == -1) {
            throw new Exception("BinTreeNodeReader->readString: Invalid -1 token in readString $token");
        }

        if (($token > 2) && ($token < 236)) {
            return $this->getToken($token);
        } else {
            switch ($token) {
                case 0:
                    $ret = '';
                    break;
                case 236:
                case 237:
                case 238:
                case 239:
                    $token2 = $this->readInt8();

                    return $this->getTokenDouble($token - 236, $token2);
                    break;
              case 250: {
                    $readString = $this->readString($this->readInt8());
                    $s = $this->readString($this->readInt8());
                    if ($readString != null && $s != null) {
                        return $readString.'@'.$s;
                    }
                    if ($s == null) {
                        return '';
                    }
                    break;
                }
                case 251:
                case 255:
                    return $this->readPacked8($token); //maybe utf8 decode
                case 252: {
                    $len = $this->readInt8();

                    return $this->fillArray($len); //maybe ut8 decode
                }
                case 253: {
                    $len = $this->readInt20();

                    return $this->fillArray($len); //maybe ut8 decode
                }
                case 254: {
                    $len = $this->readInt31();

                    return $this->fillArray($len); //maybe ut8 decode
                }
                default:
                    throw new Exception("readString couldn't match token ".$token);
            }
        }
    }

    protected function readPacked8($n)
    {
        $len = $this->readInt8();
        $remove = 0;
        if (($len & 0x80) != 0 && $n == 251) {
            $remove = 1;
        }
        $len = $len & 0x7F;
        $text = substr($this->input, 0, $len);
        $this->input = substr($this->input, $len);
        $data = bin2hex($text);
        $len = strlen($data);
        $out = '';
        for ($i = 0; $i < $len; ++$i) {
            $val = ord(hex2bin('0'.$data[$i]));
            if ($i == ($len - 1) && $val > 11 && $n != 251) {
                continue;
            }
            $out .= chr($this->unpackByte($n, $val));
        }

        return substr($out, 0, strlen($out) - $remove);
    }

    protected function unpackByte($n, $n2)
    {
        switch ($n) {
            case 251:
                return $this->unpackHex($n2);
            case 255:
                return $this->unpackNibble($n2);
            default:
                throw new Exception('bad packed type ' + $n);
        }
    }

    protected function unpackHex($n)
    {
        switch ($n) {
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
                return $n + 48;
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
                return 65 + ($n - 10);
            default:
                throw new Exception('bad hex '.$n);
        }
    }

    protected function unpackNibble($n)
    {
        switch ($n) {
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
                return $n + 48;
            case 10:
            case 11:
                return 45 + ($n - 10);
            default:
                throw new Exception('bad nibble '.$n);
        }
    }

    protected function readAttributes($size)
    {
        $attributes = [];
        $attribCount = ($size - 2 + $size % 2) / 2;
        for ($i = 0; $i < $attribCount; $i++) {
            $len1 = $this->readInt8();
            $key = $this->readString($len1);
            $len2 = $this->readInt8();
            $value = $this->readString($len2);
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    protected function inflateBuffer($stanzaSize = 0)
    {
        $this->input = gzuncompress($this->input); // maybe gzinflate or gzdecode .
    }

    protected function nextTreeInternal()
    {
        $size = $this->readListSize($this->readInt8());
        $token = $this->readInt8();
        if ($token == 1) {
            $token = $this->readInt8();
        }
        if ($token == 2) {
            return;
        }

        $tag = $this->readString($token);
        if ($size == 0 || $size == null) {
            throw new Exception('nextTree sees 0 list or null tag');
        }
        $attributes = $this->readAttributes($size);
        if ($size % 2 == 1) {
            return new ProtocolNode($tag, $attributes, null, '');
        }
        $read2 = $this->readInt8();
        if ($this->isListTag($read2)) {
            return new ProtocolNode($tag, $attributes, $this->readList($read2), '');
        }
        switch ($read2) {
            case 252:
                $len = $this->readInt8();
                $data = $this->fillArray($len); //maybe ut8 decode
                return new ProtocolNode($tag, $attributes, null, $data);
            break;
            case 253:
                $len = $this->readInt20();
                $data = $this->fillArray($len); //maybe ut8 decode
                return new ProtocolNode($tag, $attributes, null, $data);
            break;
            case 254:
                $len = $this->readInt31();
                $data = $this->fillArray($len); //maybe ut8 decode
                return new ProtocolNode($tag, $attributes, null, $data);
            break;
            case 255:
            case 251:
                return new ProtocolNode($tag, $attributes, null, $this->readPacked8($read2));
            break;
            default:
                return new ProtocolNode($tag, $attributes, null, $this->readString($read2));
            break;
        }
    }

    protected function isListTag($token)
    {
        return $token == 248 || $token == 0 || $token == 249;
    }

    protected function readList($token)
    {
        $size = $this->readListSize($token);
        $ret = [];
        for ($i = 0; $i < $size; $i++) {
            array_push($ret, $this->nextTreeInternal());
        }

        return $ret;
    }

    protected function readListSize($token)
    {
        if ($token == 0) {
            return 0;
        }
        if ($token == 0xf8) {
            return $this->readInt8();
        } elseif ($token == 0xf9) {
            return $this->readInt16();
        }

        throw new Exception("BinTreeNodeReader->readListSize: invalid list size in readListSize: token $token");
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

    public function readHeader($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (3 + $offset)) {
            $b0 = ord(substr($this->input, $offset, 1));
            $b1 = ord(substr($this->input, $offset + 1, 1));
            $b2 = ord(substr($this->input, $offset + 2, 1));
            $ret = $b0 + (($b1 << 16) + ($b2 << 8));
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
            $ret = ord($sbstr);
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

    protected function peekInt20($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (3 + $offset)) {
            $b1 = ord(substr($this->input, $offset, 1));
            $b2 = ord(substr($this->input, $offset + 1, 1));
            $b3 = ord(substr($this->input, $offset + 2, 1));
            $ret = ($b1 << 16) | ($b2 << 8) | $b3;
        }

        return $ret;
    }

    protected function readInt20()
    {
        $ret = $this->peekInt20();
        if (strlen($this->input) >= 3) {
            $this->input = substr($this->input, 3);
        }

        return $ret;
    }

    protected function peekInt31($offset = 0)
    {
        $ret = 0;
        if (strlen($this->input) >= (4 + $offset)) {
            $b1 = ord(substr($this->input, $offset, 1));
            $b2 = ord(substr($this->input, $offset + 1, 1));
            $b3 = ord(substr($this->input, $offset + 2, 1));
            $b4 = ord(substr($this->input, $offset + 3, 1));
            // $n = 0x7F & $b1; dont know what is this for
            $ret = ($b1 << 24) | ($b2 << 16) | ($b3 << 8) | $b4;
        }

        return $ret;
    }

    protected function readInt31()
    {
        $ret = $this->peekInt31();
        if (strlen($this->input) >= 4) {
            $this->input = substr($this->input, 4);
        }

        return $ret;
    }

    protected function fillArray($len)
    {
        $ret = '';
        if (strlen($this->input) >= $len) {
            $ret = substr($this->input, 0, $len);
            $this->input = substr($this->input, $len);
        }

        return $ret;
    }
}
