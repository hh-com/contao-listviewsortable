<?php

if (defined('TL_MODE') && TL_MODE == 'BE')
{
    $GLOBALS['TL_HOOKS']['loadDataContainer']['listViewSortable'] = array('ListViewSortable','injectJavascript');
    $GLOBALS['TL_HOOKS']['executePostActions']['listViewSortable'] = array('ListViewSortable','resort');
}
    
?>