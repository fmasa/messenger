<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Tracy;

use Tracy\IBarPanel;

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
                    function (LogToPanelMiddleware $middleware) : int {
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

        return ob_get_clean();
    }

    private function renderBus(LogToPanelMiddleware $middleware) : string
    {
        $busName = $middleware->getBusName();
        $handledMessages = $middleware->getHandledMessages();
        $icon = $this->getIcon();

        ob_start();

        require __DIR__ . '/bus.phtml';

        return ob_get_clean();
    }

    private function getIcon() : string
    {
        return file_get_contents(__DIR__ . '/icon.svg');
    }
}
