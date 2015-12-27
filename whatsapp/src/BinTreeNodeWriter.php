<?php

class BinTreeNodeWriter
{
    private $output;
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
    /*public function ResetStreamNewStanza(){
        return "\x00\x00\x00";
    }*/
    /*public function NewStreamReset(){
         $this->output = "\x00\x00\x00\x00\x00\x00";
    }*/
    public function StartStream($domain, $resource)
    {
        $attributes = array();
        $attributes["to"]       = $domain;
        $attributes["resource"] = $resource;

        $this->writeListStart(count($attributes) * 2 + 1);

        $this->output .= "\x01";

        $this->writeAttributes($attributes);
        //echo "tx "se. "WA" . $this->writeInt8(1) . $this->writeInt8(6)."\n";
        return "WA" . $this->writeInt8(1) . $this->writeInt8(6). $this->flushBuffer();
    }

    /**
     * @param ProtocolNode $node
     * @param bool         $encrypt
     *
     * @return string
     */
    public function write($node, $encrypt = true)
    {
        if ($node == null) {
            $this->output .= "\x00";
        } else {
            $this->writeInternal($node);
        }

        return $this->flushBuffer($encrypt);
    }

    /**
     * @param ProtocolNode $node
     */
    /*protected function writeInternal($node)
    {
        $len = 1;
        if ($node->getAttributes() != null) {
            $len += count($node->getAttributes()) * 2;
        }
        if (count($node->getChildren()) > 0) {
            $len += 1;
        }
        if (strlen($node->getData()) > 0) {
            $len += 1;
        }
        $this->writeListStart($len);
        $this->writeString($node->getTag());
        $this->writeAttributes($node->getAttributes());
        if (strlen($node->getData()) > 0) {
            $this->writeBytes($node->getData());
        }
        if ($node->getChildren()) {
            $this->writeListStart(count($node->getChildren()));
            foreach ($node->getChildren() as $child) {
                $this->writeInternal($child);
            }
        }
    }*/
    private function writeInternal($protocolTreeNode){
        $len = 1;
        if ($protocolTreeNode->getAttributes() != null) {
            $len += count($protocolTreeNode->getAttributes()) * 2;
        }
        if (count($protocolTreeNode->getChildren()) > 0) {
            $len += 1;
        }
        if (strlen($protocolTreeNode->getData()) > 0) {
            $len += 1;
        }
        $this->writeListStart($len);
        $this->writeString($protocolTreeNode->getTag());
        $this->writeAttributes($protocolTreeNode->getAttributes());
        if (strlen($protocolTreeNode->getData()) > 0) {
            $this->writeBytes($protocolTreeNode->getData());
        }
        if ($protocolTreeNode->getChildren()) {
            $this->writeListStart(count($protocolTreeNode->getChildren()));
            foreach ($protocolTreeNode->getChildren() as $child) {
                $this->writeInternal($child);
            }
        }
    }
    protected function parseInt24($data)
    {
        $ret = ord(substr($data, 0, 1)) << 16;
        $ret |= ord(substr($data, 1, 1)) << 8;
        $ret |= ord(substr($data, 2, 1)) << 0;
        return $ret;
    }
    private static function packHex($n) {
        switch ($n) {
            case 48:
            case 49:
            case 50:
            case 51:
            case 52:
            case 53:
            case 54:
            case 55:
            case 56:
            case 57:
                return $n - 48;
            case 65:
            case 66:
            case 67:
            case 68:
            case 69:
            case 70:
                return 10 + ($n - 65);
            default:
                return -1;
        }
    }

    private static function packNibble($n) {
        switch ($n) {
            case 45:
            case 46:
                return 10 + ($n - 45);
            case 48:
            case 49:
            case 50:
            case 51:
            case 52:
            case 53:
            case 54:
            case 55:
            case 56:
            case 57:
                return $n - 48;
            default:
                return -1;
        }
    }
    protected function flushBuffer($encrypt = true)
    {
        $size = strlen($this->output);
        $data = $this->output;
        if ($this->key != null && $encrypt) {
            $bsize = $this->getInt24($size);
            $data     = $this->key->EncodeMessage($data, $size, 0, $size);
            $len      = strlen($data);
            $bsize[0] = chr((8 << 4) | (($len & 16711680) >> 16));
            $bsize[1] = chr(($len & 65280) >> 8);
            $bsize[2] = chr($len & 255);
            $size     = $this->parseInt24($bsize);
        }
        $ret          =  $this->writeInt24($size). $data;
        $this->output = '';
        return $ret;
    }

    protected function getInt24($length)
    {
        $ret = '';
        $ret .= chr((($length & 0xf0000) >> 16));
        $ret .= chr((($length & 0xff00) >> 8));
        $ret .= chr(($length & 0xff));
        return $ret;
    }

