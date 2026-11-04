<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as Sanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** تعقيم محتوى المحرر — قائمة سماح صارمة للوسوم، بلا سكربت ولا روابط خارجية نشطة */
class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p', ['style', 'dir'])->allowElement('h2', ['style'])->allowElement('h3', ['style'])
            ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')->allowElement('u')->allowElement('s')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')->allowElement('br')->allowElement('hr')
            ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')->allowElement('td')->allowElement('th')
            ->withMaxInputLength(500000);
        $clean = (new Sanitizer($config))->sanitize($html);

        // نسمح فقط بمحاذاة النص من خاصية style
        return preg_replace_callback('/style="([^"]*)"/', function ($m) {
            return preg_match('/text-align:\s*(right|left|center|justify)/', $m[1], $a) ? 'style="text-align: '.$a[1].'"' : '';
        }, $clean);
    }
}
