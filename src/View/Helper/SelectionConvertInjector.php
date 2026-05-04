<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;

class SelectionConvertInjector extends AbstractHelper
{
    public function __invoke(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE
            && empty($_COOKIE[session_name()])
        ) {
            return '';
        }

        $flag = new Container('SelectionConvert');
        if (empty($flag->pending) || !empty($flag->dismissed)) {
            return '';
        }

        $view = $this->getView();
        $site = $view->currentSite();
        if (!$site) {
            return '';
        }

        $url = $view->url('site/selection', [
            'site-slug' => $site->slug(),
            'action' => 'convert-dialog',
        ]);

        $escapeAttr = $view->plugin('escapeHtmlAttr');
        $assetUrl = $view->plugin('assetUrl');

        $commonDialogCss = $assetUrl('css/common-dialog.css', 'Common');
        $selectionCss = $assetUrl('css/selection.css', 'Selection');
        $commonDialogJs = $assetUrl('js/common-dialog.js', 'Common');
        $selectionJs = $assetUrl('js/selection.js', 'Selection');

        return '<link rel="stylesheet" href="' . $escapeAttr($commonDialogCss) . '">'
            . '<link rel="stylesheet" href="' . $escapeAttr($selectionCss) . '">'
            . '<script src="' . $escapeAttr($commonDialogJs) . '"></script>'
            . '<script src="' . $escapeAttr($selectionJs) . '"></script>'
            . '<div id="selection-convert-auto" data-url="'
            . $escapeAttr($url) . '"></div>';
    }
}
