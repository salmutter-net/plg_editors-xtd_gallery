# Gallery Editor Button (plg_editors-xtd_gallery)

Joomla 4/5/6 `editors-xtd` plugin that adds a **Galerie** button to backend editors. Clicking it opens a modal folder picker listing all subfolders of `/images/` (with image counts); selecting a folder inserts `{gallery}folder/path{/gallery}` at the cursor position.

## How it works together with plg_content_gallery

The two plugins are a pair:

- **plg_editors-xtd_gallery** (this plugin, *backend*) — only writes the `{gallery}…{/gallery}` tag into the article text. It renders nothing on its own.
- **[plg_content_gallery](https://github.com/salmutter-net/gallery)** (*frontend*) — parses the tag on `onContentPrepare`, reads all images from the given folder, generates responsive thumbnails (via [plg_system_imagetransformer](https://github.com/salmutter-net/plg_system_imagetransformer) if installed) and opens them in a PhotoSwipe v5 lightbox.

```
Backend editor                Frontend rendering
────────────────              ──────────────────────────────
[Galerie] button              {gallery}fotos/2024{/gallery}
      │                               │
 folder picker modal          plg_content_gallery parses tag
      │                               │
 inserts {gallery}…{/gallery} ──────► thumbnails + PhotoSwipe
```

## Requirements

- Joomla 4.3+ / 5.x / 6.x, PHP ≥ 8.1
- [plg_content_gallery](https://github.com/salmutter-net/gallery) must be installed and enabled — otherwise the inserted tags stay unparsed in the output.
- Optional: [plg_system_imagetransformer](https://github.com/salmutter-net/plg_system_imagetransformer) for generated/responsive image variants (recommended).

## Installation

1. Download the latest release zip and install via **System → Install → Extensions** (com_installer), or copy the files to `plugins/editors-xtd/gallery/` and use **Discover Install**.
2. Enable the plugin under **System → Plugins → "Button - Galerie"**.
3. Install and enable `plg_content_gallery` (group `content`) as well.

## Usage

1. Upload images into a subfolder of `/images/`, e.g. `/images/fotos/2024/` (nested folders are supported and shown indented in the picker).
2. In the article editor, click the **Galerie** button below the editor area.
3. Select the folder — `{gallery}fotos/2024{/gallery}` is inserted at the cursor.
4. Thumbnail size, full-image size, srcset widths, images per row and thumbnail max height are configured in `plg_content_gallery`'s plugin parameters.

## Technical notes

- The modal content is served by the plugin itself via `com_ajax` (`onAjaxGallery`) — no component is required. The endpoint is admin-only and token-protected (`Session::checkToken('get')`).
- Insertion uses `Joomla.editors.instances[e_name].replaceSelection()`, which works with TinyMCE, CodeMirror and the plain "none" editor.
- Updates ship through Joomla's update server mechanism (`update.xml` in this repo).
