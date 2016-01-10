<?php

class ByteUtil
{
    public static function combine($elements) // [byte[]... elements]
    {
        $result = '';
        foreach ($elements as $e) {
            $result .= $e;
        }

        return $result;
    }

    public static function split($input, $firstLength, $secondLength, $thirdLength = null) // [byte[] input, int firstLength, int secondLength]
    {
        $parts = [];
        $parts[] = substr($input, 0, $firstLength);
        $parts[] = substr($input, $firstLength, $secondLength);
        if (is_int($thirdLength)) {
            $parts[] = substr($input, $firstLength + $secondLength, $thirdLength);
        }

        return $parts;
    }

    public static function trim($input, $length) // [byte[] input, int length]
    {
        return substr($input, 0, $length);
    }

    public static function copyFrom($input) // [byte[] input]
    {
        $output = [];
        foreach (range(0, (count($output) /*from: output.length*/ + 0)) as $_upto) {
            $output[$_upto] = $input[$_upto - (0) + 0];
        } /* from: System.arraycopy(input, 0, output, 0, output.length) */;

        return $output;
    }

    public static function intsToByteHighAndLow($highValue, $lowValue) // [int highValue, int lowValue]
    {
        if (is_string($highValue)) {
            $highValue = ord($highValue[0]);
        }
        if (is_string($lowValue)) {
            $lowValue = ord($lowValue[0]);
        }

        return ((($highValue << 4) | $lowValue)) & 0xFF;
    }

    public static function highBitsToInt($value) // [byte value]
    {
        if (is_string($value)) {
            $value = ord($value[0]);
        }

        return (($value & 0xFF)) >> 4;
    }

    public static function lowBitsToInt($value) // [byte value]
    {
        if (is_string($value)) {
            $value = ord($value[0]);
        }

        return $value & 0xF;
    }

    public static function highBitsToMedium($value) // [int value]
    {
        if (is_string($value)) {
            $value = ord($value[0]);
        }

        return $value >> 12;
    }

    public static function lowBitsToMedium($value) // [int value]
    {
        return $value & 0xFFF;
    }

    public static function shortToByteArray_197ef($value) // [int value]
    {
        $bytes = [];
        self::shortToByteArray($bytes, 0, $value);

        return $bytes;
    }

    public static function shortToByteArray_21c8b6ca($bytes, $offset, $value) // [byte[] bytes, int offset, int value]
    {
        $bytes[($offset + 1)] = $value;
        $bytes[$offset] = (($value >> 8));

        return 2;
    }

    public static function shortToLittleEndianByteArray($bytes, $offset, $value) // [byte[] bytes, int offset, int value]
    {
        $bytes[$offset] = $value;
        $bytes[($offset + 1)] = (($value >> 8));

        return 2;
    }

    public static function mediumToByteArray_197ef($value) // [int value]
    {
        $bytes = [];
        self::mediumToByteArray($bytes, 0, $value);

        return $bytes;
    }

    public static function mediumToByteArray_21c8b6ca($bytes, $offset, $value) // [byte[] bytes, int offset, int value]
    {
        $bytes[($offset + 2)] = $value;
        $bytes[($offset + 1)] = (($value >> 8));
        $bytes[$offset] = (($value >> 16));

        return 3;
    }

    public static function intToByteArray_197ef($value) // [int value]
    {
        $bytes = [];
        self::intToByteArray_21c8b6ca($bytes, 0, $value);

        return $bytes;
    }

    public static function intToByteArray_21c8b6ca(&$bytes, $offset, $value) // [byte[] bytes, int offset, int value]
    {
        $bytes = unpack('C*', pack('L', $value));
        //$bytes[$offset + 3] = $value;
        //$bytes[$offset + 2] = (($value >> 8));
        //$bytes[$offset + 1] = (($value >> 16));
        //$bytes[$offset] = (($value >> 24));
        return 4;
    }

    public static function intToLittleEndianByteArray($bytes, $offset, $value) // [byte[] bytes, int offset, int value]
    {
        $bytes[$offset] = $value;
        $bytes[($offset + 1)] = (($value >> 8));
        $bytes[($offset + 2)] = (($value >> 16));
        $bytes[($offset + 3)] = (($value >> 24));

        return 4;
    }

