Selection (module for Omeka S)
==============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Selection] is a module for [Omeka S] that allows any users to store and to
share one or multiple selections and the same for any visitor via local session.

Each selections can be saved with a label to simplify management and it is
possible to create a directory-like hierarchy to arrange resources. The
selection can be dynamic too when a search query is used.

Furthermore, when the module [Bulk Export] is installed, it is possible to
export them instantly to common formats, included common spreadsheet formats.

The selection is saved in a cookie for anonymous visitor, so anybody can create
a selection. When the user is authenticated, in particular as a [Guest], the
selections is saved in the database and available permanently. Only
authenticated users can share a selection.


Installation
------------

See general end user documentation for [installing a module].

The module [Common] must be installed first.

The optional module [Guest] can be used, but Selection is available for
anonymous users too. The module is integrated with module [Bulk Export] too.

If you use an old theme, you can install [Blocks Disposition] too.

You may use the release zip to install it or clone the source via git.

* From the zip

Download the last release [Selection.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Selection`.

```sh
cd modules
git clone https://gitlab.com/Daniel-KM/Omeka-S-module-Selection Selection
```

Then install it like any other Omeka module and follow the config instructions.

* For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Selection/phpunit.xml --testdox
```


Usage
-----

URL scheme:

| URL                              | Purpose                                    |
|----------------------------------|--------------------------------------------|
| `/s/:slug/selection`             | Public list of all public selections.      |
| `/s/:slug/selection/{id}`        | Public view of a published selection.      |
| `/s/:slug/guest/selection`       | Personal browse: own (db) or session.      |
| `/s/:slug/guest/selection/{id}`  | Personal view: own selection.              |
| `/s/:slug/guest/favorites`       | Personal favorites (single starred basket).|

The personal area lives entirely under `/guest/*` regardless of whether the
[Guest] module is installed; anonymous visitors are served from session
storage, authenticated users from the database. AJAX/operations endpoints
(`add`, `toggle`, `include`, `exclude`, `move`, `reorder`, `reset`, etc.)
share the public `/selection` and `/selection/{id}` prefixes.

To fill a selection, the resource blocks "Selection button" or "Selection
Select" can be used. Other resource blocks allow to display the user list
of selections. On a click, the item is added to the selection or in a
specific group, or removed.

A resource can be selected in multiple selections, but only once by selection.


Two-axis configuration
----------------------

Selection has two orthogonal features, each toggled per role (anonymous
visitor and authenticated user):

| Axis        | Site setting                       | Default (visitor / user) | What it does |
|-------------|------------------------------------|--------------------------|--------------|
| Favorites   | `selection_favorites_visitor` / `_user` | off / on            | Single starred basket per role. Star button on resources, dedicated `/favorites` page. |
| Selections  | `selection_selections_visitor` / `_user` | off / on            | Multiple named lists. Multi-select dropdown on resources, `/selection` index page. |

Other related settings:

| Setting                          | Default        | Effect |
|----------------------------------|----------------|--------|
| `selection_disposition`          | `flat`         | Per-list rendering: `flat`, `grouped`, `hierarchy`. |
| `selection_convert_auto`         | `true`         | Silent merge of anonymous data on login. See below. |
| `selection_label_favorites`      | `My favorites` | Title of `/guest/favorites` page. |
| `selection_label_selections`     | `Selections`   | Title of `/selection` index page. |

### Use cases

| Goal                                  | Favorites visitor | Favorites user | Selections visitor | Selections user |
|---------------------------------------|-------------------|----------------|--------------------|-----------------|
| Simple cart everywhere                | on                | on             | off                | off             |
| Research tool (lists only)            | off               | off            | off / on           | on              |
| Hybrid (default basket + power users) | on                | on             | off                | on              |
| Public site, no saving                | off               | off            | off                | off             |

### Buttons

- `selectionButtonFavorite($resource)`: explicit star button (favorites axis).
- `selectionButton($resource)`: multi-select dropdown (selections axis only).
  Returns nothing if selections are disabled.
- A theme places either or both, depending on the site configuration. There is
  no automatic dispatch — pick the buttons that match the configured axes.

The same view helpers are exposed as resource-page block layouts:
`selectionButtonFavorite` and `selectionButton`.

### Pages

- `/s/:slug/guest/favorites` — single starred basket (own for users, session
  for anonymous). Not shareable. Rendered as a flat list. Only the exclude
  action is offered.
- `/s/:slug/selection` — public index of all public selections (read-only).
- `/s/:slug/guest/selection` — personal browse. Available only when the
  selections axis is enabled. If only the favorites axis is enabled, this URL
  redirects to `/guest/favorites`.

### Conversion at login

When an anonymous visitor logs in with non-empty session data, the conversion
follows this matrix:

| User favorites | User selections | `convert_auto` | Behavior |
|----------------|-----------------|----------------|----------|
| off            | off             | -              | Session discarded. |
| on             | off             | -              | Silent merge into the user default favorites basket. |
| on             | on              | true           | Silent merge into favorites basket (same as above). |
| on             | on              | false          | Modal dialog: new selection / merge into existing / discard. |
| off            | on              | -              | Modal dialog (always). |

Dismissing the dialog sets a per-session flag. The prompt is not shown again
during the current session, but reappears at the next login while the
favorites still contain items.

### Backward compatibility

Legacy settings are migrated on upgrade:

- 3.4.18: `selection_mode` and `selection_disable_anonymous` →
  `selection_mode_visitor` / `selection_mode_user`.
- 3.4.19: `selection_mode_visitor` / `selection_mode_user` /
  `selection_my_favorites` / `selection_label_visitor` / `selection_label_user`
  → the two-axis settings above. Disposition is bumped to `hierarchy` when the
  legacy mode was `nested`.
- 3.4.20: routes were clarified. The personal area is now exclusively under
  `/guest/selection` and `/guest/favorites`. Top-level `/favorites` and
  `/selection/browse` were removed. Update bookmarks, theme overrides and
  external links accordingly. Site settings are not affected.

No manual action is required for site settings, but theme overrides that
emitted legacy URLs must be adapted.


Integration in a theme
----------------------

The public list of selections is viewable at /s/my-site/selection. Personal
browse is at /s/my-site/guest/selection (available regardless of module Guest).

### Themes with resource blocks

Add the block for the favorites in the pages you need.

### Themes without resource blocks (before Omeka S v4)

It is recommended to edit the theme directly to include the selection besides
the item and the media, in particular in the item/show and the item/browse views.

- Display the button to select current resource:

```php
echo $this->selectionButton();
```

- Display a simple list of selected resources:

```php
echo $this->selectionList();
```

- Display the link to the board of selected resources:

```php
echo $this->selectionLinkBrowse();
```

- Display the public selections of all users.

```php
echo $this->selectionBrowse($query, $options);
```


Integration via api (js)
------------------------

The api is the standard one and is available internally and via the endpoint:

1. List selected resources of an owner

  The available keys are : `owner_id`, `resource_id`, `selection_id`, `selection_label`.
  To get the selected resources that are not stored in a specific selection, use
  `selection_id=0`. Via endpoint, selected resources are not visible, so the
  credentials are required.

```sh
curl -X GET -H 'Accept: application/json' -i 'https://example.org/api/selection_resources?key_identity=xxx&key_credential=yyy&&pretty_print=1'
```

```php
$selectedResources = $this->api()->search('selection_resources', ['owner_id' => 1])->getContent();
```

2. Create a selection with a resource

  The example below creates a selection for resource #2 and saves it in the
  selection list with the label test. If the selection does not exist, it is
  automatically created. If the selection is not defined, the selected resource
  is saved but not attached to a specific selection. Via endpoint, the user is
  set via the credential.

  Warning: it is not possible currently to add a resource that is already
  selected  via the key `selection_resources`, so check the list of existing
  selected resources before doing the request.

```sh
curl -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -i 'https://example.org/api/selection_resources?key_identity=xxx&key_credential=yyy&pretty_print=1' --data '{"o:resource":{"o:id":2},"o:selection":{"o:label":"test"}}'
```

```php
$selectedResource = $this->api()->create('selection_resources', [
    'o:owner' => ['o:id' => 1],
    'o:selection' => ['o:label' => 'test'],
)->getContent();
```

3. Create a dynamic selection

  The example below uses the query "resource_class_id=1" to create a dynamic
  selection:

```php
$selectedResource = $this->api()->create('selections', [
    'o:owner' => ['o:id' => 1],
    'o:label' => 'my query',
    'o:comment' => 'a comment',
    'o:search_query' => 'resource_class_id=1'
)->getContent();
```

  Important: when a selection is converted into a search query, all selection
  resources are removed. The type change of a selection may be forbidden in a
  future version.

4. Add a selection of resources in bulk in a specific selection.

  Here, the api key is `selections`, so it avoids to use the key `selection_resources`
  multiple times, in particular via endpoint. A check is done on the list of
  resources, so a resource can be saved only if it is visible by the user and
  there won't be error if the resource is already listed in the specified
  selection. Nevertheless, the full list should be used, because the existing
  other selected resources will be removed.

```sh
curl -X PATCH -H 'Content-Type: application/json' -H 'Accept: application/json' -i 'https://example.org/api/selections/1?key_identity=xxx&key_credential=yyy&pretty_print=1' --data '{"resources":[3,4,5]}'
```

```php
$selection = $this->api()->update('selections', 1, [
    'o:comment' => 'the updated list of resources',
    'o:resources' => [['o:id' => 3], ['o:id' => 4], ['o:id' => 5]],
], [], ['isPartial' => true])->getContent();
```

5. Simplified management

  To simplify management of selection of resources in bulk without check, the
  shortcut key `resources` is available in the json payload or in the internal
  api. It allows to specify what to do for each resource: add, delete, or toggle.

```php
    // Replace the existing selected resources by this new list:
    'resources' => [3, 4, 5],

    // Fine tune the update of the resources:
    'resources' => [
        // Replace all the selected resources (default behavior).
        'replace' => [3, 4, 5],
        // Add this selection of resources to the selection.
        'append' => [6, 7, 8],
        // Remove these resources from the selection.
        'remove' => [9, 10]
        // Toggle the resources: if a resource is in a selection, it is removed,
        // else it is added.
        'toggle' => [11, 12]
    ],
```

  Important: `'resources' => []` or `'resources' => ['remove' => []]` means to
  remove all selected resources of the selection. Set it to `null` if you really
  need the key.


TODO
----

- [ ] Important: Finalize process of multiple selections against container and remove useless code.
- [ ] Make selection not nullable in entity SelectionResource.
- [ ] Make owner nullable.
- [ ] Multiple selection of resources at once when a selection is not specified. Use a default selection in all cases?
- [x] Multiple selections with a title for each.
- [ ] Multiple selections (view pages).
- [ ] Integrate visibility in order to share selections.
- [ ] Allow to query multiple ids (owner id, resource id, selection id) in the api.
- [x] Add a modified date of a selection (from the selection itself, not only from the list of selected resources).
- [ ] Add a third view with a button to let user choose disposition.
- [ ] Make `selection_disposition` (flat / grouped / hierarchy) configurable per role (visitor vs user) and possibly per axis (favorites vs selections). Currently a single global value for the whole site.
- [ ] Model the three basket modes (single / multiple / hierarchy) as an explicit choice rather than letting them emerge implicitly from the combination of favorites/selections toggles and `selection_disposition`.
- [ ] For moderation, manage the case where the user modify the list of resources or changes the label.
- [ ] Add options to display selected resources (show) as item/browse.
- [ ] Add a block layout to render a specific public selection by id (currently only via route `site/selection-id`).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Biblibre, 2016-2017 (see [Biblibre])
* Copyright Daniel Berthereau, 2017-2026 (see [Daniel-KM] on GitLab)

This module was initially based on the fork of the module [Basket] from BibLibre
and restructured and improved for various projects, like [Collections de la Maison de Salins],
[Bibliothèque numérique] of Université Paris Sciences et Lettres ([PSL]), and
[Collections du musée de Bretagne] (upcoming).


[Selection]: https://gitlab.com/Daniel-KM/Omeka-S-module-Selection
[Omeka S]: https://omeka.org/s
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Guest]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest
[Bulk Export]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Selection/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Basket]: https://github.com/BibLibre/Omeka-S-module-Basket
[Biblibre]: https://github.com/biblibre
[GitLab]: https://gitlab.com/Daniel-KM
[Collections de la Maison de Salins]: https://collections.maison-salins.fr
[Bibliothèque numérique]: https://bibnum.explore.psl.eu
[PSL]: https://psl.eu
[Collections du musée de Bretagne]: http://www.collections.musee-bretagne.fr
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
