<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Settings\SiteSettings;

/**
 * Inject the anonymous "added to favorites" dialog configuration as a JSON
 * payload consumed by selection.js, which calls CommonDialog.dialogAlert().
 *
 * Renders nothing for authenticated users or when the site setting is off. The
 * script tag is emitted at most once per page (singleton helper).
 */
class SelectionAnonymousAddDialog extends AbstractHelper
{
    private static bool $rendered = false;

    private SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    public function __invoke(): string
    {
        if (self::$rendered) {
            return '';
        }
        $view = $this->getView();
        if ($view->identity()) {
            return '';
        }
        if (!$this->siteSettings->get('selection_anonymous_add_dialog', true)) {
            return '';
        }

        self::$rendered = true;

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $basePath = $plugins->get('basePath');
        $currentSite = $plugins->get('currentSite');
        $translate = $plugins->get('translate');
        $site = $currentSite();

        $urlLogin = $site
            ? $basePath() . '/s/' . $site->slug() . '/guest/login'
            : $url('forgot-password');
        $urlRegister = $site
            ? $basePath() . '/s/' . $site->slug() . '/guest/register'
            : $urlLogin;

            $defaultMessage = $translate(<<<'TXT'
                <p>The resource has been added to your favorites.</p>
                <p>Warning: it will only be available as long as you do not clear your browser cookies.</p>
                <p><strong>Want to keep your favorites and access more features?</strong></p>
                <p><a href="{url_login}">Log in</a> or <a href="{url_register}">create an account</a>.</p>
                TXT);
        $message = (string) $this->siteSettings->get('selection_anonymous_add_message') ?: $defaultMessage;
        $message = strtr($message, [
            '{url_login}' => $urlLogin,
            '{url_register}' => $urlRegister,
        ]);

        $config = [
            'heading' => $translate('Added to favorites'), // @translate
            'body' => $message,
            'textOk' => $translate('OK'),
        ];

        return '<script>window.SelectionAnonymousAddDialog = ' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    }
}
