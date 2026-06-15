<?php

namespace App\Support;

class ArabicPdfText
{
    private const DASH = "\u{2014}";

    /**
     * @var array<string, array{0: string, 1?: string, 2?: string, 3?: string, type: string}>
     */
    private const FORMS = [
        "\u{0621}" => ["\u{FE80}", 'type' => 'none'],
        "\u{0622}" => ["\u{FE81}", "\u{FE82}", 'type' => 'right'],
        "\u{0623}" => ["\u{FE83}", "\u{FE84}", 'type' => 'right'],
        "\u{0624}" => ["\u{FE85}", "\u{FE86}", 'type' => 'right'],
        "\u{0625}" => ["\u{FE87}", "\u{FE88}", 'type' => 'right'],
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}", 'type' => 'dual'],
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}", 'type' => 'right'],
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}", 'type' => 'dual'],
        "\u{0629}" => ["\u{FE93}", "\u{FE94}", 'type' => 'right'],
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}", 'type' => 'dual'],
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}", 'type' => 'dual'],
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}", 'type' => 'dual'],
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}", 'type' => 'dual'],
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}", 'type' => 'dual'],
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}", 'type' => 'right'],
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}", 'type' => 'right'],
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}", 'type' => 'right'],
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}", 'type' => 'right'],
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}", 'type' => 'dual'],
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}", 'type' => 'dual'],
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}", 'type' => 'dual'],
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}", 'type' => 'dual'],
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}", 'type' => 'dual'],
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}", 'type' => 'dual'],
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}", 'type' => 'dual'],
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}", 'type' => 'dual'],
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}", 'type' => 'dual'],
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}", 'type' => 'dual'],
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}", 'type' => 'dual'],
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}", 'type' => 'dual'],
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}", 'type' => 'dual'],
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}", 'type' => 'dual'],
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}", 'type' => 'dual'],
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}", 'type' => 'right'],
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}", 'type' => 'right'],
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}", 'type' => 'dual'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const LAM_ALEF = [
        "\u{0622}" => ["\u{FEF5}", "\u{FEF6}"],
        "\u{0623}" => ["\u{FEF7}", "\u{FEF8}"],
        "\u{0625}" => ["\u{FEF9}", "\u{FEFA}"],
        "\u{0627}" => ["\u{FEFB}", "\u{FEFC}"],
    ];

    public static function display(mixed $value, string $fallback = self::DASH): string
    {
        $text = filled($value) ? (string) $value : $fallback;
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $shaped = preg_replace_callback('/[\p{Arabic}]+/u', fn (array $matches): string => self::shapeRun($matches[0]), $escaped) ?? $escaped;

        return mb_encode_numericentity($shaped, [0x80, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
    }

    private static function shapeRun(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = '';

        for ($index = 0; $index < count($chars); $index++) {
            $char = $chars[$index];

            if ($char === "\u{0644}" && isset($chars[$index + 1], self::LAM_ALEF[$chars[$index + 1]])) {
                $result .= self::LAM_ALEF[$chars[$index + 1]][self::connectsPrevious($chars, $index) ? 1 : 0];
                $index++;

                continue;
            }

            if (! isset(self::FORMS[$char])) {
                $result .= $char;

                continue;
            }

            $forms = self::FORMS[$char];
            $previous = self::connectsPrevious($chars, $index);
            $next = self::connectsNext($chars, $index);

            if ($previous && $next && isset($forms[3])) {
                $result .= $forms[3];
            } elseif ($previous && isset($forms[1])) {
                $result .= $forms[1];
            } elseif ($next && isset($forms[2])) {
                $result .= $forms[2];
            } else {
                $result .= $forms[0];
            }
        }

        return implode('', array_reverse(preg_split('//u', $result, -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    /**
     * @param  list<string>  $chars
     */
    private static function connectsPrevious(array $chars, int $index): bool
    {
        $current = $chars[$index] ?? null;
        $previous = $chars[$index - 1] ?? null;

        return $current !== null
            && $previous !== null
            && isset(self::FORMS[$current], self::FORMS[$previous])
            && self::FORMS[$current]['type'] !== 'none'
            && self::FORMS[$previous]['type'] === 'dual';
    }

    /**
     * @param  list<string>  $chars
     */
    private static function connectsNext(array $chars, int $index): bool
    {
        $current = $chars[$index] ?? null;
        $next = $chars[$index + 1] ?? null;

        return $current !== null
            && $next !== null
            && isset(self::FORMS[$current], self::FORMS[$next])
            && self::FORMS[$current]['type'] === 'dual'
            && self::FORMS[$next]['type'] !== 'none';
    }
}
