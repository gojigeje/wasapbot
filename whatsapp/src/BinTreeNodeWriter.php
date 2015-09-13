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

    public function StartStream($domain, $resource)
    {
        $attributes = array();

        $attributes["to"]       = $domain;
        $attributes["resource"] = $resource;
        $this->writeListStart(count($attributes) * 2 + 1);

        $this->output .= "\x01";
        $this->writeAttributes($attributes);

        return "WA" . $this->writeInt8(1) . $this->writeInt8(5) . $this->flushBuffer();
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
    protected function writeInternal($node)
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
    }

    protected function parseInt24($data)
    {
        $ret = ord(substr($data, 0, 1)) << 16;
        $ret |= ord(substr($data, 1, 1)) << 8;
        $ret |= ord(substr($data, 2, 1)) << 0;
        return $ret;
    }

    protected function flushBuffer($encrypt = true)
    {
        $size = strlen($this->output);
        $data = $this->output;
        if ($this->key != null && $encrypt) {
            $bsize = $this->getInt24($size);
            //encrypt
            $data     = $this->key->EncodeMessage($data, $size, 0, $size);
            $len      = strlen($data);
            $bsize[0] = chr((8 << 4) | (($len & 16711680) >> 16));
            $bsize[1] = chr(($len & 65280) >> 8);
            $bsize[2] = chr($len & 255);
            $size     = $this->parseInt24($bsize);
        }
        $ret          = $this->writeInt24($size) . $data;
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
        if ($token < 0xf5) {
            $this->output .= chr($token);
        } elseif ($token <= 0x1f4) {
            $this->output .= "\xfe" . chr($token - 0xf5);
        }
    }

    protected function writeJid($user, $server)
    {
        $this->output .= "\xfa";
        if (strlen($user) > 0) {
            $this->writeString($user);
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

    protected function writeInt24($v)
    {
        $ret = chr(($v & 0xff0000) >> 16);
        $ret .= chr(($v & 0x00ff00) >> 8);
        $ret .= chr(($v & 0x0000ff) >> 0);

        return $ret;
    }

    protected function writeBytes($bytes)
    {
        $len = strlen($bytes);
        if ($len >= 0x100) {
            $this->output .= "\xfd";
            $this->output .= $this->writeInt24($len);
        } else {
            $this->output .= "\xfc";
            $this->output .= $this->writeInt8($len);
        }
        $this->output .= $bytes;
    }

    protected function writeString($tag)
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
            $this->writeBytes($tag);
        }
    }

    protected function writeAttributes($attributes)
    {
        if ($attributes) {
            foreach ($attributes as $key => $value) {
                $this->writeString($key);
                $this->writeString($value);
            }
        }
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
