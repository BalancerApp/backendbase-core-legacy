<?php

declare(strict_types=1);

namespace BackendBase\PublicWeb\Contents;

use BackendBase\Infrastructure\Persistence\Doctrine\Repository\ContentRepository;
use BackendBase\Shared\Services\MessageBus\Interfaces\QueryBus;
use Keiko\Uuid\Shortener\Dictionary;
use Keiko\Uuid\Shortener\Shortener;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Csrf\CsrfMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PageHandler implements RequestHandlerInterface
{
    public function __construct(private QueryBus $queryBus, private ?\Mezzio\Template\TemplateRendererInterface $template, private ContentRepository $contentRepository, private array $config)
    {
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $guard    = $request->getAttribute(CsrfMiddleware::GUARD_ATTRIBUTE);
        $token    = $guard->generateToken();
        $pageSlug = $request->getAttribute('pageSlug');

        $shortener = Shortener::make(
            Dictionary::createUnmistakable() // or pass your own characters set
        );
        $page      = $this->contentRepository->getContentBySlugForClient($pageSlug);

        $data = ['page' => $page];

        return new HtmlResponse($this->template->render('app::default-page', $data));
    }
}
