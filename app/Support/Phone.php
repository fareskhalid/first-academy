<?php

namespace App\Support;

class Phone
{
    public static function digits(string $value): string
    {
        return strtr($value, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    }

    public static function normalize(string $value): string
    {
        $value = preg_replace('/[\s()\-]+/u', '', self::digits(trim($value)));
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }
        if (preg_match('/^01[0125][0-9]{8}$/D', $value)) {
            $value = '+20'.substr($value, 1);
        }

        return $value;
    }

    public static function egyptian(string $value): bool
    {
        return (bool) preg_match('/^\+201[0125][0-9]{8}$/D', $value);
    }

    public static function international(string $value): bool
    {
        return (bool) preg_match('/^\+[1-9][0-9]{7,14}$/D', $value);
    }

    public static function identifier(string $value): string
    {
        $value = trim(self::digits($value));

        return str_starts_with(strtoupper($value), 'STU-') ? strtoupper($value) : self::normalize($value);
    }
}
