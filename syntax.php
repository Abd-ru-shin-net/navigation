<?php
/**
 * DokuWiki Plugin navigation (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Marek Ištvánek <marek.istvanek@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

require_once DOKU_INC.'inc/common.php';
require_once DOKU_INC.'inc/pageutils.php';
require_once DOKU_INC.'inc/search.php';
require_once 'Command.php';
require_once 'Content.php';
require_once 'CSS.php';
require_once 'DateTimeMode.php';
require_once 'Html.php';
require_once 'IPlugin.php';
require_once 'LevelItem.php';
require_once 'Metadata.php';
require_once 'Navigation.php';
require_once 'Parameter.php';
require_once 'RenderMode.php';

class syntax_plugin_navigation
    extends DokuWiki_Syntax_Plugin
    implements IPlugin
{
    /**
     * @return string Syntax mode type
     */
    public function getType() : string
    {
        return 'substition';
    }

    // /**
    //  * @return string Paragraph type
    //  */
    // public function getPType() : string
    // {
    //     return 'block';
    // }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() : int
    {
        return 10;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{Navigation\|.+?(?:}|\|.*?})', $mode, 'plugin_navigation');
    }

    /**
     * Handle matches of the navigation syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // {Navigation|Command[|Parameter1|...|ParameterN]}
        $match = trim($match, '{}');
        $items = explode('|', $match);
        $command = $items[1];
        $parameters = array_slice($items, 2);
        switch ($command)
        {
            case Command::treeMenu:
            case Command::list:
            case Command::tree:
            case Command::content:
                $data = $this->prepareTree($command, $parameters);
                break;
            case Command::lastTreeChange:
                $data = $this->getLastTreeChange($parameters);
                break;
            case Command::levelMenu:
                $data = Content::getLevelItems($this, $parameters);
                break;
        }
        $data[Parameter::command] = $command;
        return $data;
    }

    function prepareTree(string $command, array $parameters) : array
    {
        $inPage = $command !== Command::treeMenu;
        $namespace = $parameters[0] ??
            $inPage ?
                '.' : // current
                '';   // root
        if ($namespace === '.')
        {
            global $ID;
            list(Navigation::namespace => $namespace) = Ids::getNamespaceAndName($ID);
        }
        $levelsText = $parameters[1];
        $levels =
            !$levelsText &&
            $command === Command::list ?
                1 :
                intval($levelsText);
        $skippedIds = [];
        if ($command === Command::content)
        {
            global $ID;
            $skippedIds[] = $ID;
        }
        foreach ($parameters as $parameter)
        {
            if ($parameter[0] !== '-')
                continue;
            $id = substr($parameter, 1);
            if ($id === '.')
            {
                global $ID;
                $id = $ID;
            }
            $skippedIds[] = $id;
        }
        return Content::getTree($this, $inPage, $namespace, $levels, $skippedIds);
    }

    function getLastTreeChange(array $parameters) : array
    {
        global $ID;
        $mode = $parameters[0] ?? DateTimeMode::DateTime;
        $data = Content::getLastTreeChange($this, $ID);
        $data[Parameter::mode] = $mode;
        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode === RenderMode::xhtml)
        {
            $command = $data[Parameter::command];
            unset($data[Parameter::command]);
            switch ($command)
            {
                case Command::treeMenu:
                case Command::list:
                case Command::tree:
                case Command::content:
                    $this->renderTree($renderer, $data, $command !== Command::treeMenu);
                    break;
                case Command::link:
                    $this->renderLink($renderer);
                    break;
                case Command::lastTreeChange:
                    $this->renderLastTreeChange($renderer, $data);
                    break;
                case Command::levelMenu:
                    $this->renderLevelItems($renderer, $data);
                    break;
                default:
                    return false;
            }
            return true;
        }
        else if ($mode === RenderMode::metadata)
        {
            $renderer->meta[Metadata::navigation] = true;
            return true;
        }
        else
            return false;
    }

    function renderTree(Doku_Renderer $renderer, array &$data, bool $inPage)
    {
        if ($inPage)
        {
            $renderer->doc .= html_buildlist(
            $data,
            CSS::navigationList,
            'syntax_plugin_navigation::htmlListItem'
            );
        }
        // menu
        else
        {
            $renderer->doc .= html_buildlist(
                $data,
                CSS::navigationMenu,
                'syntax_plugin_navigation::htmlMenuItem',
                'syntax_plugin_navigation::htmlMenuLi'
                );
        }
    }

    function renderLink(Doku_Renderer $renderer)
    {
        global $ID;
        $id = $ID;
        $data = Ids::getNamespaceAndName($id);
        $isNamespace = $data[Navigation::isNamespace];
        if (!$isNamespace)
        {
            $namespaceId = Ids::getNamespaceId(($data[Navigation::namespace]));
            $namespacePageId = Ids::getNamespacePageId($namespaceId);
            $isNamespace = $id === $namespacePageId;
            if ($isNamespace)
                $id = $namespaceId;
        }
        $link  = $this->wikiLink($id, $isNamespace);
        $renderer->externallink($link);
    }

    public static function htmlMenuLi(array $item) : string
    {
        global $INFO;
        if ($item[Navigation::isNamespace])
        {
            $id = Ids::trimLeadingNamespaceSeparator($item[Navigation::id]);
            $open = $id &&
                    strpos($INFO[Navigation::id], $id) === 0;
            $class = $open ? 'open' : 'closed';
        }
        else
            $class = 'level'.$item[Navigation::level];
        $levelItem = $item[Navigation::levelItem];
        if ($levelItem)
            $class .= ' '.$levelItem;
        return '<li class="'.$class.'">';
    }

    public static function htmlMenuItem(array $item) : string
    {
        $levelItem = $item[Navigation::levelItem];
        if ($levelItem)
        {
            $levelItemName = $item[Navigation::levelItemName];
            $symbol = syntax_plugin_navigation::getLevelItemSymbol($levelItem);
            $result = "<span title='$levelItemName' style='display: inline-block; width: 2em; text-align: center;width: 2em'>$symbol</span>";
        }
        $result .= syntax_plugin_navigation::htmlMenuListItem($item, true);
        return $result;
    }

    public static function getLevelItemSymbol(string $levelItem)
    {
        switch ($levelItem)
        {
            case LevelItem::first:
                $symbol = "|←";
                break;
            case LevelItem::previous:
                $symbol = "←";
                break;
            case LevelItem::last:
                $symbol = "→|";
                break;
            case LevelItem::next:
                $symbol = "→";
                break;
            case LevelItem::inside:
                $symbol = "↓";
                break;
            case LevelItem::outside:
                $symbol = "↑";
                break;
            default:
                $symbol = '';
                break;
        }
        return $symbol;
    }

    public static function htmlListItem(array $item) : string
    {
        return syntax_plugin_navigation::htmlMenuListItem($item, false);
    }

    public static function htmlMenuListItem(array &$item, bool $showCurrent) : string
    {
        // global $ID;
        $id = $item[Navigation::id];
        $title = $item[Navigation::title];
        $result = html_wikilink($id, $title);
        // if (
        //     $showCurrent &&
        //     $ID === $id
        //     )
        // {
        //     $result = Html::Tag('strong', CSS::navigationCurrentItem, $result);
        // }
        return $result;
    }

    public function wikiLink($id, $namespace = false)
    {
        if ($namespace)
            $id = getNS($id);
        $link = wl($id, '', true);
        if ($namespace)
            $link .= '/';
        return $link;
    }

    public function renderLastTreeChange(Doku_Renderer $renderer, array &$data)
    {
        $time = $data[Metadata::date];
        $mode = $data[Parameter::mode];
        $renderer->doc .= Content::FormatTime($this, $time, $mode);
    }

    public function renderLevelItems(Doku_Renderer $renderer, array &$data)
    {
        foreach ($data as $index => $item)
        {
            if (!$item[Navigation::id])
            {
                unset($data[$index]);
                $reindex = true;
            }
        }
        if ($reindex)
            $data = array_values($data);
        syntax_plugin_navigation::clearLevelItemDuplicate($data, LevelItem::first, LevelItem::previous);
        syntax_plugin_navigation::clearLevelItemDuplicate($data, LevelItem::last, LevelItem::next);
        $this->renderTree($renderer, $data, false);
    }

    public static function getLevelItemIndex(array &$data, string $levelItem)
    {
        return array_search($levelItem, array_column($data, Navigation::levelItem));
    }

    public static function clearLevelItemDuplicate(array &$data, string $levelItem, string $levelItemDuplicate)
    {
        $index = syntax_plugin_navigation::getLevelItemIndex($data, $levelItem);
        if ($index === false)
            return;
        $duplicateIndex = syntax_plugin_navigation::getLevelItemIndex($data, $levelItemDuplicate);
        if ($duplicateIndex === false)
            return;
        if ($data[$index][Navigation::id] !== $data[$duplicateIndex][Navigation::id])
            return;
        unset($data[$duplicateIndex]);
        $data = array_values($data);
    }
}