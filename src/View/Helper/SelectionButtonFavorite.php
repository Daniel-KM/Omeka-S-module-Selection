<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Render a single favorite (star) toggle button for a resource.
 *
 * Independent of selection_mode_user/visitor: always renders the favorite
 * button, regardless of the configured site mode. Works for both anonymous
 * visitors (session) and authenticated users (DB).
 */
class SelectionButtonFavorite extends AbstractHelper
{
    const PARTIAL_NAME = 'common/selection-button-simple';

    public function __invoke(AbstractResourceEntityRepresentation $resource): string
    {
        static $first = true;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();

        if ($first) {
            $assetUrl = $plugins->get('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/common-dialog.css', 'Common'))
                ->appendStylesheet($assetUrl('css/selection.css', 'Selection'));
            $view->headScript()
                ->appendFile($assetUrl('js/common-dialog.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('js/selection.js', 'Selection'), 'text/javascript', ['defer' => 'defer']);
            $first = false;
        }

        $site = $plugins->get('currentSite')();
        $user = $view->identity();

        return $view->partial(self::PARTIAL_NAME, [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
        ]);
    }
}
