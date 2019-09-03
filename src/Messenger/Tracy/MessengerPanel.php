<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Tracy;

use Tracy\IBarPanel;
use function array_filter;
use function array_map;
use function count;
use function file_get_contents;
use function implode;
use function ob_get_clean;
use function ob_start;

final class MessengerPanel implements IBarPanel
{
    /** @var LogToPanelMiddleware[] */
    private $middlewares;

    /**
     * @param LogToPanelMiddleware[] $middlewares
     */
    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function getTab() : string
    {
        $counter = implode(
            '+',
            array_filter(
                array_map(
                    static function (LogToPanelMiddleware $middleware) : int {
                        return count($middleware->getHandledMessages());
                    },
                    $this->middlewares
                )
            )
        );

        return file_get_contents(__DIR__ . '/icon.svg') . $counter . ' messages ';
    }

    public function getPanel() : string
    {
        $buses = array_map(
            function (LogToPanelMiddleware $middleware) : string {
                return $this->renderBus($middleware);
            },
            $this->middlewares
        );

        ob_start();

        require __DIR__ . '/panel.phtml';

        return (string) ob_get_clean();
    }

    private function renderBus(LogToPanelMiddleware $middleware) : string
    {
        $busName         = $middleware->getBusName();
        $handledMessages = $middleware->getHandledMessages();
        $icon            = $this->getIcon();

        ob_start();

        require __DIR__ . '/bus.phtml';

        return (string) ob_get_clean();
    }

    private function getIcon() : string
    {
        return (string) file_get_contents(__DIR__ . '/icon.svg');
    }
}