    public static function longToByteArray_32c67c($l) // [long l]
    {
        $bytes = [];
        self::longToByteArray($bytes, 0, $l);

        return $bytes;
    }

    public static function longToByteArray_174f8301($bytes, $offset, $value) // [byte[] bytes, int offset, long value]
    {
        $bytes[($offset + 7)] = $value;
        $bytes[($offset + 6)] = (($value >> 8));
        $bytes[($offset + 5)] = (($value >> 16));
        $bytes[($offset + 4)] = (($value >> 24));
        $bytes[($offset + 3)] = (($value >> 32));
        $bytes[($offset + 2)] = (($value >> 40));
        $bytes[($offset + 1)] = (($value >> 48));
        $bytes[$offset] = (($value >> 56));

        return 8;
    }

    public static function longTo4ByteArray($bytes, $offset, $value) // [byte[] bytes, int offset, long value]
    {
        $bytes[($offset + 3)] = $value;
        $bytes[($offset + 2)] = (($value >> 8));
        $bytes[($offset + 1)] = (($value >> 16));
        $bytes[($offset + 0)] = (($value >> 24));

        return 4;
    }

    public static function byteArrayToShort_ae1a4a6a($bytes) // [byte[] bytes]
    {
        return self::byteArrayToShort($bytes, 0);
    }

    public static function byteArrayToShort_29e7cc9a($bytes, $offset) // [byte[] bytes, int offset]
    {
        return ((($bytes[$offset] & 0xff)) << 8) | (($bytes[($offset + 1)] & 0xff));
    }

    public static function byteArrayToMedium($bytes, $offset) // [byte[] bytes, int offset]
    {
        return (((($bytes[$offset] & 0xff)) << 16) | ((($bytes[($offset + 1)] & 0xff)) << 8)) | (($bytes[($offset + 2)] & 0xff));
    }

    public static function byteArrayToInt_ae1a4a6a($bytes) // [byte[] bytes]
    {
        return self::byteArrayToInt($bytes, 0);
    }

    public static function byteArrayToInt_29e7cc9a($bytes, $offset) // [byte[] bytes, int offset]
    {
        return ((((($bytes[$offset] & 0xff)) << 24) | ((($bytes[($offset + 1)] & 0xff)) << 16)) | ((($bytes[($offset + 2)] & 0xff)) << 8)) | (($bytes[($offset + 3)] & 0xff));
    }

    public static function byteArrayToIntLittleEndian($bytes, $offset) // [byte[] bytes, int offset]
    {
        return ((((($bytes[($offset + 3)] & 0xff)) << 24) | ((($bytes[($offset + 2)] & 0xff)) << 16)) | ((($bytes[($offset + 1)] & 0xff)) << 8)) | (($bytes[$offset] & 0xff));
    }

    public static function byteArrayToLong_ae1a4a6a($bytes) // [byte[] bytes]
    {
        return self::byteArrayToLong($bytes, 0);
    }

    public static function byteArray4ToLong($bytes, $offset) // [byte[] bytes, int offset]
    {
        return (((((($bytes[($offset + 0)] & 0xff)) << 24)) | (((($bytes[($offset + 1)] & 0xff)) << 16))) | (((($bytes[($offset + 2)] & 0xff)) << 8))) | ((($bytes[($offset + 3)] & 0xff)));
    }

    public static function byteArrayToLong_29e7cc9a($bytes, $offset) // [byte[] bytes, int offset]
    {
        return (((((((((($bytes[$offset] & 0xff)) << 56)) | (((($bytes[($offset + 1)] & 0xff)) << 48))) | (((($bytes[($offset + 2)] & 0xff)) << 40))) | (((($bytes[($offset + 3)] & 0xff)) << 32))) | (((($bytes[($offset + 4)] & 0xff)) << 24))) | (((($bytes[($offset + 5)] & 0xff)) << 16))) | (((($bytes[($offset + 6)] & 0xff)) << 8))) | ((($bytes[($offset + 7)] & 0xff)));
    }
}
