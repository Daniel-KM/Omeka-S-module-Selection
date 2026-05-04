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
            'selectionMode' => Service\ViewHelper\SelectionModeFactory::class,
            'selectionConvertInjector' => Service\ViewHelper\SelectionConvertInjectorFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
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
            'selectionMode' => Service\ControllerPlugin\SelectionModeFactory::class,
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
                    // TODO Clarify routes.
                    // When the visitor is anonymous, the url is /selection/xxx/view.
                    // When the user is logged, the url is /guest/selection/xxx/view (view can be omitted).
                    // When the selection is published, the url is /selection/xxx/show (show can be omitted).
                    // The publication is done via edit. The moderation is done via validate.
                    'selection' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/selection[/:action]',
                            'constraints' => [
                                // Set groups first for regex.
                                'action' => 'index|browse|add-group|rename-group|move-group|delete-group|include|exclude|move|reset|toggle|update|add|convert-dialog|convert',
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
                                // "show" is used for public display of a selection.
                                // "view" is used for internal display when the
                                // selection is not public.
                                'action' => 'browse|delete|edit|show|view|validate|add-group|rename-group|move-group|remove-group|include|exclude|move|reset|toggle|update',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Selection\Controller\Site',
                                'controller' => 'Selection',
                                'action' => 'show',
                            ],
                        ],
                    ],
                    // The js urls uses routes above.
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
                                        'action' => 'browse|reset|view',
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
                                        'action' => 'browse|reset|view',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Selection\Controller\Site',
                                        'controller' => 'Guest',
                                        'action' => 'view',
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
            'selection_notify_recipients' => [],
        ],
        // TODO Move "selection_share" to main settings?
        'site_settings' => [
            'selection_mode_visitor' => 'none',
            'selection_mode_user' => 'standard',
            'selection_disposition' => 'flat',
            'selection_share' => '',
            'selection_share_redirect' => '',
            'selection_share_redirect_search' => '',
            'selection_hide_login' => false,
            'selection_selectable_resources' => [
                'items',
            ],
            'selection_max_resources' => 50,
            'selection_placement_button' => [],
            'selection_placement_list' => [],
            'selection_resource_show_open' => true,
            'selection_resource_show_open_list' => true,
            'selection_individual_select' => 'auto',
            'selection_append_items_browse_individual' => false,
            'selection_label' => 'Selection', // @translate
            'selection_label_guest_link' => 'My selection', // @translate
            'selection_warning_anonymous' => 'This selection is stored in the session cookie. It will be lost if the cookie is cleared.', // @translate
        ],
        'block_settings' => [
            'selection' => [
                'disposition' => 'flat',
                'individual_select' => 'auto',
            ],
        ],
    ],
];
