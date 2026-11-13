# Changelog

## 0.2.1
- Fix: folder image counts showed 0 on hosts where `glob()` fails (e.g. open_basedir) — counts now collected during the same directory iteration that builds the tree

## 0.2.0
- Restructured to namespaced service-provider layout (services/provider.php + src/) for J5.1+/J6 compatibility
- Button registration via `onEditorButtonsSetup` + `ButtonsRegistry` (fixes missing button on J5.1+); legacy `onDisplay` kept for J4.x
- Modal folder picker UX: live search filter, collapsible tree, full-path breadcrumbs, image counts, empty folders greyed out, styled to match Joomla admin UI
- Media root folder read from `com_media` `image_path` param (fallback `images`)

## 0.1.0
- Initial release: "Galerie" editor button with modal /images folder picker (com_ajax, no component needed)
- Inserts `{gallery}path{/gallery}` at cursor position
- en-GB + de-DE language files
