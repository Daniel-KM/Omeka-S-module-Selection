<?php declare(strict_types=1);

namespace Selection;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.79')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.79'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare($oldVersion, '3.3.4.1', '<')) {
    $sqls = '';
    $sm = $connection->getSchemaManager();
    $keys = [
        'FK_CB95FBE3A76ED395',
        'selection_resource_ibfk_1',
        'FK_6B34815E7E3C61F9',
        'FK_6B34815EE48EFE78',
        'FK_CB95FBE389329D25',
        'idx_cb95fbe3a76ed395',
        'idx_cb95fbe389329d25',
    ];
    $keys = array_map('strtoupper', $keys);
    $foreignKeys = $sm->listTableForeignKeys('selection_item');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array(strtoupper($foreignKey->getName()), $keys)) {
            $sqls .= "ALTER TABLE `selection_item` DROP FOREIGN KEY {$foreignKey->getName()};\n";
        }
    }
    $sqls .= <<<'SQL'
        ALTER TABLE `selection_item`
        CHANGE `user_id` `owner_id` int(11) NOT NULL AFTER `id`,
        RENAME TO `selection_resource`;
        SQL;

    $sm = $connection->getSchemaManager();
    $foreignKeys = $sm->listTableForeignKeys('selection_resource');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array(strtoupper($foreignKey->getName()), $keys)) {
            $sqls .= "ALTER TABLE `selection_resource` DROP FOREIGN KEY {$foreignKey->getName()};\n";
        }
    }
    $indexes = $sm->listTableIndexes('selection_resource');
    foreach ($indexes as $index) {
        if ($index && in_array(strtoupper($index->getName()), $keys)) {
            $sql .= 'DROP INDEX ' . $index->getName() . ' ON `selection_resource`;' . "\n";
        }
    }
    foreach (explode(";\n", $sqls) as $sql) {
        $connection->executeStatement($sql);
    }

    $sqls = <<<'SQL'
        CREATE TABLE `selection` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `owner_id` INT NOT NULL,
            `label` VARCHAR(190) NOT NULL,
            `comment` LONGTEXT DEFAULT NULL,
            `created` DATETIME NOT NULL,
            INDEX IDX_96A50CD77E3C61F9 (`owner_id`),
            UNIQUE INDEX UNIQ_96A50CD77E3C61F9EA750E8 (`owner_id`, `label`),
            PRIMARY KEY(`id`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ALTER TABLE `selection` ADD CONSTRAINT FK_96A50CD77E3C61F9 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
        ALTER TABLE `selection_resource` ADD `selection_id` INT DEFAULT NULL AFTER `resource_id`;
        CREATE UNIQUE INDEX UNIQ_6B34815E89329D25E48EFE78 ON `selection_resource` (`resource_id`, `selection_id`);
        CREATE INDEX IDX_6B34815E7E3C61F9 ON `selection_resource` (`owner_id`);
        CREATE INDEX IDX_6B34815E89329D25 ON `selection_resource` (`resource_id`);
        CREATE INDEX IDX_6B34815EE48EFE78 ON `selection_resource` (`selection_id`);
        ALTER TABLE selection_resource ADD CONSTRAINT FK_6B34815E7E3C61F9 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
        ALTER TABLE selection_resource ADD CONSTRAINT FK_6B34815EE48EFE78 FOREIGN KEY (`selection_id`) REFERENCES `selection` (`id`) ON DELETE CASCADE;
        ALTER TABLE selection_resource ADD CONSTRAINT FK_CB95FBE389329D25 FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
        ALTER TABLE selection_resource ADD CONSTRAINT selection_resource_ibfk_1 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`);
        SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        $connection->executeStatement($sql);
    }
}

if (version_compare($oldVersion, '3.3.4.2', '<')) {
    $sm = $connection->getSchemaManager();
    $indexes = $sm->listTableIndexes('selection');
    foreach ($indexes as $index) {
        if ($index && strtoupper($index->getName()) === 'UNIQ_96A50CD77E3C61F9EA750E8') {
            $sql = 'DROP INDEX UNIQ_96A50CD77E3C61F9EA750E8 ON `selection`;';
            $connection->executeStatement($sql);
            break;
        }
    }

    $sqls = <<<'SQL'
        ALTER TABLE `selection`
        ADD `is_public` TINYINT(1) DEFAULT 0 NOT NULL AFTER `owner_id`,
        ADD `is_dynamic` TINYINT(1) DEFAULT 0 NOT NULL AFTER `is_public`,
        ADD `search_query` LONGTEXT DEFAULT NULL AFTER `comment`,
        ADD `modified` DATETIME DEFAULT NULL AFTER `created`;
        CREATE UNIQUE INDEX UNIQ_96A50CD77E3C61F9EA750E85D978C7C ON `selection` (`owner_id`, `label`, `is_dynamic`);
        UPDATE `selection` SET `is_dynamic` = 0, `search_query` = NULL WHERE `search_query` IS NULL OR TRIM(search_query) = "";
        UPDATE `selection` SET `is_dynamic` = 1, `search_query` = TRIM(`search_query`) WHERE `search_query` IS NOT NULL AND TRIM(search_query) != "";
        SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        $connection->executeStatement($sql);
    }
}

if (version_compare($oldVersion, '3.3.4.4', '<')) {
    $message = new PsrMessage(
        'Helpers and templates were renamed. Old ones will be removed in a future version.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.4.5', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `selection`
        ADD `structure` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)' AFTER `search_query`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Throwable $e) {
        // Skip.
    }

    // Add at least one selection by user for all selected resources.
    // Selections by owner.
    $sql = <<<'SQL'
        SELECT id, owner_id
        FROM selection_resource
        WHERE selection_id IS NULL
        ORDER BY owner_id ASC;
        SQL;
    $selectionResources = $connection->executeQuery($sql)->fetchAllKeyValue();

    // Create a new selection for all selections, even if the user has one.
    if ($selectionResources) {
        // Append "id" to avoid issue with the unique index.
        $sql = <<<'SQL'
            INSERT INTO selection (owner_id, label, created)
            SELECT DISTINCT owner_id, CONCAT('__SELECTION__ ', id), NOW()
            FROM selection_resource
            WHERE selection_id IS NULL;
            SQL;
        $connection->executeStatement($sql);
        $sql = <<<'SQL'
            UPDATE selection_resource
            JOIN selection
                ON selection.owner_id =  selection_resource.owner_id
                    AND selection.label LIKE "\_\_SELECTION\_\_%"
            SET selection_id = selection.id
            WHERE selection_id IS NULL;
            SQL;
        $connection->executeStatement($sql);
        $sql = <<<'SQL'
            UPDATE selection
            SET label = "%s"
            WHERE label LIKE "\_\_SELECTION\_\_%";
            SQL;
        $translate = $services->get('ControllerPluginManager')->get('translate');
        $connection->executeStatement(sprintf($sql, $translate('Selection'))); // @translate
    }

    $message = new PsrMessage(
        'It is now possible to organize selected resources in a structured way.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'The structure is not available for anonymous visitors yet.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'Some url routes have been updated to use query arguments. Check your theme if needed.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `selection`
            CHANGE `created` `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER `structure`;
        ALTER TABLE `selection_resource`
            CHANGE `created` `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER `selection_id`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Throwable $e) {
        // Skip.
    }

    $sql = <<<'SQL'
        UPDATE `site_setting`
        SET `id` = "selection_anonymous"
        WHERE `id` = "selection_visitor_allow";
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        UPDATE `site_setting`
        SET `id` = "selection_resource_show_open"
        WHERE `id` = "selection_open";
        SQL;
    $connection->executeStatement($sql);

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->set('selection_selectable_resources', ['items', 'media', 'item_sets'], $siteId);
    }

    $message = new PsrMessage(
        'The option "selection_visitor_allow" was renamed "selection_disable_anonymous" and "selection_open" as "selection_resource_show_open". Update your theme if you customized it.' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'The views were restructured, so check your theme if you customized it.' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'A new option in site settings and in block settings allows to display the selection flat, by default, or hierarchically.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A new option in site settings allows to define selectable resources (items, medias, item sets).' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The resource block "Selection" was split into a block "Selection" and a block "Selection list".' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Modules {link_1}Bulk Export{link_end} and {link_2}Contact Us{link_end} are fully integrated in the selection page.', // @translate
        [
            'link_1' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport" target="_blank" rel="noopener">',
            'link_2' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-ContactUs" target="_blank" rel="noopener">',
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.8', '<')) {
    /**
     * Migrate blocks of this module to new blocks of Omeka S v4.1.
     *
     * Replace filled settting "heading" by a specific block "Heading".
     * Move setting template to block layout template.
     *
     * @see \Omeka\Db\Migrations\MigrateBlockLayoutData
     */

    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);

    $viewHelpers = $services->get('ViewHelperManager');
    $escape = $viewHelpers->get('escapeHtml');
    $hasBlockPlus = $this->isModuleActive('BlockPlus');

    $pagesUpdated = [];
    $pagesUpdated2 = [];
    foreach ($pageRepository->findAll() as $page) {
        $pageSlug = $page->getSlug();
        $siteSlug = $page->getSite()->getSlug();
        $position = 0;
        foreach ($page->getBlocks() as $block) {
            $block->setPosition(++$position);
            $layout = $block->getLayout();
            if ($layout !== 'selection') {
                continue;
            }
            $data = $block->getData() ?: [];

            $heading = $data['heading'] ?? '';
            if (strlen($heading)) {
                $b = new \Omeka\Entity\SitePageBlock();
                $b->setPage($page);
                $b->setPosition(++$position);
                if ($hasBlockPlus) {
                    $b->setLayout('heading');
                    $b->setData([
                        'text' => $heading,
                        'level' => 2,
                    ]);
                } else {
                    $b->setLayout('html');
                    $b->setData([
                        'html' => '<h2>' . $escape($heading) . '</h2>',
                    ]);
                }
                $entityManager->persist($b);
                $block->setPosition(++$position);
                $pagesUpdated[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['heading']);

            $template = $data['template'] ?? null;
            if ($template && $template !== 'common/block-layout/selection') {
                $layoutData = $block->getLayoutData();
                $layoutData['template_name'] = pathinfo($template, PATHINFO_FILENAME);
                $block->setLayoutData($layoutData);
                $pagesUpdated2[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['template']);

            $block->setData($data);
        }
    }

    $entityManager->flush();
    $entityManager->clear();

    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The setting "heading" was removed from block Selection. New blocks "Heading" or "Html" were prepended to all blocks that had a filled heading. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    if ($pagesUpdated2) {
        $result = array_map('array_values', $pagesUpdated2);
        $message = new PsrMessage(
            'The setting "template" was moved to the new block layout settings available since Omeka S v4.1. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());

        $message = new PsrMessage(
            'The template files for the block Selection should be moved from "view/common/block-layout" to "view/common/block-template" in your themes. You may check your themes for pages: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addError($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }
}

if (version_compare($oldVersion, '3.4.9', '<')) {
    /**
     * @var \Omeka\Settings\SiteSettings $siteSettings
     * @var \Omeka\Site\Theme\Manager $themeManager
     * @var \Omeka\Api\Representation\SiteRepresentation $site
     */
    $themeManager = $services->get('Omeka\Site\ThemeManager');
    $siteSettings = $services->get('Omeka\Settings\Site');
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $theme = $site->theme();
        if ($theme && $themeManager->isRegistered($theme)) {
            $theme = $themeManager->getTheme($theme);
            $blockOrAfter = $theme->isConfigurableResourcePageBlocks() ? 'block' : 'after';
            $siteId = $site->id();
            $siteSettings->setTargetId($siteId);
            $selectables = $siteSettings->get('selection_selectable_resources', []);
            $buttons = [];
            foreach ($selectables as $resourceName) {
                $buttons[] = "$blockOrAfter/$resourceName";
            }
            $siteSettings->set('selection_placement_button', $buttons);
            $siteSettings->set('selection_placement_list', $buttons);
        }
    }

    $message = new PsrMessage(
        'A new option is added to display selection button and list before, after or via resource blocks. See site settings.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.11', '<')) {
    // See version 3.4.7.
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $strings = [
        'selectionButtonToggle(',
        // Deprecated aliases.
        'showSelectionLink(',
        'updateSelectionLink(',
        'containerSelection(',
        // Renamed to selectionButton, selectionList, selectionLinkBrowse, selectionContainer.
    ];
    $globs = [
        'themes/*/view/common/*',
        'themes/*/view/common/*/*',
        'themes/*/view/guest/site/guest/*',
        'themes/*/view/guest/site/guest/*/*',
        'themes/*/view/selection/site/selection/*',
    ];
    $result = [];
    foreach ($globs as $glob) {
        $res = $manageModuleAndResources->checkStringsInFiles($strings, $glob) ?? [];
        $result = array_merge($result, $res);
    }
    if ($result) {
        $message = new PsrMessage(
            'The view helpers "selectionButtonToggle", "showSelectionLink", "updateSelectionLink" , and "containerSelection" were renamed "selectionButton", "selectionList", "selectionLinkBrowse", and "selectionContainer". You should update your theme. Matching templates: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $logger->err($message->getMessage(), $message->getContext());
        $messenger->addError($message);
    }
}

if (version_compare($oldVersion, '3.4.15', '<')) {
    // See version 3.4.7.
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $strings = [
        'selection.js',
    ];
    $globs = [
        'themes/*/view/common/*',
        'themes/*/view/common/*/*',
        'themes/*/view/guest/site/guest/*',
        'themes/*/view/guest/site/guest/*/*',
        'themes/*/view/selection/site/selection/*',
    ];
    $result = [];
    foreach ($globs as $glob) {
        $res = $manageModuleAndResources->checkStringsInFiles($strings, $glob) ?? [];
        $result = array_merge($result, $res);
    }
    if ($result) {
        $strings = [
            'common-dialog.js',
        ];
        $globs = [
            'themes/*/view/layout/*',
            'themes/*/view/common/*',
            'themes/*/view/common/*/*',
            'themes/*/view/guest/site/guest/*',
            'themes/*/view/guest/site/guest/*/*',
            'themes/*/view/selection/site/selection/*',
        ];
        $resultWith = [];
        foreach ($globs as $glob) {
            $res = $manageModuleAndResources->checkStringsInFiles($strings, $glob) ?? [];
            $resultWith = array_merge($resultWith, $res);
        }
        $resultDiff = array_diff($result, $resultWith);
        if ($resultDiff) {
            $message = new PsrMessage(
                'The js "selection.js" was simplified to use "common-dialog.js", that should be added to the templates. You should check your theme. Matching templates: {json}', // @translate
                ['json' => json_encode($resultDiff, 448)]
            );
            $logger->warn($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        } else {
            $message = new PsrMessage(
                'The js "selection.js" was simplified to use "common-dialog.js", that should be added to the templates. You should check your theme.' // @translate
            );
            $logger->err($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }
    }

    $searchFields = $settings->get('advancedsearch_search_fields');
    if ($searchFields !== null) {
        $searchFields[] = 'common/advanced-search/selections';
        $settings->set('advancedsearch_search_fields', $searchFields);
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $v = $siteSettings->get('selection_placement_button', []);
        $siteSettings->set('selection_placement_button', array_diff($v, ['block/items', 'block/media', 'block/item_sets']));
        $v = $siteSettings->get('selection_placement_list', []);
        $siteSettings->set('selection_placement_list', array_diff($v, ['block/items', 'block/media', 'block/item_sets']));
    }

    $message = new PsrMessage(
        'It is now possible to display a flat list of named selections of resources.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `selection`
        ADD `is_validated` TINYINT(1) NULL AFTER `is_public`,
        ADD INDEX `is_public` (`is_public`),
        ADD INDEX `is_validated` (`is_validated`);
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Throwable $e) {
        // Skip.
    }

    // Just a move of columns.
    $sql = <<<'SQL'
        ALTER TABLE `selection_resource`
        CHANGE `selection_id` `selection_id` int(11) NULL AFTER `id`,
        CHANGE `owner_id` `owner_id` int(11) NOT NULL AFTER `resource_id`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Throwable $e) {
        // Skip.
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->set('selection_mode', 'single', $siteId);
        $siteSettings->set('selection_disposition', $siteSettings->get('selection_browse_disposition', 'flat', $siteId) ?: 'hierarchy', $siteId);
        $siteSettings->delete('selection_browse_disposition', $siteId);
    }

    $message = new PsrMessage(
        'It is now possible to define the main mode of process by site: the user can manage a single selection or multiple selections.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to publish selection and to moderate them.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Many urls for selections was modified, so check the menu and the theme if customized.' // @translate
    );
    $messenger->addWarning($message);
}
