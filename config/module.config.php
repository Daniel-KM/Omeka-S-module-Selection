<?php declare(strict_types=1);

namespace Selection;

return  [
    'api_adapters' => [
        'invokables' => [
            'selections' => Api\Adapter\SelectionAdapter::class,
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
            'selectionButton' => View\Helper\SelectionButton::class,
            'selectionButtonToGroup' => View\Helper\SelectionButtonToGroup::class,
            'selectionList' => View\Helper\SelectionList::class,
        ],
        'factories' => [
            'selectionReset' => Service\ViewHelper\SelectionResetFactory::class,
            'selectionContainer' => Service\ViewHelper\SelectionContainerFactory::class,
            'selectionLinkBrowse' => Service\ViewHelper\SelectionLinkBrowseFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SelectionFieldset::class => Form\SelectionFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
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
            'selectionButtonToGroup' => Site\ResourcePageBlockLayout\SelectionButtonToGroup::class,
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
        ],
        'factories' => [
            'Selection\Controller\Site\Selection' => Service\Controller\Site\SelectionControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'selectionContainer' => Service\ControllerPlugin\SelectionContainerFactory::class,
        ],
    ],
    'navigation' => [
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
                    'selection' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/selection[/:action]',
                            'constraints' => [
                                // Set groups first for regex.
                                'action' => 'browse|add-group|rename-group|move-group|delete-group|add|delete|move|reset|toggle|update',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Site',
                                'controller' => 'Selection',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'selection-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/selection/:id[/:action]',
                            'constraints' => [
                                'action' => 'browse|add-group|rename-group|move-group|delete-group|add|delete|move|toggle|update',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Site',
                                'controller' => 'Selection',
                                'action' => 'toggle',
                            ],
                        ],
                    ],
                    'guest' => [
                        // The default values for the guest user route are kept
                        // to avoid issues for visitors when an upgrade of
                        // module Guest occurs or when it is disabled.
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
    'selection' => [
        'site_settings' => [
            'selection_disable_anonymous' => false,
            'selection_hide_login' => false,
            'selection_selectable_resources' => [
                'items',
            ],
            'selection_placement_button' => [],
            'selection_placement_list' => [],
            'selection_resource_show_open' => true,
            'selection_resource_show_open_list' => true,
            'selection_browse_disposition' => 'list',
            'selection_individual_select' => 'auto',
            'selection_append_items_browse_individual' => false,
            'selection_label' => 'Selection', // @translate
            'selection_label_guest_link' => 'My selection', // @translate
            'selection_warning_anonymous' => 'This selection is stored in the session cookie. It will be lost if the cookie is cleared.', // @translate
        ],
        'block_settings' => [
            'selection' => [
                'disposition' => 'list',
                'individual_select' => 'auto',
            ],
        ],
    ],

    'advancedsearch' => [
        'search_fields' => [
            'common/advanced-search/selections' => [
                'module' => 'Selection',
                'label' => 'Selections', // @translate
                'resource_type' => ['item_sets', 'items', 'media'],
                'default_admin' => true,
                'default_site' => false,
            ],
        ],
    ],
];
