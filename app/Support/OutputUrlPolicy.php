<?php

namespace App\Support;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;

/**
 * Every link or image written by the model must point to our own application.
 * Anything else (other domains, javascript:, data:...) is turned into plain text.
 */
class OutputUrlPolicy implements ExtensionInterface
{
    public function __construct(private SecurityTrace $trace) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) {
            $nodes = collect($event->getDocument()->iterator())
                ->filter(fn ($node) => $node instanceof Link || $node instanceof Image);

            foreach ($nodes as $node) {
                if (! $this->isAllowed($node->getUrl())) {
                    $this->trace->step('Untrusted URL', "BLOCKED (not allowed by output policy): {$node->getUrl()}", 'blocked');
                    $node->replaceWith(new Text('[blocked link]'));
                }
            }
        });
    }

    public function isAllowed(string $url): bool
    {
        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isOurApp = str_starts_with($url, rtrim(config('app.url'), '/').'/');

        return $isRelative || $isOurApp;
    }
}
