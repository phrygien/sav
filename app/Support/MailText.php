<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Helpers purs (sans état) pour nettoyer et présenter les textes venant de l'API Cosmia.
 * Sortis du composant Livewire : plus de méthodes publiques inutiles exposées au client.
 */
class MailText
{
    // Nettoie un texte venant de l'API : entités HTML (&#39; &amp;#39; &quot;…) puis mojibake (Ã©, â€™…)
    public static function decode(?string $value): string
    {
        $v = (string) $value;

        // Plusieurs passes : gère le double encodage (&amp;#39; -> &#39; -> ')
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $v) {
                break;
            }

            $v = $decoded;
        }

        // Texte UTF-8 mal interprété en Windows-1252 (« rÃ©ponse », « dÃƒÂ©fectueux » = double encodage)
        for ($i = 0; $i < 3 && preg_match('/[ÃÂâ]/u', $v); $i++) {
            $fixed = @mb_convert_encoding($v, 'Windows-1252', 'UTF-8');

            if ($fixed === false || $fixed === $v || ! mb_check_encoding($fixed, 'UTF-8')) {
                break;
            }

            // Garde-fou : on ne corrige que si la conversion est réversible
            if (mb_convert_encoding($fixed, 'UTF-8', 'Windows-1252') !== $v) {
                break;
            }

            $v = $fixed;
        }

        return $v;
    }

    // Format : 25 Janvier 2026 à 14:30
    public static function formatDate(?string $value, bool $withTime = true): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $c = Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $value))
                ->setTimezone('Europe/Paris')
                ->locale('fr');
        } catch (\Throwable) {
            return $value;
        }

        $date = $c->day.' '.mb_convert_case($c->translatedFormat('F'), MB_CASE_TITLE).' '.$c->year;

        return $withTime ? $date.' à '.$c->format('H:i') : $date;
    }

    public static function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 2).' Go',
            $bytes >= 1048576    => number_format($bytes / 1048576, 2).' Mo',
            $bytes >= 1024       => number_format($bytes / 1024, 2).' Ko',
            default              => $bytes.' octets',
        };
    }

    // Garde la structure d'un mail HTML (paragraphes, cellules) sous forme de retours à la ligne,
    // et retire styles/scripts pour qu'ils ne s'affichent pas comme du texte
    public static function structureText(string $html): string
    {
        $html = preg_replace('~<(style|script|head)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~<br\b[^>]*>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</(p|div|tr|li|h[1-6]|table|ul|ol|blockquote)>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</t[dh]>~i', ' ', $html) ?? $html;

        return $html;
    }

    // HTML -> texte brut décodé (retours à la ligne conservés)
    public static function plainText(string $html): string
    {
        return self::decode(strip_tags(self::structureText($html)));
    }

    // Texte -> HTML sûr : balises retirées, texte échappé, liens cliquables. Les retours à la ligne restent des \n.
    public static function renderText(string $message): string
    {
        $links = [];

        $message = self::structureText($message);

        $message = preg_replace_callback(
            '~<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
            function ($m) use (&$links) {
                $links[] = ['url' => trim(self::decode($m[1])), 'text' => trim(self::decode(strip_tags($m[2])))];

                return '___LINK_'.(count($links) - 1).'___';
            },
            $message
        );

        $message = e(self::decode(strip_tags($message)));

        // Espaces insécables / caractères invisibles, retours à la ligne propres
        $message = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $message) ?? $message;
        $message = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $message) ?? $message;
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/[ \t]+\n/', "\n", $message);
        $message = preg_replace('/\n{3,}/', "\n\n", $message);

        $class = 'text-blue-600 underline dark:text-blue-400';

        // URLs brutes
        $message = preg_replace_callback(
            '~\b(?:https?://|www\.)[^\s]*[^\s.,;:!?)\]]~i',
            function ($m) use ($class) {
                $href = preg_match('~^https?://~i', $m[0]) ? $m[0] : 'http://'.$m[0];

                return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$m[0].'</a>';
            },
            $message
        );

        // Liens HTML d'origine
        foreach ($links as $i => $link) {
            $url  = $link['url'];
            $text = e($link['text'] !== '' ? $link['text'] : $url);

            if (preg_match('~^(https?://|mailto:)~i', $url)) {
                $html = '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$text.'</a>';
            } elseif (! preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) {
                $html = '<a href="'.e('http://'.$url).'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$text.'</a>';
            } else {
                $html = $text; // schéma non autorisé (javascript:, data:…)
            }

            $message = str_replace('___LINK_'.$i.'___', $html, $message);
        }

        return trim($message);
    }

    // Texte -> HTML sûr avec <br> (utilisé par le chatbot et la traduction)
    public static function formatMessage(string $message): string
    {
        return nl2br(self::renderText($message));
    }

    // Découpe un mail en corps / signature / historique cité
    public static function presentMessage(string $message): array
    {
        $lines  = explode("\n", self::renderText($message));
        $plain  = fn (string $l): string => trim(strip_tags($l));
        $quoted = [];

        // 1. Historique cité
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }

            $text   = $plain($line);
            $nextTx = $plain(($lines[$i + 1] ?? '').' '.($lines[$i + 2] ?? ''));

            $isQuote = preg_match('~^(le|on)\s.{6,250}(a écrit|wrote)\s*:?$~iu', $text)
                || preg_match('~^-{2,}\s*(original message|message d.origine|forwarded message|message transf[ée]r[ée])~iu', $text)
                || str_starts_with($text, '&gt;')
                || (preg_match('~^(de|from)\s*:\s*\S~iu', $text) && preg_match('~^(envoy[ée]|sent|date|objet|subject)\s*:~iu', $nextTx));

            if ($isQuote) {
                $top = array_slice($lines, 0, $i);

                if ($plain(implode('', $top)) !== '') {
                    $quoted = array_slice($lines, $i);
                    $lines  = $top;
                }

                break;
            }
        }

        // 2. Signature
        $closing = '~^(?:(?:bien\s+)?cordialement(?:\s+v[oô]tre)?|bien\s+[àa]\s+vous|amicalement|sinc[èe]res\s+salutations|salutations\s+(?:distingu[ée]es|cordiales|respectueuses)|meilleures\s+salutations|respectueusement|bonne\s+(?:journ[ée]e|soir[ée]e|r[ée]ception)|(?:best|kind|warm|with)\s+regards|regards|sincerely|best\s+wishes|yours\s+(?:truly|sincerely|faithfully))[\s,.!;:\-]*$~iu';

        $signature = [];
        $count     = count($lines);

        for ($i = max(0, $count - 25); $i < $count; $i++) {
            $text  = $plain($lines[$i]);
            $start = null;

            if (preg_match('~^(--|__+)\s*$~u', $text)) {
                $start = $i;
            } elseif (preg_match('~^(envoy[ée] de mon|envoy[ée] depuis|sent from my|get outlook for|obtenir outlook)~iu', $text)) {
                $start = $i;
            } elseif (mb_strlen($text) <= 45 && preg_match($closing, $text)) {
                $start = $i + 1; // la formule de politesse reste dans le corps
            }

            if ($start !== null) {
                $rest = array_slice($lines, $start);

                if ($plain(implode('', $rest)) !== '' && count($rest) <= 20) {
                    $signature = $rest;
                    $lines     = array_slice($lines, 0, $start);

                    break;
                }
            }
        }

        $html = fn (array $part): ?string => ($t = trim(implode("\n", $part))) !== ''
            ? nl2br(preg_replace('/\n{3,}/', "\n\n", $t))
            : null;

        return [
            'body'      => $html($lines) ?? '',
            'signature' => $html($signature),
            'quoted'    => $html($quoted),
        ];
    }

    // Métadonnées d'affichage d'un message (nom, rôle client/support, aperçu…)
    public static function messageMeta(array $msg, string $clientMail = '', string $supportMail = ''): array
    {
        $from  = self::decode($msg['from'] ?? '');
        $to    = self::decode($msg['to'] ?? '');
        $email = strtolower(trim(preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from));

        $client  = strtolower(trim($clientMail));
        $support = strtolower(trim($supportMail));

        $role = null;

        if ($client !== '' && str_contains($email, $client)) {
            $role = 'client';
        } elseif ($support !== '' && str_contains($email, $support)) {
            $role = 'support';
        }

        $clean = fn (string $v) => trim(trim(preg_replace('/\s*<[^>]*>/', '', $v)) ?: $v, "\" '");

        return [
            'name'    => $clean($from) ?: '-',
            'to'      => $clean($to) ?: '-',
            'role'    => $role,
            'date'    => self::formatDate($msg['date'] ?? null),
            'subject' => trim(self::decode($msg['subject'] ?? '')) ?: __('(Sans objet)'),
            'preview' => trim(preg_replace('/\s+/u', ' ', self::plainText((string) ($msg['message'] ?? ''))) ?? ''),
        ];
    }

    public static function attachmentInfo(array $a): array
    {
        $filename = (string) ($a['filename'] ?? 'Fichier sans nom');
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime     = (string) ($a['mimeType'] ?? '');

        return [
            'name'    => preg_replace('/^[0-9a-f]{16}_/', '', $filename),
            'ext'     => $ext,
            'url'     => $a['url'] ?? null,
            'size'    => ! empty($a['size']) ? self::formatFileSize((int) $a['size']) : null,
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_contains($mime, 'image'),
        ];
    }

    public static function presentComment(array $c): array
    {
        $raw  = trim((string) ($c['comment'] ?? ''));
        $type = 'info';

        if (preg_match('/^\[(\w+)\]\s*(.*)$/su', $raw, $m)) {
            $type = strtolower($m[1]);
            $raw  = trim($m[2]);
        }

        $ago = null;

        try {
            $ago = Carbon::parse($c['created_at'])->locale('fr')->diffForHumans();
        } catch (\Throwable) {
        }

        return [
            'type' => $type,
            'text' => self::decode($raw),
            'date' => self::formatDate($c['created_at'] ?? null),
            'ago'  => $ago,
        ];
    }
}
