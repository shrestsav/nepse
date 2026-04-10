<?php

namespace App\Services\Blog;

use App\Enums\BlogContentFormat;
use HTMLPurifier;
use HTMLPurifier_Config;
use League\CommonMark\CommonMarkConverter;

class BlogPostContentRenderer
{
    private readonly CommonMarkConverter $markdownConverter;

    private readonly HTMLPurifier $purifier;

    public function __construct()
    {
        $this->markdownConverter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $config = HTMLPurifier_Config::createDefault();
        $config->set(
            'HTML.Allowed',
            'h1,h2,h3,h4,p,br,strong,b,em,i,ul,ol,li,blockquote,code,pre,hr,a[href|title|target|rel]',
        );
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.AutoParagraph', false);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        $this->purifier = new HTMLPurifier($config);
    }

    public function render(BlogContentFormat|string $format, string $bodySource): string
    {
        $resolvedFormat = $format instanceof BlogContentFormat
            ? $format
            : BlogContentFormat::from($format);

        $html = match ($resolvedFormat) {
            BlogContentFormat::Markdown => (string) $this->markdownConverter
                ->convert($bodySource)
                ->getContent(),
            BlogContentFormat::RichText => $bodySource,
        };

        return $this->sanitizeHtml($html);
    }

    public function sanitizeHtml(string $html): string
    {
        return trim($this->purifier->purify($html));
    }
}
