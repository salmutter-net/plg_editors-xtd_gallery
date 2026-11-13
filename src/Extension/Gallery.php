<?php
/**
 * @package     plg_editors-xtd_gallery
 * @author      Philipp Salmutter
 * @license     GNU General Public License version 2 or later
 *
 * Adds a "Gallery" editor button (editors-xtd) that opens a modal folder
 * picker for /images and inserts {gallery}folder{/gallery} into the editor.
 * The modal content is served by the plugin itself via com_ajax
 * (onAjaxGallery), so no component is required.
 */

namespace Joomla\Plugin\EditorsXtd\Gallery\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Gallery extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onEditorButtonsSetup' => 'onEditorButtonsSetup',
            'onDisplay'            => 'onDisplay',
            'onAjaxGallery'        => 'onAjaxGallery',
        ];
    }

    /**
     * Joomla 5.1+/6: register the button via the ButtonsRegistry.
     */
    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        if (\in_array($this->_name, $event->getDisabledButtons(), true)) {
            return;
        }

        $link = $this->buildModalLink($event->getEditorId());
        if ($link === null) {
            return;
        }

        $this->loadLanguage();

        $event->getButtonsRegistry()->add(new Button(
            $this->_name,
            [
                'action' => 'modal',
                'text'   => Text::_('PLG_EDITORS-XTD_GALLERY_BUTTON'),
                'name'   => $this->_type . '_' . $this->_name,
                'icon'   => 'images',
                'link'   => $link,
            ],
            [
                'popupType'  => 'iframe',
                'textHeader' => Text::_('PLG_EDITORS-XTD_GALLERY_BUTTON'),
                'iconHeader' => 'icon-images',
            ]
        ));
    }

    /**
     * Legacy (Joomla 4.x): return a CMSObject button.
     * $name is the editor instance name (e.g. "jform_articletext").
     */
    public function onDisplay($name)
    {
        if ($name instanceof \Joomla\Event\Event) {
            $args = $name->getArguments();
            $name = isset($args[0]) ? $args[0] : '';
        }

        $link = $this->buildModalLink((string)$name);
        if ($link === null) {
            return null;
        }

        $button = new CMSObject();
        $button->modal   = true;
        $button->class   = 'btn';
        $button->link    = $link;
        $button->text    = Text::_('PLG_EDITORS-XTD_GALLERY_BUTTON');
        $button->name    = 'image gallery';
        $button->icon    = 'image gallery';
        $button->options = "{handler:'iframe', size:{x:800,y:500}}";

        return $button;
    }

    private function buildModalLink(string $editorName): ?string
    {
        if (!$this->getApplication()->isClient('administrator') || $editorName === '') {
            return null;
        }

        return 'index.php?option=com_ajax&plugin=gallery&group=editors-xtd&format=html&tmpl=component'
            . '&e_name=' . urlencode($editorName)
            . '&' . Session::getFormToken() . '=1';
    }

    /**
     * com_ajax endpoint: outputs the folder list inside the modal iframe.
     */
    public function onAjaxGallery()
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator')) {
            $app->close();
        }

        if (!Session::checkToken('get')) {
            echo Text::_('JINVALID_TOKEN');
            $app->close();
        }

        $eName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $app->getInput()->getString('e_name', ''));

        $mediaPath = ComponentHelper::getParams('com_media')->get('image_path', 'images');
        $root = JPATH_ROOT . DIRECTORY_SEPARATOR . trim(str_replace('/', DIRECTORY_SEPARATOR, $mediaPath), '/\\');
        $imgExt = ['jpg', 'jpeg', 'gif', 'png', 'webp'];

        // Build a nested tree of directories, each with its direct image count.
        // Counts are collected during the same iteration — no glob(), which can
        // silently return false under open_basedir restrictions on some hosts.
        $tree = [];
        $nodeByPath = [];
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if ($file->isDir()) {
                    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
                    $parts = explode('/', $rel);
                    $ref = &$tree;
                    foreach ($parts as $i => $part) {
                        if (!isset($ref[$part])) {
                            $ref[$part] = ['path' => implode('/', array_slice($parts, 0, $i + 1)), 'count' => 0, 'children' => []];
                        }
                        if ($i === count($parts) - 1) {
                            $nodeByPath[$rel] = &$ref[$part];
                        }
                        $ref = &$ref[$part]['children'];
                    }
                    unset($ref);
                } elseif (in_array(strtolower($file->getExtension()), $imgExt, true)) {
                    $relDir = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPath(), strlen($root) + 1));
                    if (isset($nodeByPath[$relDir])) {
                        $nodeByPath[$relDir]['count']++;
                    }
                }
            }
            unset($ref, $nodeByPath);
            ksort($tree, SORT_NATURAL | SORT_FLAG_CASE);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<style>
            *{box-sizing:border-box}
            html,body{height:100%}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;margin:0;background:#f5f7fb;font-size:.875rem;color:#22303c;display:flex;flex-direction:column}
            .picker-head{position:sticky;top:0;z-index:5;background:#fff;padding:.9rem 1.1rem .75rem;border-bottom:1px solid #e4e9f0;box-shadow:0 2px 8px rgba(20,40,70,.05)}
            .picker-head h1{font-size:1.05rem;font-weight:600;margin:0 0 .6rem;color:#1a2a3a}
            .filter-wrap{position:relative}
            .filter-wrap svg{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);width:15px;height:15px;fill:#9aa7b5;pointer-events:none}
            #filter{width:100%;padding:.55rem .75rem .55rem 2.1rem;border:1px solid #d5dce4;border-radius:6px;font-size:.875rem;background:#fbfcfe;color:#22303c;outline:none;transition:border-color .15s,box-shadow .15s}
            #filter:focus{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.15);background:#fff}
            #filter::placeholder{color:#9aa7b5}
            .picker-body{flex:1;overflow-y:auto;padding:.5rem .6rem 1rem}
            ul{list-style:none;margin:0;padding:0}
            ul.children{padding-left:1.35rem;display:none;border-left:1px solid #e4e9f0;margin-left:1.35rem}
            li.open > ul.children{display:block}
            li.dir{margin:2px 0}
            .row{display:flex;align-items:center;gap:.55rem;padding:.45rem .6rem;border-radius:6px;cursor:default}
            .row:hover{background:#e9f0fd}
            .toggle{width:1.35rem;height:1.35rem;flex:none;display:inline-flex;align-items:center;justify-content:center;border:0;background:none;cursor:pointer;color:#7d8ca0;padding:0;border-radius:4px}
            .toggle:hover{background:rgba(13,110,253,.12);color:#0d6efd}
            .toggle svg{width:10px;height:10px;fill:currentColor;transition:transform .15s}
            li.open > .row > .toggle svg{transform:rotate(90deg)}
            .toggle.leaf{visibility:hidden;cursor:default}
            .icon-folder{width:18px;height:18px;flex:none;fill:#f5b544}
            a.name .icon-folder{fill:#f5b544}
            .name{flex:1;min-width:0;display:flex;align-items:center;gap:.5rem;color:#0d6efd;text-decoration:none;font-weight:500}
            .name-text{min-width:0}
            .name .crumb{display:block;font-weight:400;font-size:.72rem;color:#9aa7b5;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:1px}
            a.name:hover{text-decoration:underline}
            .name.static{color:#4a5a6a;cursor:default;font-weight:500}
            .count{flex:none;font-size:.7rem;font-weight:600;color:#5f7183;background:#e9eef5;border-radius:999px;padding:.15rem .55rem;white-space:nowrap}
            a.name + .count{background:#dbeafe;color:#0d6efd}
            li.is-empty > .row{opacity:.4}
            .empty-msg{color:#a4262c;padding:2rem 1rem;text-align:center}
            .no-match{display:none;color:#7d8ca0;padding:1.5rem;text-align:center;font-style:italic}
        </style></head><body>';
        echo '<div class="picker-head">';
        echo '<h1>' . Text::_('PLG_EDITORS-XTD_GALLERY_SELECT_FOLDER') . '</h1>';

        if (empty($tree)) {
            echo '<p class="empty-msg">' . Text::_('PLG_EDITORS-XTD_GALLERY_NO_FOLDERS') . '</p>';
        } else {
            echo '<input id="filter" type="search" placeholder="' . Text::_('PLG_EDITORS-XTD_GALLERY_FILTER') . '" autofocus>';
            echo '<ul id="tree">';
            $this->renderTree($tree);
            echo '</ul><p class="no-match" id="noMatch">' . Text::_('PLG_EDITORS-XTD_GALLERY_NO_MATCH') . '</p>';
        }

        echo '<script>
            function galleryInsert(el) {
                var tag = "{gallery}" + el.getAttribute("data-path") + "{/gallery}";
                var parent = window.parent;
                if (parent.Joomla && parent.Joomla.editors && parent.Joomla.editors.instances[' . json_encode($eName) . ']) {
                    parent.Joomla.editors.instances[' . json_encode($eName) . '].replaceSelection(tag);
                }
                if (parent.Joomla && parent.Joomla.Modal) {
                    var modal = parent.Joomla.Modal.getCurrent();
                    if (modal) { modal.close(); }
                }
                return false;
            }
            document.addEventListener("click", function (e) {
                var t = e.target.closest(".toggle");
                if (t && !t.classList.contains("leaf")) {
                    t.closest("li").classList.toggle("open");
                    e.preventDefault();
                }
            });
            var filterInput = document.getElementById("filter");
            if (filterInput) {
                filterInput.addEventListener("input", function () {
                    var q = this.value.trim().toLowerCase();
                    var items = document.querySelectorAll("#tree > li");
                    var noMatch = document.getElementById("noMatch");
                    var any = false;
                    items.forEach(function (li) {
                        var hit = applyFilter(li, q);
                        li.style.display = hit ? "" : "none";
                        if (hit) { any = true; }
                    });
                    noMatch.style.display = (q && !any) ? "block" : "none";
                });
            }
            function applyFilter(li, q) {
                // Returns true if this node or any descendant matches
                var path = li.getAttribute("data-path") || "";
                var self = !q || path.toLowerCase().indexOf(q) !== -1;
                var childHit = false;
                var kids = li.querySelectorAll(":scope > ul.children > li");
                kids.forEach(function (kid) {
                    var hit = applyFilter(kid, q);
                    kid.style.display = hit ? "" : "none";
                    if (hit) { childHit = true; }
                });
                if (q && childHit) {
                    li.classList.add("open");
                } else if (!q) {
                    li.classList.remove("open");
                }
                return self || childHit;
            }
        </script></body></html>';

        $app->close();
    }

    /**
     * Renders the nested folder tree for the modal picker.
     */
    private function renderTree(array $nodes): void
    {
        foreach ($nodes as $name => $node) {
            $hasKids = !empty($node['children']);
            if ($hasKids) {
                uksort($node['children'], 'strnatcasecmp');
            }
            $path   = htmlspecialchars($node['path']);
            $empty  = !$hasKids && $node['count'] === 0;
            $liCls  = $empty ? ' class="dir is-empty"' : ' class="dir"';
            $toggle = $hasKids ? '<button type="button" class="toggle" aria-hidden="true"><svg viewBox="0 0 10 10"><path d="M3 1l4 4-4 4z"/></svg></button>'
                               : '<span class="toggle leaf"></span>';
            $folder = '<svg class="icon-folder" viewBox="0 0 20 20"><path d="M2 4a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4z"/></svg>';
            $crumb  = $hasKids ? '<span class="crumb">' . $path . '</span>' : '';

            echo '<li' . $liCls . ' data-path="' . $path . '">';
            echo '<div class="row">' . $toggle;
            if ($node['count'] > 0) {
                echo '<a class="name" href="#" data-path="' . $path . '" onclick="return galleryInsert(this)" title="' . $path . '">'
                    . $folder . '<span class="name-text">' . htmlspecialchars($name) . $crumb . '</span>'
                    . '</a>';
            } else {
                echo '<span class="name static" title="' . $path . '">'
                    . $folder . '<span class="name-text">' . htmlspecialchars($name) . $crumb . '</span>'
                    . '</span>';
            }
            echo '<span class="count">' . $node['count'] . ' ' . Text::_('PLG_EDITORS-XTD_GALLERY_IMAGES') . '</span>';
            echo '</div>';
            if ($hasKids) {
                echo '<ul class="children">';
                $this->renderTree($node['children']);
                echo '</ul>';
            }
            echo '</li>';
        }
    }
}