    protected function writeToken($token)
    {
        if ($token <= 255 && $token >= 0) {
            $this->output .= chr($token);
        }
        else throw new Exception("Invalid token.");
         /*elseif ($token <= 0x1f4) {
            $this->output .= "\xfe" . chr($token - 0xf5);
        }*/
    }

    protected function writeJid($user, $server)
    {
        $this->output .= "\xfa"; // 250
        if (strlen($user) > 0) {
            $this->writeString($user,true);
        } else {
            $this->writeToken(0);
        }
        $this->writeString($server);
    }

    protected function writeInt8($v)
    {
        $ret = chr($v & 0xff);

        return $ret;
    }

    protected function writeInt16($v)
    {
        $ret = chr(($v & 0xff00) >> 8);
        $ret .= chr(($v & 0x00ff) >> 0);

        return $ret;
    }
    protected function writeInt20($v)  {
        $ret = chr((0xF0000 & $v) >> 16);
        $ret .= chr((0xFF00 & $v) >> 8);
        $ret .= chr(($v & 0xFF) >> 0);
        return $ret;
    }
    private function writeInt31($v) {
        $ret = chr((0x7F000000 & $v) >> 24);
        $ret .= chr((0xFF0000 & $v) >> 16);
        $ret .= chr((0xFF00 & $v) >> 8);
        $ret .= chr(($v & 0xFF) >> 0);
        return $ret;
    }
    protected function writeInt24($v)
    {
        $ret = chr(($v & 0xff0000) >> 16);
        $ret .= chr(($v & 0x00ff00) >> 8);
        $ret .= chr(($v & 0x0000ff) >> 0);

        return $ret;
    }

    protected function writeBytes($bytes, $b = false)
    {
        $len = strlen($bytes);
        $toWrite = $bytes;
        if($len >= 0x100000){
            $this->output .= "\xfe";
            $this->output .= $this->writeInt31($len);
        }
        else if ($len >= 0x100) {
            $this->output .= "\xfd";
            $this->output .= $this->writeInt20($len);
        }
        else {
            $r = "";
            if ($b) {
                if ($len < 128) {
                    $r = $this->tryPackAndWriteHeader(255, $bytes);
                    if ($r == "") {
                        $r = $this->tryPackAndWriteHeader(251, $bytes);
                    }
                }
            }
            if ($r == "") {
                $this->output .= "\xfc";
                $this->output .= $this->writeInt8($len);
            }
            else {
                $toWrite = $r;
            }
        }
        $this->output .= $toWrite;
    }

    protected function writeString($tag, $packed = false)
    {
        $intVal  = -1;
        $subdict = false;
        if (TokenMap::TryGetToken($tag, $subdict, $intVal)) {
            if ($subdict) {
                $this->writeToken(236);
            }
            $this->writeToken($intVal);
            return;
        }
        $index = strpos($tag, '@');
        if ($index) {
            $server = substr($tag, $index + 1);
            $user   = substr($tag, 0, $index);
            $this->writeJid($user, $server);
        } else {
            if($packed)
                $this->writeBytes($tag,true);
            else
                $this->writeBytes($tag);
        }
    }

    protected function writeAttributes($attributes)
    {
        if ($attributes) {
            foreach ($attributes as $key => $value) {
                $this->writeString($key);
                $this->writeString($value,true);
            }
        }
    }
    private function packByte($v, $n2) {
        switch ($v) {
            case 251:
                return $this->packHex($n2);
            case 255:
                return $this->packNibble($n2);
            default:
                return -1;
        }
    }
    private function tryPackAndWriteHeader($v,$data) {
        $length = strlen($data);
        if($length >= 128) return "";
        $array2 =array_fill(0,floor(($length+1)/2),0);
        for ($i = 0; $i < $length; $i++) {
            $packByte = $this->packByte($v,ord($data[$i]));
            if ($packByte == -1) {
                $array2 = [];
                break;
            }
            $n2 = floor($i / 2);
            $array2[$n2] |= ($packByte << 4 * (1 - $i % 2));
        }
        if(count($array2) > 0){

            if ($length % 2 == 1) {
                $array2[count($array2)-1] |= 0xF;
            }
            $string = implode(array_map("chr", $array2));
            $this->output .= chr($v);
            $this->output .= $this->writeInt8($length % 2 << 7 | strlen($string));
            return $string;
        }
        return "";
    }

    protected function writeListStart($len)
    {
        if ($len == 0) {
            $this->output .= "\x00";
        } elseif ($len < 256) {
            $this->output .= "\xf8" . chr($len);
        } else {
            $this->output .= "\xf9" . $this->writeInt16($len);
        }
    }
}