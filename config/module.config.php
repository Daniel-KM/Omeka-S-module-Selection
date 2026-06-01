<?php declare(strict_types=1);

namespace Selection;

return  [
    'api_adapters' => [
        'invokables' => [
            'selections' => Api\Adapter\SelectionAdapter::class,
            // TODO Remove selection_resources from the api: it is only a table for selections?
            'selection_resources' => Api\Adapter\SelectionResourceAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        'filters' => [
            'selection_visibility' => Db\Filter\SelectionVisibilityFilter::class,
        ],
    ],
    'service_manager' => [
        'invokables' => [
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
    ],
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'selectionBrowse' => View\Helper\SelectionBrowse::class,
            'selectionButton' => View\Helper\SelectionButton::class,
            'selectionButtonToGroup' => View\Helper\SelectionButtonToGroup::class,
            'selectionButtonToSelect' => View\Helper\SelectionButtonToSelect::class,
            'selectionList' => View\Helper\SelectionList::class,
            'selectionRecords' => View\Helper\SelectionRecords::class,
        ],
        'factories' => [
            'selectionReset' => Service\ViewHelper\SelectionResetFactory::class,
            'selectionContainer' => Service\ViewHelper\SelectionContainerFactory::class,
            'selectionLinkBrowse' => Service\ViewHelper\SelectionLinkBrowseFactory::class,
            'selectionButtonFavorite' => Service\ViewHelper\SelectionButtonFavoriteFactory::class,
            'selectionFavoritesEnabled' => Service\ViewHelper\SelectionFavoritesEnabledFactory::class,
            'selectionSelectionsEnabled' => Service\ViewHelper\SelectionSelectionsEnabledFactory::class,
            'selectionEffectiveMode' => Service\ViewHelper\SelectionEffectiveModeFactory::class,
            'selectionLabel' => Service\ViewHelper\SelectionLabelFactory::class,
            'selectionConvertInjector' => Service\ViewHelper\SelectionConvertInjectorFactory::class,
            'selectionAnonymousAddDialog' => Service\ViewHelper\SelectionAnonymousAddDialogFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\QuickSearchForm::class => Form\QuickSearchForm::class,
            Form\SelectionFieldset::class => Form\SelectionFieldset::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SendMessageForm::class => Form\SendMessageForm::class,
        ],
        'factories' => [
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'selection' => Site\BlockLayout\Selection::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'selection' => Site\ResourcePageBlockLayout\Selection::class,
            'selectionButton' => Site\ResourcePageBlockLayout\SelectionButton::class,
            'selectionButtonFavorite' => Site\ResourcePageBlockLayout\SelectionButtonFavorite::class,
            'selectionButtonToGroup' => Site\ResourcePageBlockLayout\SelectionButtonToGroup::class,
            'selectionButtonToSelect' => Site\ResourcePageBlockLayout\SelectionButtonToSelect::class,
            'selectionList' => Site\ResourcePageBlockLayout\SelectionList::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'selection' => Site\Navigation\Link\Selection::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Selection\Controller\Site\Guest' => Controller\Site\GuestController::class,
            'Selection\Controller\Admin\Selection' => Controller\Admin\SelectionController::class,
        ],
        'factories' => [
            'Selection\Controller\Site\Selection' => Service\Controller\Site\SelectionControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'selectionContainer' => Service\ControllerPlugin\SelectionContainerFactory::class,
            'selectionFavoritesEnabled' => Service\ControllerPlugin\SelectionFavoritesEnabledFactory::class,
            'selectionSelectionsEnabled' => Service\ControllerPlugin\SelectionSelectionsEnabledFactory::class,
            'selectionLabel' => Service\ControllerPlugin\SelectionLabelFactory::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'selection' => [
                'label' => 'Selections', // @translate
                // Class can be star, list-alt, basket, but bookmark for now.
                'class' => 'o-icon- selections fa-bookmark',
                'route' => 'admin/selection',
                'admin_section' => 'users',
                'pages' => [
                    [
                        'route' => 'admin/selection/default',
                        'controller' => Controller\Admin\SelectionController::class,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/selection/id',
                        'controller' => Controller\Admin\SelectionController::class,
                        'visible' => false,
                    ],
                ],
            ],
        ],
        'site' => [
            [
                'label' => 'Selection', // @translate
                'route' => 'site/guest/selection',
                'controller' => 'Selection\Controller\Site\Guest',
                'action' => 'browse',
                'useRouteMatch' => true,
                'visible' => false,
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    // Public area: /selection lists public selections from all
                    // users. AJAX/ops endpoints (add, toggle, include, ...)
                    // share the same prefix. Personal area lives under
                    // /guest/* (see below) regardless of the Guest module.
                    'selection' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/selection[/:action]',
                            'constraints' => [
                                // Public list (index) + AJAX/ops endpoints.
                                'action' => 'index|add|add-group|rename-group|move-group|delete-group|include|exclude|move|reorder|reset|toggle|update|convert-dialog|convert',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Site',
                                'controller' => 'Selection',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    'selection-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/selection/:id[/:action]',
                            'constraints' => [
                                // "show" is the public display of a selection.
                                // Personal "view" lives under /guest/selection.
                                'action' => 'show|edit|delete|validate|add-group|rename-group|move-group|remove-group|include|exclude|move|reorder|reset|toggle|update',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Site',
                                'controller' => 'Selection',
                                'action' => 'show',
                            ],
                        ],
                    ],
                    // Personal area (own selections + favorites). Works for
                    // both authenticated users (db) and anonymous visitors
                    // (session). The /guest/ prefix is kept for URL stability
                    // and module Guest interoperability; it does not require
                    // module Guest to be installed.
                    'guest' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/guest',
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'selection' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/selection[/:action]',
                                    'constraints' => [
                                        'action' => 'browse|reset',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Selection\Controller\Site',
                                        'controller' => 'Guest',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'selection-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/selection/:id[/:action]',
                                    'constraints' => [
                                        'action' => 'view|reset',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Selection\Controller\Site',
                                        'controller' => 'Guest',
                                        'action' => 'view',
                                    ],
                                ],
                            ],
                            'favorites' => [
                                'type' => \Laminas\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/favorites',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Selection\Controller\Site',
                                        'controller' => 'Guest',
                                        'action' => 'favorites',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'selection' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/selection',
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Admin',
                                'controller' => 'Selection',
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => \Laminas\I18n\Translator\Loader\Gettext::class,
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'selections' => [
                'sort_by' => 'id',
                'sort_order' => 'desc',
            ],
        ],
        'public' => [
            'selections' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
            'selection_resources' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'selections' => [
                'label' => 'Title', // @translate
                'owner_name' => 'Owner', // @translate
                'created' => 'Created', // @translate
                'modified' => 'Modified', // @translate
                'is_public' => 'Public', // @translate
                'id' => 'ID', // @translate
            ],
        ],
        'public' => [
            'selections' => [
                'label' => 'Title', // @translate
                'owner_name' => 'Owner', // @translate
                'created' => 'Created', // @translate
                'modified' => 'Modified', // @translate
            ],
            'selection_resources' => [
                'created' => 'Date added', // @translate
                'title' => 'Title', // @translate
            ],
        ],
    ],
    'js_translate_strings' => [
        'Are you sure you want to delete this group?', // @translate
        'Enter new group name', // @translate
        'Enter new selection name', // @translate
        'Remove from selection?', // @translate
        'Resource updated for selections.', // @translate
        'Selection limit reached. Maximum %d resources allowed.', // @translate
    ],
    'selection' => [
        'settings' => [
            'selection_message_approve_subject' => 'Your selection has been approved on {main_title}', // @translate
            'selection_message_approve_body' => <<<'MAIL'
                Hello,

                Your selection "{selection_label}" on {main_title} has been approved and is now publicly available.

                You can view it at: {selection_url}

                Sincerely,
                MAIL, // @translate
            'selection_message_reject_subject' => 'Your selection on {main_title}', // @translate
            'selection_message_reject_body' => <<<'MAIL'
                Hello,

                Your selection "{selection_label}" on {main_title} could not be approved for publication.

                Please contact us if you have any question.

                Sincerely,
                MAIL, // @translate
            'selection_reply_to_email' => '',
            'selection_notify_recipients' => [],
        ],
        // TODO Move "selection_share" to main settings?
        'site_settings' => [
            // Favorites: a single starred basket per role. One toggle per role.
            'selection_favorites_visitor' => false,
            'selection_favorites_user' => true,
            // Selections: multi-list management. One toggle per role.
            'selection_selections_visitor' => false,
            'selection_selections_user' => true,
            // Conversion of session favorites/selections into user account on
            // login: silent merge for favorites, dialog for selections unless
            // this flag is true (then auto-create a new selection).
            'selection_convert_auto' => true,
            // Per-list rendering for selections (flat / grouped / hierarchy).
            'selection_disposition' => 'flat',
            // How to show the favorites basket within /guest/selection when
            // both axes are enabled: hide / top / hierarchy_root.
            'selection_show_favorites_in_list' => 'hide',
            'selection_share' => '',
            'selection_share_redirect' => '',
            'selection_share_redirect_search' => '',
            'selection_hide_login' => false,
            'selection_selectable_resources' => [
                'items',
            ],
            'selection_max_resources' => 50,
            'selection_per_page' => 25,
            'selection_placement_button' => [],
            'selection_placement_list' => [],
            'selection_resource_show_open' => true,
            'selection_resource_show_open_list' => true,
            'selection_individual_select' => 'auto',
            'selection_append_items_browse_individual' => false,
            'selection_label_favorites' => 'My favorites', // @translate
            'selection_label_selections' => 'Selections', // @translate
            'selection_label_guest_link' => 'My selection', // @translate
            'selection_warning_anonymous' => 'This selection is stored in the session cookie. It will be lost if the cookie is cleared.', // @translate
            'selection_anonymous_add_dialog' => true,
            'selection_anonymous_add_message' => <<<'TXT'
                <p>The resource has been added to your favorites.</p>
                <p>Warning: it will only be available as long as you do not clear your browser cookies.</p>
                <p><strong>Want to keep your favorites and access more features?</strong></p>
                <p><a href="{url_login}">Log in</a> or <a href="{url_register}">create an account</a>.</p>
                TXT, // @translate
        ],
        'block_settings' => [
            'selection' => [
                'disposition' => 'flat',
                'individual_select' => 'auto',
            ],
        ],
    ],
];
