<?php
/**
 * Czech language file for navigation plugin
 *
 * @author Marek Ištvánek <marek.istvanek@gmail.com>
 */

@include('../../LangId.php');
@include('../../Config.php');

$lang[LangId::definitionPageTitle(Config::content)] = 'Obsah';
$lang[LangId::definitionPageTitle(Config::versions)] = 'Verze';

$lang[LangId::dateFormat] = 'j. n. Y';
$lang[LangId::timeFormat] = 'G:i';
$lang[LangId::dateTimeFormat] = $lang[LangId::dateFormat].' '.$lang[LangId::timeFormat];

$lang[LevelItem::next] = 'Následující';
$lang[LevelItem::previous] = 'Předchozí';
$lang[LevelItem::first] = 'První';
$lang[LevelItem::last] = 'Poslední';
$lang[LevelItem::inside] = 'Uvnitř';
$lang[LevelItem::outside] = 'Vně';
$lang[LevelItem::top] = 'Nahoru';
$lang[LevelItem::bottom] = 'Dolů';