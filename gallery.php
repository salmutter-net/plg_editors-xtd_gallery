<?php
/**
 * @package     plg_editors-xtd_gallery
 * @author      Philipp Salmutter
 * @license     GNU General Public License version 2 or later
 *
 * Adds an "Gallery" editor button (editors-xtd) that opens a modal folder
 * picker for /images and inserts {gallery}folder{/gallery} into the editor.
 * The modal content is served by the plugin itself via com_ajax
 * (onAjaxGallery), so no component is required.
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Event\SubscriberInterface;

defined('_JEXEC') or die('Restricted access');

class PlgEditorsXtdGallery extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onDisplay'     => 'onDisplay',
            'onAjaxGallery' => 'onAjaxGallery',
        ];
    }

    /**
     * Renders the editor button.
     * $name is the editor instance name (e.g. "jform_articletext").
     * Legacy events may arrive as an Event object — unwrap if needed.
     */
    public function onDisplay($name)
    {
        if ($name instanceof \Joomla\Event\Event) {
            $args = $name->getArguments();
            $name = isset($args[0]) ? $args[0] : '';
        }

        $app = Factory::getApplication();
        if (!$app->isClient('administrator') || empty($name)) {
            return null;
        }

        $doc = $app->getDocument();
        if (method_exists($doc, 'getWebAssetManager')) {
            $doc->getWebAssetManager()->useScript('core');
        }
        HTMLHelper::_('behavior.modal');

        $link = 'index.php?option=com_ajax&plugin=gallery&group=editors-xtd&format=html&tmpl=component'
            . '&e_name=' . urlencode($name)
            . '&' . Session::getFormToken() . '=1';

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

    /**
     * com_ajax endpoint: outputs the folder list inside the modal iframe.
     */
    public function onAjaxGallery()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            $app->close();
        }

        if (!Session::checkToken('get')) {
            echo Text::_('JINVALID_TOKEN');
            $app->close();
        }

        $eName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $app->input->getString('e_name', ''));

        $root = JPATH_ROOT . DIRECTORY_SEPARATOR . 'images';
        $dirs = [];

        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                    $dirs[$rel] = true;
                }
            }
            ksort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:1rem;background:#fff}
            h1{font-size:1.1rem;margin:0 0 .75rem}
            ul{list-style:none;margin:0;padding:0}
            li{margin:0;padding:0}
            a.folder{display:flex;justify-content:space-between;gap:1rem;padding:.5rem .75rem;color:#0d6efd;text-decoration:none;border-radius:4px}
            a.folder:hover{background:#f0f4ff}
            .count{color:#888;font-size:.85em}
            .empty{color:#a00;padding:1rem 0}
        </style></head><body>';
        echo '<h1>' . Text::_('PLG_EDITORS-XTD_GALLERY_SELECT_FOLDER') . '</h1>';

        if (empty($dirs)) {
            echo '<p class="empty">' . Text::_('PLG_EDITORS-XTD_GALLERY_NO_FOLDERS') . '</p>';
        } else {
            $imgExt = ['jpg', 'jpeg', 'gif', 'png', 'webp'];
            echo '<ul>';
            foreach (array_keys($dirs) as $rel) {
                $depth = substr_count($rel, '/');
                $count = 0;
                foreach (glob($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . DIRECTORY_SEPARATOR . '*.*') ?: [] as $f) {
                    if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $imgExt, true)) {
                        $count++;
                    }
                }
                $label = str_repeat('&nbsp;&nbsp;', $depth) . htmlspecialchars(basename($rel));
                echo '<li><a class="folder" href="#" data-path="' . htmlspecialchars($rel) . '" onclick="return galleryInsert(this)">'
                    . '<span>' . $label . '</span><span class="count">' . $count . ' ' . Text::_('PLG_EDITORS-XTD_GALLERY_IMAGES') . '</span></a></li>';
            }
            echo '</ul>';
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
        </script></body></html>';

        $app->close();
    }
}
