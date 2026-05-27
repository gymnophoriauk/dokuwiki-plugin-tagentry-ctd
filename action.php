<?php
/**
 * DokuWiki Plugin tagentry (Action Component)
 *
 * Assign tags by clicking in edit mode
 *
 * Original authors:
 *   Robin Gareus <robin@gareus.org>
 *   Trailjeep <trailjeep@gmail.com>
 *
 * Updated by Rob How 2026 for DokuWiki 2025-05-14a "Librarian" compatibility
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\File\PageResolver;

class action_plugin_tagentry extends ActionPlugin {

    /**
     * Register the event handlers
     */
    public function register(EventHandler $controller) {
        $controller->register_hook(
            'FORM_EDIT_OUTPUT', 'BEFORE', $this,
            'handle_editform_output'
        );
    }

    /**
     * Create the additional fields for the edit form.
     *
     * Uses the new dokuwiki\Form\Form API (FORM_EDIT_OUTPUT event).
     */
    public function handle_editform_output(Event $event, $param) {
        /** @var \dokuwiki\Form\Form $form */
        $form = $event->data;

        // Find the first submit button position to insert before it
        $pos = $form->findPositionByAttribute('type', 'submit');
        if (!$pos) {
            return; // no submit button found (e.g. source view)
        }

        // Check if we are in a section edit (prefix/suffix present)
        // In new Form API, hidden fields are accessed differently
        // We need to check if this is a full-page edit, not a section edit
        // Section edits should still show tags since they may contain the tag syntax

        // Get all tags
        $tagns = $this->getConf('namespace');
        $thlp = plugin_load('helper', 'tag');
        if ($thlp) {
            if ($this->getConf('tagsrc') == 'Pagenames in tag NS') {
                $tagnst = $thlp->getConf('namespace');
                if (!empty($tagnst)) {
                    $tagns = $tagnst;
                }
            }
        }
        if ($this->getConf('tagsrc') == 'All tags' && $thlp) {
            $alltags = array_map('trim', idx_getIndex('subject', '_w'));
        } else {
            $alltags = $this->_getpages($tagns);
        }

        // Get already assigned tags for this page by parsing the wiki text
        $assigned = false;

        // In the new Form API, we need to find the textarea element
        // and extract its content to parse for existing tags
        $textPos = $form->findPositionByType('textarea');
        if ($textPos !== false) {
            $textElement = $form->getElementAt($textPos);
            if ($textElement !== null) {
                $wikipage = $textElement->val();
                if (!empty($wikipage)) {
                    if (preg_match('@\{\{tag>(.*?)\}\}@', $wikipage, $m)) {
                        $assigned = explode(' ', $m[1]);
                    }
                }
            }
        }

        if (!is_array($assigned)) {
            // Fall back to metadata from the previously saved version
            global $ID;
            $meta = p_get_metadata($ID);
            $assigned = isset($meta['subject']) ? $meta['subject'] : [];
        }

        $options = [
            'blacklist' => explode(' ', $this->getConf('blacklist')),
            'assigned'  => $assigned,
        ];

        $out = '<div id="plugin__tagentry_wrapper">';
        $out .= $this->_format_tags($alltags, $options);
        $out .= '</div>';

        // Insert the tag entry HTML before the submit button
        $form->addHTML($out, $pos);
    }

    /**
     * Callback function for dokuwiki search()
     *
     * Build a list of tags from the tag namespace
     * $opts['ns'] is the namespace to browse
     */
    public function _tagentry_search_tagpages(&$data, $base, $file, $type, $lvl, $opts) {
        if ($type == 'd') {
            return true;
        } elseif ($type == 'f' && !preg_match('#\.txt$#', $file)) {
            return false;
        }

        $id = pathID($file);
        if (getNS($id) != $opts['ns']) {
            return false;
        }
        if (isHiddenPage($id)) {
            return false;
        }
        if ($type == 'f' && auth_quickaclcheck($id) < AUTH_READ) {
            return false;
        }
        $data[] = noNS($id);
        return true;
    }

    /**
     * List all pages in the namespace.
     *
     * @param string $tagns namespace to search
     * @return array list of tag names
     */
    public function _getpages($tagns = 'wiki:tags') {
        global $conf;
        require_once(DOKU_INC . 'inc/search.php');
        $data = [];
        search($data, $conf['datadir'], [$this, '_tagentry_search_tagpages'], ['ns' => $tagns]);
        return $data;
    }

    /**
     * Clip a string to a maximum length.
     *
     * @param string $s the string to clip
     * @param int $len maximum length
     * @return string
     */
    public function clipstring($s, $len = 22) {
        return substr($s, 0, $len) . ((strlen($s) > $len) ? '..' : '');
    }

    /**
     * Escape a string for safe use in JavaScript.
     *
     * @param string $o the string to escape
     * @return string
     */
    public function escapeJSstring($o) {
        return str_replace(
            "\n", '\\n',
            str_replace("\r", '',
                str_replace("'", "\\'",
                    str_replace('\\', '\\\\', $o)
                )
            )
        );
    }

    /**
     * Case-insensitive in_array().
     *
     * @param string $needle
     * @param array $haystack
     * @return bool
     */
    public function in_iarray($needle, $haystack) {
        if (!is_array($haystack)) {
            return false;
        }
        foreach ($haystack as $t) {
            if (strcasecmp($needle, $t) == 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render and return the tag-select box.
     *
     * @param array $alltags array of tags to display
     * @param array $options options including blacklist and assigned tags
     * @return string XHTML
     */
    public function _format_tags($alltags, $options) {
        $rv = '';
        if (!is_array($alltags) || count($alltags) < 1) {
            return $rv;
        }

        $rv .= '<div class="taglist">';

        // Sort tags naturally (case-insensitive)
        natcasesort($alltags);

        $limit = (int)$this->getConf('limit');
        $i = 0;
        foreach ($alltags as $tagname) {
            // Skip blacklisted tags
            if (is_array($options['blacklist']) && $this->in_iarray($tagname, $options['blacklist'])) {
                continue;
            }

            $i++;
            if ($limit > 0 && $i > $limit) {
                break;
            }

            $rv .= '<label><input type="checkbox" id="plugin__tagentry_cb' . hsc($tagname) . '"';
            $rv .= ' value="1" name="' . hsc($tagname) . '"';
            if ($this->in_iarray($tagname, $options['assigned'])) {
                $rv .= ' checked="checked"';
            }

            $rv .= ' onclick="tagentry_clicktag(\'' . $this->escapeJSstring($tagname) . '\', this);"';
            $rv .= ' /> ' . $this->_getTagTitle($tagname);
            $rv .= '</label>' . "\n";
        }

        $rv .= '</div>';
        return $rv;
    }

    /**
     * Return the heading title of a tag page, or the clipped tag name.
     *
     * Uses the new PageResolver class instead of the deprecated resolve_pageid().
     *
     * @param string $tagname the tag name without namespace
     * @return string title of the tag page or clipped tag name
     */
    public function _getTagTitle($tagname) {
        global $conf;
        global $ID;

        if ($conf['useheading']) {
            $tagplugin = plugin_load('helper', 'tag');
            if (plugin_isdisabled('tag') || !$tagplugin) {
                msg('The Tag Plugin must be installed to display tagentry.', -1);
                return $this->clipstring($tagname);
            }

            // Use the new PageResolver instead of deprecated resolve_pageid()
            $ns = $tagplugin->getConf('namespace');
            if (empty($ns)) {
                $ns = 'tag';
            }
            $resolver = new PageResolver($ns . ':start');
            $id = $resolver->resolveId($tagname);
            if (page_exists($id)) {
                return p_get_first_heading($id, false);
            }
        }
        return $this->clipstring($tagname);
    }
}
